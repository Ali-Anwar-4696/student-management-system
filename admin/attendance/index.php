<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Attendance.php';

$pdo = db();
$database = null;


// =====================================================
// OBJECT
// =====================================================

$attendanceObject = new Attendance($pdo);


// =====================================================
// CURRENT USER
// =====================================================

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];


// =====================================================
// FILTERS
// =====================================================

$search = trim(
    (string) ($_GET['search'] ?? '')
);

$status = trim(
    (string) ($_GET['status'] ?? '')
);

$classId = (int) (
    $_GET['class_id'] ?? 0
);

$sectionId = (int) (
    $_GET['section_id'] ?? 0
);

$subjectId = (int) (
    $_GET['subject_id'] ?? 0
);

$date = trim(
    (string) ($_GET['date'] ?? '')
);


// =====================================================
// PAGINATION
// =====================================================

$limit = 10;

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$offset = ($page - 1) * $limit;


// =====================================================
// GET ATTENDANCE
// =====================================================

$attendance = $attendanceObject->getAttendance(
    $search,
    $status,
    $classId > 0 ? $classId : null,
    $sectionId > 0 ? $sectionId : null,
    $subjectId > 0 ? $subjectId : null,
    $date,
    $limit,
    $offset
);


// =====================================================
// COUNT ATTENDANCE
// =====================================================

$totalRecords = $attendanceObject->countAttendance(
    $search,
    $status,
    $classId > 0 ? $classId : null,
    $sectionId > 0 ? $sectionId : null,
    $subjectId > 0 ? $subjectId : null,
    $date
);

$totalPages = max(
    1,
    (int) ceil($totalRecords / $limit)
);


// =====================================================
// BUILD PAGINATION URL
// =====================================================

function buildPageUrl(int $page): string
{
    $params = $_GET;

    $params['page'] = $page;

    return '?' . http_build_query($params);
}


// =====================================================
// REPORT FILTERS
// =====================================================

$reportStudentId = (int) (
    $_GET['report_student_id'] ?? 0
);

$reportType = trim(
    (string) ($_GET['report_type'] ?? '')
);

$reportDate = trim(
    (string) (
        $_GET['report_date']
        ?? date('Y-m-d')
    )
);


// =====================================================
// VALID REPORT TYPE
// =====================================================

$allowedReportTypes = [
    'daily',
    'weekly',
    'monthly',
    'total'
];

if (
    $reportType !== '' &&
    !in_array(
        $reportType,
        $allowedReportTypes,
        true
    )
) {
    $reportType = '';
}


// =====================================================
// TEACHER ID
// =====================================================

$teacherId = null;

if ($userRole === 'teacher') {

    $teacherSql = "
        SELECT id
        FROM teachers
        WHERE user_id = :user_id
          AND status = 'active'
        LIMIT 1
    ";

    $teacherStmt = $pdo->prepare($teacherSql);

    $teacherStmt->execute([
        ':user_id' => $userId
    ]);

    $teacher = $teacherStmt->fetch();

    if ($teacher) {
        $teacherId = (int) $teacher['id'];
    }
}


// =====================================================
// GET STUDENTS FOR REPORT
// =====================================================

/*
|--------------------------------------------------------------------------
| Report student picker
|
| The picker used to load every active student into one <select>, which
| made this page grow with the size of the school and took seconds to
| render at a few thousand students. It now supports a server-side search
| term and an explicit cap.
|--------------------------------------------------------------------------
*/

$reportStudentSearch = trim((string) ($_GET['student_q'] ?? ''));

$reportStudentLimit = 100;

$reportStudentCount = 0;

