<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Section.php';

$pdo = db();
$section = new Section($pdo);


$errors = [];

$name = '';
$classId = '';
$status = 'active';


// =====================================================
// GET ACTIVE CLASSES
// =====================================================

$classes = $section->getActiveClasses();


// =====================================================
// FORM SUBMISSION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $name = trim($_POST['name'] ?? '');
    $classId = trim($_POST['class_id'] ?? '');
    $status = trim($_POST['status'] ?? 'active');


    // =================================================
    // VALIDATE SECTION NAME
    // =================================================

    if ($name === '') {

        $errors[] = 'Section name is required.';

    } elseif (strlen($name) < 1) {

        $errors[] = 'Section name is invalid.';

    } elseif (strlen($name) > 50) {

        $errors[] = 'Section name must not exceed 50 characters.';
    }


    // =================================================
    // VALIDATE CLASS
    // =================================================

    $validatedClassId = filter_var(
        $classId,
        FILTER_VALIDATE_INT
    );

    if (
        $validatedClassId === false ||
        $validatedClassId <= 0
    ) {

        $errors[] = 'Please select a valid class.';

    } else {

        $classId = (string) $validatedClassId;
    }


    // =================================================
    // VALIDATE STATUS
    // =================================================

    $allowedStatuses = [
        'active',
        'inactive'
    ];

    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $errors[] = 'Invalid status selected.';
    }


    // =================================================
    // DUPLICATE CHECK
    // =================================================

    if (empty($errors)) {

        if (
            $section->sectionNameExists(
                (int) $classId,
                $name
            )
        ) {

            $errors[] =
                'This section already exists in the selected class.';
        }
    }


    // =================================================
    // INSERT
    // =================================================

    if (empty($errors)) {

        try {

            $success = $section->addSection([
                'class_id' => (int) $classId,
                'name'     => $name,
                'status'   => $status
            ]);

            if ($success) {

                header(
                    'Location: index.php?success=' .
                    urlencode('Section added successfully.')
                );

                exit;
            }

            $errors[] =
                'Unable to add section. Please try again.';

        } catch (Throwable $e) {

            $errors[] =
                'An unexpected error occurred. Please try again.';
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

    <title>Add Section</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f9;
            color: #1f2937;
        }

        .container {
            width: 92%;
            max-width: 850px;
            margin: 40px auto;
        }

        /* =========================================
           HEADER
        ========================================= */

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;

            text-decoration: none;
            color: #2563eb;

            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* =========================================
           CARD
        ========================================= */

        .card {
            background: white;
            padding: 30px;
            border-radius: 14px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        /* =========================================
           ALERT
        ========================================= */

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 22px;

            font-size: 14px;
        }

        .alert-danger {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-danger ul {
            margin-left: 20px;
        }

        .alert-danger li {
            margin-bottom: 5px;
        }

        /* =========================================
           FORM
        ========================================= */

        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;

            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;

            padding: 12px 14px;

            border: 1px solid #d1d5db;
            border-radius: 8px;

            font-size: 14px;

            outline: none;
            background: white;
        }

        .form-control:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .help-text {
            display: block;

            margin-top: 6px;

            color: #6b7280;
            font-size: 12px;
        }

        /* =========================================
           BUTTONS
        ========================================= */

        .form-actions {
            display: flex;
            gap: 10px;

            margin-top: 30px;
        }

        .btn {
            display: inline-block;

            padding: 12px 20px;

            border: none;
            border-radius: 8px;

            text-decoration: none;
            cursor: pointer;

            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 600px) {

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .form-actions {
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

<div class="container">

    <!-- =========================================
         HEADER
    ========================================== -->

    <div class="page-header">

        <a
            href="index.php"
            class="back-link"
        >
            ← Sections
        </a>

        <h1>
            Add Section
        </h1>

        <p>
            Create a new section and assign it to a class.
        </p>

    </div>


    <!-- =========================================
         CARD
    ========================================== -->

    <div class="card">

        <!-- =====================================
             ERRORS
        ====================================== -->

        <?php if (!empty($errors)): ?>

            <div class="alert alert-danger">

                <strong>
                    Please fix the following:
                </strong>

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- =====================================
             FORM
        ====================================== -->

        <form
            method="POST"
            action=""
        >
            <?= csrf_field() ?>

            <!-- SECTION NAME -->

            <div class="form-group">

                <label
                    for="name"
                    class="form-label"
                >
                    Section Name
                    <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    maxlength="50"
                    placeholder="Example: Section A"
                    value="<?= htmlspecialchars(
                        $name,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >

                <span class="help-text">
                    Example: Section A, Section B, Section C
                </span>

            </div>


            <!-- CLASS -->

            <div class="form-group">

                <label
                    for="class_id"
                    class="form-label"
                >
                    Assign to Class
                    <span class="required">*</span>
                </label>

                <select
                    id="class_id"
                    name="class_id"
                    class="form-control"
                    required
                >

                    <option value="">
                        Select Class
                    </option>

                    <?php foreach ($classes as $class): ?>

                        <option
                            value="<?= (int) $class['id'] ?>"
                            <?= (string) $classId ===
                                (string) $class['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars(
                                $class['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <span class="help-text">
                    Select the class to which this section belongs.
                </span>

            </div>


            <!-- STATUS -->

            <div class="form-group">

                <label
                    for="status"
                    class="form-label"
                >
                    Status
                    <span class="required">*</span>
                </label>

                <select
                    id="status"
                    name="status"
                    class="form-control"
                    required
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

            </div>


            <!-- ACTIONS -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Create Section
                </button>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>