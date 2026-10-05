<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/classes/Notification.php';

requireAuth();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = (string) ($_SESSION['user_role'] ?? '');

if ($userId <= 0 || $userRole === '') {
    http_response_code(403);
    exit('User session not found.');
}

$pdo = db();
$notificationModel = new Notification($pdo);

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function notificationTimeAgo(string $date): string
{
    $time = strtotime($date);

    if ($time === false) {
        return '';
    }

    $diff = time() - $time;

    if ($diff < 60) {
        return 'Just now';
    }

    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }

    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }

    if ($diff < 172800) {
        return 'Yesterday';
    }

    if ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    }

    return date('d M Y', $time);
}

function notificationGroup(string $date): string
{
    $time = strtotime($date);

    if ($time === false) {
        return 'older';
    }

    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    if ($time >= $today) {
        return 'today';
    }

    if ($time >= $yesterday) {
        return 'yesterday';
    }

    return 'older';
}

function notificationGroupLabel(string $group): string
{
    return match ($group) {
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        default => 'Earlier',
    };
}

function notificationTypeIcon(string $type): string
{
    return match ($type) {
        'announcement' => '📢',
        'assignment' => '📝',
        'submission' => '📤',
        'attendance' => '📅',
        'exam' => '📚',
        'marks' => '📊',
        'fee' => '💳',
        'system' => '⚙️',
        default => '🔔',
    };
}

function notificationPriorityClass(string $priority): string
{
    return match ($priority) {
        'urgent' => 'priority-urgent',
        'high' => 'priority-high',
        'low' => 'priority-low',
        default => 'priority-normal',
    };
}

function notificationTypeLabel(string $type): string
{
    return match ($type) {
        'announcement' => 'Announcement',
        'assignment' => 'Assignment',
        'submission' => 'Submission',
        'attendance' => 'Attendance',
        'exam' => 'Exam',
        'marks' => 'Marks',
        'fee' => 'Fee',
        'system' => 'System',
        default => 'General',
    };
}

function notificationPriorityLabel(string $priority): string
{
    return match ($priority) {
        'urgent' => 'Urgent',
        'high' => 'High',
        'low' => 'Low',
        default => 'Normal',
    };
}

function notificationTargetLabel(string $role): string
{
    return match ($role) {
        'all' => 'All Users',
        'admin' => 'Admins',
        'teacher' => 'Teachers',
        'student' => 'Students',
        default => ucfirst($role),
    };
}

function notificationSenderLabel(?string $senderType): string
{
    if (!$senderType || $senderType === 'system') {
        return 'System';
    }

    return ucfirst($senderType);
}

function roleDashboard(): string
{
    return match ($_SESSION['user_role'] ?? '') {
        'admin' => 'admin/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        default => 'auth/login.php',
    };
}

function roleName(): string
{
    return match ($_SESSION['user_role'] ?? '') {
        'admin' => 'Admin Portal',
        'teacher' => 'Teacher Portal',
        'student' => 'Student Portal',
        default => 'Portal',
    };
}

