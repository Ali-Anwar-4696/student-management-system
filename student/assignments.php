<?php


require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Assignment.php';

requireStudent();

$pdo = db();

$student = current_student($pdo);

layout_start('My Assignments', 'assignments');


/*
|--------------------------------------------------------------------------
| Student Profile Not Found
|--------------------------------------------------------------------------
*/

if (!$student) {
    ?>

    <style>
        .student-assignments-page {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .assignment-status-card {
            width: 100%;
            max-width: 600px;
            padding: 50px 30px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf3;
            border-radius: 28px;
            box-shadow: 0 22px 65px rgba(15, 23, 42, .08);
        }

        .assignment-status-icon {
            width: 85px;
            height: 85px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 26px;
            background: #fff7e8;
            color: #f59e0b;
            font-size: 35px;
        }

        .assignment-status-card h2 {
            color: #182033;
            font-size: 24px;
            font-weight: 800;
        }

        .assignment-status-card p {
            color: #7b8498;
            line-height: 1.7;
        }
    </style>

    <div class="student-assignments-page">

        <div class="assignment-status-card">

            <div class="assignment-status-icon">
                <i class="bi bi-person-exclamation"></i>
            </div>

            <h2 class="mb-3">
                Student Profile Not Found
            </h2>

            <p class="mb-0">
                Your student profile is not linked yet.
                Please contact the administrator to complete
                your registration.
            </p>

        </div>

    </div>

    <?php
    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Student Status
|--------------------------------------------------------------------------
*/

$studentStatus = strtolower(
    trim((string) ($student['status'] ?? 'pending'))
);


if ($studentStatus !== 'active') {
    ?>

    <style>
        .student-assignments-page {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .assignment-status-card {
            width: 100%;
            max-width: 600px;
            padding: 50px 30px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf3;
            border-radius: 28px;
            box-shadow: 0 22px 65px rgba(15, 23, 42, .08);
        }

        .assignment-status-icon {
            width: 85px;
            height: 85px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 26px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 35px;
        }

        .assignment-status-card h2 {
            color: #182033;
            font-size: 24px;
            font-weight: 800;
        }

        .assignment-status-card p {
            color: #7b8498;
            line-height: 1.7;
        }

        .assignment-status-badge {
            display: inline-flex;
            padding: 8px 15px;
            border-radius: 50px;
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
        }
    </style>

    <div class="student-assignments-page">

        <div class="assignment-status-card">

            <div class="assignment-status-icon">
                <i class="bi bi-person-lock"></i>
            </div>

            <h2 class="mb-3">
                Assignments Unavailable
            </h2>

            <p class="mb-3">
                Your student account currently has this status:
            </p>

            <span class="assignment-status-badge">
                <?= e(ucfirst($studentStatus)) ?>
            </span>

            <p class="small mt-4 mb-0">
                Please contact the administrator if you
                believe this status is incorrect.
            </p>

        </div>

    </div>

    <?php
    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Check Class / Section
|--------------------------------------------------------------------------
*/

/*
 * Only the class is required: whole-class assignments
 * (section_id IS NULL) are available to every student of
 * the class, including students without a section. The
 * query below filters concrete sections automatically.
 */
if (empty($student['class_id'])) {
    ?>

    <style>
        .student-assignments-page {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .assignment-status-card {
            width: 100%;
            max-width: 650px;
            padding: 50px 30px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf3;
            border-radius: 28px;
            box-shadow: 0 22px 65px rgba(15, 23, 42, .08);
        }

        .assignment-status-icon {
            width: 85px;
            height: 85px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 26px;
            background: #f0f0ff;
            color: #5b5ce2;
            font-size: 35px;
        }

        .assignment-status-card h2 {
            color: #182033;
            font-size: 24px;
            font-weight: 800;
        }

        .assignment-status-card p {
            color: #7b8498;
            line-height: 1.7;
        }
    </style>

    <div class="student-assignments-page">

        <div class="assignment-status-card">

            <div class="assignment-status-icon">
                <i class="bi bi-mortarboard"></i>
            </div>

            <h2 class="mb-3">
                Class Not Assigned Yet
            </h2>

            <p class="mb-0">
                Your class has not been assigned yet.
                Assignments will become available after the
                administrator completes your academic placement.
            </p>

        </div>

    </div>

    <?php
    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Student IDs
|--------------------------------------------------------------------------
*/

$studentId = (int) $student['id'];

$classId = (int) $student['class_id'];

$sectionId = (int) $student['section_id'];


/*
|--------------------------------------------------------------------------
| Get Assignments
|--------------------------------------------------------------------------
*/

$assignmentManager = new Assignment($pdo);

$assignments = $assignmentManager->getStudentAssignments(
    $classId,
    $sectionId,
    $studentId
);


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalAssignments = count($assignments);

$pendingCount = 0;

$submittedCount = 0;

$gradedCount = 0;


foreach ($assignments as $assignment) {

    $submissionStatus = strtolower(
        trim(
            (string) (
                $assignment['submission_status']
                ?? 'pending'
            )
        )
    );

    if ($submissionStatus === 'submitted') {

        $submittedCount++;

    } elseif ($submissionStatus === 'graded') {

        $gradedCount++;

    } else {

        $pendingCount++;
    }
}

?>

<style>

/* =========================================================
   MAIN
========================================================= */

.student-assignments {
    --primary: #5b5ce2;
    --text: #182033;
    --muted: #7b8498;
    --border: #e8ebf3;

    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 5px 0 35px;
}


/* =========================================================
   HERO
========================================================= */

.assignments-hero {
    position: relative;
    overflow: hidden;
    padding: 30px;
    margin-bottom: 20px;
    border-radius: 28px;
    color: #fff;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #6366f1 48%,
            #7c3aed
        );

    box-shadow:
        0 20px 50px rgba(79,70,229,.18);
}

.assignments-hero::before {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    right: -75px;
    top: -135px;
    border-radius: 50%;
    background: rgba(255,255,255,.09);
}

.assignments-hero::after {
    content: "";
    position: absolute;
    width: 145px;
    height: 145px;
    right: 190px;
    bottom: -100px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
}

.assignments-hero-content {
    position: relative;
    z-index: 2;

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 20px;
}

.assignments-heading {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}

.assignments-icon {
    width: 59px;
    height: 59px;
    flex: 0 0 59px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 19px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.18);

    font-size: 24px;
}

.assignments-eyebrow {
    margin-bottom: 4px;
    color: rgba(255,255,255,.68);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .13em;
    text-transform: uppercase;
}

.assignments-title {
    margin: 0;
    font-size: clamp(23px, 3vw, 31px);
    font-weight: 850;
}

.assignments-description {
    margin-top: 5px;
    color: rgba(255,255,255,.75);
    font-size: 12px;
}

.student-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    padding: 9px 13px;

    border-radius: 50px;

    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.16);

    font-size: 11px;
    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   SUMMARY
========================================================= */

.assignment-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 13px;

    min-width: 0;

    padding: 17px;

    border: 1px solid var(--border);
    border-radius: 18px;

    background: #fff;

    box-shadow:
        0 7px 25px rgba(30,41,59,.045);
}

.summary-icon {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 13px;
    font-size: 17px;
}

.summary-total {
    color: #5b5ce2;
    background: #f0f0ff;
}

.summary-pending {
    color: #d97706;
    background: #fffbeb;
}

.summary-submitted {
    color: #059669;
    background: #ecfdf5;
}

.summary-label {
    color: var(--muted);
    font-size: 10px;
    font-weight: 750;
    margin-bottom: 3px;
}

.summary-number {
    color: var(--text);
    font-size: 21px;
    font-weight: 850;
}


/* =========================================================
   SECTION
========================================================= */

.assignments-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 13px;
}

.section-title {
    margin: 0;
    color: var(--text);
    font-size: 16px;
    font-weight: 820;
}

.section-subtitle {
    color: var(--muted);
    font-size: 10px;
    margin-top: 3px;
}

.assignment-count {
    padding: 7px 11px;
    border-radius: 50px;

    color: #5b5ce2;
    background: #f1f1ff;

    font-size: 9px;
    font-weight: 800;

    white-space: nowrap;
}


/* =========================================================
   GRID
========================================================= */

.assignment-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}


