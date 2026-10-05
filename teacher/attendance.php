<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Attendance.php';

requireTeacher();

$pdo = db();

/*
|--------------------------------------------------------------------------
| CURRENT TEACHER
|--------------------------------------------------------------------------
*/

$teacher = current_teacher($pdo);

if (!$teacher) {

    layout_start('Mark Attendance', 'attendance');

    echo '
        <div class="alert alert-warning">
            Your teacher profile is not linked to this account yet.
            Please contact an administrator.
        </div>
    ';

    layout_end();
    exit;
}

$teacherId = (int) $teacher['id'];

/*
|--------------------------------------------------------------------------
| ATTENDANCE OBJECT
|--------------------------------------------------------------------------
*/

$attendance = new Attendance($pdo);

/*
|--------------------------------------------------------------------------
| PAGE VARIABLES
|--------------------------------------------------------------------------
*/

$errors = [];
$success = '';

$selectedClassId = isset($_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;

$selectedSectionId =
    isset($_GET['section_id']) &&
    $_GET['section_id'] !== ''
        ? (int) $_GET['section_id']
        : null;

$selectedSubjectId =
    isset($_GET['subject_id']) &&
    $_GET['subject_id'] !== ''
        ? (int) $_GET['subject_id']
        : null;

$selectedDate =
    isset($_GET['date']) &&
    $_GET['date'] !== ''
        ? trim($_GET['date'])
        : date('Y-m-d');


/*
|--------------------------------------------------------------------------
| GET TEACHER ASSIGNMENTS
|--------------------------------------------------------------------------
|
| section_id can be NULL.
|
| NULL = Whole Class
| value = Specific Section
|
*/

$assignmentSql = "
    SELECT
        tc.id,
        tc.class_id,
        tc.section_id,
        tc.subject_id,

        c.name AS class_name,

        sec.name AS section_name,

        sub.name AS subject_name,
        sub.code AS subject_code

    FROM teacher_classes AS tc

    INNER JOIN classes AS c
        ON tc.class_id = c.id

    LEFT JOIN sections AS sec
        ON tc.section_id = sec.id

    INNER JOIN subjects AS sub
        ON tc.subject_id = sub.id

    WHERE tc.teacher_id = :teacher_id

    ORDER BY
        c.name ASC,
        CASE
            WHEN tc.section_id IS NULL THEN 0
            ELSE 1
        END ASC,
        sec.name ASC,
        sub.name ASC
";

$assignmentStmt = $pdo->prepare($assignmentSql);

$assignmentStmt->execute([
    ':teacher_id' => $teacherId
]);

$teacherAssignments = $assignmentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| POST - SAVE ATTENDANCE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $postClassId =
        isset($_POST['class_id'])
            ? (int) $_POST['class_id']
            : 0;

    $postSectionId =
        isset($_POST['section_id']) &&
        $_POST['section_id'] !== ''
            ? (int) $_POST['section_id']
            : null;

    $postSubjectId =
        isset($_POST['subject_id']) &&
        $_POST['subject_id'] !== ''
            ? (int) $_POST['subject_id']
            : null;

    $postDate =
        isset($_POST['date'])
            ? trim($_POST['date'])
            : '';

    $students =
        isset($_POST['students']) &&
        is_array($_POST['students'])
            ? $_POST['students']
            : [];


    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($postClassId <= 0) {
        $errors[] = 'Please select a class.';
    }

    if ($postSubjectId === null || $postSubjectId <= 0) {
        $errors[] = 'Please select a subject.';
    }

    if ($postDate === '') {
        $errors[] = 'Please select an attendance date.';
    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($postDate !== '') {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $postDate
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $postDate
        ) {
            $errors[] = 'Invalid attendance date.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY TEACHER ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    $validAssignment = false;

    if (
        $postClassId > 0 &&
        $postSubjectId !== null &&
        $postSubjectId > 0
    ) {

        /*
        |--------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------
        |
        | If teacher has a specific section assignment:
        |
        |   section_id = selected section
        |
        | If teacher has Whole Class assignment:
        |
        |   section_id IS NULL
        |
        */

        $assignmentCheckSql = "
            SELECT id
            FROM teacher_classes
            WHERE teacher_id = :teacher_id
              AND class_id = :class_id
              AND subject_id = :subject_id
        ";

        $assignmentParams = [
            ':teacher_id' => $teacherId,
            ':class_id' => $postClassId,
            ':subject_id' => $postSubjectId
        ];

        if ($postSectionId === null) {

            $assignmentCheckSql .= "
                AND section_id IS NULL
            ";

        } else {

            $assignmentCheckSql .= "
                AND section_id = :section_id
            ";

            $assignmentParams[':section_id'] =
                $postSectionId;
        }

        $assignmentCheckSql .= "
            LIMIT 1
        ";

        $assignmentCheckStmt =
            $pdo->prepare($assignmentCheckSql);

        $assignmentCheckStmt->execute(
            $assignmentParams
        );

        $validAssignment =
            $assignmentCheckStmt->fetch() !== false;
    }


    if (!$validAssignment) {

        $errors[] =
            'You are not assigned to this class, section and subject.';
    }


    /*
    |--------------------------------------------------------------------------
    | STUDENTS VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($students)) {

        $errors[] =
            'No students were selected for attendance.';
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE ATTENDANCE STATUS VALUES
    |--------------------------------------------------------------------------
    */

    $allowedStatuses = [
        'present',
        'absent',
        'late',
        'leave'
    ];

    foreach ($students as $studentId => $status) {

        if (!in_array(
            (string) $status,
            $allowedStatuses,
            true
        )) {

            $errors[] =
                'Invalid attendance status detected.';
            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $attendance->saveAttendanceBatch(
                $postClassId,
                $postSectionId,
                $postSubjectId,
                $postDate,
                $students,
                $teacherId
            );

            $success =
                'Attendance has been saved successfully.';

            /*
            |--------------------------------------------------------------
            | RESET FORM
            |--------------------------------------------------------------
            */

            $selectedClassId = 0;
            $selectedSectionId = null;
            $selectedSubjectId = null;
            $selectedDate = date('Y-m-d');

        } catch (Throwable $e) {

            $errors[] = $e->getMessage();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESTORE SELECTED VALUES AFTER ERROR
    |--------------------------------------------------------------------------
    */

    if (!empty($errors)) {

        $selectedClassId = $postClassId;
        $selectedSectionId = $postSectionId;
        $selectedSubjectId = $postSubjectId;
        $selectedDate = $postDate;
    }
}


/*
|--------------------------------------------------------------------------
| LOAD SELECTED ASSIGNMENT
|--------------------------------------------------------------------------
*/

$selectedAssignment = null;

foreach ($teacherAssignments as $assignmentRow) {

    $rowClassId =
        (int) $assignmentRow['class_id'];

    $rowSubjectId =
        (int) $assignmentRow['subject_id'];

    $rowSectionId =
        $assignmentRow['section_id'] !== null
            ? (int) $assignmentRow['section_id']
            : null;

    if (
        $rowClassId === $selectedClassId &&
        $rowSubjectId === $selectedSubjectId &&
        $rowSectionId === $selectedSectionId
    ) {

        $selectedAssignment = $assignmentRow;
        break;
    }
}


/*
|--------------------------------------------------------------------------
| LOAD RESULT SETTINGS
|--------------------------------------------------------------------------
|
| These marks are NOT daily attendance marks.
|
| They are the maximum marks attendance contributes
| to the final result.
|
| Exact Section > Whole Class
|
*/

$attendanceTotal = null;

if ($selectedAssignment !== null) {

    $settingsSql = "
        SELECT *
        FROM result_settings
        WHERE teacher_id = :teacher_id
          AND class_id = :class_id
          AND subject_id = :subject_id
          AND (
                section_id = :section_id
                OR section_id IS NULL
              )
        ORDER BY
            CASE
                WHEN section_id = :exact_section
                THEN 0
                ELSE 1
            END ASC,

            CASE
                WHEN status = 'active'
                THEN 0
                ELSE 1
            END ASC,

            id DESC

        LIMIT 1
    ";

    $settingsStmt = $pdo->prepare($settingsSql);

    $settingsStmt->bindValue(
        ':teacher_id',
        $teacherId,
        PDO::PARAM_INT
    );

    $settingsStmt->bindValue(
        ':class_id',
        (int) $selectedAssignment['class_id'],
        PDO::PARAM_INT
    );

    $settingsStmt->bindValue(
        ':subject_id',
        (int) $selectedAssignment['subject_id'],
        PDO::PARAM_INT
    );

    if ($selectedAssignment['section_id'] === null) {

        $settingsStmt->bindValue(
            ':section_id',
            null,
            PDO::PARAM_INT
        );

        $settingsStmt->bindValue(
            ':exact_section',
            -1,
            PDO::PARAM_INT
        );

    } else {

        $sectionId =
            (int) $selectedAssignment['section_id'];

        $settingsStmt->bindValue(
            ':section_id',
            $sectionId,
            PDO::PARAM_INT
        );

        $settingsStmt->bindValue(
            ':exact_section',
            $sectionId,
            PDO::PARAM_INT
        );
    }

    $settingsStmt->execute();

    $resultSettings =
        $settingsStmt->fetch();

    if ($resultSettings !== false) {

        $attendanceTotal =
            (float) $resultSettings['attendance_total'];
    }
}


/*
|--------------------------------------------------------------------------
| LOAD STUDENTS
|--------------------------------------------------------------------------
*/

$studentsList = [];

if ($selectedAssignment !== null) {

    $studentsList =
        $attendance->getStudentsForAttendance(
            (int) $selectedAssignment['class_id'],
            $selectedAssignment['section_id'] !== null
                ? (int) $selectedAssignment['section_id']
                : null
        );
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING ATTENDANCE
|--------------------------------------------------------------------------
*/

$existingAttendance = [];

if (
    $selectedAssignment !== null &&
    $selectedDate !== ''
) {

    $existingSql = "
        SELECT
            student_id,
            status
        FROM attendance
        WHERE class_id = :class_id
          AND date = :date
          AND subject_id = :subject_id
    ";

    if ($selectedAssignment['section_id'] === null) {

        $existingSql .= "
            AND section_id IS NULL
        ";

    } else {

        $existingSql .= "
            AND section_id = :section_id
        ";
    }

    $existingStmt =
        $pdo->prepare($existingSql);

    $existingParams = [
        ':class_id' =>
            (int) $selectedAssignment['class_id'],

        ':date' =>
            $selectedDate,

        ':subject_id' =>
            (int) $selectedAssignment['subject_id']
    ];

    if ($selectedAssignment['section_id'] !== null) {

        $existingParams[':section_id'] =
            (int) $selectedAssignment['section_id'];
    }

    $existingStmt->execute(
        $existingParams
    );

    foreach (
        $existingStmt->fetchAll()
        as $existingRow
    ) {

        $existingAttendance[
            (int) $existingRow['student_id']
        ] =
            $existingRow['status'];
    }
}


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

layout_start(
    'Mark Attendance',
    'attendance'
);

?>

<div class="container-fluid">

    <!-- ============================================================
         PAGE HEADER
    ============================================================ -->

    <div class="d-flex flex-column flex-md-row
                justify-content-between
                align-items-md-center
                gap-3
                mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Mark Attendance
            </h2>

            <p class="text-muted mb-0">
                Record attendance for your assigned students.
            </p>

        </div>

        <div>

            <a
                href="<?= e(url('teacher/dashboard.php')) ?>"
                class="btn btn-outline-secondary"
            >
                ← Dashboard
            </a>

        </div>

    </div>


    <!-- ============================================================
         ALERTS
    ============================================================ -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-danger">

            <strong>
                Please fix the following:
            </strong>

            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <?php if ($success !== ''): ?>

        <div class="alert alert-success">
            <?= e($success) ?>
        </div>

    <?php endif; ?>


    <!-- ============================================================
         NO ASSIGNMENTS
    ============================================================ -->

    <?php if (empty($teacherAssignments)): ?>

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center py-5">

                <div class="fs-1 mb-3">
                    📚
                </div>

                <h4 class="fw-bold">
                    No Teaching Assignment Found
                </h4>

                <p class="text-muted mb-0">
                    You have not been assigned any class,
                    section and subject yet.
                </p>

                <p class="text-muted">
                    Please contact the administrator.
                </p>

            </div>

        </div>

    <?php else: ?>


        <!-- ========================================================
             SELECT CLASS / SECTION / SUBJECT / DATE
        ======================================================== -->

        <div class="card border-0 shadow-sm mb-4">

            <div class="card-body p-4">

                <h5 class="fw-bold mb-3">
                    Attendance Details
                </h5>

                <form
                    method="GET"
                    action="<?= e(url('teacher/attendance.php')) ?>"
                >

                    <div class="row g-3">


                        <!-- CLASS -->

                        <div class="col-md-6 col-lg-3">

                            <label class="form-label fw-semibold">
                                Class
                            </label>

                            <select
                                name="class_id"
                                class="form-select"
                                required
                                onchange="this.form.submit()"
                            >

                                <option value="">
                                    Select Class
                                </option>

                                <?php

                                $uniqueClasses = [];

                                foreach (
                                    $teacherAssignments
                                    as $assignmentRow
                                ) {

                                    $classId =
                                        (int) $assignmentRow['class_id'];

                                    $uniqueClasses[$classId] =
                                        $assignmentRow['class_name'];
                                }

                                ?>

                                <?php foreach (
                                    $uniqueClasses
                                    as $classId => $className
                                ): ?>

                                    <option
                                        value="<?= $classId ?>"
                                        <?= $selectedClassId === $classId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e($className) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SECTION -->

                        <div class="col-md-6 col-lg-3">

                            <label class="form-label fw-semibold">
                                Section
                            </label>

                            <select
                                name="section_id"
                                class="form-select"
                                onchange="this.form.submit()"
                            >

                                <option value="">
                                    Select Section
                                </option>

                                <?php

                                $hasWholeClass = false;
                                $uniqueSections = [];

                                foreach (
                                    $teacherAssignments
                                    as $assignmentRow
                                ) {

                                    if (
                                        (int) $assignmentRow['class_id']
                                        !== $selectedClassId
                                    ) {
                                        continue;
                                    }

                                    if (
                                        $assignmentRow['section_id']
                                        === null
                                    ) {

                                        $hasWholeClass = true;
                                        continue;
                                    }

                                    $sectionId =
                                        (int) $assignmentRow['section_id'];

                                    $uniqueSections[$sectionId] =
                                        $assignmentRow['section_name'];
                                }

                                ?>

                                <?php if ($hasWholeClass): ?>

                                    <option value="">
                                        Whole Class
                                    </option>

                                <?php endif; ?>

                                <?php foreach (
                                    $uniqueSections
                                    as $sectionId => $sectionName
                                ): ?>

                                    <option
                                        value="<?= $sectionId ?>"
                                        <?= $selectedSectionId === $sectionId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e($sectionName) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <?php if ($hasWholeClass): ?>

                                <div class="form-text">
                                    Whole Class means the teacher is assigned
                                    to all sections of this class.
                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- SUBJECT -->

                        <div class="col-md-6 col-lg-3">

                            <label class="form-label fw-semibold">
                                Subject
                            </label>

                            <select
                                name="subject_id"
                                class="form-select"
                                onchange="this.form.submit()"
                            >

                                <option value="">
                                    Select Subject
                                </option>

                                <?php

                                $shownSubjects = [];

                                foreach (
                                    $teacherAssignments
                                    as $assignmentRow
                                ) {

                                    if (
                                        (int) $assignmentRow['class_id']
                                        !== $selectedClassId
                                    ) {
                                        continue;
                                    }

                                    $rowSectionId =
                                        $assignmentRow['section_id'] !== null
                                            ? (int) $assignmentRow['section_id']
                                            : null;

                                    if (
                                        $rowSectionId !==
                                        $selectedSectionId
                                    ) {
                                        continue;
                                    }

                                    $subjectId =
                                        (int) $assignmentRow['subject_id'];

                                    if (
                                        isset($shownSubjects[$subjectId])
                                    ) {
                                        continue;
                                    }

                                    $shownSubjects[$subjectId] = true;

                                    ?>

                                    <option
                                        value="<?= $subjectId ?>"
                                        <?= $selectedSubjectId === $subjectId
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e(
                                            $assignmentRow['subject_name']
                                        ) ?>

                                        <?php if (
                                            !empty(
                                                $assignmentRow['subject_code']
                                            )
                                        ): ?>

                                            (
                                            <?= e(
                                                $assignmentRow['subject_code']
                                            ) ?>
                                            )

                                        <?php endif; ?>

                                    </option>

                                <?php } ?>

                            </select>

                        </div>


                        <!-- DATE -->

                        <div class="col-md-6 col-lg-3">

                            <label class="form-label fw-semibold">
                                Date
                            </label>

                            <input
                                type="date"
                                name="date"
                                value="<?= e($selectedDate) ?>"
                                class="form-control"
                                onchange="this.form.submit()"
                                required
                            >

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- ========================================================
             SELECTED ASSIGNMENT
        ======================================================== -->

        <?php if ($selectedAssignment !== null): ?>

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-body">

                    <div class="row g-3">


                        <div class="col-md-3">

                            <div class="text-muted small">
                                Class
                            </div>

                            <div class="fw-bold">
                                <?= e(
                                    $selectedAssignment['class_name']
                                ) ?>
                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="text-muted small">
                                Section
                            </div>

                            <div class="fw-bold">

                                <?=
                                    $selectedAssignment['section_id']
                                    === null
                                        ? 'Whole Class'
                                        : e(
                                            $selectedAssignment[
                                                'section_name'
                                            ]
                                        )
                                ?>

                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="text-muted small">
                                Subject
                            </div>

                            <div class="fw-bold">
                                <?= e(
                                    $selectedAssignment['subject_name']
                                ) ?>
                            </div>

                        </div>


                        <div class="col-md-3">

                            <div class="text-muted small">
                                Result Attendance Total
                            </div>

                            <div class="fw-bold">

                                <?php if ($attendanceTotal !== null): ?>

                                    <?= e(
                                        rtrim(
                                            rtrim(
                                                number_format(
                                                    $attendanceTotal,
                                                    2,
                                                    '.',
                                                    ''
                                                ),
                                                '0'
                                            ),
                                            '.'
                                        )
                                    ) ?>

                                    marks

                                <?php else: ?>

                                    <span class="text-warning">
                                        Not configured
                                    </span>

                                <?php endif; ?>

                            </div>

                            <div class="small text-muted">
                                This is the maximum attendance contribution
                                in the final result.
                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- ====================================================
                 RESULT SETTINGS NOTICE
            ==================================================== -->

            <?php if ($attendanceTotal === null): ?>

                <div class="alert alert-warning">

                    <strong>
                        Attendance result setting is not configured.
                    </strong>

                    <br>

                    Daily attendance can still be recorded, but this
                    subject will not receive an attendance contribution
                    in the final result until the teacher configures
                    <strong>Attendance Total</strong> in Result Settings.

                    <div class="mt-3">

                        <a
                            href="<?= e(
                                url('teacher/result-settings.php')
                            ) ?>"
                            class="btn btn-sm btn-warning"
                        >
                            Configure Result Settings
                        </a>

                    </div>

                </div>

            <?php else: ?>

                <div class="alert alert-info">

                    <strong>
                        Result calculation:
                    </strong>

                    Student attendance percentage will automatically be
                    converted to the configured
                    <strong>
                        <?= e(
                            rtrim(
                                rtrim(
                                    number_format(
                                        $attendanceTotal,
                                        2,
                                        '.',
                                        ''
                                    ),
                                    '0'
                                ),
                                '.'
                            )
                        ) ?>
                        marks
                    </strong>
                    in the final result.

                </div>

            <?php endif; ?>


            <!-- ====================================================
                 EXISTING ATTENDANCE WARNING
            ==================================================== -->

            <?php if (!empty($existingAttendance)): ?>

                <div class="alert alert-warning">

                    <strong>
                        Attendance already exists for this date.
                    </strong>

                    <br>

                    Attendance for this class, section and subject
                    has already been recorded for
                    <strong><?= e($selectedDate) ?></strong>.

                </div>

            <?php endif; ?>


            <!-- ====================================================
                 STUDENTS
            ==================================================== -->

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <div class="d-flex flex-column flex-md-row
                                justify-content-between
                                align-items-md-center
                                gap-2
                                mb-4">

                        <div>

                            <h5 class="fw-bold mb-1">
                                Student Attendance
                            </h5>

                            <div class="text-muted small">
                                Date:
                                <?= e($selectedDate) ?>
                            </div>

                        </div>

                        <div>

                            <span class="badge bg-primary">
                                <?= count($studentsList) ?>
                                Students
                            </span>

                        </div>

                    </div>


                    <?php if (empty($studentsList)): ?>

                        <div class="text-center py-5">

                            <div class="fs-1 mb-3">
                                👨‍🎓
                            </div>

                            <h5 class="fw-bold">
                                No Active Students Found
                            </h5>

                            <p class="text-muted mb-0">
                                There are no active students in this
                                class and section.
                            </p>

                        </div>

                    <?php else: ?>


                        <?php if (empty($existingAttendance)): ?>

                            <form
                                method="POST"
                                action="<?= e(
                                    url('teacher/attendance.php')
                                ) ?>"
                            >
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="class_id"
                                    value="<?= (int)
                                        $selectedAssignment['class_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="section_id"
                                    value="<?= 
                                        $selectedAssignment['section_id']
                                        !== null
                                            ? (int)
                                                $selectedAssignment[
                                                    'section_id'
                                                ]
                                            : ''
                                    ?>"
                                >

                                <input
                                    type="hidden"
                                    name="subject_id"
                                    value="<?= (int)
                                        $selectedAssignment['subject_id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="date"
                                    value="<?= e($selectedDate) ?>"
                                >


                                <div class="table-responsive">

                                    <table class="table align-middle">

                                        <thead>

                                            <tr>

                                                <th style="width: 60px;">
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

                                                <th style="width: 220px;">
                                                    Attendance
                                                </th>

                                            </tr>

                                        </thead>


                                        <tbody>

                                            <?php foreach (
                                                $studentsList
                                                as $index => $student
                                            ): ?>

                                                <tr>

                                                    <td>
                                                        <?= $index + 1 ?>
                                                    </td>

                                                    <td>

                                                        <span
                                                            class="fw-semibold"
                                                        >
                                                            <?= e(
                                                                $student[
                                                                    'student_id'
                                                                ]
                                                            ) ?>
                                                        </span>

                                                    </td>

                                                    <td>
                                                        <?= e(
                                                            $student['name']
                                                        ) ?>
                                                    </td>

                                                    <td>
                                                        <?= e(
                                                            $student[
                                                                'father_name'
                                                            ]
                                                        ) ?>
                                                    </td>

                                                    <td>

                                                        <select
                                                            name="students[<?= (int)
                                                                $student['id'] ?>]"
                                                            class="form-select"
                                                            required
                                                        >

                                                            <option
                                                                value="present"
                                                                selected
                                                            >
                                                                Present
                                                            </option>

                                                            <option
                                                                value="absent"
                                                            >
                                                                Absent
                                                            </option>

                                                            <option
                                                                value="late"
                                                            >
                                                                Late
                                                            </option>

                                                            <option
                                                                value="leave"
                                                            >
                                                                Leave
                                                            </option>

                                                        </select>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>


                                <div class="d-flex
                                            justify-content-end
                                            gap-2
                                            mt-4">

                                    <a
                                        href="<?= e(
                                            url(
                                                'teacher/attendance.php'
                                            )
                                        ) ?>"
                                        class="btn btn-outline-secondary"
                                    >
                                        Reset
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn btn-primary px-4"
                                    >
                                        Save Attendance
                                    </button>

                                </div>

                            </form>

                        <?php else: ?>

                            <!-- EXISTING ATTENDANCE TABLE -->

                            <div class="table-responsive">

                                <table class="table align-middle">

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
                                                Status
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>

                                        <?php foreach (
                                            $studentsList
                                            as $index => $student
                                        ): ?>

                                            <?php

                                            $studentId =
                                                (int) $student['id'];

                                            $studentStatus =
                                                $existingAttendance[
                                                    $studentId
                                                ] ?? 'Not Marked';

                                            ?>

                                            <tr>

                                                <td>
                                                    <?= $index + 1 ?>
                                                </td>

                                                <td>
                                                    <?= e(
                                                        $student[
                                                            'student_id'
                                                        ]
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= e(
                                                        $student['name']
                                                    ) ?>
                                                </td>

                                                <td>

                                                    <?php if (
                                                        $studentStatus ===
                                                        'present'
                                                    ): ?>

                                                        <span
                                                            class="badge bg-success"
                                                        >
                                                            Present
                                                        </span>

                                                    <?php elseif (
                                                        $studentStatus ===
                                                        'absent'
                                                    ): ?>

                                                        <span
                                                            class="badge bg-danger"
                                                        >
                                                            Absent
                                                        </span>

                                                    <?php elseif (
                                                        $studentStatus ===
                                                        'late'
                                                    ): ?>

                                                        <span
                                                            class="badge bg-warning text-dark"
                                                        >
                                                            Late
                                                        </span>

                                                    <?php elseif (
                                                        $studentStatus ===
                                                        'leave'
                                                    ): ?>

                                                        <span
                                                            class="badge bg-info text-dark"
                                                        >
                                                            Leave
                                                        </span>

                                                    <?php else: ?>

                                                        <span
                                                            class="badge bg-secondary"
                                                        >
                                                            <?= e(
                                                                $studentStatus
                                                            ) ?>
                                                        </span>

                                                    <?php endif; ?>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>

            </div>

        <?php else: ?>

            <!-- ====================================================
                 SELECT ASSIGNMENT MESSAGE
            ==================================================== -->

            <div class="card border-0 shadow-sm">

                <div class="card-body text-center py-5">

                    <div class="fs-1 mb-3">
                        📋
                    </div>

                    <h5 class="fw-bold">
                        Select Your Class, Section and Subject
                    </h5>

                    <p class="text-muted mb-0">
                        Select an assigned class, section and subject
                        above to load students.
                    </p>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

<?php

layout_end();