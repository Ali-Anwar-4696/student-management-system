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

$id = (int) ($_GET['id'] ?? 0);

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
        die('You are not authorized to edit this attendance.');
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

$studentCode = (string) (
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
    ?? ''
);

$status = strtolower(
    (string) (
        $attendance['status']
        ?? 'present'
    )
);

$errors = [];


// =====================================================
// UPDATE
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
        $errors[] = 'Invalid security token. Please try again.';
    }


    // -------------------------------------------------
    // INPUT
    // -------------------------------------------------

    $date = trim(
        (string) ($_POST['date'] ?? '')
    );

    $status = strtolower(
        trim(
            (string) ($_POST['status'] ?? '')
        )
    );


    // -------------------------------------------------
    // DATE VALIDATION
    // -------------------------------------------------

    if ($date === '') {

        $errors[] = 'Attendance date is required.';

    } else {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $date
        ) {
            $errors[] = 'Please enter a valid attendance date.';
        }
    }


    // -------------------------------------------------
    // STATUS VALIDATION
    // -------------------------------------------------

    $allowedStatuses = [
        'present',
        'absent',
        'late',
        'leave'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Invalid attendance status.';
    }


    // -------------------------------------------------
    // DUPLICATE CHECK
    // -------------------------------------------------

    if (empty($errors)) {

        $duplicateSql = "
            SELECT id
            FROM attendance
            WHERE student_id = :student_id
              AND date = :date
              AND id != :id
        ";

        if ($attendance['subject_id'] === null) {

            $duplicateSql .= "
                AND subject_id IS NULL
            ";

        } else {

            $duplicateSql .= "
                AND subject_id = :subject_id
            ";
        }

        $duplicateSql .= " LIMIT 1";

        $duplicateStmt = $pdo->prepare($duplicateSql);

        $duplicateParams = [
            ':student_id' => (int) $attendance['student_id'],
            ':date' => $date,
            ':id' => $id
        ];

        if ($attendance['subject_id'] !== null) {
            $duplicateParams[':subject_id'] =
                (int) $attendance['subject_id'];
        }

        $duplicateStmt->execute($duplicateParams);

        if ($duplicateStmt->fetch()) {
            $errors[] =
                'Attendance already exists for this student on this date.';
        }
    }


    // -------------------------------------------------
    // UPDATE DATABASE
    // -------------------------------------------------

    if (empty($errors)) {

        try {

            $updated = $attendanceObject->updateAttendance(
                $id,
                [
                    'date' => $date,
                    'status' => $status
                ]
            );

            if ($updated) {

                $_SESSION['success_message'] =
                    'Attendance updated successfully.';

                header(
                    'Location: view.php?id=' . $id
                );

                exit;

            } else {

                $errors[] =
                    'Unable to update attendance.';
            }

        } catch (PDOException $e) {

            $errors[] =
                'Database error occurred while updating attendance.';
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
        Edit Attendance | Student Management
    </title>


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
        }

        .container {
            max-width: 850px;
            margin: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
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
            font-size: 23px;
        }

        h1 {
            font-size: 26px;
            font-weight: 800;
        }

        .subtitle {
            margin-top: 5px;
            color: #7b8498;
            font-size: 14px;
        }

        .back-btn {
            text-decoration: none;
            background: white;
            color: #4b5563;
            border: 1px solid #dfe3ea;
            padding: 10px 15px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
        }

        .card {
            background: white;
            border: 1px solid #e7eaf0;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(20,30,55,.04);
        }

        .student-box {
            padding: 17px;
            background: #f8f9fc;
            border: 1px solid #edf0f4;
            border-radius: 11px;
            margin-bottom: 22px;
        }

        .student-name {
            font-size: 17px;
            font-weight: 800;
        }

        .student-meta {
            margin-top: 7px;
            color: #7b8498;
            font-size: 12px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            margin-bottom: 5px;
        }

        label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #4b5563;
        }

        input,
        select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d9dee7;
            border-radius: 9px;
            background: white;
            font-family: inherit;
            font-size: 13px;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #4f46e5;
        }

        .readonly {
            background: #f8fafc;
            color: #6b7280;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
            font-size: 13px;
        }

        .alert ul {
            padding-left: 18px;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #edf0f4;
        }

        .btn {
            border: 0;
            text-decoration: none;
            padding: 11px 17px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .cancel-btn {
            background: #f3f4f6;
            color: #4b5563;
        }

        .update-btn {
            background: #4f46e5;
            color: white;
        }

        .update-btn:hover {
            background: #4338ca;
        }

        @media (max-width: 700px) {

            .page-wrapper {
                padding: 18px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                text-align: center;
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="page-wrapper">

<div class="container">


    <div class="page-header">

        <div class="header-left">

            <div class="header-icon">
                ✎
            </div>

            <div>

                <h1>
                    Edit Attendance
                </h1>

                <div class="subtitle">
                    Update attendance record
                </div>

            </div>

        </div>

        <a
            href="view.php?id=<?= $id ?>"
            class="back-btn"
        >
            ← View Attendance
        </a>

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


    <div class="card">


        <div class="student-box">

            <div class="student-name">
                <?= e($studentName) ?>
            </div>

            <div class="student-meta">

                Student ID:
                <?= e($studentCode) ?>

                &nbsp; • &nbsp;

                Class:
                <?= e($className) ?>

                &nbsp; • &nbsp;

                Section:
                <?= e($sectionName) ?>

                &nbsp; • &nbsp;

                Subject:
                <?= e($subjectName) ?>

            </div>

        </div>


        <form
            method="POST"
            action="edit.php?id=<?= $id ?>"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <div class="grid">


                <div class="form-group">

                    <label for="date">
                        Attendance Date
                    </label>

                    <input
                        type="date"
                        id="date"
                        name="date"
                        value="<?= e($date) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="status">
                        Attendance Status
                    </label>

                    <select
                        id="status"
                        name="status"
                        required
                    >

                        <option
                            value="present"
                            <?= $status === 'present'
                                ? 'selected'
                                : '' ?>
                        >
                            Present
                        </option>

                        <option
                            value="absent"
                            <?= $status === 'absent'
                                ? 'selected'
                                : '' ?>
                        >
                            Absent
                        </option>

                        <option
                            value="late"
                            <?= $status === 'late'
                                ? 'selected'
                                : '' ?>
                        >
                            Late
                        </option>

                        <option
                            value="leave"
                            <?= $status === 'leave'
                                ? 'selected'
                                : '' ?>
                        >
                            Leave
                        </option>

                    </select>

                </div>


            </div>


            <div class="actions">

                <a
                    href="view.php?id=<?= $id ?>"
                    class="btn cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn update-btn"
                >
                    Update Attendance
                </button>

            </div>


        </form>

    </div>

</div>

</div>

</body>

</html>