/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $action = (string) ($_POST['action'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | MARK SINGLE AS READ
    |--------------------------------------------------------------------------
    */
    if ($action === 'mark_read') {

        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        if (
            $notificationId > 0 &&
            $notificationModel->canAccess(
                $notificationId,
                $userId,
                $userRole
            )
        ) {
            $notificationModel->markRead(
                $notificationId,
                $userId
            );
        }

        redirect('notifications.php');
    }

    /*
    |--------------------------------------------------------------------------
    | MARK SINGLE AS UNREAD
    |--------------------------------------------------------------------------
    */
    if ($action === 'mark_unread') {

        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        if (
            $notificationId > 0 &&
            $notificationModel->canAccess(
                $notificationId,
                $userId,
                $userRole
            )
        ) {
            $notificationModel->markUnread(
                $notificationId,
                $userId
            );
        }

        redirect('notifications.php');
    }

    /*
    |--------------------------------------------------------------------------
    | MARK ALL AS READ
    |--------------------------------------------------------------------------
    */
    if ($action === 'mark_all_read') {

        $notificationModel->markAllRead(
            $userId,
            $userRole
        );

        redirect('notifications.php');
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE NOTIFICATION
    |--------------------------------------------------------------------------
    */
    if ($action === 'delete') {

        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        if ($notificationId > 0) {
            $notificationModel->delete(
                $notificationId,
                $userId,
                $userRole
            );
        }

        redirect('notifications.php');
    }
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$statusFilter = trim(
    (string) ($_GET['status'] ?? '')
);

$search = trim(
    (string) ($_GET['search'] ?? '')
);

if (!in_array($statusFilter, ['read', 'unread'], true)) {
    $statusFilter = '';
}

/*
|--------------------------------------------------------------------------
| FETCH NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$allNotifications = $notificationModel->forUser(
    $userId,
    $userRole,
    $statusFilter,
    $search
);

/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$totalNotifications = $notificationModel->totalCount(
    $userId,
    $userRole
);

$unreadCount = $notificationModel->unreadCount(
    $userId,
    $userRole
);

$readCount = $notificationModel->readCount(
    $userId,
    $userRole
);

/*
|--------------------------------------------------------------------------
| GROUP NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$grouped = [
    'today' => [],
    'yesterday' => [],
    'older' => [],
];

foreach ($allNotifications as $notification) {

    $group = notificationGroup(
        (string) $notification['created_at']
    );

    $grouped[$group][] = $notification;
}

$userName = (string) ($_SESSION['name'] ?? 'User');
$userInitial = strtoupper(
    substr(trim($userName), 0, 1)
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    >

    <title>Notifications</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #e0e7ff;
            --primary-soft: #eef2ff;

            --success: #10b981;
            --success-soft: #d1fae5;

            --warning: #f59e0b;
            --warning-soft: #fef3c7;

            --danger: #ef4444;
            --danger-soft: #fee2e2;

            --gray-900: #111827;
            --gray-800: #1f2937;
            --gray-700: #374151;
            --gray-600: #4b5563;
            --gray-500: #6b7280;
            --gray-400: #9ca3af;
            --gray-300: #d1d5db;
            --gray-200: #e5e7eb;
            --gray-100: #f3f4f6;
            --gray-50: #f9fafb;

            --white: #ffffff;

            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 16px;

            --shadow-sm:
                0 1px 2px 0 rgb(0 0 0 / 0.05);

            --shadow:
                0 1px 3px 0 rgb(0 0 0 / 0.1),
                0 1px 2px -1px rgb(0 0 0 / 0.1);

            --shadow-lg:
                0 10px 15px -3px rgb(0 0 0 / 0.1),
                0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family:
                'Inter',
                system-ui,
                -apple-system,
                sans-serif;

            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        button,
        input,
        select {
            font: inherit;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* HEADER */

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .page-header-left {
            flex: 1;
            min-width: 0;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--gray-500);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb-sep {
            color: var(--gray-400);
        }

        .breadcrumb-current {
            color: var(--primary);
            font-weight: 600;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;
            color: var(--gray-900);
            letter-spacing: -0.025em;
            line-height: 1.2;
        }

        .page-desc {
            margin-top: 6px;
            color: var(--gray-500);
            font-size: 14px;
            max-width: 560px;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 999px;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );
            color: var(--white);
            display: grid;
            place-items: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
            color: var(--gray-800);
        }

        .user-role {
            font-size: 12px;
            color: var(--gray-500);
            text-transform: capitalize;
        }

        /* STATS */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 20px;
            position: relative;
            overflow: hidden;
            transition:
                transform .2s,
                box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
            opacity: 0;
            transition: opacity .2s;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card.unread::before {
            background: var(--danger);
        }

        .stat-card.read::before {
            background: var(--success);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            display: grid;
            place-items: center;
            font-size: 18px;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .stat-card.unread .stat-icon-wrap {
            background: var(--danger-soft);
            color: #dc2626;
        }

        .stat-card.read .stat-icon-wrap {
            background: var(--success-soft);
            color: #059669;
        }

        .stat-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 999px;
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .stat-value {
            font-size: 30px;
            font-weight: 800;
            color: var(--gray-900);
            line-height: 1;
        }

        .stat-label {
            margin-top: 6px;
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 500;
        }

        /* TOOLBAR */

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-form {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            width: 100%;
        }

        .filter-group.search {
            flex: 1;
            min-width: 240px;
        }

        .filter-group.select {
            min-width: 160px;
        }

        .filter-input-wrap {
            position: relative;
        }

        .filter-input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            height: 42px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            padding: 0 12px;
            font-size: 14px;
            color: var(--gray-800);
            background: var(--white);
            outline: none;
            transition:
                border-color .15s,
                box-shadow .15s;
        }

        .form-control.search {
            padding-left: 38px;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }

        select.form-control {
            cursor: pointer;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 40px;
            padding: 0 15px;
            border-radius: var(--radius);
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
            transition: all .15s;
            background: none;
        }

        .btn-primary {
            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );
            color: var(--white);
            box-shadow:
                0 4px 12px rgba(99, 102, 241, .25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border-color: var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);
        }

        .btn-sm {
            min-height: 36px;
            padding: 0 12px;
            font-size: 12px;
        }

        /* LIST */

        .notif-list {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .notif-group-header {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 14px 24px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            font-size: 12px;
            font-weight: 700;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .group-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--primary);
        }

        .group-count {
            margin-left: auto;
            font-size: 11px;
            color: var(--gray-400);
            font-weight: 600;
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 24px;
            border-bottom: 1px solid var(--gray-100);
            transition: background .12s;
            position: relative;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-item:hover {
            background: #fafbff;
        }

        .notif-item.unread {
            background: #fafbff;
            border-left: 3px solid var(--primary);
        }

        .notif-read-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            flex-shrink: 0;
            margin-top: 8px;
        }

        .notif-item.read .notif-read-dot {
            background: var(--gray-300);
        }

        .notif-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            display: grid;
            place-items: center;
            font-size: 20px;
            flex-shrink: 0;
            background:
                linear-gradient(
                    135deg,
                    var(--primary-soft),
                    var(--primary-light)
                );
        }

        .notif-content {
            flex: 1;
            min-width: 0;
        }

        .notif-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .notif-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.4;
            word-break: break-word;
        }

        .notif-item.unread .notif-title {
            color: var(--primary-dark);
        }

        .notif-time {
            font-size: 12px;
            color: var(--gray-500);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .notif-message {
            margin-top: 5px;
            font-size: 13px;
            color: var(--gray-600);
            line-height: 1.6;
            word-break: break-word;
        }

        .notif-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 9px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .badge-type {
            background: var(--primary-soft);
            color: var(--primary-dark);
        }

        .badge-target {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .priority-normal {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        .priority-low {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .priority-high {
            background: var(--warning-soft);
            color: #b45309;
        }

        .priority-urgent {
            background: var(--danger-soft);
            color: #b91c1c;
        }

        .notif-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            opacity: 0;
            transition: opacity .15s;
        }

        .notif-item:hover .notif-actions {
            opacity: 1;
        }

        .action-form {
            margin: 0;
        }

        .notif-action-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--gray-500);
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 14px;
        }

        .notif-action-btn:hover {
            background: var(--gray-100);
            color: var(--gray-900);
        }

        .notif-action-btn.read:hover {
            background: var(--success-soft);
            color: var(--success);
        }

        .notif-action-btn.unread:hover {
            background: var(--warning-soft);
            color: var(--warning);
        }

        .notif-action-btn.delete:hover {
            background: var(--danger-soft);
            color: var(--danger);
        }

        /* ACTION URL */

        .notif-open {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            color: var(--primary);
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .notif-open:hover {
            color: var(--primary-dark);
        }

        /* MOBILE */

        .mobile-notifs {
            display: none;
            padding: 12px;
        }

        .m-notif-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 15px;
            margin-bottom: 12px;
        }

        .m-notif-card:last-child {
            margin-bottom: 0;
        }

        .m-notif-card.unread {
            border-left: 3px solid var(--primary);
            background: #fafbff;
        }

        .m-header {
            display: flex;
            align-items: flex-start;
            gap: 11px;
        }

        .m-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1.4;
        }

        .m-time {
            font-size: 11px;
            color: var(--gray-500);
            margin-top: 2px;
        }

        .m-message {
            margin-top: 11px;
            font-size: 13px;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .m-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--gray-100);
        }

        .m-actions {
            display: flex;
            gap: 5px;
        }

        /* EMPTY */

        .empty-state {
            text-align: center;
            padding: 80px 24px;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: var(--radius-lg);
            background: var(--primary-soft);
            display: grid;
            place-items: center;
            font-size: 34px;
            margin: 0 auto 18px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: var(--gray-900);
        }

        .empty-state p {
            margin: 8px auto 0;
            max-width: 420px;
            color: var(--gray-500);
            font-size: 14px;
            line-height: 1.6;
        }

        /* RESPONSIVE */

        @media (max-width: 900px) {

            .page-wrapper {
                padding: 20px;
            }

            .toolbar {
                align-items: stretch;
            }

            .toolbar-right {
                width: 100%;
            }

            .toolbar-right .btn {
                width: 100%;
            }
        }

        @media (max-width: 767px) {

            .page-wrapper {
                padding: 15px;
            }

            .page-title {
                font-size: 23px;
            }

            .user-pill {
                width: 100%;
            }

            .stats-grid {
                gap: 9px;
            }

            .stat-card {
                padding: 15px 12px;
            }

            .stat-value {
                font-size: 22px;
            }

            .stat-label {
                font-size: 10px;
            }

            .stat-icon-wrap {
                width: 34px;
                height: 34px;
                font-size: 15px;
            }

            .notif-item {
                display: none;
            }

            .mobile-notifs {
                display: block;
            }

            .notif-group-header {
                padding: 12px 15px;
            }

            .filter-group.search,
            .filter-group.select {
                width: 100%;
                min-width: 0;
            }

            .form-control {
                height: 44px;
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {

            .page-wrapper {
                padding: 11px;
            }

            .stats-grid {
                gap: 7px;
            }

            .stat-card {
                padding: 13px 9px;
            }

            .stat-value {
                font-size: 19px;
            }

            .stat-label {
                font-size: 9px;
            }

            .stat-icon-wrap {
                width: 30px;
                height: 30px;
                font-size: 13px;
            }

            .m-notif-card {
                padding: 13px;
            }

            .m-footer {
                align-items: flex-end;
                flex-direction: column;
            }

            .m-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }

        @media (hover: none) and (pointer: coarse) {

            .notif-actions {
                opacity: 1;
            }

            .notif-action-btn {
                width: 36px;
                height: 36px;
                background: var(--gray-50);
            }
        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <!-- HEADER -->

    <header class="page-header">

        <div class="page-header-left">

            <nav class="breadcrumb">

                <a href="<?= e(url(roleDashboard())) ?>">
                    <?= e(roleName()) ?>
                </a>

                <span class="breadcrumb-sep">/</span>

                <span class="breadcrumb-current">
                    Notifications
                </span>

            </nav>

            <h1 class="page-title">
                Notifications
            </h1>

            <p class="page-desc">
                Stay updated with announcements, reminders and system notifications.
            </p>

        </div>

        <div class="user-pill">

            <div class="user-avatar">
                <?= e($userInitial) ?>
            </div>

            <div>

                <div class="user-name">
                    <?= e($userName) ?>
                </div>

                <div class="user-role">
                    <?= e($userRole) ?>
                </div>

            </div>

        </div>

    </header>

    <!-- STATS -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    🔔
                </div>

                <span class="stat-badge">
                    All
                </span>

            </div>

            <div class="stat-value">
                <?= $totalNotifications ?>
            </div>

            <div class="stat-label">
                Total Notifications
            </div>

        </div>

        <div class="stat-card unread">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    ●
                </div>

                <span class="stat-badge">
                    New
                </span>

            </div>

            <div class="stat-value">
                <?= $unreadCount ?>
            </div>

            <div class="stat-label">
                Unread
            </div>

        </div>

        <div class="stat-card read">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    ✓
                </div>

                <span class="stat-badge">
                    Done
                </span>

            </div>

            <div class="stat-value">
                <?= $readCount ?>
            </div>

            <div class="stat-label">
                Read
            </div>

        </div>

    </div>

    <!-- TOOLBAR -->

    <div class="toolbar">

        <form
            method="get"
            action="<?= e(url('notifications.php')) ?>"
            class="filter-form"
        >

            <div class="filter-group search">

                <div class="filter-input-wrap">

                    <span class="filter-input-icon">
                        🔎
                    </span>

                    <input
                        type="search"
                        name="search"
                        class="form-control search"
                        value="<?= e($search) ?>"
                        placeholder="Search notifications..."
                        autocomplete="off"
                    >

                </div>

            </div>

            <div class="filter-group select">

                <select
                    name="status"
                    class="form-control"
                    onchange="this.form.submit()"
                >

                    <option value="">
                        All Status
                    </option>

                    <option
                        value="unread"
                        <?= $statusFilter === 'unread' ? 'selected' : '' ?>
                    >
                        Unread
                    </option>

                    <option
                        value="read"
                        <?= $statusFilter === 'read' ? 'selected' : '' ?>
                    >
                        Read
                    </option>

                </select>

            </div>

            <button
                type="submit"
                class="btn btn-secondary btn-sm"
            >
                Filter
            </button>

        </form>

        <?php if ($unreadCount > 0): ?>

            <div class="toolbar-right">

                <form
                    method="post"
                    action="<?= e(url('notifications.php')) ?>"
                    onsubmit="return confirm('Mark all notifications as read?');"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >

                    <button
                        type="submit"
                        class="btn btn-primary btn-sm"
                    >
                        ✓ Mark all as read
                    </button>

                </form>

            </div>

        <?php endif; ?>

    </div>

    <!-- NOTIFICATION LIST -->

    <div class="notif-list">

        <?php if (empty($allNotifications)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    🔔
                </div>

                <h3>
                    No notifications
                </h3>

                <p>

                    <?php if ($search !== '' || $statusFilter !== ''): ?>

                        No notifications match your current filters.
                        Try clearing the filters.

                    <?php else: ?>

                        You have no notifications at the moment.
                        New notifications will appear here when they arrive.

                    <?php endif; ?>

                </p>

                <?php if ($search !== '' || $statusFilter !== ''): ?>

                    <a
                        href="<?= e(url('notifications.php')) ?>"
                        class="btn btn-secondary"
                        style="margin-top:16px;"
                    >
                        Clear Filters
                    </a>

                <?php endif; ?>

            </div>

        <?php else: ?>

            <?php foreach (['today', 'yesterday', 'older'] as $groupKey): ?>

                <?php if (empty($grouped[$groupKey])) {
                    continue;
                } ?>

                <div class="notif-group-header">

                    <span class="group-dot"></span>

                    <?= e(notificationGroupLabel($groupKey)) ?>

                    <span class="group-count">

                        <?= count($grouped[$groupKey]) ?>

                        notification<?= count($grouped[$groupKey]) !== 1 ? 's' : '' ?>

                    </span>

                </div>

                <!-- DESKTOP -->

                <?php foreach ($grouped[$groupKey] as $n): ?>

                    <?php

                    $isUnread = !(bool) ($n['is_read'] ?? false);

                    $type = (string) ($n['type'] ?? 'general');

                    $priority = (string) ($n['priority'] ?? 'normal');

                    $targetRole = (string) ($n['target_role'] ?? 'all');

                    $senderType = $n['sender_type'] ?? 'system';

                    $actionUrl = trim(
                        (string) ($n['action_url'] ?? '')
                    );

                    ?>

                    <div
                        class="notif-item <?= $isUnread ? 'unread' : 'read' ?>"
                    >

                        <div class="notif-read-dot"></div>

                        <div class="notif-icon-wrap">
                            <?= e(notificationTypeIcon($type)) ?>
                        </div>

                        <div class="notif-content">

                            <div class="notif-header">

                                <div class="notif-title">
                                    <?= e((string) $n['title']) ?>
                                </div>

                                <div class="notif-time">
                                    <?= e(notificationTimeAgo((string) $n['created_at'])) ?>
                                </div>

                            </div>

                            <div class="notif-message">
                                <?= e((string) $n['message']) ?>
                            </div>

                            <div class="notif-meta">

                                <span class="badge badge-type">
                                    <?= e(notificationTypeLabel($type)) ?>
                                </span>

                                <span class="badge <?= e(notificationPriorityClass($priority)) ?>">
                                    <?= e(notificationPriorityLabel($priority)) ?>
                                </span>

                                <span class="badge badge-target">
                                    <?= e(notificationTargetLabel($targetRole)) ?>
                                </span>

                                <span class="badge badge-target">
                                    From <?= e(notificationSenderLabel($senderType)) ?>
                                </span>

                            </div>

                            <?php if ($actionUrl !== ''): ?>

                                <a
                                    href="<?= e($actionUrl) ?>"
                                    class="notif-open"
                                >
                                    Open related page →
                                </a>

                            <?php endif; ?>

                        </div>

                        <div class="notif-actions">

                            <?php if ($isUnread): ?>

                                <form
                                    method="post"
                                    class="action-form"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_read"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int) $n['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="notif-action-btn read"
                                        title="Mark as read"
                                    >
                                        ✓
                                    </button>

                                </form>

                            <?php else: ?>

                                <form
                                    method="post"
                                    class="action-form"
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_unread"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int) $n['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="notif-action-btn unread"
                                        title="Mark as unread"
                                    >
                                        ●
                                    </button>

                                </form>

                            <?php endif; ?>

                            <form
                                method="post"
                                class="action-form"
                                onsubmit="return confirm('Delete this notification permanently?');"
                            >

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?= (int) $n['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="notif-action-btn delete"
                                    title="Delete"
                                >
                                    🗑
                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

                <!-- MOBILE -->

                <div class="mobile-notifs">

                    <?php foreach ($grouped[$groupKey] as $n): ?>

                        <?php

                        $isUnread = !(bool) ($n['is_read'] ?? false);

                        $type = (string) ($n['type'] ?? 'general');

                        $priority = (string) ($n['priority'] ?? 'normal');

                        $targetRole = (string) ($n['target_role'] ?? 'all');

                        $senderType = $n['sender_type'] ?? 'system';

                        $actionUrl = trim(
                            (string) ($n['action_url'] ?? '')
                        );

                        ?>

                        <div
                            class="m-notif-card <?= $isUnread ? 'unread' : '' ?>"
                        >

                            <div class="m-header">

                                <div class="notif-icon-wrap">
                                    <?= e(notificationTypeIcon($type)) ?>
                                </div>

                                <div style="flex:1;min-width:0;">

                                    <div class="m-title">
                                        <?= e((string) $n['title']) ?>
                                    </div>

                                    <div class="m-time">
                                        <?= e(notificationTimeAgo((string) $n['created_at'])) ?>
                                    </div>

                                </div>

                            </div>

                            <div class="m-message">
                                <?= e((string) $n['message']) ?>
                            </div>

                            <div class="notif-meta">

                                <span class="badge badge-type">
                                    <?= e(notificationTypeLabel($type)) ?>
                                </span>

                                <span class="badge <?= e(notificationPriorityClass($priority)) ?>">
                                    <?= e(notificationPriorityLabel($priority)) ?>
                                </span>

                                <span class="badge badge-target">
                                    <?= e(notificationTargetLabel($targetRole)) ?>
                                </span>

                            </div>

                            <?php if ($actionUrl !== ''): ?>

                                <a
                                    href="<?= e($actionUrl) ?>"
                                    class="notif-open"
                                >
                                    Open related page →
                                </a>

                            <?php endif; ?>

                            <div class="m-footer">

                                <span class="badge badge-target">
                                    From <?= e(notificationSenderLabel($senderType)) ?>
                                </span>

                                <div class="m-actions">

                                    <?php if ($isUnread): ?>

                                        <form
                                            method="post"
                                            class="action-form"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="mark_read"
                                            >

                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= (int) $n['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="notif-action-btn read"
                                                title="Mark as read"
                                            >
                                                ✓
                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <form
                                            method="post"
                                            class="action-form"
                                        >

                                            <?= csrf_field() ?>

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="mark_unread"
                                            >

                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= (int) $n['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="notif-action-btn unread"
                                                title="Mark as unread"
                                            >
                                                ●
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                    <form
                                        method="post"
                                        class="action-form"
                                        onsubmit="return confirm('Delete this notification permanently?');"
                                    >

                                        <?= csrf_field() ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="notification_id"
                                            value="<?= (int) $n['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="notif-action-btn delete"
                                            title="Delete"
                                        >
                                            🗑
                                        </button>

                                    </form>

                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>