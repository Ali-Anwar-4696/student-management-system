<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireApprovedStudent();

require_once __DIR__ . '/../classes/Student.php';
require_once __DIR__ . '/../classes/Result.php';

$pdo = db();
$database = null;


// =====================================================
// STUDENT
// =====================================================

$studentObject =
    new Student($pdo);

$resultObject =
    new Result($pdo);


$userId =
    (int) $_SESSION['user_id'];


$student =
    $studentObject->getStudentByUserId(
        $userId
    );


if (!$student) {

    die(
        'Student profile not found.'
    );
}


$studentId =
    (int) $student['id'];


$classId =
    (int) $student['class_id'];


$sectionId =
    $student['section_id'] !== null
        ? (int) $student['section_id']
        : null;


// =====================================================
// GET RESULT
// =====================================================

$result =
    $resultObject->getStudentResult(
        $studentId,
        $classId,
        $sectionId
    );


$subjects =
    $result['subjects'];


// =====================================================
// HELPER
// =====================================================

function resultNumber(
    ?float $value
): string {

    if ($value === null) {
        return '—';
    }

    return number_format(
        $value,
        2
    );
}


function resultPercent(
    ?float $value
): string {

    if ($value === null) {
        return '—';
    }

    return number_format(
        $value,
        2
    ) . '%';
}


