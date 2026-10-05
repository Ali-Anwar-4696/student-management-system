<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';
require_once __DIR__ . '/../../classes/TeacherClass.php';
require_once __DIR__ . '/../../classes/Attendance.php';

$pdo = db();
$database = null;


// =====================================================
// OBJECTS
// =====================================================

$teacherObject = new Teacher($pdo);
$teacherClassObject = new TeacherClass($pdo);
$attendanceObject = new Attendance($pdo);


// =====================================================
// CSRF TOKEN
// =====================================================

$csrfToken = getCsrfToken();

// =====================================================
// CURRENT USER ROLE
// =====================================================

$userRole = $_SESSION['user_role'] ?? '';


// =====================================================
// VARIABLES
// =====================================================

$errors = [];
$success = '';


// =====================================================
// GET VALUES
// =====================================================

$selectedClassId = isset($_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;

$selectedSectionId = isset($_GET['section_id'])
    ? (int) $_GET['section_id']
    : 0;

$selectedDate = $_GET['date'] ?? date('Y-m-d');


// =====================================================
// DATE VALIDATION
// =====================================================

if (
    !is_string($selectedDate) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
) {
    $selectedDate = date('Y-m-d');
}

if ($selectedDate > date('Y-m-d')) {
    $selectedDate = date('Y-m-d');
}


// =====================================================
// TEACHER PROFILE
// =====================================================

$teacher = null;
$teacherId = null;

if ($userRole === 'teacher') {

    $teacher =
        $teacherObject->getTeacherByUserId($userId);

    if (!$teacher) {
        http_response_code(403);
        exit('Teacher profile not found or inactive.');
    }

    $teacherId =
        (int) $teacher['id'];
}


// =====================================================
// GET CLASSES
// =====================================================

$classes = [];

if ($userRole === 'admin') {

    $stmt = $pdo->prepare("
        SELECT
            id,
            name
        FROM classes
        WHERE status = 'active'
        ORDER BY name ASC
    ");

    $stmt->execute();

    $classes = $stmt->fetchAll();

} else {

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            c.id,
            c.name
        FROM teacher_classes AS tc

        INNER JOIN classes AS c
            ON tc.class_id = c.id

        WHERE tc.teacher_id = :teacher_id
          AND c.status = 'active'

        ORDER BY c.name ASC
    ");

    $stmt->execute([
        ':teacher_id' => $teacherId
    ]);

    $classes = $stmt->fetchAll();
}


// =====================================================
// VALIDATE SELECTED CLASS
// =====================================================

if ($selectedClassId > 0) {

    $classExists = false;

    foreach ($classes as $class) {

        if (
            (int) $class['id']
            === $selectedClassId
        ) {
            $classExists = true;
            break;
        }
    }

    if (!$classExists) {

        $errors[] =
            'You are not authorized to use the selected class.';

        $selectedClassId = 0;
        $selectedSectionId = 0;
    }
}


// =====================================================
// GET SECTIONS
// =====================================================

$sections = [];

if ($selectedClassId > 0) {

    if ($userRole === 'admin') {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name
            FROM sections
            WHERE class_id = :class_id
              AND status = 'active'
            ORDER BY name ASC
        ");

        $stmt->execute([
            ':class_id' => $selectedClassId
        ]);

        $sections = $stmt->fetchAll();

    } else {

        $stmt = $pdo->prepare("
            SELECT DISTINCT
                s.id,
                s.name
            FROM teacher_classes AS tc

            INNER JOIN sections AS s
                ON tc.section_id = s.id

            WHERE tc.teacher_id = :teacher_id
              AND tc.class_id = :class_id
              AND tc.section_id IS NOT NULL
              AND s.class_id = :class_id
              AND s.status = 'active'

            ORDER BY s.name ASC
        ");

        $stmt->execute([
            ':teacher_id' => $teacherId,
            ':class_id'  => $selectedClassId
        ]);

        $sections = $stmt->fetchAll();
    }
}


// =====================================================
// VALIDATE SELECTED SECTION
// =====================================================

if ($selectedSectionId > 0) {

    $sectionExists = false;

    foreach ($sections as $section) {

        if (
            (int) $section['id']
            === $selectedSectionId
        ) {
            $sectionExists = true;
            break;
        }
    }

    if (!$sectionExists) {

        $errors[] =
            'You are not authorized to use the selected section.';

        $selectedSectionId = 0;
    }
}


// =====================================================
// SECTION ID FOR DATABASE
// =====================================================

$sectionIdForDatabase =
    $selectedSectionId > 0
        ? $selectedSectionId
        : null;


// =====================================================
// GET STUDENTS
// =====================================================

$students = [];

if ($selectedClassId > 0) {

    $students =
        $attendanceObject->getStudentsForAttendance(
            $selectedClassId,
            $sectionIdForDatabase
        );
}


// =====================================================
// POST - SAVE ATTENDANCE
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // =================================================
    // CSRF
    // =================================================

    $postedToken =
        $_POST['csrf_token'] ?? '';

    if (
        !is_string($postedToken) ||
        !hash_equals(
            $csrfToken,
            $postedToken
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';
    }


    // =================================================
    // POST VALUES
    // =================================================

    $postClassId = filter_input(
        INPUT_POST,
        'class_id',
        FILTER_VALIDATE_INT
    );

    $postSectionId = filter_input(
        INPUT_POST,
        'section_id',
        FILTER_VALIDATE_INT
    );

    $postDate =
        $_POST['attendance_date'] ?? '';

    $attendanceData =
        $_POST['attendance'] ?? [];


    // =================================================
    // NORMALIZE SECTION
    // =================================================

    $postSectionId =
        (
            $postSectionId !== false &&
            $postSectionId !== null &&
            $postSectionId > 0
        )
            ? (int) $postSectionId
            : null;


    // =================================================
    // VALIDATE CLASS
    // =================================================

    if (
        !$postClassId ||
        $postClassId <= 0
    ) {

        $errors[] =
            'Please select a class.';
    }


    // =================================================
    // VALIDATE DATE
    // =================================================

    if (
        !is_string($postDate) ||
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $postDate
        )
    ) {

        $errors[] =
            'Please select a valid attendance date.';
    }


    if (
        is_string($postDate) &&
        $postDate > date('Y-m-d')
    ) {

        $errors[] =
            'Attendance date cannot be in the future.';
    }


    // =================================================
    // CHECK CLASS AUTHORIZATION
    // =================================================

    if (empty($errors)) {

        $classAuthorized = false;

        foreach ($classes as $class) {

            if (
                (int) $class['id']
                === (int) $postClassId
            ) {

                $classAuthorized = true;
                break;
            }
        }

        if (!$classAuthorized) {

            $errors[] =
                'You are not authorized to use the selected class.';
        }
    }


    // =================================================
    // GET CLASS SECTIONS
    // =================================================

    $postSections = [];

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name
            FROM sections
            WHERE class_id = :class_id
              AND status = 'active'
            ORDER BY name ASC
        ");

        $stmt->execute([
            ':class_id' => (int) $postClassId
        ]);

        $postSections =
            $stmt->fetchAll();
    }


    // =================================================
    // SECTION VALIDATION
    // =================================================

    if (empty($errors)) {

        $classHasSections =
            !empty($postSections);


        // Class has sections
        if (
            $classHasSections &&
            $postSectionId === null
        ) {

            $errors[] =
                'Please select a section for this class.';
        }


        // Class has no sections
        if (
            !$classHasSections &&
            $postSectionId !== null
        ) {

            $errors[] =
                'This class has no active sections. Attendance must be marked for the whole class.';
        }


        // Check section belongs to class
        if ($postSectionId !== null) {

            $validSection = false;

            foreach ($postSections as $section) {

                if (
                    (int) $section['id']
                    === $postSectionId
                ) {

                    $validSection = true;
                    break;
                }
            }

            if (!$validSection) {

                $errors[] =
                    'Selected section does not belong to the selected class.';
            }
        }
    }


    // =================================================
    // TEACHER AUTHORIZATION
    // =================================================

    if (
        empty($errors) &&
        $userRole === 'teacher'
    ) {

        // Whole class
        if ($postSectionId === null) {

            $stmt = $pdo->prepare("
                SELECT
                    id
                FROM teacher_classes
                WHERE teacher_id = :teacher_id
                  AND class_id = :class_id
                  AND section_id IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                ':teacher_id' =>
                    $teacherId,

                ':class_id' =>
                    (int) $postClassId
            ]);

        }

        // Specific section
        else {

            $stmt = $pdo->prepare("
                SELECT
                    id
                FROM teacher_classes
                WHERE teacher_id = :teacher_id
                  AND class_id = :class_id
                  AND section_id = :section_id
                LIMIT 1
            ");

            $stmt->execute([
                ':teacher_id' =>
                    $teacherId,

                ':class_id' =>
                    (int) $postClassId,

                ':section_id' =>
                    $postSectionId
            ]);
        }


        if (!$stmt->fetch()) {

            if ($postSectionId === null) {

                $errors[] =
                    'You are not authorized to mark attendance for the whole selected class.';

            } else {

                $errors[] =
                    'You are not authorized to mark attendance for this class and section.';
            }
        }
    }


    // =================================================
    // GET STUDENTS FOR POSTED CLASS / SECTION
    // =================================================

    $postStudents = [];

    if (empty($errors)) {

        $postStudents =
            $attendanceObject->getStudentsForAttendance(
                (int) $postClassId,
                $postSectionId
            );


        if (empty($postStudents)) {

            if ($postSectionId === null) {

                $errors[] =
                    'No active students were found in the selected class.';

            } else {

                $errors[] =
                    'No active students were found in this class and section.';
            }
        }
    }


    // =================================================
    // ALLOWED STATUS
    // =================================================

    $allowedStatuses = [
        'present',
        'absent',
        'late',
        'leave'
    ];

    $cleanAttendance = [];


    // =================================================
    // VALIDATE ATTENDANCE DATA
    // =================================================

    if (empty($errors)) {

        if (!is_array($attendanceData)) {

            $errors[] =
                'Invalid attendance data.';

        } else {

            foreach ($postStudents as $student) {

                $studentId =
                    (int) $student['id'];


                if (
                    !isset(
                        $attendanceData[$studentId]
                    )
                ) {

                    $errors[] =
                        'Attendance status is missing for student: '
                        . htmlspecialchars(
                            $student['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        );

                    continue;
                }


                $status =
                    $attendanceData[$studentId];


                if (!is_string($status)) {

                    $errors[] =
                        'Invalid attendance status.';

                    continue;
                }


                if (
                    !in_array(
                        $status,
                        $allowedStatuses,
                        true
                    )
                ) {

                    $errors[] =
                        'Invalid attendance status selected.';

                    continue;
                }


                $cleanAttendance[$studentId] =
                    $status;
            }
        }
    }


    // =================================================
    // SAVE ATTENDANCE
    // =================================================

    if (empty($errors)) {

        try {

            $markedBy =
                $userRole === 'teacher'
                    ? $teacherId
                    : null;


            $saved =
                $attendanceObject->saveAttendanceBatch(
                    (int) $postClassId,
                    $postSectionId,
                    null,
                    $postDate,
                    $cleanAttendance,
                    $markedBy
                );


            if ($saved) {

                $_SESSION['attendance_success'] =
                    'Attendance saved successfully for '
                    . count($cleanAttendance)
                    . ' students.';


                // =====================================
                // REDIRECT
                // =====================================

                $redirectUrl =
                    'mark.php'
                    . '?class_id='
                    . (int) $postClassId
                    . '&date='
                    . urlencode($postDate);


                if ($postSectionId !== null) {

                    $redirectUrl .=
                        '&section_id='
                        . $postSectionId;
                }


                header(
                    'Location: '
                    . $redirectUrl
                );

                exit;
            }


            $errors[] =
                'Attendance could not be saved. Please try again.';

        } catch (Throwable $e) {

            $errors[] =
                $e->getMessage();
        }
    }


    // =================================================
    // KEEP FORM DATA
    // =================================================

    $selectedClassId =
        (int) $postClassId;

    $selectedSectionId =
        $postSectionId ?? 0;

    $selectedDate =
        is_string($postDate)
            ? $postDate
            : date('Y-m-d');

    $students =
        $postStudents;
}


// =====================================================
// SUCCESS MESSAGE
// =====================================================

if (
    isset(
        $_SESSION['attendance_success']
    )
) {

    $success =
        $_SESSION['attendance_success'];

    unset(
        $_SESSION['attendance_success']
    );
}


// =====================================================
// SELECTED CLASS NAME
// =====================================================

$selectedClassName = '';

foreach ($classes as $class) {

    if (
        (int) $class['id']
        === $selectedClassId
    ) {

        $selectedClassName =
            $class['name'];

        break;
    }
}


// =====================================================
// SELECTED SECTION NAME
// =====================================================

$selectedSectionName = '';

foreach ($sections as $section) {

    if (
        (int) $section['id']
        === $selectedSectionId
    ) {

        $selectedSectionName =
            $section['name'];

        break;
    }
}


// =====================================================
// WHOLE CLASS
// =====================================================

$isWholeClass =
    $selectedClassId > 0 &&
    empty($sections);


// =====================================================
// STUDENT COUNT
// =====================================================

$studentCount =
    count($students);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mark Attendance</title>


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
            color: #222;
        }

        .container {
            width: 95%;
            max-width: 1200px;
            margin: 30px auto;
        }

        .header {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow:
                0 2px 10px rgba(0,0,0,0.08);
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .header p {
            margin: 0;
            color: #666;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow:
                0 2px 10px rgba(0,0,0,0.08);
        }

        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 8px;
        }

        select,
        input[type="date"] {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 15px;
            background: #fff;
        }

        .btn {
            display: inline-block;
            border: none;
            padding: 12px 22px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            text-decoration: none;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-success {
            background: #16a34a;
            color: #fff;
        }

        .btn-success:hover {
            background: #15803d;
        }

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .error ul {
            margin: 0;
            padding-left: 20px;
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .attendance-info {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            background: #e5e7eb;
            font-size: 14px;
            font-weight: bold;
        }

        .badge-whole {
            background: #dbeafe;
            color: #1e40af;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f8fafc;
            font-weight: bold;
        }

        tr:hover {
            background: #f9fafb;
        }

        .student-id {
            font-weight: bold;
            color: #555;
        }

        .status-select {
            min-width: 130px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
        }

        @media (max-width: 800px) {

            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                width: 92%;
            }

            .header h1 {
                font-size: 23px;
            }
        }

    </style>

</head>


<body>


<div class="container">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="header">

        <a
            href="index.php"
            class="back-link"
        >
            ← Attendance
        </a>

        <h1>
            Mark Attendance
        </h1>

        <p>
            Select class, section and date,
            then mark student attendance.
        </p>

    </div>


    <!-- =================================================
         SUCCESS
    ================================================== -->

    <?php if ($success): ?>

        <div class="message success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         ERRORS
    ================================================== -->

    <?php if (!empty($errors)): ?>

        <div class="message error">

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


    <!-- =================================================
         SELECTION FORM
    ================================================== -->

    <div class="card">

        <h2>
            Attendance Selection
        </h2>


        <form
            method="GET"
            action="mark.php"
        >

            <div class="form-grid">


                <!-- CLASS -->

                <div class="form-group">

                    <label for="class_id">
                        Class
                    </label>

                    <select
                        name="class_id"
                        id="class_id"
                        required
                        onchange="this.form.submit()"
                    >

                        <option value="">
                            Select Class
                        </option>

                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= (int) $class['id'] ?>"
                                <?= (
                                    $selectedClassId
                                    === (int) $class['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $class['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- SECTION -->

                <div class="form-group">

                    <label for="section_id">
                        Section
                    </label>

                    <?php if ($selectedClassId > 0 && !empty($sections)): ?>

                        <select
                            name="section_id"
                            id="section_id"
                            onchange="this.form.submit()"
                        >

                            <option value="">
                                Select Section
                            </option>

                            <?php foreach ($sections as $section): ?>

                                <option
                                    value="<?= (int) $section['id'] ?>"
                                    <?= (
                                        $selectedSectionId
                                        === (int) $section['id']
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>
                                >

                                    <?= htmlspecialchars(
                                        $section['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    <?php elseif ($selectedClassId > 0): ?>

                        <select
                            name="section_id"
                            id="section_id"
                            disabled
                        >

                            <option value="">
                                Whole Class — No Section
                            </option>

                        </select>

                    <?php else: ?>

                        <select
                            id="section_id"
                            disabled
                        >

                            <option value="">
                                Select class first
                            </option>

                        </select>

                    <?php endif; ?>

                </div>


                <!-- DATE -->

                <div class="form-group">

                    <label for="date">
                        Date
                    </label>

                    <input
                        type="date"
                        name="date"
                        id="date"
                        value="<?= htmlspecialchars(
                            $selectedDate,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>

            </div>


            <div style="margin-top:20px;">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Load Students
                </button>

            </div>

        </form>

    </div>


    <!-- =================================================
         ATTENDANCE FORM
    ================================================== -->

    <?php if ($selectedClassId > 0): ?>

        <div class="card">


            <div class="attendance-header">

                <div>

                    <h2>
                        Student Attendance
                    </h2>

                    <div class="attendance-info">

                        <span class="badge">

                            Class:
                            <?= htmlspecialchars(
                                $selectedClassName,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <?php if ($isWholeClass): ?>

                            <span class="badge badge-whole">
                                Whole Class — No Section
                            </span>

                        <?php elseif ($selectedSectionName): ?>

                            <span class="badge">

                                Section:
                                <?= htmlspecialchars(
                                    $selectedSectionName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        <?php endif; ?>


                        <span class="badge">

                            Date:
                            <?= htmlspecialchars(
                                $selectedDate,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <span class="badge">

                            Students:
                            <?= $studentCount ?>

                        </span>

                    </div>

                </div>

            </div>


            <?php if (!empty($students)): ?>


                <form
                    method="POST"
                    action="mark.php"
                >


                    <!-- CSRF -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $csrfToken,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >


                    <!-- CLASS -->

                    <input
                        type="hidden"
                        name="class_id"
                        value="<?= $selectedClassId ?>"
                    >


                    <!-- SECTION -->

                    <?php if ($selectedSectionId > 0): ?>

                        <input
                            type="hidden"
                            name="section_id"
                            value="<?= $selectedSectionId ?>"
                        >

                    <?php else: ?>

                        <input
                            type="hidden"
                            name="section_id"
                            value=""
                        >

                    <?php endif; ?>


                    <!-- DATE -->

                    <input
                        type="hidden"
                        name="attendance_date"
                        value="<?= htmlspecialchars(
                            $selectedDate,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >


                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Student ID
                                    </th>

                                    <th>
                                        Student Name
                                    </th>

                                    <th>
                                        Father Name
                                    </th>

                                    <th>
                                        Attendance
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($students as $index => $student): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td class="student-id">

                                            <?= htmlspecialchars(
                                                $student['student_id'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>

                                        <td>

                                            <?= htmlspecialchars(
                                                $student['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>

                                        <td>

                                            <?= htmlspecialchars(
                                                $student['father_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </td>

                                        <td>

                                            <select
                                                name="attendance[<?= (int) $student['id'] ?>]"
                                                class="status-select"
                                                required
                                            >

                                                <option value="present">
                                                    Present
                                                </option>

                                                <option value="absent">
                                                    Absent
                                                </option>

                                                <option value="late">
                                                    Late
                                                </option>

                                                <option value="leave">
                                                    Leave
                                                </option>

                                            </select>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <!-- SAVE -->

                    <div class="actions">

                        <button
                            type="submit"
                            class="btn btn-success"
                        >
                            Save Attendance
                        </button>

                    </div>

                </form>


            <?php else: ?>


                <div class="empty">

                    No active students found
                    for this class/section.

                </div>


            <?php endif; ?>


        </div>

    <?php endif; ?>


</div>


</body>

</html>