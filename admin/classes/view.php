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

/*
|--------------------------------------------------------------------------
| Invalid ID
|--------------------------------------------------------------------------
*/

if ($id === false || $id === null || $id <= 0) {

    http_response_code(400);

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
            'The requested class does not exist or may have been deleted.';
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
        <?= isset($class)
            ? e((string) $class['name']) . ' | Class Details'
            : 'Class Details'
        ?>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 35px 20px;
        }

        /* =========================
           Header
        ========================== */

        .page-header {
            margin-bottom: 24px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;

            color: #2563eb;
            text-decoration: none;

            font-size: 14px;
            font-weight: 600;

            margin-bottom: 18px;
        }

        .back-link:hover {
            color: #1d4ed8;
        }

        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .page-header h1 {
            font-size: 30px;
            color: #111827;
            margin-bottom: 6px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =========================
           Header Actions
        ========================== */

        .header-actions {
            display: flex;
            gap: 9px;
        }

        .btn {
            min-height: 42px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            padding: 0 16px;

            border-radius: 9px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 650;

            transition: 0.2s ease;
        }

        .btn-edit {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-edit:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #fef2f2;
            color: #dc2626;

            border: 1px solid #fecaca;
        }

        .btn-delete:hover {
            background: #fee2e2;
        }

        /* =========================
           Main Card
        ========================== */

        .details-card {
            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 16px;

            box-shadow:
                0 8px 25px rgba(15, 23, 42, 0.05);

            overflow: hidden;
        }

        .card-header {
            padding: 22px 25px;

            border-bottom: 1px solid #e5e7eb;

            display: flex;
            align-items: center;
            gap: 15px;
        }

        .class-icon {
            width: 52px;
            height: 52px;

            border-radius: 13px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eff6ff;

            font-size: 24px;
        }

        .card-header h2 {
            font-size: 19px;
            color: #111827;
            margin-bottom: 4px;
        }

        .card-header p {
            color: #6b7280;
            font-size: 13px;
        }

        /* =========================
           Details
        ========================== */

        .details-body {
            padding: 25px;
        }

        .details-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }

        .detail-item {
            padding: 18px;

            background: #f9fafb;

            border: 1px solid #eef0f3;

            border-radius: 12px;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .detail-label {
            display: block;

            color: #6b7280;

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.04em;

            margin-bottom: 8px;
        }

        .detail-value {
            color: #111827;

            font-size: 15px;

            font-weight: 600;

            word-break: break-word;
        }

        .description-value {
            color: #4b5563;

            font-weight: 400;

            line-height: 1.7;

            white-space: pre-wrap;
        }

        /* =========================
           Status
        ========================== */

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 11px;

            border-radius: 999px;

            font-size: 12px;
            font-weight: 700;
        }

        .badge::before {
            content: "";

            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: currentColor;
        }

        .badge-active {
            color: #15803d;
            background: #dcfce7;
        }

        .badge-inactive {
            color: #b45309;
            background: #fef3c7;
        }

        /* =========================
           Bottom Actions
        ========================== */

        .bottom-actions {
            padding: 20px 25px;

            border-top: 1px solid #e5e7eb;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 15px;
        }

        .bottom-note {
            color: #6b7280;
            font-size: 13px;
        }

        .bottom-buttons {
            display: flex;
            gap: 9px;
        }

        .btn-back {
            background: #f3f4f6;
            color: #374151;

            border: 1px solid #e5e7eb;
        }

        .btn-back:hover {
            background: #e5e7eb;
        }

        /* =========================
           Error State
        ========================== */

        .error-card {
            background: #ffffff;

            border: 1px solid #fecaca;

            border-radius: 16px;

            padding: 45px 25px;

            text-align: center;

            box-shadow:
                0 8px 25px rgba(15, 23, 42, 0.04);
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

            max-width: 550px;

            margin: 0 auto 22px;
        }

        /* =========================
           Responsive
        ========================== */

        @media (max-width: 700px) {

            .container {
                padding: 22px 15px;
            }

            .header-row {
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .header-actions {
                width: 100%;
            }

            .header-actions .btn {
                flex: 1;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .detail-item.full-width {
                grid-column: auto;
            }

            .details-body {
                padding: 18px;
            }

            .bottom-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .bottom-buttons {
                flex-direction: column;
            }

            .bottom-buttons .btn {
                width: 100%;
            }

            .card-header {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================
         Page Header
    ========================== -->

    <div class="page-header">

        <a
            href="index.php"
            class="back-link"
        >
            ← Back to Classes
        </a>

        <?php if (isset($class)): ?>

            <div class="header-row">

                <div>

                    <h1>
                        <?= e(
                            (string) $class['name']
                        ) ?>
                    </h1>

                    <p>
                        View complete class information.
                    </p>

                </div>

                <div class="header-actions">

                    <a
                        href="edit.php?id=<?= (int) $class['id'] ?>"
                        class="btn btn-edit"
                    >
                        ✏ Edit
                    </a>

                    <a
                        href="delete.php?id=<?= (int) $class['id'] ?>"
                        class="btn btn-delete"
                    >
                        🗑 Delete
                    </a>

                </div>

            </div>

        <?php endif; ?>

    </div>


    <!-- =========================
         Class Found
    ========================== -->

    <?php if (isset($class)): ?>

        <div class="details-card">

            <!-- Card Header -->

            <div class="card-header">

                <div class="class-icon">
                    🎓
                </div>

                <div>

                    <h2>
                        Class Information
                    </h2>

                    <p>
                        Details and current status of this class.
                    </p>

                </div>

            </div>


            <!-- Details -->

            <div class="details-body">

                <div class="details-grid">

                    <!-- Class Name -->

                    <div class="detail-item">

                        <span class="detail-label">
                            Class Name
                        </span>

                        <div class="detail-value">

                            <?= e(
                                (string) $class['name']
                            ) ?>

                        </div>

                    </div>


                    <!-- Status -->

                    <div class="detail-item">

                        <span class="detail-label">
                            Status
                        </span>

                        <div>

                            <?php if (
                                $class['status'] === 'active'
                            ): ?>

                                <span
                                    class="badge badge-active"
                                >
                                    Active
                                </span>

                            <?php else: ?>

                                <span
                                    class="badge badge-inactive"
                                >
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- Description -->

                    <div
                        class="detail-item full-width"
                    >

                        <span class="detail-label">
                            Description
                        </span>

                        <div
                            class="detail-value description-value"
                        >

                            <?php if (
                                !empty($class['description'])
                            ): ?>

                                <?= e(
                                    (string)
                                    $class['description']
                                ) ?>

                            <?php else: ?>

                                No description has been added
                                for this class.

                            <?php endif; ?>

                        </div>

                    </div>


                    <!-- Created -->

                    <div class="detail-item">

                        <span class="detail-label">
                            Created At
                        </span>

                        <div class="detail-value">

                            <?= e(
                                date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        (string)
                                        $class['created_at']
                                    )
                                )
                            ) ?>

                        </div>

                    </div>


                    <!-- Updated -->

                    <div class="detail-item">

                        <span class="detail-label">
                            Last Updated
                        </span>

                        <div class="detail-value">

                            <?= e(
                                date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        (string)
                                        $class['updated_at']
                                    )
                                )
                            ) ?>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Bottom Actions -->

            <div class="bottom-actions">

                <div class="bottom-note">

                    Class ID:
                    <strong>
                        #<?= (int) $class['id'] ?>
                    </strong>

                </div>

                <div class="bottom-buttons">

                    <a
                        href="index.php"
                        class="btn btn-back"
                    >
                        ← Back
                    </a>

                    <a
                        href="edit.php?id=<?= (int) $class['id'] ?>"
                        class="btn btn-edit"
                    >
                        ✏ Edit Class
                    </a>

                </div>

            </div>

        </div>


    <?php else: ?>

        <!-- =========================
             Error State
        ========================== -->

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
                class="btn btn-back"
            >
                ← Back to Classes
            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>

