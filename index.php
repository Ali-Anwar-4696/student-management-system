<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (isAuthenticated()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') {
        redirect('admin/dashboard.php');
    }
    if ($role === 'teacher') {
        redirect('teacher/dashboard.php');
    }
    if ($role === 'parent') {
        redirect('parent/dashboard.php');
    }
    redirect('student/dashboard.php');
}

redirect('auth/login.php');
