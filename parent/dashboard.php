<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Student.php';
require_once __DIR__ . '/../classes/Fee.php';
require_once __DIR__ . '/../classes/Mark.php';

requireParent();

$pdo = db();

$studentObj = new Student($pdo);
$feeObj = new Fee($pdo);
$markObj = new Mark($pdo);

/*
 * Keep overdue badges truthful (refreshOverdue() flips only
 * pending -> overdue and reverts stale overdue rows).
 */
$feeObj->refreshOverdue();

$parentUserId = (int) ($_SESSION['user_id'] ?? 0);

$children = $studentObj->getChildrenByParent($parentUserId);

/*
|--------------------------------------------------------------------------
| PER-CHILD SUMMARY
|--------------------------------------------------------------------------
*/

$summaries = [];
$aggregateAttendanceTotal = 0;
$aggregateAttendancePresent = 0;
$totalFeeBalance = 0.0;
$activeChildren = 0;

foreach ($children as $child) {

    $sid = (int) $child['id'];

    // --- Attendance ---
    $attStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END),
                0
            ) AS present
        FROM attendance
        WHERE student_id = :sid
    ");
    $attStmt->execute([':sid' => $sid]);
    $attRow = $attStmt->fetch(PDO::FETCH_ASSOC)
        ?: ['total' => 0, 'present' => 0];

    $attTotal = (int) $attRow['total'];
    $attPresent = (int) $attRow['present'];

    $attPercent = $attTotal > 0
        ? round(($attPresent / $attTotal) * 100, 1)
        : null;

    $aggregateAttendanceTotal += $attTotal;
    $aggregateAttendancePresent += $attPresent;

    // --- Fees ---
    $feeStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(f.amount), 0) AS billed,
            COALESCE((
                SELECT SUM(p.amount)
                FROM fee_payments p
                INNER JOIN fees f2 ON f2.id = p.fee_id
                WHERE f2.student_id = :sid_paid
            ), 0) AS paid
        FROM fees f
        WHERE f.student_id = :sid_fee
    ");
    $feeStmt->execute([
        ':sid_paid' => $sid,
        ':sid_fee'  => $sid,
    ]);
    $feeRow = $feeStmt->fetch(PDO::FETCH_ASSOC)
        ?: ['billed' => 0, 'paid' => 0];

    $feeBalance = max(
        (float) $feeRow['billed'] - (float) $feeRow['paid'],
        0.0
    );

    $totalFeeBalance += $feeBalance;

    // --- Latest exam result ---
    $latestExamStmt = $pdo->prepare("
        SELECT e.id, e.name
        FROM exams e
        INNER JOIN marks m ON m.exam_id = e.id
        WHERE m.student_id = :sid
        ORDER BY e.id DESC
        LIMIT 1
    ");
    $latestExamStmt->execute([':sid' => $sid]);
    $latestExam = $latestExamStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $latestResult = null;

    if ($latestExam !== null) {
        $latestResult = $markObj->calculateResult(
            (int) $latestExam['id'],
            $sid
        );
    }

    if (($child['status'] ?? '') === 'active') {
        $activeChildren++;
    }

    $summaries[$sid] = [
        'att_percent'  => $attPercent,
        'att_total'    => $attTotal,
        'fee_balance'  => $feeBalance,
        'latest_exam'  => $latestExam,
        'latest_result'=> $latestResult,
    ];
}

$overallAttendancePercent = $aggregateAttendanceTotal > 0
    ? round(
        ($aggregateAttendancePresent / $aggregateAttendanceTotal) * 100,
        1
    )
    : null;

layout_start('Parent Dashboard', 'dashboard');

?>

<!-- WELCOME -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body p-4">

        <div class="small text-muted mb-1">PARENT PORTAL</div>

        <h1 class="h3 fw-bold mb-1">
            Welcome,
            <?= e((string) ($_SESSION['user_name'] ?? 'Parent')) ?>
        </h1>

        <p class="text-muted mb-0">
            Follow your children's attendance, examination results
            and fee balances from one place.
        </p>

    </div>

