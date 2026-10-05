<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/role_check.php';

requireAdmin();

$database = new Database();
$pdo = $database->connect();

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

$csrfToken = getCsrfToken();

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function notificationUserExists(PDO $pdo, int $userId): bool
{
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :id
          AND status = 'active'
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $userId
    ]);

    return $stmt->fetch() !== false;
}

/*
|--------------------------------------------------------------------------
| CREATE NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    try {

        if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token. Please refresh the page.');
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $targetRole = trim((string) ($_POST['target_role'] ?? 'all'));
        $userIdInput = trim((string) ($_POST['user_id'] ?? ''));

        $allowedTargets = [
            'all',
            'student',
            'teacher',
            'parent',
            'user'
        ];

        $allowedTypes = [
            'general',
            'system',
            'announcement',
            'assignment',
            'submission',
            'attendance',
            'exam',
            'marks',
            'fee'
        ];

        $allowedPriorities = [
            'low',
            'normal',
            'high',
            'urgent'
        ];

        $notificationType = trim(
            (string) (
                $_POST['type'] ?? 'general'
            )
        );

        $priority = trim(
            (string) (
                $_POST['priority'] ?? 'normal'
            )
        );

        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($title === '') {
            $errors[] = 'Notification title is required.';
        } elseif (mb_strlen($title) > 255) {
            $errors[] = 'Notification title cannot exceed 255 characters.';
        }

        if ($message === '') {
            $errors[] = 'Notification message is required.';
        }

        if (!in_array($targetRole, $allowedTargets, true)) {
            $errors[] = 'Invalid notification target.';
        }

        if (!in_array($notificationType, $allowedTypes, true)) {
            $errors[] = 'Invalid notification type.';
        }

        if (!in_array($priority, $allowedPriorities, true)) {
            $errors[] = 'Invalid notification priority.';
        }

        $userId = null;

        if ($targetRole === 'user') {

            if ($userIdInput === '' || !ctype_digit($userIdInput)) {
                $errors[] = 'Please select a specific user.';
            } else {

                $userId = (int) $userIdInput;

                if (!notificationUserExists($pdo, $userId)) {
                    $errors[] = 'Selected user does not exist or is inactive.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        if (!$errors) {

            $createdBy = isset($_SESSION['user_id'])
                ? (int) $_SESSION['user_id']
                : null;

            $stmt = $pdo->prepare("
                INSERT INTO notifications
                (
                    user_id,
                    target_role,
                    title,
                    message,
                    type,
                    priority,
                    created_by
                )
                VALUES
                (
                    :user_id,
                    :target_role,
                    :title,
                    :message,
                    :type,
                    :priority,
                    :created_by
                )
            ");

            $stmt->execute([
                ':user_id' => $userId,
                ':target_role' => $targetRole,
                ':title' => $title,
                ':message' => $message,
                ':type' => $notificationType,
                ':priority' => $priority,
                ':created_by' => $createdBy
            ]);

            $success = 'Notification created successfully.';
        }

    } catch (Throwable $e) {

        if (!$errors) {
            $errors[] = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {

    try {

        if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Invalid security token. Please refresh the page.');
        }

        $notificationId = trim((string) ($_POST['notification_id'] ?? ''));

        if ($notificationId === '' || !ctype_digit($notificationId)) {
            throw new RuntimeException('Invalid notification ID.');
        }

        $notificationId = (int) $notificationId;

        $stmt = $pdo->prepare("
            DELETE FROM notifications
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $notificationId
        ]);

        if ($stmt->rowCount() > 0) {
            $success = 'Notification deleted successfully.';
        } else {
            $errors[] = 'Notification not found.';
        }

    } catch (Throwable $e) {

        if (!$errors) {
            $errors[] = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH & FILTER
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$targetFilter = trim((string) ($_GET['target_role'] ?? ''));

$notifications = [];

$sql = "
    SELECT
        n.*,

        creator.name AS creator_name,

        recipient.name AS recipient_name,
        recipient.email AS recipient_email,
        recipient.role AS recipient_role

    FROM notifications n

    LEFT JOIN users creator
        ON creator.id = n.created_by

    LEFT JOIN users recipient
        ON recipient.id = n.user_id

    WHERE 1 = 1
";

$params = [];

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            n.title LIKE :search
            OR n.message LIKE :search
            OR creator.name LIKE :search
            OR recipient.name LIKE :search
            OR recipient.email LIKE :search
        )
    ";

    $params[':search'] = '%' . $search . '%';
}

/*
|--------------------------------------------------------------------------
| TARGET FILTER
|--------------------------------------------------------------------------
*/

