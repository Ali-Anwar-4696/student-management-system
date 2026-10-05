<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Fee.php';
require_once __DIR__ . '/../../classes/Mark.php';
require_once __DIR__ . '/../../classes/Exam.php';

requireAdmin();

$pdo = db();

$feeManager = new Fee($pdo);
$markManager = new Mark($pdo);
$examManager = new Exam($pdo);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$reportType = trim((string) ($_GET['report'] ?? 'overview'));

$allowedReports = [
    'overview',
    'students',
    'attendance',
    'fees',
    'results',
];

if (!in_array($reportType, $allowedReports, true)) {
    $reportType = 'overview';
}

$classId = isset($_GET['class_id'])
    && ctype_digit((string) $_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;

$examId = isset($_GET['exam_id'])
    && ctype_digit((string) $_GET['exam_id'])
    ? (int) $_GET['exam_id']
    : 0;

$feeStatus = trim((string) ($_GET['fee_status'] ?? ''));

if (!in_array(
    $feeStatus,
    ['pending', 'partial', 'paid', 'overdue'],
    true
)) {
    $feeStatus = '';
}

$attendanceFrom = trim(
    (string) ($_GET['attendance_from'] ?? date('Y-m-01'))
);

$attendanceTo = trim(
    (string) ($_GET['attendance_to'] ?? date('Y-m-d'))
);

$search = trim((string) ($_GET['search'] ?? ''));

/*
|--------------------------------------------------------------------------
| Refresh overdue fees
|--------------------------------------------------------------------------
*/

$feeManager->refreshOverdue();

/*
|--------------------------------------------------------------------------
| Common Data
|--------------------------------------------------------------------------
*/

$classes = $pdo->query("
    SELECT id, name
    FROM classes
    ORDER BY name ASC
")->fetchAll();

$exams = $examManager->list('', '', '');

/*
|--------------------------------------------------------------------------
| Overview Statistics
|--------------------------------------------------------------------------
*/

$totalStudents = (int) $pdo->query("
    SELECT COUNT(*)
    FROM students
    WHERE status = 'active'
")->fetchColumn();

$totalTeachers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM teachers
    WHERE status = 'active'
")->fetchColumn();

$totalClasses = (int) $pdo->query("
    SELECT COUNT(*)
    FROM classes
")->fetchColumn();

$totalSubjects = (int) $pdo->query("
    SELECT COUNT(*)
    FROM subjects
    WHERE status = 'active'
")->fetchColumn();

$totalExams = (int) $pdo->query("
    SELECT COUNT(*)
    FROM exams
")->fetchColumn();

$feeSummary = $feeManager->summary();

$totalAssignments = (int) $pdo->query("
    SELECT COUNT(*)
    FROM assignments
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| Class Student Report
|--------------------------------------------------------------------------
*/

$classReport = [];

if ($classId > 0) {

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name AS class_name,
            COUNT(st.id) AS total_students,
            SUM(
                CASE
                    WHEN st.status = 'active' THEN 1
                    ELSE 0
                END
            ) AS active_students,
            SUM(
                CASE
                    WHEN st.status != 'active' THEN 1
                    ELSE 0
                END
            ) AS inactive_students
        FROM classes c
        LEFT JOIN students st
            ON st.class_id = c.id
        WHERE c.id = :class_id
        GROUP BY c.id, c.name
    ");

    $stmt->execute([
        ':class_id' => $classId
    ]);

    $classReport = $stmt->fetch() ?: [];
}

/*
|--------------------------------------------------------------------------
| Student Report
|--------------------------------------------------------------------------
*/

$studentRows = [];

if ($reportType === 'students') {

    $sql = "
        SELECT
            st.id,
            st.student_id,
            st.name,
            st.status,
            c.name AS class_name,
            sec.name AS section_name
        FROM students st
        INNER JOIN classes c
            ON st.class_id = c.id
        LEFT JOIN sections sec
            ON st.section_id = sec.id
        WHERE 1 = 1
    ";

    $params = [];

    if ($classId > 0) {
        $sql .= " AND st.class_id = :class_id";
        $params[':class_id'] = $classId;
    }

    if ($search !== '') {
        $sql .= "
            AND (
                st.name LIKE :search
                OR st.student_id LIKE :search2
            )
        ";

        $like = '%' . $search . '%';

        $params[':search'] = $like;
        $params[':search2'] = $like;
    }

    $sql .= "
        ORDER BY c.name ASC, st.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $studentRows = $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| Attendance Report
|--------------------------------------------------------------------------
*/

$attendanceRows = [];
$attendanceSummary = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'late' => 0,
];

if ($reportType === 'attendance') {

    $stmt = $pdo->prepare("
        SELECT
            st.student_id,
            st.name,
            c.name AS class_name,
            sec.name AS section_name,

            COUNT(a.id) AS total_days,

            SUM(
                CASE
                    WHEN a.status = 'present' THEN 1
                    ELSE 0
                END
            ) AS present_days,

            SUM(
                CASE
                    WHEN a.status = 'absent' THEN 1
                    ELSE 0
                END
            ) AS absent_days,

            SUM(
                CASE
                    WHEN a.status = 'late' THEN 1
                    ELSE 0
                END
            ) AS late_days

        FROM students st

        INNER JOIN classes c
            ON st.class_id = c.id

        LEFT JOIN sections sec
            ON st.section_id = sec.id

        LEFT JOIN attendance a
            ON a.student_id = st.id
            AND a.date BETWEEN :date_from AND :date_to

        WHERE st.status = 'active'
    ");

    $params = [
        ':date_from' => $attendanceFrom,
        ':date_to' => $attendanceTo,
    ];

    if ($classId > 0) {
        $stmt = null;

        $sql = "
            SELECT
                st.student_id,
                st.name,
                c.name AS class_name,
                sec.name AS section_name,

                COUNT(a.id) AS total_days,

                SUM(
                    CASE
                        WHEN a.status = 'present' THEN 1
                        ELSE 0
                    END
                ) AS present_days,

                SUM(
                    CASE
                        WHEN a.status = 'absent' THEN 1
                        ELSE 0
                    END
                ) AS absent_days,

                SUM(
                    CASE
                        WHEN a.status = 'late' THEN 1
                        ELSE 0
                    END
                ) AS late_days

            FROM students st

            INNER JOIN classes c
                ON st.class_id = c.id

            LEFT JOIN sections sec
                ON st.section_id = sec.id

            LEFT JOIN attendance a
                ON a.student_id = st.id
                AND a.date BETWEEN :date_from AND :date_to

            WHERE st.status = 'active'
              AND st.class_id = :class_id

            GROUP BY
                st.id,
                st.student_id,
                st.name,
                c.name,
                sec.name

            ORDER BY
                c.name ASC,
                st.name ASC
        ";

        $stmt = $pdo->prepare($sql);

        $params[':class_id'] = $classId;

    } else {

        $sql = "
            SELECT
                st.student_id,
                st.name,
                c.name AS class_name,
                sec.name AS section_name,

                COUNT(a.id) AS total_days,

                SUM(
                    CASE
                        WHEN a.status = 'present' THEN 1
                        ELSE 0
                    END
                ) AS present_days,

                SUM(
                    CASE
                        WHEN a.status = 'absent' THEN 1
                        ELSE 0
                    END
                ) AS absent_days,

                SUM(
                    CASE
                        WHEN a.status = 'late' THEN 1
                        ELSE 0
                    END
                ) AS late_days

            FROM students st

            INNER JOIN classes c
                ON st.class_id = c.id

            LEFT JOIN sections sec
                ON st.section_id = sec.id

            LEFT JOIN attendance a
                ON a.student_id = st.id
                AND a.date BETWEEN :date_from AND :date_to

            WHERE st.status = 'active'

            GROUP BY
                st.id,
                st.student_id,
                st.name,
                c.name,
                sec.name

            ORDER BY
                c.name ASC,
                st.name ASC
        ";

        $stmt = $pdo->prepare($sql);
    }

    $stmt->execute($params);

    $attendanceRows = $stmt->fetchAll();

    foreach ($attendanceRows as &$row) {

        $total = (int) $row['total_days'];
        $present = (int) $row['present_days'];
        $absent = (int) $row['absent_days'];
        $late = (int) $row['late_days'];

        $row['percentage'] = $total > 0
            ? round(($present / $total) * 100, 2)
            : 0;

        $attendanceSummary['total'] += $total;
        $attendanceSummary['present'] += $present;
        $attendanceSummary['absent'] += $absent;
        $attendanceSummary['late'] += $late;
    }

    unset($row);
}

/*
|--------------------------------------------------------------------------
| Fee Report
|--------------------------------------------------------------------------
*/

$feeRows = [];

$feeReportSummary = [
    'billed' => 0,
    'paid' => 0,
    'outstanding' => 0,
];

if ($reportType === 'fees') {

    $feeRows = $feeManager->list(
        $search,
        $feeStatus,
        $classId > 0 ? '' : ''
    );

    /*
    |--------------------------------------------------------------------------
    | Filter fees by class if selected
    |--------------------------------------------------------------------------
    */

    if ($classId > 0) {

        $stmt = $pdo->prepare("
            SELECT
                f.*,
                s.name AS student_name,
                s.student_id AS student_code,
                c.name AS class_name,

                COALESCE(
                    (
                        SELECT SUM(p.amount)
                        FROM fee_payments p
                        WHERE p.fee_id = f.id
                    ),
                    0
                ) AS paid_amount

            FROM fees f

            INNER JOIN students s
                ON f.student_id = s.id

            INNER JOIN classes c
                ON s.class_id = c.id

            WHERE s.class_id = :class_id
        ");

        $params = [
            ':class_id' => $classId
        ];

        if ($feeStatus !== '') {

            $stmt = null;

            $sql = "
                SELECT
                    f.*,
                    s.name AS student_name,
                    s.student_id AS student_code,
                    c.name AS class_name,

                    COALESCE(
                        (
                            SELECT SUM(p.amount)
                            FROM fee_payments p
                            WHERE p.fee_id = f.id
                        ),
                        0
                    ) AS paid_amount

                FROM fees f

                INNER JOIN students s
                    ON f.student_id = s.id

                INNER JOIN classes c
                    ON s.class_id = c.id

                WHERE s.class_id = :class_id
                  AND f.status = :status

                ORDER BY f.due_date DESC, f.id DESC
            ";

            $stmt = $pdo->prepare($sql);

            $params[':status'] = $feeStatus;

        } else {

            $sql = "
                SELECT
                    f.*,
                    s.name AS student_name,
                    s.student_id AS student_code,
                    c.name AS class_name,

                    COALESCE(
                        (
                            SELECT SUM(p.amount)
                            FROM fee_payments p
                            WHERE p.fee_id = f.id
                        ),
                        0
                    ) AS paid_amount

                FROM fees f

                INNER JOIN students s
                    ON f.student_id = s.id

                INNER JOIN classes c
                    ON s.class_id = c.id

                WHERE s.class_id = :class_id

                ORDER BY f.due_date DESC, f.id DESC
            ";

            $stmt = $pdo->prepare($sql);
        }

        $stmt->execute($params);

        $feeRows = $stmt->fetchAll();
    }

    foreach ($feeRows as &$feeRow) {

        $amount = (float) $feeRow['amount'];
        $paid = (float) $feeRow['paid_amount'];

        $feeRow['remaining'] = max(
            $amount - $paid,
            0
        );

        $feeReportSummary['billed'] += $amount;
        $feeReportSummary['paid'] += $paid;
        $feeReportSummary['outstanding'] += $feeRow['remaining'];
    }

    unset($feeRow);
}

/*
|--------------------------------------------------------------------------
| Result Report
|--------------------------------------------------------------------------
*/

$resultRows = [];

$resultSummary = [
    'students' => 0,
    'passed' => 0,
    'failed' => 0,
    'average' => 0,
];

if ($reportType === 'results' && $examId > 0) {

    $selectedExam = $examManager->getById($examId);

    if ($selectedExam !== null) {

        $stmt = $pdo->prepare("
            SELECT
                st.id,
                st.student_id,
                st.name,
                c.name AS class_name,
                sec.name AS section_name
            FROM students st
            INNER JOIN classes c
                ON st.class_id = c.id
            LEFT JOIN sections sec
                ON st.section_id = sec.id
            WHERE st.class_id = :class_id
              AND st.status = 'active'
            ORDER BY st.name ASC
        ");

        $stmt->execute([
            ':class_id' => $selectedExam['class_id']
        ]);

        $studentsForResult = $stmt->fetchAll();

        $percentageTotal = 0;

        foreach ($studentsForResult as $student) {

            $result = $markManager->calculateResult(
                $examId,
                (int) $student['id']
            );

            if ($result === null) {
                continue;
            }

            $resultRows[] = [
                'student' => $student,
                'result' => $result,
            ];

            $resultSummary['students']++;

            $percentageTotal += (float) $result['percentage'];

            if ($result['status'] === 'Pass') {
                $resultSummary['passed']++;
            } else {
                $resultSummary['failed']++;
            }
        }

        if ($resultSummary['students'] > 0) {

            $resultSummary['average'] = round(
                $percentageTotal / $resultSummary['students'],
                2
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| CSV EXPORT
|--------------------------------------------------------------------------
|
| ?export=csv streams the currently selected report (report=...) using
| the exact same filters that were applied above. Read-only download,
| so GET is fine — requireAdmin() at the top still gates access.
|
*/

if (trim((string) ($_GET['export'] ?? '')) === 'csv') {

    require_once __DIR__ . '/../../includes/export.php';

    $exportName = 'report_' . $reportType . '_' . date('Ymd_His') . '.csv';

    switch ($reportType) {

        case 'students':

            export_csv($exportName, [
                'Student ID',
                'Name',
                'Class',
                'Section',
                'Status',
            ], (static function () use ($studentRows): iterable {
                foreach ($studentRows as $row) {
                    yield [
                        $row['student_id'] ?? '',
                        $row['name'] ?? '',
                        $row['class_name'] ?? '',
                        $row['section_name'] ?? '',
                        $row['status'] ?? '',
                    ];
                }
            })());

            break;

        case 'attendance':

            export_csv($exportName, [
                'Student ID',
                'Name',
                'Class',
                'Section',
                'Total Days',
                'Present',
                'Absent',
                'Late',
                'Attendance %',
            ], (static function () use ($attendanceRows): iterable {
                foreach ($attendanceRows as $row) {
                    yield [
                        $row['student_id'] ?? '',
                        $row['name'] ?? '',
                        $row['class_name'] ?? '',
                        $row['section_name'] ?? '',
                        $row['total_days'] ?? 0,
                        $row['present_days'] ?? 0,
                        $row['absent_days'] ?? 0,
                        $row['late_days'] ?? 0,
                        $row['percentage'] ?? 0,
                    ];
                }
            })());

            break;

        case 'fees':

            export_csv($exportName, [
                'Student ID',
                'Student Name',
                'Class',
                'Fee Type',
                'Amount',
                'Paid',
                'Remaining',
                'Due Date',
                'Status',
            ], (static function () use ($feeRows): iterable {
                foreach ($feeRows as $row) {
                    yield [
                        $row['student_code'] ?? '',
                        $row['student_name'] ?? '',
                        $row['class_name'] ?? '',
                        $row['fee_type'] ?? '',
                        $row['amount'] ?? 0,
                        $row['paid_amount'] ?? 0,
                        $row['remaining'] ?? 0,
                        $row['due_date'] ?? '',
                        $row['status'] ?? '',
                    ];
                }
            })());

            break;

        case 'results':

            export_csv($exportName, [
                'Student ID',
                'Name',
                'Class',
                'Section',
                'Total Marks',
                'Obtained Marks',
                'Percentage',
                'Grade',
                'Status',
            ], (static function () use ($resultRows): iterable {
                foreach ($resultRows as $row) {
                    $student = $row['student'];
                    $result = $row['result'];

                    yield [
                        $student['student_id'] ?? '',
                        $student['name'] ?? '',
                        $student['class_name'] ?? '',
                        $student['section_name'] ?? '',
                        $result['total_marks'] ?? 0,
                        $result['obtained_marks'] ?? 0,
                        $result['percentage'] ?? 0,
                        $result['grade'] ?? '',
                        $result['status'] ?? '',
                    ];
                }
            })());

            break;

        default:

            // overview — metric / value pairs.
            $overviewRows = [
                ['Active students', $totalStudents],
                ['Active teachers', $totalTeachers],
                ['Classes', $totalClasses],
                ['Active subjects', $totalSubjects],
                ['Exams', $totalExams],
                ['Assignments', $totalAssignments],
                ['Fees billed', $feeSummary['billed'] ?? 0],
                ['Fees collected', $feeSummary['collected'] ?? 0],
                ['Fees outstanding', $feeSummary['outstanding'] ?? 0],
            ];

            if ($classReport !== []) {
                $overviewRows[] = ['Class report — class', $classReport['class_name'] ?? ''];
                $overviewRows[] = ['Class report — total students', $classReport['total_students'] ?? 0];
                $overviewRows[] = ['Class report — active students', $classReport['active_students'] ?? 0];
                $overviewRows[] = ['Class report — inactive students', $classReport['inactive_students'] ?? 0];
            }

            export_csv($exportName, ['Metric', 'Value'], $overviewRows);

            break;
    }

    // export_csv() exits.
}

layout_start('Reports', 'reports');

?>

<style>

    .report-hero {
        border-radius: 18px;
        background: linear-gradient(
            135deg,
            #1e3a8a 0%,
            #172554 100%
        );
        color: #fff;
        overflow: hidden;
        position: relative;
    }

    .report-hero::after {
        content: "";
        position: absolute;
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
        right: -90px;
        top: -120px;
    }

    .report-card {
        border: 0;
        border-radius: 17px;
    }

    .report-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #2563eb;
        font-size: 23px;
    }

    .summary-box {
        border: 1px solid rgba(0,0,0,.07);
        border-radius: 15px;
        background: #fff;
        padding: 18px;
        height: 100%;
    }

    .report-table th {
        white-space: nowrap;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .report-table td {
        vertical-align: middle;
    }

    .empty-state {
        padding: 65px 20px;
        text-align: center;
    }

    .empty-icon {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        background: #f1f5f9;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
    }

    .progress {
        height: 7px;
        border-radius: 10px;
    }

    @media print {

        .app-sidebar,
        .app-topbar,
        .no-print,
        .btn {
            display: none !important;
        }

        .app-main {
            margin: 0 !important;
        }

        .app-content {
            padding: 0 !important;
        }

        .card {
            box-shadow: none !important;
        }
    }

</style>


<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">

    <div>

        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-graph-up-arrow me-2"></i>
            Reports
        </h1>

        <p class="text-muted mb-0">
            Generate student, attendance, fee and examination reports.
        </p>

    </div>

    <div class="d-flex gap-2">

        <a
            class="btn btn-outline-success"
            href="<?= e('?' . http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>"
            title="Download the current report as CSV"
        >
            <i class="bi bi-download me-2"></i>
            Export CSV
        </a>

        <button
            type="button"
            class="btn btn-outline-primary"
            onclick="window.print()"
        >
            <i class="bi bi-printer me-2"></i>
            Print Report
        </button>

    </div>

</div>


<!-- =========================================================
     REPORT SELECTOR
========================================================= -->

<div class="card shadow-sm border-0 mb-4 no-print">

    <div class="card-body p-4">

        <div class="row g-3 align-items-end">

            <div class="col-lg-4">

                <label class="form-label fw-semibold">
                    Report Type
                </label>

                <select
                    id="reportType"
                    class="form-select form-select-lg"
                >

                    <option
                        value="overview"
                        <?= $reportType === 'overview' ? 'selected' : '' ?>
                    >
                        Overview Report
                    </option>

                    <option
                        value="students"
                        <?= $reportType === 'students' ? 'selected' : '' ?>
                    >
                        Student Report
                    </option>

                    <option
                        value="attendance"
                        <?= $reportType === 'attendance' ? 'selected' : '' ?>
                    >
                        Attendance Report
                    </option>

                    <option
                        value="fees"
                        <?= $reportType === 'fees' ? 'selected' : '' ?>
                    >
                        Fee Report
                    </option>

                    <option
                        value="results"
                        <?= $reportType === 'results' ? 'selected' : '' ?>
                    >
                        Examination Result Report
                    </option>

                </select>

            </div>


            <div class="col-lg-3">

                <label class="form-label fw-semibold">
                    Class
                </label>

                <select
                    id="classSelector"
                    class="form-select"
                >

                    <option value="">
                        All Classes
                    </option>

                    <?php foreach ($classes as $class): ?>

                        <option
                            value="<?= (int) $class['id'] ?>"
                            <?= $classId === (int) $class['id'] ? 'selected' : '' ?>
                        >
                            <?= e($class['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-lg-3">

                <label class="form-label fw-semibold">
                    Examination
                </label>

                <select
                    id="examSelector"
                    class="form-select"
                >

                    <option value="">
                        Select exam
                    </option>

                    <?php foreach ($exams as $exam): ?>

                        <option
                            value="<?= (int) $exam['id'] ?>"
                            <?= $examId === (int) $exam['id'] ? 'selected' : '' ?>
                        >
                            <?= e($exam['name']) ?>
                            — <?= e($exam['class_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-lg-2">

                <button
                    type="button"
                    class="btn btn-primary btn-lg w-100"
                    onclick="openReport()"
                >
                    <i class="bi bi-bar-chart me-2"></i>
                    Generate
                </button>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     OVERVIEW
========================================================= -->

<?php if ($reportType === 'overview'): ?>

    <div class="card report-hero shadow-sm mb-4">

        <div class="card-body p-4 position-relative">

            <div class="small text-white-50 mb-2">
                ADMINISTRATION OVERVIEW
            </div>

            <h2 class="h3 fw-bold mb-2">
                School Reports Dashboard
            </h2>

            <p class="text-white-50 mb-0">
                Quick overview of students, staff, academics and finances.
            </p>

        </div>

    </div>


    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">

            <div class="summary-box shadow-sm">

                <div class="report-icon mb-3">
                    <i class="bi bi-people"></i>
                </div>

                <div class="text-muted small">
                    Active Students
                </div>

                <div class="fs-2 fw-bold">
                    <?= $totalStudents ?>
                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="summary-box shadow-sm">

                <div class="report-icon mb-3">
                    <i class="bi bi-person-badge"></i>
                </div>

                <div class="text-muted small">
                    Active Teachers
                </div>

                <div class="fs-2 fw-bold">
                    <?= $totalTeachers ?>
                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="summary-box shadow-sm">

                <div class="report-icon mb-3">
                    <i class="bi bi-building"></i>
                </div>

                <div class="text-muted small">
                    Classes
                </div>

                <div class="fs-2 fw-bold">
                    <?= $totalClasses ?>
                </div>

            </div>

        </div>


        <div class="col-6 col-lg-3">

            <div class="summary-box shadow-sm">

                <div class="report-icon mb-3">
                    <i class="bi bi-book"></i>
                </div>

                <div class="text-muted small">
                    Subjects
                </div>

                <div class="fs-2 fw-bold">
                    <?= $totalSubjects ?>
                </div>

            </div>

        </div>

    </div>


    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="card report-card shadow-sm">

                <div class="card-body">

                    <div class="text-muted small">
                        Total Exams
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $totalExams ?>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card report-card shadow-sm">

                <div class="card-body">

                    <div class="text-muted small">
                        Total Assignments
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $totalAssignments ?>
                    </div>

                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="card report-card shadow-sm">

                <div class="card-body">

                    <div class="text-muted small">
                        Outstanding Fees
                    </div>

                    <div class="fs-3 fw-bold text-danger">
                        <?= number_format(
                            $feeSummary['outstanding'],
                            2
                        ) ?>
                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="row g-3">

        <div class="col-lg-4">

            <div class="card report-card shadow-sm h-100">

                <div class="card-body">

                    <h2 class="h6 fw-bold mb-3">
                        <i class="bi bi-cash-stack me-2"></i>
                        Fee Summary
                    </h2>

                    <p class="mb-2">
                        Billed:
                        <strong>
                            <?= number_format(
                                $feeSummary['billed'],
                                2
                            ) ?>
                        </strong>
                    </p>

                    <p class="mb-2 text-success">
                        Collected:
                        <strong>
                            <?= number_format(
                                $feeSummary['collected'],
                                2
                            ) ?>
                        </strong>
                    </p>

                    <p class="mb-0 text-danger">
                        Outstanding:
                        <strong>
                            <?= number_format(
                                $feeSummary['outstanding'],
                                2
                            ) ?>
                        </strong>
                    </p>

                </div>

            </div>

        </div>


        <div class="col-lg-8">

            <div class="card report-card shadow-sm h-100">

                <div class="card-body">

                    <h2 class="h6 fw-bold mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Admin Reporting
                    </h2>

                    <p class="text-muted mb-0">
                        Use the report selector above to generate detailed
                        student, attendance, fee and examination reports.
                    </p>

                </div>

            </div>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     STUDENT REPORT
========================================================= -->

<?php if ($reportType === 'students'): ?>

    <div class="card report-card shadow-sm">

        <div class="card-header bg-white p-3 no-print">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h2 class="h6 fw-bold mb-1">
                        <i class="bi bi-people me-2"></i>
                        Student Report
                    </h2>

                    <small class="text-muted">
                        <?= count($studentRows) ?> student(s)
                    </small>

                </div>

                <form method="get" class="d-flex gap-2">

                    <input
                        type="hidden"
                        name="report"
                        value="students"
                    >

                    <input
                        type="hidden"
                        name="class_id"
                        value="<?= $classId ?>"
                    >

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search student..."
                        value="<?= e($search) ?>"
                    >

                    <button class="btn btn-primary">
                        Search
                    </button>

                </form>

            </div>

        </div>


        <?php if ($studentRows === []): ?>

            <div class="empty-state">

                <div class="empty-icon mb-3">
                    <i class="bi bi-people"></i>
                </div>

                <h4 class="fw-bold">
                    No Students Found
                </h4>

                <p class="text-muted mb-0">
                    No students match the selected filters.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover mb-0 report-table">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4">#</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Status</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($studentRows as $index => $student): ?>

                        <tr>

                            <td class="px-4">
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= e($student['student_id']) ?>
                            </td>

                            <td class="fw-semibold">
                                <?= e($student['name']) ?>
                            </td>

                            <td>
                                <?= e($student['class_name']) ?>
                            </td>

                            <td>
                                <?= e(
                                    $student['section_name'] ?: '—'
                                ) ?>
                            </td>

                            <td>

                                <?php if ($student['status'] === 'active'): ?>

                                    <span class="badge text-bg-success">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-secondary">
                                        <?= e(
                                            ucfirst(
                                                (string) $student['status']
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     ATTENDANCE REPORT
========================================================= -->

<?php if ($reportType === 'attendance'): ?>

    <div class="card report-card shadow-sm mb-4 no-print">

        <div class="card-body">

            <form method="get">

                <input
                    type="hidden"
                    name="report"
                    value="attendance"
                >

                <input
                    type="hidden"
                    name="class_id"
                    value="<?= $classId ?>"
                >

                <div class="row g-3 align-items-end">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            From
                        </label>

                        <input
                            type="date"
                            name="attendance_from"
                            class="form-control"
                            value="<?= e($attendanceFrom) ?>"
                            required
                        >

                    </div>


                    <div class="col-md-4">

                        <label class="form-label fw-semibold">
                            To
                        </label>

                        <input
                            type="date"
                            name="attendance_to"
                            class="form-control"
                            value="<?= e($attendanceTo) ?>"
                            required
                        >

                    </div>


                    <div class="col-md-4">

                        <button class="btn btn-primary w-100">
                            <i class="bi bi-calendar-check me-2"></i>
                            Generate Attendance
                        </button>

                    </div>

                </div>

            </form>

        </div>

    </div>


    <div class="row g-3 mb-4">

        <div class="col-6 col-md-3">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Attendance Records
                </div>

                <div class="fs-3 fw-bold">
                    <?= $attendanceSummary['total'] ?>
                </div>

            </div>

        </div>


        <div class="col-6 col-md-3">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Present
                </div>

                <div class="fs-3 fw-bold text-success">
                    <?= $attendanceSummary['present'] ?>
                </div>

            </div>

        </div>


        <div class="col-6 col-md-3">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Absent
                </div>

                <div class="fs-3 fw-bold text-danger">
                    <?= $attendanceSummary['absent'] ?>
                </div>

            </div>

        </div>


        <div class="col-6 col-md-3">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Late
                </div>

                <div class="fs-3 fw-bold text-warning">
                    <?= $attendanceSummary['late'] ?>
                </div>

            </div>

        </div>

    </div>


    <div class="card report-card shadow-sm">

        <div class="card-header bg-white p-3">

            <h2 class="h6 fw-bold mb-0">
                <i class="bi bi-calendar-check me-2"></i>
                Student Attendance Report
            </h2>

        </div>


        <?php if ($attendanceRows === []): ?>

            <div class="empty-state">

                <div class="empty-icon mb-3">
                    <i class="bi bi-calendar-x"></i>
                </div>

                <h4 class="fw-bold">
                    No Attendance Data
                </h4>

                <p class="text-muted mb-0">
                    No attendance records were found for this period.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover mb-0 report-table">

                    <thead class="table-light">

                        <tr>
                            <th class="px-4">Student</th>
                            <th>Class</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Late</th>
                            <th>Attendance %</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($attendanceRows as $row): ?>

                        <tr>

                            <td class="px-4">

                                <div class="fw-semibold">
                                    <?= e($row['name']) ?>
                                </div>

                                <small class="text-muted">
                                    <?= e($row['student_id']) ?>
                                </small>

                            </td>

                            <td>

                                <?= e($row['class_name']) ?>

                                <?php if (!empty($row['section_name'])): ?>

                                    <small class="text-muted d-block">
                                        Section <?= e(
                                            $row['section_name']
                                        ) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?= (int) $row['total_days'] ?>
                            </td>

                            <td class="text-success fw-semibold">
                                <?= (int) $row['present_days'] ?>
                            </td>

                            <td class="text-danger fw-semibold">
                                <?= (int) $row['absent_days'] ?>
                            </td>

                            <td class="text-warning fw-semibold">
                                <?= (int) $row['late_days'] ?>
                            </td>

                            <td style="min-width:150px;">

                                <div class="d-flex justify-content-between">

                                    <span>
                                        <?= e(
                                            (string) $row['percentage']
                                        ) ?>%
                                    </span>

                                </div>

                                <div class="progress mt-1">

                                    <div
                                        class="progress-bar"
                                        role="progressbar"
                                        style="width: <?= min(
                                            (float) $row['percentage'],
                                            100
                                        ) ?>%;"
                                    ></div>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     FEE REPORT
========================================================= -->

<?php if ($reportType === 'fees'): ?>

    <div class="row g-3 mb-4">

        <div class="col-md-4">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Total Billed
                </div>

                <div class="fs-3 fw-bold">
                    <?= number_format(
                        $feeReportSummary['billed'],
                        2
                    ) ?>
                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Total Collected
                </div>

                <div class="fs-3 fw-bold text-success">
                    <?= number_format(
                        $feeReportSummary['paid'],
                        2
                    ) ?>
                </div>

            </div>

        </div>


        <div class="col-md-4">

            <div class="summary-box shadow-sm">

                <div class="text-muted small">
                    Outstanding
                </div>

                <div class="fs-3 fw-bold text-danger">
                    <?= number_format(
                        $feeReportSummary['outstanding'],
                        2
                    ) ?>
                </div>

            </div>

        </div>

    </div>


    <div class="card report-card shadow-sm">

        <div class="card-header bg-white p-3 no-print">

            <form method="get">

                <input
                    type="hidden"
                    name="report"
                    value="fees"
                >

                <input
                    type="hidden"
                    name="class_id"
                    value="<?= $classId ?>"
                >

                <div class="row g-3 align-items-end">

                    <div class="col-lg-6">

                        <label class="form-label fw-semibold">
                            Search
                        </label>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Student name or ID..."
                        >

                    </div>


                    <div class="col-lg-4">

                        <label class="form-label fw-semibold">
                            Fee Status
                        </label>

                        <select
                            name="fee_status"
                            class="form-select"
                        >

                            <option value="">
                                All
                            </option>

                            <option
                                value="pending"
                                <?= $feeStatus === 'pending'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Pending
                            </option>

                            <option
                                value="partial"
                                <?= $feeStatus === 'partial'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Partial
                            </option>

                            <option
                                value="paid"
                                <?= $feeStatus === 'paid'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Paid
                            </option>

                            <option
                                value="overdue"
                                <?= $feeStatus === 'overdue'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Overdue
                            </option>

                        </select>

                    </div>


                    <div class="col-lg-2">

                        <button class="btn btn-primary w-100">
                            Filter
                        </button>

                    </div>

                </div>

            </form>

        </div>


        <?php if ($feeRows === []): ?>

            <div class="empty-state">

                <div class="empty-icon mb-3">
                    <i class="bi bi-cash-stack"></i>
                </div>

                <h4 class="fw-bold">
                    No Fee Records
                </h4>

                <p class="text-muted mb-0">
                    No fee records match the selected filters.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover mb-0 report-table">

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">
                                Student
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Fee Type
                            </th>

                            <th>
                                Due Date
                            </th>

                            <th class="text-end">
                                Billed
                            </th>

                            <th class="text-end">
                                Paid
                            </th>

                            <th class="text-end">
                                Outstanding
                            </th>

                            <th class="text-center">
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($feeRows as $fee): ?>

                        <?php

                        $badgeClass = match ($fee['status']) {

                            'paid' => 'text-bg-success',

                            'partial' => 'text-bg-primary',

                            'overdue' => 'text-bg-danger',

                            default => 'text-bg-warning',

                        };

                        ?>

                        <tr>

                            <td class="px-4">

                                <div class="fw-semibold">
                                    <?= e(
                                        $fee['student_name']
                                    ) ?>
                                </div>

                                <small class="text-muted">
                                    <?= e(
                                        $fee['student_code']
                                    ) ?>
                                </small>

                            </td>

                            <td>
                                <?= e(
                                    $fee['class_name']
                                    ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <?= e($fee['fee_type']) ?>
                            </td>

                            <td>
                                <?= e($fee['due_date']) ?>
                            </td>

                            <td class="text-end">
                                <?= number_format(
                                    (float) $fee['amount'],
                                    2
                                ) ?>
                            </td>

                            <td class="text-end text-success fw-semibold">
                                <?= number_format(
                                    (float) $fee['paid_amount'],
                                    2
                                ) ?>
                            </td>

                            <td class="text-end text-danger fw-semibold">
                                <?= number_format(
                                    (float) $fee['remaining'],
                                    2
                                ) ?>
                            </td>

                            <td class="text-center">

                                <span class="badge <?= $badgeClass ?>">
                                    <?= e(
                                        ucfirst(
                                            (string) $fee['status']
                                        )
                                    ) ?>
                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>


<!-- =========================================================
     RESULT REPORT
========================================================= -->

<?php if ($reportType === 'results'): ?>

    <?php if ($examId <= 0 || !isset($selectedExam)): ?>

        <div class="card report-card shadow-sm">

            <div class="empty-state">

                <div class="empty-icon mb-3">
                    <i class="bi bi-award"></i>
                </div>

                <h4 class="fw-bold">
                    Select an Examination
                </h4>

                <p class="text-muted mb-0">
                    Select an examination above and generate the report.
                </p>

            </div>

        </div>

    <?php else: ?>

        <div class="card report-hero shadow-sm mb-4">

            <div class="card-body p-4 position-relative">

                <div class="small text-white-50 mb-2">
                    EXAMINATION REPORT
                </div>

                <h2 class="h3 fw-bold mb-2">
                    <?= e($selectedExam['name']) ?>
                </h2>

                <div class="text-white-50">

                    <?= e($selectedExam['class_name']) ?>

                    ·

                    <?= e(
                        ucfirst(
                            (string) $selectedExam['type']
                        )
                    ) ?>

                </div>

            </div>

        </div>


        <div class="row g-3 mb-4">

            <div class="col-md-3">

                <div class="summary-box shadow-sm">

                    <div class="text-muted small">
                        Students
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= $resultSummary['students'] ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="summary-box shadow-sm">

                    <div class="text-muted small">
                        Passed
                    </div>

                    <div class="fs-3 fw-bold text-success">
                        <?= $resultSummary['passed'] ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="summary-box shadow-sm">

                    <div class="text-muted small">
                        Failed
                    </div>

                    <div class="fs-3 fw-bold text-danger">
                        <?= $resultSummary['failed'] ?>
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="summary-box shadow-sm">

                    <div class="text-muted small">
                        Class Average
                    </div>

                    <div class="fs-3 fw-bold">
                        <?= e(
                            (string) $resultSummary['average']
                        ) ?>%
                    </div>

                </div>

            </div>

        </div>


        <div class="card report-card shadow-sm">

            <div class="card-header bg-white p-3">

                <h2 class="h6 fw-bold mb-0">
                    <i class="bi bi-award me-2"></i>
                    Examination Performance
                </h2>

            </div>


            <?php if ($resultRows === []): ?>

                <div class="empty-state">

                    <div class="empty-icon mb-3">
                        <i class="bi bi-bar-chart"></i>
                    </div>

                    <h4 class="fw-bold">
                        No Results Available
                    </h4>

                    <p class="text-muted mb-0">
                        Marks have not been entered for this examination.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover mb-0 report-table">

                        <thead class="table-light">

                            <tr>

                                <th class="px-4">
                                    #
                                </th>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Class
                                </th>

                                <th class="text-end">
                                    Total
                                </th>

                                <th class="text-end">
                                    Obtained
                                </th>

                                <th class="text-center">
                                    Percentage
                                </th>

                                <th class="text-center">
                                    Grade
                                </th>

                                <th class="text-center">
                                    Result
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($resultRows as $index => $row): ?>

                            <?php
                            $student = $row['student'];
                            $result = $row['result'];
                            ?>

                            <tr>

                                <td class="px-4">
                                    <?= $index + 1 ?>
                                </td>

                                <td>

                                    <div class="fw-semibold">
                                        <?= e(
                                            $student['name']
                                        ) ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= e(
                                            $student['student_id']
                                        ) ?>
                                    </small>

                                </td>

                                <td>
                                    <?= e(
                                        $student['class_name']
                                    ) ?>
                                </td>

                                <td class="text-end">
                                    <?= number_format(
                                        $result['total_marks'],
                                        2
                                    ) ?>
                                </td>

                                <td class="text-end fw-bold">
                                    <?= number_format(
                                        $result['obtained_marks'],
                                        2
                                    ) ?>
                                </td>

                                <td class="text-center">
                                    <?= e(
                                        (string) $result['percentage']
                                    ) ?>%
                                </td>

                                <td class="text-center">

                                    <span class="badge text-bg-primary fs-6">
                                        <?= e(
                                            $result['grade']
                                        ) ?>
                                    </span>

                                </td>

                                <td class="text-center">

                                    <?php if (
                                        $result['status'] === 'Pass'
                                    ): ?>

                                        <span class="badge text-bg-success">
                                            Pass
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-danger">
                                            Fail
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

<?php endif; ?>


<script>

function openReport() {

    const report =
        document.getElementById('reportType').value;

    const classId =
        document.getElementById('classSelector').value;

    const examId =
        document.getElementById('examSelector').value;

    const baseUrl =
        '<?= e(url('admin/reports/index.php')) ?>';

    const params = new URLSearchParams();

    params.set('report', report);

    if (classId) {
        params.set('class_id', classId);
    }

    if (report === 'results' && examId) {
        params.set('exam_id', examId);
    }

    window.location.href =
        baseUrl + '?' + params.toString();
}

</script>


<?php layout_end(); ?>