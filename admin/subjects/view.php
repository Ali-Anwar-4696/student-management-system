<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Subject.php';

$pdo = db();
$subject = new Subject($pdo);



// =====================================================
// Validate ID
// =====================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(404);
    $subjectData = null;
} else {
    $subjectData = $subject->getSubjectById($id);
}


// =====================================================
// Format Date
// =====================================================

function formatDate(?string $date): string
{
    if (empty($date)) {
        return 'N/A';
    }

    return date('d M Y, h:i A', strtotime($date));
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
        <?= $subjectData
            ? htmlspecialchars($subjectData['name']) . ' | Subject'
            : 'Subject Not Found'
        ?>
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

            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* =================================================
           Header
        ================================================= */

        .page-header {
            margin-bottom: 25px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 18px;
            text-decoration: none;
            color: #2563eb;
            font-size: 14px;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .page-header h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-header p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =================================================
           Card
        ================================================= */

        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .card-header {
            padding: 22px 25px;
            border-bottom: 1px solid #e5e7eb;
        }

        .card-header h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .card-header p {
            color: #6b7280;
            font-size: 13px;
        }

        /* =================================================
           Details
        ================================================= */

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .detail-item {
            padding: 20px 25px;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-item:nth-child(odd) {
            border-right: 1px solid #f0f0f0;
        }

        .detail-label {
            display: block;
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 7px;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 15px;
            color: #111827;
            word-break: break-word;
        }

        .description {
            grid-column: 1 / -1;
        }

        .description .detail-value {
            line-height: 1.7;
            white-space: pre-wrap;
        }

        /* =================================================
           Status
        ================================================= */

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        /* =================================================
           Actions
        ================================================= */

        .actions {
            display: flex;
            gap: 10px;
            padding: 20px 25px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 11px 18px;
            border-radius: 8px;
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

        /* =================================================
           Not Found
        ================================================= */

        .not-found {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 50px 25px;
            text-align: center;
        }

        .not-found h2 {
            margin-bottom: 10px;
            color: #374151;
        }

        .not-found p {
            color: #6b7280;
            margin-bottom: 20px;
        }

        /* =================================================
           Responsive
        ================================================= */

        @media (max-width: 650px) {

            .container {
                padding: 20px 12px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .detail-item:nth-child(odd) {
                border-right: none;
            }

            .description {
                grid-column: auto;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =================================================
         Header
    ================================================= -->

    <div class="page-header">

        <a
            href="index.php"
            class="back-link"
        >
            ← Subjects
        </a>

        <?php if ($subjectData): ?>

            <h1>
                Subject Details
            </h1>

            <p>
                View complete information about this subject.
            </p>

        <?php else: ?>

            <h1>
                Subject Not Found
            </h1>

            <p>
                The requested subject could not be found.
            </p>

        <?php endif; ?>

    </div>


    <!-- =================================================
         Subject Details
    ================================================= -->

    <?php if ($subjectData): ?>

        <div class="card">

            <div class="card-header">

                <h2>
                    <?= htmlspecialchars(
                        $subjectData['name']
                    ) ?>
                </h2>

                <p>
                    Subject ID:
                    #<?= (int) $subjectData['id'] ?>
                </p>

            </div>


            <div class="details">

                <!-- ID -->

                <div class="detail-item">

                    <span class="detail-label">
                        Subject ID
                    </span>

                    <div class="detail-value">
                        #<?= (int) $subjectData['id'] ?>
                    </div>

                </div>


                <!-- Name -->

                <div class="detail-item">

                    <span class="detail-label">
                        Subject Name
                    </span>

                    <div class="detail-value">

                        <strong>
                            <?= htmlspecialchars(
                                $subjectData['name']
                            ) ?>
                        </strong>

                    </div>

                </div>


                <!-- Code -->

                <div class="detail-item">

                    <span class="detail-label">
                        Subject Code
                    </span>

                    <div class="detail-value">

                        <?php if (!empty($subjectData['code'])): ?>

                            <?= htmlspecialchars(
                                $subjectData['code']
                            ) ?>

                        <?php else: ?>

                            <span style="color:#9ca3af;">
                                Not assigned
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- Status -->

                <div class="detail-item">

                    <span class="detail-label">
                        Status
                    </span>

                    <div class="detail-value">

                        <?php if ($subjectData['status'] === 'active'): ?>

                            <span class="status status-active">
                                Active
                            </span>

                        <?php else: ?>

                            <span class="status status-inactive">
                                Inactive
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- Description -->

                <div class="detail-item description">

                    <span class="detail-label">
                        Description
                    </span>

                    <div class="detail-value">

                        <?php if (!empty(trim(
                            (string) $subjectData['description']
                        ))): ?>

                            <?= htmlspecialchars(
                                $subjectData['description']
                            ) ?>

                        <?php else: ?>

                            <span style="color:#9ca3af;">
                                No description provided.
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- Created -->

                <div class="detail-item">

                    <span class="detail-label">
                        Created At
                    </span>

                    <div class="detail-value">
                        <?= formatDate(
                            $subjectData['created_at']
                        ) ?>
                    </div>

                </div>


                <!-- Updated -->

                <div class="detail-item">

                    <span class="detail-label">
                        Last Updated
                    </span>

                    <div class="detail-value">
                        <?= formatDate(
                            $subjectData['updated_at']
                        ) ?>
                    </div>

                </div>

            </div>


            <!-- =================================================
                 Actions
            ================================================= -->

            <div class="actions">

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >
                    ← Back
                </a>

                <a
                    href="edit.php?id=<?= (int) $subjectData['id'] ?>"
                    class="btn btn-primary"
                >
                    Edit Subject
                </a>

                <a
                    href="delete.php?id=<?= (int) $subjectData['id'] ?>"
                    class="btn btn-danger"
                >
                    Delete Subject
                </a>

            </div>

        </div>

    <?php else: ?>

        <!-- =================================================
             Not Found
        ================================================= -->

        <div class="not-found">

            <h2>
                Subject Not Found
            </h2>

            <p>
                The subject you are looking for does not exist
                or the ID is invalid.
            </p>

            <a
                href="index.php"
                class="btn btn-primary"
            >
                ← Back to Subjects
            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>