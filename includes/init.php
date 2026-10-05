<?php

declare(strict_types=1);

$appConfig = require dirname(__DIR__) . '/config/app.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

$debug = !empty($appConfig['debug']);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    session_start();
}

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/role_check.php';
require_once dirname(__DIR__) . '/config/Database.php';

function app_config(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/app.php';
    }

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = (new Database())->connect();
        ensure_optional_schema($pdo);
    }
    return $pdo;
}

function app_base(): string
{
    return rtrim((string) app_config('base_path', '/student-management'), '/');
}

function url(string $path = ''): string
{
    return app_base() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    if (!str_starts_with($path, 'http') && !str_starts_with($path, '/')) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

function wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    return str_contains($accept, 'application/json')
        || str_ends_with($script, 'get_sections.php')
        || str_contains($script, '/api/');
}

function require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Please refresh the page and try again.');
    }
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">';
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/*
|--------------------------------------------------------------------------
| Student notifications (email + SMS)
|--------------------------------------------------------------------------
| Non-blocking by contract:
|  - never throws, so it can sit inside any business flow;
|  - both transports are disabled by default (mail_enabled /
|    sms_enabled in config/app.php) — until configured, this is a
|    silent no-op;
|  - the linked parent account (students.parent_user_id) receives the
|    same email when present.
|
| The $sms text may use the {name} placeholder for the student name.
*/
function notify_student(
    int $studentId,
    string $subject,
    string $body,
    string $sms = ''
): void {
    if ($studentId <= 0) {
        return;
    }

    try {
        $stmt = db()->prepare(
            'SELECT s.name, s.email, s.phone, u.email AS parent_email
             FROM students s
             LEFT JOIN users u ON u.id = s.parent_user_id
             WHERE s.id = :id
             LIMIT 1'
        );

        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return;
        }

        require_once __DIR__ . '/../classes/Mailer.php';
        require_once __DIR__ . '/../classes/Sms.php';

        $name = (string) $student['name'];
        $subject = str_replace('{name}', $name, $subject);
        $body = str_replace('{name}', $name, $body);

        Mailer::send((string) ($student['email'] ?? ''), $subject, $body);

        $parentEmail = (string) ($student['parent_email'] ?? '');
        $ownEmail = (string) ($student['email'] ?? '');

        if ($parentEmail !== '' && $parentEmail !== $ownEmail) {
            Mailer::send($parentEmail, $subject, $body);
        }

        if ($sms !== '') {
            Sms::send(
                (string) ($student['phone'] ?? ''),
                str_replace('{name}', $name, $sms)
            );
        }
    } catch (Throwable $e) {
        error_log('notify_student failed: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| In-app student notification
|--------------------------------------------------------------------------
| Mail and SMS are silent until configured, so the student would otherwise
| get no visible trace of the event at all. This records the same message
| in the in-app notifications centre the student actually reads.
|
| Like notify_student() it never throws, so it is safe to call from inside
| any business flow.
*/
function notify_student_in_app(
    int $studentId,
    string $title,
    string $message,
    string $type = 'general',
    string $priority = 'normal',
    ?string $actionUrl = null,
    ?int $senderUserId = null
): void {
    if ($studentId <= 0) {
        return;
    }

    try {
        $stmt = db()->prepare(
            'SELECT s.user_id, s.name, s.parent_user_id
             FROM students s
             WHERE s.id = :id
             LIMIT 1'
        );

        $stmt->execute([':id' => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return;
        }

        $message = str_replace(
            '{name}',
            (string) $student['name'],
            $message
        );

        require_once __DIR__ . '/../classes/Notification.php';

        $notification = new Notification(db());

        $studentUserId = (int) ($student['user_id'] ?? 0);

        if ($studentUserId > 0) {
            $notification->create([
                'user_id' => $studentUserId,
                'target_role' => 'user',
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'priority' => $priority,
                'action_url' => $actionUrl,
                'created_by' => $senderUserId
            ]);
        }

        // The linked guardian sees the same event in their own centre.
        $parentUserId = (int) ($student['parent_user_id'] ?? 0);

        if ($parentUserId > 0 && $parentUserId !== $studentUserId) {
            $notification->create([
                'user_id' => $parentUserId,
                'target_role' => 'user',
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'priority' => $priority,
                'action_url' => $actionUrl,
                'created_by' => $senderUserId
            ]);
        }
    } catch (Throwable $e) {
        error_log('notify_student_in_app failed: ' . $e->getMessage());
    }
}

function request_int(string $key, mixed $source = null, int $default = 0): int
{
    $source = $source ?? $_GET;
    $value = $source[$key] ?? null;

    if ($value === null || $value === '' || !ctype_digit((string) $value)) {
        return $default;
    }

    return (int) $value;
}

function current_teacher(?PDO $pdo = null): ?array
{
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => (int) ($_SESSION['user_id'] ?? 0)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function current_student(?PDO $pdo = null): ?array
{
    $pdo = $pdo ?? db();
    $stmt = $pdo->prepare(
        'SELECT s.*, c.name AS class_name, sec.name AS section_name
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN sections sec ON s.section_id = sec.id
         WHERE s.user_id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => (int) ($_SESSION['user_id'] ?? 0)]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function login_throttle_key(string $email = ''): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash('sha256', strtolower(trim($email)) . '|' . $ip);
}

function login_is_locked(string $email = ''): bool
{
    $key = login_throttle_key($email);
    $lock = $_SESSION['_login_lock'][$key] ?? 0;

    return is_int($lock) && $lock > time();
}

function login_register_failure(string $email = ''): void
{
    $key = login_throttle_key($email);
    $max = (int) app_config('login_max_attempts', 8);
    $lockSeconds = (int) app_config('login_lockout_seconds', 900);

    $count = (int) ($_SESSION['_login_fail'][$key] ?? 0);
    $count++;
    $_SESSION['_login_fail'][$key] = $count;

    if ($count >= $max) {
        $_SESSION['_login_lock'][$key] = time() + $lockSeconds;
        $_SESSION['_login_fail'][$key] = 0;
    }
}

function login_register_success(string $email = ''): void
{
    $key = login_throttle_key($email);
    unset($_SESSION['_login_fail'][$key], $_SESSION['_login_lock'][$key]);
}

function force_logout_and_redirect(): never
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
    redirect('auth/login.php');
}

function enforce_session_security(): void
{
    $idle = (int) app_config('session_idle_seconds', 7200);
    $regen = (int) app_config('session_regenerate_seconds', 900);
    $now = time();

    if (!empty($_SESSION['_last_activity']) && ($now - (int) $_SESSION['_last_activity']) > $idle) {
        force_logout_and_redirect();
    }

    $_SESSION['_last_activity'] = $now;

    if (empty($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = $now;
    }

    if (($now - (int) ($_SESSION['_last_regen'] ?? $_SESSION['_created_at'])) > $regen) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = $now;
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        force_logout_and_redirect();
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, name, email, role, status FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('Session user lookup failed: ' . $e->getMessage());
        force_logout_and_redirect();
    }

    if (!$user || ($user['status'] ?? '') !== 'active') {
        force_logout_and_redirect();
    }

    if ((string) $user['role'] !== currentRole()) {
        force_logout_and_redirect();
    }

    $_SESSION['user_name'] = (string) $user['name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role'] = (string) $user['role'];

    if (currentRole() === 'student') {
        $student = current_student();
        if ($student) {
            $_SESSION['student_id'] = (int) $student['id'];
            $_SESSION['student_status'] = (string) $student['status'];

            $status = strtolower(trim((string) $student['status']));
            if ($status !== 'active' && !student_pending_path_allowed()) {
                redirect('student/dashboard.php');
            }
        }
    }

    if (currentRole() === 'teacher') {
        $teacher = current_teacher();
        if ($teacher === null || ($teacher['status'] ?? '') !== 'active') {
            force_logout_and_redirect();
        }

        /*
         * Role-specific teacher context, always derived from the
         * database row (never from a request). Auth::login() sets
         * these at login time; keeping them in sync here also
         * heals sessions created before this data existed.
         */
        $_SESSION['teacher_profile_id'] = (int) $teacher['id'];
        $_SESSION['teacher_record_id'] = (int) $teacher['id'];
        $_SESSION['teacher_id'] = (string) $teacher['teacher_id'];
        $_SESSION['teacher_name'] = (string) $teacher['name'];
    }
}

function student_pending_path_allowed(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowed = [
        '/student/dashboard.php',
        '/auth/logout.php',
        '/index.php',
    ];

    foreach ($allowed as $suffix) {
        if (str_ends_with($script, $suffix)) {
            return true;
        }
    }

    return false;
}

/**
 * Optional schema drift DETECTION - read-only by design.
 *
 * This runs from db() on the normal request path, so it must never
 * execute DDL. It performs one metadata query and, if a column
 * definition has drifted from what the application depends on,
 * writes a single actionable line to the error log.
 *
 * Applying the fix is an explicit migration step:
 *
 *     php includes/migrate.php
 *
 * Previously this function issued ALTER TABLE inline and pulled in
 * migrate.php, which meant an ordinary page request could silently
 * rewrite the schema and take write locks on large tables. That DDL
 * now lives in database/migrations/008_schema_alignment.sql and runs
 * only from the CLI runner.
 */
function ensure_optional_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        // Missing post-dump structure is reported, never created here.
        // A web request must not run schema changes; the operator applies
        // database/migrations/*.sql with: php includes/migrate.php
        $existing = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('exam_subjects','result_settings','timetables')"
        )->fetchColumn();

        if ((int) $existing < 3) {
            error_log(
                'Schema drift: expected tables exam_subjects, result_settings '
                . 'and timetables are incomplete. Apply the migrations with: '
                . 'php includes/migrate.php'
            );
        }

        // One metadata query for every column the code depends on.
        $rows = $pdo->query(
            "SELECT TABLE_NAME, COLUMN_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND (
                    (TABLE_NAME = 'notifications'   AND COLUMN_NAME = 'target_role')
                 OR (TABLE_NAME = 'fee_payments'    AND COLUMN_NAME = 'payment_method')
                 OR (TABLE_NAME = 'students'        AND COLUMN_NAME = 'status')
                 OR (TABLE_NAME IN ('assignments','attendance','teacher_classes')
                         AND COLUMN_NAME = 'section_id')
               )"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $table = (string) $row['TABLE_NAME'];
            $type  = strtolower((string) $row['COLUMN_TYPE']);
            $nullable = (string) $row['IS_NULLABLE'];

            if ($table === 'notifications' && !str_contains($type, "'user'")) {
                error_log(
                    "Schema drift: notifications.target_role is '{$type}' but the "
                    . "application expects ENUM('admin','teacher','student','user',"
                    . "'parent','all'). Apply the migrations with: php includes/migrate.php"
                );
            }

            if ($table === 'fee_payments' && !str_contains($type, "'card'")) {
                error_log(
                    "Schema drift: fee_payments.payment_method is '{$type}' but the "
                    . "application expects ENUM('cash','bank','online','card','other'). "
                    . "Apply the migrations with: php includes/migrate.php"
                );
            }

            if ($table === 'students' && !str_contains($type, 'pending')) {
                error_log(
                    "Schema drift: students.status is '{$type}' but the application "
                    . "expects ENUM('pending','active','inactive','graduated','left'). "
                    . "Apply the migrations with: php includes/migrate.php"
                );
            }

            if (
                in_array($table, ['assignments', 'attendance', 'teacher_classes'], true)
                && $nullable === 'NO'
            ) {
                // Whole-class rows are stored with section_id = NULL.
                error_log(
                    "Schema drift: {$table}.section_id is NOT NULL but whole-class "
                    . "rows require NULL. Apply the migrations with: "
                    . "php includes/migrate.php"
                );
            }
        }
    } catch (Throwable $e) {
        error_log('Optional schema check failed: ' . $e->getMessage());
    }
}

function letter_grade(float $percent): string
{
    if ($percent >= 80) {
        return 'A';
    }
    if ($percent >= 70) {
        return 'B';
    }
    if ($percent >= 60) {
        return 'C';
    }
    if ($percent >= 50) {
        return 'D';
    }
    return 'F';
}

set_exception_handler(static function (Throwable $e): void {
    error_log('Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (!empty(app_config('debug'))) {
        echo 'Application error: ' . e($e->getMessage());
        exit;
    }
    echo 'An unexpected error occurred. Please try again later.';
    exit;
});
