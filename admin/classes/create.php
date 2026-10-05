<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/ClassRoom.php';

$pdo = db();
$classRoom = new ClassRoom($pdo);


/*
|--------------------------------------------------------------------------
| Form Variables
|--------------------------------------------------------------------------
*/

$name = '';
$description = '';
$status = 'active';

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $errors[] = 'Class name is required.';

    } elseif (mb_strlen($name) < 2) {

        $errors[] = 'Class name must be at least 2 characters.';

    } elseif (mb_strlen($name) > 100) {

        $errors[] = 'Class name cannot exceed 100 characters.';
    }


    if (mb_strlen($description) > 5000) {

        $errors[] = 'Description is too long.';
    }


    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        $errors[] = 'Invalid status selected.';
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Class Check
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        if ($classRoom->classNameExists($name)) {

            $errors[] = 'A class with this name already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Class
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $classRoom->addClass([
                'name' => $name,
                'description' => $description !== ''
                    ? $description
                    : null,
                'status' => $status
            ]);

            /*
            |--------------------------------------------------------------------------
            | Redirect with Success Message
            |--------------------------------------------------------------------------
            */

            header(
                'Location: index.php?success=' .
                urlencode('Class added successfully.')
            );

            exit;

        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Database Error
            |--------------------------------------------------------------------------
            */

            $errors[] =
                'Unable to add class right now. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
| Guarded like the other pages so the canonical null-safe
| e() from includes/auth_check.php stays the single source.
*/

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars(
            $value,
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

    <title>Add Class | Student Management System</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 35px 20px;
        }

        /* =========================
           Header
        ========================== */

        .page-header {
            margin-bottom: 24px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            color: #2563eb;
            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            margin-bottom: 18px;
        }

        .back-link:hover {
            color: #1d4ed8;
        }

        .page-header h1 {
            font-size: 30px;
            color: #111827;
            margin-bottom: 6px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =========================
           Card
        ========================== */

        .form-card {
            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 16px;

            padding: 30px;

            box-shadow:
                0 8px 25px rgba(15, 23, 42, 0.05);
        }

        .form-section-title {
            font-size: 18px;
            font-weight: 700;

            color: #111827;

            margin-bottom: 22px;

            padding-bottom: 15px;

            border-bottom: 1px solid #e5e7eb;
        }

        /* =========================
           Alerts
        ========================== */

        .alert {
            border-radius: 10px;
            padding: 14px 16px;

            margin-bottom: 22px;

            font-size: 14px;
            line-height: 1.5;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-error strong {
            display: block;
            margin-bottom: 6px;
        }

        .alert-error ul {
            margin-left: 18px;
        }

        .alert-error li {
            margin: 3px 0;
        }

        /* =========================
           Form
        ========================== */

        .form-group {
            margin-bottom: 22px;
        }

        .form-row {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }

        label {
            display: block;

            margin-bottom: 8px;

            font-size: 14px;
            font-weight: 650;

            color: #374151;
        }

        .required {
            color: #dc2626;
        }

        .input,
        .textarea,
        .select {
            width: 100%;

            border: 1px solid #d1d5db;

            border-radius: 10px;

            background: #ffffff;

            color: #111827;

            font-size: 14px;

            outline: none;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        .input,
        .select {
            height: 46px;
            padding: 0 14px;
        }

        .textarea {
            min-height: 130px;

            padding: 13px 14px;

            resize: vertical;

            line-height: 1.5;
        }

        .input:focus,
        .textarea:focus,
        .select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }

        .help-text {
            display: block;

            margin-top: 7px;

            color: #6b7280;

            font-size: 12px;
        }

        /* =========================
           Buttons
        ========================== */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;

            gap: 10px;

            padding-top: 8px;
        }

        .btn {
            min-height: 44px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 0 18px;

            border-radius: 10px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 650;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #374151;

            border: 1px solid #e5e7eb;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
        }

        .btn-submit {
            background: #2563eb;
            color: #ffffff;

            border: 1px solid #2563eb;
        }

        .btn-submit:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* =========================
           Responsive
        ========================== */

        @media (max-width: 700px) {

            .container {
                padding: 22px 15px;
            }

            .form-card {
                padding: 22px 18px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================
         Page Header
    ========================== -->

    <div class="page-header">

        <a
            href="index.php"
            class="back-link"
        >
            ← Back to Classes
        </a>

        <h1>
            Add Class
        </h1>

        <p>
            Create a new class in your school management system.
        </p>

    </div>


    <!-- =========================
         Form Card
    ========================== -->

    <div class="form-card">

        <div class="form-section-title">
            Class Information
        </div>


        <!-- =========================
             Error Messages
        ========================== -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-error">

                <strong>
                    Please fix the following:
                </strong>

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- =========================
             Form
        ========================== -->

        <form
            method="POST"
            action=""
            novalidate
        >
            <?= csrf_field() ?>

            <!-- Class Name -->

            <div class="form-group">

                <label for="name">

                    Class Name

                    <span class="required">
                        *
                    </span>

                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="input"
                    value="<?= e($name) ?>"
                    placeholder="e.g. Class 1"
                    maxlength="100"
                    required
                >

                <span class="help-text">
                    Enter a unique class name, such as Class 1,
                    Class 2, or Grade 10.
                </span>

            </div>


            <!-- Description -->

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="textarea"
                    maxlength="5000"
                    placeholder="Enter a short description about this class..."
                ><?= e($description) ?></textarea>

                <span class="help-text">
                    Optional. You can add additional information
                    about the class.
                </span>

            </div>


            <!-- Status -->

            <div class="form-group">

                <label for="status">
                    Status
                </label>

                <select
                    id="status"
                    name="status"
                    class="select"
                >

                    <option
                        value="active"
                        <?= $status === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $status === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>

                </select>

                <span class="help-text">
                    Inactive classes will remain in the database
                    but can be excluded from active operations.
                </span>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <a
                    href="index.php"
                    class="btn btn-cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-submit"
                >
                    Add Class
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>