function componentProgress(
    ?float $percentage
): float {

    if ($percentage === null) {
        return 0;
    }

    return max(
        0,
        min(
            100,
            $percentage
        )
    );
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
        My Results | Student Portal
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;

            background:
                #f5f7fb;

            color:
                #172033;
        }


        .page {

            min-height:
                100vh;

            padding:
                28px 18px 60px;
        }


        .container {

            width:
                min(
                    1180px,
                    100%
                );

            margin:
                0 auto;
        }


        /* =====================================================
           HEADER
        ===================================================== */

        .page-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                20px;

            margin-bottom:
                25px;
        }


        .header-left {

            display:
                flex;

            align-items:
                center;

            gap:
                14px;
        }


        .avatar {

            width:
                54px;

            height:
                54px;

            border-radius:
                16px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            color:
                white;

            background:
                linear-gradient(
                    135deg,
                    #2563eb,
                    #4f46e5
                );

            font-size:
                21px;

            font-weight:
                800;

            box-shadow:
                0 8px 20px
                rgba(
                    37,
                    99,
                    235,
                    .20
                );
        }


        .header-title h1 {

            margin:
                0;

            font-size:
                24px;

            font-weight:
                800;
        }


        .header-title p {

            margin:
                4px 0 0;

            color:
                #64748b;

            font-size:
                13px;
        }


        .back-btn {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                10px 15px;

            background:
                white;

            color:
                #475569;

            border:
                1px solid #e2e8f0;

            border-radius:
                9px;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                650;
        }


        /* =====================================================
           STUDENT PROFILE
        ===================================================== */

        .profile-card {

            background:
                white;

            border:
                1px solid #e5e7eb;

            border-radius:
                18px;

            padding:
                22px;

            margin-bottom:
                20px;

            box-shadow:
                0 8px 28px
                rgba(
                    15,
                    23,
                    42,
                    .05
                );
        }


        .profile-grid {

            display:
                grid;

            grid-template-columns:
                1.5fr repeat(
                    3,
                    1fr
                );

            gap:
                14px;
        }


        .profile-item {

            padding:
                13px 15px;

            border:
                1px solid #eef2f7;

            background:
                #fafbfc;

            border-radius:
                11px;
        }


        .profile-item .label {

            color:
                #94a3b8;

            font-size:
                10px;

            text-transform:
                uppercase;

            font-weight:
                750;

            letter-spacing:
                .5px;

            margin-bottom:
                5px;
        }


        .profile-item .value {

            color:
                #334155;

            font-size:
                14px;

            font-weight:
                700;
        }


        /* =====================================================
           OVERALL CARDS
        ===================================================== */

        .overall-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            gap:
                15px;

            margin-bottom:
                22px;
        }


        .summary-card {

            background:
                white;

            border:
                1px solid #e5e7eb;

            border-radius:
                16px;

            padding:
                20px;

            position:
                relative;

            overflow:
                hidden;

            box-shadow:
                0 8px 25px
                rgba(
                    15,
                    23,
                    42,
                    .04
                );
        }


        .summary-card::after {

            content:
                "";

            position:
                absolute;

            width:
                80px;

            height:
                80px;

            border-radius:
                50%;

            background:
                #eff6ff;

            right:
                -30px;

            top:
                -30px;
        }


        .summary-label {

            color:
                #64748b;

            font-size:
                11px;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                .4px;
        }


        .summary-value {

            margin-top:
                8px;

            color:
                #172033;

            font-size:
                25px;

            font-weight:
                800;
        }


        .summary-sub {

            margin-top:
                4px;

            color:
                #94a3b8;

            font-size:
                11px;
        }


        /* =====================================================
           OVERALL RESULT BANNER
        ===================================================== */

        .result-banner {

            display:
                grid;

            grid-template-columns:
                1fr auto;

            gap:
                20px;

            align-items:
                center;

            background:
                linear-gradient(
                    135deg,
                    #172554,
                    #1e3a8a
                );

            color:
                white;

            border-radius:
                18px;

            padding:
                25px;

            margin-bottom:
                25px;

            box-shadow:
                0 15px 35px
                rgba(
                    30,
                    58,
                    138,
                    .18
                );
        }


        .banner-title {

            font-size:
                12px;

            opacity:
                .75;

            text-transform:
                uppercase;

            letter-spacing:
                1px;

            font-weight:
                700;
        }


        .banner-main {

            display:
                flex;

            align-items:
                baseline;

            gap:
                8px;

            margin-top:
                5px;
        }


        .banner-percentage {

            font-size:
                38px;

            font-weight:
                850;
        }


        .banner-total {

            font-size:
                14px;

            opacity:
                .7;
        }


        .banner-grade {

            min-width:
                105px;

            text-align:
                center;

            border:
                1px solid
                rgba(
                    255,
                    255,
                    255,
                    .22
                );

            background:
                rgba(
                    255,
                    255,
                    255,
                    .10
                );

            border-radius:
                14px;

            padding:
                13px;
        }


        .banner-grade small {

            display:
                block;

            font-size:
                10px;

            opacity:
                .7;

            text-transform:
                uppercase;
        }


        .banner-grade strong {

            display:
                block;

            margin-top:
                2px;

            font-size:
                28px;
        }


        /* =====================================================
           SECTION TITLE
        ===================================================== */

        .section-heading {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            margin:
                28px 0 14px;
        }


        .section-heading h2 {

            margin:
                0;

            font-size:
                18px;

            font-weight:
                800;
        }


        .section-heading span {

            color:
                #94a3b8;

            font-size:
                12px;
        }


        /* =====================================================
           SUBJECT CARD
        ===================================================== */

        .subjects {

            display:
                grid;

            gap:
                15px;
        }


        .subject-card {

            background:
                white;

            border:
                1px solid #e5e7eb;

            border-radius:
                16px;

            overflow:
                hidden;

            box-shadow:
                0 7px 25px
                rgba(
                    15,
                    23,
                    42,
                    .04
                );
        }


        .subject-header {

            padding:
                19px 20px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                15px;

            border-bottom:
                1px solid #f1f5f9;
        }


        .subject-info {

            display:
                flex;

            align-items:
                center;

            gap:
                12px;
        }


        .subject-icon {

            width:
                42px;

            height:
                42px;

            border-radius:
                11px;

            background:
                #eff6ff;

            color:
                #2563eb;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                18px;

            font-weight:
                800;
        }


        .subject-info h3 {

            margin:
                0;

            font-size:
                15px;

            font-weight:
                800;
        }


        .subject-code {

            margin-top:
                3px;

            color:
                #94a3b8;

            font-size:
                10px;

            font-weight:
                650;
        }


        .subject-result {

            display:
                flex;

            align-items:
                center;

            gap:
                10px;
        }


        .subject-percentage {

            font-size:
                19px;

            font-weight:
                850;
        }


        .grade-badge {

            min-width:
                39px;

            height:
                34px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            border-radius:
                9px;

            font-size:
                12px;

            font-weight:
                800;
        }


        .grade-excellent {

            background:
                #dcfce7;

            color:
                #15803d;
        }


        .grade-good {

            background:
                #dbeafe;

            color:
                #1d4ed8;
        }


        .grade-average {

            background:
                #fef3c7;

            color:
                #a16207;
        }


        .grade-fail {

            background:
                #fee2e2;

            color:
                #b91c1c;
        }


        .grade-neutral {

            background:
                #f1f5f9;

            color:
                #64748b;
        }


        .status-pill {

            padding:
                5px 9px;

            border-radius:
                20px;

            font-size:
                9px;

            font-weight:
                750;

            text-transform:
                uppercase;
        }


        .status-passed {

            background:
                #dcfce7;

            color:
                #15803d;
        }


        .status-failed {

            background:
                #fee2e2;

            color:
                #b91c1c;
        }


        .status-incomplete {

            background:
                #fef3c7;

            color:
                #a16207;
        }


        .status-provisional {

            background:
                #e0e7ff;

            color:
                #4338ca;
        }


        /* =====================================================
           COMPONENTS
        ===================================================== */

        .component-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    4,
                    1fr
                );

            padding:
                18px 20px;

            gap:
                12px;
        }


        .component {

            border:
                1px solid #eef2f7;

            border-radius:
                11px;

            padding:
                13px;

            background:
                #fafbfc;
        }


        .component-top {

            display:
                flex;

            justify-content:
                space-between;

            gap:
                8px;

            margin-bottom:
                9px;
        }


        .component-name {

            color:
                #64748b;

            font-size:
                10px;

            font-weight:
                750;

            text-transform:
                uppercase;
        }


        .component-marks {

            color:
                #334155;

            font-size:
                11px;

            font-weight:
                800;

            white-space:
                nowrap;
        }


        .progress {

            height:
                6px;

            background:
                #e2e8f0;

            border-radius:
                10px;

            overflow:
                hidden;
        }


        .progress-bar {

            height:
                100%;

            border-radius:
                inherit;

            background:
                linear-gradient(
                    90deg,
                    #2563eb,
                    #4f46e5
                );
        }


        .component-bottom {

            display:
                flex;

            justify-content:
                space-between;

            margin-top:
                7px;

            color:
                #94a3b8;

            font-size:
                9px;
        }


        /* =====================================================
           SUBJECT FOOTER / DETAILS
        ===================================================== */

        .subject-footer {

            padding:
                14px 20px;

            border-top:
                1px solid #f1f5f9;

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            background:
                #fcfdff;
        }


        .subject-total {

            color:
                #475569;

            font-size:
                12px;

            font-weight:
                700;
        }


        .subject-total strong {

            color:
                #172033;

            font-size:
                14px;
        }


        .detail-toggle {

            border:
                0;

            background:
                transparent;

            color:
                #2563eb;

            cursor:
                pointer;

            font-family:
                inherit;

            font-size:
                11px;

            font-weight:
                700;
        }


        .details {

            display:
                none;

            padding:
                0 20px 18px;

            background:
                #fcfdff;
        }


        .details.open {

            display:
                block;
        }


        .detail-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap:
                9px;
        }


        .detail-item {

            padding:
                10px;

            border-radius:
                8px;

            background:
                white;

            border:
                1px solid #edf2f7;

            font-size:
                10px;

            color:
                #64748b;
        }


        .detail-item strong {

            color:
                #334155;

            display:
                block;

            margin-bottom:
                3px;

            font-size:
                11px;
        }


        /* =====================================================
           EMPTY STATE
        ===================================================== */

        .empty {

            background:
                white;

            border:
                1px solid #e5e7eb;

            border-radius:
                18px;

            padding:
                55px 25px;

            text-align:
                center;
        }


        .empty-icon {

            width:
                60px;

            height:
                60px;

            margin:
                0 auto 14px;

            border-radius:
                16px;

            background:
                #f1f5f9;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            font-size:
                25px;
        }


        .empty h3 {

            margin:
                0 0 6px;

            font-size:
                16px;
        }


        .empty p {

            margin:
                0;

            color:
                #94a3b8;

            font-size:
                12px;

            line-height:
                1.6;
        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 900px) {

            .profile-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }


            .overall-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }


            .component-grid {

                grid-template-columns:
                    repeat(
                        2,
                        1fr
                    );
            }
        }


        @media (max-width: 600px) {

            .page {

                padding:
                    18px 11px 40px;
            }


            .page-header {

                align-items:
                    flex-start;
            }


            .header-title h1 {

                font-size:
                    20px;
            }


            .back-btn {

                padding:
                    8px 11px;
            }


            .profile-grid {

                grid-template-columns:
                    1fr;
            }


            .overall-grid {

                grid-template-columns:
                    1fr 1fr;
            }


            .result-banner {

                grid-template-columns:
                    1fr;

                padding:
                    20px;
            }


            .banner-grade {

                width:
                    100%;
            }


            .subject-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }


            .subject-result {

                width:
                    100%;

                justify-content:
                    space-between;
            }


            .component-grid {

                grid-template-columns:
                    1fr;
            }
        }


        @media (max-width: 380px) {

            .overall-grid {

                grid-template-columns:
                    1fr;
            }


            .header-left {

                align-items:
                    flex-start;
            }


            .avatar {

                width:
                    46px;

                height:
                    46px;
            }
        }

    </style>

