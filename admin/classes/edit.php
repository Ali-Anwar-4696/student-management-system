<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/ClassRoom.php';

$pdo = db();
$classRoom = new ClassRoom($pdo);


/*
|--------------------------------------------------------------------------
| Get Class ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {

    http_response_code(400);

    die(
        '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invalid Class ID</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f5f7fb;
                    padding: 40px;
                    text-align: center;
                }
                .box {
                    max-width: 600px;
                    margin: 80px auto;
                    background: #fff;
                    padding: 40px;
                    border-radius: 15px;
                    border: 1px solid #fecaca;
                }
                h2 {
                    color: #991b1b;
                }
                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 16px;
                    background: #2563eb;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 8px;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <h2>Invalid Class ID</h2>
                <p>The class ID provided is invalid or missing.</p>
                <a href="index.php">Back to Classes</a>
            </div>
        </body>
        </html>'
    );
}

/*
|--------------------------------------------------------------------------
| Get Existing Class
|--------------------------------------------------------------------------
*/

$class = $classRoom->getClassById($id);

if ($class === null) {

    http_response_code(404);

    die(
        '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Class Not Found</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background: #f5f7fb;
                    padding: 40px;
                    text-align: center;
                }
                .box {
                    max-width: 600px;
                    margin: 80px auto;
                    background: #fff;
                    padding: 40px;
                    border-radius: 15px;
                    border: 1px solid #fecaca;
                }
                h2 {
                    color: #991b1b;
                }
                a {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 16px;
                    background: #2563eb;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 8px;
                }
            </style>
        </head>
        <body>
            <div class="box">
                <h2>Class Not Found</h2>
                <p>The requested class does not exist or may have been deleted.</p>
                <a href="index.php">Back to Classes</a>
            </div>
        </body>
        </html>'
    );
}

/*
|--------------------------------------------------------------------------
| Form Variables
|--------------------------------------------------------------------------
*/

$name = (string) $class['name'];

$description = (string) (
    $class['description'] ?? ''
);

$status = (string) $class['status'];

$errors = [];

/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $name = trim($_POST['name'] ?? '');

    $description = trim(
        $_POST['description'] ?? ''
    );

    $status = trim(
        $_POST['status'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($name === '') {

        $errors[] =
            'Class name is required.';

    } elseif (mb_strlen($name) < 2) {

        $errors[] =
            'Class name must be at least 2 characters.';

    } elseif (mb_strlen($name) > 100) {

        $errors[] =
            'Class name cannot exceed 100 characters.';
    }


    if (mb_strlen($description) > 5000) {

        $errors[] =
            'Description is too long.';
    }


    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (!in_array(
        $status,
        $allowedStatuses,
        true
    )) {

        $errors[] =
            'Invalid status selected.';
    }


    /*
    |--------------------------------------------------------------------------
    | Duplicate Class Name
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        if ($classRoom->classNameExists(
            $name,
            $id
        )) {

            $errors[] =
                'Another class with this name already exists.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $updated = $classRoom->updateClass(
                $id,
                [
                    'name' => $name,
                    'description' =>
                        $description !== ''
                            ? $description
                            : null,
                    'status' => $status
                ]
            );

            if ($updated) {

                header(
                    'Location: index.php?success=' .
                    urlencode(
                        'Class updated successfully.'
                    )
                );

                exit;

            } else {

                $errors[] =
                    'No changes were made to the class.';
            }

        } catch (PDOException $e) {

            $errors[] =
                'Unable to update class right now. Please try again.';
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

    <title>
        Edit <?= e($name) ?> | Student Management System
    </title>

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
           Form Card
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
           Alert
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
           Class ID Info
        ========================== */

        .class-info {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 12px 14px;

            margin-bottom: 22px;

            background: #eff6ff;

            border: 1px solid #dbeafe;

            border-radius: 10px;

            color: #1e40af;

            font-size: 13px;
        }

        .class-info strong {
            color: #1e3a8a;
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
            gap: 7px;

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
            Edit Class
        </h1>

        <p>
            Update the information and status of this class.
        </p>

    </div>


    <!-- =========================
         Form Card
    ========================== -->

    <div class="form-card">

        <div class="form-section-title">
            Class Information
        </div>


        <!-- Class ID -->

        <div class="class-info">

            <span>
                🎓
            </span>

            <span>
                Editing Class ID:
                <strong>
                    #<?= (int) $id ?>
                </strong>
            </span>

        </div>


        <!-- =========================
             Errors
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
            action="?id=<?= (int) $id ?>"
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
                    Class name must be unique.
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
                    placeholder="Enter a short description..."
                ><?= e($description) ?></textarea>

                <span class="help-text">
                    Optional information about this class.
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
                    Inactive classes remain stored but are
                    treated as inactive.
                </span>

            </div>


            <!-- Buttons -->

            <div class="form-actions">

                <a
                    href="view.php?id=<?= (int) $id ?>"
                    class="btn btn-cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-submit"
                >
                    ✓ Update Class
                </button>

            </div>

        </form>

    </div>

</div>

</body>

</html>

