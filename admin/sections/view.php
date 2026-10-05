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
    $errorTitle = "Invalid Section";
    $errorMessage = "The section ID provided is invalid.";
    $sectionData = null;
} else {
    $sectionData = $section->getSectionById((int) $id);

    if ($sectionData === null) {
        $errorTitle = "Section Not Found";
        $errorMessage = "The requested section does not exist.";
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
        <?= $sectionData
            ? 'View Section'
            : 'Section Not Found' ?>
    </title>

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
            max-width: 900px;
            margin: 40px auto;
        }

        /* =========================================
           HEADER
        ========================================= */

        .back-link {
            display: inline-block;
            margin-bottom: 15px;

            text-decoration: none;
            color: #2563eb;

            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =========================================
           CARD
        ========================================= */

        .card {
            background: white;
            border-radius: 14px;
            padding: 30px;

            box-shadow:
                0 4px 15px rgba(0, 0, 0, 0.07);
        }

        /* =========================================
           DETAILS
        ========================================= */

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .detail-item {
            padding: 18px;

            background: #f9fafb;
            border: 1px solid #e5e7eb;

            border-radius: 10px;
        }

        .detail-label {
            font-size: 12px;
            color: #6b7280;

            margin-bottom: 7px;

            font-weight: 600;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #111827;

            word-break: break-word;
        }

        .description {
            grid-column: 1 / -1;
        }

        .description .detail-value {
            font-weight: 400;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* =========================================
           STATUS
        ========================================= */

        .badge {
            display: inline-block;

            padding: 6px 12px;

            border-radius: 20px;

            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* =========================================
           ACTIONS
        ========================================= */

        .actions {
            display: flex;
            gap: 10px;

            margin-top: 25px;
            padding-top: 25px;

            border-top: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-block;

            padding: 11px 18px;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-secondary:hover {
            background: #d1d5db;
        }

        /* =========================================
           ERROR
        ========================================= */

        .error-card {
            text-align: center;
            padding: 50px 25px;
        }

        .error-icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        .error-card h2 {
            margin-bottom: 10px;
            font-size: 24px;
        }

        .error-card p {
            color: #6b7280;
            margin-bottom: 25px;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 650px) {

            .container {
                width: 94%;
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .description {
                grid-column: auto;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                text-align: center;
                width: 100%;
            }

            .page-header h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <a
        href="index.php"
        class="back-link"
    >
        ← Sections
    </a>


    <?php if ($sectionData === null): ?>

        <!-- =========================================
             ERROR STATE
        ========================================== -->

        <div class="card error-card">

            <div class="error-icon">
                ⚠️
            </div>

            <h2>
                <?= htmlspecialchars(
                    $errorTitle,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h2>

            <p>
                <?= htmlspecialchars(
                    $errorMessage,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                Back to Sections
            </a>

        </div>


    <?php else: ?>

        <!-- =========================================
             HEADER
        ========================================== -->

        <div class="page-header">

            <h1>
                Section Details
            </h1>

            <p>
                View complete information about this section.
            </p>

        </div>


        <!-- =========================================
             DETAILS CARD
        ========================================== -->

        <div class="card">

            <div class="details">

                <!-- ID -->

                <div class="detail-item">

                    <div class="detail-label">
                        Section ID
                    </div>

                    <div class="detail-value">
                        #<?= (int) $sectionData['id'] ?>
                    </div>

                </div>


                <!-- SECTION NAME -->

                <div class="detail-item">

                    <div class="detail-label">
                        Section Name
                    </div>

                    <div class="detail-value">

                        <?= htmlspecialchars(
                            $sectionData['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <!-- CLASS -->

                <div class="detail-item">

                    <div class="detail-label">
                        Assigned Class
                    </div>

                    <div class="detail-value">

                        <?= htmlspecialchars(
                            $sectionData['class_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <!-- STATUS -->

                <div class="detail-item">

                    <div class="detail-label">
                        Status
                    </div>

                    <div class="detail-value">

                        <?php if (
                            $sectionData['status'] === 'active'
                        ): ?>

                            <span class="badge badge-active">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="badge badge-inactive">
                                Inactive
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- CREATED -->

                <div class="detail-item">

                    <div class="detail-label">
                        Created At
                    </div>

                    <div class="detail-value">

                        <?= htmlspecialchars(
                            date(
                                'd M Y, h:i A',
                                strtotime(
                                    $sectionData['created_at']
                                )
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>


                <!-- UPDATED -->

                <div class="detail-item">

                    <div class="detail-label">
                        Last Updated
                    </div>

                    <div class="detail-value">

                        <?= htmlspecialchars(
                            date(
                                'd M Y, h:i A',
                                strtotime(
                                    $sectionData['updated_at']
                                )
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                </div>

            </div>


            <!-- =====================================
                 ACTIONS
            ====================================== -->

            <div class="actions">

                <a
                    href="edit.php?id=<?= (int) $sectionData['id'] ?>"
                    class="btn btn-primary"
                >
                    Edit Section
                </a>

                <a
                    href="delete.php?id=<?= (int) $sectionData['id'] ?>"
                    class="btn btn-danger"
                >
                    Delete Section
                </a>

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    Back
                </a>

            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>