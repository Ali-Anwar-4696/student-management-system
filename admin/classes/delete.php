<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/ClassRoom.php';

$pdo = db();
$classRoom = new ClassRoom($pdo);


/*
|--------------------------------------------------------------------------
| Get Class ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {

    http_response_code(400);

    $class = null;

    $errorTitle = 'Invalid Class ID';

    $errorMessage =
        'The class ID provided is invalid or missing.';

} else {

    /*
    |--------------------------------------------------------------------------
    | Get Class
    |--------------------------------------------------------------------------
    */

    $class = $classRoom->getClassById($id);

    if ($class === null) {

        http_response_code(404);

        $errorTitle = 'Class Not Found';

        $errorMessage =
            'The requested class does not exist or may have already been deleted.';
    }
}

/*
|--------------------------------------------------------------------
| DEPENDENT RECORDS
|--------------------------------------------------------------------
| Structural children (sections, teacher assignments, result
| settings) may cascade with the class, but real academic records
| (students, exams, assignments, attendance) block deletion so
| history is never silently destroyed.
|--------------------------------------------------------------------
*/

$dependents = [];

$canDelete = false;

$dependentSummary = '';

if ($class !== null) {

    $dependents = $classRoom->countDependents($id);

    $blockingLabels = [
        'students'    => 'students',
        'exams'       => 'exams',
        'assignments' => 'assignments',
        'attendance'  => 'attendance records',
    ];

    $summaryParts = [];

    foreach ($blockingLabels as $key => $label) {

        if (($dependents[$key] ?? 0) > 0) {
            $summaryParts[] = $dependents[$key] . ' ' . $label;
        }
    }

    $canDelete = ($summaryParts === []);

    $dependentSummary = implode(', ', $summaryParts);
}

/*
|--------------------------------------------------------------------------
| Delete Confirmation
|--------------------------------------------------------------------------
*/

