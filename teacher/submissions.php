<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Assignment.php';

requireTeacher();

$pdo = db();

$teacher = current_teacher($pdo);

if (!$teacher) {
    exit('Teacher profile not found.');
}

$teacherId = (int) $teacher['id'];

$manager = new Assignment($pdo);

$assignments = $manager->getTeacherAssignments($teacherId);

$selected = filter_input(
    INPUT_GET,
    'assignment_id',
    FILTER_VALIDATE_INT
) ?: 0;

$submissions = [];
$assignment = null;

if (
    $selected &&
    $manager->teacherOwnsAssignment($selected, $teacherId)
) {
    $assignment = $manager->getAssignmentById($selected);
    $submissions = $manager->getSubmissions($selected);
}

/*
|--------------------------------------------------------------------------
| Grade Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $submissionId = (int) ($_POST['submission_id'] ?? 0);

    $marks = (float) ($_POST['marks'] ?? 0);

    $feedback = trim(
        (string) ($_POST['feedback'] ?? '')
    );

    if ($marks < 0 || $marks > 100) {

        flash_set(
            'danger',
            'Marks must be between 0 and 100.'
        );

    } elseif (
        $manager->gradeSubmission(
            $submissionId,
            $teacherId,
            $marks,
            $feedback !== '' ? $feedback : null
        )
    ) {

        /*
        | Non-blocking notification — silent no-op until
        | mail_enabled/sms_enabled are configured.
        */
        $gradeContext = $manager->getSubmissionContext(
            $submissionId,
            $teacherId
        );

        if ($gradeContext !== null) {

            notify_student(
                (int) $gradeContext['student_id'],
                'Your submission has been graded',
                "Hello {name},\n\n"
                . "Your submission for \""
                . (string) $gradeContext['title']
                . "\" has been graded.\n\n"
                . "Marks: " . number_format($marks, 2) . "/100\n"
                . "Feedback: " . ($feedback !== '' ? $feedback : '-')
                . "\n\n- Teacher",
                'Your submission for "'
                . (string) $gradeContext['title']
                . '" was graded: ' . number_format($marks, 2) . '/100.'
            );

            /*
            | The same event recorded in the in-app notifications centre
            | so the student and their guardian actually see it.
            */
            notify_student_in_app(
                (int) $gradeContext['student_id'],
                'Your submission has been graded',
                'Hello {name}, your submission for "'
                . (string) $gradeContext['title']
                . '" has been graded. Marks: '
                . number_format($marks, 2) . '/100. Feedback: '
                . ($feedback !== '' ? $feedback : '-'),
                'submission',
                'normal',
                'student/submissions.php',
                isset($_SESSION['user_id'])
                    ? (int) $_SESSION['user_id']
                    : null
            );
        }

        flash_set(
            'success',
            'Submission graded successfully.'
        );

    } else {

        flash_set(
            'danger',
            'Unable to grade this submission.'
        );
    }

    redirect(
        'teacher/submissions.php?assignment_id=' . $selected
    );
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalSubmissions = count($submissions);
$gradedCount = 0;
$submittedCount = 0;
$pendingCount = 0;

foreach ($submissions as $submission) {

    $status = strtolower(
        trim((string) ($submission['status'] ?? ''))
    );

    if ($status === 'graded') {
        $gradedCount++;
    } elseif ($status === 'submitted') {
        $submittedCount++;
    } else {
        $pendingCount++;
    }
}

$gradingPercentage = $totalSubmissions > 0
    ? round(($gradedCount / $totalSubmissions) * 100)
    : 0;

layout_start('Assignment Submissions', 'submissions');
?>

<style>
/* =========================================================
   TEACHER SUBMISSIONS
   ========================================================= */

.submissions-page {
    --ts-primary: #4f46e5;
    --ts-primary-dark: #3730a3;
    --ts-success: #16a34a;
    --ts-warning: #d97706;
    --ts-danger: #dc2626;
    --ts-text: #111827;
    --ts-muted: #6b7280;
    --ts-border: #e5e7eb;
    --ts-bg: #f8fafc;

    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
}

/* Hero */

.submissions-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 30px;
    margin-bottom: 24px;
    background:
        linear-gradient(
            135deg,
            #312e81 0%,
            #4f46e5 55%,
            #6366f1 100%
        );
    color: #fff;
    box-shadow: 0 18px 45px rgba(79, 70, 229, .20);
}

