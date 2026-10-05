<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Subject.php';

$pdo = db();
$subject = new Subject($pdo);



// =====================================================
// Validate ID
// =====================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(404);
    die("Invalid subject ID.");
}


// =====================================================
// Get Existing Subject
// =====================================================

$subjectData = $subject->getSubjectById($id);

if ($subjectData === null) {
    http_response_code(404);
    die("Subject not found.");
}


// =====================================================
// Form Values
// =====================================================

$name = (string) $subjectData['name'];
$code = (string) ($subjectData['code'] ?? '');
$description = (string) ($subjectData['description'] ?? '');
$status = (string) $subjectData['status'];

$errors = [];


// =====================================================
// Form Submit
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? 'active');


    // =================================================
    // Validation
    // =================================================

    if ($name === '') {

        $errors[] = "Subject name is required.";

    } elseif (mb_strlen($name) > 100) {

        $errors[] =
            "Subject name cannot exceed 100 characters.";
    }


    if ($code !== '' && mb_strlen($code) > 50) {

        $errors[] =
            "Subject code cannot exceed 50 characters.";
    }


    if (!in_array($status, ['active', 'inactive'], true)) {

        $errors[] =
            "Invalid status selected.";
    }


    // =================================================
    // Duplicate Check
    // =================================================

    if (empty($errors)) {

        if (
            $subject->subjectExists(
                $name,
                $code !== '' ? $code : null,
                $id
            )
        ) {

            $errors[] =
                "Another subject with this name or code already exists.";
        }
    }


    // =================================================
    // Update Subject
    // =================================================

    if (empty($errors)) {

        try {

            $updated = $subject->updateSubject(
                $id,
                [
                    'name' => $name,
                    'code' => $code !== ''
                        ? $code
                        : null,
                    'description' => $description !== ''
                        ? $description
                        : null,
                    'status' => $status
                ]
            );

            if ($updated) {

                header(
                    "Location: index.php?success=" .
                    urlencode("Subject updated successfully.")
                );

                exit;
            }

            $errors[] =
                "No changes were made to the subject.";

        } catch (PDOException $e) {

            $errors[] =
                "Unable to update subject. Please try again.";
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
        Edit Subject | Student Management System
    </title>

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

            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            width: 100%;
            max-width: 850px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* =================================================
           Header
        ================================================= */

        .page-header {
            margin-bottom: 25px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 18px;
            text-decoration: none;
            color: #2563eb;
            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =================================================
           Card
        ================================================= */

        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 25px;
        }

        /* =================================================
           Error
        ================================================= */

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            padding: 15px 18px;
            margin-bottom: 20px;
        }

        .error-box strong {
            display: block;
            margin-bottom: 8px;
        }

        .error-box ul {
            padding-left: 20px;
        }

        .error-box li {
            margin-bottom: 4px;
        }

        /* =================================================
           Subject ID
        ================================================= */

        .subject-id {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 22px;
            font-size: 13px;
            color: #6b7280;
        }

        .subject-id strong {
            color: #111827;
        }

        /* =================================================
           Form
        ================================================= */

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }

        .required {
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
            font-family: inherit;
        }

        .form-control:focus {
            border-color: #2563eb;
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .help-text {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
        }

        /* =================================================
           Buttons
        ================================================= */

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 11px 20px;
            border-radius: 8px;
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

        /* =================================================
           Responsive
        ================================================= */

        @media (max-width: 650px) {

            .container {
                padding: 20px 12px;
            }

            .card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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

    <!-- =================================================
         Header
    ================================================= -->

    <div class="page-header">

        <a
            href="index.php"
            class="back-link"
        >
            ← Subjects
        </a>

        <h1>
            Edit Subject
        </h1>

        <p>
            Update the information of this subject.
        </p>

    </div>


    <!-- =================================================
         Form Card
    ================================================= -->

    <div class="card">

        <!-- Subject ID -->

        <div class="subject-id">

            Subject ID:

            <strong>
                #<?= (int) $subjectData['id'] ?>
            </strong>

        </div>


        <!-- =================================================
             Errors
        ================================================= -->

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <strong>
                    Please fix the following errors:
                </strong>

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= htmlspecialchars($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- =================================================
             Form
        ================================================= -->

        <form
            method="POST"
            action=""
        >
            <?= csrf_field() ?>

            <!-- Name + Code -->

            <div class="form-row">

                <div class="form-group">

                    <label for="name">

                        Subject Name

                        <span class="required">*</span>

                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        class="form-control"
                        maxlength="100"
                        value="<?= htmlspecialchars($name) ?>"
                        required
                    >

                    <div class="help-text">
                        Enter the complete subject name.
                    </div>

                </div>


                <div class="form-group">

                    <label for="code">
                        Subject Code
                    </label>

                    <input
                        type="text"
                        id="code"
                        name="code"
                        class="form-control"
                        maxlength="50"
                        value="<?= htmlspecialchars($code) ?>"
                    >

                    <div class="help-text">
                        Optional unique subject code.
                    </div>

                </div>

            </div>


            <!-- Description -->

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    placeholder="Enter subject description..."
                ><?= htmlspecialchars($description) ?></textarea>

                <div class="help-text">
                    Optional information about this subject.
                </div>

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
                    class="form-control"
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


            <!-- Actions -->

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Update Subject
                </button>

                <a
                    href="view.php?id=<?= (int) $subjectData['id'] ?>"
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