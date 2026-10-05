<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Section.php';

$pdo = db();
$section = new Section($pdo);



// =====================================================
// VALIDATE ID
// =====================================================

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id <= 0) {
    die("Invalid section ID.");
}


// =====================================================
// GET SECTION
// =====================================================

$sectionData = $section->getSectionById((int) $id);

if ($sectionData === null) {
    die("Section not found.");
}


$errors = [];


// =====================================================
// DEPENDENT RECORDS
// =====================================================
// Students, attendance and assignments block the deletion
// so academic history is never silently destroyed.
// =====================================================

$dependents = $section->countDependents((int) $id);

$blockingLabels = [
    'students'    => 'students',
    'attendance'  => 'attendance records',
    'assignments' => 'assignments',
];

$summaryParts = [];

foreach ($blockingLabels as $key => $label) {

    if (($dependents[$key] ?? 0) > 0) {
        $summaryParts[] = $dependents[$key] . ' ' . $label;
    }
}

$canDelete = ($summaryParts === []);

$dependentSummary = implode(', ', $summaryParts);


// =====================================================
// DELETE REQUEST
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    $confirmation = trim(
        $_POST['confirmation'] ?? ''
    );


    // ================================================
    // CONFIRMATION CHECK
    // ================================================

    if (!$canDelete) {

        $errors[] =
            'This section cannot be deleted because it still has academic records ('
            . $dependentSummary
            . '). Move or remove them first — deleting would destroy them.';

    } elseif ($confirmation !== 'DELETE') {

        $errors[] =
            'Please type DELETE exactly to confirm deletion.';

    } else {

        try {

            $success = $section->deleteSection(
                (int) $id
            );

            if ($success) {

                header(
                    'Location: index.php?success=' .
                    urlencode('Section deleted successfully.')
                );

                exit;
            }

            $errors[] =
                'Unable to delete section. Please try again.';

        } catch (Throwable $e) {

            $errors[] =
                'An unexpected error occurred. Please try again.';
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

    <title>Delete Section</title>

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

            background: #f4f6f9;
            color: #1f2937;
        }

        .container {
            width: 92%;
            max-width: 700px;
            margin: 50px auto;
        }

        /* =========================================
           CARD
        ========================================= */

        .card {
            background: white;

            padding: 35px;

            border-radius: 14px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        /* =========================================
           WARNING
        ========================================= */

        .warning-icon {
            width: 65px;
            height: 65px;

            margin: 0 auto 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #fee2e2;

            font-size: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 27px;
            margin-bottom: 8px;
        }

        .header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =========================================
           SECTION INFO
        ========================================= */

        .section-info {
            background: #f9fafb;

            border: 1px solid #e5e7eb;

            border-radius: 10px;

            padding: 18px;

            margin-bottom: 22px;
        }

        .info-row {
            display: flex;

            justify-content: space-between;

            gap: 20px;

            padding: 9px 0;

            border-bottom: 1px solid #e5e7eb;

            font-size: 14px;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6b7280;
            font-weight: 600;
        }

        .info-value {
            font-weight: 600;
            text-align: right;
        }

        /* =========================================
           DANGER MESSAGE
        ========================================= */

        .danger-box {
            background: #fff7ed;

            border: 1px solid #fed7aa;

            color: #9a3412;

            border-radius: 9px;

            padding: 15px;

            margin-bottom: 22px;

            font-size: 13px;

            line-height: 1.6;
        }

        .danger-box strong {
            display: block;
            margin-bottom: 5px;
        }

        /* =========================================
           ERROR
        ========================================= */

        .error-box {
            background: #fee2e2;

            border: 1px solid #fecaca;

            color: #991b1b;

            border-radius: 8px;

            padding: 13px 15px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        /* =========================================
           FORM
        ========================================= */

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;

            margin-bottom: 8px;

            font-size: 14px;

            font-weight: 600;

            color: #374151;
        }

        .form-control {
            width: 100%;

            padding: 12px 14px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 14px;

            outline: none;
        }

        .form-control:focus {
            border-color: #dc2626;

            box-shadow:
                0 0 0 3px rgba(220, 38, 38, 0.10);
        }

        .help-text {
            display: block;

            margin-top: 6px;

            color: #6b7280;

            font-size: 12px;
        }

        /* =========================================
           BUTTONS
        ========================================= */

        .actions {
            display: flex;

            gap: 10px;

            margin-top: 25px;
        }

        .btn {
            flex: 1;

            padding: 12px 18px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            text-align: center;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;
        }

        .btn-danger {
            background: #dc2626;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 600px) {

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .card {
                padding: 22px;
            }

            .header h1 {
                font-size: 24px;
            }

            .actions {
                flex-direction: column;
            }

            .info-row {
                flex-direction: column;
                gap: 4px;
            }

            .info-value {
                text-align: left;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <div class="card">

        <!-- =========================================
             WARNING ICON
        ========================================== -->

        <div class="warning-icon">
            ⚠️
        </div>


        <!-- =========================================
             HEADER
        ========================================== -->

        <div class="header">

            <h1>
                Delete Section
            </h1>

            <p>
                You are about to permanently delete this section.
            </p>

        </div>


        <!-- =========================================
             SECTION INFORMATION
        ========================================== -->

        <div class="section-info">

            <div class="info-row">

                <span class="info-label">
                    Section ID
                </span>

                <span class="info-value">
                    #<?= (int) $sectionData['id'] ?>
                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Section Name
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        $sectionData['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Assigned Class
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        $sectionData['class_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


            <div class="info-row">

                <span class="info-label">
                    Status
                </span>

                <span class="info-value">

                    <?= htmlspecialchars(
                        ucfirst(
                            $sectionData['status']
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>

        </div>


        <!-- =========================================
             WARNING / DEPENDENT RECORDS
        ========================================== -->

        <?php if ($canDelete): ?>

            <div class="danger-box">

                <strong>
                    ⚠️ Important Warning
                </strong>

                Deleting this section is permanent.
                Empty sections, teacher-class assignments
                and result configurations that belong to
                this section will be removed with it.

                <br><br>

                If you only want to stop using this section,
                consider changing its status to
                <strong>Inactive</strong> instead.

            </div>

        <?php else: ?>

            <div class="danger-box" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;">

                <strong>
                    ⛔ This section cannot be deleted yet
                </strong>

                <ul style="margin:8px 0 8px 20px;">

                    <?php if (($dependents['students'] ?? 0) > 0): ?>

                        <li>
                            <?= (int) $dependents['students'] ?>
                            enrolled student(s)
                        </li>

                    <?php endif; ?>

                    <?php if (($dependents['attendance'] ?? 0) > 0): ?>

                        <li>
                            <?= (int) $dependents['attendance'] ?>
                            attendance record(s)
                        </li>

                    <?php endif; ?>

                    <?php if (($dependents['assignments'] ?? 0) > 0): ?>

                        <li>
                            <?= (int) $dependents['assignments'] ?>
                            assignment(s)
                        </li>

                    <?php endif; ?>

                </ul>

                Deleting this section would permanently destroy
                these academic records. Move or remove them
                first.

            </div>

        <?php endif; ?>


        <!-- =========================================
             ERRORS
        ========================================== -->

        <?php if (!empty($errors)): ?>

            <div class="error-box">

                <?php foreach ($errors as $error): ?>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <?php if ($canDelete): ?>

            <!-- =========================================
                 DELETE FORM
            ========================================== -->

            <form
                method="POST"
                action=""
                onsubmit="
                    return confirm(
                        'Are you absolutely sure you want to delete this section?'
                    );
                "
            >
                <?= csrf_field() ?>

                <div class="form-group">

                    <label
                        for="confirmation"
                        class="form-label"
                    >
                        Type DELETE to confirm
                    </label>

                    <input
                        type="text"
                        id="confirmation"
                        name="confirmation"
                        class="form-control"
                        placeholder="Type DELETE"
                        autocomplete="off"
                        required
                    >

                    <span class="help-text">
                        Type DELETE exactly as shown above.
                    </span>

                </div>


                <!-- =====================================
                     ACTIONS
                ====================================== -->

                <div class="actions">

                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-danger"
                    >
                        Delete Section
                    </button>

                </div>

            </form>

        <?php else: ?>

            <!-- =========================================
                 ACTIONS (deletion blocked)
            ========================================== -->

            <div class="actions">

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Back to Sections
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>