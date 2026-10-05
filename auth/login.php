<?php

declare(strict_types=1);

/*
 * ============================================================================
 * Centralized, role-aware login — the single sign-in page of the application
 * ============================================================================
 *
 * Architecture (enforced below and in Auth::login()):
 *
 *   Main Login  /auth/login.php
 *        |
 *        v
 *   Auth::login()          <- the ONE centralized authentication service
 *                            (throttling, password_verify(), account status,
 *                             expected-role restriction, session-ID
 *                             regeneration, server-side identity)
 *        |
 *        v
 *   Role read from the DATABASE (users.role)  -- never from the request
 *        |
 *        v
 *   Automatic redirect to the matching dashboard:
 *       admin   -> /admin/dashboard.php
 *       teacher -> /teacher/dashboard.php
 *       student -> /student/dashboard.php
 *       parent  -> /parent/dashboard.php
 *
 * Role-specific login pages (?entry=admin|teacher|student|parent) are
 * convenience views of THIS page and THIS Auth::login() service. They only
 * restrict which role may pass through them (a mismatch produces the same
 * generic failure as a wrong password) — they can never grant a role, so
 * tampering with the entry value cannot escalate privileges.
 *
 * There is no redirect/next/return parameter at all: after login the user is
 * always sent to their own role dashboard, so open redirects are impossible.
 *
 * MFA: privileged-role MFA would be gated inside Auth::login() after password
 * verification (reserved flag admin_mfa_enabled in config/app.php).
 */

require_once __DIR__ . '/../includes/init.php';

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database();
$pdo = $database->connect();

$auth = new Auth($pdo);


// =====================================================
// ALREADY SIGNED IN?
//
// Route through the shared entry point, which redirects
// by the server-side session role.
// =====================================================

if (isAuthenticated()) {
    redirect('index.php');
}


// =====================================================
// ROLE-SPECIFIC ENTRY POINTS (whitelisted)
//
// $entry only ever RESTRICTS the role that may pass.
// An unknown value falls back to the unrestricted
// main login.
// =====================================================

$entries = [
    'main' => [
        'label' => 'All accounts',
        'title' => 'Sign in to StudentHub',
        'role'  => null,
        'note'  => 'Sign in with your email and password. Your role is detected automatically and the right dashboard opens.',
    ],

    'admin' => [
        'label' => 'Admin',
        'title' => 'Administrator Sign In',
        'role'  => 'admin',
        'note'  => 'This form is for administrator accounts only. Administrator accounts are provisioned through the secure installer — there is no public sign-up.',
    ],

    'teacher' => [
        'label' => 'Teacher',
        'title' => 'Teacher Sign In',
        'role'  => 'teacher',
        'note'  => 'This form is for teacher accounts. Teacher accounts are created by the administrator.',
    ],

    'student' => [
        'label' => 'Student',
        'title' => 'Student Sign In',
        'role'  => 'student',
        'note'  => 'This form is for student accounts. New students can create an account and wait for admin approval.',
    ],

    'parent' => [
        'label' => 'Parent',
        'title' => 'Parent Sign In',
        'role'  => 'parent',
        'note'  => 'This form is for parent accounts. Parent accounts are linked to a student by the administrator.',
    ],
];

$entry = (string) ($_GET['entry'] ?? 'main');

if (!isset($entries[$entry])) {
    $entry = 'main';
}

$expectedRole = $entries[$entry]['role'];

$message = '';
$messageType = '';


