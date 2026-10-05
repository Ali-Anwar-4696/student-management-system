<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/timetable_view.php';
require_once __DIR__ . '/../classes/Timetable.php';

requireStudent();

$pdo = db();

$timetableObj = new Timetable($pdo);

$student = current_student($pdo);

if (!$student) {
    denyAccess('No student profile is linked to your account.');
}

$classId = (int) ($student['class_id'] ?? 0);
$sectionId = $student['section_id'] !== null && $student['section_id'] !== ''
    ? (int) $student['section_id']
    : null;

/*
 * Whole-class slots (section_id IS NULL) + the student's own section.
 */
$rows = $classId > 0
    ? $timetableObj->listForStudent($classId, $sectionId)
    : [];

layout_start('My Timetable', 'timetable');

?>

<div class="mb-4">

    <h1 class="h3 fw-bold mb-1">
        <i class="bi bi-calendar3-week me-2"></i>My Timetable
    </h1>

    <p class="text-muted mb-0">
        Weekly class schedule
        <?php if ($classId > 0 && !empty($student['class_name'])): ?>
            for
            <?= e((string) $student['class_name']) ?>
            <?php if ($sectionId !== null && !empty($student['section_name'])): ?>
                &mdash; <?= e((string) $student['section_name']) ?>
            <?php endif; ?>
        <?php endif; ?>
        .
    </p>

</div>


<div class="card shadow-sm border-0">

    <div class="card-header bg-white py-3">
        <h2 class="h6 fw-bold mb-0">Weekly Grid</h2>
    </div>

    <div class="card-body p-0">

        <?php if ($classId <= 0): ?>

            <div class="text-center text-muted py-5 px-3">

                <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>

                <p class="mb-1 fw-semibold">
                    No class has been assigned to you yet.
                </p>

                <p class="mb-0" style="font-size:.9rem;">
                    Please contact the school administration.
                </p>

            </div>

        <?php else: ?>

            <?php
            timetable_grid($rows, [
                'editable'     => false,
                'show_class'   => false,
                'show_teacher' => true,
            ]);
            ?>

        <?php endif; ?>

    </div>

</div>

<?php layout_end(); ?>
