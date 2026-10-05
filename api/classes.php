<?php

declare(strict_types=1);

/*
| GET api/classes.php
| admin: all classes (+sections) | teacher: assigned classes
| student: own class | parent: children's classes
*/

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/Student.php';

api_require_get();
api_require_auth();

$role = currentRole();

if (!in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {
    api_fail(403, 'You do not have access to this endpoint.');
}

$pdo = db();

// ---- allowed class ids per role ----
$allowedClassIds = null; // null = unrestricted (admin)

if ($role === 'teacher') {

    $teacher = current_teacher();

    if (!$teacher) {
        api_fail(403, 'No teacher profile linked to your account.');
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT class_id FROM teacher_classes WHERE teacher_id = :id'
    );
    $stmt->execute([':id' => (int) $teacher['id']]);
    $allowedClassIds = array_map('intval', array_column($stmt->fetchAll(), 'class_id'));

} elseif ($role === 'student') {

    $student = current_student();
    $allowedClassIds = $student && !empty($student['class_id'])
        ? [(int) $student['class_id']]
        : [];

} elseif ($role === 'parent') {

    $allowedClassIds = array_values(array_unique(array_filter(
        array_map(
            static fn (array $c): int => (int) ($c['class_id'] ?? 0),
            (new \Student($pdo))->getChildrenByParent((int) ($_SESSION['user_id'] ?? 0))
        )
    )));
}

// ---- classes ----
$sql = 'SELECT id, name, status FROM classes';
$params = [];

if ($allowedClassIds !== null) {
    if ($allowedClassIds === []) {
        api_ok(['classes' => []]);
    }

    $sql .= ' WHERE id IN (' . implode(',', array_fill(0, count($allowedClassIds), '?')) . ')';
    $params = $allowedClassIds;
} else {
    $sql .= " WHERE 1 = 1";
}

if ($role !== 'admin') {
    $sql .= ($params !== [] ? ' AND' : ' WHERE') . " status = 'active'";
}

$sql .= ' ORDER BY name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$classRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- sections (active) ----
$sectionStmt = $pdo->query(
    "SELECT id, class_id, name FROM sections WHERE status = 'active' ORDER BY name ASC"
);

$sectionsByClass = [];

foreach ($sectionStmt->fetchAll(PDO::FETCH_ASSOC) as $section) {
    $sectionsByClass[(int) $section['class_id']][] = [
        'id'   => (int) $section['id'],
        'name' => (string) $section['name'],
    ];
}

$classes = [];

foreach ($classRows as $class) {
    $classes[] = [
        'id'       => (int) $class['id'],
        'name'     => (string) $class['name'],
        'status'   => (string) $class['status'],
        'sections' => $sectionsByClass[(int) $class['id']] ?? [],
    ];
}

api_ok(['classes' => $classes], ['count' => count($classes)]);
