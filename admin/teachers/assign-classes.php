<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';
require_once __DIR__ . '/../../classes/ClassRoom.php';
require_once __DIR__ . '/../../classes/Section.php';
require_once __DIR__ . '/../../classes/Subject.php';
require_once __DIR__ . '/../../classes/TeacherClass.php';

// =====================================================
// DATABASE
// =====================================================

$pdo = db();
$database = null;


// =====================================================
// OBJECTS
// =====================================================

$teacherObject = new Teacher($pdo);
$classObject = new ClassRoom($pdo);
$sectionObject = new Section($pdo);
$subjectObject = new Subject($pdo);
$teacherClassObject = new TeacherClass($pdo);


// =====================================================
// GET TEACHER ID
// =====================================================

$teacherId = filter_input(
    INPUT_GET,
    'teacher_id',
    FILTER_VALIDATE_INT
);

if (!$teacherId || $teacherId <= 0) {
    die("Invalid teacher ID.");
}


// =====================================================
// GET TEACHER
// =====================================================

$teacher = $teacherObject->getTeacherById($teacherId);

if (!$teacher) {
    die("Teacher not found.");
}


// =====================================================
// VARIABLES
// =====================================================

$error = '';
$success = '';


// =====================================================
// FORM VALUES
// =====================================================

$selectedClassId = 0;
$selectedSectionId = null;
$selectedSubjectId = 0;


// =====================================================
// HANDLE FORM SUBMISSION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    // -------------------------------------------------
    // CLASS
    // -------------------------------------------------

    $classId = filter_input(
        INPUT_POST,
        'class_id',
        FILTER_VALIDATE_INT
    );

    if ($classId === false || $classId === null) {
        $classId = 0;
    }


    // -------------------------------------------------
    // SECTION
    // -------------------------------------------------

    $sectionId = filter_input(
        INPUT_POST,
        'section_id',
        FILTER_VALIDATE_INT
    );

    /*
     * Empty section means:
     *
     * Whole Class / No Section
     *
     * This is only allowed when the selected
     * class has NO active sections.
     */

    if ($sectionId === false || $sectionId === null) {
        $sectionId = null;
    }


    // -------------------------------------------------
    // SUBJECT
    // -------------------------------------------------

    $subjectId = filter_input(
        INPUT_POST,
        'subject_id',
        FILTER_VALIDATE_INT
    );

    if ($subjectId === false || $subjectId === null) {
        $subjectId = 0;
    }


    // -------------------------------------------------
    // KEEP FORM VALUES
    // -------------------------------------------------

    $selectedClassId = $classId;
    $selectedSectionId = $sectionId;
    $selectedSubjectId = $subjectId;


    // =================================================
    // BASIC VALIDATION
    // =================================================

    if ($classId <= 0) {

        $error = "Please select a class.";

    } elseif ($subjectId <= 0) {

        $error = "Please select a subject.";

    } else {

        // =================================================
        // GET SELECTED CLASS
        // =================================================

        $selectedClass = $classObject->getClassById($classId);


        // =================================================
        // GET SELECTED SUBJECT
        // =================================================

        $selectedSubject =
            $subjectObject->getSubjectById($subjectId);


        // =================================================
        // GET SECTIONS FOR SELECTED CLASS
        // =================================================

        /*
         * We intentionally get sections directly from DB
         * for the selected class.
         *
         * This makes the server-side validation independent
         * from JavaScript.
         */

        $sectionStmt = $pdo->prepare(
            "
            SELECT
                id,
                class_id,
                name,
                status
            FROM sections
            WHERE class_id = :class_id
              AND status = 'active'
            ORDER BY name ASC
            "
        );

        $sectionStmt->execute([
            ':class_id' => $classId
        ]);

        $classSections = $sectionStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


        // =================================================
        // VALIDATE CLASS
        // =================================================

        if (!$selectedClass) {

            $error = "Selected class does not exist.";

        } elseif (
            ($selectedClass['status'] ?? '') !== 'active'
        ) {

            $error = "Selected class is inactive.";

        }


        // =================================================
        // VALIDATE SUBJECT
        // =================================================

        elseif (!$selectedSubject) {

            $error = "Selected subject does not exist.";

        } elseif (
            ($selectedSubject['status'] ?? '') !== 'active'
        ) {

            $error = "Selected subject is inactive.";

        }


        // =================================================
        // SECTION RULE
        // =================================================

        else {

            $sectionCount = count($classSections);


            /*
             * CASE 1:
             *
             * Class HAS sections.
             *
             * Therefore section is compulsory.
             */

            if ($sectionCount > 0) {

                if ($sectionId === null) {

                    $error =
                        "This class has sections. "
                        . "Please select a section.";

                } else {

                    /*
                     * Check that submitted section
                     * actually belongs to selected class.
                     */

                    $validSection = false;

                    foreach ($classSections as $classSection) {

                        if (
                            (int) $classSection['id']
                            === $sectionId
                        ) {

                            $validSection = true;

                            break;
                        }
                    }


                    if (!$validSection) {

                        $error =
                            "The selected section does not "
                            . "belong to this class.";

                    }
                }


            /*
             * CASE 2:
             *
             * Class has NO sections.
             *
             * Therefore NULL / Whole Class is required.
             */

            } else {

                if ($sectionId !== null) {

                    $error =
                        "This class has no sections. "
                        . "The assignment must apply to "
                        . "the whole class.";

                } else {

                    /*
                     * Keep NULL intentionally.
                     */
                    $sectionId = null;

                    $selectedSectionId = null;
                }
            }


            // =================================================
            // ASSIGNMENT
            // =================================================

            if ($error === '') {

                // =================================================
                // CHECK DUPLICATE
                // =================================================

                $alreadyAssigned =
                    $teacherClassObject->assignmentExists(
                        $teacherId,
                        $classId,
                        $sectionId,
                        $subjectId
                    );


                if ($alreadyAssigned) {

                    if ($sectionId === null) {

                        $error =
                            "This teacher is already assigned "
                            . "to this subject for the whole class.";

                    } else {

                        $error =
                            "This teacher is already assigned "
                            . "to this class, section and subject.";
                    }


                } else {

                    // =================================================
                    // ASSIGN
                    // =================================================

                    $assigned =
                        $teacherClassObject->assignClass(
                            $teacherId,
                            $classId,
                            $sectionId,
                            $subjectId
                        );


                    if ($assigned) {

                        header(
                            "Location: assign-classes.php?teacher_id="
                            . $teacherId
                            . "&success=1"
                        );

                        exit;

                    } else {

                        $error =
                            "Failed to assign class. "
                            . "Please try again.";
                    }
                }
            }
        }
    }
}


