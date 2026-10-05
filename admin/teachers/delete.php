<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';


// =====================================================
// DATABASE
// =====================================================

$pdo = db();
$database = null;

$teacher = new Teacher($pdo);


// =====================================================
// GET TEACHER ID
// =====================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    die('Invalid teacher ID.');
}


// =====================================================
// GET TEACHER
// =====================================================

$teacherData = $teacher->getTeacherById($id);

if ($teacherData === null) {
    die('Teacher not found.');
}


// =====================================================
// DEPENDENT ACADEMIC RECORDS
// =====================================================
// If the teacher already marked attendance, created
// assignments or entered marks, a permanent delete would
// destroy that history. In that case only deactivation
// is offered.
// =====================================================

$dependentCounts = $teacher->hasAcademicRecords($id);
$dependentTotal = array_sum($dependentCounts);
$canDelete = ($dependentTotal === 0);


// =====================================================
// DELETE CONFIRMATION
// =====================================================

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $postedId = filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

    $action = (string) ($_POST['action'] ?? 'deactivate');


    if (!$postedId || $postedId !== $id) {

        $error = 'Invalid request.';

    } elseif ($action === 'deactivate') {

        // =================================================
        // SOFT DELETE (preferred - keeps academic history)
        // =================================================

        try {

            $deactivated = $teacher->deactivateTeacher($id);

            if ($deactivated) {

                flash_set(
                    'success',
                    'Teacher account deactivated. Assignments, attendance and marks were kept.'
                );

                header('Location: index.php');
                exit;
            }

            $error = 'Teacher could not be deactivated.';

        } catch (Throwable $e) {

            error_log(
                'Teacher deactivate error: ' . $e->getMessage()
            );

            $error = 'Something went wrong while deactivating the teacher.';
        }

    } elseif ($action === 'delete') {

        // =================================================
        // PERMANENT DELETE (only without academic history)
        // =================================================

        try {

            if (!$canDelete) {

                $error =
                    'This teacher already has academic records '
                    . '(attendance, assignments or marks). '
                    . 'Deactivate the account instead of deleting it.';

            } else {

                $deleted = $teacher->deleteTeacher($id);

                if ($deleted) {

                    flash_set(
                        'success',
                        'Teacher account permanently deleted.'
                    );

                    header('Location: index.php');
                    exit;
                }

                $error = 'Teacher could not be deleted.';
            }

        } catch (RuntimeException $e) {

            $error = $e->getMessage();

        } catch (Throwable $e) {

            if ($e instanceof PDOException && $e->getCode() === '23000') {

                $error =
                    'This teacher cannot be deleted because '
                    . 'the teacher may be linked with other records. '
                    . 'Deactivate the account instead.';

            } else {

                error_log(
                    'Teacher delete error: ' . $e->getMessage()
                );

                $error = 'Something went wrong while deleting the teacher.';
            }
        }

    } else {

        $error = 'Invalid action.';
    }
}


// =====================================================
// ESCAPE FUNCTION
// =====================================================

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars(
            $value ?? '',
            ENT_QUOTES,
            'UTF-8'
        );
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

    <title>Remove Teacher</title>


    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }


        body {

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f5f7fb;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

            color: #1f2937;
        }


        .delete-container {

            width: 100%;

            max-width: 560px;
        }


        .delete-card {

            background: #ffffff;

            border-radius: 18px;

            padding: 35px;

            box-shadow:
                0 12px 35px
                rgba(0, 0, 0, 0.10);

            text-align: center;
        }


        /* =============================================
           WARNING ICON
        ============================================= */

        .warning-icon {

            width: 72px;

            height: 72px;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #fee2e2;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 34px;

            color: #dc2626;
        }


        /* =============================================
           TITLE
        ============================================= */

        .delete-card h1 {

            font-size: 26px;

            margin-bottom: 10px;

            color: #111827;
        }


        .subtitle {

            color: #6b7280;

            font-size: 15px;

            line-height: 1.6;

            margin-bottom: 25px;
        }


        /* =============================================
           TEACHER INFORMATION
        ============================================= */

        .teacher-info {

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            padding: 18px;

            margin-bottom: 22px;

            text-align: left;
        }


        .info-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            padding: 9px 0;

            border-bottom:
                1px solid #e5e7eb;
        }


        .info-row:last-child {

            border-bottom: none;

            padding-bottom: 0;
        }


        .info-row:first-child {

            padding-top: 0;
        }


        .info-label {

            color: #6b7280;

            font-size: 13px;

            font-weight: 600;
        }


        .info-value {

            color: #111827;

            font-size: 14px;

            font-weight: 600;

            text-align: right;

            word-break: break-word;
        }


        /* =============================================
           WARNING MESSAGE
        ============================================= */

        .warning-box {

            background: #fff7ed;

            border:
                1px solid #fed7aa;

            color: #9a3412;

            border-radius: 10px;

            padding: 14px 16px;

            margin-bottom: 20px;

            font-size: 13px;

            line-height: 1.6;

            text-align: left;
        }


        .warning-box strong {

            display: block;

            margin-bottom: 3px;

            font-size: 14px;
        }


        /* =============================================
           ERROR
        ============================================= */

        .error-box {

            background: #fef2f2;

            border:
                1px solid #fecaca;

            color: #991b1b;

            border-radius: 10px;

            padding: 14px 16px;

            margin-bottom: 20px;

            font-size: 14px;

            text-align: left;

            line-height: 1.5;
        }


        /* =============================================
           BUTTONS
        ============================================= */

        .actions {

            display: flex;

            gap: 12px;

            margin-top: 25px;
        }


        .btn {

            flex: 1;

            min-height: 46px;

            border-radius: 9px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            font-size: 14px;

            font-weight: 700;

            border: none;

            cursor: pointer;

            transition:
                transform 0.15s ease,
                opacity 0.15s ease;
        }


        .btn:hover {

            transform: translateY(-1px);

            opacity: 0.92;
        }


        .btn-cancel {

            background: #e5e7eb;

            color: #1f2937;
        }


        .btn-delete {

            background: #dc2626;

            color: #ffffff;
        }


        .btn-deactivate {

            background: #f59e0b;

            color: #ffffff;
        }


        .dependent-list {

            margin: 8px 0 0;

            padding-left: 20px;

            line-height: 1.8;

            text-align: left;
        }


        /* =============================================
           FOOTER NOTE
        ============================================= */

        .footer-note {

            margin-top: 18px;

            color: #9ca3af;

            font-size: 12px;
        }


        /* =============================================
           RESPONSIVE
        ============================================= */

        @media (max-width: 600px) {

            body {

                padding: 15px;
            }


            .delete-card {

                padding: 25px 20px;

                border-radius: 15px;
            }


            .delete-card h1 {

                font-size: 23px;
            }


            .subtitle {

                font-size: 14px;
            }


            .actions {

                flex-direction: column;
            }


            .btn {

                width: 100%;
            }


            .info-row {

                align-items: flex-start;

                flex-direction: column;

                gap: 3px;
            }


            .info-value {

                text-align: left;
            }

        }

    </style>

