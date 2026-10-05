<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API root — self-documenting endpoint catalog
|--------------------------------------------------------------------------
| Read-only JSON API over the same session auth the web pages use.
| Log in through auth/login.php; send the session cookie with every
| request. All endpoints are GET and return the envelope:
|
|   success: { "success": true,  "data": ..., "meta": {...}? }
|   error:   { "success": false, "message": "..." }
|
*/

require_once __DIR__ . '/helpers.php';

api_require_auth();

api_ok([
    'app'    => (string) app_config('name', 'StudentHub'),
    'type'   => 'json-api',
    'auth'   => 'Session cookie — log in via auth/login.php. '
        . '401 when unauthenticated or expired, 403 when the role may '
        . 'not access the endpoint.',
    'method' => 'GET only.',
    'endpoints' => [
        [
            'path'        => 'api/classes.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Classes with sections. Admin: all; teacher: '
                . 'assigned classes; student: own class; parent: children\'s classes.',
        ],
        [
            'path'        => 'api/subjects.php',
            'roles'       => ['admin', 'teacher'],
            'description' => 'Active subjects.',
        ],
        [
            'path'        => 'api/students.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Student records. Admin: filters + pagination; '
                . 'teacher: own classes; student: self; parent: linked children.',
        ],
        [
            'path'        => 'api/attendance.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Attendance rows for one student (student_id, '
                . 'from, to, status, page, per_page). Ownership enforced.',
        ],
        [
            'path'        => 'api/marks.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Recorded marks for an exam (exam_id required, '
                . 'student_id optional for admin/teacher).',
        ],
        [
            'path'        => 'api/results.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Computed result (total, percentage, grade, '
                . 'pass/fail + per-subject rows) for exam_id + student_id.',
        ],
        [
            'path'        => 'api/fees.php',
            'roles'       => ['admin', 'student', 'parent'],
            'description' => 'Fee records with paid/remaining amounts. '
                . 'Admin: filters; student: own; parent: linked children.',
        ],
        [
            'path'        => 'api/timetable.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Weekly timetable slots. Admin: class_id; '
                . 'teacher/student: own; parent: children\'s classes.',
        ],
        [
            'path'        => 'api/notifications.php',
            'roles'       => ['admin', 'teacher', 'student', 'parent'],
            'description' => 'Own notifications (status=all|read|unread, '
                . 'search, limit).',
        ],
    ],
]);
