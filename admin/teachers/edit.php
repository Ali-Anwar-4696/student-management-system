<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';

// =====================================================
// DATABASE
// =====================================================

$pdo = db();
$database = null;

$teacher = new Teacher($pdo);


// =====================================================
// GET TEACHER ID
// =====================================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('Invalid teacher ID.');
}


// =====================================================
// GET EXISTING TEACHER
// =====================================================

$teacherData = $teacher->getTeacherById($id);

if ($teacherData === null) {
    die('Teacher not found.');
}


// =====================================================
// FORM VARIABLES
// =====================================================

$errors = [];
$success = '';

$teacherId   = $teacherData['teacher_id'];
$name        = $teacherData['name'];
$phone       = $teacherData['phone'] ?? '';
$email       = $teacherData['email'] ?? '';
$address     = $teacherData['address'] ?? '';
$joiningDate = $teacherData['joining_date'] ?? '';
$status      = $teacherData['status'];


// =====================================================
// FORM SUBMISSION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $teacherId   = trim($_POST['teacher_id'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $joiningDate = trim($_POST['joining_date'] ?? '');
    $status      = trim($_POST['status'] ?? 'active');
    $newPassword = (string) ($_POST['new_password'] ?? '');


    // =================================================
    // VALIDATION
    // =================================================

    if ($teacherId === '') {
        $errors[] = 'Teacher ID is required.';
    }

    if ($name === '') {
        $errors[] = 'Teacher name is required.';
    }

    // =================================================
    // EMAIL (REQUIRED: LOGIN IDENTIFIER)
    // =================================================

    if ($email === '') {
        $errors[] = 'Teacher email is required. It is used to log in.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // =================================================
    // OPTIONAL PASSWORD RESET
    // =================================================

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    } elseif (strlen($newPassword) > 72) {
        $errors[] = 'New password must not exceed 72 characters.';
    }

    // =================================================
    // MISSING LOGIN ACCOUNT
    // =================================================
    // Legacy teacher records exist without a users row, so
    // the teacher could never log in. In that case the
    // password is REQUIRED and creates the account.
    // =================================================

    if (empty($teacherData['user_id']) && $newPassword === '') {
        $errors[] =
            'This teacher has no login account yet. '
            . 'Set an initial password (minimum 8 characters) to create one.';
    }

    if (
        $status !== 'active' &&
        $status !== 'inactive'
    ) {
        $errors[] = 'Invalid teacher status.';
    }


    // =================================================
    // DUPLICATE TEACHER ID
    // =================================================

    if (empty($errors)) {

        if ($teacher->teacherIdExists($teacherId, $id)) {
            $errors[] = 'This Teacher ID already exists.';
        }
    }


    // =================================================
    // DUPLICATE LOGIN EMAIL
    // =================================================

    if (empty($errors)) {

        $excludeUserId = (int) ($teacherData['user_id'] ?? 0);

        if ($teacher->emailTakenByAnotherUser(
            $email,
            $excludeUserId > 0 ? $excludeUserId : null
        )) {
            $errors[] = 'This email is already used by another account.';
        }
    }


    // =================================================
    // UPDATE DATABASE
    // =================================================

    if (empty($errors)) {

        try {

            $data = [

                'user_id' => $teacherData['user_id'],

                'teacher_id' => $teacherId,

                'name' => $name,

                'phone' => $phone !== ''
                    ? $phone
                    : null,

                'email' => $email !== ''
                    ? $email
                    : null,

                'address' => $address !== ''
                    ? $address
                    : null,

                'joining_date' => $joiningDate !== ''
                    ? $joiningDate
                    : null,

                'status' => $status,

                'password' => $newPassword !== ''
                    ? $newPassword
                    : null
            ];


            $updated = $teacher->updateTeacher(
                $id,
                $data
            );


            if ($updated) {

                header(
                    'Location: view.php?id=' .
                    $id .
                    '&updated=1'
                );

                exit;

            } else {

                $errors[] = 'No changes were made.';
            }


        } catch (PDOException $e) {

            error_log(
                'Teacher Update Error: ' . $e->getMessage()
            );

            $errors[] =
                'Database error. Please try again.';

        } catch (Throwable $e) {

            error_log(
                'Teacher Update Error: ' . $e->getMessage()
            );

            $errors[] =
                'Something went wrong while updating the teacher.';
        }
    }
}