// =====================================================
// SUCCESS MESSAGE
// =====================================================

if (
    isset($_GET['success']) &&
    $_GET['success'] === '1'
) {

    $success =
        "Teacher has been successfully assigned "
        . "to the class and section.";
}


if (
    isset($_GET['removed']) &&
    $_GET['removed'] === '1'
) {

    $success =
        "Class assignment has been successfully removed.";
}


// =====================================================
// GET ACTIVE CLASSES
// =====================================================

$classes = $classObject->getClasses(
    '',
    'active',
    1000,
    0
);


// =====================================================
// GET ACTIVE SECTIONS
// =====================================================

/*
 * We need class_id here because JavaScript uses it
 * to dynamically filter the section dropdown.
 */

$sectionStmt = $pdo->prepare(
    "
    SELECT
        s.id,
        s.class_id,
        s.name,
        s.status,
        c.name AS class_name
    FROM sections AS s
    INNER JOIN classes AS c
        ON c.id = s.class_id
    WHERE s.status = 'active'
      AND c.status = 'active'
    ORDER BY
        s.class_id ASC,
        s.name ASC
    "
);

$sectionStmt->execute();

$sections = $sectionStmt->fetchAll(
    PDO::FETCH_ASSOC
);


// =====================================================
// GET ACTIVE SUBJECTS
// =====================================================

$subjects = $subjectObject->getSubjects(
    '',
    'active',
    1000,
    0
);


// =====================================================
// GET EXISTING ASSIGNMENTS
// =====================================================

