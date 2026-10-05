<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/timetable_view.php';
require_once __DIR__ . '/../classes/Timetable.php';

requireTeacher();

$pdo = db();

$timetableObj = new Timetable($pdo);

$teacher = current_teacher($pdo);

if (!$teacher) {
    denyAccess('No teacher profile is linked to your account.');
}

/*
 * ListForTeacher = own teaching slots + the full grid of every class
 * this teacher is authorized for (teacher_classes is the single
 * source of truth for that authorization).
 */
$rows = $timetableObj->listForTeacher((int) $teacher['id']);

$subjectNames = [];

foreach ($rows as $row) {
    if (!empty($row['subject_name'])) {
        $subjectNames[(string) $row['subject_name']] = true;
    }
}

$classesSeen = [];

foreach ($rows as $row) {
    $classesSeen[(int) $row['class_id']] = true;
}

layout_start('My Timetable', 'timetable');

?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-calendar3-week me-2"></i>My Timetable
        </h1>

        <p class="text-muted mb-0">
            Weekly lessons across all classes assigned to you.
        </p>

    </div>

    <div class="text-end">

        <div class="fs-4 fw-bold"><?= count($rows) ?></div>

        <div class="text-muted" style="font-size:.8rem;">
            slots &middot; <?= count($classesSeen) ?> classes
            &middot; <?= count($subjectNames) ?> subjects
        </div>

    </div>

</div>


<div class="card shadow-sm border-0">

    <div class="card-header bg-white py-3">
        <h2 class="h6 fw-bold mb-0">Weekly Grid</h2>
    </div>

    <div class="card-body p-0">

        <?php
        timetable_grid($rows, [
            'editable'     => false,
            'show_class'   => true,
            'show_teacher' => false,
        ]);
        ?>

    </div>

</div>

<?php layout_end(); ?>
