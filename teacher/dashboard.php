<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Assignment.php';
require_once __DIR__ . '/../classes/Attendance.php';

requireTeacher();

$pdo = db();

/*
|--------------------------------------------------------------------------
| CURRENT TEACHER
|--------------------------------------------------------------------------
*/

$teacher = current_teacher($pdo);

if (!$teacher) {

    layout_start('Teacher Dashboard', 'dashboard');

    echo '
        <div class="alert alert-warning">
            Your teacher profile is not linked to this account yet.
            Please contact an administrator.
        </div>
    ';

    layout_end();
    exit;
}

/*
|--------------------------------------------------------------------------
| TEACHER DATA
|--------------------------------------------------------------------------
*/

$teacherId = (int) $teacher['id'];

$teacherName = $teacher['name'] ?? $teacher['teacher_name'] ?? 'Teacher';
$teacherCode = $teacher['teacher_id'] ?? 'N/A';
$teacherEmail = $teacher['email'] ?? '';

/*
|--------------------------------------------------------------------------
| ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$assignment = new Assignment($pdo);

$assignments = $assignment->getTeacherAssignments($teacherId);

if (!is_array($assignments)) {
    $assignments = [];
}

/*
|--------------------------------------------------------------------------
| ATTENDANCE
|--------------------------------------------------------------------------
*/

$attendance = new Attendance($pdo);

/*
|--------------------------------------------------------------------------
| TODAY'S ATTENDANCE COUNT
|--------------------------------------------------------------------------
*/

$todayCountStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE marked_by = :teacher_id
      AND date = CURDATE()
");

$todayCountStmt->execute([
    ':teacher_id' => $teacherId
]);

$todayAttendanceCount = (int) $todayCountStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| TOTAL ATTENDANCE RECORDS
|--------------------------------------------------------------------------
*/

$totalAttendanceStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM attendance
    WHERE marked_by = :teacher_id
");

$totalAttendanceStmt->execute([
    ':teacher_id' => $teacherId
]);

$totalAttendanceCount = (int) $totalAttendanceStmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| CHART DATA — attendance status distribution + assignment statuses
|--------------------------------------------------------------------------
*/

