<?php

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Assignment.php';

requireStudent();

$pdo = db();
$student = current_student($pdo);

if (!$student) {
    http_response_code(403);
    exit('Student profile not found.');
}

layout_start('My Submissions', 'submissions');

$hasClass = !empty($student['class_id']);
$hasSection = !empty($student['section_id']);

$assignments = [];

if ($hasClass && $hasSection) {
    $manager = new Assignment($pdo);

    $assignments = $manager->getStudentAssignments(
        (int) $student['class_id'],
        (int) $student['section_id'],
        (int) $student['id']
    );
}

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total = count($assignments);
$submitted = 0;
$graded = 0;
$pending = 0;
$overdue = 0;
$today = date('Y-m-d');

foreach ($assignments as $assignment) {

    $status = (string) ($assignment['submission_status'] ?? '');

    if ($status === 'graded') {
        $graded++;
    } elseif ($status === 'submitted') {
        $submitted++;
    } else {
        $pending++;

        if (
            !empty($assignment['due_date']) &&
            $assignment['due_date'] < $today
        ) {
            $overdue++;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$filter = $_GET['status'] ?? 'all';

$allowedFilters = [
    'all',
    'pending',
    'submitted',
    'graded',
    'overdue'
];

if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$filteredAssignments = [];

foreach ($assignments as $assignment) {

    $submissionStatus = (string) (
        $assignment['submission_status'] ?? ''
    );

    $isAssignmentOverdue =
        empty($submissionStatus) &&
        !empty($assignment['due_date']) &&
        $assignment['due_date'] < $today;

    $include = false;

    switch ($filter) {

        case 'pending':
            $include = $submissionStatus === '';
            break;

        case 'submitted':
            $include = $submissionStatus === 'submitted';
            break;

        case 'graded':
            $include = $submissionStatus === 'graded';
            break;

        case 'overdue':
            $include = $isAssignmentOverdue;
            break;

        default:
            $include = true;
            break;
    }

    if ($include) {
        $filteredAssignments[] = $assignment;
    }
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function submission_status_class(
    ?string $status,
    bool $overdue = false
): string {
    if ($status === 'graded') {
        return 'graded';
    }

    if ($status === 'submitted') {
        return 'submitted';
    }

    if ($overdue) {
        return 'overdue';
    }

    return 'pending';
}

function submission_status_text(
    ?string $status,
    bool $overdue = false
): string {
    if ($status === 'graded') {
        return 'Graded';
    }

    if ($status === 'submitted') {
        return 'Submitted';
    }

    if ($overdue) {
        return 'Overdue';
    }

    return 'Pending';
}

function format_submission_date(?string $date): string
{
    if (!$date) {
        return 'Not submitted';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date('d M Y, h:i A', $timestamp);
}

function format_due_date(?string $date): string
{
    if (!$date) {
        return 'No due date';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return $date;
    }

    return date('d M Y', $timestamp);
}

?>

<style>
.student-submissions-page {
    max-width: 1180px;
    margin: 0 auto;
}

.student-submissions-page * {
    box-sizing: border-box;
}

/* =========================================================
   HERO
========================================================= */

.submissions-hero {
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
            #4f46e5 52%,
            #7c3aed 100%
        );
    box-shadow: 0 16px 40px rgba(37, 99, 235, .18);
}

.submissions-hero::before,
.submissions-hero::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.submissions-hero::before {
    width: 250px;
    height: 250px;
    right: -90px;
    top: -130px;
    background: rgba(255,255,255,.10);
}

.submissions-hero::after {
    width: 170px;
    height: 170px;
    right: 180px;
    bottom: -120px;
    background: rgba(255,255,255,.07);
}

.submissions-hero-content {
    position: relative;
    z-index: 2;
}

.submissions-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 11px;
    margin-bottom: 13px;
    border-radius: 999px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.18);
    font-size: 12px;
    font-weight: 700;
}

.submissions-hero h1 {
    margin: 0 0 8px;
    font-size: clamp(25px, 3vw, 34px);
    font-weight: 800;
    letter-spacing: -.5px;
}

.submissions-hero p {
    margin: 0;
    color: rgba(255,255,255,.83);
    font-size: 15px;
}

/* =========================================================
   STATS
========================================================= */

.submission-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}

.submission-stat {
    min-width: 0;
    padding: 19px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 17px;
    box-shadow: 0 7px 22px rgba(15,23,42,.04);
}

.submission-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.submission-stat-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    background: #f1f5f9;
    font-size: 17px;
}