if ($targetFilter !== '') {

    $allowedFilters = [
        'all',
        'student',
        'teacher',
        'parent',
        'user'
    ];

    if (in_array($targetFilter, $allowedFilters, true)) {

        $sql .= "
            AND n.target_role = :target_role
        ";

        $params[':target_role'] = $targetFilter;
    }
}

/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        n.created_at DESC,
        n.id DESC
";

/*
|--------------------------------------------------------------------------
| PAGINATION
|
| The list used to render every notification on the page, so response
| size and render cost grew without bound as the table filled. It is
| now served one page at a time with an explicit row count.
|--------------------------------------------------------------------------
*/

$perPage = 25;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

$page = max(1, $page);

$countSql = "
    SELECT COUNT(*)
    FROM notifications n

    LEFT JOIN users creator
        ON creator.id = n.created_by

    LEFT JOIN users recipient
        ON recipient.id = n.user_id

    WHERE 1 = 1
";

$countParams = $params;

if ($search !== '') {

    $countSql .= "
        AND (
            n.title LIKE :search
            OR n.message LIKE :search
            OR creator.name LIKE :search
            OR recipient.name LIKE :search
            OR recipient.email LIKE :search
        )
    ";
}

if (
    $targetFilter !== ''
    && in_array(
        $targetFilter,
        [
            'all',
            'student',
            'teacher',
            'parent',
            'user'
        ],
        true
    )
) {

    $countSql .= ' AND n.target_role = :target_role';
}

$totalNotifications = 0;

try {

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);

    $totalNotifications = (int) $countStmt->fetchColumn();

} catch (Throwable $e) {

    $errors[] = 'Unable to count notifications.';
}

$totalPages = max(
    1,
    (int) ceil($totalNotifications / $perPage)
);

if ($page > $totalPages) {

    $page = $totalPages;
}

$sql .= "
    LIMIT
        " . (int) $perPage . "
    OFFSET
        " . (int) (($page - 1) * $perPage) . "
";

/*
|--------------------------------------------------------------------------
| FETCH NOTIFICATIONS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $errors[] = 'Unable to load notifications.';
}

/*
|--------------------------------------------------------------------------
| ACTIVE USERS
|--------------------------------------------------------------------------
*/

$activeUsers = [];

$userSearch = trim((string) ($_GET['user_q'] ?? ''));

try {

    // The recipient picker is searchable and capped. Loading every active
    // account into one <select> made the page grow with the size of the
    // school and was unusable at a few thousand users.
    $userSql = "
        SELECT
            id,
            name,
            email,
            role
        FROM users
        WHERE status = 'active'
    ";

    $userParams = [];

    if ($userSearch !== '') {

        $userSql .= '
            AND (
                name LIKE :uq
                OR email LIKE :uq2
            )
        ';

        $userParams[':uq'] = '%' . $userSearch . '%';
        $userParams[':uq2'] = '%' . $userSearch . '%';
    }

    $userSql .= '
        ORDER BY name ASC
        LIMIT 150
    ';

    $stmt = $pdo->prepare($userSql);
    $stmt->execute($userParams);

    $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $errors[] = 'Unable to load users.';
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalNotifications = 0;
$totalAll = 0;
$totalStudents = 0;
$totalTeachers = 0;
$totalSpecificUsers = 0;

try {

    $stmt = $pdo->query("
        SELECT COUNT(*) AS total
        FROM notifications
    ");

    $totalNotifications = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE target_role = 'all'
    ");

    $totalAll = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE target_role = 'student'
    ");

    $totalStudents = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE target_role = 'teacher'
    ");

    $totalTeachers = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM notifications
        WHERE target_role = 'user'
    ");

    $totalSpecificUsers = (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $errors[] = 'Unable to load notification statistics.';
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function notificationTargetLabel(string $targetRole): string
{
    return match ($targetRole) {
        'all' => 'Everyone',
        'student' => 'Students',
        'teacher' => 'Teachers',
        'user' => 'Specific User',
        default => ucfirst($targetRole)
    };
}


function notificationTargetClass(string $targetRole): string
{
    return match ($targetRole) {
        'all' => 'target-all',
        'student' => 'target-student',
        'teacher' => 'target-teacher',
        'user' => 'target-user',
        default => 'target-default'
    };
}


function notificationPreview(string $message, int $length = 130): string
{
    $text = trim(
        (string) preg_replace(
            '/\s+/',
            ' ',
            strip_tags($message)
        )
    );

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . '...';
}


function notificationDate(string $date): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('M d, Y • h:i A', $timestamp);
}

?>

<?php require_once __DIR__ . '/../../includes/layout.php'; ?>

<style>

/* =========================================================
   NOTIFICATIONS PAGE
========================================================= */

.notifications-page {
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
    padding: 28px;
    box-sizing: border-box;
}


/* =========================================================
   HEADER
========================================================= */

.notifications-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 28px;
}

.notifications-heading {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}

.notifications-icon {
    width: 54px;
    height: 54px;
    border-radius: 16px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.22);
}

.notifications-icon svg {
    width: 27px;
    height: 27px;
}

.notifications-heading h1 {
    margin: 0 0 6px;
    font-size: 27px;
    line-height: 1.2;
    color: #111827;
    font-weight: 750;
}

.notifications-heading p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn-primary-custom,
.btn-secondary-custom,
.btn-danger-custom {
    border: 0;
    border-radius: 11px;
    min-height: 44px;
    padding: 0 17px;
    font-size: 14px;
    font-weight: 650;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s ease;
    font-family: inherit;
}

.btn-primary-custom {
    background: #4f46e5;
    color: #ffffff;
    box-shadow: 0 7px 18px rgba(79, 70, 229, 0.20);
}

.btn-primary-custom:hover {
    background: #4338ca;
    transform: translateY(-1px);
}

.btn-secondary-custom {
    background: #f3f4f6;
    color: #374151;
}

.btn-secondary-custom:hover {
    background: #e5e7eb;
}

.btn-danger-custom {
    background: #fee2e2;
    color: #b91c1c;
}

.btn-danger-custom:hover {
    background: #fecaca;
}

.btn-icon {
    width: 18px;
    height: 18px;
}


/* =========================================================
   ALERTS
========================================================= */

.alert-box {
    border-radius: 12px;
    padding: 14px 16px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.alert-error ul {
    margin: 0;
    padding-left: 20px;
}


/* =========================================================
   STATS
========================================================= */

.notification-stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 0;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.04);
}

.stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon svg {
    width: 21px;
    height: 21px;
}

.stat-total .stat-icon {
    background: #eef2ff;
    color: #4f46e5;
}

.stat-all .stat-icon {
    background: #ecfeff;
    color: #0891b2;
}

.stat-student .stat-icon {
    background: #eff6ff;
    color: #2563eb;
}

.stat-teacher .stat-icon {
    background: #f5f3ff;
    color: #7c3aed;
}

.stat-user .stat-icon {
    background: #fff7ed;
    color: #ea580c;
}

.stat-content {
    min-width: 0;
}

.stat-number {
    font-size: 22px;
    line-height: 1.1;
    font-weight: 750;
    color: #111827;
}

.stat-label {
    color: #6b7280;
    font-size: 12px;
    margin-top: 4px;
}


/* =========================================================
   MAIN CARD
========================================================= */

.notifications-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(15, 23, 42, 0.05);
}

.notifications-card-header {
    padding: 20px 22px;
    border-bottom: 1px solid #eef0f3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.card-title {
    margin: 0;
    font-size: 18px;
    color: #111827;
    font-weight: 700;
}

.card-subtitle {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 13px;
}


/* =========================================================
   FILTERS
========================================================= */

.notification-filters {
    padding: 18px 22px;
    background: #f9fafb;
    border-bottom: 1px solid #eef0f3;
}

.filter-form {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 190px auto auto;
    gap: 10px;
    align-items: end;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.form-label {
    font-size: 12px;
    color: #4b5563;
    font-weight: 650;
}

.form-input,
.form-select,
.form-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #111827;
    border-radius: 10px;
    outline: none;
    font-family: inherit;
    font-size: 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input,
.form-select {
    height: 43px;
    padding: 0 12px;
}

.form-textarea {
    min-height: 125px;
    padding: 12px;
    resize: vertical;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.10);
}


/* =========================================================
   NOTIFICATION LIST
========================================================= */

.notification-list {
    padding: 4px 22px 22px;
}

.notification-item {
    display: grid;
    grid-template-columns: 46px minmax(0, 1fr) auto;
    gap: 15px;
    padding: 20px 0;
    border-bottom: 1px solid #eef0f3;
}

.notification-item:last-child {
    border-bottom: 0;
}

.notification-avatar {
    width: 46px;
    height: 46px;
    border-radius: 13px;
    background: #eef2ff;
    color: #4f46e5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-avatar svg {
    width: 22px;
    height: 22px;
}

.notification-main {
    min-width: 0;
}

.notification-topline {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    margin-bottom: 6px;
}

