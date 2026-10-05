<?php

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Assignment.php';
require_once __DIR__ . '/../classes/FileUpload.php';

requireStudent();

$pdo = db();
$student = current_student($pdo);

if (!$student) {
    http_response_code(403);
    exit('Student profile not found.');
}

/*
 * Whole-class assignments (section_id IS NULL) belong to
 * every student of the class, so only the class is
 * mandatory here. Section matching happens below.
 */
if (empty($student['class_id'])) {
    http_response_code(403);
    exit('Student class is not assigned.');
}

/*
|--------------------------------------------------------------------------
| Assignment ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
) ?: 0;

if ($id <= 0) {
    http_response_code(404);
    exit('Assignment not found.');
}

/*
|--------------------------------------------------------------------------
| Load Assignment
|--------------------------------------------------------------------------
*/

$manager = new Assignment($pdo);

$assignment = $manager->getAssignmentById($id);

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found.');
}

/*
 * Section check: an assignment with section_id IS NULL is
 * a whole-class assignment and matches every student of
 * the class (including students without a section).
 */
$assignmentSectionId = $assignment['section_id'] !== null
    ? (int) $assignment['section_id']
    : null;

$sectionMatches = $assignmentSectionId === null
    || (
        $student['section_id'] !== null
        && $assignmentSectionId === (int) $student['section_id']
    );

if (
    (int) $assignment['class_id'] !== (int) $student['class_id'] ||
    !$sectionMatches ||
    $assignment['status'] !== 'active'
) {
    http_response_code(404);
    exit('Assignment not found.');
}

/*
|--------------------------------------------------------------------------
| Existing Submission
|--------------------------------------------------------------------------
*/

$existing = $manager->getSubmission(
    $id,
    (int) $student['id']
);

$errors = [];

/*
|--------------------------------------------------------------------------
| Due Date
|--------------------------------------------------------------------------
*/

$today = date('Y-m-d');
$dueDate = (string) ($assignment['due_date'] ?? '');

$isOverdue = $dueDate !== '' && $dueDate < $today;
$isDueToday = $dueDate === $today;

$isGraded = $existing && $existing['status'] === 'graded';
$isSubmitted = $existing && in_array(
    $existing['status'],
    ['submitted', 'graded'],
    true
);

/*
|--------------------------------------------------------------------------
| POST Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    if ($isGraded) {

        $errors[] = 'This assignment has already been graded.';

    } elseif ($isOverdue) {

        $errors[] = 'The due date has passed. You can no longer submit this assignment.';

    } else {

        $text = trim(
            (string) ($_POST['submission_text'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | File Upload
        |--------------------------------------------------------------------------
        */

        $upload = FileUpload::storeAssignment(
            $_FILES['file'] ?? null
        );

        if (!$upload['ok']) {

            $errors[] = $upload['message'];

        } elseif (
            $text === '' &&
            empty($upload['path']) &&
            empty($existing['file_path'])
        ) {

            $errors[] = 'Please enter submission text or attach a file.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Keep Existing File
            |--------------------------------------------------------------------------
            |
            | If student submits text only during an update, preserve the old
            | uploaded file instead of replacing it with NULL.
            |
            */

            $filePath = !empty($upload['path'])
                ? $upload['path']
                : ($existing['file_path'] ?? null);

            $ok = $manager->submitAssignment([
                'assignment_id'   => $id,
                'student_id'      => (int) $student['id'],
                'submission_text' => $text !== '' ? $text : null,
                'file_path'       => $filePath,
            ]);

            if ($ok) {

                flash_set(
                    'success',
                    $isSubmitted
                        ? 'Assignment resubmitted successfully.'
                        : 'Assignment submitted successfully.'
                );

                redirect(
                    'student/assignments.php'
                );
            }

            $errors[] = 'Could not save the submission. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Previous Values
|--------------------------------------------------------------------------
*/

$submissionText = (string) (
    $_POST['submission_text']
    ?? ($existing['submission_text'] ?? '')
);

layout_start('Submit Assignment', 'assignments');
?>

<style>
.student-submit-page {
    max-width: 1100px;
    margin: 0 auto;
}

.student-submit-page * {
    box-sizing: border-box;
}

/* Hero */

.submit-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 30px;
    margin-bottom: 24px;
    color: #fff;
    background:
        linear-gradient(
            135deg,
            #2563eb 0%,
            #4f46e5 55%,
            #7c3aed 100%
        );
    box-shadow: 0 16px 40px rgba(37, 99, 235, .18);
}

