<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Student.php';
require_once __DIR__ . '/../classes/Fee.php';
require_once __DIR__ . '/../classes/Mark.php';
require_once __DIR__ . '/../classes/Exam.php';

requireParent();

$pdo = db();

$studentObj = new Student($pdo);
$feeObj = new Fee($pdo);
$markObj = new Mark($pdo);
$examObj = new Exam($pdo);

$parentUserId = (int) ($_SESSION['user_id'] ?? 0);

/*
|--------------------------------------------------------------------------
| AUTHORIZATION
|--------------------------------------------------------------------------
| The child must be linked to THIS parent account — student_id from the
| query string is never trusted on its own.
*/

$studentId = request_int('student_id');

$child = $studentId > 0
    ? $studentObj->getAuthorizedChild($parentUserId, $studentId)
    : null;

if ($child === null) {
    http_response_code(403);
    denyAccess('This student is not linked to your parent account.');
}

$feeObj->refreshOverdue($studentId);

/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$attSummaryStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM attendance
    WHERE student_id = :sid
    GROUP BY status
");
$attSummaryStmt->execute([':sid' => $studentId]);

$attByStatus = [
    'present' => 0,
    'absent'  => 0,
    'late'    => 0,
    'leave'   => 0,
];

foreach ($attSummaryStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $attByStatus[(string) $row['status']] = (int) $row['total'];
}

$attTotal = array_sum($attByStatus);

$attPercent = $attTotal > 0
    ? round(($attByStatus['present'] / $attTotal) * 100, 1)
    : null;

