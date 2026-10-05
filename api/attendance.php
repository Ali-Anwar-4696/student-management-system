<?php

declare(strict_types=1);

/*
| GET api/attendance.php
| params: student_id (required; defaults to own record for students),
|         from, to (Y-m-d), status (present|absent|late|leave),
|         page, per_page (max 100)
|
| Ownership is enforced by api_assert_student_access() for every role.
*/

require_once __DIR__ . '/helpers.php';

api_require_get();
api_require_auth();

$role = currentRole();

if (!in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {
    api_fail(403, 'You do not have access to this endpoint.');
}

$studentId = api_int('student_id');

// Students may omit student_id and get their own record.
if ($studentId <= 0 && $role === 'student') {
    $me = current_student();
    $studentId = $me ? (int) $me['id'] : 0;
}

if ($studentId <= 0) {
    api_fail(400, 'student_id is required.');
}

// Ownership / authorization for the requested student.
api_assert_student_access($studentId);

// ---- filters ----
$normalizeDate = static function (string $value): string {
    if ($value === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $value);

    return $dt !== false && $dt->format('Y-m-d') === $value
        ? $value
        : '';
};

$from = $normalizeDate(api_str('from'));
$to = $normalizeDate(api_str('to'));

if (api_str('from') !== '' && $from === '') {
    api_fail(400, 'from must be a valid YYYY-MM-DD date.');
}

if (api_str('to') !== '' && $to === '') {
    api_fail(400, 'to must be a valid YYYY-MM-DD date.');
}

$status = api_str('status');

if ($status !== '' && !in_array($status, ['present', 'absent', 'late', 'leave'], true)) {
    api_fail(400, 'status must be one of: present, absent, late, leave.');
}

$pag = api_pagination(api_int('page', 1), api_int('per_page', 50));

$pdo = db();

$where = ' WHERE a.student_id = :sid';
$params = [':sid' => $studentId];

if ($from !== '') {
    $where .= ' AND a.date >= :from';
    $params[':from'] = $from;
}

if ($to !== '') {
    $where .= ' AND a.date <= :to';
    $params[':to'] = $to;
}

if ($status !== '') {
    $where .= ' AND a.status = :status';
    $params[':status'] = $status;
}

// ---- rows ----
$sql = "
    SELECT
        a.date,
        a.status,
        a.class_id,
        a.section_id,
        sub.name AS subject_name
    FROM attendance a
    LEFT JOIN subjects sub ON sub.id = a.subject_id
    {$where}
    ORDER BY a.date DESC, a.id DESC
";

$sqlLimited = $sql . ' LIMIT :lim OFFSET :off';

$stmt = $pdo->prepare($sqlLimited);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->bindValue(':lim', $pag['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':off', $pag['offset'], PDO::PARAM_INT);
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- status summary for the same filter set ----
$sumStmt = $pdo->prepare("
    SELECT a.status, COUNT(*) AS total
    FROM attendance a
    {$where}
    GROUP BY a.status
");

$sumStmt->execute($params);

$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'leave' => 0];

foreach ($sumStmt->fetchAll(PDO::FETCH_ASSOC) as $sumRow) {
    $summary[(string) $sumRow['status']] = (int) $sumRow['total'];
}

$summary['total'] = array_sum($summary);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a {$where}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

api_ok([
    'attendance' => $rows,
    'summary'    => $summary,
], [
    'total'    => $total,
    'page'     => $pag['page'],
    'per_page' => $pag['per_page'],
]);