.submit-hero::before,
.submit-hero::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.submit-hero::before {
    width: 220px;
    height: 220px;
    right: -80px;
    top: -100px;
    background: rgba(255,255,255,.10);
}

.submit-hero::after {
    width: 150px;
    height: 150px;
    right: 150px;
    bottom: -100px;
    background: rgba(255,255,255,.07);
}

.submit-hero-content {
    position: relative;
    z-index: 2;
}

.submit-back {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: rgba(255,255,255,.88);
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 20px;
}

.submit-back:hover {
    color: #fff;
}

.submit-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 11px;
    border-radius: 999px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.18);
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 13px;
}

.submit-hero h1 {
    margin: 0 0 9px;
    font-size: clamp(25px, 3vw, 34px);
    font-weight: 800;
    letter-spacing: -.4px;
    overflow-wrap: anywhere;
}

.submit-hero p {
    margin: 0;
    color: rgba(255,255,255,.82);
    font-size: 15px;
}

/* Main grid */

.submit-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 330px;
    gap: 24px;
    align-items: start;
}

/* Cards */

.submit-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    box-shadow: 0 8px 24px rgba(15,23,42,.05);
    overflow: hidden;
}

.submit-card-header {
    padding: 22px 24px 18px;
    border-bottom: 1px solid #eef0f3;
}

.submit-card-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 750;
    color: #111827;
}

.submit-card-header p {
    margin: 5px 0 0;
    color: #6b7280;
    font-size: 13px;
}

.submit-card-body {
    padding: 24px;
}

/* Assignment details */

.assignment-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.detail-box {
    min-width: 0;
    padding: 15px;
    border: 1px solid #e5e7eb;
    background: #f8fafc;
    border-radius: 14px;
}

.detail-label {
    display: block;
    margin-bottom: 5px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
}

