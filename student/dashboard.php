<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Assignment.php';
require_once __DIR__ . '/../classes/Fee.php';
require_once __DIR__ . '/../classes/Mark.php';

requireStudent();

$pdo = db();

$student = current_student($pdo);

layout_start('Student Dashboard', 'dashboard');


/*
|--------------------------------------------------------------------------
| Student Profile Not Found
|--------------------------------------------------------------------------
*/

if (!$student) {
    ?>

    <style>
        .student-ui {
            --primary: #5b5ce2;
            --primary-dark: #4748c9;
            --text: #182033;
            --muted: #7b8498;
            --border: #e8ebf3;
            --surface: #ffffff;
            --background: #f5f7fb;
        }

        .student-ui .status-page {
            min-height: 72vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
            background: var(--background);
        }

        .student-ui .status-card {
            width: 100%;
            max-width: 620px;
            position: relative;
            overflow: hidden;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 55px 40px;
            text-align: center;
            box-shadow: 0 25px 70px rgba(30, 41, 59, .10);
        }

        .student-ui .status-card::before {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: #fff7e8;
            top: -90px;
            right: -70px;
        }

        .student-ui .status-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 25px;
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff7e8;
            color: #f59e0b;
            font-size: 38px;
        }

        .student-ui .status-card h2 {
            color: var(--text);
            font-weight: 800;
        }

        .student-ui .status-card p {
            color: var(--muted);
            line-height: 1.7;
        }
    </style>

    <div class="student-ui">

        <div class="status-page">

            <div class="status-card">

                <div class="status-icon">
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


/*
|--------------------------------------------------------------------------
| Pending Approval
|--------------------------------------------------------------------------
*/

