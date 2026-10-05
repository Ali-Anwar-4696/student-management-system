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

$slotId = request_int('id');

$slot = $timetableObj->getById($slotId);

if ($slot === null) {
    flash_set('error', 'Timetable slot not found.');
    redirect('admin/timetable/index.php');
}

$classes = $sectionObj->getActiveClasses();
$subjects = $subjectObj->getActiveSubjects();
$teachers = $teacherObj->getTeachers('', 'active', 500, 0);

$errors = [];

$old = [
    'class_id'   => (int) $slot['class_id'],
    'section_id' => $slot['section_id'] !== null ? (int) $slot['section_id'] : 0,
    'day_of_week'=> (int) $slot['day_of_week'],
    'period'     => (string) $slot['period'],
    'start_time' => substr((string) $slot['start_time'], 0, 5),
    'end_time'   => substr((string) $slot['end_time'], 0, 5),
    'subject_id' => $slot['subject_id'] !== null ? (int) $slot['subject_id'] : 0,
    'teacher_id' => $slot['teacher_id'] !== null ? (int) $slot['teacher_id'] : 0,
    'room'       => (string) ($slot['room'] ?? ''),
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

        $timetableObj->update($slotId, $old);

        flash_set('success', 'Timetable slot updated.');

        redirect(
            'admin/timetable/index.php?class_id='
            . (int) $old['class_id']
        );

    } catch (RuntimeException $e) {

        $errors[] = $e->getMessage();

    } catch (Throwable $e) {

        error_log('Timetable update error: ' . $e->getMessage());
        $errors[] = 'Could not update the timetable slot. Please try again.';
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

$formAction = 'edit.php?id=' . $slotId;
$submitLabel = 'Save Changes';

layout_start('Edit Timetable Slot', 'timetable');

?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-calendar-event me-2"></i>Edit Timetable Slot
        </h1>

        <p class="text-muted mb-0">
            <?= e((string) $slot['class_name']) ?>
            <?php if (!empty($slot['section_name'])): ?>
                &mdash; <?= e((string) $slot['section_name']) ?>
            <?php endif; ?>
            &middot;
            <?= e($dayNames[(int) $slot['day_of_week']] ?? '') ?>
            Period <?= (int) $slot['period'] ?>
        </p>

    </div>

    <a class="btn btn-outline-secondary" href="index.php?class_id=<?= (int) $slot['class_id'] ?>">
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
