<?php

declare(strict_types=1);

/*
| GET api/subjects.php
| admin + teacher: active subjects (id, name, code).
*/

require_once __DIR__ . '/helpers.php';

api_require_get();
api_require_auth();
api_require_role('admin', 'teacher');

$stmt = db()->query("
    SELECT id, name, code, status
    FROM subjects
    WHERE status = 'active'
    ORDER BY name ASC
");

$subjects = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $subjects[] = [
        'id'     => (int) $row['id'],
        'name'   => (string) $row['name'],
        'code'   => (string) ($row['code'] ?? ''),
        'status' => (string) $row['status'],
    ];
}

api_ok(['subjects' => $subjects], ['count' => count($subjects)]);
