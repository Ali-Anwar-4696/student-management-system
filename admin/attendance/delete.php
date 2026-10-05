<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Attendance.php';

// =====================================================
// ATTENDANCE ID
// =====================================================

$pdo = db();
$database = null;

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Invalid attendance ID.');
}


// =====================================================
// DATABASE
// =====================================================

$database = new Database();
$pdo = $database->connect();


// =====================================================
// OBJECT
// =====================================================

$attendanceObject = new Attendance($pdo);


// =====================================================
// GET ATTENDANCE
// =====================================================

$attendance = $attendanceObject->getAttendanceById($id);

if (!$attendance) {
    http_response_code(404);
    die('Attendance record not found.');
}


// =====================================================
// TEACHER AUTHORIZATION
// =====================================================

if ($_SESSION['user_role'] === 'teacher') {

    $teacherStmt = $pdo->prepare("
        SELECT id
        FROM teachers
        WHERE user_id = :user_id
          AND status = 'active'
        LIMIT 1
    ");

    $teacherStmt->execute([
        ':user_id' => (int) $_SESSION['user_id']
    ]);

    $teacher = $teacherStmt->fetch();

    if (!$teacher) {
        http_response_code(403);
        die('Teacher profile not found.');
    }

    $teacherId = (int) $teacher['id'];


    $authorizationStmt = $pdo->prepare("
        SELECT id
        FROM teacher_classes
        WHERE teacher_id = :teacher_id
          AND class_id = :class_id
          AND (
                section_id IS NULL
                OR section_id = :section_id
              )
          AND (
                subject_id IS NULL
                OR subject_id = :subject_id
              )
        LIMIT 1
    ");

    $authorizationStmt->execute([
        ':teacher_id' => $teacherId,
        ':class_id' => (int) $attendance['class_id'],
        ':section_id' => $attendance['section_id'] !== null
            ? (int) $attendance['section_id']
            : null,
        ':subject_id' => $attendance['subject_id'] !== null
            ? (int) $attendance['subject_id']
            : null
    ]);

    if (!$authorizationStmt->fetch()) {
        http_response_code(403);
        die('You are not authorized to delete this attendance.');
    }
}


// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


// =====================================================
// VALUES
// =====================================================

$studentName = (string) (
    $attendance['student_name']
    ?? $attendance['name']
    ?? '-'
);

$studentId = (string) (
    $attendance['student_id']
    ?? '-'
);

$className = (string) (
    $attendance['class_name']
    ?? '-'
);

$sectionName = (string) (
    $attendance['section_name']
    ?? 'Whole Class'
);

$subjectName = (string) (
    $attendance['subject_name']
    ?? '-'
);

$date = (string) (
    $attendance['date']
    ?? '-'
);

$status = strtolower(
    (string) (
        $attendance['status']
        ?? ''
    )
);

$errors = [];


// =====================================================
// DELETE
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -------------------------------------------------
    // CSRF
    // -------------------------------------------------

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {
        $errors[] =
            'Invalid security token. Please try again.';
    }


    // -------------------------------------------------
    // CONFIRMATION
    // -------------------------------------------------

    if (
        !isset($_POST['confirm']) ||
        $_POST['confirm'] !== 'yes'
    ) {
        $errors[] =
            'Please confirm that you want to delete this record.';
    }


    // -------------------------------------------------
    // DELETE
    // -------------------------------------------------

    if (empty($errors)) {

        try {

            $deleted =
                $attendanceObject->deleteAttendance($id);

            if ($deleted) {

                $_SESSION['success_message'] =
                    'Attendance record deleted successfully.';

                header('Location: index.php');
                exit;

            } else {

                $errors[] =
                    'Unable to delete attendance record.';
            }

        } catch (PDOException $e) {

            $errors[] =
                'Database error occurred while deleting attendance.';
        }
    }
}


// =====================================================
// HELPER
// =====================================================

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
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
        Delete Attendance | Student Management
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


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

        .page-wrapper {
            min-height: 100vh;
            padding: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 620px;
        }

        .card {
            background: white;
            border: 1px solid #e7eaf0;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(20,30,55,.06);
        }

        .icon {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            background: #fff1f2;
            color: #be123c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 27px;
            margin: 0 auto 18px;
        }

        .title {
            text-align: center;
            font-size: 23px;
            font-weight: 800;
        }

        .subtitle {
            text-align: center;
            margin-top: 7px;
            color: #7b8498;
            font-size: 13px;
        }

        .warning {
            margin-top: 22px;
            padding: 14px 16px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.6;
        }

        .details {
            margin-top: 20px;
            border: 1px solid #edf0f4;
            border-radius: 11px;
            overflow: hidden;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 13px 15px;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        .row:last-child {
            border-bottom: 0;
        }

        .label {
            color: #8991a3;
            font-weight: 600;
        }

        .value {
            font-weight: 700;
            text-align: right;
        }

        .status {
            text-transform: capitalize;
        }

        .alert {
            margin-bottom: 18px;
            padding: 13px 15px;
            border-radius: 9px;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #be123c;
            font-size: 12px;
        }

        .alert ul {
            padding-left: 18px;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 12px 15px;
            border-radius: 9px;
            text-align: center;
            text-decoration: none;
            border: 0;
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .cancel {
            background: #f3f4f6;
            color: #4b5563;
        }

        .delete {
            background: #dc2626;
            color: white;
        }

        .delete:hover {
            background: #b91c1c;
        }

        @media (max-width: 600px) {

            .page-wrapper {
                padding: 15px;
            }

            .card {
                padding: 22px;
            }

            .actions {
                flex-direction: column;
            }

            .row {
                flex-direction: column;
                gap: 5px;
            }

            .value {
                text-align: left;
            }

        }

    </style>

</head>

<body>

<div class="page-wrapper">

<div class="container">


    <div class="card">


        <div class="icon">
            🗑
        </div>


        <div class="title">
            Delete Attendance
        </div>


        <div class="subtitle">
            Are you sure you want to delete this attendance record?
        </div>


        <?php if (!empty($errors)): ?>

            <div class="alert">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?= e($error) ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <div class="warning">

            <strong>Warning:</strong>

            This action will permanently remove this attendance
            record from the database and cannot be undone.

        </div>


        <div class="details">


            <div class="row">

                <span class="label">
                    Student
                </span>

                <span class="value">
                    <?= e($studentName) ?>
                </span>

            </div>


            <div class="row">

                <span class="label">
                    Student ID
                </span>

                <span class="value">
                    <?= e($studentId) ?>
                </span>

            </div>


            <div class="row">

                <span class="label">
                    Class
                </span>

                <span class="value">
                    <?= e($className) ?>
                </span>

            </div>


            <div class="row">

                <span class="label">
                    Section
                </span>

                <span class="value">
                    <?= e($sectionName) ?>
                </span>

            </div>


            <div class="row">

                <span class="label">
                    Subject
                </span>

                <span class="value">
                    <?= e($subjectName) ?>
                </span>

            </div>


            <div class="row">

                <span class="label">
                    Date
                </span>

                <span class="value">
                    <?= e($date) ?>
                </span>

            </div>


            <div class="row">

                <span class="label">
                    Status
                </span>

                <span class="value status">
                    <?= e($status) ?>
                </span>

            </div>


        </div>


        <form
            method="POST"
            action="delete.php?id=<?= $id ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= $id ?>"
            >

            <input
                type="hidden"
                name="confirm"
                value="yes"
            >


            <div class="actions">

                <a
                    href="view.php?id=<?= $id ?>"
                    class="btn cancel"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn delete"
                >
                    Yes, Delete Attendance
                </button>

            </div>

        </form>


    </div>

</div>

</div>

</body>

</html>