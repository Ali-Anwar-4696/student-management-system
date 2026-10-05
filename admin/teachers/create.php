<?php

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';

$pdo = db();
$database = null;

$teacherObj = new Teacher($pdo);


// =====================================================
// VARIABLES
// =====================================================

$errors = [];

$success = '';

$teacherId = '';
$name = '';
$phone = '';
$email = '';
$address = '';
$joiningDate = '';
$status = 'active';
$password = '';


// =====================================================
// FORM SUBMISSION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    // Get form data
    $teacherId = trim($_POST['teacher_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $joiningDate = trim($_POST['joining_date'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = (string) ($_POST['password'] ?? '');


    // =================================================
    // VALIDATION
    // =================================================

    // Teacher ID
    if ($teacherId === '') {

        $errors[] = 'Teacher ID is required.';

    } elseif (strlen($teacherId) > 50) {

        $errors[] = 'Teacher ID must not exceed 50 characters.';
    }


    // Teacher Name
    if ($name === '') {

        $errors[] = 'Teacher name is required.';

    } elseif (strlen($name) > 100) {

        $errors[] = 'Teacher name must not exceed 100 characters.';
    }


    // =================================================
    // EMAIL / GMAIL
    // =================================================
    // The centralized login (auth/login.php) authenticates
    // teachers with email + password.
    // Therefore email is now REQUIRED.
    // =================================================

    if ($email === '') {

        $errors[] = 'Teacher Gmail is required.';

    } elseif (strlen($email) > 150) {

        $errors[] = 'Email must not exceed 150 characters.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errors[] = 'Please enter a valid email address.';
    }


    // =================================================
    // INITIAL PASSWORD
    // =================================================
    // The teacher needs a real password to log in.
    // It is stored only as a password_hash() value.
    // =================================================

    if ($password === '') {

        $errors[] = 'Initial password is required.';

    } elseif (strlen($password) < 8) {

        $errors[] = 'Password must be at least 8 characters long.';

    } elseif (strlen($password) > 72) {

        $errors[] = 'Password must not exceed 72 characters.';
    }


    // =================================================
    // STATUS
    // =================================================

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        $errors[] = 'Invalid status selected.';
    }


    // =================================================
    // CHECK DUPLICATE TEACHER ID
    // =================================================

    if (
        empty($errors) &&
        $teacherObj->teacherIdExists($teacherId)
    ) {

        $errors[] =
            'Teacher ID already exists. Please use a different Teacher ID.';
    }


    // =================================================
    // CREATE TEACHER + LOGIN ACCOUNT
    // =================================================

    if (empty($errors)) {

        try {

            $data = [

                'teacher_id' => $teacherId,

                'name' => $name,

                'phone' =>
                    $phone !== ''
                        ? $phone
                        : null,

                'email' => $email,

                'address' =>
                    $address !== ''
                        ? $address
                        : null,

                'joining_date' =>
                    $joiningDate !== ''
                        ? $joiningDate
                        : null,

                'status' => $status,

                'password' => $password
            ];


            // =================================================
            // IMPORTANT
            // =================================================
            //
            // This method creates BOTH:
            //
            // users record
            // +
            // teachers record
            //
            // and automatically links:
            //
            // teachers.user_id = users.id
            //
            // =================================================

            $teacherObj->createTeacherWithAccount($data);


            $success =
                'Teacher account created successfully. '
                . 'The teacher can now sign in at auth/login.php '
                . 'with their email and the password you set.';


            // =================================================
            // CLEAR FORM AFTER SUCCESS
            // =================================================

            $teacherId = '';
            $name = '';
            $phone = '';
            $email = '';
            $address = '';
            $joiningDate = '';
            $status = 'active';
            $password = '';


        } catch (Throwable $e) {

            // =================================================
            // SHOW ACTUAL SAFE ERROR
            // =================================================

            $errors[] = $e->getMessage();
        }
    }
}