.submission-stat-number {
    margin-top: 13px;
    color: #111827;
    font-size: 27px;
    line-height: 1;
    font-weight: 800;
}

.submission-stat-label {
    margin-top: 7px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
}

/* =========================================================
   FILTER
========================================================= */

.submissions-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 18px;
}

.submissions-toolbar-title h2 {
    margin: 0;
    color: #111827;
    font-size: 19px;
    font-weight: 800;
}

.submissions-toolbar-title p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 12px;
}

.status-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.status-filter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    color: #4b5563;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    transition: .2s ease;
}

.status-filter:hover {
    color: #4f46e5;
    border-color: #c7d2fe;
    background: #f8f7ff;
}

.status-filter.active {
    color: #fff;
    border-color: #4f46e5;
    background: #4f46e5;
}

/* =========================================================
   ASSIGNMENT CARDS
========================================================= */

.submission-list {
    display: grid;
    gap: 16px;
}

.submission-card {
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 19px;
    box-shadow: 0 7px 22px rgba(15,23,42,.04);
}

.submission-card-main {
    padding: 21px;
}

.submission-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 15px;
}

.submission-title-wrap {
    min-width: 0;
}

.submission-title {
    margin: 0;
    color: #111827;
    font-size: 17px;
    line-height: 1.4;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.submission-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 7px;
}

.meta-item {
    color: #6b7280;
    font-size: 12px;
}

.meta-item strong {
    color: #374151;
}