if ($studentStatus === 'pending') {
    ?>

    <style>
        .student-ui {
            --primary: #5b5ce2;
            --primary-dark: #4748c9;
            --text: #182033;
            --muted: #7b8498;
            --border: #e8ebf3;
            --surface: #ffffff;
            --background: #f5f7fb;
        }

        .student-ui .pending-wrapper {
            min-height: 75vh;
            padding: 35px 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(
                    circle at 10% 20%,
                    rgba(91, 92, 226, .08),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 90% 80%,
                    rgba(139, 92, 246, .08),
                    transparent 30%
                );
        }

        .student-ui .pending-card {
            position: relative;
            width: 100%;
            max-width: 760px;
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 32px;
            box-shadow: 0 30px 80px rgba(30, 41, 59, .11);
        }

        .student-ui .pending-cover {
            height: 8px;
            background:
                linear-gradient(
                    90deg,
                    #f59e0b,
                    #f97316,
                    #ef4444
                );
        }

        .student-ui .pending-body {
            padding: 48px;
        }

        .student-ui .pending-icon {
            width: 92px;
            height: 92px;
            margin: 0 auto 24px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                linear-gradient(
                    145deg,
                    #fff8e8,
                    #fff1d6
                );
            color: #f59e0b;
            font-size: 38px;
            box-shadow: 0 12px 30px rgba(245, 158, 11, .12);
        }

        .student-ui .pending-title {
            color: var(--text);
            font-size: 30px;
            font-weight: 850;
            text-align: center;
            margin-bottom: 10px;
        }

        .student-ui .pending-description {
            max-width: 590px;
            margin: 0 auto 30px;
            text-align: center;
            color: var(--muted);
            line-height: 1.75;
        }

        .student-ui .pending-badge {
            display: table;
            margin: 0 auto 25px;
            padding: 8px 15px;
            border-radius: 50px;
            background: #fff7e6;
            color: #c77700;
            font-size: 12px;
            font-weight: 750;
        }

        .student-ui .student-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 28px;
        }

        .student-ui .summary-box {
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #fafbfe;
        }

        .student-ui .summary-label {
            color: #9098aa;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .07em;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .student-ui .summary-value {
            color: var(--text);
            font-size: 14px;
            font-weight: 700;
            word-break: break-word;
        }

        .student-ui .process-title {
            color: #9098aa;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .1em;
            margin-bottom: 13px;
        }

        .student-ui .process {
            display: grid;
            gap: 10px;
        }

        .student-ui .process-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: #fff;
        }

        .student-ui .process-number {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #f3f4ff;
            color: var(--primary);
            font-size: 12px;
            font-weight: 800;
        }

        .student-ui .process-item.completed
        .process-number {
            background: #eafaf2;
            color: #059669;
        }

        .student-ui .process-name {
            color: var(--text);
            font-size: 13px;
            font-weight: 700;
        }

        .student-ui .process-status {
            color: var(--muted);
            font-size: 11px;
            margin-top: 2px;
        }

        .student-ui .process-icon {
            margin-left: auto;
            font-size: 17px;
        }

        @media (max-width: 575px) {

            .student-ui .pending-body {
                padding: 32px 20px;
            }

            .student-ui .pending-title {
                font-size: 24px;
            }

            .student-ui .student-summary {
                grid-template-columns: 1fr;
            }

            .student-ui .pending-card {
                border-radius: 24px;
            }
        }
    </style>

    <div class="student-ui">

        <div class="pending-wrapper">

            <div class="pending-card">

                <div class="pending-cover"></div>

                <div class="pending-body">

                    <div class="pending-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>

                    <div class="pending-badge">
                        <i class="bi bi-clock me-1"></i>
                        Pending Approval
                    </div>

                    <h1 class="pending-title">
                        Registration Received
                    </h1>

                    <p class="pending-description">
                        Your registration has been submitted successfully.
                        An administrator needs to verify your information
                        before your student portal becomes active.
                    </p>

                    <div class="student-summary">

                        <div class="summary-box">

                            <div class="summary-label">
                                Student Name
                            </div>

                            <div class="summary-value">
                                <?= e(
                                    (string) (
                                        $student['name'] ?? ''
                                    )
                                ) ?>
                            </div>

                        </div>

                        <div class="summary-box">

                            <div class="summary-label">
                                Student ID
                            </div>

                            <div class="summary-value">
                                <?= e(
                                    (string) (
                                        $student['student_id']
                                        ?? 'Not assigned'
                                    )
                                ) ?>
                            </div>

                        </div>

                    </div>

                    <div class="process-title">
                        REGISTRATION PROGRESS
                    </div>

                    <div class="process">

                        <div class="process-item completed">

                            <div class="process-number">
                                1
                            </div>

                            <div>
                                <div class="process-name">
                                    Registration submitted
                                </div>

                                <div class="process-status">
                                    Completed
                                </div>
                            </div>

                            <i class="bi bi-check-circle-fill text-success process-icon"></i>

                        </div>

                        <div class="process-item">

                            <div class="process-number">
                                2
                            </div>

                            <div>
                                <div class="process-name">
                                    Administrator verification
                                </div>

                                <div class="process-status">
                                    Waiting for approval
                                </div>
                            </div>

                            <i class="bi bi-clock text-warning process-icon"></i>

                        </div>

                        <div class="process-item">

                            <div class="process-number">
                                3
                            </div>

                            <div>
                                <div class="process-name">
                                    Class & section assignment
                                </div>

                                <div class="process-status">
                                    After verification
                                </div>
                            </div>

                            <i class="bi bi-lock text-muted process-icon"></i>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <?php

    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Inactive / Graduated / Left
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $studentStatus,
        ['inactive', 'graduated', 'left'],
        true
    )
) {
    ?>

    <style>
        .student-ui .blocked-wrapper {
            min-height: 72vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
        }

        .student-ui .blocked-card {
            width: 100%;
            max-width: 620px;
            padding: 50px 35px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf3;
            border-radius: 30px;
            box-shadow: 0 25px 70px rgba(30, 41, 59, .09);
        }

        .student-ui .blocked-icon {
            width: 90px;
            height: 90px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 28px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 37px;
        }
    </style>

    <div class="student-ui">

        <div class="blocked-wrapper">

            <div class="blocked-card">

                <div class="blocked-icon">
                    <i class="bi bi-person-lock"></i>
                </div>

                <h2 class="fw-bold mb-3">
                    Dashboard Unavailable
                </h2>

                <p class="text-muted mb-4">
                    Your student account currently has this status:
                </p>

                <span class="badge rounded-pill text-bg-secondary px-3 py-2">
                    <?= e(ucfirst($studentStatus)) ?>
                </span>

                <p class="small text-muted mt-4 mb-0">
                    Please contact the administrator if you believe
                    this status is incorrect.
                </p>

            </div>

        </div>

    </div>

    <?php

    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Active Student
|--------------------------------------------------------------------------
*/

$assignments = [];

if (
    !empty($student['class_id']) &&
    !empty($student['section_id'])
) {
    $assignments = (new Assignment($pdo))->getStudentAssignments(
        (int) $student['class_id'],
        (int) $student['section_id'],
        (int) $student['id']
    );
}


/*
|--------------------------------------------------------------------------
| Fees
|--------------------------------------------------------------------------
*/

$fee = new Fee($pdo);

$fees = $fee->list(
    '',
    '',
    (string) $student['id']
);

$due = 0.0;

foreach ($fees as $row) {

    $amount = (float) ($row['amount'] ?? 0);

    $paidAmount = (float) (
        $row['paid_amount'] ?? 0
    );

    $due += max(
        $amount - $paidAmount,
        0
    );
}

?>

<style>

/* =========================================================
   STUDENT DASHBOARD DESIGN SYSTEM
========================================================= */

.student-ui {

    --primary: #5b5ce2;
    --primary-dark: #4748c9;
    --secondary: #7c3aed;

    --text: #182033;
    --muted: #7b8498;

    --border: #e8ebf3;

    --surface: #ffffff;
    --background: #f5f7fb;

    --green: #10b981;
    --blue: #3b82f6;
    --orange: #f59e0b;

    color: var(--text);
}


/* =========================================================
   PAGE
========================================================= */

.student-ui .dashboard-page {

    max-width: 1500px;

    margin: 0 auto;

    padding: 10px 0 35px;
}


/* =========================================================
   HERO
========================================================= */

.student-ui .hero {

    position: relative;

    overflow: hidden;

    min-height: 210px;

    padding: 34px;

    border-radius: 30px;

    color: #fff;

    background:
        linear-gradient(
            135deg,
            #4f46e5 0%,
            #6366f1 45%,
            #7c3aed 100%
        );

    box-shadow:
        0 22px 55px
        rgba(79, 70, 229, .22);

    margin-bottom: 22px;
}


/* Decorative circles */

.student-ui .hero::before {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.08);

    top: -150px;
    right: -60px;
}


