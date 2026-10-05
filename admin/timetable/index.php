<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../includes/timetable_view.php';
require_once __DIR__ . '/../../classes/Timetable.php';
require_once __DIR__ . '/../../classes/Section.php';
require_once __DIR__ . '/../../classes/Student.php';

requireAdmin();

$pdo = db();

$timetableObj = new Timetable($pdo);
$sectionObj = new Section($pdo);
$studentObj = new Student($pdo);

/*
|--------------------------------------------------------------------------
| DELETE (POST + CSRF)
|--------------------------------------------------------------------------
*/

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    require_post_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $backQs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $backUrl = 'admin/timetable/index.php'
        . ($backQs !== '' ? '?' . $backQs : '');

    if ($action === 'delete') {

        // The grid posts id as a hidden form field, so read it from
        // $_POST (request_int() defaults to $_GET).
        $slotId = request_int('id', $_POST);

        try {
            if ($timetableObj->delete($slotId)) {
                flash_set('success', 'Timetable slot deleted.');
            } else {
                flash_set('error', 'Timetable slot not found.');
            }
        } catch (Throwable $e) {
            error_log('Timetable delete error: ' . $e->getMessage());
            flash_set('error', 'Could not delete the timetable slot.');
        }

        redirect($backUrl);
    }

    flash_set('error', 'Unknown action.');
    redirect('admin/timetable/index.php');
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$classId = request_int('class_id');
$sectionId = request_int('section_id');

$classes = $sectionObj->getActiveClasses();

// Default to the first class so the grid is never empty on arrival.
if ($classId <= 0 && $classes !== []) {
    $classId = (int) $classes[0]['id'];
}

$sections = $classId > 0
    ? $studentObj->getSectionsByClass($classId)
    : [];

$sectionIds = array_map(
    static fn (array $s): int => (int) $s['id'],
    $sections
);

// Only honor a section filter that actually belongs to the class.
if ($sectionId > 0 && !in_array($sectionId, $sectionIds, true)) {
    $sectionId = 0;
}

$rows = [];

if ($classId > 0) {
    $rows = $timetableObj->listForClass(
        $classId,
        $sectionId > 0 ? $sectionId : null
    );
}

$currentQs = http_build_query(array_filter([
    'class_id'   => $classId > 0 ? $classId : null,
    'section_id' => $sectionId > 0 ? $sectionId : null,
], static fn ($v): bool => $v !== null));

$className = '';

foreach ($classes as $cls) {
    if ((int) $cls['id'] === $classId) {
        $className = (string) $cls['name'];
        break;
    }
}

layout_start('Timetable', 'timetable');

?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

    <div>

        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-calendar3-week me-2"></i>Class Timetable
        </h1>

        <p class="text-muted mb-0">
            Weekly lesson slots per class and section.
        </p>

    </div>

    <a
        class="btn btn-primary"
        href="create.php<?= $classId > 0 ? '?class_id=' . $classId : '' ?>"
    >
        <i class="bi bi-plus-lg me-1"></i>
        Add Slot
    </a>

</div>


<!-- FILTERS -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body p-3">

        <form method="GET" class="row g-2 align-items-end">

            <div class="col-sm-5 col-lg-4">

                <label class="form-label fw-semibold" for="class_id">
                    Class
                </label>

                <select
                    class="form-select"
                    id="class_id"
                    name="class_id"
                    onchange="this.form.submit()"
                >

                    <?php foreach ($classes as $cls): ?>

                        <option
                            value="<?= (int) $cls['id'] ?>"
                            <?= $classId === (int) $cls['id'] ? 'selected' : '' ?>
                        >
                            <?= e((string) $cls['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-sm-5 col-lg-4">

                <label class="form-label fw-semibold" for="section_id">
                    Section
                </label>

                <select
                    class="form-select"
                    id="section_id"
                    name="section_id"
                    onchange="this.form.submit()"
                >

                    <option value="0">
                        All sections
                    </option>

                    <?php foreach ($sections as $sec): ?>

                        <option
                            value="<?= (int) $sec['id'] ?>"
                            <?= $sectionId === (int) $sec['id'] ? 'selected' : '' ?>
                        >
                            <?= e((string) $sec['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-sm-2 col-lg-4">
                <button type="submit" class="btn btn-outline-primary w-100">
                    Apply
                </button>
            </div>

        </form>

    </div>

</div>


<!-- GRID -->

<div class="card shadow-sm border-0">

    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">

        <h2 class="h6 fw-bold mb-0">
            Weekly Grid
            <?php if ($className !== ''): ?>
                &mdash;
                <?= e($className) ?>
            <?php endif; ?>
        </h2>

        <?php if ($sectionId > 0): ?>
            <span class="badge bg-primary">
                Section filter active
            </span>
        <?php endif; ?>

    </div>

    <div class="card-body p-0">

        <?php if ($classes === []): ?>

            <div class="text-center text-muted py-5">
                No active classes yet — create a class first.
            </div>

        <?php else: ?>

            <?php
            timetable_grid($rows, [
                'editable'     => true,
                'show_class'   => false,
                'show_teacher' => true,
                'delete_action'=> 'index.php'
                    . ($currentQs !== '' ? '?' . $currentQs : ''),
            ]);
            ?>

        <?php endif; ?>

    </div>

</div>

<?php layout_end(); ?>
