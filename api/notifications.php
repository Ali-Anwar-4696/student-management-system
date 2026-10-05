<?php

declare(strict_types=1);

/*
| GET api/notifications.php
| Own notifications for any authenticated role.
| params: status (all|read|unread, default all), search
|
| Read-only (marking read stays a POST action on the web page, which
| carries the CSRF token).
*/

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/Notification.php';

api_require_get();
api_require_auth();

$role = currentRole();

if (!in_array($role, ['admin', 'teacher', 'student', 'parent'], true)) {
    api_fail(403, 'You do not have access to this endpoint.');
}

$status = api_str('status');

if ($status === '') {
    $status = 'all';
}

if (!in_array($status, ['all', 'read', 'unread'], true)) {
    api_fail(400, 'status must be one of: all, read, unread.');
}

$search = api_str('search');

$userId = (int) ($_SESSION['user_id'] ?? 0);

$notificationObj = new Notification(db());

$rows = $notificationObj->forUser($userId, $role, $status, $search);

// Slim payload: keep only what a client needs.
$notifications = [];

foreach ($rows as $row) {
    $notifications[] = [
        'id'          => (int) $row['id'],
        'title'       => (string) $row['title'],
        'message'     => (string) $row['message'],
        'target_role' => (string) $row['target_role'],
        'is_read'     => (int) ($row['is_read'] ?? 0) === 1,
        'created_at'  => (string) ($row['created_at'] ?? ''),
    ];
}

api_ok(['notifications' => $notifications], [
    'count'  => count($notifications),
    'status' => $status,
]);