.detail-value {
    display: block;
    color: #111827;
    font-size: 14px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* Description */

.assignment-description {
    margin-top: 18px;
    padding: 17px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
}

.assignment-description h3 {
    margin: 0 0 8px;
    font-size: 14px;
    font-weight: 750;
    color: #111827;
}

.assignment-description p {
    margin: 0;
    color: #4b5563;
    font-size: 14px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

/* Status */

.assignment-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
    padding: 13px 15px;
    border-radius: 13px;
    font-size: 13px;
    font-weight: 650;
}

.assignment-status.success {
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.assignment-status.warning {
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #92400e;
}

.assignment-status.danger {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.assignment-status-icon {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: rgba(255,255,255,.75);
}

/* Form */

.form-group {
    margin-bottom: 22px;
}

.form-label-custom {
    display: block;
    margin-bottom: 8px;
    color: #111827;
    font-size: 14px;
    font-weight: 700;
}

.form-label-custom span {
    color: #9ca3af;
    font-weight: 500;
}

.submission-textarea {
    width: 100%;
    min-height: 190px;
    resize: vertical;
    padding: 14px 15px;
    border: 1px solid #d9dee7;
    border-radius: 14px;
    background: #fff;
    color: #111827;
    font-size: 14px;
    line-height: 1.65;
    outline: none;
    transition: .2s ease;
}

.submission-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 4px rgba(99,102,241,.10);
}

.submission-textarea::placeholder {
    color: #9ca3af;
}

/* Upload */

.upload-wrapper {
    position: relative;
}

.upload-box {
    position: relative;
    display: flex;
    align-items: center;
    gap: 15px;
    min-height: 120px;
    padding: 20px;
    border: 2px dashed #d6dbe4;
    border-radius: 16px;
    background: #fafbfc;
    transition: .2s ease;
    cursor: pointer;
}

.upload-box:hover {
    border-color: #6366f1;
    background: #f8f7ff;
}

.upload-icon {
    width: 50px;
    height: 50px;
    flex: 0 0 50px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    background: #eef2ff;
    color: #4f46e5;
    font-size: 21px;
}

.upload-content {
    min-width: 0;
}

.upload-title {
    margin: 0 0 4px;
    color: #111827;
    font-size: 14px;
    font-weight: 750;
}

.upload-help {
    margin: 0;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.upload-input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

/* Existing file */

.existing-file {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    padding: 12px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #f8fafc;
}

.existing-file-icon {
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 10px;
    background: #eef2ff;
    color: #4f46e5;
}

.existing-file-info {
    min-width: 0;
    flex: 1;
}

.existing-file-info strong {
    display: block;
    font-size: 13px;
    color: #111827;
    overflow-wrap: anywhere;
}

.existing-file-info span {
    display: block;
    margin-top: 2px;
    font-size: 11px;
    color: #6b7280;
}

/* Errors */

.form-errors {
    margin-bottom: 20px;
    padding: 14px 16px;
    border: 1px solid #fecaca;
    border-radius: 13px;
    background: #fef2f2;
    color: #991b1b;
}

.form-errors strong {
    display: block;
    margin-bottom: 5px;
    font-size: 13px;
}

.form-errors ul {
    margin: 0;
    padding-left: 19px;
    font-size: 13px;
    line-height: 1.6;
}

/* Buttons */

.submit-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 4px;
}

.btn-submit-custom,
.btn-back-custom {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 18px;
    border-radius: 11px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid transparent;
    transition: .2s ease;
    cursor: pointer;
}

.btn-submit-custom {
    color: #fff;
    background: #4f46e5;
    border-color: #4f46e5;
}

.btn-submit-custom:hover {
    background: #4338ca;
    border-color: #4338ca;
    color: #fff;
    transform: translateY(-1px);
}

.btn-back-custom {
    color: #374151;
    background: #fff;
    border-color: #d9dee7;
}

.btn-back-custom:hover {
    color: #111827;
    background: #f9fafb;
}

/* Sidebar information */

.info-list {
    display: grid;
    gap: 10px;
}

.info-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
    padding-bottom: 12px;
    border-bottom: 1px solid #eef0f3;
}

.info-row:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

.info-row span:first-child {
    color: #6b7280;
    font-size: 12px;
}

.info-row strong {
    color: #111827;
    font-size: 13px;
    text-align: right;
    overflow-wrap: anywhere;
}

.help-box {
    margin-top: 18px;
    padding: 15px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
}

.help-box h3 {
    margin: 0 0 7px;
    font-size: 13px;
    font-weight: 750;
    color: #111827;
}

.help-box p {
    margin: 0;
    color: #6b7280;
    font-size: 12px;
    line-height: 1.6;
}

/* Graded */

.graded-box {
    padding: 18px;
    border-radius: 15px;
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
}

.graded-box h3 {
    margin: 0 0 6px;
    color: #166534;
    font-size: 15px;
    font-weight: 750;
}

.graded-box p {
    margin: 0;
    color: #166534;
    font-size: 13px;
    line-height: 1.6;
}

/* Responsive */

@media (max-width: 991.98px) {

    .submit-grid {
        grid-template-columns: 1fr;
    }

    .submit-card-body {
        padding: 20px;
    }

    .submit-hero {
        padding: 25px;
    }
}

@media (max-width: 767.98px) {

    .student-submit-page {
        width: 100%;
    }

    .submit-hero {
        border-radius: 18px;
        padding: 22px 18px;
        margin-bottom: 18px;
    }

    .submit-hero h1 {
        font-size: 25px;
    }

    .submit-card {
        border-radius: 16px;
    }

    .submit-card-header {
        padding: 18px;
    }

    .submit-card-body {
        padding: 18px;
    }

    .assignment-details {
        grid-template-columns: 1fr;
    }

    .submit-actions {
        flex-direction: column-reverse;
        align-items: stretch;
    }

    .btn-submit-custom,
    .btn-back-custom {
        width: 100%;
    }
}

@media (max-width: 480px) {

    .submit-hero {
        padding: 20px 16px;
    }

    .submit-hero h1 {
        font-size: 22px;
    }

    .submit-hero p {
        font-size: 13px;
    }

    .submit-card-header,
    .submit-card-body {
        padding: 15px;
    }

    .upload-box {
        min-height: 110px;
        padding: 15px;
    }

    .upload-icon {
        width: 43px;
        height: 43px;
        flex-basis: 43px;
        font-size: 18px;
    }

    .submission-textarea {
        min-height: 160px;
    }
}

@media (max-width: 360px) {

    .submit-hero h1 {
        font-size: 20px;
    }

    .upload-box {
        gap: 10px;
        padding: 12px;
    }

    .upload-title {
        font-size: 13px;
    }

    .upload-help {
        font-size: 11px;
    }
}
</style>

<div class="student-submit-page">

    <!-- Hero -->
    <section class="submit-hero">
        <div class="submit-hero-content">

            <a
                href="<?= e(url('student/assignments.php')) ?>"
                class="submit-back"
            >
                <span>←</span>
                Back to Assignments
            </a>

            <div class="submit-eyebrow">
                📝 Assignment Submission
            </div>

            <h1><?= e((string) $assignment['title']) ?></h1>

            <p>
                Submit your work before the deadline.
            </p>

        </div>
    </section>

    <div class="submit-grid">

        <!-- Main Content -->
        <main>

            <!-- Assignment Information -->
            <section class="submit-card">

                <div class="submit-card-header">
                    <h2>Assignment Details</h2>
                    <p>Review the assignment before submitting.</p>
                </div>

                <div class="submit-card-body">

                    <div class="assignment-details">

                        <div class="detail-box">
                            <span class="detail-label">Subject</span>
                            <span class="detail-value">
                                <?= e((string) ($assignment['subject_name'] ?? '—')) ?>
                            </span>
                        </div>

                        <div class="detail-box">
                            <span class="detail-label">Due Date</span>
                            <span class="detail-value">
                                <?= e($dueDate ?: '—') ?>
                            </span>
                        </div>

                        <div class="detail-box">
                            <span class="detail-label">Class</span>
                            <span class="detail-value">
                                <?= e((string) ($assignment['class_name'] ?? '—')) ?>
                            </span>
                        </div>

                        <div class="detail-box">
                            <span class="detail-label">Section</span>
                            <span class="detail-value">
                                <?= e((string) ($assignment['section_name'] ?? '—')) ?>
                            </span>
                        </div>

                    </div>

                    <?php if (!empty($assignment['description'])): ?>

                        <div class="assignment-description">

                            <h3>Instructions / Description</h3>

                            <p><?= e((string) $assignment['description']) ?></p>

                        </div>

                    <?php endif; ?>

                </div>
            </section>

            <!-- Submission Form -->
            <section class="submit-card" style="margin-top:24px;">

                <div class="submit-card-header">
                    <h2>
                        <?= $isSubmitted ? 'Update Submission' : 'Your Submission' ?>
                    </h2>

                    <p>
                        <?= $isSubmitted
                            ? 'You can update your submission while the assignment is still open.'
                            : 'Enter your answer and optionally attach a file.'
                        ?>
                    </p>
                </div>

                <div class="submit-card-body">

                    <?php if ($errors): ?>

                        <div class="form-errors">

                            <strong>Please check the following:</strong>

                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= e($error) ?></li>
                                <?php endforeach; ?>
                            </ul>

                        </div>

                    <?php endif; ?>

                    <?php if ($isGraded): ?>

                        <div class="graded-box">

                            <h3>✓ Assignment Already Graded</h3>

                            <p>
                                This assignment has already been graded.
                                You can view your submission from the assignments page.
                            </p>

                        </div>

                    <?php elseif ($isOverdue): ?>

                        <div class="assignment-status danger">

                            <div class="assignment-status-icon">
                                ⚠
                            </div>

                            <div>
                                <strong>Submission Closed</strong><br>
                                The due date for this assignment has passed.
                            </div>

                        </div>

                    <?php else: ?>

                        <?php if ($isSubmitted): ?>

                            <div class="assignment-status success">

                                <div class="assignment-status-icon">
                                    ✓
                                </div>

                                <div>
                                    <strong>Already Submitted</strong><br>
                                    You can update your submission before the deadline.
                                </div>

                            </div>

                        <?php elseif ($isDueToday): ?>

                            <div class="assignment-status warning">

                                <div class="assignment-status-icon">
                                    !
                                </div>

                                <div>
                                    <strong>Due Today</strong><br>
                                    Make sure you submit your work before the deadline.
                                </div>

                            </div>

                        <?php else: ?>

                            <div class="assignment-status success">

                                <div class="assignment-status-icon">
                                    ✓
                                </div>

                                <div>
                                    <strong>Submission Open</strong><br>
                                    You can submit your assignment before the due date.
                                </div>

                            </div>

                        <?php endif; ?>

                        <form
                            method="post"
                            enctype="multipart/form-data"
                            id="submissionForm"
                        >

                            <?= csrf_field() ?>

                            <!-- Text -->
                            <div class="form-group">

                                <label
                                    for="submission_text"
                                    class="form-label-custom"
                                >
                                    Submission Text
                                    <span>(optional if file is attached)</span>
                                </label>

                                <textarea
                                    id="submission_text"
                                    name="submission_text"
                                    class="submission-textarea"
                                    placeholder="Write your answer, explanation or comments here..."
                                ><?= e($submissionText) ?></textarea>

                            </div>

                            <!-- File -->
                            <div class="form-group">

                                <label class="form-label-custom">
                                    Attachment
                                    <span>(optional)</span>
                                </label>

                                <div class="upload-wrapper">

                                    <label
                                        class="upload-box"
                                        for="assignmentFile"
                                    >

                                        <div class="upload-icon">
                                            ↑
                                        </div>

                                        <div class="upload-content">

                                            <p class="upload-title" id="uploadTitle">
                                                Choose a file to upload
                                            </p>

                                            <p
                                                class="upload-help"
                                                id="uploadHelp"
                                            >
                                                Click here to browse your device.
                                                Maximum file size: 5MB.
                                            </p>

                                        </div>

                                        <input
                                            type="file"
                                            id="assignmentFile"
                                            name="file"
                                            class="upload-input"
                                        >

                                    </label>

                                </div>

                                <?php if (!empty($existing['file_path'])): ?>

                                    <div class="existing-file">

                                        <div class="existing-file-icon">
                                            📎
                                        </div>

                                        <div class="existing-file-info">

                                            <strong>
                                                Existing attachment
                                            </strong>

                                            <span>
                                                A previous file is already attached to this submission.
                                                Uploading a new file will replace it.
                                                <a
                                                    href="<?= e(
                                                        url(
                                                            'download.php?file='
                                                            . rawurlencode(
                                                                (string) $existing['file_path']
                                                            )
                                                        )
                                                    ) ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    Download current file
                                                </a>
                                            </span>

                                        </div>

                                    </div>

                                <?php endif; ?>

                            </div>

                            <!-- Actions -->
                            <div class="submit-actions">

                                <a
                                    href="<?= e(url('student/assignments.php')) ?>"
                                    class="btn-back-custom"
                                >
                                    ← Back
                                </a>

                                <button
                                    type="submit"
                                    class="btn-submit-custom"
                                >
                                    <?= $isSubmitted
                                        ? '↻ Update Submission'
                                        : '✓ Submit Assignment'
                                    ?>
                                </button>

                            </div>

                        </form>

                    <?php endif; ?>

                </div>
            </section>

        </main>

        <!-- Sidebar -->
        <aside>

            <section class="submit-card">

                <div class="submit-card-header">
                    <h2>Submission Status</h2>
                    <p>Your current assignment status.</p>
                </div>

                <div class="submit-card-body">

                    <div class="info-list">

                        <div class="info-row">
                            <span>Status</span>

                            <strong>
                                <?php if ($isGraded): ?>
                                    Graded
                                <?php elseif ($isSubmitted): ?>
                                    Submitted
                                <?php elseif ($isOverdue): ?>
                                    Overdue
                                <?php else: ?>
                                    Pending
                                <?php endif; ?>
                            </strong>
                        </div>

                        <div class="info-row">
                            <span>Due Date</span>

                            <strong>
                                <?= e($dueDate ?: '—') ?>
                            </strong>
                        </div>

                        <div class="info-row">
                            <span>Student</span>

                            <strong>
                                <?= e((string) ($student['name'] ?? 'Student')) ?>
                            </strong>
                        </div>

                        <div class="info-row">
                            <span>Student ID</span>

                            <strong>
                                <?= e((string) ($student['student_id'] ?? '—')) ?>
                            </strong>
                        </div>

                    </div>

                    <div class="help-box">

                        <h3>Submission Tips</h3>

                        <p>
                            Make sure your answer is complete before submitting.
                            If you attach a file, check that it opens correctly
                            and is the correct assignment.
                        </p>

                    </div>

                </div>

            </section>

        </aside>

    </div>

</div>

<script>
(function () {

    const fileInput = document.getElementById('assignmentFile');
    const uploadTitle = document.getElementById('uploadTitle');
    const uploadHelp = document.getElementById('uploadHelp');

    if (!fileInput) {
        return;
    }

    fileInput.addEventListener('change', function () {

        const file = this.files && this.files.length
            ? this.files[0]
            : null;

        if (!file) {
            uploadTitle.textContent = 'Choose a file to upload';

            uploadHelp.textContent =
                'Click here to browse your device. Maximum file size: 5MB.';

            return;
        }

        const maxSize = 5 * 1024 * 1024;

        if (file.size > maxSize) {

            uploadTitle.textContent = 'File is too large';

            uploadHelp.textContent =
                'Please choose a file smaller than 5MB.';

            this.value = '';

            return;
        }

        uploadTitle.textContent = file.name;

        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);

        uploadHelp.textContent =
            'Selected file • ' + sizeMB + ' MB';

    });

})();
</script>

<?php layout_end(); ?>