// =====================================================
// HELPER
// =====================================================

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars(
            $value ?? '',
            ENT_QUOTES,
            'UTF-8'
        );
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

    <title>Add Teacher</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f9;

            color: #1f2937;
        }


        .container {
            width: 95%;

            max-width: 1000px;

            margin: 40px auto;
        }


        /* =========================================
           HEADER
        ========================================= */

        .header {
            background: #ffffff;

            padding: 25px;

            border-radius: 14px;

            margin-bottom: 20px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.06);
        }


        .header h1 {
            margin: 0 0 6px;

            font-size: 27px;
        }


        .header p {
            margin: 0;

            color: #6b7280;
        }


        .btn {
            display: inline-block;

            padding: 10px 16px;

            border-radius: 8px;

            text-decoration: none;

            border: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;
        }


        .btn-back {
            background: #e5e7eb;

            color: #111827;
        }


        .btn-save {
            background: #2563eb;

            color: #ffffff;
        }


        .btn-save:hover {
            background: #1d4ed8;
        }


        /* =========================================
           ALERTS
        ========================================= */

        .alert {
            padding: 14px 16px;

            border-radius: 9px;

            margin-bottom: 20px;

            font-size: 14px;

            line-height: 1.5;
        }


        .alert-success {
            background: #dcfce7;

            border: 1px solid #bbf7d0;

            color: #166534;
        }


        .alert-error {
            background: #fee2e2;

            border: 1px solid #fecaca;

            color: #991b1b;
        }


        .alert ul {
            margin: 0;

            padding-left: 20px;
        }


        /* =========================================
           CARD
        ========================================= */

        .card {
            background: #ffffff;

            border-radius: 14px;

            padding: 28px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 18px
                rgba(0, 0, 0, 0.06);
        }


        .card h2 {
            margin: 0 0 22px;

            font-size: 19px;

            padding-bottom: 13px;

            border-bottom: 1px solid #e5e7eb;
        }


        /* =========================================
           FORM
        ========================================= */

        .form-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }


        .form-group {
            display: flex;

            flex-direction: column;
        }


        .form-group.full {
            grid-column: 1 / -1;
        }


        label {
            margin-bottom: 7px;

            font-size: 14px;

            font-weight: 600;
        }


        .required {
            color: #dc2626;
        }


        input,
        textarea,
        select {
            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            background: #ffffff;

            color: #111827;

            font-size: 14px;

            outline: none;
        }


        input:focus,
        textarea:focus,
        select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.12);
        }


        textarea {
            min-height: 110px;

            resize: vertical;
        }


        .help {
            margin-top: 6px;

            font-size: 12px;

            color: #6b7280;
        }


        /* =========================================
           LOGIN INFO
        ========================================= */

        .login-info {
            margin-top: 20px;

            padding: 15px 16px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            border-radius: 9px;

            color: #1e40af;

            font-size: 13px;

            line-height: 1.6;
        }


        .login-info strong {
            color: #1e3a8a;
        }


        /* =========================================
           ACTIONS
        ========================================= */

        .actions {
            display: flex;

            justify-content: flex-end;

            gap: 10px;
        }


        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 700px) {

            .container {
                width: 92%;

                margin: 20px auto;
            }


            .header {
                flex-direction: column;

                align-items: flex-start;
            }


            .form-grid {
                grid-template-columns: 1fr;
            }


            .form-group.full {
                grid-column: auto;
            }


            .actions {
                flex-direction: column;
            }


            .actions .btn {
                width: 100%;

                text-align: center;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- =============================================
         HEADER
    ============================================== -->

    <div class="header">

        <div>

            <h1>
                Add Teacher
            </h1>

            <p>
                Create a new teacher profile and login account
            </p>

        </div>


        <a
            href="index.php"
            class="btn btn-back"
        >
            ← Teachers
        </a>

    </div>


    <!-- =============================================
         SUCCESS MESSAGE
    ============================================== -->

    <?php if ($success): ?>

        <div class="alert alert-success">

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <!-- =============================================
         ERROR MESSAGES
    ============================================== -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-error">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =============================================
         TEACHER INFORMATION
    ============================================== -->

    <form method="POST">
        <?= csrf_field() ?>


        <div class="card">

            <h2>
                Teacher Information
            </h2>


            <div class="form-grid">


                <!-- Teacher ID -->

                <div class="form-group">

                    <label>
                        Teacher ID
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="teacher_id"
                        value="<?= e($teacherId) ?>"
                        maxlength="50"
                        placeholder="e.g. TCH-001"
                        required
                    >

                    <span class="help">
                        Unique identification number
                    </span>

                </div>


                <!-- Name -->

                <div class="form-group">

                    <label>
                        Teacher Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= e($name) ?>"
                        maxlength="100"
                        placeholder="Enter teacher name"
                        required
                    >

                </div>


                <!-- Phone -->

                <div class="form-group">

                    <label>
                        Phone
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="<?= e($phone) ?>"
                        maxlength="30"
                        placeholder="03XX-XXXXXXX"
                    >

                </div>


                <!-- Email -->

                <div class="form-group">

                    <label>
                        Teacher Gmail
                        <span class="required">*</span>
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= e($email) ?>"
                        maxlength="150"
                        placeholder="teacher@gmail.com"
                        required
                    >

                    <span class="help">
                        This Gmail will be used for teacher login.
                    </span>

                </div>


                <!-- Password -->

                <div class="form-group">

                    <label>
                        Initial Password
                        <span class="required">*</span>
                    </label>

                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        maxlength="72"
                        placeholder="At least 8 characters"
                        required
                        autocomplete="new-password"
                    >

                    <span class="help">
                        Stored securely as a hash. Share it with the
                        teacher through a safe channel; they can
                        change it after logging in.
                    </span>

                </div>


                <!-- Address -->

                <div class="form-group full">

                    <label>
                        Address
                    </label>

                    <textarea
                        name="address"
                        placeholder="Enter teacher address"
                    ><?= e($address) ?></textarea>

                </div>


                <!-- Joining Date -->

                <div class="form-group">

                    <label>
                        Joining Date
                    </label>

                    <input
                        type="date"
                        name="joining_date"
                        value="<?= e($joiningDate) ?>"
                    >

                </div>


                <!-- Status -->

                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <option
                            value="active"
                            <?= $status === 'active' ? 'selected' : '' ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $status === 'inactive' ? 'selected' : '' ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


            </div>


            <!-- =========================================
                 LOGIN INFORMATION
            ========================================== -->

            <div class="login-info">

                <strong>Teacher Login:</strong>

                After creating this teacher, the teacher will
                sign in on the main login page (auth/login.php)
                using their registered email and the initial
                password you set above. The teacher-specific
                login page accepts teacher accounts only.

                <br>

                <strong>Example:</strong>

                Email: teacher@gmail.com

                &nbsp; | &nbsp;

                Password: (the one you set)

            </div>


        </div>


        <!-- =============================================
             ACTIONS
        ============================================== -->

        <div class="actions">

            <a
                href="index.php"
                class="btn btn-back"
            >
                Cancel
            </a>


            <button
                type="submit"
                class="btn btn-save"
            >
                Create Teacher Account
            </button>

        </div>


    </form>


</div>


</body>

</html>

