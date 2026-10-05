<?php
declare(strict_types=1);

function isAuthenticated(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['user_role'])
        && (int) $_SESSION['user_id'] > 0
        && $_SESSION['user_role'] !== '';
}

function currentRole(): string
{
    return (string) ($_SESSION['user_role'] ?? '');
}

function hasRole(array|string $roles): bool
{
    $allowed = is_array($roles) ? $roles : [$roles];

    return in_array(currentRole(), $allowed, true);
}

function requireAuth(?string $redirectUrl = null): void
{
    if (!isAuthenticated()) {
        if ($redirectUrl === null) {
            $redirectUrl = (function_exists('url') ? url('auth/login.php') : '/student-management/auth/login.php');
        }

        header('Location: ' . $redirectUrl);
        exit;
    }

    if (function_exists('enforce_session_security')) {
        enforce_session_security();
    }
}

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(?string $token): bool
{
    if ($token === null || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $token);
}

if (!function_exists('e')) {
    function e(?string $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
    }
}
