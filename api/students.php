<?php

declare(strict_types=1);

/*
| GET api/students.php
| admin:   search + status + class_id filters, paginated
| teacher: students in the teacher's own classes (same teacher_classes
|          EXISTS predicate as teacher/students.php), paginated
| student: own record
| parent:  linked children
|
| Filters: search, status, class_id, page, per_page (max 100)
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
$studentObj = new Student($pdo);

$search = api_str('search');
$status = api_str('status');
$classId = api_int('class_id');

$pag = api_pagination(api_int('page', 1), api_int('per_page', 20));

// ------------------------------------------------------------------
// STUDENT: own record only
// ------------------------------------------------------------------
if ($role === 'student') {

    $me = current_student();

    if (!$me) {
        api_fail(404, 'No student profile linked to your account.');
    }

    unset($me['user_id']); // internal linkage, not part of the payload

    api_ok(['student' => $me]);
}

// ------------------------------------------------------------------
// PARENT: linked children
// ------------------------------------------------------------------
if ($role === 'parent') {

    $children = $studentObj->getChildrenByParent(
        (int) ($_SESSION['user_id'] ?? 0)
    );

    if ($classId > 0) {
        $children = array_values(array_filter(
            $children,
            static fn (array $c): bool => (int) ($c['class_id'] ?? 0) === $classId
        ));
    }

    $total = count($children);
    $children = array_slice($children, $pag['offset'], $pag['per_page']);

    api_ok(['students' => $children], [
        'total'     => $total,
        'page'      => $pag['page'],
        'per_page'  => $pag['per_page'],
    ]);
}

// ------------------------------------------------------------------
// TEACHER: own classes only
// ------------------------------------------------------------------
if ($role === 'teacher') {

    $teacher = current_teacher();

    if (!$teacher) {
        api_fail(403, 'No teacher profile linked to your account.');
    }

    $sql = "
        SELECT
            s.id, s.student_id, s.name, s.father_name, s.email, s.phone,
            s.status, s.class_id, s.section_id,
            c.name AS class_name,
            sec.name AS section_name
        FROM students s
        LEFT JOIN classes c ON c.id = s.class_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        WHERE EXISTS (
            SELECT 1 FROM teacher_classes tc
            WHERE tc.teacher_id = :teacher_id
              AND tc.class_id = s.class_id
              AND (tc.section_id IS NULL OR tc.section_id = s.section_id)
        )
    ";

    $params = [':teacher_id' => (int) $teacher['id']];

    if ($search !== '') {
        $sql .= '
            AND (
                s.name LIKE :q1
                OR s.student_id LIKE :q2
                OR s.email LIKE :q3
            )';
        $like = '%' . $search . '%';
        $params[':q1'] = $like;
        $params[':q2'] = $like;
        $params[':q3'] = $like;
    }

    if ($classId > 0) {
        $sql .= ' AND s.class_id = :class_id';
        $params[':class_id'] = $classId;
    }

    // total (same predicate, without limit)
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM students s WHERE EXISTS (
            SELECT 1 FROM teacher_classes tc
            WHERE tc.teacher_id = :teacher_id
              AND tc.class_id = s.class_id
              AND (tc.section_id IS NULL OR tc.section_id = s.section_id)
        )'
        . ($search !== '' ? ' AND (s.name LIKE :q1 OR s.student_id LIKE :q2 OR s.email LIKE :q3)' : '')
        . ($classId > 0 ? ' AND s.class_id = :class_id' : '')
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $sql .= ' ORDER BY s.name ASC LIMIT :lim OFFSET :off';
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':lim', $pag['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':off', $pag['offset'], PDO::PARAM_INT);
    $stmt->execute();

    api_ok(['students' => $stmt->fetchAll(PDO::FETCH_ASSOC)], [
        'total'    => $total,
        'page'     => $pag['page'],
        'per_page' => $pag['per_page'],
    ]);
}

// ------------------------------------------------------------------
// ADMIN: full list with filters
// ------------------------------------------------------------------

$total = $studentObj->countStudents($search, $status, $classId > 0 ? (string) $classId : '');

$rows = $studentObj->getStudents(
    $search,
    $status,
    $classId > 0 ? (string) $classId : '',
    $pag['per_page'],
    $pag['offset']
);

// Drop internal linkage column from the payload.
foreach ($rows as &$row) {
    unset($row['user_id']);
}
unset($row);

api_ok(['students' => $rows], [
    'total'     => $total,
    'page'      => $pag['page'],
    'per_page'  => $pag['per_page'],
]);
