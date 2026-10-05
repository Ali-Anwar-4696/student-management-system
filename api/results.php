<?php

declare(strict_types=1);

/*
| GET api/results.php
| params: exam_id (required), student_id (required for admin/teacher/
|         parent; forced to the caller for students)
|
| Returns the computed canonical result (Mark::calculateResult) —
| totals, percentage, grade, pass/fail and the per-subject rows.
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
$studentId = api_int('student_id');

if ($examId <= 0) {
    api_fail(400, 'exam_id is required.');
}

$pdo = db();

// ---- role scoping ----
if ($role === 'student') {

    $me = current_student();

    if (!$me) {
        api_fail(404, 'No student profile linked to your account.');
    }

    $studentId = (int) $me['id'];

} elseif ($studentId <= 0) {

    api_fail(400, 'student_id is required.');
}

if ($role === 'teacher') {
    api_assert_exam_access($examId);
}

if ($role !== 'admin') {
    api_assert_student_access($studentId);
}

// ---- existence ----
$examStmt = $pdo->prepare(
    'SELECT id, name, class_id, type, status FROM exams WHERE id = :id LIMIT 1'
);
$examStmt->execute([':id' => $examId]);
$exam = $examStmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    api_fail(404, 'Exam not found.');
}

$studentStmt = $pdo->prepare(
    'SELECT id, name, student_id, status FROM students WHERE id = :id LIMIT 1'
);
$studentStmt->execute([':id' => $studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    api_fail(404, 'Student not found.');
}

// ---- canonical result ----
$markObj = new Mark($pdo);

$result = $markObj->calculateResult($examId, $studentId);

if ($result === null) {
    api_fail(404, 'No marks are recorded for this student in that exam.');
}

api_ok([
    'result'  => $result,
    'exam'    => [
        'id'       => (int) $exam['id'],
        'name'     => (string) $exam['name'],
        'type'     => (string) $exam['type'],
        'class_id' => (int) $exam['class_id'],
        'status'   => (string) $exam['status'],
    ],
    'student' => [
        'id'         => (int) $student['id'],
        'student_id' => (string) $student['student_id'],
        'name'       => (string) $student['name'],
        'status'     => (string) $student['status'],
    ],
]);
