<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Exam.php';
require_once __DIR__ . '/../../classes/ClassRoom.php';
requireAdmin();

$pdo = db();
$exam = new Exam($pdo);
$classes = (new ClassRoom($pdo))->getClasses('', 'active', 200, 0);
$errors = [];
$name = $type = $start = $end = $classId = '';
$status = 'upcoming';
$types = ['monthly','midterm','final','quiz','other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $type = (string) ($_POST['type'] ?? 'other');
    $start = trim((string) ($_POST['start_date'] ?? ''));
    $end = trim((string) ($_POST['end_date'] ?? ''));
    $classId = (string) ($_POST['class_id'] ?? '');
    $status = (string) ($_POST['status'] ?? 'upcoming');

    if ($name === '' || mb_strlen($name) > 150) {
        $errors[] = 'Exam name is required (max 150 characters).';
    }
    if (!in_array($type, $types, true)) {
        $errors[] = 'Invalid exam type.';
    }
    if (!ctype_digit($classId) || (int) $classId <= 0) {
        $errors[] = 'Select a class.';
    }
    if ($start !== '' && $end !== '' && $end < $start) {
        $errors[] = 'End date cannot be before start date.';
    }
    if ($errors === [] && $exam->existsForClass($name, (int) $classId)) {
        $errors[] = 'An exam with this name already exists for the selected class.';
    }
    if ($errors === []) {
        $id = $exam->create([
            'name' => $name,
            'type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'class_id' => (int) $classId,
            'status' => $status,
        ]);
        flash_set('success', 'Exam created.');
        redirect('admin/exams/view.php?id=' . $id);
    }
}

layout_start('Create Exam', 'exams');
?>
<div class="card stat-card p-4">
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($name) ?>" required></div>
    <div class="col-md-3">
        <label class="form-label">Type</label>
        <select name="type" class="form-select"><?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Class</label>
        <select name="class_id" class="form-select" required>
            <option value="">Select</option>
            <?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= $classId === (string) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date" value="<?= e($start) ?>"></div>
    <div class="col-md-4"><label class="form-label">End date</label><input type="date" class="form-control" name="end_date" value="<?= e($end) ?>"></div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <?php foreach (['upcoming','active','completed'] as $st): ?><option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-12"><button class="btn btn-primary">Save</button> <a class="btn btn-outline-secondary" href="index.php">Cancel</a></div>
</form>
</div>
<?php layout_end();
