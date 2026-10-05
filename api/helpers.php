<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| JSON API helpers
|--------------------------------------------------------------------------
|
| Every /api/ endpoint:
|  - reuses the application's session auth (no second auth system,
|    no API tokens — same isAuthenticated()/session rules as pages);
|  - is GET-only (read API — nothing mutates state, so no CSRF token
|    is needed beyond the session cookie);
|  - returns a uniform envelope:
|      success: { "success": true,  "data": ..., "meta": {...}? }
|      error:   { "success": false, "message": "..." }
|
*/

require_once __DIR__ . '/../includes/init.php';

function api_json(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        | JSON_PARTIAL_OUTPUT_ON_ERROR
    );

    exit;
}

function api_ok(mixed $data, array $meta = []): never
{
    $payload = ['success' => true, 'data' => $data];

    if ($meta !== []) {
        $payload['meta'] = $meta;
    }

    api_json($payload);
}

function api_fail(int $status, string $message): never
{
    api_json(['success' => false, 'message' => $message], $status);
}

/**
 * Session required — same rules as requireAuth(), but answering with
 * 401 JSON instead of a login redirect. enforce_session_security()
 * still runs afterwards as the authoritative idle/regen/role check;
 * the small idle pre-check below only converts its redirect into a
 * proper 401 for API clients (it reads the same config key).
 */
function api_require_auth(): void
{
    if (!isAuthenticated()) {
        api_fail(401, 'Authentication required.');
    }

    $idle = (int) app_config('session_idle_seconds', 7200);

    if (
        !empty($_SESSION['_last_activity'])
        && (time() - (int) $_SESSION['_last_activity']) > $idle
    ) {
        api_fail(401, 'Session expired. Please log in again.');
    }

    if (function_exists('enforce_session_security')) {
        enforce_session_security();
    }
}

function api_require_role(string ...$roles): void
{
    if (!hasRole($roles)) {
        api_fail(403, 'You do not have access to this endpoint.');
    }
}

function api_require_get(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        api_fail(405, 'Method not allowed. Use GET.');
    }
}

function api_int(string $key, int $default = 0): int
{
    return request_int($key, $_GET, $default);
}

function api_str(string $key): string
{
    $value = $_GET[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

/**
 * Clamped pagination: page >= 1, 1 <= per_page <= 100.
 *
 * @return array{page:int, per_page:int, offset:int}
 */
function api_pagination(int $page, int $perPage): array
{
    $page = max(1, $page);
    $perPage = min(max(1, $perPage), 100);

    return [
        'page'     => $page,
        'per_page' => $perPage,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/**
 * Ownership check shared by student-scoped endpoints:
 *  - student: must request their own record;
 *  - parent:  the record must belong to a linked child;
 *  - teacher: the student must satisfy the exact teacher_classes
 *    EXISTS predicate used by teacher/students.php;
 *  - admin:   always allowed.
 *
 * Fails the request when the access rule does not hold.
 */
function api_assert_student_access(int $studentId): void
{
    $role = currentRole();

    if ($role === 'admin') {
        return;
    }

    if ($role === 'teacher') {
        $teacher = current_teacher();

        if (!$teacher) {
            api_fail(403, 'No teacher profile linked to your account.');
        }

        $stmt = db()->prepare("
            SELECT 1
            FROM students s
            WHERE s.id = :id
              AND EXISTS (
                    SELECT 1 FROM teacher_classes tc
                    WHERE tc.teacher_id = :teacher_id
                      AND tc.class_id = s.class_id
                      AND (tc.section_id IS NULL
                           OR tc.section_id = s.section_id)
              )
            LIMIT 1
        ");

        $stmt->execute([
            ':id'        => $studentId,
            ':teacher_id' => (int) $teacher['id'],
        ]);

        if (!$stmt->fetch()) {
            api_fail(403, 'This student is not in one of your classes.');
        }

        return;
    }

    if ($role === 'student') {
        $student = current_student();

        if (!$student || (int) $student['id'] !== $studentId) {
            api_fail(403, 'You may only request your own record.');
        }

        return;
    }

    if ($role === 'parent') {
        $stmt = db()->prepare("
            SELECT 1 FROM students
            WHERE id = :id AND parent_user_id = :parent_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id'        => $studentId,
            ':parent_id' => (int) ($_SESSION['user_id'] ?? 0),
        ]);

        if (!$stmt->fetch()) {
            api_fail(403, 'This student is not linked to your account.');
        }

        return;
    }

    api_fail(403, 'You do not have access to this endpoint.');
}

/**
 * Ownership check for exam-scoped endpoints (marks/results):
 * teachers may only touch exams of classes they are assigned to.
 */
function api_assert_exam_access(int $examId): void
{
    $role = currentRole();

    if ($role === 'admin' || $role === 'student') {
        return;
    }

    if ($role === 'teacher') {
        $teacher = current_teacher();

        if (!$teacher) {
            api_fail(403, 'No teacher profile linked to your account.');
        }

        $stmt = db()->prepare("
            SELECT 1
            FROM exams e
            WHERE e.id = :exam_id
              AND e.class_id IN (
                    SELECT tc.class_id
                    FROM teacher_classes tc
                    WHERE tc.teacher_id = :teacher_id
              )
            LIMIT 1
        ");

        $stmt->execute([
            ':exam_id'    => $examId,
            ':teacher_id' => (int) $teacher['id'],
        ]);

        if (!$stmt->fetch()) {
            api_fail(403, 'This exam does not belong to one of your classes.');
        }

        return;
    }

    if ($role === 'parent') {
        // Parents pass the student ownership check instead; an exam is
        // readable when at least one linked child has marks in it.
        return;
    }

    api_fail(403, 'You do not have access to this endpoint.');
}

/**
 * Linked child ids for the current parent session (empty otherwise).
 *
 * @return int[]
 */
function api_parent_child_ids(): array
{
    if (currentRole() !== 'parent') {
        return [];
    }

    $stmt = db()->prepare("
        SELECT id FROM students
        WHERE parent_user_id = :parent_id
        ORDER BY name ASC
    ");

    $stmt->execute([':parent_id' => (int) ($_SESSION['user_id'] ?? 0)]);

    return array_map(
        'intval',
        array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id')
    );
}
