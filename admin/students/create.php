<?php


require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';

// =====================================================
// DATABASE CONNECTION
// =====================================================

$pdo = db();
$database = null;



// =====================================================
// STUDENT OBJECT
// =====================================================

$student = new Student($pdo);


// =====================================================
// VARIABLES
// =====================================================

$errors = [];
$success = '';


// =====================================================
// FORM SUBMISSION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    // =================================================
    // GET FORM DATA
    // =================================================

    $studentId = trim($_POST['student_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $fatherName = trim($_POST['father_name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $classId = $_POST['class_id'] ?? '';
    $sectionId = $_POST['section_id'] ?? '';
    $admissionDate = trim($_POST['admission_date'] ?? '');
    $status = $_POST['status'] ?? 'pending';
    $accountPassword = (string) ($_POST['account_password'] ?? '');


    // =================================================
    // VALIDATION
    // =================================================

    // Student ID
    if ($studentId === '') {

        $errors[] = 'Student ID is required.';

    } elseif (strlen($studentId) > 50) {

        $errors[] = 'Student ID cannot be longer than 50 characters.';
    }


    // Student Name
    if ($name === '') {

        $errors[] = 'Student name is required.';

    } elseif (strlen($name) < 2) {

        $errors[] = 'Student name must contain at least 2 characters.';

    } elseif (strlen($name) > 100) {

        $errors[] = 'Student name cannot be longer than 100 characters.';
    }


    // Father Name
    if ($fatherName === '') {

        $errors[] = 'Father name is required.';

    } elseif (strlen($fatherName) > 100) {

        $errors[] = 'Father name cannot be longer than 100 characters.';
    }


    // Date of Birth
    if ($dateOfBirth !== '') {

        $date = DateTime::createFromFormat('Y-m-d', $dateOfBirth);

        if (!$date || $date->format('Y-m-d') !== $dateOfBirth) {

            $errors[] = 'Please enter a valid date of birth.';
        }
    }


    // Gender
    $allowedGenders = [
        'male',
        'female',
        'other'
    ];

    if (
        $gender !== '' &&
        !in_array($gender, $allowedGenders, true)
    ) {

        $errors[] = 'Invalid gender selected.';
    }


    // Phone
    if ($phone !== '' && strlen($phone) > 30) {

        $errors[] = 'Phone number cannot be longer than 30 characters.';
    }


    // Email
    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $errors[] = 'Please enter a valid email address.';
    }

    if ($email !== '' && strlen($email) > 150) {

        $errors[] = 'Email cannot be longer than 150 characters.';
    }


    // =================================================
    // LOGIN ACCOUNT (OPTIONAL)
    // =================================================
    // When a password is given, addStudent() creates the
    // users account in the same transaction.
    // =================================================

    if ($accountPassword !== '') {

        if (strlen($accountPassword) < 8) {

            $errors[] =
                'Login account password must be at least 8 characters long.';

        } elseif (strlen($accountPassword) > 72) {

            $errors[] = 'Login account password must not exceed 72 characters.';
        }

        if ($email === '') {

            $errors[] =
                'A login account requires an email address for the student.';

        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {

            if ($student->emailTakenByAnotherUser($email)) {

                $errors[] =
                    'This email is already used by another login account.';
            }
        }
    }


    // Status
    $allowedStatuses = [
        'pending',
        'active',
        'inactive',
        'graduated',
        'left'
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        $errors[] = 'Invalid status selected.';
    }


    // Admission Date
    if ($admissionDate !== '') {

        $date = DateTime::createFromFormat('Y-m-d', $admissionDate);

        if (!$date || $date->format('Y-m-d') !== $admissionDate) {

            $errors[] = 'Please enter a valid admission date.';
        }
    }


    // =================================================
    // CHECK DUPLICATE STUDENT ID
    // =================================================

    if (empty($errors)) {

        if ($student->studentIdExists($studentId)) {

            $errors[] = 'This Student ID already exists.';
        }
    }


    // =================================================
    // INSERT STUDENT
    // =================================================

    if (empty($errors)) {

        $data = [

            'student_id' => $studentId,

            'name' => $name,

            'father_name' => $fatherName,

            'date_of_birth' =>
                $dateOfBirth !== ''
                    ? $dateOfBirth
                    : null,

            'gender' =>
                $gender !== ''
                    ? $gender
                    : null,

            'phone' =>
                $phone !== ''
                    ? $phone
                    : null,

            'email' =>
                $email !== ''
                    ? $email
                    : null,

            'address' =>
                $address !== ''
                    ? $address
                    : null,

            'class_id' =>
                $classId !== ''
                    ? (int) $classId
                    : null,

            'section_id' =>
                $sectionId !== ''
                    ? (int) $sectionId
                    : null,

            'admission_date' =>
                $admissionDate !== ''
                    ? $admissionDate
                    : null,

            'status' => $status,

            // Optional login account (see addStudent()).
            'password' => $accountPassword !== ''
                ? $accountPassword
                : null
        ];


        try {

            if ($student->addStudent($data)) {

                $success = $accountPassword !== ''
                    ? 'Student added successfully. The login account was created.'
                    : 'Student added successfully.';

                $_POST = [];
            }

        } catch (PDOException $e) {

            if ($e->getCode() === '23000') {

                $errors[] =
                    'A record with this email address or Student ID already exists.';

            } else {

                $errors[] =
                    'Something went wrong while adding the student.';
            }

        } catch (RuntimeException $e) {

            $errors[] = $e->getMessage();
        }
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

    <title>Add Student | Student Management</title>


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


    <!-- =================================================
         CSS
    ================================================== -->

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


        /* =================================================
           LAYOUT
        ================================================= */

        .page-wrapper {
            min-height: 100vh;
            padding: 35px;
        }


        .container {
            max-width: 1200px;
            margin: 0 auto;
        }


        /* =================================================
           PAGE HEADER
        ================================================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }


        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
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
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.20);
        }


        .page-title h1 {
            font-size: 26px;
            font-weight: 800;
            color: #172033;
        }


        .page-title p {
            margin-top: 5px;
            font-size: 14px;
            color: #7b8498;
        }


        /* =================================================
           BACK BUTTON
        ================================================== */

        .back-btn {
            text-decoration: none;
            background: white;
            color: #4b5563;

            padding: 11px 17px;
            border-radius: 10px;

            border: 1px solid #e5e7eb;

            font-size: 14px;
            font-weight: 600;

            transition: 0.2s ease;
        }


        .back-btn:hover {
            background: #f8f9fc;
            border-color: #d8dce5;
            transform: translateY(-1px);
        }


        /* =================================================
           ALERTS
        ================================================== */

        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 20px;

            font-size: 14px;
            font-weight: 500;
        }


        .alert-success {
            background: #ecfdf3;
            color: #087443;
            border: 1px solid #b7efd0;
        }


        .alert-error {
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }


        .alert-error p {
            margin: 4px 0;
        }


        /* =================================================
           FORM CARD
        ================================================== */

        .form-card {
            background: white;
            border: 1px solid #e7eaf0;
            border-radius: 18px;

            box-shadow: 0 10px 35px rgba(20, 30, 55, 0.06);

            overflow: hidden;
        }


        /* =================================================
           CARD HEADER
        ================================================== */

        .card-header {
            padding: 22px 28px;
            border-bottom: 1px solid #edf0f4;

            display: flex;
            align-items: center;
            gap: 12px;
        }


        .card-header-icon {
            width: 38px;
            height: 38px;

            background: #eef2ff;
            color: #4f46e5;

            border-radius: 10px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 18px;
        }


        .card-header h2 {
            font-size: 17px;
            font-weight: 700;
        }


        .card-header p {
            color: #8a93a5;
            font-size: 12px;
            margin-top: 3px;
        }


        /* =================================================
           FORM BODY
        ================================================== */

        .form-body {
            padding: 30px;
        }


        .form-section {
            margin-bottom: 32px;
        }


        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #252d3d;

            margin-bottom: 18px;

            display: flex;
            align-items: center;
            gap: 8px;
        }


        .section-line {
            height: 1px;
            background: #edf0f4;
            flex: 1;
        }


        /* =================================================
           GRID
        ================================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }


        .form-group {
            display: flex;
            flex-direction: column;
        }


        .full-width {
            grid-column: 1 / -1;
        }


        /* =================================================
           LABEL
        ================================================== */

        label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }


        .required {
            color: #ef4444;
        }


        .optional {
            color: #9ca3af;
            font-size: 11px;
            font-weight: 400;
        }


        /* =================================================
           INPUTS
        ================================================== */

        input,
        select,
        textarea {

            width: 100%;

            border: 1px solid #dfe3ea;
            border-radius: 10px;

            padding: 12px 14px;

            background: #fff;

            color: #1f2937;

            font-family: inherit;
            font-size: 14px;

            outline: none;

            transition: all 0.2s ease;
        }


        input::placeholder,
        textarea::placeholder {
            color: #a7afbd;
        }


        input:hover,
        select:hover,
        textarea:hover {
            border-color: #c5cad5;
        }


        input:focus,
        select:focus,
        textarea:focus {

            border-color: #6366f1;

            box-shadow:
                0 0 0 3px rgba(99, 102, 241, 0.10);
        }


        textarea {
            resize: vertical;
            min-height: 105px;
        }


        /* =================================================
           INPUT WITH ICON
        ================================================== */

        .input-wrapper {
            position: relative;
        }


        .input-icon {
            position: absolute;

            left: 13px;
            top: 50%;

            transform: translateY(-50%);

            color: #9ca3af;

            font-size: 15px;

            pointer-events: none;
        }


        .input-wrapper input {
            padding-left: 40px;
        }


        /* =================================================
           HELP TEXT
        ================================================== */

        .help-text {
            margin-top: 6px;
            font-size: 11px;
            color: #9ca3af;
        }


        /* =================================================
           FOOTER
        ================================================== */

        .form-footer {

            padding: 20px 30px;

            background: #fafbfc;

            border-top: 1px solid #edf0f4;

            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }


        .btn {
            border: none;
            border-radius: 10px;

            padding: 12px 22px;

            font-family: inherit;
            font-size: 14px;
            font-weight: 600;

            cursor: pointer;

            transition: all 0.2s ease;
        }


        .btn-cancel {
            background: white;
            color: #4b5563;
            border: 1px solid #dfe3ea;
            text-decoration: none;
        }


        .btn-cancel:hover {
            background: #f7f8fa;
        }


        .btn-primary {

            background: #4f46e5;
            color: white;

            box-shadow:
                0 6px 15px rgba(79, 70, 229, 0.20);
        }


        .btn-primary:hover {

            background: #4338ca;

            transform: translateY(-1px);

            box-shadow:
                0 8px 20px rgba(79, 70, 229, 0.28);
        }


        /* =================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 768px) {

            .page-wrapper {
                padding: 20px;
            }


            .page-header {
                align-items: flex-start;
                gap: 15px;
            }


            .header-left {
                align-items: flex-start;
            }


            .page-title h1 {
                font-size: 21px;
            }


            .header-icon {
                width: 45px;
                height: 45px;
                font-size: 20px;
            }


            .form-grid {
                grid-template-columns: 1fr;
            }


            .full-width {
                grid-column: auto;
            }


            .form-body {
                padding: 22px;
            }


            .form-footer {
                padding: 18px 22px;
            }
        }


        @media (max-width: 500px) {

            .page-wrapper {
                padding: 12px;
            }


            .page-header {
                flex-direction: column;
            }


            .back-btn {
                width: 100%;
                text-align: center;
            }


            .form-body {
                padding: 18px;
            }


            .form-footer {
                flex-direction: column;
            }


            .btn {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>


<body>


<div class="page-wrapper">

    <div class="container">


        <!-- =================================================
             PAGE HEADER
        ================================================== -->

        <div class="page-header">

            <div class="header-left">

                <div class="header-icon">
                    ðŸ‘¨â€ðŸŽ“
                </div>

                <div class="page-title">

                    <h1>Add Student</h1>

                    <p>
                        Create a new student profile
                    </p>

                </div>

            </div>


            <a
                href="index.php"
                class="back-btn"
            >
                â† Back to Students
            </a>

        </div>


        <!-- =================================================
             SUCCESS MESSAGE
        ================================================== -->

        <?php if ($success !== ''): ?>

            <div class="alert alert-success">

                âœ“ <?= htmlspecialchars($success) ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGES
        ================================================== -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-error">

                <strong>
                    Please fix the following errors:
                </strong>

                <?php foreach ($errors as $error): ?>

                    <p>
                        â€¢ <?= htmlspecialchars($error) ?>
                    </p>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FORM CARD
        ================================================== -->

        <div class="form-card">


            <!-- CARD HEADER -->

            <div class="card-header">

                <div class="card-header-icon">
                    âœŽ
                </div>

                <div>

                    <h2>
                        Student Information
                    </h2>

                    <p>
                        Enter the student's basic information below
                    </p>

                </div>

            </div>


            <!-- FORM -->

            <form method="POST">
                <?= csrf_field() ?>


                <div class="form-body">


                    <!-- =================================================
                         PERSONAL INFORMATION
                    ================================================== -->

                    <div class="form-section">

                        <div class="section-title">

                            Personal Information

                            <div class="section-line"></div>

                        </div>


                        <div class="form-grid">


                            <!-- Student ID -->

                            <div class="form-group">

                                <label for="student_id">

                                    Student ID

                                    <span class="required">*</span>

                                </label>

                                <div class="input-wrapper">

                                    <span class="input-icon">
                                        #
                                    </span>

                                    <input
                                        type="text"
                                        id="student_id"
                                        name="student_id"
                                        maxlength="50"
                                        placeholder="e.g. STU001"
                                        value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>"
                                        required
                                    >

                                </div>

                                <span class="help-text">
                                    Must be unique
                                </span>

                            </div>


                            <!-- Student Name -->

                            <div class="form-group">

                                <label for="name">

                                    Student Name

                                    <span class="required">*</span>

                                </label>

                                <div class="input-wrapper">

                                    <span class="input-icon">
                                        ðŸ‘¤
                                    </span>

                                    <input
                                        type="text"
                                        id="name"
                                        name="name"
                                        maxlength="100"
                                        placeholder="Enter full name"
                                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- Father Name -->

                            <div class="form-group">

                                <label for="father_name">

                                    Father Name

                                    <span class="required">*</span>

                                </label>

                                <div class="input-wrapper">

                                    <span class="input-icon">
                                        ðŸ‘¨
                                    </span>

                                    <input
                                        type="text"
                                        id="father_name"
                                        name="father_name"
                                        maxlength="100"
                                        placeholder="Enter father's name"
                                        value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- Date of Birth -->

                            <div class="form-group">

                                <label for="date_of_birth">

                                    Date of Birth

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <input
                                    type="date"
                                    id="date_of_birth"
                                    name="date_of_birth"
                                    value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"
                                >

                            </div>


                            <!-- Gender -->

                            <div class="form-group">

                                <label for="gender">

                                    Gender

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <select
                                    id="gender"
                                    name="gender"
                                >

                                    <option value="">
                                        Select Gender
                                    </option>

                                    <option
                                        value="male"
                                        <?= (($_POST['gender'] ?? '') === 'male')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Male
                                    </option>

                                    <option
                                        value="female"
                                        <?= (($_POST['gender'] ?? '') === 'female')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Female
                                    </option>

                                    <option
                                        value="other"
                                        <?= (($_POST['gender'] ?? '') === 'other')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Other
                                    </option>

                                </select>

                            </div>


                            <!-- Phone -->

                            <div class="form-group">

                                <label for="phone">

                                    Phone

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <div class="input-wrapper">

                                    <span class="input-icon">
                                        â˜Ž
                                    </span>

                                    <input
                                        type="text"
                                        id="phone"
                                        name="phone"
                                        maxlength="30"
                                        placeholder="03XXXXXXXXX"
                                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                    >

                                </div>

                            </div>


                            <!-- Email -->

                            <div class="form-group">

                                <label for="email">

                                    Email

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <div class="input-wrapper">

                                    <span class="input-icon">
                                        @
                                    </span>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        maxlength="150"
                                        placeholder="student@example.com"
                                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    >

                                </div>

                            </div>


                            <!-- LOGIN ACCOUNT PASSWORD (OPTIONAL) -->

                            <div class="form-group full-width">

                                <label for="account_password">

                                    Login Password

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <input
                                    type="password"
                                    id="account_password"
                                    name="account_password"
                                    minlength="8"
                                    maxlength="72"
                                    placeholder="Minimum 8 characters - creates the student's login account (requires the email above)"
                                    autocomplete="new-password"
                                >

                            </div>


                            <!-- Address -->

                            <div class="form-group full-width">

                                <label for="address">

                                    Address

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <textarea
                                    id="address"
                                    name="address"
                                    placeholder="Enter complete address"
                                ><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         ACADEMIC INFORMATION
                    ================================================== -->

                    <div class="form-section">

                        <div class="section-title">

                            Academic Information

                            <div class="section-line"></div>

                        </div>


                        <div class="form-grid">


                            <!-- Class ID -->

                            <div class="form-group">

                                <label for="class_id">

                                    Class ID

                                    <span class="optional">
                                        (Optional for now)
                                    </span>

                                </label>

                                <input
                                    type="number"
                                    id="class_id"
                                    name="class_id"
                                    min="1"
                                    placeholder="e.g. 1"
                                    value="<?= htmlspecialchars($_POST['class_id'] ?? '') ?>"
                                >

                                <span class="help-text">
                                    Will be replaced with class selection later
                                </span>

                            </div>


                            <!-- Section ID -->

                            <div class="form-group">

                                <label for="section_id">

                                    Section ID

                                    <span class="optional">
                                        (Optional for now)
                                    </span>

                                </label>

                                <input
                                    type="number"
                                    id="section_id"
                                    name="section_id"
                                    min="1"
                                    placeholder="e.g. 1"
                                    value="<?= htmlspecialchars($_POST['section_id'] ?? '') ?>"
                                >

                                <span class="help-text">
                                    Will be replaced with section selection later
                                </span>

                            </div>


                            <!-- Admission Date -->

                            <div class="form-group">

                                <label for="admission_date">

                                    Admission Date

                                    <span class="optional">
                                        (Optional)
                                    </span>

                                </label>

                                <input
                                    type="date"
                                    id="admission_date"
                                    name="admission_date"
                                    value="<?= htmlspecialchars($_POST['admission_date'] ?? '') ?>"
                                >

                            </div>


                            <!-- Status -->

                            <div class="form-group">

                                <label for="status">

                                    Status

                                    <span class="required">*</span>

                                </label>

                                <select
                                    id="status"
                                    name="status"
                                >

                                    <option
                                        value="pending"
                                        <?= (($_POST['status'] ?? 'pending') === 'pending')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Pending approval
                                    </option>

                                    <option
                                        value="active"
                                        <?= (($_POST['status'] ?? '') === 'active')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Active
                                    </option>

                                    <option
                                        value="inactive"
                                        <?= (($_POST['status'] ?? '') === 'inactive')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Inactive
                                    </option>

                                    <option
                                        value="graduated"
                                        <?= (($_POST['status'] ?? '') === 'graduated')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Graduated
                                    </option>

                                    <option
                                        value="left"
                                        <?= (($_POST['status'] ?? '') === 'left')
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        Left
                                    </option>

                                </select>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     FORM FOOTER
                ================================================== -->

                <div class="form-footer">

                    <a
                        href="index.php"
                        class="btn btn-cancel"
                    >
                        Cancel
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        + Add Student
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


</body>

</html>

