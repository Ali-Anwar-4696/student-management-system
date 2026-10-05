<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Timetable.php';
require_once __DIR__ . '/../../classes/Section.php';
require_once __DIR__ . '/../../classes/Student.php';
require_once __DIR__ . '/../../classes/Subject.php';
require_once __DIR__ . '/../../classes/Teacher.php';

requireAdmin();

$pdo = db();

$timetableObj = new Timetable($pdo);
$sectionObj = new Section($pdo);
$studentObj = new Student($pdo);
$subjectObj = new Subject($pdo);
$teacherObj = new Teacher($pdo);

$dayNames = Timetable::dayNames();

$classes = $sectionObj->getActiveClasses();
$subjects = $subjectObj->getActiveSubjects();
$teachers = $teacherObj->getTeachers('', 'active', 500, 0);

$errors = [];

// First class pre-selected when no explicit class was requested.
$defaultClassId = $classes !== [] ? (int) $classes[0]['id'] : 0;

$old = [
    'class_id'   => request_int('class_id', $_GET, $defaultClassId),
    'section_id' => request_int('section_id'),
    'day_of_week'=> request_int('day_of_week', $_GET, 1),
    'period'     => (string) ($_GET['period'] ?? ''),
    'start_time' => (string) ($_GET['start_time'] ?? ''),
    'end_time'   => (string) ($_GET['end_time'] ?? ''),
    'subject_id' => request_int('subject_id'),
    'teacher_id' => request_int('teacher_id'),
    'room'       => (string) ($_GET['room'] ?? ''),
];

/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    require_post_csrf();

    $old = [
        'class_id'   => trim((string) ($_POST['class_id'] ?? '')),
        'section_id' => trim((string) ($_POST['section_id'] ?? '')),
        'day_of_week'=> trim((string) ($_POST['day_of_week'] ?? '')),
        'period'     => trim((string) ($_POST['period'] ?? '')),
        'start_time' => trim((string) ($_POST['start_time'] ?? '')),
        'end_time'   => trim((string) ($_POST['end_time'] ?? '')),
        'subject_id' => trim((string) ($_POST['subject_id'] ?? '')),
        'teacher_id' => trim((string) ($_POST['teacher_id'] ?? '')),
        'room'       => trim((string) ($_POST['room'] ?? '')),
    ];

    try {

        $slotId = $timetableObj->create($old);

        flash_set(
            'success',
            'Timetable slot created (Period '
            . $old['period'] . ').'
        );

        redirect(
            'admin/timetable/index.php?class_id='
            . (int) $old['class_id']
        );

    } catch (RuntimeException $e) {

        $errors[] = $e->getMessage();

    } catch (Throwable $e) {

        error_log('Timetable create error: ' . $e->getMessage());
        $errors[] = 'Could not create the timetable slot. Please try again.';
    }
}

/*
|--------------------------------------------------------------------------
| INITIAL SECTION OPTIONS (for the currently selected class)
|--------------------------------------------------------------------------
*/

$sections = ((int) ($old['class_id'] ?? 0)) > 0
    ? $studentObj->getSectionsByClass((int) $old['class_id'])
    : [];

$formAction = 'create.php';
$submitLabel = 'Create Slot';

layout_start('Add Timetable Slot', 'timetable');

?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-calendar-plus me-2"></i>Add Timetable Slot
        </h1>

        <p class="text-muted mb-0">
            Schedule a weekly lesson for a class or section.
        </p>

    </div>

    <a class="btn btn-outline-secondary" href="index.php">
        <i class="bi bi-arrow-left me-1"></i>
        Back to Grid
    </a>

</div>


<div class="card shadow-sm border-0">

    <div class="card-body p-4">

        <?php require __DIR__ . '/_slot_form.php'; ?>

    </div>

</div>

<?php layout_end(); ?>