// =====================================================
// ESCAPE OUTPUT
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

    <title>Edit Teacher</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family: Arial, sans-serif;

            background: #f5f7fb;

            color: #1f2937;

            padding: 30px;
        }


        .container {

            max-width: 900px;

            margin: 0 auto;
        }


        .top-bar {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;

            gap: 15px;
        }


        .top-bar h1 {

            font-size: 28px;

            margin-bottom: 5px;
        }


        .top-bar p {

            color: #6b7280;

            font-size: 14px;
        }


        .btn {

            display: inline-block;

            padding: 10px 16px;

            border-radius: 7px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            border: none;

            cursor: pointer;
        }


        .btn-back {

            background: #e5e7eb;

            color: #111827;
        }


        .btn-primary {

            background: #2563eb;

            color: white;
        }


        .card {

            background: white;

            border-radius: 12px;

            box-shadow:
                0 3px 15px
                rgba(0, 0, 0, 0.08);

            padding: 30px;
        }


        .card-title {

            font-size: 20px;

            margin-bottom: 25px;

            padding-bottom: 12px;

            border-bottom:
                1px solid #e5e7eb;
        }


        .alert {

            padding: 14px 16px;

            border-radius: 7px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;
        }


        .alert-error ul {

            margin-left: 20px;
        }


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

            font-size: 14px;

            font-weight: 600;

            margin-bottom: 7px;
        }


        .required {

            color: #dc2626;
        }


        input,
        textarea,
        select {

            width: 100%;

            padding: 11px 12px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            font-size: 14px;

            outline: none;
        }


        input:focus,
        textarea:focus,
        select:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37, 99, 235, 0.1);
        }


        textarea {

            min-height: 110px;

            resize: vertical;
        }


        .help {

            color: #6b7280;

            font-size: 12px;

            margin-top: 5px;
        }


        .actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;
        }


        @media (max-width: 700px) {

            body {

                padding: 15px;
            }


            .top-bar {

                flex-direction: column;

                align-items: flex-start;
            }


            .card {

                padding: 20px;
            }


            .form-grid {

                grid-template-columns: 1fr;
            }


            .form-group.full {

                grid-column: auto;
            }


            .actions {

                justify-content: flex-start;

                flex-wrap: wrap;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- TOP BAR -->

    <div class="top-bar">

        <div>

            <h1>Edit Teacher</h1>

            <p>
                Update teacher information
            </p>

        </div>


        <a
            href="view.php?id=<?= (int) $id ?>"
            class="btn btn-back"
        >
            ← Teacher Profile
        </a>

    </div>


    <!-- CARD -->

    <div class="card">


        <h2 class="card-title">
            Teacher Information
        </h2>


        <!-- ERRORS -->

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


        <!-- FORM -->

        <form
            method="POST"
            action=""
        >
            <?= csrf_field() ?>

            <div class="form-grid">


                <!-- TEACHER ID -->

                <div class="form-group">

                    <label for="teacher_id">

                        Teacher ID
                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="teacher_id"
                        name="teacher_id"
                        value="<?= e($teacherId) ?>"
                        required
                    >

                    <span class="help">
                        Unique teacher identification number
                    </span>

                </div>


                <!-- NAME -->

                <div class="form-group">

                    <label for="name">

                        Teacher Name
                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?= e($name) ?>"
                        required
                    >

                </div>


                <!-- PHONE -->

                <div class="form-group">

                    <label for="phone">
                        Phone
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        value="<?= e($phone) ?>"
                    >

                </div>


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= e($email) ?>"
                        required
                    >

                    <span class="help">
                        Used for login.
                    </span>

                </div>


                <!-- NEW PASSWORD / INITIAL PASSWORD -->

                <div class="form-group">

                    <label for="new_password">
                        <?= empty($teacherData['user_id'])
                            ? 'Initial Password (creates the login account)'
                            : 'New Password' ?>
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        maxlength="72"
                        <?= empty($teacherData['user_id'])
                            ? 'required'
                            : '' ?>
                        placeholder="<?= empty($teacherData['user_id'])
                            ? 'Minimum 8 characters'
                            : 'Leave blank to keep the current password' ?>"
                        autocomplete="new-password"
                    >

                    <span class="help">
                        <?php if (empty($teacherData['user_id'])): ?>

                            This teacher has no login account yet.
                            This password creates one, so the
                            teacher can sign in.

                        <?php else: ?>

                            Optional. Fill this only to reset this
                            teacher's password (minimum 8 characters).

                        <?php endif; ?>
                    </span>

                </div>


                <!-- JOINING DATE -->

                <div class="form-group">

                    <label for="joining_date">
                        Joining Date
                    </label>

                    <input
                        type="date"
                        id="joining_date"
                        name="joining_date"
                        value="<?= e($joiningDate) ?>"
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="active"
                            <?= $status === 'active'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $status === 'inactive'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <!-- ADDRESS -->

                <div class="form-group full">

                    <label for="address">
                        Address
                    </label>

                    <textarea
                        id="address"
                        name="address"
                    ><?= e($address) ?></textarea>

                </div>


            </div>


            <!-- ACTIONS -->

            <div class="actions">

                <a
                    href="view.php?id=<?= (int) $id ?>"
                    class="btn btn-back"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Teacher
                </button>

            </div>


        </form>


    </div>


</div>


</body>

</html>