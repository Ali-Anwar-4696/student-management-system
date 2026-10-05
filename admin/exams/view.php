<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Exam.php';
require_once __DIR__ . '/../../classes/Subject.php';
requireAdmin();

$pdo = db();
$exam = new Exam($pdo);
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$row = $exam->getById($id);
if (!$row) {
    redirect('admin/exams/index.php');
}

$subjects = (new Subject($pdo))->getActiveSubjects();
$assigned = $exam->getSubjects($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
        $subjectId = (int) ($_POST['subject_id'] ?? 0);
        $total = (float) ($_POST['total_marks'] ?? 100);
        $pass = (float) ($_POST['pass_marks'] ?? 40);
        if ($subjectId <= 0 || $total <= 0 || $pass < 0 || $pass > $total) {
            flash_set('danger', 'Invalid subject marks.');
        } elseif (!$exam->addSubject($id, $subjectId, $total, $pass)) {
            flash_set('danger', 'That subject is already added to this exam.');
        } else {
            flash_set('success', 'Subject added.');
        }
    } elseif ($action === 'remove') {
        $exam->removeSubject($id, (int) ($_POST['exam_subject_id'] ?? 0));
        flash_set('success', 'Subject removed.');
    }
    redirect('admin/exams/view.php?id=' . $id);
}

layout_start('Exam Subjects', 'exams');
?>
<div class="card stat-card p-4 mb-3">
    <h2 class="h5"><?= e($row['name']) ?> · <?= e($row['class_name']) ?></h2>
    <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="col-md-4">
            <label class="form-label">Subject</label>
            <select name="subject_id" class="form-select" required>
                <option value="">Select</option>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= (int) $subject['id'] ?>"><?= e($subject['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><label class="form-label">Total</label><input type="number" step="0.01" name="total_marks" class="form-control" value="100" required></div>
        <div class="col-md-2"><label class="form-label">Pass</label><input type="number" step="0.01" name="pass_marks" class="form-control" value="40" required></div>
        <div class="col-md-2"><button class="btn btn-primary">Add</button></div>
    </form>
</div>
<div class="card stat-card p-3 table-responsive">
<table class="table mb-0">
<thead><tr><th>Subject</th><th>Total</th><th>Pass</th><th></th></tr></thead>
<tbody>
<?php foreach ($assigned as $item): ?>
<tr>
    <td><?= e($item['subject_name']) ?></td>
    <td><?= e($item['total_marks']) ?></td>
    <td><?= e($item['pass_marks']) ?></td>
    <td>
        <form method="post" class="d-inline"><?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="exam_subject_id" value="<?= (int) $item['id'] ?>">
            <button class="btn btn-sm btn-outline-danger">Remove</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
<?php if ($assigned === []): ?><tr><td colspan="4" class="text-muted">No subjects yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
<p class="mt-3"><a href="index.php">Back to exams</a> · <a href="<?= e(url('admin/marks/index.php?exam_id=' . $id)) ?>">Enter marks</a></p>
<?php layout_end();