</div>


<!-- SUMMARY STATS -->

<div class="row g-3 mb-4">

    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 text-center">
            <div class="text-muted small">Children</div>
            <div class="fs-3 fw-bold"><?= count($children) ?></div>
            <div class="text-muted" style="font-size:.75rem;">
                <?= $activeChildren ?> active
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 text-center">
            <div class="text-muted small">Overall Attendance</div>
            <div class="fs-3 fw-bold">
                <?= $overallAttendancePercent !== null
                    ? e(number_format($overallAttendancePercent, 1)) . '%'
                    : '—' ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                <?= $aggregateAttendanceTotal ?> records
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 text-center">
            <div class="text-muted small">Outstanding Fees</div>
            <div class="fs-3 fw-bold">
                <?= e(number_format($totalFeeBalance, 2)) ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                across all children
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3 text-center">
            <div class="text-muted small">Latest Results</div>
            <div class="fs-3 fw-bold">
                <?php
                $resultCount = 0;
                foreach ($summaries as $s) {
                    if ($s['latest_result'] !== null) {
                        $resultCount++;
                    }
                }
                echo $resultCount;
                ?>
            </div>
            <div class="text-muted" style="font-size:.75rem;">
                exams with published marks
            </div>
        </div>
    </div>

</div>


<!-- CHILDREN -->

<div class="card shadow-sm border-0" id="children">

    <div class="card-header bg-white py-3">
        <h2 class="h6 fw-bold mb-0">
            <i class="bi bi-people me-2"></i>My Children
        </h2>
    </div>

    <div class="card-body p-0">

        <?php if ($children === []): ?>

            <div class="text-center text-muted py-5 px-3">

                <i class="bi bi-people fs-1 d-block mb-3"></i>

                <p class="mb-1 fw-semibold">
                    No children are linked to your account yet.
                </p>

                <p class="mb-0" style="font-size:.9rem;">
                    Please contact the school administration to link
                    your parent account to your child's record.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover mb-0 align-middle">

                    <thead class="table-light">
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class &amp; Section</th>
                            <th>Status</th>
                            <th class="text-end">Attendance</th>
                            <th class="text-end">Fee Balance</th>
                            <th class="text-end">Latest Result</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($children as $child): ?>

                            <?php
                            $sid = (int) $child['id'];
                            $sum = $summaries[$sid];
                            ?>

                            <tr>

                                <td><?= e((string) $child['student_id']) ?></td>

                                <td class="fw-semibold">
                                    <?= e((string) $child['name']) ?>
                                </td>

                                <td>
                                    <?= e((string) ($child['class_name'] ?? '—')) ?>
                                    —
                                    <?= e((string) ($child['section_name'] ?? 'No Section')) ?>
                                </td>

                                <td>
                                    <?php
                                    $st = (string) ($child['status'] ?? '');
                                    $badge = $st === 'active'
                                        ? 'bg-success'
                                        : ($st === 'pending' ? 'bg-warning text-dark' : 'bg-secondary');
                                    ?>
                                    <span class="badge <?= $badge ?>">
                                        <?= e(ucfirst($st)) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <?= $sum['att_percent'] !== null
                                        ? e(number_format($sum['att_percent'], 1)) . '%'
                                        : '—' ?>
                                </td>

                                <td class="text-end">
                                    <?= e(number_format((float) $sum['fee_balance'], 2)) ?>
                                </td>

                                <td class="text-end">
                                    <?php if ($sum['latest_result'] !== null): ?>
                                        <?= e(number_format((float) $sum['latest_result']['percentage'], 1)) ?>%
                                        <span class="text-muted">
                                            (<?= e((string) $sum['latest_result']['status']) ?>)
                                        </span>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="<?= e(url('parent/child.php?student_id=' . $sid)) ?>"
                                    >
                                        Details
                                    </a>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<?php layout_end(); ?>
