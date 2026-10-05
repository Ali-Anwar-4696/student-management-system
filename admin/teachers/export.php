<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Teachers CSV export (admin, read-only download)
|--------------------------------------------------------------------------
|
| Mirrors the filters of admin/teachers/index.php (q, status) so
| "Export CSV" downloads exactly what the table shows, without the
| pagination limit.
|
| GET-only by nature (no state is changed), but still requires an
| authenticated admin session via requireAdmin().
|
*/

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';
require_once __DIR__ . '/../../includes/export.php';

$pdo = db();

$teacherManager = new Teacher($pdo);

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$teachers = $teacherManager->getTeachers(
    $search,
    $status,
    1000000,
    0
);

export_csv(
    'teachers_' . date('Ymd_His') . '.csv',
    [
        'Teacher ID',
        'Name',
        'Email',
        'Phone',
        'Address',
        'Joining Date',
        'Status',
        'Created At',
    ],
    (static function () use ($teachers): iterable {
        foreach ($teachers as $t) {
            yield [
                $t['teacher_id'] ?? '',
                $t['name'] ?? '',
                $t['email'] ?? '',
                $t['phone'] ?? '',
                $t['address'] ?? '',
                $t['joining_date'] ?? '',
                $t['status'] ?? '',
                $t['created_at'] ?? '',
            ];
        }
    })()
);