$assignments =
    $teacherClassObject->getTeacherClasses(
        $teacherId
    );


// =====================================================
// HELPER
// =====================================================

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

    <title>
        Assign Classes - <?= e((string) $teacher['name']) ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 30px;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }


        .container {
            max-width: 1100px;
            margin: 0 auto;
        }


        /* =============================================
           HEADER
        ============================================== */

        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }


        .header h1 {
            margin: 0 0 8px;
            font-size: 26px;
        }


        .header p {
            margin: 0;
            color: #6b7280;
            line-height: 1.6;
        }


        .back-link {
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
        }


        /* =============================================
           CARDS
        ============================================== */

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }


        .card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 20px;
        }


        /* =============================================
           FORM
        ============================================== */

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }


        .form-group {
            display: flex;
            flex-direction: column;
        }


        label {
            margin-bottom: 8px;
            font-weight: 600;
        }


        label small {
            font-weight: normal;
            color: #6b7280;
        }


        .required {
            color: #dc2626;
        }


        select {
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: white;
            font-size: 15px;
            color: #111827;
        }


        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);
        }


        select:disabled {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }


        .help-text {
            margin-top: 7px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }


        .section-status {
            display: none;
            margin-top: 8px;
            padding: 9px 11px;
            border-radius: 7px;
            font-size: 12px;
            line-height: 1.5;
        }


        .section-status.info {
            display: block;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }


        .section-status.warning {
            display: block;
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }


        .button-area {
            margin-top: 20px;
        }


        .btn {
            display: inline-block;
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
        }


        .btn-primary {
            background: #2563eb;
            color: white;
        }


        .btn-primary:hover {
            background: #1d4ed8;
        }


        .btn-danger {
            background: #dc2626;
            color: white;
            font-size: 14px;
        }


        .btn-danger:hover {
            background: #b91c1c;
        }


        /* =============================================
           ALERTS
        ============================================== */

        .alert {
            padding: 13px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
            line-height: 1.5;
        }


        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }


        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }


        /* =============================================
           TABLE
        ============================================== */

        .table-wrapper {
            overflow-x: auto;
        }


        table {
            width: 100%;
            border-collapse: collapse;
        }


        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }


        th {
            background: #f9fafb;
            font-size: 14px;
        }


        td {
            font-size: 14px;
        }


        tbody tr:hover td {
            background: #f9fafb;
        }


        .empty {
            padding: 25px;
            text-align: center;
            color: #6b7280;
        }


        /* =============================================
           BADGES
        ============================================== */

        .whole-class {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 600;
        }


        .section-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 600;
        }


        .subject-badge {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #f5f3ff;
            color: #6d28d9;
            font-size: 12px;
            font-weight: 600;
        }


        /* =============================================
           RESPONSIVE
        ============================================== */

        @media (max-width: 800px) {

            .form-grid {
                grid-template-columns: 1fr;
            }


            body {
                padding: 15px;
            }


            .header h1 {
                font-size: 22px;
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

        <h1>
            Assign Classes
        </h1>

        <p>

            Assign a subject, class and section to

            <strong>
                <?= e((string) $teacher['name']) ?>
            </strong>

        </p>


        <a
            href="view.php?id=<?= (int) $teacherId ?>"
            class="back-link"
        >
            ← Back to Teacher
        </a>

    </div>


    <!-- =================================================
         MESSAGES
    ================================================== -->

    <?php if ($success !== ''): ?>

        <div class="alert alert-success">

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-error">

            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         ASSIGN FORM
    ================================================== -->

    <div class="card">

        <h2>
            Assign New Class
        </h2>


        <form
            method="POST"
            action=""
        >
            <?= csrf_field() ?>

            <div class="form-grid">


                <!-- ======================================
                     CLASS
                ======================================= -->

                <div class="form-group">

                    <label for="class_id">

                        Class

                        <span class="required">
                            *
                        </span>

                    </label>


                    <select
                        name="class_id"
                        id="class_id"
                        required
                    >

                        <option value="">
                            Select Class
                        </option>


                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= (int) $class['id'] ?>"
                                <?= (
                                    $selectedClassId ===
                                    (int) $class['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    (string) $class['name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="help-text">
                        Select the class where this teacher will teach.
                    </div>

                </div>


                <!-- ======================================
                     SECTION
                ======================================= -->

                <div class="form-group">

                    <label for="section_id">

                        Section

                        <span
                            id="section-required"
                            class="required"
                            style="display:none;"
                        >
                            *
                        </span>

                        <small id="section-label-note">
                            (Select a class first)
                        </small>

                    </label>


                    <select
                        name="section_id"
                        id="section_id"
                        disabled
                    >

                        <option
                            value=""
                            data-whole-class="1"
                        >
                            Whole Class / No Section
                        </option>


                        <?php foreach ($sections as $section): ?>

                            <option
                                value="<?= (int) $section['id'] ?>"
                                data-class-id="<?= (int) $section['class_id'] ?>"
                                <?= (
                                    $selectedSectionId !== null &&
                                    $selectedSectionId ===
                                    (int) $section['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    (string) $section['name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <div
                        id="section-status"
                        class="section-status"
                    ></div>


                    <div class="help-text">

                        Sections belong to the selected class.
                        They cannot be selected from another class.

                    </div>

                </div>


                <!-- ======================================
                     SUBJECT
                ======================================= -->

                <div class="form-group">

                    <label for="subject_id">

                        Subject

                        <span class="required">
                            *
                        </span>

                    </label>


                    <select
                        name="subject_id"
                        id="subject_id"
                        required
                    >

                        <option value="">
                            Select Subject
                        </option>


                        <?php foreach ($subjects as $subject): ?>

                            <option
                                value="<?= (int) $subject['id'] ?>"
                                <?= (
                                    $selectedSubjectId ===
                                    (int) $subject['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e(
                                    (string) $subject['name']
                                ) ?>


                                <?php if (
                                    !empty($subject['code'])
                                ): ?>

                                    -
                                    <?= e(
                                        (string) $subject['code']
                                    ) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="help-text">
                        Select the subject this teacher will teach.
                    </div>

                </div>

            </div>


            <!-- =========================================
                 BUTTON
            ========================================== -->

            <div class="button-area">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Assign Class
                </button>

            </div>

        </form>

    </div>


    <!-- =================================================
         EXISTING ASSIGNMENTS
    ================================================== -->

    <div class="card">

        <h2>
            Assigned Classes
        </h2>


        <?php if (empty($assignments)): ?>

            <div class="empty">

                No class assignments found.

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Section
                            </th>

                            <th>
                                Subject
                            </th>

                            <th>
                                Code
                            </th>

                            <th>
                                Assigned On
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach (
                            $assignments as $index => $assignment
                        ): ?>

                            <tr>


                                <!-- NUMBER -->

                                <td>

                                    <?= $index + 1 ?>

                                </td>


                                <!-- CLASS -->

                                <td>

                                    <?= e(
                                        (string)
                                        $assignment['class_name']
                                    ) ?>

                                </td>


                                <!-- SECTION -->

                                <td>

                                    <?php

                                    $sectionName =
                                        $assignment['section_name']
                                        ?? '';

                                    if (
                                        $sectionName === '' ||
                                        $sectionName === 'Whole Class'
                                    ):

                                    ?>

                                        <span
                                            class="whole-class"
                                        >
                                            Whole Class
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="section-badge"
                                        >

                                            <?= e(
                                                $sectionName
                                            ) ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- SUBJECT -->

                                <td>

                                    <span
                                        class="subject-badge"
                                    >

                                        <?= e(
                                            (string)
                                            $assignment['subject_name']
                                        ) ?>

                                    </span>

                                </td>


                                <!-- CODE -->

                                <td>

                                    <?= !empty(
                                        $assignment['subject_code']
                                    )
                                        ? e(
                                            (string)
                                            $assignment['subject_code']
                                        )
                                        : '-'
                                    ?>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?= e(
                                        (string)
                                        $assignment['created_at']
                                    ) ?>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="remove-class.php?id=<?= (int) $assignment['id'] ?>&teacher_id=<?= (int) $teacherId ?>"
                                        class="btn btn-danger"
                                        onclick="return confirm('Are you sure you want to remove this class assignment?');"
                                    >
                                        Remove
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


</div>


<!-- =====================================================
     SECTION FILTER + DYNAMIC REQUIRED RULE
====================================================== -->

<script>

    const classSelect =
        document.getElementById('class_id');

    const sectionSelect =
        document.getElementById('section_id');

    const sectionRequired =
        document.getElementById('section-required');

    const sectionLabelNote =
        document.getElementById('section-label-note');

    const sectionStatus =
        document.getElementById('section-status');


    /*
     * Keep the section that was submitted by PHP.
     *
     * This is useful when validation fails and
     * the page reloads.
     */

    const submittedSectionId =
        <?= $selectedSectionId !== null
            ? (int) $selectedSectionId
            : 'null'
        ?>;


    function filterSections() {

        const selectedClassId =
            classSelect.value;


        /*
         * No class selected.
         */

        if (selectedClassId === '') {

            sectionSelect.disabled = true;

            sectionSelect.required = false;

            sectionRequired.style.display = 'none';

            sectionLabelNote.textContent =
                '(Select a class first)';

            sectionStatus.className =
                'section-status';

            sectionStatus.textContent = '';

            sectionSelect.value = '';

            return;
        }


        /*
         * Get all section options.
         */

        const options =
            sectionSelect.querySelectorAll(
                'option[data-class-id]'
            );


        /*
         * Whole Class option.
         */

        const wholeClassOption =
            sectionSelect.querySelector(
                'option[data-whole-class="1"]'
            );


        /*
         * First reset.
         */

        let sectionCount = 0;


        options.forEach(
            function (option) {

                if (
                    option.dataset.classId ===
                    selectedClassId
                ) {

                    option.style.display = '';

                    sectionCount++;

                } else {

                    option.style.display = 'none';

                }

            }
        );


        /*
         * =============================================
         * CLASS HAS SECTIONS
         * =============================================
         */

        if (sectionCount > 0) {

            sectionSelect.disabled = false;

            sectionSelect.required = true;

            sectionRequired.style.display = 'inline';

            sectionLabelNote.textContent =
                '(Required)';


            /*
             * Whole Class is NOT allowed when
             * the class has sections.
             */

            if (wholeClassOption) {

                wholeClassOption.style.display =
                    'none';
            }


            /*
             * Restore submitted section if it
             * belongs to this class.
             */

            let restored = false;


            if (submittedSectionId !== null) {

                options.forEach(
                    function (option) {

                        if (
                            option.value ===
                            String(submittedSectionId) &&
                            option.dataset.classId ===
                            selectedClassId
                        ) {

                            sectionSelect.value =
                                String(submittedSectionId);

                            restored = true;
                        }

                    }
                );
            }


            if (!restored) {

                /*
                 * Do not automatically choose a section.
                 * Teacher/admin must select it.
                 */

                sectionSelect.value = '';
            }


            /*
             * Professional information.
             */

            sectionStatus.className =
                'section-status info';

            sectionStatus.textContent =
                'This class has '
                + sectionCount
                + ' section'
                + (sectionCount === 1 ? '' : 's')
                + '. Please select a section.';

        }


        /*
         * =============================================
         * CLASS HAS NO SECTIONS
         * =============================================
         */

        else {

            sectionSelect.disabled = false;

            sectionSelect.required = false;

            sectionRequired.style.display = 'none';

            sectionLabelNote.textContent =
                '(Not required)';


            /*
             * Show Whole Class.
             */

            if (wholeClassOption) {

                wholeClassOption.style.display =
                    '';
            }


            /*
             * Automatically select Whole Class.
             */

            sectionSelect.value = '';


            /*
             * Professional information.
             */

            sectionStatus.className =
                'section-status warning';

            sectionStatus.textContent =
                'This class has no sections. '
                + 'This assignment will apply to the whole class.';

        }

    }


    /*
     * When class changes.
     */

    classSelect.addEventListener(
        'change',
        function () {

            /*
             * Clear previously submitted section
             * when user manually changes class.
             */

            filterSections();

        }
    );


    /*
     * Initial page load.
     */

    filterSections();

</script>


</body>

</html>