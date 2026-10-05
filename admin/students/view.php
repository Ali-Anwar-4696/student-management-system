<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';

$pdo = db();
$database = null;


$studentObj = new Student($pdo);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/*
|--------------------------------------------------------------------------
| Get Student ID
|--------------------------------------------------------------------------
*/

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Student
|--------------------------------------------------------------------------
*/

$student = $studentObj->getStudentById($id);

if (!$student) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Classes
|--------------------------------------------------------------------------
*/

$classes = $studentObj->getClasses();

/*
|--------------------------------------------------------------------------
| Get Selected Class Sections
|--------------------------------------------------------------------------
*/

$sections = [];

if (!empty($student['class_id'])) {
    $sections = $studentObj->getSectionsByClass(
        (int) $student['class_id']
    );
}

/*
|--------------------------------------------------------------------------
| Message
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| Approve Student
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {

        /*
        |----------------------------------------------------------------------
        | Class
        |----------------------------------------------------------------------
        */

        $classId = filter_input(
            INPUT_POST,
            'class_id',
            FILTER_VALIDATE_INT
        );

        /*
        |----------------------------------------------------------------------
        | Section
        |----------------------------------------------------------------------
        |
        | Section is optional.
        | Empty section becomes NULL.
        |
        */

        $sectionId = filter_input(
            INPUT_POST,
            'section_id',
            FILTER_VALIDATE_INT
        );

        if (!$classId || $classId <= 0) {

            $message = 'Please select a class.';
            $messageType = 'error';

        } else {

            $approved = $studentObj->approveStudent(
                (int) $id,
                (int) $classId,
                $sectionId && $sectionId > 0
                    ? (int) $sectionId
                    : null
            );

            if ($approved) {

                /*
                | Non-blocking notification — silent no-op until
                | mail_enabled/sms_enabled are configured.
                */
                notify_student(
                    (int) $id,
                    'Your student account has been approved',
                    "Hello {name},\n\n"
                    . "Your student account has been approved. You can now "
                    . "log in to the student portal to view your "
                    . "attendance, results and fees.\n\n"
                    . "- School Administration",
                    'Your student account has been approved. You can now '
                    . 'log in to the student portal.'
                );

                header(
                    'Location: view.php?id=' .
                    $id .
                    '&approved=1'
                );

                exit;

            } else {

                $message =
                    'Student could not be approved. Please verify the student and selected class/section.';

                $messageType = 'error';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Success Message
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['approved']) &&
    $_GET['approved'] === '1'
) {

    $message =
        'Student approved successfully. The student is now active.';

    $messageType = 'success';

    /*
    |----------------------------------------------------------------------
    | Refresh Student
    |----------------------------------------------------------------------
    */

    $student = $studentObj->getStudentById($id);

    if (!empty($student['class_id'])) {

        $sections = $studentObj->getSectionsByClass(
            (int) $student['class_id']
        );

    } else {

        $sections = [];
    }
}

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

$status = strtolower(
    (string) ($student['status'] ?? 'pending')
);

$statusLabels = [

    'pending'   => 'Pending Approval',
    'active'    => 'Active',
    'inactive'  => 'Inactive',
    'graduated' => 'Graduated',
    'left'      => 'Left',

];

$statusLabel =
    $statusLabels[$status] ?? ucfirst($status);

/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

$statusClass = match ($status) {

    'pending'   => 'pending',
    'active'    => 'active',
    'inactive'  => 'inactive',
    'graduated' => 'graduated',
    'left'      => 'left',

    default => 'inactive',

};

/*
|--------------------------------------------------------------------------
| Student Information
|--------------------------------------------------------------------------
*/

$studentName =
    $student['name'] ?? 'Unknown Student';

$studentId =
    $student['student_id'] ?? '-';

$email =
    $student['email'] ?? '-';

$phone =
    $student['phone'] ?? '-';

$fatherName =
    $student['father_name'] ?? '-';

$gender =
    $student['gender'] ?? '-';

$dateOfBirth =
    $student['date_of_birth'] ?? '-';

$address =
    $student['address'] ?? '-';

$admissionDate =
    $student['admission_date'] ?? '-';

$className =
    $student['class_name'] ?? 'Not Assigned';

$sectionName =
    $student['section_name'] ?? 'Not Assigned';

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