/* =========================================================
   CARD
========================================================= */

.assignment-card {
    position: relative;

    display: flex;
    flex-direction: column;

    min-width: 0;

    overflow: hidden;

    padding: 20px;

    border: 1px solid var(--border);
    border-radius: 21px;

    background: #fff;

    box-shadow:
        0 8px 28px rgba(30,41,59,.05);

    transition:
        transform .22s ease,
        box-shadow .22s ease,
        border-color .22s ease;
}

.assignment-card:hover {
    transform: translateY(-4px);

    border-color: #d9d9ff;

    box-shadow:
        0 17px 42px rgba(30,41,59,.09);
}

.assignment-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;

    gap: 12px;

    margin-bottom: 16px;
}

.assignment-file-icon {
    width: 45px;
    height: 45px;
    flex: 0 0 45px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 14px;

    color: #5b5ce2;
    background: #f1f1ff;

    font-size: 18px;
}

.assignment-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;

    padding: 6px 9px;

    border-radius: 50px;

    font-size: 9px;
    font-weight: 800;

    white-space: nowrap;
}

.assignment-status.pending {
    color: #b45309;
    background: #fffbeb;
}

.assignment-status.submitted {
    color: #047857;
    background: #ecfdf5;
}

.assignment-status.graded {
    color: #1d4ed8;
    background: #eff6ff;
}