.notification-title {
    margin: 0;
    color: #111827;
    font-size: 15px;
    font-weight: 700;
}

.notification-preview {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

.notification-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 9px;
    color: #9ca3af;
    font-size: 12px;
}

.target-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}

.target-all {
    background: #ecfeff;
    color: #0e7490;
}

.target-student {
    background: #eff6ff;
    color: #1d4ed8;
}

.target-teacher {
    background: #f5f3ff;
    color: #6d28d9;
}

.target-user {
    background: #fff7ed;
    color: #c2410c;
}

.target-default {
    background: #f3f4f6;
    color: #4b5563;
}

.notification-actions {
    display: flex;
    align-items: flex-start;
    gap: 7px;
}

.small-action {
    width: 36px;
    height: 36px;
    border-radius: 9px;
    border: 1px solid #e5e7eb;
    background: #ffffff;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
}

.small-action:hover {
    background: #f3f4f6;
    color: #111827;
}

.small-action.delete:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fecaca;
}

.small-action svg {
    width: 17px;
    height: 17px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {
    text-align: center;
    padding: 65px 20px;
}

.empty-icon {
    width: 62px;
    height: 62px;
    margin: 0 auto 15px;
    border-radius: 18px;
    background: #f3f4f6;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
}

.empty-icon svg {
    width: 29px;
    height: 29px;
}

.empty-state h3 {
    margin: 0 0 6px;
    color: #374151;
    font-size: 17px;
}

.empty-state p {
    margin: 0;
    color: #9ca3af;
    font-size: 13px;
}


/* =========================================================
   MODAL
========================================================= */

.custom-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.custom-modal.active {
    display: flex;
}

.modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(3px);
}

.modal-box {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 560px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 25px 70px rgba(15, 23, 42, 0.25);
    animation: modalIn 0.18s ease-out;
}

@keyframes modalIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 19px 21px;
    border-bottom: 1px solid #eef0f3;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
}

.modal-header h2 {
    margin: 0;
    font-size: 18px;
    color: #111827;
}

