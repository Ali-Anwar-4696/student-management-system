<?php

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

function denyAccess(string $message = 'You do not have permission to access this page.'): never
{
    if (function_exists('wants_json') && wants_json()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
        ]);
        exit;
    }

    http_response_code(403);

    $loginUrl = function_exists('url') ? url('auth/login.php') : '/student-management/auth/login.php';
    $homeUrl = function_exists('url') ? url('index.php') : '/student-management/index.php';

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow-sm p-4 text-center" style="max-width: 450px;">
        <h1 class="display-4 text-danger mb-3">403</h1>
        <h4 class="mb-3">Access Denied</h4>
        <p class="text-muted mb-4">' . e($message) . '</p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="' . e($homeUrl) . '" class="btn btn-outline-secondary">Home</a>
            <a href="' . e($loginUrl) . '" class="btn btn-primary">Return to Login</a>
        </div>
    </div>
</body>
</html>';

    exit;
}

function requireRole(array|string $roles, ?string $redirectUrl = null): void
{
    requireAuth($redirectUrl);

    if (!hasRole($roles)) {
        denyAccess();
    }
}

function requireAdmin(): void
{
    requireRole('admin');
}

function requireTeacher(): void
{
    requireRole('teacher');

    if (function_exists('current_teacher')) {
        $teacher = current_teacher();
        if ($teacher === null || ($teacher['status'] ?? '') !== 'active') {
            denyAccess('Your teacher account is inactive or not linked. Contact administration.');
        }
    }
}

function requireStudent(): void
{
    requireRole('student');
}

function requireParent(): void
{
    requireRole('parent');
}

function requireApprovedStudent(): void
{
    requireStudent();

    if (!function_exists('current_student')) {
        denyAccess();
    }

    $student = current_student();
    if ($student === null) {
        denyAccess('Student profile not found.');
    }

    $status = strtolower(trim((string) ($student['status'] ?? '')));
    if ($status !== 'active') {
        if (function_exists('redirect')) {
            redirect('student/dashboard.php');
        }
        header('Location: /student-management/student/dashboard.php');
        exit;
    }
}

function requireStaff(): void
{
    requireRole(['admin', 'teacher']);
}

function canAccess(array|string $roles): bool
{
    return isAuthenticated() && hasRole($roles);
}