if ($userRole === 'admin') {

    $studentSql = "
        SELECT
            s.id,
            s.student_id,
            s.name,
            s.father_name,
            c.name AS class_name,
            sec.name AS section_name

        FROM students AS s

        LEFT JOIN classes AS c
            ON s.class_id = c.id

        LEFT JOIN sections AS sec
            ON s.section_id = sec.id

        WHERE s.status = 'active'
    ";

    $studentParams = [];

    if ($reportStudentSearch !== '') {

        $studentSql .= '
            AND (
                s.name LIKE :sq
                OR s.student_id LIKE :sq2
            )
        ';

        $studentParams[':sq'] = '%' . $reportStudentSearch . '%';
        $studentParams[':sq2'] = '%' . $reportStudentSearch . '%';
    }

    $studentSql .= '
        ORDER BY s.name ASC
        LIMIT ' . (int) $reportStudentLimit . '
    ';

    $studentStmt = $pdo->prepare($studentSql);

    $studentStmt->execute($studentParams);

    $reportStudents = $studentStmt->fetchAll();

    $reportStudentCount = $pdo->query(
        "SELECT COUNT(*) FROM students WHERE status = 'active'"
    )->fetchColumn();

} else {

    if ($teacherId !== null) {

        $studentSql = "
            SELECT DISTINCT

                s.id,
                s.student_id,
                s.name,
                s.father_name,
                c.name AS class_name,
                sec.name AS section_name

            FROM students AS s

            INNER JOIN classes AS c
                ON s.class_id = c.id

            LEFT JOIN sections AS sec
                ON s.section_id = sec.id

            INNER JOIN teacher_classes AS tc
                ON tc.class_id = s.class_id

            WHERE s.status = 'active'

              AND tc.teacher_id = :teacher_id

              AND (
                    tc.section_id IS NULL
                    OR tc.section_id = s.section_id
              )
        ";

        $studentParams = [':teacher_id' => $teacherId];

        if ($reportStudentSearch !== '') {

            $studentSql .= '
                AND (
                    s.name LIKE :sq
                    OR s.student_id LIKE :sq2
                )
            ';

            $studentParams[':sq'] = '%' . $reportStudentSearch . '%';
            $studentParams[':sq2'] = '%' . $reportStudentSearch . '%';
        }

        $studentSql .= '
            ORDER BY s.name ASC
            LIMIT ' . (int) $reportStudentLimit . '
        ';

        $studentStmt = $pdo->prepare($studentSql);

        $studentStmt->execute($studentParams);

        $reportStudents = $studentStmt->fetchAll();

    } else {

        $reportStudents = [];
    }
}


// =====================================================
// VALIDATE SELECTED REPORT STUDENT
// =====================================================

$selectedReportStudent = null;