.submission-badge {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 29px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.submission-badge.pending {
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fde68a;
}

.submission-badge.submitted {
    color: #1d4ed8;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

.submission-badge.graded {
    color: #166534;
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
}

.submission-badge.overdue {
    color: #991b1b;
    background: #fef2f2;
    border: 1px solid #fecaca;
}

/* Details */

.submission-details {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-top: 18px;
}

.submission-detail {
    min-width: 0;
    padding: 12px 13px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #edf0f4;
}

.submission-detail-label {
    display: block;
    margin-bottom: 4px;
    color: #6b7280;
    font-size: 11px;
    font-weight: 600;
}

.submission-detail-value {
    display: block;
    color: #111827;
    font-size: 13px;
    font-weight: 750;
    overflow-wrap: anywhere;
}

/* Description */

.submission-description {
    margin-top: 15px;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.65;
    overflow-wrap: anywhere;
}

.submission-description strong {
    color: #374151;
}

/* Submission content */

.submitted-content {
    margin-top: 17px;
    padding: 15px;
    border-radius: 14px;
    border: 1px solid #e5e7eb;
    background: #fafbfc;
}

.submitted-content-title {
    margin-bottom: 8px;
    color: #374151;
    font-size: 12px;
    font-weight: 800;
}

.submitted-text {
    color: #4b5563;
    font-size: 13px;
    line-height: 1.7;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.no-text {
    color: #9ca3af;
    font-size: 12px;
    font-style: italic;
}

/* Footer */

.submission-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 14px 21px;
    border-top: 1px solid #eef0f3;
    background: #fcfcfd;
}

.submitted-date {
    color: #6b7280;
    font-size: 11px;
}

.submitted-date strong {
    color: #374151;
}

/* Grade */

.grade-box {
    display: flex;
    align-items: center;
    gap: 10px;
}

.grade-number {
    color: #166534;
    font-size: 19px;
    font-weight: 850;
}

.grade-label {
    color: #6b7280;
    font-size: 11px;
}

/* Feedback */

.feedback-box {
    margin-top: 12px;
    padding: 13px;
    border-radius: 12px;
    background: #eff6ff;
    border: 1px solid #dbeafe;
}

.feedback-box strong {
    display: block;
    margin-bottom: 5px;
    color: #1e40af;
    font-size: 12px;
}

.feedback-box p {
    margin: 0;
    color: #374151;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
}

/* Buttons */

.submission-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.submission-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 7px 12px;
    border-radius: 9px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 750;
    transition: .2s ease;
}

.submission-btn-primary {
    color: #fff;
    background: #4f46e5;
    border: 1px solid #4f46e5;
}

.submission-btn-primary:hover {
    color: #fff;
    background: #4338ca;
}

.submission-btn-outline {
    color: #374151;
    background: #fff;
    border: 1px solid #d9dee7;
}

.submission-btn-outline:hover {
    color: #111827;
    background: #f9fafb;
}

/* Empty */

.submissions-empty {
    padding: 55px 25px;
    text-align: center;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 19px;
}

.empty-icon {
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    margin: 0 auto 13px;
    border-radius: 17px;
    background: #f1f5f9;
    font-size: 25px;
}

.submissions-empty h3 {
    margin: 0 0 6px;
    color: #111827;
    font-size: 17px;
    font-weight: 800;
}

.submissions-empty p {
    max-width: 460px;
    margin: 0 auto;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

/* No profile / class */

.submission-notice {
    padding: 20px;
    border-radius: 16px;
    background: #fff;
    border: 1px solid #e5e7eb;
}

.submission-notice h3 {
    margin: 0 0 7px;
    color: #111827;
    font-size: 16px;
}

.submission-notice p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991.98px) {

    .submission-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .submissions-toolbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .status-filters {
        width: 100%;
    }

    .submission-details {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 767.98px) {

    .submissions-hero {
        padding: 23px 19px;
        border-radius: 18px;
        margin-bottom: 18px;
    }

    .submissions-hero h1 {
        font-size: 25px;
    }

    .submissions-hero p {
        font-size: 13px;
    }

    .submission-stats {
        gap: 10px;
        margin-bottom: 19px;
    }

    .submission-stat {
        padding: 15px;
        border-radius: 14px;
    }

    .submission-stat-number {
        font-size: 23px;
    }

    .submission-card-main {
        padding: 17px;
    }

    .submission-card-top {
        flex-direction: column;
        gap: 10px;
    }

    .submission-badge {
        align-self: flex-start;
    }

    .submission-details {
        grid-template-columns: 1fr;
    }

    .submission-card-footer {
        align-items: flex-start;
        flex-direction: column;
        padding: 13px 17px;
    }

    .submission-actions {
        width: 100%;
    }

    .submission-btn {
        flex: 1;
    }
}

@media (max-width: 480px) {

    .submissions-hero {
        padding: 20px 16px;
    }

    .submissions-hero h1 {
        font-size: 22px;
    }

    .submission-stats {
        grid-template-columns: 1fr 1fr;
    }

    .submission-stat {
        padding: 13px;
    }

    .submission-stat-icon {
        width: 35px;
        height: 35px;
        border-radius: 10px;
    }

    .submission-stat-number {
        margin-top: 10px;
        font-size: 21px;
    }

    .submission-title {
        font-size: 15px;
    }

    .submission-meta {
        flex-direction: column;
        gap: 3px;
    }

    .status-filters {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }

    .status-filter {
        width: 100%;
    }

    .submission-card-footer {
        gap: 10px;
    }

    .submission-actions {
        flex-direction: column;
    }

    .submission-btn {
        width: 100%;
    }
}

@media (max-width: 360px) {

    .submission-stats {
        grid-template-columns: 1fr;
    }

    .status-filters {
        grid-template-columns: 1fr;
    }

    .submissions-hero h1 {
        font-size: 20px;
    }
}
</style>

<div class="student-submissions-page">

    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="submissions-hero">

        <div class="submissions-hero-content">

            <div class="submissions-eyebrow">
                📚 Academic Work
            </div>

            <h1>My Submissions</h1>

            <p>
                Track your assignments, submissions, marks and teacher feedback.
            </p>

        </div>

    </section>

    <?php if (!$hasClass || !$hasSection): ?>

        <div class="submission-notice">

            <h3>Class or Section Not Assigned</h3>

            <p>
                Your class and section must be assigned before your assignments
                and submissions can be displayed.
            </p>

        </div>

    <?php else: ?>

        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="submission-stats">

            <div class="submission-stat">

                <div class="submission-stat-top">
                    <span class="submission-stat-icon">📚</span>
                </div>

                <div class="submission-stat-number">
                    <?= $total ?>
                </div>

                <div class="submission-stat-label">
                    Total Assignments
                </div>

            </div>

            <div class="submission-stat">

                <div class="submission-stat-top">
                    <span class="submission-stat-icon">📤</span>
                </div>

                <div class="submission-stat-number">
                    <?= $submitted ?>
                </div>

                <div class="submission-stat-label">
                    Submitted
                </div>

            </div>

            <div class="submission-stat">

                <div class="submission-stat-top">
                    <span class="submission-stat-icon">✓</span>
                </div>

                <div class="submission-stat-number">
                    <?= $graded ?>
                </div>

                <div class="submission-stat-label">
                    Graded
                </div>

            </div>

            <div class="submission-stat">

                <div class="submission-stat-top">
                    <span class="submission-stat-icon">⏳</span>
                </div>

                <div class="submission-stat-number">
                    <?= $pending ?>
                </div>

                <div class="submission-stat-label">
                    Pending
                    <?php if ($overdue > 0): ?>
                        · <?= $overdue ?> overdue
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <!-- =================================================
             TOOLBAR
        ================================================== -->

        <div class="submissions-toolbar">

            <div class="submissions-toolbar-title">

                <h2>Assignment History</h2>

                <p>
                    <?= count($filteredAssignments) ?>
                    result<?= count($filteredAssignments) === 1 ? '' : 's' ?>
                    shown
                </p>

            </div>

            <div class="status-filters">

                <a
                    href="<?= e(url('student/submissions.php')) ?>"
                    class="status-filter <?= $filter === 'all' ? 'active' : '' ?>"
                >
                    All
                </a>

                <a
                    href="<?= e(url('student/submissions.php?status=pending')) ?>"
                    class="status-filter <?= $filter === 'pending' ? 'active' : '' ?>"
                >
                    Pending
                </a>

                <a
                    href="<?= e(url('student/submissions.php?status=submitted')) ?>"
                    class="status-filter <?= $filter === 'submitted' ? 'active' : '' ?>"
                >
                    Submitted
                </a>

                <a
                    href="<?= e(url('student/submissions.php?status=graded')) ?>"
                    class="status-filter <?= $filter === 'graded' ? 'active' : '' ?>"
                >
                    Graded
                </a>

                <a
                    href="<?= e(url('student/submissions.php?status=overdue')) ?>"
                    class="status-filter <?= $filter === 'overdue' ? 'active' : '' ?>"
                >
                    Overdue
                </a>

            </div>

        </div>

        <!-- =================================================
             ASSIGNMENT LIST
        ================================================== -->

        <?php if (!$filteredAssignments): ?>

            <div class="submissions-empty">

                <div class="empty-icon">
                    📭
                </div>

                <h3>No Submissions Found</h3>

                <p>
                    There are no assignments matching the selected filter.
                </p>

            </div>

        <?php else: ?>

            <div class="submission-list">

                <?php foreach ($filteredAssignments as $assignment): ?>

                    <?php
                    $submissionStatus = $assignment['submission_status'] ?? null;

                    $isOverdue =
                        empty($submissionStatus) &&
                        !empty($assignment['due_date']) &&
                        $assignment['due_date'] < $today;

                    $statusClass = submission_status_class(
                        $submissionStatus,
                        $isOverdue
                    );

                    $statusText = submission_status_text(
                        $submissionStatus,
                        $isOverdue
                    );

                    $marks = $assignment['marks'] ?? null;
                    $feedback = trim(
                        (string) ($assignment['feedback'] ?? '')
                    );

                    $submissionText = trim(
                        (string) ($assignment['submission_text'] ?? '')
                    );

                    $filePath = trim(
                        (string) ($assignment['file_path'] ?? '')
                    );
                    ?>

                    <article class="submission-card">

                        <div class="submission-card-main">

                            <div class="submission-card-top">

                                <div class="submission-title-wrap">

                                    <h3 class="submission-title">
                                        <?= e((string) $assignment['title']) ?>
                                    </h3>

                                    <div class="submission-meta">

                                        <span class="meta-item">
                                            Subject:
                                            <strong>
                                                <?= e((string) ($assignment['subject_name'] ?? '—')) ?>
                                            </strong>
                                        </span>

                                        <span class="meta-item">•</span>

                                        <span class="meta-item">
                                            Class:
                                            <strong>
                                                <?= e((string) ($assignment['class_name'] ?? '—')) ?>
                                            </strong>
                                        </span>

                                        <span class="meta-item">•</span>

                                        <span class="meta-item">
                                            Section:
                                            <strong>
                                                <?= e((string) ($assignment['section_name'] ?? '—')) ?>
                                            </strong>
                                        </span>

                                    </div>

                                </div>

                                <span class="submission-badge <?= e($statusClass) ?>">

                                    <?php if ($statusClass === 'graded'): ?>
                                        ✓
                                    <?php elseif ($statusClass === 'submitted'): ?>
                                        ↑
                                    <?php elseif ($statusClass === 'overdue'): ?>
                                        !
                                    <?php else: ?>
                                        ⏳
                                    <?php endif; ?>

                                    <?= e($statusText) ?>

                                </span>

                            </div>

                            <!-- Details -->

                            <div class="submission-details">

                                <div class="submission-detail">

                                    <span class="submission-detail-label">
                                        Due Date
                                    </span>

                                    <span class="submission-detail-value">
                                        <?= e(format_due_date($assignment['due_date'] ?? null)) ?>
                                    </span>

                                </div>

                                <div class="submission-detail">

                                    <span class="submission-detail-label">
                                        Submitted At
                                    </span>

                                    <span class="submission-detail-value">
                                        <?= e(
                                            format_submission_date(
                                                $assignment['submitted_at'] ?? null
                                            )
                                        ) ?>
                                    </span>

                                </div>

                                <div class="submission-detail">

                                    <span class="submission-detail-label">
                                        Marks
                                    </span>

                                    <span class="submission-detail-value">

                                        <?php if ($marks !== null && $marks !== ''): ?>

                                            <?= e((string) $marks) ?>

                                        <?php else: ?>

                                            Not graded

                                        <?php endif; ?>

                                    </span>

                                </div>

                            </div>

                            <!-- Assignment Description -->

                            <?php if (!empty($assignment['description'])): ?>

                                <div class="submission-description">

                                    <strong>Assignment:</strong>

                                    <?= e((string) $assignment['description']) ?>

                                </div>

                            <?php endif; ?>

                            <!-- Submitted Content -->

                            <?php if (
                                $submissionText !== '' ||
                                $filePath !== ''
                            ): ?>

                                <div class="submitted-content">

                                    <div class="submitted-content-title">
                                        Your Submission
                                    </div>

                                    <?php if ($submissionText !== ''): ?>

                                        <div class="submitted-text">
                                            <?= e($submissionText) ?>
                                        </div>

                                    <?php else: ?>

                                        <div class="no-text">
                                            No text submission.
                                        </div>

                                    <?php endif; ?>

                                    <?php if ($filePath !== ''): ?>

                                        <div style="margin-top:12px;">

                                            <a
                                                href="<?= e(
                                                    url(
                                                        'download.php?file='
                                                        . rawurlencode($filePath)
                                                    )
                                                ) ?>"
                                                target="_blank"
                                                rel="noopener"
                                                class="submission-btn submission-btn-outline"
                                            >
                                                📎 View Submitted File
                                            </a>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                            <!-- Feedback -->

                            <?php if ($feedback !== ''): ?>

                                <div class="feedback-box">

                                    <strong>
                                        Teacher Feedback
                                    </strong>

                                    <p><?= e($feedback) ?></p>

                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- Card Footer -->

                        <div class="submission-card-footer">

                            <div class="submitted-date">

                                <?php if ($submissionStatus): ?>

                                    Last submitted:
                                    <strong>
                                        <?= e(
                                            format_submission_date(
                                                $assignment['submitted_at'] ?? null
                                            )
                                        ) ?>
                                    </strong>

                                <?php else: ?>

                                    No submission yet.

                                <?php endif; ?>

                            </div>

                            <div class="submission-actions">

                                <?php if (
                                    !$submissionStatus &&
                                    !$isOverdue
                                ): ?>

                                    <a
                                        href="<?= e(
                                            url(
                                                'student/submit_assignment.php?id=' .
                                                (int) $assignment['id']
                                            )
                                        ) ?>"
                                        class="submission-btn submission-btn-primary"
                                    >
                                        Submit Assignment
                                    </a>

                                <?php elseif (
                                    $submissionStatus === 'submitted' &&
                                    !$isOverdue
                                ): ?>

                                    <a
                                        href="<?= e(
                                            url(
                                                'student/submit_assignment.php?id=' .
                                                (int) $assignment['id']
                                            )
                                        ) ?>"
                                        class="submission-btn submission-btn-outline"
                                    >
                                        Update Submission
                                    </a>

                                <?php endif; ?>

                                <a
                                    href="<?= e(
                                        url(
                                            'student/submit_assignment.php?id=' .
                                            (int) $assignment['id']
                                        )
                                    ) ?>"
                                    class="submission-btn submission-btn-outline"
                                >
                                    View Assignment
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php layout_end(); ?>