.student-ui .hero::after {

    content: "";

    position: absolute;

    width: 180px;
    height: 180px;

    border-radius: 50%;

    background:
        rgba(255,255,255,.07);

    bottom: -100px;
    right: 230px;
}


.student-ui .hero-content {

    position: relative;

    z-index: 3;
}


/* Hero top */

.student-ui .hero-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}


.student-ui .student-intro {

    display: flex;

    align-items: center;

    gap: 17px;

    min-width: 0;
}


.student-ui .avatar {

    width: 68px;
    height: 68px;

    flex: 0 0 68px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 22px;

    background:
        rgba(255,255,255,.15);

    border:
        1px solid
        rgba(255,255,255,.22);

    box-shadow:
        inset 0 1px 0
        rgba(255,255,255,.15);

    font-size: 28px;
}


.student-ui .portal-label {

    font-size: 11px;

    font-weight: 800;

    letter-spacing: .13em;

    text-transform: uppercase;

    opacity: .75;

    margin-bottom: 5px;
}


.student-ui .hero-title {

    margin: 0;

    font-size: clamp(24px, 4vw, 34px);

    line-height: 1.15;

    font-weight: 850;

    letter-spacing: -.6px;
}


.student-ui .hero-description {

    margin-top: 7px;

    color: rgba(255,255,255,.78);

    font-size: 13px;

    max-width: 620px;

    line-height: 1.6;
}


