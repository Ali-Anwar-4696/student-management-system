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
// Get Subject
// =====================================================

$subjectData = $subject->getSubjectById($id);

if ($subjectData === null) {
    http_response_code(404);
    die("Subject not found.");
}


$errors = [];


// =====================================================
// Dependent Records
// =====================================================
// Marks (grades!), exam entries, assignments and
// subject-level attendance block the deletion so academic
// history is never silently destroyed.
// =====================================================

$dependents = $subject->countDependents($id);

$blockingLabels = [
    'marks'         => 'marks',
    'exam_subjects' => 'exam entries',
    'assignments'   => 'assignments',
    'attendance'    => 'attendance records',
];

$summaryParts = [];

foreach ($blockingLabels as $key => $label) {

    if (($dependents[$key] ?? 0) > 0) {
        $summaryParts[] = $dependents[$key] . ' ' . $label;
    }
}

$canDelete = ($summaryParts === []);

$dependentSummary = implode(', ', $summaryParts);


// =====================================================
// Delete Request
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $confirmation = trim(
        $_POST['confirmation'] ?? ''
    );


    // =================================================
    // Confirmation Validation
    // =================================================

    if (!$canDelete) {

        $errors[] =
            'This subject cannot be deleted because it still has academic records ('
            . $dependentSummary
            . '). Move or remove them first — deleting would destroy them.';

    } elseif ($confirmation !== 'DELETE') {

        $errors[] =
            "Please type DELETE exactly to confirm deletion.";

    } else {

        try {

            $deleted = $subject->deleteSubject($id);

            if ($deleted) {

                header(
                    "Location: index.php?success=" .
                    urlencode("Subject deleted successfully.")
                );

                exit;

            } else {

                $errors[] =
                    "Subject could not be deleted.";

            }

        } catch (PDOException $e) {

            $errors[] =
                "Unable to delete subject. Please try again.";
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
        Delete Subject | Student Management System
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
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* =================================================
           Back Link
        ================================================= */

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* =================================================
           Card
        ================================================= */

        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 25px;
            background: #fef2f2;
            border-bottom: 1px solid #fecaca;
        }

        .card-header h1 {
            font-size: 26px;
            color: #991b1b;
            margin-bottom: 8px;
        }

        .card-header p {
            color: #7f1d1d;
            font-size: 14px;
            line-height: 1.6;
        }

        /* =================================================
           Content
        ================================================= */

        .content {
            padding: 25px;
        }

        .warning {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 22px;
            line-height: 1.6;
            font-size: 14px;
        }

        /* =================================================
           Subject Info
        ================================================= */

        .subject-info {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 22px;
            overflow: hidden;
        }

        .info-row {
            display: grid;
            grid-template-columns: 180px 1fr;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            padding: 13px 15px;
            background: #f3f4f6;
            font-size: 13px;
            font-weight: 600;
            color: #4b5563;
        }

        .info-value {
            padding: 13px 15px;
            font-size: 14px;
            color: #111827;
            word-break: break-word;
        }

        /* =================================================
           Error
        ================================================= */

        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }

        /* =================================================
           Confirmation
        ================================================= */

        .confirmation-box {
            margin-top: 10px;
        }

        .confirmation-box label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
        }

        .confirmation-box input {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        .confirmation-box input:focus {
            border-color: #dc2626;
        }

        .help-text {
            margin-top: 7px;
            color: #6b7280;
            font-size: 12px;
        }

        /* =================================================
           Buttons
        ================================================= */

        .actions {
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

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
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

        @media (max-width: 600px) {

            .container {
                padding: 25px 12px;
            }

            .card-header,
            .content {
                padding: 20px;
            }

            .info-row {
                grid-template-columns: 1fr;
            }

            .info-label {
                border-bottom: 1px solid #e5e7eb;
            }

            .actions {
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
         Back
    ================================================= -->

    <a
        href="index.php"
        class="back-link"
    >
        ← Subjects
    </a>


    <!-- =================================================
         Delete Card
    ================================================= -->

    <div class="card">

        <div class="card-header">

            <h1>
                Delete Subject
            </h1>

            <p>
                You are about to permanently delete this subject.
                This action cannot be undone.
            </p>

        </div>


        <div class="content">


            <!-- =================================================
                 Warning / Dependent Records
            ================================================= -->

            <?php if ($canDelete): ?>

                <div class="warning">

                    <strong>Warning:</strong>

                    Deleting this subject will also remove its
                    teacher-subject assignments, class links and
                    result configurations. This subject has no
                    marks, exams, assignments or attendance,
                    so no academic history is lost.

                    If you only want to stop using this subject,
                    consider changing its status to
                    <strong>Inactive</strong> instead.

                </div>

            <?php else: ?>

                <div
                    class="warning"
                    style="background:#fef2f2; border-color:#fecaca; color:#991b1b;"
                >

                    <strong>⛔ This subject cannot be deleted yet.</strong>

                    <ul style="margin:8px 0 8px 20px;">

                        <?php if (($dependents['marks'] ?? 0) > 0): ?>

                            <li>
                                <?= (int) $dependents['marks'] ?>
                                mark record(s)
                            </li>

                        <?php endif; ?>

                        <?php if (($dependents['exam_subjects'] ?? 0) > 0): ?>

                            <li>
                                <?= (int) $dependents['exam_subjects'] ?>
                                exam entr(ies)
                            </li>

                        <?php endif; ?>

                        <?php if (($dependents['assignments'] ?? 0) > 0): ?>

                            <li>
                                <?= (int) $dependents['assignments'] ?>
                                assignment(s)
                            </li>

                        <?php endif; ?>

                        <?php if (($dependents['attendance'] ?? 0) > 0): ?>

                            <li>
                                <?= (int) $dependents['attendance'] ?>
                                attendance record(s)
                            </li>

                        <?php endif; ?>

                    </ul>

                    Deleting this subject would permanently
                    destroy these academic records. Move or
                    remove them first.

                </div>

            <?php endif; ?>


            <!-- =================================================
                 Errors
            ================================================= -->

            <?php if (!empty($errors)): ?>

                <div class="error-box">

                    <?php foreach ($errors as $error): ?>

                        <?= htmlspecialchars($error) ?>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 Subject Information
            ================================================= -->

            <div class="subject-info">

                <div class="info-row">

                    <div class="info-label">
                        Subject ID
                    </div>

                    <div class="info-value">

                        #<?= (int) $subjectData['id'] ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Subject Name
                    </div>

                    <div class="info-value">

                        <strong>
                            <?= htmlspecialchars(
                                $subjectData['name']
                            ) ?>
                        </strong>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Subject Code
                    </div>

                    <div class="info-value">

                        <?php if (!empty($subjectData['code'])): ?>

                            <?= htmlspecialchars(
                                $subjectData['code']
                            ) ?>

                        <?php else: ?>

                            <span style="color:#9ca3af;">
                                Not assigned
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="info-row">

                    <div class="info-label">
                        Status
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            ucfirst($subjectData['status'])
                        ) ?>

                    </div>

                </div>

            </div>


            <?php if ($canDelete): ?>

                <!-- =================================================
                     Confirmation Form
                ================================================= -->

                <form
                    method="POST"
                    action=""
                    onsubmit="return confirm(
                        'Are you absolutely sure you want to delete this subject?'
                    );"
                >
                    <?= csrf_field() ?>

                    <div class="confirmation-box">

                        <label for="confirmation">

                            Type
                            <strong>DELETE</strong>
                            to confirm:

                        </label>

                        <input
                            type="text"
                            id="confirmation"
                            name="confirmation"
                            placeholder="Type DELETE"
                            autocomplete="off"
                            required
                        >

                        <div class="help-text">

                            This confirmation is case-sensitive.

                        </div>

                    </div>


                    <!-- =================================================
                         Actions
                    ================================================= -->

                    <div class="actions">

                        <button
                            type="submit"
                            class="btn btn-danger"
                        >
                            Delete Subject
                        </button>

                        <a
                            href="view.php?id=<?= (int) $subjectData['id'] ?>"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            <?php else: ?>

                <!-- =================================================
                     Actions (deletion blocked)
                ================================================= -->

                <div class="actions">

                    <a
                        href="view.php?id=<?= (int) $subjectData['id'] ?>"
                        class="btn btn-secondary"
                    >
                        Back to Subject
                    </a>

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        Subjects List
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>