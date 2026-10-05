<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Exam.php';
require_once __DIR__ . '/../../classes/ClassRoom.php';
requireAdmin();

$pdo = db();
$exam = new Exam($pdo);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$row = $exam->getById($id);
if (!$row) {
    redirect('admin/exams/index.php');
}

$classes = (new ClassRoom($pdo))->getClasses('', 'active', 200, 0);
$errors = [];
$types = ['monthly','midterm','final','quiz','other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $data = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'type' => (string) ($_POST['type'] ?? 'other'),
        'start_date' => trim((string) ($_POST['start_date'] ?? '')),
        'end_date' => trim((string) ($_POST['end_date'] ?? '')),
        'class_id' => (int) ($_POST['class_id'] ?? 0),
        'status' => (string) ($_POST['status'] ?? 'upcoming'),
    ];
    if ($data['name'] === '' || $data['class_id'] <= 0 || !in_array($data['type'], $types, true)) {
        $errors[] = 'Please complete the required fields.';
    } elseif ($exam->existsForClass($data['name'], $data['class_id'], $id)) {
        $errors[] = 'Duplicate exam name for this class.';
    } elseif ($exam->update($id, $data)) {
        flash_set('success', 'Exam updated.');
        redirect('admin/exams/index.php');
    }
}

layout_start('Edit Exam', 'exams');
?>
<div class="card stat-card p-4">
<?php if ($errors): ?><div class="alert alert-danger"><?= e($errors[0]) ?></div><?php endif; ?>
<form method="post" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e((string) ($_POST['name'] ?? $row['name'])) ?>" required></div>
    <div class="col-md-3">
        <select name="type" class="form-select mt-4"><?php foreach ($types as $t): ?><option value="<?= $t ?>" <?= ($row['type'] === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select>
    </div>
    <div class="col-md-3">
        <select name="class_id" class="form-select mt-4">
            <?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>" <?= (int) $row['class_id'] === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4"><input type="date" name="start_date" class="form-control" value="<?= e((string) $row['start_date']) ?>"></div>
    <div class="col-md-4"><input type="date" name="end_date" class="form-control" value="<?= e((string) $row['end_date']) ?>"></div>
    <div class="col-md-4">
        <select name="status" class="form-select">
            <?php foreach (['upcoming','active','completed'] as $st): ?><option value="<?= $st ?>" <?= $row['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="col-12"><button class="btn btn-primary">Update</button></div>
</form>
</div>
<?php layout_end();