.student-ui .active-pill {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 9px 14px;

    border-radius: 50px;

    background:
        rgba(255,255,255,.13);

    border:
        1px solid
        rgba(255,255,255,.2);

    backdrop-filter: blur(10px);

    font-size: 12px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   STAT CARDS
========================================================= */

.student-ui .stats-grid {

    display: grid;

    grid-template-columns:
        repeat(3, minmax(0, 1fr));

    gap: 18px;

    margin-bottom: 22px;
}


.student-ui .stat-card {

    position: relative;

    overflow: hidden;

    min-height: 145px;

    padding: 23px;

    border-radius: 22px;

    background: var(--surface);

    border: 1px solid var(--border);

    box-shadow:
        0 9px 30px
        rgba(30,41,59,.055);

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}


.student-ui .stat-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 18px 42px
        rgba(30,41,59,.10);
}


.student-ui .stat-card::after {

    content: "";

    position: absolute;

    width: 90px;
    height: 90px;

    border-radius: 50%;

    right: -42px;
    bottom: -45px;

    background: #f5f6ff;
}


.student-ui .stat-content {

    position: relative;

    z-index: 2;

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;
}


.student-ui .stat-label {

    color: var(--muted);

    font-size: 12px;

    font-weight: 700;

    margin-bottom: 8px;
}


.student-ui .stat-value {

    color: var(--text);

    font-size: 25px;

    line-height: 1.2;

    font-weight: 850;

    letter-spacing: -.5px;
}


.student-ui .stat-meta {

    color: #a0a8b8;

    font-size: 11px;

    margin-top: 7px;
}


.student-ui .stat-icon {

    width: 50px;
    height: 50px;

    flex: 0 0 50px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 16px;

    font-size: 21px;
}


.student-ui .purple-icon {

    color: var(--primary);

    background: #f0f0ff;
}


.student-ui .blue-icon {

    color: var(--blue);

    background: #eff6ff;
}


.student-ui .green-icon {

    color: var(--green);

    background: #ecfdf5;
}


/* =========================================================
   MAIN GRID
========================================================= */

.student-ui .main-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.8fr)
        minmax(300px, .9fr);

    gap: 22px;

    margin-bottom: 22px;
}


/* =========================================================
   COMMON CARD
========================================================= */

.student-ui .card-box {

    background: var(--surface);

    border:
        1px solid
        var(--border);

    border-radius: 24px;

    box-shadow:
        0 9px 30px
        rgba(30,41,59,.05);

    overflow: hidden;
}


.student-ui .card-head {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 23px 24px 15px;
}


.student-ui .card-heading {

    font-size: 16px;

    font-weight: 800;

    margin: 0;
}


.student-ui .card-subheading {

    color: var(--muted);

    font-size: 11px;

    margin-top: 4px;
}


.student-ui .card-head-icon {

    width: 44px;
    height: 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    background: #f0f0ff;

    color: var(--primary);

    font-size: 18px;
}


/* =========================================================
   PROFILE
========================================================= */

.student-ui .profile-body {

    padding: 0 24px 22px;
}


.student-ui .profile-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 0 25px;
}


.student-ui .profile-item {

    padding: 15px 0;

    border-bottom:
        1px solid
        #f0f2f6;
}


.student-ui .profile-label {

    color: #929aac;

    font-size: 10px;

    text-transform: uppercase;

    letter-spacing: .07em;

    font-weight: 750;

    margin-bottom: 5px;
}


.student-ui .profile-value {

    color: var(--text);

    font-size: 13px;

    font-weight: 700;

    word-break: break-word;
}


