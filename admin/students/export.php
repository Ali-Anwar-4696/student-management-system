<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Students CSV export (admin, read-only download)
|--------------------------------------------------------------------------
|
| Mirrors the filters of admin/students/index.php (search, status,
| class_id) so "Export CSV" downloads exactly what the table shows,
| without the pagination limit.
|
| GET-only by nature (no state is changed), but still requires an
| authenticated admin session via requireAdmin().
|
*/

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';
require_once __DIR__ . '/../../includes/export.php';

$pdo = db();

$studentManager = new Student($pdo);

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$classId = trim((string) ($_GET['class_id'] ?? ''));

$students = $studentManager->getStudents(
    $search,
    $status,
    $classId,
    1000000,
    0
);

export_csv(
    'students_' . date('Ymd_His') . '.csv',
    [
        'Student ID',
        'Name',
        "Father's Name",
        'Email',
        'Phone',
        'Class',
        'Section',
        'Admission Date',
        'Status',
        'Created At',
    ],
    (static function () use ($students): iterable {
        foreach ($students as $s) {
            yield [
                $s['student_id'] ?? '',
                $s['name'] ?? '',
                $s['father_name'] ?? '',
                $s['email'] ?? '',
                $s['phone'] ?? '',
                $s['class_name'] ?? '',
                $s['section_name'] ?? '',
                $s['admission_date'] ?? '',
                $s['status'] ?? '',
                $s['created_at'] ?? '',
            ];
        }
    })()
);
