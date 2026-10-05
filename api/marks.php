<?php

declare(strict_types=1);

/*
| GET api/marks.php
| params: exam_id (required), student_id (optional for admin/teacher;
|         forced to the caller for students, ownership-checked for
|         parents), page/per_page (admin overview only)
|
| Authorization:
|  - teacher: exam must belong to an assigned class (teacher_classes),
|             and an explicitly requested student must be in an
|             assigned class (same predicate as teacher/students.php);
|  - student: always own record only;
|  - parent:  requested student (or all children) must be linked.
*/

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/Mark.php';

api_require_get();
api_require_auth();

$role = currentRole();

if (!in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {
    api_fail(403, 'You do not have access to this endpoint.');
}

$examId = api_int('exam_id');

if ($examId <= 0) {
    api_fail(400, 'exam_id is required.');
}

$pdo = db();
$markObj = new Mark($pdo);

$examStmt = $pdo->prepare(
    'SELECT id, name, class_id, status FROM exams WHERE id = :id LIMIT 1'
);
$examStmt->execute([':id' => $examId]);
$exam = $examStmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    api_fail(404, 'Exam not found.');
}

$studentId = api_int('student_id');

// ---- role scoping ----
$studentIds = [];

if ($role === 'student') {

    $me = current_student();

    if (!$me) {
        api_fail(404, 'No student profile linked to your account.');
    }

    $studentIds = [(int) $me['id']];

} elseif ($role === 'teacher') {

    api_assert_exam_access($examId);

    if ($studentId > 0) {
        api_assert_student_access($studentId);
        $studentIds = [$studentId];
    }
    // no student_id: all marks of this (owned) exam

} elseif ($role === 'parent') {

    if ($studentId > 0) {
        api_assert_student_access($studentId);
        $studentIds = [$studentId];
    } else {
        $studentIds = api_parent_child_ids();

        if ($studentIds === []) {
            api_ok(['marks' => []], [
                'exam' => [
                    'id'   => (int) $exam['id'],
                    'name' => (string) $exam['name'],
                ],
                'count' => 0,
            ]);
        }
    }

} elseif ($role === 'admin' && $studentId > 0) {

    $studentIds = [$studentId];
}

// ---- rows ----
if (count($studentIds) === 1) {

    $rows = $markObj->getStudentExamMarks($examId, $studentIds[0]);

} elseif ($studentIds === []) {

    // admin, or teacher without a student filter: full exam overview
    $rows = $markObj->getForExamWithAudit($examId);

} else {

    // parent across several children
    $rows = [];

    foreach ($studentIds as $childId) {
        foreach ($markObj->getStudentExamMarks($examId, $childId) as $row) {
            $rows[] = $row;
        }
    }
}

// ---- attach student names where the query did not include them ----
$missingIds = [];

foreach ($rows as $row) {
    if (empty($row['student_name']) && !empty($row['student_id'])) {
        $missingIds[(int) $row['student_id']] = true;
    }
}

$namesByStudent = [];

if ($missingIds !== []) {
    $nameStmt = $pdo->prepare(
        'SELECT id, name, student_id FROM students WHERE id IN ('
        . implode(',', array_fill(0, count($missingIds), '?'))
        . ')'
    );
    $nameStmt->execute(array_keys($missingIds));

    foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $nameRow) {
        $namesByStudent[(int) $nameRow['id']] = $nameRow;
    }
}

foreach ($rows as &$row) {
    $sid = (int) ($row['student_id'] ?? 0);

    if (empty($row['student_name']) && isset($namesByStudent[$sid])) {
        $row['student_name'] = $namesByStudent[$sid]['name'];
        $row['student_code'] = $namesByStudent[$sid]['student_id'];
    }
}
unset($row);

api_ok(['marks' => $rows], [
    'exam' => [
        'id'       => (int) $exam['id'],
        'name'     => (string) $exam['name'],
        'class_id' => (int) $exam['class_id'],
        'status'   => (string) $exam['status'],
    ],
    'count' => count($rows),
]);
