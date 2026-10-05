<?php

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';

$pdo = db();
$database = null;


$studentObj = new Student($pdo);


// =====================================================
// GET STUDENT ID
// =====================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {

    header('Location: index.php');
    exit;
}


// =====================================================
// GET STUDENT
// =====================================================

$student = $studentObj->getStudentById($id);

if (!$student) {

    header('Location: index.php');
    exit;
}


// =====================================================
// DEPENDENT ACADEMIC RECORDS
// =====================================================
//
// If the student already has marks, attendance, submissions
// or fees, a permanent delete would cascade-delete that
// history. In that case only deactivation is offered.
// =====================================================

$dependents = $studentObj->hasAcademicRecords($id);
$dependentTotal = array_sum($dependents);
$canDelete = ($dependentTotal === 0);


// =====================================================
// HELPER
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


$error = '';


// =====================================================
// DELETE CONFIRMATION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    /*
     * Make sure the submitted ID matches
     * the page ID.
     */

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

            $deactivated = $studentObj->deactivateStudent($id);

            if ($deactivated) {

                flash_set(
                    'success',
                    'Student account deactivated. All academic records were kept.'
                );

                header('Location: index.php');
                exit;

            } else {

                $error = 'Student could not be deactivated.';
            }

        } catch (Throwable $e) {

            error_log(
                'Student deactivate error: ' . $e->getMessage()
            );

            $error = 'Something went wrong while deactivating the student.';
        }

    } elseif ($action === 'delete') {

        // =================================================
        // PERMANENT DELETE (only without academic history)
        // =================================================

        try {

            if (!$canDelete) {

                $error =
                    'This student already has academic records '
                    . '(marks, attendance, submissions or fees). '
                    . 'Deactivate the account instead of deleting it.';

            } else {

                $deleted = $studentObj->deleteStudent($id);

                if ($deleted) {

                    flash_set(
                        'success',
                        'Student account permanently deleted.'
                    );

                    header('Location: index.php');
                    exit;

                } else {

                    $error = 'Student could not be deleted.';
                }
            }

        } catch (RuntimeException $e) {

            $error = $e->getMessage();

        } catch (Throwable $e) {

            if ($e instanceof PDOException && $e->getCode() === '23000') {

                $error =
                    'This student cannot be deleted because related records exist. '
                    . 'Deactivate the account instead.';

            } else {

                error_log(
                    'Student delete error: ' . $e->getMessage()
                );

                $error = 'Something went wrong while deleting the student.';
            }
        }

    } else {

        $error = 'Invalid action.';
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

    <title>Remove Student</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f9;

            color: #1f2937;
        }


        .container {
            width: 100%;

            max-width: 520px;
        }


        .card {
            background: #ffffff;

            border-radius: 16px;

            padding: 35px;

            text-align: center;

            box-shadow:
                0 10px 35px
                rgba(0, 0, 0, 0.08);
        }


        .icon {
            width: 70px;

            height: 70px;

            margin: 0 auto 20px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #fee2e2;

            color: #dc2626;

            font-size: 32px;

            font-weight: bold;
        }


        h1 {
            margin: 0 0 10px;

            font-size: 25px;

            color: #111827;
        }


        .description {
            margin: 0 0 25px;

            color: #6b7280;

            line-height: 1.6;

            font-size: 15px;
        }


        .student-box {
            background: #f9fafb;

            border: 1px solid #e5e7eb;

            border-radius: 10px;

            padding: 18px;

            margin-bottom: 25px;

            text-align: left;
        }


        .student-row {
            display: flex;

            justify-content: space-between;

            gap: 15px;

            padding: 8px 0;

            border-bottom: 1px solid #e5e7eb;
        }


        .student-row:last-child {
            border-bottom: none;
        }


        .label {
            color: #6b7280;

            font-size: 14px;
        }


        .value {
            font-weight: 600;

            color: #111827;

            text-align: right;
        }


        .warning {
            background: #fff7ed;

            border: 1px solid #fed7aa;

            color: #9a3412;

            padding: 13px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 13px;

            line-height: 1.5;
        }


        .error {
            background: #fee2e2;

            border: 1px solid #fecaca;

            color: #991b1b;

            padding: 13px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

            line-height: 1.5;
        }


        .actions {
            display: flex;

            gap: 12px;

            justify-content: center;
        }


        .btn {
            display: inline-block;

            padding: 12px 20px;

            border-radius: 8px;

            border: none;

            text-decoration: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;
        }


        .btn-cancel {
            background: #e5e7eb;

            color: #111827;
        }


        .btn-delete {
            background: #dc2626;

            color: #ffffff;
        }


        .btn-delete:hover {
            background: #b91c1c;
        }


        .btn-deactivate {
            background: #f59e0b;

            color: #ffffff;
        }


        .btn-deactivate:hover {
            background: #d97706;
        }


        .dependent-list {
            text-align: left;

            margin: 10px auto 0;

            padding-left: 22px;

            max-width: 320px;

            line-height: 1.8;
        }


        .btn-cancel:hover {
            background: #d1d5db;
        }


        @media (max-width: 500px) {

            .card {
                padding: 25px 20px;
            }


            .actions {
                flex-direction: column;
            }


            .btn {
                width: 100%;
            }


            .student-row {
                flex-direction: column;

                gap: 3px;
            }


            .value {
                text-align: left;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="card">


        <div class="icon">
            !
        </div>


        <h1>
            Remove Student?
        </h1>


        <p class="description">

            Choose how this student should be removed.
            Deactivation is always safe: it blocks the login
            account but keeps every academic record.

        </p>


        <!-- ========================================= -->
        <!-- ERROR -->
        <!-- ========================================= -->

        <?php if ($error): ?>

            <div class="error">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- ========================================= -->
        <!-- STUDENT INFORMATION -->
        <!-- ========================================= -->

        <div class="student-box">


            <div class="student-row">

                <span class="label">
                    Student ID
                </span>

                <span class="value">
                    <?= e($student['student_id']) ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Name
                </span>

                <span class="value">
                    <?= e($student['name']) ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Father Name
                </span>

                <span class="value">
                    <?= e($student['father_name']) ?>
                </span>

            </div>


            <div class="student-row">

                <span class="label">
                    Status
                </span>

                <span class="value">
                    <?= e(ucfirst($student['status'])) ?>
                </span>

            </div>


        </div>


        <!-- ========================================= -->
        <!-- DEPENDENT ACADEMIC RECORDS -->
        <!-- ========================================= -->

        <?php if ($dependentTotal > 0): ?>

            <div class="warning">

                This student already has academic records:

                <ul class="dependent-list">

                    <?php foreach ($dependents as $table => $count): ?>

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
                marks, attendance and fees are never
                destroyed. Deactivate the account instead.

            </div>

        <?php endif; ?>


        <!-- ========================================= -->
        <!-- ACTION DESCRIPTION -->
        <!-- ========================================= -->

        <p class="description" style="font-size:14px; color:#6b7280;">

            <strong>Deactivate</strong>
            &mdash; blocks the login account, keeps every
            record, and can be reversed later.

            <?php if ($canDelete): ?>

                <br>
                <strong>Delete permanently</strong>
                &mdash; removes the account and profile.
                Only offered because no academic records
                exist yet. This cannot be undone.

            <?php endif; ?>

        </p>


        <!-- ========================================= -->
        <!-- CONFIRM FORM -->
        <!-- ========================================= -->

        <form method="POST">
            <?= csrf_field() ?>


            <input
                type="hidden"
                name="id"
                value="<?= (int)$id ?>"
            >


            <div class="actions">


                <a
                    href="index.php"
                    class="btn btn-cancel"
                >
                    Cancel
                </a>


                <?php if (($student['status'] ?? '') !== 'inactive'): ?>

                    <button
                        type="submit"
                        name="action"
                        value="deactivate"
                        class="btn btn-deactivate"
                        onclick="return confirm('Deactivate this student? The login will be blocked but all records are kept.');"
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
                        onclick="return confirm('Permanently delete this student? This cannot be undone.');"
                    >
                        Delete Permanently
                    </button>

                <?php endif; ?>


            </div>


        </form>


    </div>


</div>


</body>

</html>