$recentAttStmt = $pdo->prepare("
    SELECT a.date, a.status, sub.name AS subject_name
    FROM attendance a
    LEFT JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = :sid
    ORDER BY a.date DESC, a.id DESC
    LIMIT 15
");
$recentAttStmt->execute([':sid' => $studentId]);
$recentAttendance = $recentAttStmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| RESULTS (every exam of the child's class that has marks)
|--------------------------------------------------------------------------
*/

$examsStmt = $pdo->prepare("
    SELECT DISTINCT e.id, e.name, e.type, e.start_date, e.status
    FROM exams e
    INNER JOIN marks m ON m.exam_id = e.id
    WHERE m.student_id = :sid
    ORDER BY e.id DESC
");
$examsStmt->execute([':sid' => $studentId]);
$examRows = $examsStmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];

foreach ($examRows as $examRow) {
    $result = $markObj->calculateResult(
        (int) $examRow['id'],
        $studentId
    );

    if ($result !== null) {
        $results[] = [
            'exam'   => $examRow,
            'result' => $result,
        ];
    }
}

$latestResult = $results[0]['result'] ?? null;

/*
|--------------------------------------------------------------------------
| FEES
|--------------------------------------------------------------------------
*/

$feeRows = $feeObj->list('', '', (string) $studentId);

$feeBilled = 0.0;
$feePaid = 0.0;

foreach ($feeRows as $feeRow) {
    $feeBilled += (float) $feeRow['amount'];
    $feePaid += (float) $feeRow['paid_amount'];
}

$feeBalance = max($feeBilled - $feePaid, 0.0);

layout_start((string) $child['name'] . ' — Details', 'children');

$statusBadge = match ((string) ($child['status'] ?? '')) {
    'active'    => 'bg-success',
    'pending'   => 'bg-warning text-dark',
    'graduated' => 'bg-info text-dark',
    default     => 'bg-secondary',
};

?>

<!-- CHILD HEADER -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">

            <div>

                <div class="small text-muted mb-1">STUDENT PROFILE</div>

                <h1 class="h3 fw-bold mb-1">
                    <?= e((string) $child['name']) ?>
                </h1>

                <div class="text-muted">
                    <?= e((string) $child['student_id']) ?>
                    &middot;
                    <?= e((string) ($child['class_name'] ?? 'No class')) ?>
                    &mdash;
                    <?= e((string) ($child['section_name'] ?? 'No Section')) ?>
                </div>

            </div>

            <span class="badge <?= $statusBadge ?> fs-6">
                <?= e(ucfirst((string) ($child['status'] ?? ''))) ?>
            </span>

        </div>

        <div class="row g-3 mt-2 mb-0">

            <div class="col-6 col-md-3">
                <div class="text-muted small">Father's Name</div>
                <div class="fw-semibold">
                    <?= e((string) ($child['father_name'] ?? '—')) ?>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="text-muted small">Date of Birth</div>
                <div class="fw-semibold">
                    <?= e((string) ($child['date_of_birth'] ?? '—')) ?>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="text-muted small">Phone</div>
                <div class="fw-semibold">
                    <?= e((string) ($child['phone'] ?? '—')) ?>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="text-muted small">Email</div>
                <div class="fw-semibold">
                    <?= e((string) ($child['email'] ?? '—')) ?>
                </div>
            </div>

        </div>

    </div>

</div>


<!-- SUMMARY STATS -->

<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Attendance</div>
            <div class="fs-3 fw-bold">
                <?= $attPercent !== null
                    ? e(number_format($attPercent, 1)) . '%'
                    : '—' ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                <?= $attTotal ?> records
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Fee Balance</div>
            <div class="fs-3 fw-bold">
                <?= e(number_format($feeBalance, 2)) ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                billed <?= e(number_format($feeBilled, 2)) ?>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Latest Result</div>
            <div class="fs-3 fw-bold">
                <?= $latestResult !== null
                    ? e(number_format((float) $latestResult['percentage'], 1)) . '%'
                    : '—' ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                <?= $latestResult !== null
                    ? e((string) $latestResult['grade'] . ' · ' . $latestResult['status'])
                    : 'no published marks yet' ?>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card p-3 text-center">
            <div class="text-muted small">Exams With Marks</div>
            <div class="fs-3 fw-bold"><?= count($results) ?></div>
            <div class="text-muted" style="font-size:.75rem;">
                published so far
            </div>
        </div>
    </div>

</div>


<div class="row g-4">

    <!-- ATTENDANCE -->

    <div class="col-lg-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-bold mb-0">
                    <i class="bi bi-calendar-check me-2"></i>Attendance
                </h2>
            </div>

            <div class="card-body">

                <div class="d-flex flex-wrap gap-2 mb-3">

                    <span class="badge bg-success">
                        Present <?= $attByStatus['present'] ?>
                    </span>
                    <span class="badge bg-danger">
                        Absent <?= $attByStatus['absent'] ?>
                    </span>
                    <span class="badge bg-warning text-dark">
                        Late <?= $attByStatus['late'] ?>
                    </span>
                    <span class="badge bg-secondary">
                        Leave <?= $attByStatus['leave'] ?>
                    </span>

                </div>

                <?php if ($recentAttendance === []): ?>

                    <p class="text-muted mb-0">
                        No attendance has been recorded yet.
                    </p>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-sm table-hover mb-0 align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($recentAttendance as $rec): ?>

                                    <?php
                                    $recStatus = (string) $rec['status'];
                                    $recBadge = match ($recStatus) {
                                        'present' => 'bg-success',
                                        'absent'  => 'bg-danger',
                                        'late'    => 'bg-warning text-dark',
                                        default   => 'bg-secondary',
                                    };
                                    ?>

                                    <tr>
                                        <td><?= e((string) $rec['date']) ?></td>
                                        <td>
                                            <?= e((string) ($rec['subject_name'] ?? 'General')) ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $recBadge ?>">
                                                <?= e(ucfirst($recStatus)) ?>
                                            </span>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- FEES -->

    <div class="col-lg-6">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-bold mb-0">
                    <i class="bi bi-cash-coin me-2"></i>Fees
                </h2>
            </div>

            <div class="card-body">

                <?php if ($feeRows === []): ?>

                    <p class="text-muted mb-0">
                        No fees have been raised for this student yet.
                    </p>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-sm table-hover mb-0 align-middle">

                            <thead class="table-light">
                                <tr>
                                    <th>Fee</th>
                                    <th>Due Date</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Paid</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($feeRows as $feeRow): ?>

                                    <?php
                                    $feeStatus = (string) $feeRow['status'];
                                    $feeBadge = match ($feeStatus) {
                                        'paid'    => 'bg-success',
                                        'partial' => 'bg-info text-dark',
                                        'overdue' => 'bg-danger',
                                        default   => 'bg-warning text-dark',
                                    };
                                    $feeRemaining = max(
                                        (float) $feeRow['amount'] - (float) $feeRow['paid_amount'],
                                        0.0
                                    );
                                    ?>

                                    <tr>
                                        <td><?= e((string) $feeRow['fee_type']) ?></td>
                                        <td><?= e((string) $feeRow['due_date']) ?></td>
                                        <td class="text-end">
                                            <?= e(number_format((float) $feeRow['amount'], 2)) ?>
                                        </td>
                                        <td class="text-end">
                                            <?= e(number_format((float) $feeRow['paid_amount'], 2)) ?>

                                            <?php if ($feeRemaining > 0): ?>
                                                <div class="text-muted" style="font-size:.75rem;">
                                                    due <?= e(number_format($feeRemaining, 2)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $feeBadge ?>">
                                                <?= e(ucfirst($feeStatus)) ?>
                                            </span>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<!-- RESULTS -->

<div class="card shadow-sm border-0 mt-4">

    <div class="card-header bg-white py-3">
        <h2 class="h6 fw-bold mb-0">
            <i class="bi bi-graph-up me-2"></i>Examination Results
        </h2>
    </div>

    <div class="card-body p-0">

        <?php if ($results === []): ?>

            <p class="text-muted p-3 mb-0">
                No examination results have been published yet.
            </p>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover mb-0 align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>Exam</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th class="text-end">Obtained</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">%</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($results as $row): ?>

                            <tr>
                                <td class="fw-semibold">
                                    <?= e((string) $row['exam']['name']) ?>
                                </td>
                                <td>
                                    <?= e(ucfirst((string) $row['exam']['type'])) ?>
                                </td>
                                <td>
                                    <?= e((string) ($row['exam']['start_date'] ?? '—')) ?>
                                </td>
                                <td class="text-end">
                                    <?= e(number_format((float) $row['result']['obtained_marks'], 2)) ?>
                                </td>
                                <td class="text-end">
                                    <?= e(number_format((float) $row['result']['total_marks'], 2)) ?>
                                </td>
                                <td class="text-end">
                                    <?= e(number_format((float) $row['result']['percentage'], 1)) ?>
                                </td>
                                <td>
                                    <span class="badge bg-dark">
                                        <?= e((string) $row['result']['grade']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $resStatus = (string) $row['result']['status'];
                                    ?>
                                    <span class="badge <?= $resStatus === 'Pass' ? 'bg-success' : 'bg-danger' ?>">
                                        <?= e($resStatus) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>


<div class="mt-4">

    <a
        class="btn btn-outline-secondary"
        href="<?= e(url('parent/dashboard.php')) ?>"
    >
        <i class="bi bi-arrow-left me-1"></i>
        Back to Dashboard
    </a>

</div>

<?php layout_end(); ?>