if ($reportStudentId > 0) {

    /*
    | Looked up directly rather than by scanning the (now capped) picker
    | list, so a previously selected student still resolves.
    */
    $selectedStmt = $pdo->prepare("
        SELECT
            s.id,
            s.student_id,
            s.name,
            s.father_name,
            c.name AS class_name,
            sec.name AS section_name

        FROM students AS s

        LEFT JOIN classes AS c
            ON s.class_id = c.id

        LEFT JOIN sections AS sec
            ON s.section_id = sec.id

        WHERE s.id = :id
          AND s.status = 'active'
    ");

    $selectedStmt->execute([
        ':id' => $reportStudentId
    ]);

    $selectedReportStudent = $selectedStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    /*
    | A teacher may only produce a report for a student they actually
    | teach, so the id from the query string is re-checked against
    | teacher_classes authorization rather than trusted.
    */
    if (
        $selectedReportStudent !== null
        && $teacherId !== null
    ) {

        $allowedStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM students AS s

            INNER JOIN teacher_classes AS tc
                ON tc.class_id = s.class_id

            WHERE s.id = :id

              AND tc.teacher_id = :teacher_id

              AND (
                    tc.section_id IS NULL
                    OR tc.section_id = s.section_id
              )
        ");

        $allowedStmt->execute([
            ':id' => $reportStudentId,
            ':teacher_id' => $teacherId
        ]);

        if ((int) $allowedStmt->fetchColumn() === 0) {
            $selectedReportStudent = null;
        }
    }

    if ($selectedReportStudent === null) {
        $reportStudentId = 0;
    }
}


// =====================================================
// REPORT DATA
// =====================================================

$reportRows = [];

$reportSummary = [
    'total'       => 0,
    'present'     => 0,
    'absent'      => 0,
    'late'        => 0,
    'leave_count' => 0,
    'percentage'  => 0
];

$reportStartDate = '';
$reportEndDate = '';


// =====================================================
// GENERATE REPORT
// =====================================================

if (
    $reportStudentId > 0 &&
    $reportType !== ''
) {


    // =================================================
    // DAILY
    // =================================================

    if ($reportType === 'daily') {

        $reportStartDate = $reportDate;
        $reportEndDate = $reportDate;

        $reportRows =
            $attendanceObject->getDailyAttendance(
                $reportStudentId,
                $reportDate
            );
    }


    // =================================================
    // WEEKLY
    // =================================================

    elseif ($reportType === 'weekly') {

        $timestamp = strtotime($reportDate);

        $dayOfWeek = (int) date(
            'N',
            $timestamp
        );

        $mondayTimestamp = strtotime(
            '-' . ($dayOfWeek - 1) . ' days',
            $timestamp
        );

        $sundayTimestamp = strtotime(
            '+6 days',
            $mondayTimestamp
        );

        $reportStartDate = date(
            'Y-m-d',
            $mondayTimestamp
        );

        $reportEndDate = date(
            'Y-m-d',
            $sundayTimestamp
        );

        $reportRows =
            $attendanceObject->getWeeklyAttendance(
                $reportStudentId,
                $reportStartDate,
                $reportEndDate
            );
    }


    // =================================================
    // MONTHLY
    // =================================================

    elseif ($reportType === 'monthly') {

        $timestamp = strtotime($reportDate);

        $reportStartDate = date(
            'Y-m-01',
            $timestamp
        );

        $reportEndDate = date(
            'Y-m-t',
            $timestamp
        );

        $reportRows =
            $attendanceObject->getMonthlyAttendance(
                $reportStudentId,
                $reportStartDate,
                $reportEndDate
            );
    }


    // =================================================
    // TOTAL
    // =================================================

    elseif ($reportType === 'total') {

        $reportRows = [];

        $reportSummary =
            $attendanceObject->getAttendanceSummary(
                $reportStudentId
            );
    }


    // =================================================
    // CALCULATE SUMMARY
    // =================================================

    if ($reportType !== 'total') {

        foreach ($reportRows as $row) {

            $rowStatus =
                strtolower(
                    (string) (
                        $row['status'] ?? ''
                    )
                );

            $reportSummary['total']++;


            if ($rowStatus === 'present') {

                $reportSummary['present']++;

            } elseif ($rowStatus === 'absent') {

                $reportSummary['absent']++;

            } elseif ($rowStatus === 'late') {

                $reportSummary['late']++;

            } elseif ($rowStatus === 'leave') {

                $reportSummary['leave_count']++;
            }
        }


        if (
            $reportSummary['total'] > 0
        ) {

            $attended =
                $reportSummary['present']
                +
                $reportSummary['late'];

            $reportSummary['percentage'] =
                round(
                    (
                        $attended
                        /
                        $reportSummary['total']
                    ) * 100,
                    2
                );
        }
    }
}


// =====================================================
// REPORT TITLE
// =====================================================

$reportTitle = '';

if ($reportType === 'daily') {

    $reportTitle =
        'Daily Attendance Report';

} elseif ($reportType === 'weekly') {

    $reportTitle =
        'Weekly Attendance Report';

} elseif ($reportType === 'monthly') {

    $reportTitle =
        'Monthly Attendance Report';

} elseif ($reportType === 'total') {

    $reportTitle =
        'Total Attendance Summary';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Attendance | Student Management
    </title>


    <!-- =================================================
         GOOGLE FONT
    ================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #172033;
        }


        .page-wrapper {
            min-height: 100vh;
            padding: 32px;
        }


        .container {
            max-width: 1250px;
            margin: auto;
        }


        /* =================================================
           HEADER
        ================================================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }


        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }


        .header-icon {
            width: 52px;
            height: 52px;
            background: #4f46e5;
            color: white;
            border-radius: 14px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 24px;

            box-shadow:
                0 8px 20px
                rgba(79, 70, 229, 0.20);
        }


        .page-title h1 {
            font-size: 26px;
            font-weight: 800;
        }


        .page-title p {
            margin-top: 5px;
            color: #7b8498;
            font-size: 14px;
        }


        .mark-btn {
            text-decoration: none;
            background: #4f46e5;
            color: white;

            padding: 11px 17px;

            border-radius: 9px;

            font-size: 13px;
            font-weight: 700;
        }


        .mark-btn:hover {
            background: #4338ca;
        }


        /* =================================================
           CARD
        ================================================== */

        .card {
            background: white;
            border: 1px solid #e7eaf0;
            border-radius: 15px;

            padding: 22px;
            margin-bottom: 20px;

            box-shadow:
                0 5px 20px
                rgba(20, 30, 55, 0.04);
        }


        .card-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 18px;
        }


        .card-description {
            color: #8991a3;
            font-size: 12px;
            margin-top: -10px;
            margin-bottom: 18px;
        }


        /* =================================================
           FILTERS
        ================================================== */

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }


        .field label {
            display: block;

            font-size: 12px;
            font-weight: 700;

            color: #6b7280;

            margin-bottom: 8px;
        }


        .field input,
        .field select {
            width: 100%;

            padding: 11px 13px;

            border: 1px solid #dfe3ea;

            border-radius: 9px;

            outline: none;

            font-family: inherit;
            font-size: 13px;

            background: white;
            color: #374151;
        }


        .field input:focus,
        .field select:focus {
            border-color: #6366f1;

            box-shadow:
                0 0 0 3px
                rgba(99, 102, 241, 0.10);
        }


        .filter-actions {
            display: flex;
            align-items: end;
            gap: 8px;
        }


        .filter-btn {
            border: none;

            background: #4f46e5;
            color: white;

            padding: 11px 18px;

            border-radius: 9px;

            font-family: inherit;
            font-size: 13px;
            font-weight: 700;

            cursor: pointer;
        }


        .filter-btn:hover {
            background: #4338ca;
        }


        .reset-btn {
            text-decoration: none;

            background: #f3f4f6;
            color: #4b5563;

            padding: 11px 15px;

            border-radius: 9px;

            font-size: 13px;
            font-weight: 600;
        }


        .reset-btn:hover {
            background: #e5e7eb;
        }


        /* =================================================
           REPORT
        ================================================== */

        .report-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }


        .report-card {
            background: #fafbff;

            border: 1px solid #e7eaf0;

            border-radius: 12px;

            padding: 17px;
        }


        .report-label {
            font-size: 11px;

            color: #7b8498;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .3px;

            margin-bottom: 7px;
        }


        .report-value {
            font-size: 24px;

            font-weight: 800;

            color: #1f2937;
        }


        .report-student {
            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 20px;

            padding-bottom: 18px;

            border-bottom: 1px solid #edf0f4;
        }


        .report-avatar {
            width: 45px;
            height: 45px;

            border-radius: 11px;

            background: #eef2ff;

            color: #4f46e5;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 14px;

            font-weight: 800;
        }


        .report-student-name {
            font-size: 15px;

            font-weight: 800;

            color: #1f2937;
        }


        .report-student-meta {
            margin-top: 4px;

            color: #8991a3;

            font-size: 11px;
        }


        .report-period {
            margin-top: 18px;

            font-size: 12px;

            color: #6b7280;
        }


        /* =================================================
           SUMMARY
        ================================================== */

        .summary {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 18px;
        }


        .summary-title {
            font-size: 16px;
            font-weight: 800;
        }


        .summary-count {
            color: #8991a3;
            font-size: 12px;
            margin-top: 5px;
        }


        /* =================================================
           TABLE
        ================================================== */

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: collapse;

            min-width: 850px;
        }


        th {
            background: #fafbfc;

            color: #6b7280;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.4px;

            font-weight: 700;

            text-align: left;

            padding: 14px 16px;

            border-bottom: 1px solid #edf0f4;
        }


        td {
            padding: 14px 16px;

            font-size: 13px;

            color: #374151;

            border-bottom: 1px solid #f0f2f5;

            vertical-align: middle;
        }


        tbody tr:hover {
            background: #fafbff;
        }


        /* =================================================
           STUDENT
        ================================================== */

        .student-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .avatar {
            width: 36px;
            height: 36px;

            border-radius: 9px;

            background: #eef2ff;
            color: #4f46e5;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 12px;
            font-weight: 700;
        }


        .student-name {
            font-weight: 700;
            color: #1f2937;
        }


        .student-id {
            color: #9ca3af;
            font-size: 11px;
            margin-top: 3px;
        }


        /* =================================================
           STATUS
        ================================================== */

        .status {
            display: inline-flex;

            padding: 6px 10px;

            border-radius: 7px;

            font-size: 11px;

            font-weight: 700;

            text-transform: capitalize;
        }


        .status-present {
            background: #ecfdf3;
            color: #087443;
        }


        .status-absent {
            background: #fff1f2;
            color: #be123c;
        }


        .status-late {
            background: #fff7ed;
            color: #c2410c;
        }


        .status-leave {
            background: #eff6ff;
            color: #1d4ed8;
        }


        /* =================================================
           ACTION BUTTONS
        ================================================== */

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }


        .view-btn,
        .edit-btn,
        .delete-btn {
            display: inline-block;

            text-decoration: none;

            padding: 7px 11px;

            border-radius: 7px;

            font-size: 11px;
            font-weight: 700;
        }


        .view-btn {
            background: #eef2ff;
            color: #4f46e5;
        }


        .view-btn:hover {
            background: #e0e7ff;
        }


        .edit-btn {
            background: #fff7ed;
            color: #c2410c;
        }


        .edit-btn:hover {
            background: #ffedd5;
        }


        .delete-btn {
            background: #fff1f2;
            color: #be123c;
        }


        .delete-btn:hover {
            background: #ffe4e6;
        }


        /* =================================================
           EMPTY STATE
        ================================================== */

        .empty-state {
            padding: 55px 20px;

            text-align: center;
        }


        .empty-icon {
            font-size: 40px;

            margin-bottom: 12px;
        }


        .empty-state h3 {
            font-size: 16px;
            margin-bottom: 6px;
        }


        .empty-state p {
            color: #8991a3;
            font-size: 13px;
        }


        /* =================================================
           PAGINATION
        ================================================== */

        .pagination {
            display: flex;

            justify-content: center;

            align-items: center;

            gap: 6px;

            margin-top: 22px;
        }


        .pagination a,
        .pagination span {
            min-width: 34px;
            height: 34px;

            padding: 0 10px;

            display: flex;

            align-items: center;
            justify-content: center;

            border-radius: 8px;

            font-size: 12px;
            font-weight: 700;

            text-decoration: none;
        }


        .pagination a {
            background: #f3f4f6;
            color: #4b5563;
        }


        .pagination a:hover {
            background: #e5e7eb;
        }


        .pagination .active {
            background: #4f46e5;
            color: white;
        }


        .pagination .disabled {
            background: #f3f4f6;
            color: #c0c4cc;
        }


        /* =================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 1000px) {

            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .report-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }


        @media (max-width: 700px) {

            .page-wrapper {
                padding: 18px;
            }


            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }


            .filter-grid {
                grid-template-columns: 1fr;
            }


            .report-grid {
                grid-template-columns: 1fr;
            }


            .filter-actions {
                align-items: stretch;
            }


            .filter-btn,
            .reset-btn {
                text-align: center;
            }

        }


        @media (max-width: 450px) {

            .page-wrapper {
                padding: 12px;
            }


            .page-title h1 {
                font-size: 21px;
            }


            .header-icon {
                width: 45px;
                height: 45px;
            }

        }

    </style>

</head>


<body>


<div class="page-wrapper">

<div class="container">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="page-header">

        <div class="header-left">

            <div class="header-icon">
                📅
            </div>

            <div class="page-title">

                <h1>
                    Attendance
                </h1>

                <p>
                    View and manage student attendance records
                </p>

            </div>

        </div>


        <a
            href="mark.php"
            class="mark-btn"
        >
            + Mark Attendance
        </a>

    </div>



    <!-- =================================================
         STUDENT ATTENDANCE REPORT
    ================================================== -->

    <div class="card">

        <div class="card-title">
            Student Attendance Report
        </div>

        <div class="card-description">
            Generate daily, weekly, monthly or total attendance report.
        </div>


        <form method="GET">

            <div class="filter-grid">


                <!-- STUDENT -->

                <div class="field">

                    <label>
                        Student
                    </label>

                    <form
                        method="get"
                        action="index.php"
                        class="mb-16"
                    >

                        <label
                            class="form-label"
                            for="reportStudentSearch"
                        >
                            Find Student
                        </label>

                        <div
                            class="d-flex gap-2"
                        >

                            <input
                                type="search"
                                id="reportStudentSearch"
                                name="student_q"
                                class="form-control"
                                placeholder="Name or student ID"
                                value="<?= htmlspecialchars($reportStudentSearch) ?>"
                            >

                            <button
                                type="submit"
                                class="btn btn-outline-secondary"
                            >
                                Search
                            </button>

                        </div>

                    </form>

                    <select name="report_student_id">

                        <option value="">
                            Select Student
                        </option>


                        <?php foreach (
                            $reportStudents
                            as $student
                        ): ?>

                            <?php

                            $studentClass =
                                $student['class_name']
                                ?? '';

                            ?>

                            <option
                                value="<?= (int) $student['id'] ?>"
                                <?= $reportStudentId ===
                                    (int) $student['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    (string) $student['name']
                                ) ?>

                                -

                                <?= htmlspecialchars(
                                    (string) $student['student_id']
                                ) ?>

                                <?php if (
                                    $studentClass !== ''
                                ): ?>

                                    -
                                    <?= htmlspecialchars(
                                        $studentClass
                                    ) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>



                <!-- REPORT TYPE -->

                <div class="field">

                    <label>
                        Report Type
                    </label>

                    <select name="report_type">

                        <option value="">
                            Select Report
                        </option>

                        <option
                            value="daily"
                            <?= $reportType === 'daily'
                                ? 'selected'
                                : '' ?>
                        >
                            Daily
                        </option>

                        <option
                            value="weekly"
                            <?= $reportType === 'weekly'
                                ? 'selected'
                                : '' ?>
                        >
                            Weekly
                        </option>

                        <option
                            value="monthly"
                            <?= $reportType === 'monthly'
                                ? 'selected'
                                : '' ?>
                        >
                            Monthly
                        </option>

                        <option
                            value="total"
                            <?= $reportType === 'total'
                                ? 'selected'
                                : '' ?>
                        >
                            Total
                        </option>

                    </select>

                </div>



                <!-- REPORT DATE -->

                <div class="field">

                    <label>
                        Date
                    </label>

                    <input
                        type="date"
                        name="report_date"
                        value="<?= htmlspecialchars(
                            $reportDate
                        ) ?>"
                    >

                </div>



                <!-- REPORT ACTION -->

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="filter-btn"
                    >
                        Generate Report
                    </button>

                    <a
                        href="index.php"
                        class="reset-btn"
                    >
                        Reset
                    </a>

                </div>


            </div>

        </form>

    </div>



    <?php if (
        $reportStudentId > 0 &&
        $reportType !== '' &&
        $selectedReportStudent !== null
    ): ?>


        <!-- =================================================
             REPORT RESULT
        ================================================== -->

        <div class="card">


            <!-- STUDENT INFO -->

            <div class="report-student">

                <?php

                $reportStudentName =
                    (string) (
                        $selectedReportStudent['name']
                        ?? ''
                    );

                $reportInitial =
                    strtoupper(
                        substr(
                            $reportStudentName,
                            0,
                            1
                        )
                    );

                ?>

                <div class="report-avatar">

                    <?= htmlspecialchars(
                        $reportInitial
                    ) ?>

                </div>


                <div>

                    <div class="report-student-name">

                        <?= htmlspecialchars(
                            $reportStudentName
                        ) ?>

                    </div>


                    <div class="report-student-meta">

                        ID:
                        <?= htmlspecialchars(
                            (string) (
                                $selectedReportStudent['student_id']
                                ?? ''
                            )
                        ) ?>


                        <?php if (
                            !empty(
                                $selectedReportStudent['class_name']
                            )
                        ): ?>

                            &nbsp; • &nbsp;

                            Class:
                            <?= htmlspecialchars(
                                (string) (
                                    $selectedReportStudent['class_name']
                                )
                            ) ?>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $selectedReportStudent['section_name']
                            )
                        ): ?>

                            &nbsp; • &nbsp;

                            Section:
                            <?= htmlspecialchars(
                                (string) (
                                    $selectedReportStudent['section_name']
                                )
                            ) ?>

                        <?php endif; ?>

                    </div>

                </div>

            </div>



            <div class="summary">

                <div>

                    <div class="summary-title">

                        <?= htmlspecialchars(
                            $reportTitle
                        ) ?>

                    </div>


                    <?php if (
                        $reportStartDate !== ''
                    ): ?>

                        <div class="report-period">

                            Period:

                            <?= htmlspecialchars(
                                $reportStartDate
                            ) ?>

                            <?php if (
                                $reportEndDate !==
                                $reportStartDate
                            ): ?>

                                to

                                <?= htmlspecialchars(
                                    $reportEndDate
                                ) ?>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>



            <!-- =================================================
                 REPORT SUMMARY CARDS
            ================================================== -->

            <div class="report-grid">


                <!-- TOTAL -->

                <div class="report-card">

                    <div class="report-label">
                        Total
                    </div>

                    <div class="report-value">

                        <?= number_format(
                            $reportSummary['total']
                        ) ?>

                    </div>

                </div>



                <!-- PRESENT -->

                <div class="report-card">

                    <div class="report-label">
                        Present
                    </div>

                    <div class="report-value">

                        <?= number_format(
                            $reportSummary['present']
                        ) ?>

                    </div>

                </div>



                <!-- ABSENT -->

                <div class="report-card">

                    <div class="report-label">
                        Absent
                    </div>

                    <div class="report-value">

                        <?= number_format(
                            $reportSummary['absent']
                        ) ?>

                    </div>

                </div>



                <!-- LATE -->

                <div class="report-card">

                    <div class="report-label">
                        Late
                    </div>

                    <div class="report-value">

                        <?= number_format(
                            $reportSummary['late']
                        ) ?>

                    </div>

                </div>



                <!-- LEAVE -->

                <div class="report-card">

                    <div class="report-label">
                        Leave
                    </div>

                    <div class="report-value">

                        <?= number_format(
                            $reportSummary['leave_count']
                        ) ?>

                    </div>

                </div>



                <!-- PERCENTAGE -->

                <div class="report-card">

                    <div class="report-label">
                        Attendance Percentage
                    </div>

                    <div class="report-value">

                        <?= number_format(
                            (float) $reportSummary['percentage'],
                            2
                        ) ?>%

                    </div>

                </div>


            </div>



            <?php if (
                $reportType !== 'total'
            ): ?>


                <!-- =================================================
                     DAILY / WEEKLY / MONTHLY TABLE
                ================================================== -->

                <div
                    class="table-wrapper"
                    style="margin-top: 25px;"
                >

                    <?php if (
                        !empty($reportRows)
                    ): ?>

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Class
                                    </th>

                                    <th>
                                        Section
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $reportRows
                                as $index => $row
                            ): ?>

                                <?php

                                $rowStatus =
                                    strtolower(
                                        (string) (
                                            $row['status']
                                            ?? ''
                                        )
                                    );

                                ?>

                                <tr>

                                    <td>
                                        <?= $index + 1 ?>
                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            (string) (
                                                $row['date']
                                                ?? '-'
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            (string) (
                                                $row['class_name']
                                                ?? '-'
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php

                                        $sectionName =
                                            $row['section_name']
                                            ?? null;

                                        ?>

                                        <?= $sectionName
                                            ? htmlspecialchars(
                                                (string) $sectionName
                                            )
                                            : 'Whole Class' ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            (string) (
                                                $row['subject_name']
                                                ?? 'General'
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <span
                                            class="status status-<?= htmlspecialchars(
                                                $rowStatus
                                            ) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $rowStatus
                                                )
                                            ) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    <?php else: ?>


                        <div class="empty-state">

                            <div class="empty-icon">
                                📋
                            </div>

                            <h3>
                                No Attendance Found
                            </h3>

                            <p>
                                No attendance record exists
                                for the selected period.
                            </p>

                        </div>


                    <?php endif; ?>

                </div>


            <?php endif; ?>


        </div>


    <?php endif; ?>



    <!-- =================================================
         FILTER CARD
    ================================================== -->

    <div class="card">

        <div class="card-title">
            Attendance Filters
        </div>


        <form method="GET">

            <div class="filter-grid">


                <!-- SEARCH -->

                <div class="field">

                    <label>
                        Search Student
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Name or Student ID"
                    >

                </div>



                <!-- STATUS -->

                <div class="field">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="present"
                            <?= $status === 'present'
                                ? 'selected'
                                : '' ?>
                        >
                            Present
                        </option>

                        <option
                            value="absent"
                            <?= $status === 'absent'
                                ? 'selected'
                                : '' ?>
                        >
                            Absent
                        </option>

                        <option
                            value="late"
                            <?= $status === 'late'
                                ? 'selected'
                                : '' ?>
                        >
                            Late
                        </option>

                        <option
                            value="leave"
                            <?= $status === 'leave'
                                ? 'selected'
                                : '' ?>
                        >
                            Leave
                        </option>

                    </select>

                </div>



                <!-- CLASS -->

                <div class="field">

                    <label>
                        Class ID
                    </label>

                    <input
                        type="number"
                        name="class_id"
                        value="<?= $classId > 0
                            ? $classId
                            : '' ?>"
                        placeholder="Class ID"
                        min="1"
                    >

                </div>



                <!-- SECTION -->

                <div class="field">

                    <label>
                        Section ID
                    </label>

                    <input
                        type="number"
                        name="section_id"
                        value="<?= $sectionId > 0
                            ? $sectionId
                            : '' ?>"
                        placeholder="Section ID"
                        min="1"
                    >

                </div>



                <!-- SUBJECT -->

                <div class="field">

                    <label>
                        Subject ID
                    </label>

                    <input
                        type="number"
                        name="subject_id"
                        value="<?= $subjectId > 0
                            ? $subjectId
                            : '' ?>"
                        placeholder="Subject ID"
                        min="1"
                    >

                </div>



                <!-- DATE -->

                <div class="field">

                    <label>
                        Attendance Date
                    </label>

                    <input
                        type="date"
                        name="date"
                        value="<?= htmlspecialchars($date) ?>"
                    >

                </div>



                <!-- ACTIONS -->

                <div class="filter-actions">

                    <button
                        type="submit"
                        class="filter-btn"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="index.php"
                        class="reset-btn"
                    >
                        Reset
                    </a>

                </div>


            </div>

        </form>

    </div>



    <!-- =================================================
         ATTENDANCE TABLE
    ================================================== -->

    <div class="card">


        <div class="summary">

            <div>

                <div class="summary-title">
                    Attendance Records
                </div>

                <div class="summary-count">

                    <?= number_format($totalRecords) ?>

                    records found

                </div>

            </div>

        </div>



        <?php if (!empty($attendance)): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Student
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Section
                            </th>

                            <th>
                                Subject
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $attendance as $index => $row
                    ): ?>


                        <?php

                        $studentName =
                            $row['student_name']
                            ?? $row['name']
                            ?? '';

                        $studentCode =
                            $row['student_code']
                            ?? $row['student_id']
                            ?? '';

                        $initial =
                            strtoupper(
                                substr(
                                    $studentName,
                                    0,
                                    1
                                )
                            );

                        $rowStatus =
                            strtolower(
                                (string) (
                                    $row['status']
                                    ?? ''
                                )
                            );

                        ?>


                        <tr>


                            <!-- NUMBER -->

                            <td>

                                <?= $offset + $index + 1 ?>

                            </td>



                            <!-- STUDENT -->

                            <td>

                                <div class="student-info">

                                    <div class="avatar">

                                        <?= htmlspecialchars(
                                            $initial
                                        ) ?>

                                    </div>


                                    <div>

                                        <div class="student-name">

                                            <?= htmlspecialchars(
                                                $studentName
                                            ) ?>

                                        </div>


                                        <div class="student-id">

                                            ID:

                                            <?= htmlspecialchars(
                                                (string) $studentCode
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>



                            <!-- CLASS -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $row['class_name']
                                        ?? '-'
                                    )
                                ) ?>

                            </td>



                            <!-- SECTION -->

                            <td>

                                <?php

                                $sectionName =
                                    $row['section_name']
                                    ?? null;

                                ?>

                                <?= $sectionName
                                    ? htmlspecialchars(
                                        (string) $sectionName
                                    )
                                    : 'Whole Class' ?>

                            </td>



                            <!-- SUBJECT -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $row['subject_name']
                                        ?? 'General'
                                    )
                                ) ?>

                            </td>



                            <!-- DATE -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) (
                                        $row['date']
                                        ?? '-'
                                    )
                                ) ?>

                            </td>



                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status status-<?= htmlspecialchars(
                                        $rowStatus
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $rowStatus
                                        )
                                    ) ?>

                                </span>

                            </td>



                            <!-- ACTION -->

                            <td>

                                <div class="action-buttons">


                                    <!-- VIEW -->

                                    <a
                                        href="view.php?id=<?= (int) $row['id'] ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>


                                    <!-- EDIT -->

                                    <a
                                        href="edit.php?id=<?= (int) $row['id'] ?>"
                                        class="edit-btn"
                                    >
                                        Edit
                                    </a>


                                    <!-- DELETE -->

                                    <a
                                        href="delete.php?id=<?= (int) $row['id'] ?>"
                                        class="delete-btn"
                                    >
                                        Delete
                                    </a>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>



            <!-- =================================================
                 PAGINATION
            ================================================== -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">


                    <?php if ($page > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl($page - 1)
                            ) ?>"
                        >
                            ←
                        </a>

                    <?php else: ?>

                        <span class="disabled">
                            ←
                        </span>

                    <?php endif; ?>



                    <?php

                    $startPage = max(
                        1,
                        $page - 2
                    );

                    $endPage = min(
                        $totalPages,
                        $page + 2
                    );

                    for (
                        $p = $startPage;
                        $p <= $endPage;
                        $p++
                    ):
                    ?>


                        <?php if ($p === $page): ?>

                            <span class="active">
                                <?= $p ?>
                            </span>

                        <?php else: ?>

                            <a
                                href="<?= htmlspecialchars(
                                    buildPageUrl($p)
                                ) ?>"
                            >
                                <?= $p ?>
                            </a>

                        <?php endif; ?>


                    <?php endfor; ?>



                    <?php if ($page < $totalPages): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl($page + 1)
                            ) ?>"
                        >
                            →
                        </a>

                    <?php else: ?>

                        <span class="disabled">
                            →
                        </span>

                    <?php endif; ?>


                </div>

            <?php endif; ?>


        <?php else: ?>


            <div class="empty-state">

                <div class="empty-icon">
                    📋
                </div>

                <h3>
                    No Attendance Records
                </h3>

                <p>
                    No attendance records were found
                    with the selected filters.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>

</div>


</body>

</html>