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
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$classId = trim((string) ($_GET['class_id'] ?? ''));
$exams = $exam->list($search, $status, $classId);

layout_start('Exams', 'exams');
?>
<form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search exam"></div>
    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <?php foreach (['upcoming','active','completed'] as $st): ?>
                <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="class_id" class="form-select">
            <option value="">All classes</option>
            <?php foreach ($classes as $class): ?>
                <option value="<?= (int) $class['id'] ?>" <?= $classId === (string) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
</form>
<p><a class="btn btn-primary" href="create.php">Create exam</a></p>
<div class="card stat-card p-3 table-responsive">
<table class="table mb-0">
<thead><tr><th>Name</th><th>Type</th><th>Class</th><th>Dates</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($exams as $row): ?>
<tr>
    <td><?= e($row['name']) ?></td>
    <td><?= e($row['type']) ?></td>
    <td><?= e($row['class_name']) ?></td>
    <td><?= e((string) $row['start_date']) ?> – <?= e((string) $row['end_date']) ?></td>
    <td><?= e($row['status']) ?></td>
    <td class="text-nowrap">
        <a href="view.php?id=<?= (int) $row['id'] ?>">Subjects</a>
        · <a href="edit.php?id=<?= (int) $row['id'] ?>">Edit</a>
        · <a href="delete.php?id=<?= (int) $row['id'] ?>">Delete</a>
    </td>
</tr>
<?php endforeach; ?>
<?php if ($exams === []): ?><tr><td colspan="6" class="text-muted">No exams found.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php layout_end();
