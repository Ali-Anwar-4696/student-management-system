<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Exam.php';
requireAdmin();

$pdo = db();
$exam = new Exam($pdo);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$row = $exam->getById($id);
if (!$row) {
    redirect('admin/exams/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $exam->delete($id);
    flash_set('success', 'Exam deleted.');
    redirect('admin/exams/index.php');
}

layout_start('Delete Exam', 'exams');
?>
<div class="card stat-card p-4">
    <p>Delete exam <strong><?= e($row['name']) ?></strong>? Marks for this exam will also be removed.</p>
    <form method="post"><?= csrf_field() ?><button class="btn btn-danger">Delete</button> <a class="btn btn-outline-secondary" href="index.php">Cancel</a></form>
</div>
<?php layout_end();
