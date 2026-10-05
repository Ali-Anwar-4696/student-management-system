<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Attendance.php';

// =====================================================
// ATTENDANCE ID
// =====================================================

$pdo = db();
$database = null;

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Invalid attendance ID.');
}


// =====================================================
// DATABASE
// =====================================================

$database = new Database();
$pdo = $database->connect();


// =====================================================
// OBJECT
// =====================================================

$attendanceObject = new Attendance($pdo);


// =====================================================
// GET ATTENDANCE
// =====================================================

$attendance = $attendanceObject->getAttendanceById($id);


// =====================================================
// NOT FOUND
// =====================================================

if (!$attendance) {
    http_response_code(404);
    die('Attendance record not found.');
}


// =====================================================
// VALUES
// =====================================================

$studentName = (string) (
    $attendance['student_name']
    ?? $attendance['name']
    ?? '-'
);

$studentId = (string) (
    $attendance['student_id']
    ?? '-'
);

$className = (string) (
    $attendance['class_name']
    ?? '-'
);

$sectionName = (string) (
    $attendance['section_name']
    ?? '-'
);

$subjectName = (string) (
    $attendance['subject_name']
    ?? '-'
);

$subjectCode = (string) (
    $attendance['subject_code']
    ?? ''
);

$date = (string) (
    $attendance['date']
    ?? '-'
);

$status = strtolower(
    (string) (
        $attendance['status']
        ?? ''
    )
);

$markedBy = (string) (
    $attendance['marked_by_name']
    ?? '-'
);

$createdAt = (string) (
    $attendance['created_at']
    ?? '-'
);

$initial = strtoupper(
    substr(
        $studentName,
        0,
        1
    )
);

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
        View Attendance | Student Management
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
            max-width: 1000px;
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


        .back-btn {
            text-decoration: none;

            background: white;
            color: #4b5563;

            border: 1px solid #dfe3ea;

            padding: 10px 15px;

            border-radius: 9px;

            font-size: 12px;
            font-weight: 700;
        }


        .back-btn:hover {
            background: #f8fafc;
            color: #4f46e5;
        }


        /* =================================================
           CARD
        ================================================== */

        .card {
            background: white;

            border: 1px solid #e7eaf0;

            border-radius: 15px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 5px 20px
                rgba(20, 30, 55, 0.04);
        }


        .card-title {
            font-size: 15px;
            font-weight: 800;

            margin-bottom: 20px;
        }


        /* =================================================
           STUDENT HEADER
        ================================================== */

        .student-header {
            display: flex;

            align-items: center;

            gap: 15px;

            padding-bottom: 22px;

            margin-bottom: 22px;

            border-bottom: 1px solid #edf0f4;
        }


        .avatar {
            width: 58px;
            height: 58px;

            border-radius: 14px;

            background: #eef2ff;
            color: #4f46e5;

            display: flex;

            align-items: center;
            justify-content: center;

            font-size: 19px;
            font-weight: 800;
        }


        .student-name {
            font-size: 18px;
            font-weight: 800;

            color: #1f2937;
        }


        .student-id {
            margin-top: 5px;

            color: #8991a3;

            font-size: 12px;
        }


        /* =================================================
           INFORMATION GRID
        ================================================== */

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 16px;
        }


        .info-item {
            padding: 16px;

            border: 1px solid #edf0f4;

            border-radius: 11px;

            background: #fafbfc;
        }


        .info-label {
            color: #8991a3;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.4px;

            margin-bottom: 7px;
        }


        .info-value {
            color: #1f2937;

            font-size: 14px;

            font-weight: 700;
        }


        /* =================================================
           STATUS
        ================================================== */

        .status {
            display: inline-flex;

            padding: 7px 12px;

            border-radius: 8px;

            font-size: 11px;

            font-weight: 800;

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
           ACTIONS
        ================================================== */

        .actions {
            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 22px;

            padding-top: 20px;

            border-top: 1px solid #edf0f4;
        }


        .action-btn {
            text-decoration: none;

            padding: 10px 16px;

            border-radius: 9px;

            font-size: 12px;

            font-weight: 700;
        }


        .edit-btn {
            background: #eef2ff;
            color: #4f46e5;
        }


        .edit-btn:hover {
            background: #e0e7ff;
        }


        .list-btn {
            background: #f3f4f6;
            color: #4b5563;
        }


        .list-btn:hover {
            background: #e5e7eb;
        }


        /* =================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 700px) {

            .page-wrapper {
                padding: 18px;
            }


            .page-header {
                flex-direction: column;

                align-items: flex-start;
            }


            .info-grid {
                grid-template-columns: 1fr;
            }


            .actions {
                justify-content: stretch;

                flex-direction: column;
            }


            .action-btn {
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


            .student-header {
                align-items: flex-start;
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
                📋
            </div>


            <div class="page-title">

                <h1>
                    Attendance Details
                </h1>

                <p>
                    View complete attendance record
                </p>

            </div>


        </div>


        <a
            href="index.php"
            class="back-btn"
        >
            ← Attendance List
        </a>


    </div>



    <!-- =================================================
         DETAILS CARD
    ================================================== -->

    <div class="card">


        <div class="card-title">
            Attendance Information
        </div>



        <!-- STUDENT -->

        <div class="student-header">


            <div class="avatar">

                <?= htmlspecialchars($initial) ?>

            </div>


            <div>

                <div class="student-name">

                    <?= htmlspecialchars($studentName) ?>

                </div>


                <div class="student-id">

                    Student ID:
                    <?= htmlspecialchars($studentId) ?>

                </div>

            </div>


        </div>



        <!-- INFORMATION -->

        <div class="info-grid">


            <!-- CLASS -->

            <div class="info-item">

                <div class="info-label">
                    Class
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($className) ?>

                </div>

            </div>



            <!-- SECTION -->

            <div class="info-item">

                <div class="info-label">
                    Section
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($sectionName) ?>

                </div>

            </div>



            <!-- SUBJECT -->

            <div class="info-item">

                <div class="info-label">
                    Subject
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($subjectName) ?>

                    <?php if ($subjectCode !== ''): ?>

                        <span
                            style="
                                color:#8991a3;
                                font-size:11px;
                            "
                        >

                            —
                            <?= htmlspecialchars($subjectCode) ?>

                        </span>

                    <?php endif; ?>

                </div>

            </div>



            <!-- DATE -->

            <div class="info-item">

                <div class="info-label">
                    Attendance Date
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($date) ?>

                </div>

            </div>



            <!-- STATUS -->

            <div class="info-item">

                <div class="info-label">
                    Attendance Status
                </div>

                <div class="info-value">

                    <span
                        class="status status-<?= htmlspecialchars($status) ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst($status)
                        ) ?>

                    </span>

                </div>

            </div>



            <!-- MARKED BY -->

            <div class="info-item">

                <div class="info-label">
                    Marked By
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($markedBy) ?>

                </div>

            </div>



            <!-- CREATED AT -->

            <div class="info-item">

                <div class="info-label">
                    Created At
                </div>

                <div class="info-value">

                    <?= htmlspecialchars($createdAt) ?>

                </div>

            </div>


        </div>



        <!-- ACTIONS -->

        <div class="actions">


            <a
                href="index.php"
                class="action-btn list-btn"
            >
                ← Back to List
            </a>


            <a
                href="edit.php?id=<?= $id ?>"
                class="action-btn edit-btn"
            >
                ✎ Edit Attendance
            </a>


        </div>


    </div>


</div>

</div>


</body>

</html>