.assignment-title {
    color: var(--text);
    font-size: 16px;
    line-height: 1.35;
    font-weight: 820;

    margin-bottom: 13px;

    overflow-wrap: anywhere;
}


/* =========================================================
   INFO
========================================================= */

.assignment-info-list {
    display: grid;
    gap: 8px;
    margin-bottom: 14px;
}

.assignment-info {
    display: flex;
    align-items: center;

    gap: 8px;

    min-width: 0;

    color: var(--muted);
    font-size: 10px;
}

.assignment-info i {
    width: 18px;
    flex: 0 0 18px;

    color: #929aaa;
    text-align: center;
}

.assignment-info strong {
    color: #4b5563;
    font-weight: 750;
}

.assignment-info span {
    min-width: 0;
    overflow-wrap: anywhere;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.assignment-description {
    flex: 1;

    margin-bottom: 16px;
    padding: 12px;

    border: 1px solid #eef0f5;
    border-radius: 13px;

    background: #fafbfe;
}

.description-label {
    color: #929aaa;

    font-size: 9px;
    font-weight: 800;

    text-transform: uppercase;
    letter-spacing: .06em;

    margin-bottom: 5px;
}

.description-text {
    color: #667085;

    font-size: 10px;
    line-height: 1.6;

    overflow-wrap: anywhere;
}


/* =========================================================
   FOOTER
========================================================= */

.assignment-footer {
    display: flex;
    align-items: center;

    gap: 9px;

    margin-top: auto;
    padding-top: 14px;

    border-top: 1px solid #f0f2f6;
}

.submit-button,
.completed-button {
    flex: 1;

    min-height: 39px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    gap: 6px;

    padding: 8px 12px;

    border-radius: 11px;

    font-size: 10px;
    font-weight: 800;
}

.submit-button {
    color: #fff;

    background:
        linear-gradient(
            135deg,
            #5b5ce2,
            #7c3aed
        );

    text-decoration: none;

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}

.submit-button:hover {
    color: #fff;
    transform: translateY(-1px);

    box-shadow:
        0 8px 18px rgba(91,92,226,.20);
}

.completed-submitted {
    color: #047857;
    background: #ecfdf5;
}

.completed-graded {
    color: #1d4ed8;
    background: #eff6ff;
}


/* =========================================================
   EMPTY
========================================================= */

.assignment-empty {
    padding: 65px 20px;

    text-align: center;

    border: 1px solid var(--border);
    border-radius: 23px;

    background: #fff;

    box-shadow:
        0 8px 28px rgba(30,41,59,.04);
}

.empty-icon {
    width: 72px;
    height: 72px;

    margin: 0 auto 16px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 22px;

    color: #8b95a7;
    background: #f7f8fb;

    font-size: 28px;
}

.empty-title {
    color: var(--text);
    font-size: 16px;
    font-weight: 800;
    margin-bottom: 6px;
}

.empty-text {
    max-width: 440px;
    margin: 0 auto;

    color: var(--muted);

    font-size: 11px;
    line-height: 1.7;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1199.98px) {

    .assignment-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}


@media (max-width: 767.98px) {

    .student-assignments {
        padding: 2px 0 25px;
    }

    .assignments-hero {
        padding: 22px;
        border-radius: 22px;
    }

    .assignments-hero-content {
        align-items: flex-start;
        flex-direction: column;
    }

    .assignments-icon {
        width: 50px;
        height: 50px;
        flex-basis: 50px;
        border-radius: 15px;
        font-size: 21px;
    }

    .assignments-title {
        font-size: 24px;
    }

    .assignments-description {
        font-size: 10px;
    }

    .student-pill {
        align-self: flex-start;
    }

    .assignment-summary {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .summary-card {
        min-height: 74px;
        border-radius: 16px;
    }

    .assignment-grid {
        grid-template-columns: 1fr;
        gap: 13px;
    }

    .assignment-card {
        border-radius: 18px;
        padding: 17px;
    }

    .assignment-title {
        font-size: 15px;
    }
}


@media (max-width: 480px) {

    .assignments-heading {
        align-items: flex-start;
    }

    .assignments-title {
        font-size: 21px;
    }

    .assignments-icon {
        width: 46px;
        height: 46px;
        flex-basis: 46px;
    }

    .assignment-card {
        padding: 15px;
        border-radius: 17px;
    }

    .assignment-file-icon {
        width: 42px;
        height: 42px;
        flex-basis: 42px;
    }

    .assignment-status {
        font-size: 8px;
        padding: 5px 8px;
    }

    .assignment-footer {
        flex-direction: column;
    }

    .submit-button,
    .completed-button {
        width: 100%;
    }
}


@media (max-width: 360px) {

    .assignments-hero {
        padding: 18px;
        border-radius: 19px;
    }

    .assignments-title {
        font-size: 19px;
    }

    .assignments-description {
        font-size: 9px;
    }

    .student-pill {
        font-size: 9px;
        padding: 7px 10px;
    }

    .assignment-card {
        padding: 14px;
    }

    .assignment-title {
        font-size: 14px;
    }

    .assignment-info {
        font-size: 9px;
    }

    .description-text {
        font-size: 9px;
    }
}

</style>


<div class="student-assignments">

    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="assignments-hero">

        <div class="assignments-hero-content">

            <div class="assignments-heading">

                <div class="assignments-icon">
                    <i class="bi bi-journal-text"></i>
                </div>

                <div>

                    <div class="assignments-eyebrow">
                        Student Portal
                    </div>

                    <h1 class="assignments-title">
                        My Assignments
                    </h1>

                    <div class="assignments-description">
                        View your assignments, deadlines and
                        submission status.
                    </div>

                </div>

            </div>


            <div class="student-pill">

                <i class="bi bi-person-circle"></i>

                <?= e(
                    (string) (
                        $student['name']
                        ?? 'Student'
                    )
                ) ?>

            </div>

        </div>

    </section>


    <!-- =====================================================
         SUMMARY
    ====================================================== -->

    <div class="assignment-summary">

        <div class="summary-card">

            <div class="summary-icon summary-total">
                <i class="bi bi-journal-text"></i>
            </div>

            <div>

                <div class="summary-label">
                    Total Assignments
                </div>

                <div class="summary-number">
                    <?= $totalAssignments ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon summary-pending">
                <i class="bi bi-clock-history"></i>
            </div>

            <div>

                <div class="summary-label">
                    Pending
                </div>

                <div class="summary-number">
                    <?= $pendingCount ?>
                </div>

            </div>

        </div>


        <div class="summary-card">

            <div class="summary-icon summary-submitted">
                <i class="bi bi-check2-circle"></i>
            </div>

            <div>

                <div class="summary-label">
                    Submitted / Graded
                </div>

                <div class="summary-number">
                    <?= $submittedCount + $gradedCount ?>
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         SECTION HEADER
    ====================================================== -->

    <div class="assignments-section-head">

        <div>

            <h2 class="section-title">
                Available Assignments
            </h2>

            <div class="section-subtitle">
                Assignments assigned to your class and section
            </div>

        </div>

        <div class="assignment-count">

            <?= $totalAssignments ?>

            <?= $totalAssignments === 1
                ? 'Assignment'
                : 'Assignments'
            ?>

        </div>

    </div>


    <!-- =====================================================
         ASSIGNMENTS
    ====================================================== -->

    <?php if (empty($assignments)): ?>

        <div class="assignment-empty">

            <div class="empty-icon">
                <i class="bi bi-journal-x"></i>
            </div>

            <div class="empty-title">
                No Assignments Found
            </div>

            <p class="empty-text">
                There are currently no assignments assigned
                to your class and section. New assignments
                will appear here when your teacher publishes them.
            </p>

        </div>

    <?php else: ?>

        <div class="assignment-grid">

            <?php foreach ($assignments as $assignment): ?>

                <?php

                $submissionStatus = strtolower(
                    trim(
                        (string) (
                            $assignment['submission_status']
                            ?? 'pending'
                        )
                    )
                );

                if (
                    !in_array(
                        $submissionStatus,
                        [
                            'pending',
                            'submitted',
                            'graded'
                        ],
                        true
                    )
                ) {
                    $submissionStatus = 'pending';
                }

                ?>

                <article class="assignment-card">


                    <!-- TOP -->

                    <div class="assignment-top">

                        <div class="assignment-file-icon">

                            <i class="bi bi-file-earmark-text"></i>

                        </div>


                        <?php if (
                            $submissionStatus === 'graded'
                        ): ?>

                            <span class="assignment-status graded">

                                <i class="bi bi-patch-check-fill"></i>

                                Graded

                            </span>

                        <?php elseif (
                            $submissionStatus === 'submitted'
                        ): ?>

                            <span class="assignment-status submitted">

                                <i class="bi bi-check-circle-fill"></i>

                                Submitted

                            </span>

                        <?php else: ?>

                            <span class="assignment-status pending">

                                <i class="bi bi-clock-fill"></i>

                                Pending

                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- TITLE -->

                    <h3 class="assignment-title">

                        <?= e(
                            (string) (
                                $assignment['title']
                                ?? 'Assignment'
                            )
                        ) ?>

                    </h3>


                    <!-- INFORMATION -->

                    <div class="assignment-info-list">

                        <div class="assignment-info">

                            <i class="bi bi-book"></i>

                            <strong>
                                Subject:
                            </strong>

                            <span>
                                <?= e(
                                    (string) (
                                        $assignment['subject_name']
                                        ?? 'General'
                                    )
                                ) ?>
                            </span>

                        </div>


                        <div class="assignment-info">

                            <i class="bi bi-mortarboard"></i>

                            <strong>
                                Class:
                            </strong>

                            <span>
                                <?= e(
                                    (string) (
                                        $assignment['class_name']
                                        ?? 'N/A'
                                    )
                                ) ?>
                            </span>

                        </div>


                        <div class="assignment-info">

                            <i class="bi bi-diagram-3"></i>

                            <strong>
                                Section:
                            </strong>

                            <span>
                                <?= e(
                                    (string) (
                                        $assignment['section_name']
                                        ?? 'N/A'
                                    )
                                ) ?>
                            </span>

                        </div>


                        <div class="assignment-info">

                            <i class="bi bi-calendar-event"></i>

                            <strong>
                                Due:
                            </strong>

                            <span>
                                <?= e(
                                    (string) (
                                        $assignment['due_date']
                                        ?? 'Not specified'
                                    )
                                ) ?>
                            </span>

                        </div>

                    </div>


                    <!-- DESCRIPTION -->

                    <?php if (
                        !empty($assignment['description'])
                    ): ?>

                        <div class="assignment-description">

                            <div class="description-label">

                                <i class="bi bi-info-circle me-1"></i>

                                Instructions

                            </div>

                            <div class="description-text">

                                <?= nl2br(
                                    e(
                                        (string) (
                                            $assignment['description']
                                        )
                                    )
                                ) ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <!-- FOOTER -->

                    <div class="assignment-footer">

                        <?php if (
                            $submissionStatus === 'pending'
                        ): ?>

                            <a
                                href="submit_assignment.php?id=<?= (int) $assignment['id'] ?>"
                                class="submit-button"
                            >

                                <i class="bi bi-cloud-arrow-up-fill"></i>

                                Submit Assignment

                            </a>

                        <?php elseif (
                            $submissionStatus === 'submitted'
                        ): ?>

                            <div class="completed-button completed-submitted">

                                <i class="bi bi-check-circle-fill"></i>

                                Submitted Successfully

                            </div>

                        <?php else: ?>

                            <div class="completed-button completed-graded">

                                <i class="bi bi-patch-check-fill"></i>

                                Assignment Graded

                            </div>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<?php

layout_end();

?>