</head>


<body>


<div class="delete-container">


    <div class="delete-card">


        <!-- WARNING ICON -->

        <div class="warning-icon">
            ⚠
        </div>


        <!-- TITLE -->

        <h1>
            Remove Teacher?
        </h1>


        <p class="subtitle">

            Choose how this teacher should be removed.
            Deactivation is always safe: it blocks the
            login account but keeps every record.

        </p>


        <!-- ERROR -->

        <?php if ($error !== ''): ?>

            <div class="error-box">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- TEACHER INFORMATION -->

        <div class="teacher-info">


            <div class="info-row">

                <span class="info-label">
                    Teacher ID
                </span>

                <span class="info-value">
                    <?= e($teacherData['teacher_id']) ?>
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Teacher Name
                </span>

                <span class="info-value">
                    <?= e($teacherData['name']) ?>
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Email
                </span>

                <span class="info-value">

                    <?= e(
                        $teacherData['email']
                        ?: 'Not provided'
                    ) ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Status
                </span>

                <span class="info-value">

                    <?= e(
                        ucfirst(
                            $teacherData['status']
                        )
                    ) ?>

                </span>

            </div>


        </div>


        <!-- DEPENDENT RECORDS / WARNING -->

        <?php if ($dependentTotal > 0): ?>

            <div class="warning-box">

                <strong>
                    This teacher already has academic records:
                </strong>

                <ul class="dependent-list">

                    <?php foreach ($dependentCounts as $table => $count): ?>

                        <?php if ($count > 0): ?>

                            <li>
                                <?= e(ucfirst(str_replace('_', ' ', $table))) ?>
                                :
                                <?= (int) $count ?>
                            </li>

                        <?php endif; ?>

                    <?php endforeach; ?>

                </ul>

                Permanent deletion is disabled so that
                attendance, assignments and marks are never
                destroyed. Deactivate the account instead.

            </div>

        <?php else: ?>

            <div class="warning-box">

                <strong>
                    Permanent deletion cannot be undone.
                </strong>

                It is only offered because this teacher has
                no attendance, assignments or marks yet.

            </div>

        <?php endif; ?>


        <?php if (($teacherData['status'] ?? '') === 'inactive'): ?>

            <div
                class="warning-box"
                style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;"
            >

                <strong>
                    This account is already deactivated.
                </strong>

                It cannot log in, and all records were kept.

            </div>

        <?php endif; ?>


        <!-- ACTIONS -->

        <div class="actions">


            <a
                href="view.php?id=<?= (int) $id ?>"
                class="btn btn-cancel"
            >
                Cancel
            </a>


            <?php if (
                ($teacherData['status'] ?? '') !== 'inactive'
                || $canDelete
            ): ?>

                <form
                    method="POST"
                    action=""
                    style="flex: 1;"
                >
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $id ?>"
                    >


                    <div style="display:flex; gap:12px;">


                        <?php if (($teacherData['status'] ?? '') !== 'inactive'): ?>

                            <button
                                type="submit"
                                name="action"
                                value="deactivate"
                                class="btn btn-deactivate"
                                style="flex: 1;"
                                onclick="return confirm('Deactivate this teacher? The login will be blocked but all records are kept.');"
                            >
                                Deactivate
                            </button>

                        <?php endif; ?>


                        <?php if ($canDelete): ?>

                            <button
                                type="submit"
                                name="action"
                                value="delete"
                                class="btn btn-delete"
                                style="flex: 1;"
                                onclick="return confirm('Permanently delete this teacher? This cannot be undone.');"
                            >
                                Delete Permanently
                            </button>

                        <?php endif; ?>


                    </div>

                </form>

            <?php endif; ?>


        </div>


        <div class="footer-note">

            Teacher ID:
            <?= e($teacherData['teacher_id']) ?>

        </div>


    </div>


</div>


</body>

</html>