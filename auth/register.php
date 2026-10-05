<?php


/*
 * init.php starts the session (with hardened cookie
 * parameters) and provides the CSRF helpers used below
 * (validateCsrfToken, csrf_field).
 */
require_once __DIR__ . '/../includes/init.php';

require_once "../config/Database.php";
require_once "../classes/Auth.php";

// Database connection
$database = new Database();
$pdo = $database->connect();

// Auth object
$auth = new Auth($pdo);

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // =================================================
    // CSRF
    // =================================================

    $csrfValid = validateCsrfToken(
        $_POST["csrf_token"] ?? null
    );

    if (!$csrfValid) {
        $message = "Invalid security token. Please refresh the page and try again.";
        $messageType = "danger";
    } else {

    $name = $_POST["name"] ?? "";
    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    // Public registration = student only
    $role = "student";

    $result = $auth->register(
        $name,
        $email,
        $password,
        $role
    );

    $message = $result["message"];

    if ($result["success"]) {
        $messageType = "success";
    } else {
        $messageType = "danger";
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

    <title>Create Account - StudentHub</title>


    <!-- Bootstrap 5 -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        /* =========================================
           PAGE
        ========================================= */

        body {
            min-height: 100vh;

            background:
                radial-gradient(
                    circle at top left,
                    rgba(13, 110, 253, 0.10),
                    transparent 35%
                ),
                #f6f8fc;

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        /* =========================================
           MAIN CARD
        ========================================= */

        .register-card {

            max-width: 1050px;

            border: 0;

            border-radius: 24px;

            overflow: hidden;

            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.08);
        }


        /* =========================================
           LEFT SIDE
        ========================================= */

        .register-info {

            min-height: 650px;

            background:
                linear-gradient(
                    145deg,
                    #0d6efd,
                    #084298
                );

            color: white;

            position: relative;

            overflow: hidden;
        }


        .register-info::before {

            content: "";

            position: absolute;

            width: 280px;
            height: 280px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.08);

            top: -120px;
            right: -100px;
        }


        .register-info::after {

            content: "";

            position: absolute;

            width: 220px;
            height: 220px;

            border-radius: 50%;

            background: rgba(255, 255, 255, 0.06);

            bottom: -100px;
            left: -90px;
        }


        .brand {

            font-size: 25px;

            font-weight: 700;

            position: relative;

            z-index: 2;
        }


        .brand span {

            opacity: 0.7;
        }


        .info-content {

            position: relative;

            z-index: 2;
        }


        .info-content h1 {

            font-size: clamp(2rem, 4vw, 2.8rem);

            font-weight: 700;

            line-height: 1.2;
        }


        .info-content p {

            color: rgba(255, 255, 255, 0.82);

            line-height: 1.7;
        }


        /* =========================================
           FEATURES
        ========================================= */

        .feature-item {

            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 18px;

            color: rgba(255, 255, 255, 0.9);

            font-size: 14px;
        }


        .feature-icon {

            width: 34px;
            height: 34px;

            flex-shrink: 0;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: rgba(255, 255, 255, 0.14);
        }


        /* =========================================
           FORM SIDE
        ========================================= */

        .register-form {

            background: white;
        }


        .form-title {

            font-weight: 700;

            color: #212529;
        }


        .form-subtitle {

            color: #6c757d;

            font-size: 14px;
        }


        /* =========================================
           INPUTS
        ========================================= */

        .form-label {

            font-size: 14px;

            font-weight: 600;

            color: #343a40;
        }


        .input-group-text {

            background: #f8f9fa;

            border-color: #dee2e6;

            color: #6c757d;
        }


        .form-control {

            height: 50px;

            border-color: #dee2e6;

            font-size: 14px;
        }


        .form-control:focus {

            border-color: #0d6efd;

            box-shadow:
                0 0 0 0.2rem rgba(13, 110, 253, 0.12);
        }


        /* =========================================
           PASSWORD BUTTON
        ========================================= */

        .password-toggle {

            border-left: 0;

            background: white;

            color: #6c757d;
        }


        .password-toggle:hover {

            background: #f8f9fa;

            color: #0d6efd;
        }


        /* =========================================
           REGISTER BUTTON
        ========================================= */

        .register-btn {

            height: 52px;

            border-radius: 10px;

            font-weight: 600;

            transition: all 0.2s ease;
        }


        .register-btn:hover {

            transform: translateY(-1px);

            box-shadow:
                0 8px 20px rgba(13, 110, 253, 0.20);
        }


        /* =========================================
           ALERT
        ========================================= */

        .alert {

            border-radius: 10px;

            font-size: 14px;
        }


        /* =========================================
           LOGIN LINK
        ========================================= */

        .login-text {

            color: #6c757d;

            font-size: 14px;
        }


        .login-text a {

            text-decoration: none;

            font-weight: 600;
        }


        /* =========================================
           MOBILE
        ========================================= */

        @media (max-width: 767.98px) {

            body {

                padding: 15px 0;
            }


            .register-card {

                border-radius: 18px;

                margin: 0 12px;
            }


            .register-info {

                min-height: auto;
            }


            .register-info .brand {

                font-size: 22px;
            }


            .info-content h1 {

                font-size: 30px;
            }


            .info-content p {

                font-size: 14px;
            }


            .register-form {

                padding: 30px 22px !important;
            }
        }


        /* =========================================
           SMALL MOBILE
        ========================================= */

        @media (max-width: 400px) {

            .register-card {

                margin: 0 8px;
            }


            .register-form {

                padding: 25px 17px !important;
            }


            .form-title {

                font-size: 24px;
            }
        }

    </style>

</head>


<body>


<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center py-4">


    <div class="card register-card w-100">


        <div class="row g-0">


            <!-- =====================================
                 LEFT SECTION
            ====================================== -->

            <div class="col-md-5 register-info p-4 p-lg-5 d-flex flex-column justify-content-between">


                <!-- BRAND -->

                <div class="brand">

                    <i class="bi bi-mortarboard-fill me-2"></i>

                    Student<span>Hub</span>

                </div>


                <!-- CONTENT -->

                <div class="info-content my-5">

                    <h1 class="mb-3">

                        Start your learning journey.

                    </h1>


                    <p class="mb-4">

                        Create your student account and
                        manage your academic activities
                        from one simple platform.

                    </p>


                    <!-- FEATURES -->

                    <div class="feature-item">

                        <div class="feature-icon">

                            <i class="bi bi-person-check"></i>

                        </div>

                        Manage your student profile

                    </div>


                    <div class="feature-item">

                        <div class="feature-icon">

                            <i class="bi bi-calendar-check"></i>

                        </div>

                        Track attendance easily

                    </div>


                    <div class="feature-item">

                        <div class="feature-icon">

                            <i class="bi bi-journal-text"></i>

                        </div>

                        Access assignments and results

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="small text-white-50">

                    Â© <?= date("Y") ?> StudentHub

                </div>

            </div>


            <!-- =====================================
                 RIGHT SECTION
            ====================================== -->

            <div class="col-md-7 register-form p-4 p-md-5">


                <!-- HEADER -->

                <div class="mb-4">

                    <h2 class="form-title mb-2">

                        Create your account

                    </h2>


                    <p class="form-subtitle mb-0">

                        Register as a student to continue.

                    </p>

                </div>


                <!-- MESSAGE -->

                <?php if ($message !== ""): ?>

                    <div
                        class="alert alert-<?= htmlspecialchars($messageType) ?> d-flex align-items-center"
                        role="alert"
                    >

                        <i
                            class="bi
                            <?= $messageType === "success"
                                ? "bi-check-circle-fill"
                                : "bi-exclamation-circle-fill"
                            ?>
                            me-2"
                        ></i>

                        <div>

                            <?= htmlspecialchars($message) ?>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form method="POST">
                    <?= csrf_field() ?>


                    <!-- NAME -->

                    <div class="mb-3">

                        <label
                            for="name"
                            class="form-label"
                        >
                            Full Name
                        </label>


                        <div class="input-group">

                            <span class="input-group-text">

                                <i class="bi bi-person"></i>

                            </span>


                            <input
                                type="text"
                                class="form-control"
                                id="name"
                                name="name"
                                placeholder="Enter your full name"
                                value="<?= htmlspecialchars($_POST["name"] ?? "") ?>"
                                autocomplete="name"
                                required
                            >

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="mb-3">

                        <label
                            for="email"
                            class="form-label"
                        >
                            Email Address
                        </label>


                        <div class="input-group">

                            <span class="input-group-text">

                                <i class="bi bi-envelope"></i>

                            </span>


                            <input
                                type="email"
                                class="form-control"
                                id="email"
                                name="email"
                                placeholder="you@example.com"
                                value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                                autocomplete="email"
                                required
                            >

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div class="mb-4">

                        <label
                            for="password"
                            class="form-label"
                        >
                            Password
                        </label>


                        <div class="input-group">

                            <span class="input-group-text">

                                <i class="bi bi-lock"></i>

                            </span>


                            <input
                                type="password"
                                class="form-control"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                autocomplete="new-password"
                                required
                            >


                            <button
                                type="button"
                                class="btn password-toggle"
                                id="passwordToggle"
                                onclick="togglePassword()"
                            >

                                <i
                                    class="bi bi-eye"
                                    id="passwordIcon"
                                ></i>

                            </button>

                        </div>


                        <div class="form-text">

                            Password must contain at least 6 characters.

                        </div>

                    </div>


                    <!-- BUTTON -->

                    <button
                        type="submit"
                        class="btn btn-primary register-btn w-100"
                    >

                        <i class="bi bi-person-plus me-2"></i>

                        Create Account

                    </button>


                </form>


                <!-- LOGIN -->

                <div class="text-center mt-4">

                    <span class="login-text">

                        Already have an account?

                        <a href="login.php">

                            Login

                        </a>

                    </span>

                </div>


            </div>

        </div>

    </div>

</div>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- Password Toggle -->

<script>

function togglePassword() {

    const password =
        document.getElementById("password");

    const icon =
        document.getElementById("passwordIcon");


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