.submissions-hero::before,
.submissions-hero::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.submissions-hero::before {
    width: 220px;
    height: 220px;
    right: -80px;
    top: -100px;
    background: rgba(255,255,255,.08);
}

.submissions-hero::after {
    width: 150px;
    height: 150px;
    right: 150px;
    bottom: -100px;
    background: rgba(255,255,255,.06);
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-icon {
    width: 58px;
    height: 58px;
    border-radius: 17px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255,255,255,.15);
    font-size: 27px;
    margin-bottom: 17px;
}

.hero-title {
    margin: 0;
    font-size: 30px;
    font-weight: 800;
    letter-spacing: -.5px;
}

.hero-text {
    margin: 8px 0 0;
    color: rgba(255,255,255,.82);
    font-size: 15px;
    max-width: 680px;
}

/* Assignment selector */

.selector-card {
    background: #fff;
    border: 1px solid var(--ts-border);
    border-radius: 20px;
    padding: 22px;
    margin-bottom: 24px;
    box-shadow: 0 8px 28px rgba(15,23,42,.05);
}

.selector-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: var(--ts-text);
    margin-bottom: 8px;
}

.assignment-select {
    min-height: 50px;
    border-radius: 13px;
    border: 1px solid #d1d5db;
    padding: 10px 14px;
    font-size: 14px;
    font-weight: 600;
    background-color: #fff;
}

.assignment-select:focus {
    border-color: var(--ts-primary);
    box-shadow: 0 0 0 4px rgba(79,70,229,.10);
}

/* Stats */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.stat-box {
    background: #fff;
    border: 1px solid var(--ts-border);
    border-radius: 19px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    box-shadow: 0 7px 24px rgba(15,23,42,.04);
}

.stat-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: #eef2ff;
    color: var(--ts-primary);
}

.stat-box.success .stat-icon {
    background: #ecfdf5;
    color: var(--ts-success);
}

.stat-box.warning .stat-icon {
    background: #fffbeb;
    color: var(--ts-warning);
}

.stat-box.dark .stat-icon {
    background: #f3f4f6;
    color: #374151;
}

.stat-value {
    font-size: 25px;
    line-height: 1;
    font-weight: 800;
    color: var(--ts-text);
}

.stat-label {
    margin-top: 5px;
    font-size: 12px;
    color: var(--ts-muted);
    font-weight: 600;
}

/* Assignment info */

.assignment-card {
    background: #fff;
    border: 1px solid var(--ts-border);
    border-radius: 21px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 7px 24px rgba(15,23,42,.04);
}

.assignment-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.assignment-title {
    margin: 0;
    font-size: 22px;
    font-weight: 800;
    color: var(--ts-text);
}

.assignment-description {
    margin: 8px 0 0;
    color: var(--ts-muted);
    font-size: 14px;
    line-height: 1.6;
}

.assignment-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    margin-top: 17px;
}

.meta-item {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    color: #4b5563;
    font-size: 12px;
    font-weight: 600;
}

.progress-wrap {
    margin-top: 22px;
}

.progress-head {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #4b5563;
}

.progress-bar-custom {
    height: 8px;
    background: #eef2f7;
    border-radius: 99px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #4f46e5, #6366f1);
    transition: width .3s ease;
}

/* Submissions */

.submissions-card {
    background: #fff;
    border: 1px solid var(--ts-border);
    border-radius: 21px;
    overflow: hidden;
    box-shadow: 0 7px 24px rgba(15,23,42,.04);
}