.student-ui .status-active {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 6px 10px;

    border-radius: 50px;

    color: #047857;

    background: #ecfdf5;

    font-size: 10px;

    font-weight: 800;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.student-ui .actions-body {

    padding: 0 20px 22px;
}


.student-ui .action-link {

    position: relative;

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 13px;

    margin-top: 9px;

    border:
        1px solid
        #edf0f5;

    border-radius: 17px;

    text-decoration: none;

    color: var(--text);

    background: #fff;

    transition:
        transform .2s ease,
        border-color .2s ease,
        background .2s ease;
}


.student-ui .action-link:hover {

    color: var(--primary);

    background: #fafaff;

    border-color: #d9d9ff;

    transform:
        translateX(4px);
}


.student-ui .action-icon {

    width: 43px;
    height: 43px;

    flex: 0 0 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    color: var(--primary);

    background: #f0f0ff;

    font-size: 18px;
}


.student-ui .action-title {

    font-size: 13px;

    font-weight: 800;
}


.student-ui .action-description {

    color: #929aac;

    font-size: 10px;

    margin-top: 2px;
}


.student-ui .action-arrow {

    margin-left: auto;

    color: #a8afbd;

    font-size: 13px;
}


/* =========================================================
   ASSIGNMENTS
========================================================= */

.student-ui .assignments-card {

    margin-bottom: 0;
}


.student-ui .view-all {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 7px 12px;

    border-radius: 50px;

    color: var(--primary);

    background: #f3f3ff;

    text-decoration: none;

    font-size: 10px;

    font-weight: 800;

    transition: .2s ease;
}


.student-ui .view-all:hover {

    color: #fff;

    background: var(--primary);
}


.student-ui .assignment-list {

    padding: 0 24px 10px;
}


.student-ui .assignment {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 15px 0;

    border-bottom:
        1px solid
        #f0f2f6;
}


.student-ui .assignment:last-child {

    border-bottom: 0;
}


.student-ui .assignment-icon {

    width: 44px;
    height: 44px;

    flex: 0 0 44px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 14px;

    color: #7c3aed;

    background: #f5f3ff;

    font-size: 18px;
}


.student-ui .assignment-content {

    min-width: 0;

    flex: 1;
}


.student-ui .assignment-title {

    color: var(--text);

    font-size: 13px;

    font-weight: 750;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.student-ui .assignment-subject {

    color: #929aac;

    font-size: 10px;

    margin-top: 4px;
}


.student-ui .assignment-due {

    flex: 0 0 auto;

    padding: 6px 9px;

    border-radius: 8px;

    color: #64748b;

    background: #f8fafc;

    border:
        1px solid
        #edf0f5;

    font-size: 10px;

    white-space: nowrap;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.student-ui .empty-state {

    padding: 45px 20px;

    text-align: center;
}


.student-ui .empty-icon {

    width: 65px;
    height: 65px;

    margin: 0 auto 15px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 20px;

    background: #f7f8fb;

    color: #9aa3b4;

    font-size: 25px;
}


.student-ui .empty-title {

    color: var(--text);

    font-size: 14px;

    font-weight: 800;

    margin-bottom: 5px;
}


.student-ui .empty-text {

    color: var(--muted);

    font-size: 11px;

    margin: 0;
}


/* =========================================================
   RESPONSIVE TABLET
========================================================= */

@media (max-width: 991.98px) {

    .student-ui .main-grid {

        grid-template-columns: 1fr;
    }

}


/* =========================================================
   RESPONSIVE MOBILE
========================================================= */

@media (max-width: 767.98px) {

    .student-ui .dashboard-page {

        padding:
            5px 0
            25px;
    }


    .student-ui .hero {

        padding: 23px;

        border-radius: 23px;

        min-height: auto;
    }


    .student-ui .hero-top {

        align-items: flex-start;
    }


    .student-ui .student-intro {

        gap: 12px;
    }


    .student-ui .avatar {

        width: 56px;
        height: 56px;

        flex-basis: 56px;

        border-radius: 18px;

        font-size: 22px;
    }


    .student-ui .hero-title {

        font-size: 23px;
    }


    .student-ui .hero-description {

        font-size: 11px;
    }


    .student-ui .active-pill {

        padding: 7px 10px;

        font-size: 10px;
    }


    .student-ui .stats-grid {

        grid-template-columns: 1fr;

        gap: 12px;
    }


    .student-ui .stat-card {

        min-height: 115px;

        padding: 19px;

        border-radius: 18px;
    }


    .student-ui .stat-value {

        font-size: 22px;
    }


    .student-ui .card-box {

        border-radius: 20px;
    }


    .student-ui .card-head {

        padding:
            19px 18px
            13px;
    }


    .student-ui .profile-body {

        padding:
            0 18px
            18px;
    }


    .student-ui .profile-grid {

        grid-template-columns: 1fr;
    }


    .student-ui .actions-body {

        padding:
            0 16px
            18px;
    }


    .student-ui .assignment-list {

        padding:
            0 18px
            8px;
    }

}


/* =========================================================
   SMALL MOBILE
========================================================= */

@media (max-width: 480px) {

    .student-ui .hero-top {

        flex-direction: column;
    }


    .student-ui .active-pill {

        align-self: flex-start;
    }


    .student-ui .student-intro {

        width: 100%;
    }


    .student-ui .hero-description {

        max-width: 100%;
    }


    .student-ui .assignment {

        align-items: flex-start;
    }


    .student-ui .assignment-due {

        font-size: 9px;

        padding:
            5px 7px;

        white-space: normal;

        text-align: center;

        max-width: 75px;
    }

}

</style>


<div class="student-ui">

    <div class="dashboard-page">


        <!-- =====================================================
             HERO
        ====================================================== -->

        <section class="hero">

            <div class="hero-content">

                <div class="hero-top">

                    <div class="student-intro">

                        <div class="avatar">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>

                        <div>

                            <div class="portal-label">
                                Student Portal
                            </div>

                            <h1 class="hero-title">
                                Welcome,
                                <?= e(
                                    (string) (
                                        $student['name']
                                        ?? 'Student'
                                    )
                                ) ?>
                            </h1>

                            <div class="hero-description">
                                Manage your academic activities,
                                assignments and student information
                                from one place.
                            </div>

                        </div>

                    </div>


                    <div class="active-pill">

                        <i class="bi bi-check-circle-fill"></i>

                        Active Student

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             STATISTICS
        ====================================================== -->

        <div class="stats-grid">


            <!-- Class -->
            <div class="stat-card">

                <div class="stat-content">

                    <div>

                        <div class="stat-label">
                            Current Class
                        </div>

                        <div class="stat-value">

                            <?= e(
                                (string) (
                                    $student['class_name']
                                    ?? 'Not assigned'
                                )
                            ) ?>

                            <?php if (
                                !empty(
                                    $student['section_name']
                                )
                            ): ?>

                                <span
                                    class="text-muted fw-normal"
                                    style="font-size:.7em;"
                                >
                                    /
                                    <?= e(
                                        (string) (
                                            $student['section_name']
                                        )
                                    ) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="stat-meta">
                            Academic placement
                        </div>

                    </div>

                    <div class="stat-icon purple-icon">
                        <i class="bi bi-mortarboard"></i>
                    </div>

                </div>

            </div>


            <!-- Assignments -->
            <div class="stat-card">

                <div class="stat-content">

                    <div>

                        <div class="stat-label">
                            Open Assignments
                        </div>

                        <div class="stat-value">
                            <?= count($assignments) ?>
                        </div>

                        <div class="stat-meta">
                            Available for you
                        </div>

                    </div>

                    <div class="stat-icon blue-icon">
                        <i class="bi bi-journal-text"></i>
                    </div>

                </div>

            </div>


            <!-- Fees -->
            <div class="stat-card">

                <div class="stat-content">

                    <div>

                        <div class="stat-label">
                            Fee Balance
                        </div>

                        <div class="stat-value">
                            <?= number_format(
                                $due,
                                2
                            ) ?>
                        </div>

                        <div class="stat-meta">
                            Outstanding amount
                        </div>

                    </div>

                    <div class="stat-icon green-icon">
                        <i class="bi bi-wallet2"></i>
                    </div>

                </div>

            </div>

        </div>


        <?php
        /*
         * Chart data: this student's attendance by status.
         */
        $attChartStmt = $pdo->prepare("
            SELECT status, COUNT(*) AS total
            FROM attendance
            WHERE student_id = :sid
            GROUP BY status
        ");
        $attChartStmt->execute([':sid' => (int) $student['id']]);
        $attChartRows = $attChartStmt->fetchAll(PDO::FETCH_ASSOC);

        $attLabels = [];
        $attTotals = [];
        $attMap = [
            'present' => 'Present',
            'absent'  => 'Absent',
            'late'    => 'Late',
            'leave'   => 'Leave',
        ];

        foreach ($attChartRows as $attRow) {
            $attStatusLabel = $attMap[(string) $attRow['status']]
                ?? ucfirst((string) $attRow['status']);
            $attLabels[] = $attStatusLabel;
            $attTotals[] = (int) $attRow['total'];
        }
        ?>


        <!-- =====================================================
             ATTENDANCE CHART
        ====================================================== -->

        <div class="card-box" style="margin-bottom: 18px;">

            <div class="card-head">

                <div>

                    <div style="font-weight: 700;">
                        Attendance Overview
                    </div>

                    <div class="text-muted" style="font-size: .85rem;">
                        Your attendance records by status
                    </div>

                </div>

            </div>

            <div style="padding-top: 8px;">

                <canvas id="studentAttendanceChart" height="80"></canvas>

            </div>

        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            var labels = <?= json_encode($attLabels) ?>;
            var totals = <?= json_encode($attTotals) ?>;
            var el = document.getElementById('studentAttendanceChart');

            if (!el) {
                return;
            }

            if (totals.length === 0) {
                el.replaceWith(Object.assign(document.createElement('p'), {
                    className: 'text-muted small mb-0',
                    textContent: 'No attendance records yet.'
                }));
                return;
            }

            new Chart(el, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: totals,
                        backgroundColor: ['#16a34a', '#dc2626', '#f59e0b', '#6366f1']
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        })();
        </script>


        <!-- =====================================================
             MAIN CONTENT
        ====================================================== -->

        <div class="main-grid">


            <!-- =================================================
                 PROFILE
            ================================================== -->

            <div class="card-box">

                <div class="card-head">

                    <div>

                        <h2 class="card-heading">
                            Academic Information
                        </h2>

                        <div class="card-subheading">
                            Your current student profile
                        </div>

                    </div>

                    <div class="card-head-icon">
                        <i class="bi bi-person-vcard"></i>
                    </div>

                </div>


                <div class="profile-body">

                    <div class="profile-grid">


                        <!-- Student ID -->
                        <div class="profile-item">

                            <div class="profile-label">
                                Student ID
                            </div>

                            <div class="profile-value">
                                <?= e(
                                    (string) (
                                        $student['student_id']
                                        ?? 'Not available'
                                    )
                                ) ?>
                            </div>

                        </div>


                        <!-- Name -->
                        <div class="profile-item">

                            <div class="profile-label">
                                Student Name
                            </div>

                            <div class="profile-value">
                                <?= e(
                                    (string) (
                                        $student['name']
                                        ?? ''
                                    )
                                ) ?>
                            </div>

                        </div>


                        <!-- Class -->
                        <div class="profile-item">

                            <div class="profile-label">
                                Class
                            </div>

                            <div class="profile-value">
                                <?= e(
                                    (string) (
                                        $student['class_name']
                                        ?? 'Not assigned'
                                    )
                                ) ?>
                            </div>

                        </div>


                        <!-- Section -->
                        <div class="profile-item">

                            <div class="profile-label">
                                Section
                            </div>

                            <div class="profile-value">
                                <?= e(
                                    (string) (
                                        $student['section_name']
                                        ?? 'Not assigned'
                                    )
                                ) ?>
                            </div>

                        </div>


                        <!-- Email -->
                        <div class="profile-item">

                            <div class="profile-label">
                                Email Address
                            </div>

                            <div class="profile-value">
                                <?= e(
                                    (string) (
                                        $student['email']
                                        ?? ''
                                    )
                                ) ?>
                            </div>

                        </div>


                        <!-- Status -->
                        <div class="profile-item">

                            <div class="profile-label">
                                Account Status
                            </div>

                            <div>

                                <span class="status-active">

                                    <i class="bi bi-check-circle-fill"></i>

                                    Active

                                </span>

                            </div>

                        </div>


                    </div>

                </div>

            </div>


            <!-- =================================================
                 QUICK ACTIONS
            ================================================== -->

            <div class="card-box">

                <div class="card-head">

                    <div>

                        <h2 class="card-heading">
                            Quick Actions
                        </h2>

                        <div class="card-subheading">
                            Student portal shortcuts
                        </div>

                    </div>

                    <div class="card-head-icon">
                        <i class="bi bi-grid"></i>
                    </div>

                </div>


                <div class="actions-body">


                    <!-- Assignments -->
                    <a
                        class="action-link"
                        href="<?= e(
                            url(
                                'student/assignments.php'
                            )
                        ) ?>"
                    >

                        <div class="action-icon">
                            <i class="bi bi-journal-text"></i>
                        </div>

                        <div>

                            <div class="action-title">
                                Assignments
                            </div>

                            <div class="action-description">
                                View and submit assignments
                            </div>

                        </div>

                        <i
                            class="bi bi-chevron-right action-arrow"
                        ></i>

                    </a>


                    <!-- Results -->
                    <a
                        class="action-link"
                        href="<?= e(
                            url(
                                'student/results.php'
                            )
                        ) ?>"
                    >

                        <div class="action-icon">
                            <i class="bi bi-bar-chart-line"></i>
                        </div>

                        <div>

                            <div class="action-title">
                                Results
                            </div>

                            <div class="action-description">
                                Check academic results
                            </div>

                        </div>

                        <i
                            class="bi bi-chevron-right action-arrow"
                        ></i>

                    </a>


                    <!-- Profile -->
                    <a
                        class="action-link"
                        href="#"
                        onclick="return false;"
                    >

                        <div class="action-icon">
                            <i class="bi bi-person"></i>
                        </div>

                        <div>

                            <div class="action-title">
                                My Profile
                            </div>

                            <div class="action-description">
                                Student profile information
                            </div>

                        </div>

                        <i
                            class="bi bi-chevron-right action-arrow"
                        ></i>

                    </a>

                </div>

            </div>

        </div>


        <!-- =====================================================
             ASSIGNMENTS
        ====================================================== -->

        <div class="card-box assignments-card">

            <div class="card-head">

                <div>

                    <h2 class="card-heading">
                        Recent Assignments
                    </h2>

                    <div class="card-subheading">
                        Latest assignments available for you
                    </div>

                </div>


                <a
                    class="view-all"
                    href="<?= e(
                        url(
                            'student/assignments.php'
                        )
                    ) ?>"
                >
                    View All
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>


            <div class="assignment-list">

                <?php if (empty($assignments)): ?>

                    <div class="empty-state">

                        <div class="empty-icon">
                            <i class="bi bi-journal-x"></i>
                        </div>

                        <div class="empty-title">
                            No assignments yet
                        </div>

                        <p class="empty-text">
                            There are currently no assignments
                            available for your class.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach (
                        array_slice(
                            $assignments,
                            0,
                            5
                        ) as $assignment
                    ): ?>

                        <div class="assignment">


                            <div class="assignment-icon">

                                <i class="bi bi-file-earmark-text"></i>

                            </div>


                            <div class="assignment-content">

                                <div class="assignment-title">

                                    <?= e(
                                        (string) (
                                            $assignment['title']
                                            ?? 'Assignment'
                                        )
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $assignment['subject_name']
                                    )
                                ): ?>

                                    <div class="assignment-subject">

                                        <i class="bi bi-book me-1"></i>

                                        <?= e(
                                            (string) (
                                                $assignment[
                                                    'subject_name'
                                                ]
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <?php if (
                                !empty(
                                    $assignment['due_date']
                                )
                            ): ?>

                                <div class="assignment-due">

                                    <i class="bi bi-calendar3 me-1"></i>

                                    <?= e(
                                        (string) (
                                            $assignment['due_date']
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>


                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>


    </div>

</div>


<?php

layout_end();
?>