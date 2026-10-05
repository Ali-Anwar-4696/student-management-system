<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/TeacherSubject.php';
require_once __DIR__ . '/../../classes/TeacherClass.php';

$pdo = db();
$database = null;


$teacherSubjectModel = new TeacherSubject($pdo);
$teacherClassModel   = new TeacherClass($pdo);

$assignmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$assignmentId || $assignmentId <= 0) {
    die("Invalid assignment ID.");
}

$assignment = $teacherSubjectModel->getAssignmentById($assignmentId);

if (!$assignment) {
    die("Teacher-subject assignment not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $confirmation = $_POST['confirmation'] ?? '';

    if ($confirmation !== 'REMOVE') {

        $error = "Please confirm the removal.";

    } else {

        try {

            // -------------------------------------------------
            // Remove the AUTHORITATIVE class assignments for
            // this teacher + subject. The derived
            // teacher_subjects row is cleaned up by the same
            // transaction inside TeacherClass.
            // -------------------------------------------------

            $success = $teacherClassModel->removeSubjectAssignments(
                (int) $assignment['teacher_id'],
                (int) $assignment['subject_id']
            );

            if ($success) {

                header(
                    "Location: assign-subjects.php?teacher_id="
                    . (int) $assignment['teacher_id']
                    . "&success=Subject removed successfully."
                );

                exit;

            } else {

                $error = "Subject could not be removed.";
            }

        } catch (Throwable $e) {

            $error = "An error occurred while removing the subject.";
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

    <title>Remove Subject</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 650px;
            margin: 70px auto;
            padding: 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 14px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.07);
        }

        h1 {
            margin-top: 0;
            font-size: 25px;
        }

        .warning {
            background: #fff7ed;
            color: #9a3412;
            padding: 16px;
            border-radius: 10px;
            margin: 20px 0;
            line-height: 1.6;
        }

        .info {
            background: #f8fafc;
            padding: 18px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-row {
            margin-bottom: 10px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .label {
            font-weight: 600;
        }

        .alert {
            background: #fee2e2;
            color: #991b1b;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            padding: 11px 18px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-danger {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-secondary {
            background: #6b7280;
            color: #ffffff;
        }

        @media (max-width: 600px) {

            .container {
                margin: 30px auto;
            }

            .card {
                padding: 20px;
            }

            .buttons {
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

    <div class="card">

        <h1>Remove Subject</h1>

        <div class="info">

            <div class="info-row">

                <span class="label">
                    Teacher:
                </span>

                <?= htmlspecialchars(
                    $assignment['teacher_name']
                ) ?>

            </div>

            <div class="info-row">

                <span class="label">
                    Teacher ID:
                </span>

                <?= htmlspecialchars(
                    $assignment['teacher_code']
                ) ?>

            </div>

            <div class="info-row">

                <span class="label">
                    Subject:
                </span>

                <?= htmlspecialchars(
                    $assignment['subject_name']
                ) ?>

            </div>

            <?php if (!empty($assignment['subject_code'])): ?>

                <div class="info-row">

                    <span class="label">
                        Subject Code:
                    </span>

                    <?= htmlspecialchars(
                        $assignment['subject_code']
                    ) ?>

                </div>

            <?php endif; ?>

        </div>


        <div class="warning">

            <strong>Warning:</strong>

            Are you sure you want to remove this subject
            from this teacher?

            <br>

            The subject itself will NOT be deleted.
            Only the teacher-subject assignment will be removed.

        </div>


        <?php if ($error !== ''): ?>

            <div class="alert">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            onsubmit="return confirm(
                'Are you sure you want to remove this subject?'
            );"
        >
            <?= csrf_field() ?>

            <input
                type="hidden"
                name="confirmation"
                value="REMOVE"
            >

            <div class="buttons">

                <button
                    type="submit"
                    class="btn btn-danger"
                >
                    Remove Subject
                </button>

                <a
                    href="assign-subjects.php?teacher_id=<?= (int) $assignment['teacher_id'] ?>"
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