if (!function_exists('formatDateValue')) {

    function formatDateValue(?string $date): string
    {
        if (
            !$date ||
            $date === '0000-00-00'
        ) {
            return '-';
        }

        $timestamp = strtotime($date);

        if (!$timestamp) {
            return e($date);
        }

        return date('d M Y', $timestamp);
    }
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
        <?= e($studentName) ?> - Student Profile
    </title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Arial,
                sans-serif;

            background: #f5f7fb;
            color: #1f2937;
            line-height: 1.6;
        }

        body.modal-open {
            overflow: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px 50px;
        }

        /* ================================================================
           HEADER
        ================================================================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
        }

        .page-title p {
            margin-top: 4px;
            color: #6b7280;
            font-size: 14px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            padding: 10px 16px;

            border-radius: 9px;
            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            border: 1px solid transparent;
            cursor: pointer;

            transition:
                transform 0.2s ease,
                background 0.2s ease,
                box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #ffffff;
            border-color: #d1d5db;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-success {
            background: #16a34a;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #15803d;
        }

        /* ================================================================
           TOAST
        ================================================================= */

        .toast-container {
            position: fixed;
            top: 22px;
            right: 22px;
            z-index: 9999;

            width: min(390px, calc(100vw - 30px));
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 13px;

            background: #ffffff;

            border: 1px solid #e5e7eb;
            border-radius: 14px;

            padding: 16px 17px;

            box-shadow:
                0 15px 40px
                rgba(15, 23, 42, 0.15);

            animation:
                toastIn 0.35s ease forwards;
        }

        .toast.success {
            border-left: 4px solid #16a34a;
        }

        .toast.error {
            border-left: 4px solid #dc2626;
        }

        .toast-icon {
            width: 38px;
            height: 38px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            flex-shrink: 0;

            font-size: 18px;
            font-weight: 800;
        }

        .toast.success .toast-icon {
            background: #dcfce7;
            color: #15803d;
        }

        .toast.error .toast-icon {
            background: #fee2e2;
            color: #b91c1c;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 2px;
        }

        .toast-message {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }

        .toast-close {
            border: none;
            background: transparent;
            color: #9ca3af;
            font-size: 19px;
            cursor: pointer;
            padding: 0 2px;
        }

        .toast-close:hover {
            color: #374151;
        }

        @keyframes toastIn {

            from {
                opacity: 0;
                transform: translateX(35px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }

        }

        @keyframes toastOut {

            from {
                opacity: 1;
                transform: translateX(0);
            }

            to {
                opacity: 0;
                transform: translateX(35px);
            }

        }

        .toast.hide {
            animation:
                toastOut 0.3s ease forwards;
        }

        /* ================================================================
           PROFILE
        ================================================================= */

        .profile-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;

            box-shadow:
                0 5px 18px
                rgba(15, 23, 42, 0.05);
        }

        .profile-top {
            padding: 28px;

            display: flex;
            align-items: center;

            gap: 20px;

            border-bottom: 1px solid #e5e7eb;
        }

        .avatar {
            width: 72px;
            height: 72px;

            border-radius: 18px;

            background: #eff6ff;
            color: #2563eb;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 27px;
            font-weight: 700;

            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            font-size: 22px;
            color: #111827;
            margin-bottom: 3px;
        }

        .profile-info p {
            color: #6b7280;
            font-size: 14px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;

            padding: 7px 12px;

            border-radius: 999px;

            font-size: 12px;
            font-weight: 700;

            white-space: nowrap;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.active {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.graduated {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-badge.left {
            background: #f3f4f6;
            color: #4b5563;
        }

        /* ================================================================
           SECTIONS
        ================================================================= */

        .section {
            padding: 25px 28px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 18px;
        }

        .info-grid {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 20px;
        }

        .info-item {
            min-width: 0;
        }

        .info-label {
            font-size: 12px;
            color: #6b7280;

            margin-bottom: 4px;

            text-transform: uppercase;
            letter-spacing: 0.4px;

            font-weight: 600;
        }

        .info-value {
            font-size: 14px;
            color: #111827;

            font-weight: 500;

            word-break: break-word;
        }

        /* ================================================================
           APPROVAL
        ================================================================= */

        .approval-box {
            background: #fffbeb;

            border: 1px solid #fde68a;
            border-radius: 12px;

            padding: 22px;
        }

        .approval-box h3 {
            color: #92400e;

            font-size: 17px;

            margin-bottom: 6px;
        }

        .approval-box p {
            color: #78350f;

            font-size: 13px;

            margin-bottom: 20px;
        }

        .approval-form {
            display: grid;

            grid-template-columns:
                1fr 1fr auto;

            gap: 14px;

            align-items: end;
        }

        .form-group label {
            display: block;

            font-size: 13px;
            font-weight: 600;

            color: #374151;

            margin-bottom: 7px;
        }

        .form-control {
            width: 100%;
            height: 43px;

            border: 1px solid #d1d5db;
            border-radius: 8px;

            padding: 0 12px;

            background: #ffffff;
            color: #111827;

            font-size: 14px;

            outline: none;
        }

        .form-control:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        .approval-action {
            display: flex;
        }

        .approval-action .btn {
            height: 43px;
            white-space: nowrap;
        }

        /* ================================================================
           ASSIGNMENT
        ================================================================= */

        .assignment-box {
            background: #f8fafc;

            border: 1px solid #e5e7eb;
            border-radius: 12px;

            padding: 20px;
        }

        .assignment-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;
        }

        /* ================================================================
           MODAL
        ================================================================= */

        .modal-overlay {
            position: fixed;
            inset: 0;

            background:
                rgba(15, 23, 42, 0.58);

            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            z-index: 10000;

            opacity: 0;
            visibility: hidden;

            transition:
                opacity 0.25s ease,
                visibility 0.25s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .approval-modal {
            width: min(470px, 100%);

            background: #ffffff;

            border-radius: 20px;

            padding: 30px;

            box-shadow:
                0 30px 80px
                rgba(15, 23, 42, 0.30);

            transform:
                translateY(18px)
                scale(0.96);

            transition:
                transform 0.28s ease;
        }

        .modal-overlay.show .approval-modal {
            transform:
                translateY(0)
                scale(1);
        }

        .modal-close {
            position: absolute;
            right: 16px;
            top: 14px;

            width: 34px;
            height: 34px;

            border: none;
            border-radius: 50%;

            background: #f3f4f6;
            color: #6b7280;

            font-size: 21px;

            cursor: pointer;
        }

        .modal-close:hover {
            background: #e5e7eb;
            color: #111827;
        }

        .modal-header {
            position: relative;
            text-align: center;
        }

        .modal-icon {
            width: 70px;
            height: 70px;

            margin: 0 auto 18px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #dcfce7,
                    #bbf7d0
                );

            color: #15803d;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 31px;
            font-weight: 800;

            box-shadow:
                0 8px 25px
                rgba(22, 163, 74, 0.16);
        }

        .modal-header h2 {
            font-size: 23px;
            color: #111827;

            margin-bottom: 7px;
        }

        .modal-header p {
            color: #6b7280;

            font-size: 14px;

            line-height: 1.6;

            max-width: 380px;

            margin: 0 auto;
        }

        .modal-summary {
            margin-top: 23px;

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 13px;

            padding: 15px 17px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;

            padding: 7px 0;
        }

        .summary-row + .summary-row {
            border-top: 1px solid #e5e7eb;
        }

        .summary-row span {
            color: #6b7280;
            font-size: 13px;
        }

        .summary-row strong {
            color: #111827;
            font-size: 13px;
            text-align: right;
        }

        .modal-warning {
            display: flex;
            gap: 10px;

            margin-top: 16px;

            padding: 12px 14px;

            background: #fffbeb;

            border: 1px solid #fde68a;

            border-radius: 10px;

            color: #92400e;

            font-size: 12px;

            line-height: 1.5;
        }

        .modal-actions {
            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 11px;

            margin-top: 23px;
        }

        .modal-actions .btn {
            height: 45px;
        }

        .modal-cancel {
            background: #ffffff;

            border: 1px solid #d1d5db;

            color: #374151;
        }

        .modal-cancel:hover {
            background: #f9fafb;
        }

        .modal-confirm {
            background: #16a34a;
            color: #ffffff;

            box-shadow:
                0 7px 18px
                rgba(22, 163, 74, 0.20);
        }

        .modal-confirm:hover {
            background: #15803d;
        }

        /* ================================================================
           RESPONSIVE
        ================================================================= */

        @media (max-width: 900px) {

            .info-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .approval-form {
                grid-template-columns:
                    1fr 1fr;
            }

            .approval-action {
                grid-column: 1 / -1;
            }

        }

        @media (max-width: 650px) {

            .container {
                padding: 20px 14px 40px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .profile-top {
                align-items: flex-start;
                flex-direction: column;
            }

            .info-grid,
            .assignment-grid,
            .approval-form {
                grid-template-columns: 1fr;
            }

            .approval-action {
                grid-column: auto;
            }

            .section,
            .profile-top {
                padding: 20px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .approval-modal {
                padding: 25px 20px;
            }

            .modal-actions {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- ================================================================
     TOAST MESSAGE
================================================================= -->

<?php if ($message !== ''): ?>

    <div class="toast-container">

        <div
            id="pageToast"
            class="toast <?= $messageType === 'success'
                ? 'success'
                : 'error' ?>"
        >

            <div class="toast-icon">

                <?= $messageType === 'success'
                    ? '✓'
                    : '!' ?>

            </div>

            <div class="toast-content">

                <div class="toast-title">

                    <?= $messageType === 'success'
                        ? 'Success'
                        : 'Action Failed' ?>

                </div>

                <div class="toast-message">
                    <?= e($message) ?>
                </div>

            </div>

            <button
                type="button"
                class="toast-close"
                onclick="closeToast()"
                aria-label="Close"
            >
                ×
            </button>

        </div>

    </div>

<?php endif; ?>


<div class="container">

    <!-- ============================================================
         HEADER
    ============================================================= -->

    <div class="page-header">

        <div class="page-title">

            <h1>Student Profile</h1>

            <p>
                View and manage student information
            </p>

        </div>

        <div class="header-actions">

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                ← Back
            </a>

            <a
                href="edit.php?id=<?= (int) $student['id'] ?>"
                class="btn btn-primary"
            >
                Edit Student
            </a>

        </div>

    </div>


    <!-- ============================================================
         PROFILE CARD
    ============================================================= -->

    <div class="profile-card">

        <!-- Profile Header -->

        <div class="profile-top">

            <div class="avatar">

                <?= e(
                    strtoupper(
                        substr(
                            $studentName,
                            0,
                            1
                        )
                    )
                ) ?>

            </div>

            <div class="profile-info">

                <h2>
                    <?= e($studentName) ?>
                </h2>

                <p>

                    Student ID:

                    <strong>
                        <?= e($studentId) ?>
                    </strong>

                </p>

            </div>

            <span
                class="status-badge <?= e($statusClass) ?>"
            >
                <?= e($statusLabel) ?>
            </span>

        </div>


        <!-- ========================================================
             PERSONAL INFORMATION
        ========================================================= -->

        <div class="section">

            <div class="section-title">
                Personal Information
            </div>

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        Full Name
                    </div>

                    <div class="info-value">
                        <?= e($studentName) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Father Name
                    </div>

                    <div class="info-value">
                        <?= e($fatherName) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Gender
                    </div>

                    <div class="info-value">
                        <?= e(ucfirst($gender)) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Date of Birth
                    </div>

                    <div class="info-value">
                        <?= formatDateValue($dateOfBirth) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Admission Date
                    </div>

                    <div class="info-value">
                        <?= formatDateValue($admissionDate) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Student Status
                    </div>

                    <div class="info-value">
                        <?= e($statusLabel) ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================
             CONTACT INFORMATION
        ========================================================= -->

        <div class="section">

            <div class="section-title">
                Contact Information
            </div>

            <div class="info-grid">

                <div class="info-item">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">
                        <?= e($email) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="info-value">
                        <?= e($phone) ?>
                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Address
                    </div>

                    <div class="info-value">
                        <?= e($address) ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================
             CURRENT ASSIGNMENT
        ========================================================= -->

        <div class="section">

            <div class="section-title">
                Academic Assignment
            </div>

            <div class="assignment-box">

                <div class="assignment-grid">

                    <div class="info-item">

                        <div class="info-label">
                            Class
                        </div>

                        <div class="info-value">
                            <?= e($className) ?>
                        </div>

                    </div>


                    <div class="info-item">

                        <div class="info-label">
                            Section
                        </div>

                        <div class="info-value">
                            <?= e($sectionName) ?>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================
             APPROVAL
        ========================================================= -->

        <?php if ($status === 'pending'): ?>

            <div class="section">

                <div class="approval-box">

                    <h3>
                        Student Approval Required
                    </h3>

                    <p>
                        Verify this student's information, then
                        assign a class and optional section before
                        activating the student account.
                    </p>


                    <form
                        method="POST"
                        class="approval-form"
                        id="approvalForm"
                        onsubmit="return openApprovalModal();"
                    >
                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="action"
                            value="approve"
                        >


                        <!-- Class -->

                        <div class="form-group">

                            <label for="class_id">
                                Assign Class
                            </label>

                            <select
                                name="class_id"
                                id="class_id"
                                class="form-control"
                                required
                            >

                                <option value="">
                                    Select Class
                                </option>

                                <?php foreach ($classes as $class): ?>

                                    <option
                                        value="<?= (int) $class['id'] ?>"
                                        <?= (int) ($student['class_id'] ?? 0)
                                            === (int) $class['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= e($class['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Section -->

                        <div class="form-group">

                            <label for="section_id">
                                Assign Section
                            </label>

                            <select
                                name="section_id"
                                id="section_id"
                                class="form-control"
                            >

                                <option value="">
                                    Select Section
                                </option>

                                <?php foreach ($sections as $section): ?>

                                    <option
                                        value="<?= (int) $section['id'] ?>"
                                        <?= (int) ($student['section_id'] ?? 0)
                                            === (int) $section['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= e($section['name']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- Approve -->

                        <div class="approval-action">

                            <button
                                type="submit"
                                class="btn btn-success"
                            >
                                ✓ Approve & Activate
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        <?php endif; ?>


    </div>

</div>


<!-- ================================================================
     MODERN APPROVAL MODAL
================================================================= -->

<div
    id="approvalModal"
    class="modal-overlay"
    aria-hidden="true"
>

    <div
        class="approval-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="approvalModalTitle"
    >

        <div class="modal-header">

            <button
                type="button"
                class="modal-close"
                onclick="closeApprovalModal()"
                aria-label="Close"
            >
                ×
            </button>

            <div class="modal-icon">
                ✓
            </div>

            <h2 id="approvalModalTitle">
                Approve Student?
            </h2>

            <p>
                You are about to activate this student account.
                Please review the assignment details before continuing.
            </p>

        </div>


        <!-- Summary -->

        <div class="modal-summary">

            <div class="summary-row">

                <span>
                    Student
                </span>

                <strong id="modalStudentName">
                    <?= e($studentName) ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Student ID
                </span>

                <strong>
                    <?= e($studentId) ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Class
                </span>

                <strong id="modalClassName">
                    Not Selected
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Section
                </span>

                <strong id="modalSectionName">
                    Not Assigned
                </strong>

            </div>

        </div>


        <!-- Warning -->

        <div class="modal-warning">

            <span>
                ⚠
            </span>

            <span>
                After approval, the student's status will change
                from <strong>Pending</strong> to <strong>Active</strong>.
            </span>

        </div>


        <!-- Actions -->

        <div class="modal-actions">

            <button
                type="button"
                class="btn modal-cancel"
                onclick="closeApprovalModal()"
            >
                Cancel
            </button>

            <button
                type="button"
                class="btn modal-confirm"
                onclick="submitApproval()"
            >
                ✓ Approve & Activate
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const classSelect =
    document.getElementById('class_id');

const sectionSelect =
    document.getElementById('section_id');

const approvalForm =
    document.getElementById('approvalForm');

const approvalModal =
    document.getElementById('approvalModal');

const modalClassName =
    document.getElementById('modalClassName');

const modalSectionName =
    document.getElementById('modalSectionName');


/*
|--------------------------------------------------------------------------
| Load Sections
|--------------------------------------------------------------------------
*/

function loadSections(
    classId,
    selectedSectionId = ''
) {

    if (!sectionSelect) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | No Class
    |--------------------------------------------------------------------------
    */

    if (!classId) {

        sectionSelect.innerHTML =
            '<option value="">Select Section</option>';

        sectionSelect.required = false;

        sectionSelect.disabled = false;

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Loading
    |--------------------------------------------------------------------------
    */

    sectionSelect.innerHTML =
        '<option value="">Loading sections...</option>';

    sectionSelect.disabled = true;

    sectionSelect.required = false;


    fetch(
        'get_sections.php?class_id=' +
        encodeURIComponent(classId)
    )

    .then(response => {

        if (!response.ok) {

            throw new Error(
                'Server error while loading sections.'
            );
        }

        return response.json();

    })

    .then(data => {

        sectionSelect.innerHTML = '';

        sectionSelect.disabled = false;


        /*
        |--------------------------------------------------------------------------
        | No Sections
        |--------------------------------------------------------------------------
        */

        if (
            !data.success ||
            !Array.isArray(data.sections) ||
            data.sections.length === 0
        ) {

            const option =
                document.createElement('option');

            option.value = '';

            option.textContent =
                'No sections available — Not Required';

            sectionSelect.appendChild(option);

            sectionSelect.required = false;

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Sections Available
        |--------------------------------------------------------------------------
        */

        const defaultOption =
            document.createElement('option');

        defaultOption.value = '';

        defaultOption.textContent =
            'Select Section';

        sectionSelect.appendChild(
            defaultOption
        );


        data.sections.forEach(section => {

            const option =
                document.createElement('option');

            option.value =
                section.id;

            option.textContent =
                section.name;


            if (
                selectedSectionId &&
                String(section.id) ===
                String(selectedSectionId)
            ) {

                option.selected = true;
            }


            sectionSelect.appendChild(
                option
            );

        });


        /*
        |--------------------------------------------------------------------------
        | Section Required
        |--------------------------------------------------------------------------
        */

        sectionSelect.required = true;

    })

    .catch(error => {

        console.error(error);

        sectionSelect.innerHTML =
            '<option value="">No sections available — Not Required</option>';

        sectionSelect.disabled = false;

        /*
        |--------------------------------------------------------------------------
        | Do not block approval if sections cannot be loaded.
        |--------------------------------------------------------------------------
        */

        sectionSelect.required = false;

    });

}


/*
|--------------------------------------------------------------------------
| Class Changed
|--------------------------------------------------------------------------
*/

if (classSelect) {

    classSelect.addEventListener(
        'change',
        function () {

            loadSections(
                this.value
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Load Existing Sections
    |--------------------------------------------------------------------------
    */

    if (classSelect.value) {

        loadSections(
            classSelect.value,
            <?= (int) ($student['section_id'] ?? 0) ?>
        );

    }

}


/*
|--------------------------------------------------------------------------
| Open Approval Modal
|--------------------------------------------------------------------------
*/

function openApprovalModal() {

    const classId =
        classSelect
            ? classSelect.value
            : '';

    const sectionId =
        sectionSelect
            ? sectionSelect.value
            : '';


    /*
    |--------------------------------------------------------------------------
    | Class Validation
    |--------------------------------------------------------------------------
    */

    if (!classId) {

        showToast(
            'Please select a class before approving the student.',
            'error'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Section Validation
    |--------------------------------------------------------------------------
    |
    | Section is required ONLY if sections are available.
    |
    */

    if (
        sectionSelect &&
        sectionSelect.required &&
        !sectionId
    ) {

        showToast(
            'Please select a section before approving the student.',
            'error'
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Selected Class Name
    |--------------------------------------------------------------------------
    */

    const selectedClass =
        classSelect.options[
            classSelect.selectedIndex
        ];

    if (selectedClass) {

        modalClassName.textContent =
            selectedClass.textContent.trim();

    }


    /*
    |--------------------------------------------------------------------------
    | Selected Section Name
    |--------------------------------------------------------------------------
    */

    if (
        sectionSelect &&
        sectionSelect.value
    ) {

        const selectedSection =
            sectionSelect.options[
                sectionSelect.selectedIndex
            ];

        if (selectedSection) {

            modalSectionName.textContent =
                selectedSection.textContent.trim();

        }

    } else {

        modalSectionName.textContent =
            'Not Assigned';

    }


    /*
    |--------------------------------------------------------------------------
    | Show Modal
    |--------------------------------------------------------------------------
    */

    approvalModal.classList.add('show');

    approvalModal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.classList.add(
        'modal-open'
    );


    return false;
}


/*
|--------------------------------------------------------------------------
| Close Approval Modal
|--------------------------------------------------------------------------
*/

function closeApprovalModal() {

    if (!approvalModal) {
        return;
    }

    approvalModal.classList.remove(
        'show'
    );

    approvalModal.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.classList.remove(
        'modal-open'
    );

}


/*
|--------------------------------------------------------------------------
| Submit Approval
|--------------------------------------------------------------------------
*/

function submitApproval() {

    if (!approvalForm) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Remove onsubmit handler
    |--------------------------------------------------------------------------
    |
    | Otherwise form.submit() could trigger the modal again.
    |
    */

    approvalForm.removeAttribute(
        'onsubmit'
    );


    /*
    |--------------------------------------------------------------------------
    | Close Modal
    |--------------------------------------------------------------------------
    */

    closeApprovalModal();


    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    approvalForm.submit();

}


/*
|--------------------------------------------------------------------------
| Close Modal When Clicking Outside
|--------------------------------------------------------------------------
*/

if (approvalModal) {

    approvalModal.addEventListener(
        'click',
        function (event) {

            if (
                event.target ===
                approvalModal
            ) {

                closeApprovalModal();

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| ESC Key
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (event) {

        if (
            event.key === 'Escape' &&
            approvalModal &&
            approvalModal.classList.contains('show')
        ) {

            closeApprovalModal();

        }

    }
);


/*
|--------------------------------------------------------------------------
| Toast
|--------------------------------------------------------------------------
*/

function closeToast() {

    const toast =
        document.getElementById('pageToast');

    if (!toast) {
        return;
    }

    toast.classList.add('hide');

    setTimeout(
        function () {

            const container =
                toast.parentElement;

            if (container) {
                container.remove();
            }

        },
        300
    );

}


/*
|--------------------------------------------------------------------------
| Auto Hide Toast
|--------------------------------------------------------------------------
*/

const pageToast =
    document.getElementById('pageToast');

if (pageToast) {

    setTimeout(
        closeToast,
        5000
    );

}


/*
|--------------------------------------------------------------------------
| Show Toast
|--------------------------------------------------------------------------
*/

function showToast(
    message,
    type = 'success'
) {

    /*
    |--------------------------------------------------------------------------
    | Remove Existing Toast
    |--------------------------------------------------------------------------
    */

    const existing =
        document.querySelector(
            '.toast-container'
        );

    if (existing) {
        existing.remove();
    }


    /*
    |--------------------------------------------------------------------------
    | Container
    |--------------------------------------------------------------------------
    */

    const container =
        document.createElement('div');

    container.className =
        'toast-container';


    /*
    |--------------------------------------------------------------------------
    | Toast
    |--------------------------------------------------------------------------
    */

    const toast =
        document.createElement('div');

    toast.className =
        'toast ' +
        (type === 'success'
            ? 'success'
            : 'error');


    /*
    |--------------------------------------------------------------------------
    | Icon
    |--------------------------------------------------------------------------
    */

    const icon =
        document.createElement('div');

    icon.className =
        'toast-icon';

    icon.textContent =
        type === 'success'
            ? '✓'
            : '!';


    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    */

    const content =
        document.createElement('div');

    content.className =
        'toast-content';


    const title =
        document.createElement('div');

    title.className =
        'toast-title';

    title.textContent =
        type === 'success'
            ? 'Success'
            : 'Action Failed';


    const text =
        document.createElement('div');

    text.className =
        'toast-message';

    text.textContent =
        message;


    content.appendChild(title);

    content.appendChild(text);


    /*
    |--------------------------------------------------------------------------
    | Close Button
    |--------------------------------------------------------------------------
    */

    const close =
        document.createElement('button');

    close.type =
        'button';

    close.className =
        'toast-close';

    close.textContent =
        '×';

    close.setAttribute(
        'aria-label',
        'Close'
    );

    close.onclick =
        function () {

            container.remove();

        };


    /*
    |--------------------------------------------------------------------------
    | Assemble
    |--------------------------------------------------------------------------
    */

    toast.appendChild(icon);

    toast.appendChild(content);

    toast.appendChild(close);

    container.appendChild(toast);

    document.body.appendChild(container);


    /*
    |--------------------------------------------------------------------------
    | Auto Remove
    |--------------------------------------------------------------------------
    */

    setTimeout(
        function () {

            if (container) {
                container.remove();
            }

        },
        5000
    );

}

</script>

</body>

</html>