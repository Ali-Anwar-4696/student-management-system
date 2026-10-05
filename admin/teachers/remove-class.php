<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/TeacherClass.php';


// =====================================================
// DATABASE
// =====================================================

$pdo = db();


// =====================================================
// OBJECT
// =====================================================

$teacherClassObject = new TeacherClass($pdo);


// =====================================================
// ASSIGNMENT ID
// =====================================================

$assignmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

$teacherId = filter_input(
    INPUT_GET,
    'teacher_id',
    FILTER_VALIDATE_INT
);


// =====================================================
// VALIDATION
// =====================================================

if (!$assignmentId || $assignmentId <= 0) {
    http_response_code(400);
    die("Invalid assignment ID.");
}

if (!$teacherId || $teacherId <= 0) {
    http_response_code(400);
    die("Invalid teacher ID.");
}


// =====================================================
// CHECK ASSIGNMENT
// =====================================================
//
// The assignment must belong to the teacher we
// are coming from.
//

$assignment =
    $teacherClassObject->getAssignmentById(
        $assignmentId
    );


if (!$assignment) {
    http_response_code(404);
    die("Class assignment not found.");
}


if (
    (int) $assignment['teacher_id'] !== $teacherId
) {
    http_response_code(403);
    die("Unauthorized request.");
}


// =====================================================
// POST = ACTUAL REMOVAL (CSRF PROTECTED)
// =====================================================
//
// GET only renders the confirmation screen below.
// This removes the previous GET-based state change.
//

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $deleted =
        $teacherClassObject->removeAssignment(
            $assignmentId
        );

    if ($deleted) {
        flash_set(
            'success',
            'Class assignment removed.'
        );

        header(
            "Location: assign-classes.php?teacher_id="
            . (int) $teacherId
            . "&removed=1"
        );

        exit;
    }

    flash_set(
        'danger',
        'Failed to remove class assignment.'
    );

    header(
        "Location: assign-classes.php?teacher_id="
        . (int) $teacherId
    );

    exit;
}


// =====================================================
// GET = CONFIRMATION SCREEN
// =====================================================

$classStmt = $pdo->prepare(
    'SELECT name FROM classes WHERE id = :id LIMIT 1'
);
$classStmt->execute([':id' => (int) $assignment['class_id']]);
$className = (string) ($classStmt->fetchColumn() ?: 'Unknown class');

$subjectStmt = $pdo->prepare(
    'SELECT name FROM subjects WHERE id = :id LIMIT 1'
);
$subjectStmt->execute([':id' => (int) $assignment['subject_id']]);
$subjectName = (string) ($subjectStmt->fetchColumn() ?: 'Unknown subject');

$sectionName = 'Whole Class';
if (!empty($assignment['section_id'])) {
    $sectionStmt = $pdo->prepare(
        'SELECT name FROM sections WHERE id = :id LIMIT 1'
    );
    $sectionStmt->execute([':id' => (int) $assignment['section_id']]);
    $sectionName = (string) ($sectionStmt->fetchColumn() ?: 'Unknown section');
}

require_once __DIR__ . '/../../includes/layout.php';

layout_start('Remove Class Assignment', 'teachers');
?>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-2">Remove Class Assignment</h2>

        <p class="text-muted mb-1">
            You are about to remove this assignment:
        </p>

        <ul class="mb-4">
            <li><strong>Class:</strong> <?= e($className) ?></li>
            <li><strong>Section:</strong> <?= e($sectionName) ?></li>
            <li><strong>Subject:</strong> <?= e($subjectName) ?></li>
        </ul>

        <p class="alert alert-warning">
            Removing this assignment immediately revokes the teacher's
            authorization for this class, section and subject.
            Existing marks, attendance and submissions are kept.
        </p>

        <form method="POST">
            <?= csrf_field() ?>

            <button
                type="submit"
                class="btn btn-danger"
                onclick="return confirm('Are you sure you want to remove this class assignment?');"
            >
                Yes, Remove Assignment
            </button>

            <a
                class="btn btn-outline-secondary"
                href="assign-classes.php?teacher_id=<?= (int) $teacherId ?>"
            >
                Cancel
            </a>
        </form>
    </div>
</div>

<?php layout_end();