if (
    $class !== null &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    require_post_csrf();

    $confirmation = trim(
        $_POST['confirmation'] ?? ''
    );

    if (!$canDelete) {

        $errorMessage =
            'This class cannot be deleted because it still has academic records ('
            . $dependentSummary
            . '). Removing it would permanently destroy them — move or remove those records first.';

    } elseif ($confirmation !== 'DELETE') {

        $errorMessage =
            'Please type DELETE exactly to confirm this action.';

    } else {

        try {

            $deleted = $classRoom->deleteClass($id);

            if ($deleted) {

                header(
                    'Location: index.php?success=' .
                    urlencode(
                        'Class deleted successfully.'
                    )
                );

                exit;

            } else {

                $errorMessage =
                    'The class could not be deleted. It may no longer exist.';
            }

        } catch (Throwable $e) {

            $errorMessage =
                'Unable to delete this class because related records or another database constraint may prevent the operation.';
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

    <title>
        Delete Class | Student Management System
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
            padding: 45px 20px;
        }

        /* =========================
           Back Link
        ========================== */

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            color: #2563eb;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #1d4ed8;
        }

        /* =========================
           Delete Card
        ========================== */

        .delete-card {
            background: #ffffff;

            border: 1px solid #fecaca;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 10px 30px
                rgba(15, 23, 42, 0.06);
        }

        /* =========================
           Header
        ========================== */

        .delete-header {
            text-align: center;

            padding: 32px 25px 25px;

            background: #fffafa;

            border-bottom: 1px solid #fee2e2;
        }

        .warning-icon {
            width: 72px;
            height: 72px;

            margin: 0 auto 17px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #fef2f2;

            font-size: 32px;
        }

        .delete-header h1 {
            color: #991b1b;

            font-size: 25px;

            margin-bottom: 8px;
        }

        .delete-header p {
            color: #6b7280;

            font-size: 14px;

            line-height: 1.6;
        }

        /* =========================
           Body
        ========================== */

        .delete-body {
            padding: 28px;
        }

        .class-info {
            background: #f9fafb;

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 18px;

            margin-bottom: 20px;
        }

        .info-label {
            display: block;

            color: #6b7280;

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.04em;

            margin-bottom: 7px;
        }

        .class-name {
            color: #111827;

            font-size: 19px;

            font-weight: 750;

            margin-bottom: 5px;
        }

        .class-id {
            color: #6b7280;

            font-size: 13px;
        }

        /* =========================
           Danger Warning
        ========================== */

        .danger-warning {
            background: #fff7ed;

            border: 1px solid #fed7aa;

            border-radius: 12px;

            padding: 17px;

            margin-bottom: 22px;

            color: #9a3412;
        }

        .danger-warning strong {
            display: block;

            margin-bottom: 8px;

            color: #9a3412;
        }

        .danger-warning ul {
            margin-left: 20px;

            font-size: 13px;

            line-height: 1.7;
        }

        .danger-warning .important {
            margin-top: 10px;

            font-weight: 700;
        }

        /* =========================
           Error
        ========================== */

        .error-message {
            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #991b1b;

            border-radius: 10px;

            padding: 13px 15px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        /* =========================
           Confirmation
        ========================== */

        .confirmation-label {
            display: block;

            color: #374151;

            font-size: 14px;

            font-weight: 650;

            margin-bottom: 8px;
        }

        .confirmation-label span {
            color: #dc2626;
        }

        .input {
            width: 100%;

            height: 46px;

            padding: 0 14px;

            border: 1px solid #d1d5db;

            border-radius: 10px;

            font-size: 14px;

            outline: none;

            margin-bottom: 7px;
        }

        .input:focus {
            border-color: #dc2626;

            box-shadow:
                0 0 0 3px
                rgba(220, 38, 38, 0.10);
        }

        .help-text {
            display: block;

            color: #6b7280;

            font-size: 12px;

            margin-bottom: 24px;
        }

        /* =========================
           Buttons
        ========================== */

        .actions {
            display: flex;

            justify-content: flex-end;

            gap: 10px;
        }

        .btn {
            min-height: 44px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            padding: 0 18px;

            border-radius: 10px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 650;

            cursor: pointer;

            transition: 0.2s ease;
        }

        .btn-cancel {
            background: #f3f4f6;

            color: #374151;

            border: 1px solid #e5e7eb;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
        }

        .btn-delete {
            background: #dc2626;

            color: #ffffff;

            border: 1px solid #dc2626;
        }

        .btn-delete:hover {
            background: #b91c1c;

            transform: translateY(-1px);
        }

        /* =========================
           Error State
        ========================== */

        .error-card {
            background: #ffffff;

            border: 1px solid #fecaca;

            border-radius: 18px;

            padding: 45px 25px;

            text-align: center;

            box-shadow:
                0 8px 25px
                rgba(15, 23, 42, 0.05);
        }

        .error-icon {
            width: 70px;
            height: 70px;

            margin: 0 auto 18px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #fef2f2;

            font-size: 30px;
        }

        .error-card h2 {
            color: #991b1b;

            font-size: 20px;

            margin-bottom: 8px;
        }

        .error-card p {
            color: #6b7280;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 22px;
        }

        /* =========================
           Responsive
        ========================== */

        @media (max-width: 600px) {

            .container {
                padding: 25px 15px;
            }

            .delete-body {
                padding: 20px;
            }

            .delete-header {
                padding: 27px 18px 22px;
            }

            .delete-header h1 {
                font-size: 22px;
            }

            .actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <?php if ($class !== null): ?>

        <!-- Back -->

        <a
            href="index.php"
            class="back-link"
        >
            ← Back to Classes
        </a>


        <!-- Delete Card -->

        <div class="delete-card">

            <!-- Header -->

            <div class="delete-header">

                <div class="warning-icon">
                    ⚠️
                </div>

                <h1>
                    Delete Class?
                </h1>

                <p>
                    This action cannot be undone.
                    Please review the warning below before continuing.
                </p>

            </div>


            <!-- Body -->

            <div class="delete-body">

                <!-- Class Information -->

                <div class="class-info">

                    <span class="info-label">
                        Class
                    </span>

                    <div class="class-name">

                        <?= e(
                            (string) $class['name']
                        ) ?>

                    </div>

                    <div class="class-id">

                        Class ID:
                        #<?= (int) $class['id'] ?>

                    </div>

                </div>


                <!-- Danger Warning / Dependent Records -->

                <?php if ($canDelete): ?>

                    <div class="danger-warning">

                        <strong>
                            ⚠ Important: Related records may be affected
                        </strong>

                        <ul>

                            <li>
                                Sections belonging to this class will be deleted.
                            </li>

                            <li>
                                Teacher-class assignments and result
                                configurations will be removed.
                            </li>

                        </ul>

                        <div class="important">
                            Good news: this class has
                            <strong>no students, exams, assignments
                            or attendance</strong>, so no academic
                            history is lost. For production systems,
                            consider making the class
                            <strong>Inactive</strong> instead of
                            deleting it where possible.
                        </div>

                    </div>

                <?php else: ?>

                    <div
                        class="danger-warning"
                        style="background:#fef2f2; border-color:#fecaca;"
                    >

                        <strong>
                            ⛔ This class cannot be deleted yet
                        </strong>

                        <ul>

                            <?php if (($dependents['students'] ?? 0) > 0): ?>

                                <li>
                                    <?= (int) $dependents['students'] ?>
                                    enrolled student(s)
                                </li>

                            <?php endif; ?>

                            <?php if (($dependents['exams'] ?? 0) > 0): ?>

                                <li>
                                    <?= (int) $dependents['exams'] ?>
                                    exam(s)
                                </li>

                            <?php endif; ?>

                            <?php if (($dependents['assignments'] ?? 0) > 0): ?>

                                <li>
                                    <?= (int) $dependents['assignments'] ?>
                                    assignment(s)
                                </li>

                            <?php endif; ?>

                            <?php if (($dependents['attendance'] ?? 0) > 0): ?>

                                <li>
                                    <?= (int) $dependents['attendance'] ?>
                                    attendance record(s)
                                </li>

                            <?php endif; ?>

                        </ul>

                        <div class="important">
                            Deleting this class would permanently
                            destroy these academic records. Move or
                            remove them first.
                        </div>

                    </div>

                <?php endif; ?>


                <!-- Error -->

                <?php if (
                    isset($errorMessage) &&
                    $_SERVER['REQUEST_METHOD'] === 'POST'
                ): ?>

                    <div class="error-message">

                        ⚠
                        <?= e($errorMessage) ?>

                    </div>

                <?php endif; ?>


                <?php if ($canDelete): ?>

                    <!-- Confirmation Form -->

                    <form
                        method="POST"
                        action="?id=<?= (int) $id ?>"
                        onsubmit="
                            return confirm(
                                'Are you absolutely sure you want to permanently delete this class?'
                            );
                        "
                    >
                        <?= csrf_field() ?>

                        <label
                            for="confirmation"
                            class="confirmation-label"
                        >
                            Type
                            <span>DELETE</span>
                            to confirm
                        </label>

                        <input
                            type="text"
                            id="confirmation"
                            name="confirmation"
                            class="input"
                            placeholder="Type DELETE"
                            autocomplete="off"
                            required
                        >

                        <span class="help-text">
                            This extra confirmation helps prevent accidental deletion.
                        </span>


                        <!-- Actions -->

                        <div class="actions">

                            <a
                                href="index.php"
                                class="btn btn-cancel"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="btn btn-delete"
                            >
                                🗑 Permanently Delete
                            </button>

                        </div>

                    </form>

                <?php else: ?>

                    <!-- Actions (deletion blocked) -->

                    <div class="actions">

                        <a
                            href="index.php"
                            class="btn btn-cancel"
                        >
                            Back to Classes
                        </a>

                    </div>

                <?php endif; ?>

            </div>

        </div>


    <?php else: ?>

        <!-- Error State -->

        <div class="error-card">

            <div class="error-icon">
                ⚠️
            </div>

            <h2>
                <?= e($errorTitle) ?>
            </h2>

            <p>
                <?= e($errorMessage) ?>
            </p>

            <a
                href="index.php"
                class="btn btn-cancel"
            >
                ← Back to Classes
            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>