.submissions-header {
    padding: 22px 24px;
    border-bottom: 1px solid var(--ts-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.submissions-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    color: var(--ts-text);
}

.submissions-header p {
    margin: 4px 0 0;
    color: var(--ts-muted);
    font-size: 12px;
}

.submission-table-wrap {
    overflow-x: auto;
}

.submission-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

.submission-table th {
    padding: 14px 20px;
    background: #f8fafc;
    border-bottom: 1px solid var(--ts-border);
    color: #6b7280;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}

.submission-table td {
    padding: 17px 20px;
    border-bottom: 1px solid #f0f2f5;
    vertical-align: middle;
}

.submission-table tbody tr:last-child td {
    border-bottom: 0;
}

.submission-table tbody tr:hover {
    background: #fafbff;
}

/* Student */

.student-cell {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
}

.student-avatar {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    border-radius: 13px;
    background: #eef2ff;
    color: var(--ts-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 15px;
}

.student-name {
    color: var(--ts-text);
    font-size: 13px;
    font-weight: 750;
}

.student-code {
    margin-top: 3px;
    color: var(--ts-muted);
    font-size: 11px;
}

/* Status */

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 99px;
    padding: 7px 10px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.status-submitted {
    background: #eff6ff;
    color: #2563eb;
}

.status-graded {
    background: #ecfdf5;
    color: #15803d;
}

.status-pending {
    background: #fffbeb;
    color: #b45309;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

/* Submission text */

.submission-preview {
    max-width: 220px;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.no-text {
    color: #9ca3af;
    font-style: italic;
}

/* File */

.file-btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 11px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    color: #374151;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    transition: .2s ease;
}

.file-btn:hover {
    background: #eef2ff;
    color: var(--ts-primary);
    border-color: #c7d2fe;
}

.no-file {
    color: #9ca3af;
    font-size: 12px;
}

/* Grade form */

.grade-form {
    display: flex;
    align-items: center;
    gap: 7px;
    min-width: 320px;
}

.grade-input {
    width: 80px;
    height: 38px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    padding: 7px 9px;
    font-size: 13px;
    font-weight: 700;
}

.grade-input:focus,
.feedback-input:focus {
    outline: none;
    border-color: var(--ts-primary);
    box-shadow: 0 0 0 3px rgba(79,70,229,.08);
}

.feedback-input {
    width: 150px;
    height: 38px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    padding: 7px 9px;
    font-size: 12px;
}

.save-btn {
    height: 38px;
    border: 0;
    border-radius: 9px;
    padding: 0 13px;
    background: var(--ts-primary);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: .2s ease;
}

.save-btn:hover {
    background: var(--ts-primary-dark);
    transform: translateY(-1px);
}

/* Empty */

.empty-state {
    padding: 55px 25px;
    text-align: center;
}

.empty-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 15px;
    border-radius: 19px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 26px;
}

.empty-state h4 {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: var(--ts-text);
}

.empty-state p {
    margin: 7px auto 0;
    max-width: 450px;
    color: var(--ts-muted);
    font-size: 13px;
}

/* No assignment selected */

.select-state {
    background: #fff;
    border: 1px solid var(--ts-border);
    border-radius: 21px;
    padding: 65px 25px;
    text-align: center;
    box-shadow: 0 7px 24px rgba(15,23,42,.04);
}

.select-state-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: var(--ts-primary);
    font-size: 29px;
}

.select-state h3 {
    margin: 0;
    color: var(--ts-text);
    font-size: 19px;
    font-weight: 800;
}

.select-state p {
    margin: 7px auto 0;
    max-width: 480px;
    color: var(--ts-muted);
    font-size: 13px;
}

/* Responsive */

@media (max-width: 1199px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 767.98px) {

    .submissions-hero {
        border-radius: 19px;
        padding: 23px;
    }

    .hero-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        font-size: 22px;
    }

    .hero-title {
        font-size: 24px;
    }

    .hero-text {
        font-size: 13px;
    }

    .selector-card,
    .assignment-card {
        padding: 18px;
        border-radius: 17px;
    }

    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .stat-box {
        padding: 15px;
        border-radius: 15px;
        gap: 10px;
    }

    .stat-icon {
        width: 41px;
        height: 41px;
        flex-basis: 41px;
        border-radius: 11px;
        font-size: 17px;
    }

    .stat-value {
        font-size: 21px;
    }

    .assignment-top {
        flex-direction: column;
        gap: 10px;
    }

    .assignment-title {
        font-size: 19px;
    }

    .submissions-card {
        border-radius: 17px;
    }

    .submissions-header {
        padding: 18px;
    }

}

@media (max-width: 480px) {

    .submissions-page {
        width: 100%;
    }

    .submissions-hero {
        padding: 20px;
        margin-bottom: 15px;
    }

    .hero-title {
        font-size: 21px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-box {
        padding: 14px;
    }

    .selector-card {
        margin-bottom: 15px;
    }

    .assignment-meta {
        flex-direction: column;
        align-items: stretch;
    }

    .meta-item {
        width: 100%;
    }

    .assignment-card {
        margin-bottom: 15px;
    }

    .select-state {
        padding: 45px 18px;
    }

}

@media (max-width: 360px) {

    .hero-title {
        font-size: 19px;
    }

    .hero-text {
        font-size: 12px;
    }

    .stat-value {
        font-size: 19px;
    }

    .stat-label {
        font-size: 11px;
    }

}
</style>

<div class="submissions-page">

    <!-- =====================================================
         HERO
         ===================================================== -->

    <section class="submissions-hero">

        <div class="hero-content">

            <div class="hero-icon">
                <i class="bi bi-file-earmark-check"></i>
            </div>

            <h1 class="hero-title">
                Assignment Submissions
            </h1>

            <p class="hero-text">
                Review student submissions, open attached files,
                provide feedback and record marks.
            </p>

        </div>

    </section>


    <!-- =====================================================
         ASSIGNMENT SELECTOR
         ===================================================== -->

    <section class="selector-card">

        <form method="get">

            <label
                for="assignment_id"
                class="selector-label"
            >
                Select Assignment
            </label>

            <select
                id="assignment_id"
                name="assignment_id"
                class="form-select assignment-select"
                onchange="this.form.submit()"
            >

                <option value="">
                    Choose an assignment to review...
                </option>

                <?php foreach ($assignments as $row): ?>

                    <option
                        value="<?= (int) $row['id'] ?>"
                        <?= $selected === (int) $row['id'] ? 'selected' : '' ?>
                    >
                        <?= e($row['title']) ?>
                        —
                        <?= e($row['class_name']) ?>
                        <?= e($row['section_name']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </section>


    <?php if ($assignment): ?>

        <!-- =================================================
             STATISTICS
             ================================================= -->

        <section class="stats-grid">

            <div class="stat-box">

                <div class="stat-icon">
                    <i class="bi bi-people"></i>
                </div>

                <div>
                    <div class="stat-value">
                        <?= $totalSubmissions ?>
                    </div>

                    <div class="stat-label">
                        Total Submissions
                    </div>
                </div>

            </div>


            <div class="stat-box success">

                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>

                <div>
                    <div class="stat-value">
                        <?= $gradedCount ?>
                    </div>

                    <div class="stat-label">
                        Graded
                    </div>
                </div>

            </div>


            <div class="stat-box warning">

                <div class="stat-icon">
                    <i class="bi bi-hourglass-split"></i>
                </div>

                <div>
                    <div class="stat-value">
                        <?= $submittedCount ?>
                    </div>

                    <div class="stat-label">
                        Awaiting Grade
                    </div>
                </div>

            </div>


            <div class="stat-box dark">

                <div class="stat-icon">
                    <i class="bi bi-bar-chart"></i>
                </div>

                <div>
                    <div class="stat-value">
                        <?= $gradingPercentage ?>%
                    </div>

                    <div class="stat-label">
                        Completion
                    </div>
                </div>

            </div>

        </section>


        <!-- =================================================
             ASSIGNMENT DETAILS
             ================================================= -->

        <section class="assignment-card">

            <div class="assignment-top">

                <div>

                    <h2 class="assignment-title">
                        <?= e($assignment['title']) ?>
                    </h2>

                    <?php if (!empty($assignment['description'])): ?>

                        <p class="assignment-description">
                            <?= nl2br(e($assignment['description'])) ?>
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <div class="assignment-meta">

                <span class="meta-item">
                    <i class="bi bi-book"></i>
                    <?= e($assignment['subject_name']) ?>
                </span>

                <span class="meta-item">
                    <i class="bi bi-mortarboard"></i>
                    <?= e($assignment['class_name']) ?>
                </span>

                <span class="meta-item">
                    <i class="bi bi-diagram-3"></i>
                    Section <?= e($assignment['section_name']) ?>
                </span>

                <span class="meta-item">
                    <i class="bi bi-calendar-event"></i>
                    Due <?= e((string) $assignment['due_date']) ?>
                </span>

            </div>


            <div class="progress-wrap">

                <div class="progress-head">

                    <span>
                        Grading Progress
                    </span>

                    <span>
                        <?= $gradedCount ?> / <?= $totalSubmissions ?>
                    </span>

                </div>

                <div class="progress-bar-custom">

                    <div
                        class="progress-fill"
                        style="width: <?= $gradingPercentage ?>%;"
                    ></div>

                </div>

            </div>

        </section>


        <!-- =================================================
             SUBMISSIONS
             ================================================= -->

        <section class="submissions-card">

            <div class="submissions-header">

                <div>

                    <h3>
                        Student Submissions
                    </h3>

                    <p>
                        Review and grade submitted work.
                    </p>

                </div>

            </div>


            <?php if ($submissions !== []): ?>

                <div class="submission-table-wrap">

                    <table class="submission-table">

                        <thead>

                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Submitted
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    File
                                </th>

                                <th>
                                    Grade & Feedback
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($submissions as $sub): ?>

                            <?php

                            $status = strtolower(
                                trim(
                                    (string) (
                                        $sub['status'] ?? ''
                                    )
                                )
                            );

                            if ($status === 'graded') {

                                $statusClass = 'status-graded';
                                $statusLabel = 'Graded';

                            } elseif ($status === 'submitted') {

                                $statusClass = 'status-submitted';
                                $statusLabel = 'Submitted';

                            } else {

                                $statusClass = 'status-pending';
                                $statusLabel = ucfirst(
                                    $status ?: 'Pending'
                                );
                            }

                            $studentName =
                                (string) (
                                    $sub['student_name'] ?? 'Student'
                                );

                            $initial =
                                strtoupper(
                                    substr(
                                        trim($studentName),
                                        0,
                                        1
                                    )
                                );

                            ?>

                            <tr>

                                <!-- Student -->

                                <td>

                                    <div class="student-cell">

                                        <div class="student-avatar">
                                            <?= e($initial) ?>
                                        </div>

                                        <div>

                                            <div class="student-name">
                                                <?= e($studentName) ?>
                                            </div>

                                            <div class="student-code">
                                                <?= e(
                                                    (string) (
                                                        $sub['student_code']
                                                        ?? ''
                                                    )
                                                ) ?>
                                            </div>

                                        </div>

                                    </div>

                                </td>


                                <!-- Submitted -->

                                <td>

                                    <span class="small text-muted">

                                        <?= e(
                                            (string) (
                                                $sub['submitted_at']
                                                ?? '—'
                                            )
                                        ) ?>

                                    </span>

                                </td>


                                <!-- Status -->

                                <td>

                                    <span
                                        class="status-badge <?= $statusClass ?>"
                                    >

                                        <span class="status-dot"></span>

                                        <?= e($statusLabel) ?>

                                    </span>

                                </td>


                                <!-- File -->

                                <td>

                                    <?php if (!empty($sub['file_path'])): ?>

                                        <a
                                            href="<?= e(
                                                url(
                                                    'download.php?file='
                                                    . rawurlencode(
                                                        (string) $sub['file_path']
                                                    )
                                                )
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="file-btn"
                                        >

                                            <i class="bi bi-file-earmark-arrow-down"></i>

                                            Open File

                                        </a>

                                    <?php else: ?>

                                        <span class="no-file">
                                            No attachment
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Grade -->

                                <td>

                                    <form
                                        method="post"
                                        class="grade-form"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="submission_id"
                                            value="<?= (int) $sub['id'] ?>"
                                        >

                                        <input
                                            type="number"
                                            name="marks"
                                            class="grade-input"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            placeholder="Marks"
                                            value="<?= e(
                                                (string) (
                                                    $sub['marks'] ?? ''
                                                )
                                            ) ?>"
                                            required
                                            aria-label="Marks"
                                        >

                                        <input
                                            type="text"
                                            name="feedback"
                                            class="feedback-input"
                                            placeholder="Feedback"
                                            value="<?= e(
                                                (string) (
                                                    $sub['feedback'] ?? ''
                                                )
                                            ) ?>"
                                            aria-label="Feedback"
                                        >

                                        <button
                                            type="submit"
                                            class="save-btn"
                                        >
                                            <i class="bi bi-check2"></i>
                                            Save
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <div class="empty-icon">
                        <i class="bi bi-inbox"></i>
                    </div>

                    <h4>
                        No submissions yet
                    </h4>

                    <p>
                        Students have not submitted this assignment yet.
                        Their submissions will appear here once received.
                    </p>

                </div>

            <?php endif; ?>

        </section>


    <?php else: ?>

        <!-- =================================================
             NO ASSIGNMENT SELECTED
             ================================================= -->

        <section class="select-state">

            <div class="select-state-icon">
                <i class="bi bi-file-earmark-text"></i>
            </div>

            <h3>
                Select an Assignment
            </h3>

            <p>
                Choose an assignment from the list above to view
                student submissions, attachments and grading options.
            </p>

        </section>

    <?php endif; ?>

</div>

<?php layout_end(); ?>