.modal-close {
    width: 35px;
    height: 35px;
    border: 0;
    border-radius: 9px;
    background: #f3f4f6;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}

.modal-close:hover {
    background: #e5e7eb;
    color: #111827;
}

.modal-close svg {
    width: 18px;
    height: 18px;
}

.modal-body {
    padding: 21px;
}

.modal-footer {
    padding: 16px 21px;
    border-top: 1px solid #eef0f3;
    display: flex;
    justify-content: flex-end;
    gap: 9px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.mb-16 {
    margin-bottom: 16px;
}

.specific-user-field {
    display: none;
}

.specific-user-field.show {
    display: flex;
}


/* =========================================================
   VIEW MODAL
========================================================= */

.detail-box {
    margin-bottom: 17px;
}

.detail-label {
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #9ca3af;
    margin-bottom: 5px;
}

.detail-value {
    color: #111827;
    font-size: 14px;
    line-height: 1.6;
    word-break: break-word;
}

.message-box {
    padding: 14px;
    background: #f9fafb;
    border: 1px solid #eef0f3;
    border-radius: 11px;
    white-space: pre-wrap;
}


/* =========================================================
   DELETE FORM
========================================================= */

.delete-form {
    display: inline;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1200px) {

    .notification-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .filter-form {
        grid-template-columns: 1fr 180px auto auto;
    }
}


@media (max-width: 900px) {

    .notifications-page {
        padding: 20px;
    }

    .notifications-header {
        flex-direction: column;
    }

    .notification-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .filter-form {
        grid-template-columns: 1fr 1fr;
    }
}


@media (max-width: 620px) {

    .notifications-page {
        padding: 14px;
    }

    .notifications-heading h1 {
        font-size: 22px;
    }

    .notification-stats {
        grid-template-columns: 1fr;
    }

    .filter-form {
        grid-template-columns: 1fr;
    }

    .notifications-card-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .notification-list {
        padding-left: 15px;
        padding-right: 15px;
    }

    .notification-item {
        grid-template-columns: 40px minmax(0, 1fr);
    }

    .notification-avatar {
        width: 40px;
        height: 40px;
    }

    .notification-actions {
        grid-column: 2;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .modal-box {
        max-height: calc(100vh - 25px);
    }
}

</style>


<div class="notifications-page">

    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="notifications-header">

        <div class="notifications-heading">

            <div class="notifications-icon">

                <svg viewBox="0 0 24 24" fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>

                </svg>

            </div>

            <div>

                <h1>Notifications</h1>

                <p>
                    Send and manage announcements for students, teachers and users.
                </p>

            </div>

        </div>


        <button
            type="button"
            class="btn-primary-custom"
            onclick="openCreateModal()"
        >

            <svg class="btn-icon"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round">

                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>

            </svg>

            New Notification

        </button>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($success !== ''): ?>

        <div class="alert-box alert-success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($errors): ?>

        <div class="alert-box alert-error">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="notification-stats">

        <div class="stat-card stat-total">

            <div class="stat-icon">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M4 4h16v16H4z"></path>
                    <path d="M8 8h8"></path>
                    <path d="M8 12h8"></path>
                    <path d="M8 16h5"></path>

                </svg>

            </div>

            <div class="stat-content">

                <div class="stat-number">
                    <?= number_format($totalNotifications) ?>
                </div>

                <div class="stat-label">
                    Total Notifications
                </div>

            </div>

        </div>


        <div class="stat-card stat-all">

            <div class="stat-icon">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>

                </svg>

            </div>

            <div class="stat-content">

                <div class="stat-number">
                    <?= number_format($totalAll) ?>
                </div>

                <div class="stat-label">
                    Everyone
                </div>

            </div>

        </div>


        <div class="stat-card stat-student">

            <div class="stat-icon">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <path d="M12 3L2 8l10 5 10-5-10-5z"></path>
                    <path d="M6 10.5V16c3 2.5 9 2.5 12 0v-5.5"></path>
                    <path d="M22 8v6"></path>

                </svg>

            </div>

            <div class="stat-content">

                <div class="stat-number">
                    <?= number_format($totalStudents) ?>
                </div>

                <div class="stat-label">
                    Students
                </div>

            </div>

        </div>


        <div class="stat-card stat-teacher">

            <div class="stat-icon">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <circle cx="12" cy="7" r="4"></circle>
                    <path d="M5.5 21a6.5 6.5 0 0 1 13 0"></path>

                </svg>

            </div>

            <div class="stat-content">

                <div class="stat-number">
                    <?= number_format($totalTeachers) ?>
                </div>

                <div class="stat-label">
                    Teachers
                </div>

            </div>

        </div>


        <div class="stat-card stat-user">

            <div class="stat-icon">

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 21a8 8 0 0 1 16 0"></path>

                </svg>

            </div>

            <div class="stat-content">

                <div class="stat-number">
                    <?= number_format($totalSpecificUsers) ?>
                </div>

                <div class="stat-label">
                    Specific Users
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         NOTIFICATIONS CARD
    ====================================================== -->

    <div class="notifications-card">

        <div class="notifications-card-header">

            <div>

                <h2 class="card-title">
                    Notification Center
                </h2>

                <p class="card-subtitle">
                    Review, search and manage all system notifications.
                </p>

            </div>

        </div>


        <!-- =================================================
             FILTERS
        ================================================== -->

        <div class="notification-filters">

            <form
                method="GET"
                class="filter-form"
            >

                <div class="form-group">

                    <label class="form-label">
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        class="form-input"
                        placeholder="Search title, message, user..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Target
                    </label>

                    <select
                        name="target_role"
                        class="form-select"
                    >

                        <option value="">
                            All Targets
                        </option>

                        <option
                            value="all"
                            <?= $targetFilter === 'all' ? 'selected' : '' ?>
                        >
                            Everyone
                        </option>

                        <option
                            value="student"
                            <?= $targetFilter === 'student' ? 'selected' : '' ?>
                        >
                            Students
                        </option>

                        <option
                            value="teacher"
                            <?= $targetFilter === 'teacher' ? 'selected' : '' ?>
                        >
                            Teachers
                        </option>

                        <option
                            value="user"
                            <?= $targetFilter === 'user' ? 'selected' : '' ?>
                        >
                            Specific Users
                        </option>

                    </select>

                </div>


                <button
                    type="submit"
                    class="btn-primary-custom"
                >
                    Search
                </button>


                <a
                    href="index.php"
                    class="btn-secondary-custom"
                    style="text-decoration:none;"
                >
                    Reset
                </a>

            </form>

        </div>


        <!-- =================================================
             LIST
        ================================================== -->

        <div class="notification-list">

            <?php if (!$notifications): ?>

                <div class="empty-state">

                    <div class="empty-icon">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>

                        </svg>

                    </div>

                    <h3>
                        No notifications found
                    </h3>

                    <p>
                        Create a notification or change your search filters.
                    </p>

                </div>

            <?php else: ?>


                <?php foreach ($notifications as $notification): ?>

                    <?php

                    $notificationId = (int) $notification['id'];

                    $targetRole = (string) $notification['target_role'];

                    $title = (string) $notification['title'];

                    $message = (string) $notification['message'];

                    $creatorName = $notification['creator_name']
                        ? (string) $notification['creator_name']
                        : 'System';

                    $recipientName = $notification['recipient_name']
                        ? (string) $notification['recipient_name']
                        : '';

                    $recipientEmail = $notification['recipient_email']
                        ? (string) $notification['recipient_email']
                        : '';

                    ?>

                    <div class="notification-item">

                        <!-- Avatar -->

                        <div class="notification-avatar">

                            <svg viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round">

                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>

                            </svg>

                        </div>


                        <!-- Content -->

                        <div class="notification-main">

                            <div class="notification-topline">

                                <h3 class="notification-title">
                                    <?= htmlspecialchars($title) ?>
                                </h3>

                                <span
                                    class="target-badge <?= htmlspecialchars(notificationTargetClass($targetRole)) ?>"
                                >
                                    <?= htmlspecialchars(notificationTargetLabel($targetRole)) ?>
                                </span>

                            </div>


                            <p class="notification-preview">
                                <?= htmlspecialchars(notificationPreview($message)) ?>
                            </p>


                            <div class="notification-meta">

                                <span>
                                    By <?= htmlspecialchars($creatorName) ?>
                                </span>

                                <span>
                                    •
                                </span>

                                <span>
                                    <?= htmlspecialchars(notificationDate((string) $notification['created_at'])) ?>
                                </span>


                                <?php if ($targetRole === 'user' && $recipientName !== ''): ?>

                                    <span>
                                        •
                                    </span>

                                    <span>
                                        To <?= htmlspecialchars($recipientName) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <!-- Actions -->

                        <div class="notification-actions">

                            <button
                                type="button"
                                class="small-action"
                                title="View notification"
                                onclick='viewNotification(<?= json_encode([
                                    'title' => $title,
                                    'message' => $message,
                                    'target' => notificationTargetLabel($targetRole),
                                    'creator' => $creatorName,
                                    'recipient' => $recipientName,
                                    'email' => $recipientEmail,
                                    'date' => notificationDate((string) $notification['created_at'])
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                            >

                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">

                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>

                                </svg>

                            </button>


                            <form
                                method="POST"
                                class="delete-form"
                                onsubmit="return confirm('Are you sure you want to delete this notification?');"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete"
                                >

                                <input
                                    type="hidden"
                                    name="notification_id"
                                    value="<?= $notificationId ?>"
                                >

                                <button
                                    type="submit"
                                    class="small-action delete"
                                    title="Delete notification"
                                >

                                    <svg viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2"
                                         stroke-linecap="round"
                                         stroke-linejoin="round">

                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6l-1 14H6L5 6"></path>
                                        <path d="M10 11v5"></path>
                                        <path d="M14 11v5"></path>
                                        <path d="M9 6V4h6v2"></path>

                                    </svg>

                                </button>

                            </form>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

        <?php if ($totalNotifications > $perPage): ?>

            <?php
            $pageQs = static function (int $target) use ($search, $targetFilter): string {
                $params = [];

                if ($search !== '') {
                    $params['search'] = $search;
                }

                if ($targetFilter !== '') {
                    $params['target_role'] = $targetFilter;
                }

                $params['page'] = $target;

                return '?' . http_build_query($params);
            };
            ?>

            <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">

                <small class="text-muted">

                    Showing
                    <?= (int) (($page - 1) * $perPage + 1) ?>–<?= min($totalNotifications, $page * $perPage) ?>
                    of <?= $totalNotifications ?>
                    notifications (page <?= $page ?> of <?= $totalPages ?>)

                </small>

                <nav aria-label="Notification list pages">

                    <ul class="pagination pagination-sm mb-0">

                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                            <a class="page-link" href="<?= htmlspecialchars($pageQs(max(1, $page - 1))) ?>">
                                Previous
                            </a>

                        </li>

                        <?php
                        $from = max(1, $page - 2);
                        $to = min($totalPages, $page + 2);

                        for ($p = $from; $p <= $to; $p++):
                        ?>

                            <li class="page-item <?= $p === $page ? 'active' : '' ?>">

                                <a class="page-link" href="<?= htmlspecialchars($pageQs($p)) ?>">
                                    <?= $p ?>
                                </a>

                            </li>

                        <?php endfor; ?>

                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                            <a class="page-link" href="<?= htmlspecialchars($pageQs(min($totalPages, $page + 1))) ?>">
                                Next
                            </a>

                        </li>

                    </ul>

                </nav>

            </div>

        <?php endif; ?>

    </div>

</div>


<!-- =========================================================
     CREATE NOTIFICATION MODAL
========================================================= -->

<div
    id="createModal"
    class="custom-modal"
    aria-hidden="true"
>

    <div
        class="modal-overlay"
        onclick="closeCreateModal()"
    ></div>


    <div
        class="modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="createModalTitle"
    >

        <div class="modal-header">

            <h2 id="createModalTitle">
                Create Notification
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeCreateModal()"
                aria-label="Close"
            >

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>

                </svg>

            </button>

        </div>


        <form method="POST">

            <div class="modal-body">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="create"
                >


                <!-- Title -->

                <div class="form-group mb-16">

                    <label class="form-label">
                        Notification Title
                    </label>

                    <input
                        type="text"
                        name="title"
                        class="form-input"
                        maxlength="255"
                        placeholder="Enter notification title"
                        required
                    >

                </div>


                <!-- Target -->

                <div class="form-row mb-16">

                    <div class="form-group">

                        <label class="form-label">
                            Target Audience
                        </label>

                        <select
                            id="targetRole"
                            name="target_role"
                            class="form-select"
                            onchange="toggleSpecificUser()"
                            required
                        >

                            <option value="all">
                                Everyone
                            </option>

                            <option value="student">
                                Students
                            </option>

                            <option value="teacher">
                                Teachers
                            </option>

                            <option value="parent">
                                Parents
                            </option>

                            <option value="user">
                                Specific User
                            </option>

                        </select>

                    </div>


                    <!-- Category -->

                    <div class="form-group">

                        <label class="form-label">
                            Category
                        </label>

                        <select
                            name="type"
                            id="notificationType"
                            class="form-select"
                            required
                        >

                            <option value="general">
                                General
                            </option>

                            <option value="announcement">
                                Announcement
                            </option>

                            <option value="system">
                                System
                            </option>

                            <option value="assignment">
                                Assignment
                            </option>

                            <option value="submission">
                                Submission / Grading
                            </option>

                            <option value="attendance">
                                Attendance
                            </option>

                            <option value="exam">
                                Exam
                            </option>

                            <option value="marks">
                                Result / Marks
                            </option>

                            <option value="fee">
                                Fee / Payment
                            </option>

                        </select>

                    </div>


                    <!-- Priority -->

                    <div class="form-group">

                        <label class="form-label">
                            Priority
                        </label>

                        <select
                            name="priority"
                            id="notificationPriority"
                            class="form-select"
                            required
                        >

                            <option value="low">
                                Low
                            </option>

                            <option value="normal" selected>
                                Normal
                            </option>

                            <option value="high">
                                High
                            </option>

                            <option value="urgent">
                                Urgent
                            </option>

                        </select>

                    </div>


                    <!-- Specific User -->

                    <div
                        id="specificUserField"
                        class="form-group specific-user-field"
                    >

                        <label class="form-label">
                            Select User
                        </label>

                        <input
                            type="search"
                            id="recipientSearch"
                            class="form-control mb-8"
                            placeholder="Type to filter recipients..."
                            onkeyup="filterRecipients()"
                            oninput="filterRecipients()"
                            aria-label="Filter recipients"
                        >


                        <select
                            name="user_id"
                            id="specificUser"
                            class="form-select"
                        >

                            <option value="">
                                Select a user
                            </option>

                            <?php foreach ($activeUsers as $user): ?>

                                <option value="<?= (int) $user['id'] ?>">

                                    <?= htmlspecialchars((string) $user['name']) ?>

                                    —
                                    <?= htmlspecialchars((string) $user['email']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">

                            <?php if ($userSearch === ''): ?>

                                Showing the first <?= count($activeUsers) ?>
                                active accounts. Use the recipient search box to
                                narrow this list.

                            <?php else: ?>

                                <?= count($activeUsers) ?>
                                match(es) for
                                “<?= htmlspecialchars($userSearch) ?>”.

                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- Message -->

                <div class="form-group">

                    <label class="form-label">
                        Message
                    </label>

                    <textarea
                        name="message"
                        class="form-textarea"
                        placeholder="Write your notification message..."
                        required
                    ></textarea>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn-secondary-custom"
                    onclick="closeCreateModal()"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="btn-primary-custom"
                >
                    Create Notification
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     VIEW NOTIFICATION MODAL
========================================================= -->

<div
    id="viewModal"
    class="custom-modal"
    aria-hidden="true"
>

    <div
        class="modal-overlay"
        onclick="closeViewModal()"
    ></div>


    <div
        class="modal-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="viewModalTitle"
    >

        <div class="modal-header">

            <h2 id="viewModalTitle">
                Notification Details
            </h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeViewModal()"
            >

                <svg viewBox="0 0 24 24"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     stroke-linecap="round"
                     stroke-linejoin="round">

                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>

                </svg>

            </button>

        </div>


        <div class="modal-body">

            <div class="detail-box">

                <div class="detail-label">
                    Title
                </div>

                <div
                    id="viewTitle"
                    class="detail-value"
                ></div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Target
                </div>

                <div
                    id="viewTarget"
                    class="detail-value"
                ></div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Created By
                </div>

                <div
                    id="viewCreator"
                    class="detail-value"
                ></div>

            </div>


            <div
                id="viewRecipientBox"
                class="detail-box"
                style="display:none;"
            >

                <div class="detail-label">
                    Recipient
                </div>

                <div
                    id="viewRecipient"
                    class="detail-value"
                ></div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Date
                </div>

                <div
                    id="viewDate"
                    class="detail-value"
                ></div>

            </div>


            <div class="detail-box">

                <div class="detail-label">
                    Message
                </div>

                <div
                    id="viewMessage"
                    class="detail-value message-box"
                ></div>

            </div>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn-secondary-custom"
                onclick="closeViewModal()"
            >
                Close
            </button>

        </div>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| CREATE MODAL
|--------------------------------------------------------------------------
*/

function filterRecipients() {

    const input = document.getElementById('recipientSearch');

    const select = document.getElementById('specificUser');

    if (!input || !select) {
        return;
    }

    const term = input.value.trim().toLowerCase();

    for (const option of select.options) {

        if (!option.value) {
            continue;
        }

        option.hidden =
            term !== '' &&
            option.textContent.toLowerCase().indexOf(term) === -1;
    }
}


function openCreateModal() {

    const modal = document.getElementById('createModal');

    modal.classList.add('active');

    modal.setAttribute('aria-hidden', 'false');

    document.body.style.overflow = 'hidden';

    toggleSpecificUser();

    filterRecipients();
}


function closeCreateModal() {

    const modal = document.getElementById('createModal');

    modal.classList.remove('active');

    modal.setAttribute('aria-hidden', 'true');

    document.body.style.overflow = '';
}


/*
|--------------------------------------------------------------------------
| SPECIFIC USER FIELD
|--------------------------------------------------------------------------
*/

function toggleSpecificUser() {

    const targetRole = document.getElementById('targetRole');

    const field = document.getElementById('specificUserField');

    const select = document.getElementById('specificUser');

    if (!targetRole || !field || !select) {
        return;
    }

    if (targetRole.value === 'user') {

        field.classList.add('show');

        select.required = true;

    } else {

        field.classList.remove('show');

        select.required = false;

        select.value = '';
    }
}


/*
|--------------------------------------------------------------------------
| VIEW MODAL
|--------------------------------------------------------------------------
*/

function viewNotification(data) {

    document.getElementById('viewTitle').textContent =
        data.title || '';

    document.getElementById('viewTarget').textContent =
        data.target || '';

    document.getElementById('viewCreator').textContent =
        data.creator || '';

    document.getElementById('viewDate').textContent =
        data.date || '';

    document.getElementById('viewMessage').textContent =
        data.message || '';


    const recipientBox =
        document.getElementById('viewRecipientBox');

    const recipient =
        document.getElementById('viewRecipient');

    if (data.recipient) {

        recipientBox.style.display = 'block';

        let recipientText = data.recipient;

        if (data.email) {
            recipientText += ' (' + data.email + ')';
        }

        recipient.textContent = recipientText;

    } else {

        recipientBox.style.display = 'none';

        recipient.textContent = '';
    }


    const modal =
        document.getElementById('viewModal');

    modal.classList.add('active');

    modal.setAttribute('aria-hidden', 'false');

    document.body.style.overflow = 'hidden';
}


function closeViewModal() {

    const modal =
        document.getElementById('viewModal');

    modal.classList.remove('active');

    modal.setAttribute('aria-hidden', 'true');

    document.body.style.overflow = '';
}


/*
|--------------------------------------------------------------------------
| ESC KEY
|--------------------------------------------------------------------------
*/

document.addEventListener('keydown', function (event) {

    if (event.key === 'Escape') {

        closeCreateModal();

        closeViewModal();
    }

});


/*
|--------------------------------------------------------------------------
| INITIAL STATE
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function () {

    toggleSpecificUser();

});

</script>