</head>


<body>


<div class="page">

<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div class="header-left">

            <div class="avatar">

                <?= htmlspecialchars(
                    strtoupper(
                        mb_substr(
                            $student['name'],
                            0,
                            1
                        )
                    )
                ) ?>

            </div>


            <div class="header-title">

                <h1>
                    My Academic Results
                </h1>

                <p>
                    Complete academic performance overview
                </p>

            </div>

        </div>


        <a
            href="dashboard.php"
            class="back-btn"
        >
            ← Dashboard
        </a>

    </div>



    <!-- =====================================================
         STUDENT PROFILE
    ====================================================== -->

    <div class="profile-card">

        <div class="profile-grid">


            <div class="profile-item">

                <div class="label">
                    Student
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $student['name']
                    ) ?>

                </div>

            </div>


            <div class="profile-item">

                <div class="label">
                    Student ID
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $student['student_id']
                    ) ?>

                </div>

            </div>


            <div class="profile-item">

                <div class="label">
                    Class
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $student['class_name']
                        ?? 'N/A'
                    ) ?>

                </div>

            </div>


            <div class="profile-item">

                <div class="label">
                    Section
                </div>

                <div class="value">

                    <?= htmlspecialchars(
                        $student['section_name']
                        ?? 'Whole Class'
                    ) ?>

                </div>

            </div>

        </div>

    </div>



    <?php if (
        empty($subjects)
    ): ?>


        <!-- =================================================
             NO SUBJECTS
        ================================================== -->

        <div class="empty">

            <div class="empty-icon">
                📊
            </div>

            <h3>
                No Result Data Available
            </h3>

            <p>
                Your class does not currently have
                any assigned subjects or result records.
            </p>

        </div>


    <?php else: ?>


        <!-- =================================================
             OVERALL SUMMARY
        ================================================== -->

        <div class="overall-grid">


            <div class="summary-card">

                <div class="summary-label">
                    Total Subjects
                </div>

                <div class="summary-value">

                    <?= $result['total_subjects'] ?>

                </div>

                <div class="summary-sub">

                    <?= $result['completed_subjects'] ?>
                    completed

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Total Obtained
                </div>

                <div class="summary-value">

                    <?= resultNumber(
                        $result['overall_obtained']
                    ) ?>

                </div>

                <div class="summary-sub">

                    out of
                    <?= resultNumber(
                        $result['overall_total']
                    ) ?>

                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Overall Percentage
                </div>

                <div class="summary-value">

                    <?= resultPercent(
                        $result['overall_percentage']
                    ) ?>

                </div>

                <div class="summary-sub">
                    Academic performance
                </div>

            </div>


            <div class="summary-card">

                <div class="summary-label">
                    Result Status
                </div>


                <div class="summary-value"
                     style="font-size:18px;">

                    <?php if (
                        $result['incomplete_subjects'] > 0
                    ): ?>

                        Provisional

                    <?php elseif (
                        $result['overall_passed'] === true
                    ): ?>

                        Passed

                    <?php elseif (
                        $result['overall_passed'] === false
                    ): ?>

                        Needs Improvement

                    <?php else: ?>

                        Pending

                    <?php endif; ?>

                </div>


                <div class="summary-sub">

                    <?= $result['incomplete_subjects'] ?>

                    incomplete subject(s)

                </div>

            </div>

        </div>



        <!-- =================================================
             OVERALL RESULT
        ================================================== -->

        <div class="result-banner">

            <div>

                <div class="banner-title">
                    Overall Result
                </div>


                <div class="banner-main">

                    <span class="banner-percentage">

                        <?= resultPercent(
                            $result['overall_percentage']
                        ) ?>

                    </span>


                    <span class="banner-total">

                        <?= resultNumber(
                            $result['overall_obtained']
                        ) ?>

                        /

                        <?= resultNumber(
                            $result['overall_total']
                        ) ?>

                    </span>

                </div>

            </div>


            <div class="banner-grade">

                <small>
                    Overall Grade
                </small>

                <strong>
                    <?= htmlspecialchars(
                        $result['overall_grade']
                    ) ?>
                </strong>

            </div>

        </div>



        <!-- =================================================
             INDIVIDUAL EXAM MARKS
        ================================================== -->

        <div class="section-heading">
            <h2>Exam-wise Marks</h2>
            <span>Individual exam results by type</span>
        </div>

        <div class="subjects">
            <?php
            // Get all exams for this student's class
            $examStmt = $pdo->prepare("
                SELECT e.id, e.name, e.type, e.class_id
                FROM exams e
                WHERE e.class_id = :class_id
                ORDER BY e.type, e.id
            ");
            $examStmt->execute([':class_id' => $classId]);
            $allExams = $examStmt->fetchAll();

            foreach ($allExams as $exam):

                /*
                 * Marks are looked up by EXAM ID, across every subject the
                 * student has. Passing an undefined/zero subject id here
                 * used to make this whole section permanently empty.
                 */
                $individualMarks = $resultObject->getStudentMarksByExam(
                    $studentId,
                    $classId,
                    (int) $exam['id']
                );

                if (empty($individualMarks)) {
                    continue;
                }
            ?>
                <div class="subject-card">
                    <div class="subject-header">
                        <div class="subject-info">
                            <div class="subject-icon"><?= e(strtoupper(substr($exam['name'], 0, 1))) ?></div>
                            <div>
                                <h3><?= e($exam['name']) ?></h3>
                                <div class="subject-code"><?= e(ucfirst($exam['type'])) ?> Exam</div>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 15px 20px;">
                        <?php foreach ($individualMarks as $mark): ?>
                            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9;">
                                <span><?= e($mark['subject_name']) ?></span>
                                <strong><?= number_format($mark['obtained_marks'], 2) ?> / <?= number_format($mark['total_marks'], 2) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =================================================
             SUBJECT RESULTS
        ================================================== -->

        <div class="section-heading">

            <h2>
                Subject-wise Results
            </h2>

            <span>
                Detailed academic breakdown
            </span>

        </div>


        <div class="subjects">


            <?php foreach (
                $subjects
                as $index => $subject
            ): ?>


                <?php

                $percentage =
                    $subject['percentage'];

                $gradeClass =
                    'grade-' .
                    (
                        $subject['grade_class']
                        ?? 'neutral'
                    );

                ?>


                <div class="subject-card">


                    <!-- =====================================
                         SUBJECT HEADER
                    ====================================== -->

                    <div class="subject-header">


                        <div class="subject-info">

                            <div class="subject-icon">

                                <?= $index + 1 ?>

                            </div>


                            <div>

                                <h3>

                                    <?= htmlspecialchars(
                                        $subject['subject_name']
                                    ) ?>

                                </h3>


                                <?php if (
                                    !empty(
                                        $subject['subject_code']
                                    )
                                ): ?>

                                    <div class="subject-code">

                                        <?= htmlspecialchars(
                                            $subject['subject_code']
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>



                        <div class="subject-result">


                            <div class="subject-percentage">

                                <?= resultPercent(
                                    $percentage
                                ) ?>

                            </div>


                            <span
                                class="grade-badge <?= $gradeClass ?>"
                            >

                                <?= htmlspecialchars(
                                    $subject['grade']
                                ) ?>

                            </span>


                            <?php if (
                                $subject['status']
                                === 'incomplete'
                            ): ?>

                                <span class="status-pill status-incomplete">

                                    Incomplete

                                </span>

                            <?php elseif (
                                $subject['status']
                                === 'provisional'
                            ): ?>

                                <span class="status-pill status-provisional">

                                    Provisional

                                </span>

                            <?php elseif (
                                $subject['passed']
                                === true
                            ): ?>

                                <span class="status-pill status-passed">

                                    Passed

                                </span>

                            <?php elseif (
                                $subject['passed']
                                === false
                            ): ?>

                                <span class="status-pill status-failed">

                                    Failed

                                </span>

                            <?php endif; ?>

                        </div>

                    </div>



                    <?php if (
                        $subject['configured']
                    ): ?>


                        <!-- =================================
                             COMPONENTS
                        ================================== -->

                        <div class="component-grid">


                            <?php foreach (
                                $subject['components']
                                as $component
                            ): ?>


                                <?php

                                $rawPercentage =
                                    $component[
                                        'raw_percentage'
                                    ];

                                ?>


                                <div class="component">


                                    <div class="component-top">

                                        <span class="component-name">

                                            <?= htmlspecialchars(
                                                $component['label']
                                            ) ?>

                                        </span>


                                        <span class="component-marks">

                                            <?= resultNumber(
                                                $component['obtained']
                                            ) ?>

                                            /

                                            <?= resultNumber(
                                                $component['total']
                                            ) ?>

                                        </span>

                                    </div>


                                    <div class="progress">

                                        <div
                                            class="progress-bar"
                                            style="
                                                width:
                                                <?= componentProgress(
                                                    $rawPercentage
                                                ) ?>%;
                                            "
                                        ></div>

                                    </div>


                                    <div class="component-bottom">

                                        <span>

                                            <?php if (
                                                $rawPercentage !== null
                                            ): ?>

                                                <?= resultPercent(
                                                    $rawPercentage
                                                ) ?>

                                            <?php else: ?>

                                                No data

                                            <?php endif; ?>

                                        </span>


                                        <span>

                                            <?php

                                            $status =
                                                $component['status']
                                                ?? '';

                                            if (
                                                $status ===
                                                'complete'
                                            ) {

                                                echo 'Completed';

                                            } elseif (
                                                $status ===
                                                'incomplete'
                                            ) {

                                                echo 'Incomplete';

                                            } else {

                                                echo 'Pending';
                                            }

                                            ?>

                                        </span>

                                    </div>

                                </div>


                            <?php endforeach; ?>

                        </div>



                        <!-- =================================
                             SUBJECT FOOTER
                        ================================== -->

                        <div class="subject-footer">


                            <div class="subject-total">

                                Subject Total:

                                <strong>

                                    <?= resultNumber(
                                        $subject['obtained_marks']
                                    ) ?>

                                    /

                                    <?= resultNumber(
                                        $subject['total_marks']
                                    ) ?>

                                </strong>

                            </div>


                            <button
                                type="button"
                                class="detail-toggle"
                                onclick="toggleDetails(
                                    <?= (int) $subject['subject_id'] ?>
                                )"
                            >
                                View Details ↓
                            </button>

                        </div>



                        <!-- =================================
                             DETAILS
                        ================================== -->

                        <div
                            class="details"
                            id="details-<?= (int) $subject['subject_id'] ?>"
                        >

                            <div class="detail-grid">


                                <div class="detail-item">

                                    <strong>
                                        Assignments
                                    </strong>

                                    <?php

                                    $assignment =
                                        $subject['components']['assignment'];

                                    ?>

                                    <?= resultNumber(
                                        $assignment['obtained']
                                    ) ?>

                                    /
                                    <?= resultNumber(
                                        $assignment['total']
                                    ) ?>

                                    — Raw:

                                    <?= resultPercent(
                                        $assignment['raw_percentage']
                                    ) ?>

                                    <?php if (
                                        isset(
                                            $assignment['graded_count']
                                        )
                                    ): ?>

                                        <br>

                                        Graded:

                                        <?= (int)
                                            $assignment['graded_count'] ?>

                                        /

                                        <?= (int)
                                            $assignment['item_count'] ?>

                                    <?php endif; ?>

                                </div>



                                <div class="detail-item">

                                    <strong>
                                        Attendance
                                    </strong>

                                    <?php

                                    $attendance =
                                        $subject['components']['attendance'];

                                    ?>

                                    <?= resultNumber(
                                        $attendance['obtained']
                                    ) ?>

                                    /
                                    <?= resultNumber(
                                        $attendance['total']
                                    ) ?>

                                    — Raw:

                                    <?= resultPercent(
                                        $attendance['raw_percentage']
                                    ) ?>

                                    <br>

                                    Present:

                                    <?= (int)
                                        $attendance['present'] ?>

                                    &nbsp;|&nbsp;

                                    Absent:

                                    <?= (int)
                                        $attendance['absent'] ?>

                                </div>



                                <div class="detail-item">

                                    <strong>
                                        Midterm
                                    </strong>

                                    <?php

                                    $midterm =
                                        $subject['components']['midterm'];

                                    ?>

                                    <?= resultNumber(
                                        $midterm['obtained']
                                    ) ?>

                                    /
                                    <?= resultNumber(
                                        $midterm['total']
                                    ) ?>

                                    — Raw:

                                    <?= resultPercent(
                                        $midterm['raw_percentage']
                                    ) ?>

                                </div>



                                <div class="detail-item">

                                    <strong>
                                        Final
                                    </strong>

                                    <?php

                                    $final =
                                        $subject['components']['final'];

                                    ?>

                                    <?= resultNumber(
                                        $final['obtained']
                                    ) ?>

                                    /
                                    <?= resultNumber(
                                        $final['total']
                                    ) ?>

                                    — Raw:

                                    <?= resultPercent(
                                        $final['raw_percentage']
                                    ) ?>

                                </div>


                            </div>

                        </div>


                    <?php else: ?>


                        <?php if (!empty($subject['raw_marks'])): ?>


                            <div
                                style="
                                    padding:20px;
                                    background:#fcfdff;
                                "
                            >

                                <div
                                    style="
                                        font-size:12px;
                                        color:#64748b;
                                        margin-bottom:10px;
                                    "
                                >

                                    Recorded exam marks.

                                    Component weighting has not yet
                                    been published for this subject,
                                    so these marks are shown as
                                    recorded and are not added into
                                    a weighted total.

                                </div>

                                <?php foreach ($subject['raw_marks'] as $rawMark): ?>

                                    <div
                                        style="
                                            display:flex;
                                            justify-content:space-between;
                                            padding:8px 0;
                                            border-bottom:1px solid #f1f5f9;
                                        "
                                    >
                                        <span>
                                            <?= e($rawMark['type']) ?>
                                        </span>

                                        <strong>
                                            <?= number_format(
                                                $rawMark['obtained'],
                                                2
                                            ) ?>

                                            /

                                            <?= number_format(
                                                $rawMark['total'],
                                                2
                                            ) ?>

                                            (<?= resultPercent(
                                                $rawMark['percentage']
                                            ) ?>)
                                        </strong>
                                    </div>

                                <?php endforeach; ?>

                            </div>


                        <?php else: ?>


                            <div
                                style="
                                    padding:20px;
                                    color:#64748b;
                                    font-size:12px;
                                    background:#fcfdff;
                                "
                            >

                                Result configuration has not yet
                                been published for this subject.

                                Your result will appear here after
                                the teacher/admin configures the
                                component marks.

                            </div>


                        <?php endif; ?>


                    <?php endif; ?>


                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</div>

</div>



<script>

    function toggleDetails(
        subjectId
    ) {

        const element =
            document.getElementById(
                'details-' + subjectId
            );


        if (!element) {
            return;
        }


        element.classList.toggle(
            'open'
        );


        const button =
            element
                .previousElementSibling
                ?.querySelector(
                    '.detail-toggle'
                );


        if (!button) {
            return;
        }


        if (
            element.classList.contains(
                'open'
            )
        ) {

            button.textContent =
                'Hide Details ↑';

        } else {

            button.textContent =
                'View Details ↓';
        }
    }

</script>


</body>

</html>