$attendanceStatusStmt = $pdo->prepare("
    SELECT status, COUNT(*) AS total
    FROM attendance
    WHERE marked_by = :teacher_id
    GROUP BY status
");

$attendanceStatusStmt->execute([
    ':teacher_id' => $teacherId
]);

$attendanceStatusRows = $attendanceStatusStmt->fetchAll(PDO::FETCH_ASSOC);

$assignmentStatusCounts = [];

foreach ($assignments as $assignmentRow) {
    $assignmentStatus = (string) ($assignmentRow['status'] ?? 'unknown');
    $assignmentStatusCounts[$assignmentStatus] = ($assignmentStatusCounts[$assignmentStatus] ?? 0) + 1;
}

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

layout_start('Teacher Dashboard', 'dashboard');

?>

<!-- ============================================================
     WELCOME HEADER
============================================================ -->

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">

        <div class="d-flex flex-column flex-md-row
                    justify-content-between
                    align-items-md-center gap-3">

            <div>

                <div class="text-muted small mb-1">
                    Teacher Portal
                </div>

                <h2 class="fw-bold mb-1">
                    Welcome, <?= e($teacherName) ?> 👋
                </h2>

                <p class="text-muted mb-0">
                    Manage your assignments, attendance and teaching activities.
                </p>

            </div>

            <div class="text-md-end">

                <div class="small text-muted">
                    Teacher ID
                </div>

                <div class="fw-bold fs-5">
                    <?= e($teacherCode) ?>
                </div>

            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     STATISTICS
============================================================ -->

<div class="row g-3 mb-4">

    <!-- ASSIGNMENTS -->

    <div class="col-md-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small mb-1">
                            My Assignments
                        </div>

                        <div class="fs-2 fw-bold">
                            <?= count($assignments) ?>
                        </div>

                    </div>

                    <div class="fs-3">
                        📝
                    </div>

                </div>

                <div class="small text-muted mt-2">
                    Assignments created by you
                </div>

            </div>

        </div>

    </div>


    <!-- TODAY ATTENDANCE -->

    <div class="col-md-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small mb-1">
                            Today's Attendance
                        </div>

                        <div class="fs-2 fw-bold">
                            <?= $todayAttendanceCount ?>
                        </div>

                    </div>

                    <div class="fs-3">
                        📅
                    </div>

                </div>

                <div class="small text-muted mt-2">
                    Records marked today
                </div>

            </div>

        </div>

    </div>


    <!-- TOTAL ATTENDANCE -->

    <div class="col-md-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small mb-1">
                            Attendance Records
                        </div>

                        <div class="fs-2 fw-bold">
                            <?= $totalAttendanceCount ?>
                        </div>

                    </div>

                    <div class="fs-3">
                        📊
                    </div>

                </div>

                <div class="small text-muted mt-2">
                    Total records marked by you
                </div>

            </div>

        </div>

    </div>


    <!-- TEACHER ID -->

    <div class="col-md-6 col-xl-3">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <div class="d-flex justify-content-between align-items-start">

                    <div>

                        <div class="text-muted small mb-1">
                            Teacher ID
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= e($teacherCode) ?>
                        </div>

                    </div>

                    <div class="fs-3">
                        👨‍🏫
                    </div>

                </div>

                <div class="small text-muted mt-2">
                    Your teaching account
                </div>

            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     QUICK ACTIONS
============================================================ -->

<div class="card border-0 shadow-sm mb-4">

    <div class="card-body p-4">

        <h5 class="fw-bold mb-3">
            Quick Actions
        </h5>

        <div class="d-flex flex-wrap gap-2">

            <a
                class="btn btn-primary"
                href="<?= e(url('teacher/assignments.php')) ?>"
            >
                📝 Manage Assignments
            </a>

            <!-- IMPORTANT:
                 Teacher must not access admin attendance page.
            -->

            <a
                class="btn btn-outline-primary"
                href="<?= e(url('teacher/attendance.php')) ?>"
            >
                📅 Mark Attendance
            </a>

        </div>

    </div>

</div>


<!-- ============================================================
     CHARTS
=========================================================== -->

<div class="row g-3 mb-4">

    <div class="col-lg-6">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <h5 class="fw-bold mb-3">
                    Attendance Status
                </h5>

                <canvas id="teacherAttendanceChart" height="170"></canvas>

            </div>

        </div>

    </div>

    <div class="col-lg-6">

        <div class="card border-0 shadow-sm h-100">

            <div class="card-body p-4">

                <h5 class="fw-bold mb-3">
                    My Assignments
                </h5>

                <canvas id="teacherAssignmentsChart" height="170"></canvas>

            </div>

        </div>

    </div>

</div>


<!-- ============================================================
     TEACHER INFORMATION
============================================================ -->

<div class="card border-0 shadow-sm">

    <div class="card-body p-4">

        <h5 class="fw-bold mb-3">
            My Profile
        </h5>

        <div class="row g-3">

            <div class="col-md-4">

                <div class="text-muted small">
                    Name
                </div>

                <div class="fw-semibold">
                    <?= e($teacherName) ?>
                </div>

            </div>


            <div class="col-md-4">

                <div class="text-muted small">
                    Teacher ID
                </div>

                <div class="fw-semibold">
                    <?= e($teacherCode) ?>
                </div>

            </div>


            <div class="col-md-4">

                <div class="text-muted small">
                    Email
                </div>

                <div class="fw-semibold">
                    <?= e($teacherEmail ?: 'Not available') ?>
                </div>

            </div>

        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    var attLabels = <?= json_encode(array_map(static function ($row) {
        $map = ['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'leave' => 'Leave'];
        return $map[(string) $row['status']] ?? ucfirst((string) $row['status']);
    }, $attendanceStatusRows)) ?>;
    var attTotals = <?= json_encode(array_map(static fn ($row) => (int) $row['total'], $attendanceStatusRows)) ?>;
    var el1 = document.getElementById('teacherAttendanceChart');

    if (el1) {
        if (attTotals.length > 0) {
            new Chart(el1, {
                type: 'doughnut',
                data: {
                    labels: attLabels,
                    datasets: [{
                        data: attTotals,
                        backgroundColor: ['#16a34a', '#dc2626', '#f59e0b', '#6366f1']
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        } else {
            el1.replaceWith(Object.assign(document.createElement('p'), {
                className: 'text-muted small mb-0',
                textContent: 'No attendance marked yet.'
            }));
        }
    }

    var asgLabels = <?= json_encode(array_keys($assignmentStatusCounts)) ?>;
    var asgTotals = <?= json_encode(array_values($assignmentStatusCounts)) ?>;
    var el2 = document.getElementById('teacherAssignmentsChart');

    if (el2) {
        if (asgTotals.length > 0) {
            new Chart(el2, {
                type: 'doughnut',
                data: {
                    labels: asgLabels,
                    datasets: [{
                        data: asgTotals,
                        backgroundColor: ['#16a34a', '#dc2626']
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        } else {
            el2.replaceWith(Object.assign(document.createElement('p'), {
                className: 'text-muted small mb-0',
                textContent: 'No assignments yet.'
            }));
        }
    }
})();
</script>
<?php

layout_end();