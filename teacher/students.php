<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

requireTeacher();

$pdo = db();
$teacher = current_teacher($pdo);

if (!$teacher) {
    exit('Teacher profile not found.');
}

// Get the students assigned to this teacher's classes/sections.
// EXISTS instead of a JOIN so that whole-class assignments
// (teacher_classes.section_id IS NULL) are matched too and
// no duplicate rows are produced.
$stmt = $pdo->prepare("
    SELECT 
        s.id, 
        s.student_id, 
        s.name, 
        s.email, 
        s.status,
        c.name as class_name,
        sec.name as section_name
    FROM students s
    JOIN classes c ON c.id = s.class_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE EXISTS (
        SELECT 1 FROM teacher_classes tc
        WHERE tc.teacher_id = ?
          AND tc.class_id = s.class_id
          AND (tc.section_id IS NULL OR tc.section_id = s.section_id)
    )
    ORDER BY c.name, sec.name, s.name
");
$stmt->execute([(int)$teacher['id']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_start('My Students', 'students');
?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">Students in My Classes</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Class & Section</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">No students found in your assigned classes.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['student_id']) ?></td>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td>
                                    <?= htmlspecialchars((string) $student['class_name']) ?>
                                    -
                                    <?= htmlspecialchars((string) ($student['section_name'] ?? 'No Section')) ?>
                                </td>
                                <td>
                                    <?php if ($student['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= htmlspecialchars(ucfirst($student['status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php layout_end(); ?>