// =====================================================
// CENTRALIZED AUTHENTICATION
// =====================================================

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // ---------------------------------------------
    // CSRF
    // ---------------------------------------------

    if (!validateCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Invalid security token. Please refresh the page and try again.';
        $messageType = 'danger';

    } else {

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        /*
         * ONE service for every entry point: verifies the credentials
         * with password_verify(), applies login throttling, checks the
         * account status, enforces this page's role restriction and —
         * only on full success — regenerates the session ID and
         * establishes the identity server-side.
         *
         * No role is submitted by the form; the expected role of this
         * page comes from the whitelisted $entry above.
         */
        $result = $auth->login($email, $password, $expectedRole);

        if ($result['success'] !== true) {

            // Generic failure — never echo which part was wrong.
            $message = (string) $result['message'];
            $messageType = 'danger';

        } else {

            /*
             * Post-authentication MFA for privileged roles would be
             * enforced here (future — admin_mfa_enabled).
             *
             * The role comes from Auth::login() (database-driven).
             * It is NOT read from any request parameter.
             */
            $dashboards = [
                'admin'   => 'admin/dashboard.php',
                'teacher' => 'teacher/dashboard.php',
                'student' => 'student/dashboard.php',
                'parent'  => 'parent/dashboard.php',
            ];

            $role = (string) $result['role'];

            if (!isset($dashboards[$role])) {
                // Unreachable in practice (Auth::login() rejects
                // unknown roles) — fail closed, never guess.
                force_logout_and_redirect();
            }

            // Fixed internal destinations only. No user-supplied
            // redirect target exists anywhere in this flow.
            redirect($dashboards[$role]);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= e($entries[$entry]['title']) ?> - StudentHub</title>


    <!-- =================================================
         BOOTSTRAP
    ================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =================================================
         BOOTSTRAP ICONS
    ================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        /* =================================================
           PAGE
        ================================================= */

        body {

            min-height: 100vh;

            background:
                linear-gradient(
                    135deg,
                    #f4f7fb 0%,
                    #eef4ff 100%
                );

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px;

        }


        /* =================================================
           MAIN CONTAINER
        ================================================= */

        .login-container {

            width: 100%;

            max-width: 1050px;

        }


        /* =================================================
           CARD
        ================================================= */

        .login-card {

            background: #ffffff;

            border-radius: 24px;

            overflow: hidden;

            box-shadow:
                0 20px 60px
                rgba(0, 0, 0, 0.10);

            display: flex;

            min-height: 610px;

        }


        /* =================================================
           LEFT SIDE
        ================================================= */

        .login-left {

            background:
                linear-gradient(
                    145deg,
                    #0d6efd 0%,
                    #084298 100%
                );

            color: #ffffff;

            flex: 1;

            display: flex;

            align-items: center;

            padding: 55px;

            position: relative;

            overflow: hidden;

        }


        .login-left::before {

            content: "";

            position: absolute;

            width: 260px;

            height: 260px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.08);

            top: -90px;

            right: -80px;

        }


        .login-left::after {

            content: "";

            position: absolute;

            width: 190px;

            height: 190px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.06);

            bottom: -70px;

            left: -60px;

        }


        .left-content {

            position: relative;

            z-index: 2;

        }


        .logo-icon {

            width: 70px;

            height: 70px;

            border-radius: 18px;

            background:
                rgba(255, 255, 255, 0.15);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 32px;

            margin-bottom: 28px;

        }


        .login-left h1 {

            font-size: 38px;

            font-weight: 800;

            line-height: 1.2;

        }


        .login-left p {

            font-size: 16px;

            opacity: 0.85;

            margin-top: 16px;

        }


        .role-list {

            list-style: none;

            padding: 0;

            margin: 30px 0 0;

        }


        .role-list li {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 14px;

            font-size: 15px;

            opacity: 0.92;

        }


        .role-list i {

            width: 34px;

            height: 34px;

            border-radius: 10px;

            background: rgba(255, 255, 255, 0.15);

            display: flex;

            align-items: center;

            justify-content: center;

        }


        /* =================================================
           RIGHT SIDE
        ================================================= */

        .login-right {

            flex: 1.1;

            padding: 48px 52px;

            display: flex;

            flex-direction: column;

            justify-content: center;

        }


        /* =================================================
           ENTRY PILLS (role-specific convenience entries)
        ================================================= */

        .entry-pills {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 26px;

        }

        .entry-pills a {

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

            padding: 6px 14px;

            border-radius: 999px;

            border: 1px solid #dbe3ee;

            color: #5b6b81;

            background: #f7f9fc;

            transition: all 0.15s ease;

        }

        .entry-pills a:hover {

            border-color: #0d6efd;

            color: #0d6efd;

        }

        .entry-pills a.active {

            background: #0d6efd;

            border-color: #0d6efd;

            color: #ffffff;

        }


        .login-right h2 {

            font-size: 26px;

            font-weight: 800;

            color: #1e293b;

            margin-bottom: 10px;

        }


        .entry-note {

            font-size: 13.5px;

            color: #64748b;

            background: #f7f9fc;

            border: 1px solid #e6ecf5;

            border-radius: 12px;

            padding: 10px 14px;

            margin-bottom: 20px;

        }


        .form-label {

            color: #334155;

        }


        .input-group-text {

            background: #f7f9fc;

            border-color: #dbe3ee;

            color: #94a3b8;

        }


        .form-control {

            border-color: #dbe3ee;

        }

        .form-control:focus {

            border-color: #86b7fe;

            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);

        }


        .password-toggle {

            background: transparent;

            border: none;

            color: #94a3b8;

        }


        .btn-login {

            padding: 12px;

            font-weight: 700;

            border-radius: 12px;

        }


        .login-footer-note {

            font-size: 13px;

            color: #94a3b8;

            text-align: center;

            margin-top: 18px;

        }


        .register-link {

            color: #0d6efd;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 900px) {

            .login-card {

                flex-direction: column;

            }

            .login-left {

                padding: 40px;

                min-height: auto;

            }

            .role-list {

                display: none;

            }

            .login-right {

                padding: 36px 28px;

            }

        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="login-card">

        <!-- =================================================
             LEFT — BRANDING
        ================================================== -->

        <div class="login-left">

            <div class="left-content">

                <div class="logo-icon">

                    <i class="bi bi-mortarboard-fill"></i>

                </div>

                <h1>StudentHub</h1>

                <p>
                    One secure sign-in for the whole school —
                    your dashboard is chosen automatically.
                </p>


                <ul class="role-list">

                    <li>
                        <i class="bi bi-shield-lock"></i>
                        Administrators
                    </li>

                    <li>
                        <i class="bi bi-person-badge"></i>
                        Teachers
                    </li>

                    <li>
                        <i class="bi bi-person"></i>
                        Students
                    </li>

                    <li>
                        <i class="bi bi-people"></i>
                        Parents
                    </li>

                </ul>

            </div>

        </div>


        <!-- =================================================
             RIGHT — LOGIN FORM
        ================================================== -->

        <div class="login-right">


            <!-- ROLE ENTRY POINTS (same page, same service) -->

            <nav class="entry-pills" aria-label="Login entry points">

                <?php foreach ($entries as $key => $config): ?>

                    <a
                        href="login.php<?= $key === 'main' ? '' : '?entry=' . e($key) ?>"
                        class="<?= $key === $entry ? 'active' : '' ?>"
                    >
                        <?= e($config['label']) ?>
                    </a>

                <?php endforeach; ?>

            </nav>


            <h2><?= e($entries[$entry]['title']) ?></h2>

            <div class="entry-note">
                <i class="bi bi-info-circle me-1"></i>
                <?= e($entries[$entry]['note']) ?>
            </div>


            <?php if ($message !== ''): ?>

                <div class="alert alert-<?= $messageType === 'danger' ? 'danger' : 'info' ?> py-2" role="alert">

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="login.php?entry=<?= e($entry) ?>"
                autocomplete="on"
            >

                <?= csrf_field() ?>


                <!-- EMAIL -->

                <div class="mb-3">

                    <label class="form-label fw-semibold">

                        Email Address

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-envelope"></i>

                        </span>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="you@example.com"
                            value="<?= e($_POST['email'] ?? '') ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="mb-3">

                    <label class="form-label fw-semibold">

                        Password

                    </label>

                    <div class="input-group">

                        <span class="input-group-text">

                            <i class="bi bi-lock"></i>

                        </span>

                        <input
                            type="password"
                            name="password"
                            id="loginPassword"
                            class="form-control"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="btn password-toggle"
                            onclick="togglePassword()"
                            aria-label="Show or hide password"
                        >

                            <i
                                class="bi bi-eye"
                                id="loginPasswordIcon"
                            ></i>

                        </button>

                    </div>

                </div>


                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="btn btn-primary w-100 btn-login mt-3"
                >

                    <i
                        class="bi bi-box-arrow-in-right me-2"
                    ></i>

                    Sign In

                </button>

            </form>


            <!-- ROLE-SPECIFIC FOOTER ACTIONS -->

            <?php if ($entry === 'student' || $entry === 'main'): ?>

                <p class="text-center text-muted mt-4 mb-0">

                    Don't have a student account?

                    <a
                        href="register.php"
                        class="text-decoration-none fw-semibold register-link"
                    >

                        Create Account

                    </a>

                </p>

            <?php endif; ?>


            <p class="login-footer-note">

                <i class="bi bi-shield-check me-1"></i>

                Accounts are created by the administrator —
                login attempts are rate-limited and sessions are protected.

            </p>

        </div>

    </div>

</div>


<script>

    // =====================================================
    // PASSWORD SHOW / HIDE
    // =====================================================

    function togglePassword() {

        const password =
            document.getElementById("loginPassword");

        const icon =
            document.getElementById("loginPasswordIcon");


        if (password.type === "password") {

            password.type = "text";

            icon.classList.remove("bi-eye");

            icon.classList.add("bi-eye-slash");

        } else {

            password.type = "password";

            icon.classList.remove("bi-eye-slash");

            icon.classList.add("bi-eye");

        }

    }

</script>


</body>

</html>
