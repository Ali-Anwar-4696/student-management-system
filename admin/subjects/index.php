<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Subject.php';

$pdo = db();
$subject = new Subject($pdo);


// =====================================================
// Filters
// =====================================================

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');


// =====================================================
// Pagination
// =====================================================

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

$page = ($page !== false && $page !== null && $page > 0)
    ? $page
    : 1;

$limit = 10;

$offset = ($page - 1) * $limit;


// =====================================================
// Get Subjects
// =====================================================

$subjects = $subject->getSubjects(
    $search,
    $status,
    $limit,
    $offset
);


// =====================================================
// Total Records
// =====================================================

$totalSubjects = $subject->countSubjects(
    $search,
    $status
);

$totalPages = (int) ceil($totalSubjects / $limit);


// =====================================================
// Statistics
// =====================================================

$totalAll = $subject->countSubjects();

$totalActive = $subject->countSubjects(
    '',
    'active'
);

$totalInactive = $subject->countSubjects(
    '',
    'inactive'
);


// =====================================================
// Success Message
// =====================================================

$success = trim($_GET['success'] ?? '');


// =====================================================
// Helper for URL
// =====================================================

function buildUrl(
    string $search,
    string $status,
    int $page
): string {

    $params = [];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    $params['page'] = $page;

    return '?' . http_build_query($params);
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

    <title>Subjects | Student Management System</title>

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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* =================================================
           Header
        ================================================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-title h1 {
            font-size: 30px;
            margin-bottom: 7px;
        }

        .page-title p {
            color: #6b7280;
            font-size: 14px;
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

        /* =================================================
           Success
        ================================================= */

        .success-message {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* =================================================
           Stats
        ================================================= */

        .stats {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
        }

        .stat-card span {
            display: block;
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-card strong {
            font-size: 27px;
        }

        /* =================================================
           Filters
        ================================================= */

        .filter-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 220px auto auto;
            gap: 12px;
        }

        .form-control {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            outline: none;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #2563eb;
        }

        .btn-secondary {
            background: #374151;
            color: white;
        }

        .btn-light {
            background: #e5e7eb;
            color: #111827;
        }

        /* =================================================
           Table
        ================================================= */

        .table-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
        }

        .table-header {
            padding: 18px 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-header h2 {
            font-size: 18px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th,
        td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            color: #4b5563;
            font-size: 13px;
        }

        tr:hover td {
            background: #fafafa;
        }

        /* =================================================
           Status
        ================================================= */

        .status {
            display: inline-block;
            padding: 5px 10px;
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
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-btn {
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .view {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .edit {
            background: #fef3c7;
            color: #92400e;
        }

        .delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* =================================================
           Empty State
        ================================================= */

        .empty {
            text-align: center;
            padding: 50px 20px;
            color: #6b7280;
        }

        .empty h3 {
            margin-bottom: 8px;
            color: #374151;
        }

        /* =================================================
           Pagination
        ================================================= */

        .pagination {
            display: flex;
            justify-content: center;
            gap: 7px;
            padding: 20px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            text-decoration: none;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 13px;
            color: #374151;
            background: white;
        }

        .pagination .active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        /* =================================================
           Responsive
        ================================================= */

        @media (max-width: 800px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 20px 12px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- =================================================
         Page Header
    ================================================= -->

    <div class="page-header">

        <div class="page-title">

            <h1>Subjects</h1>

            <p>
                Manage school subjects and their information
            </p>

        </div>

        <a
            href="create.php"
            class="btn btn-primary"
        >
            + Add Subject
        </a>

    </div>


    <!-- =================================================
         Success Message
    ================================================= -->

    <?php if ($success !== ''): ?>

        <div class="success-message">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         Statistics
    ================================================= -->

    <div class="stats">

        <div class="stat-card">

            <span>Total Subjects</span>

            <strong>
                <?= $totalAll ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>Active Subjects</span>

            <strong>
                <?= $totalActive ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>Inactive Subjects</span>

            <strong>
                <?= $totalInactive ?>
            </strong>

        </div>

    </div>


    <!-- =================================================
         Filters
    ================================================= -->

    <div class="filter-card">

        <form
            method="GET"
            class="filter-form"
        >

            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search subject name or code..."
                value="<?= htmlspecialchars($search) ?>"
            >


            <select
                name="status"
                class="form-control"
            >

                <option value="">
                    All Status
                </option>

                <option
                    value="active"
                    <?= $status === 'active' ? 'selected' : '' ?>
                >
                    Active
                </option>

                <option
                    value="inactive"
                    <?= $status === 'inactive' ? 'selected' : '' ?>
                >
                    Inactive
                </option>

            </select>


            <button
                type="submit"
                class="btn btn-secondary"
            >
                Search
            </button>


            <a
                href="index.php"
                class="btn btn-light"
            >
                Reset
            </a>

        </form>

    </div>


    <!-- =================================================
         Subject Table
    ================================================= -->

    <div class="table-card">

        <div class="table-header">

            <h2>
                Subject List
            </h2>

        </div>


        <?php if (!empty($subjects)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>

                            <th>
                                Subject Name
                            </th>

                            <th>
                                Code
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($subjects as $item): ?>

                        <tr>

                            <td>
                                #<?= (int) $item['id'] ?>
                            </td>


                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $item['name']
                                    ) ?>
                                </strong>

                            </td>


                            <td>

                                <?= !empty($item['code'])
                                    ? htmlspecialchars($item['code'])
                                    : '<span style="color:#9ca3af;">N/A</span>'
                                ?>

                            </td>


                            <td>

                                <?php

                                $description =
                                    trim(
                                        (string) $item['description']
                                    );

                                if ($description === '') {

                                    echo '<span style="color:#9ca3af;">No description</span>';

                                } else {

                                    $shortDescription =
                                        mb_strlen($description) > 50
                                            ? mb_substr($description, 0, 50) . '...'
                                            : $description;

                                    echo htmlspecialchars(
                                        $shortDescription
                                    );
                                }

                                ?>

                            </td>


                            <td>

                                <?php if ($item['status'] === 'active'): ?>

                                    <span class="status status-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status status-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="actions">

                                    <a
                                        href="view.php?id=<?= (int) $item['id'] ?>"
                                        class="action-btn view"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="edit.php?id=<?= (int) $item['id'] ?>"
                                        class="action-btn edit"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?= (int) $item['id'] ?>"
                                        class="action-btn delete"
                                    >
                                        Delete
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 Pagination
            ================================================= -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php if ($page > 1): ?>

                        <a
                            href="<?= buildUrl(
                                $search,
                                $status,
                                $page - 1
                            ) ?>"
                        >
                            ← Previous
                        </a>

                    <?php endif; ?>


                    <?php

                    $startPage = max(1, $page - 2);

                    $endPage = min(
                        $totalPages,
                        $page + 2
                    );

                    for (
                        $i = $startPage;
                        $i <= $endPage;
                        $i++
                    ):

                    ?>

                        <?php if ($i === $page): ?>

                            <span class="active">
                                <?= $i ?>
                            </span>

                        <?php else: ?>

                            <a
                                href="<?= buildUrl(
                                    $search,
                                    $status,
                                    $i
                                ) ?>"
                            >
                                <?= $i ?>
                            </a>

                        <?php endif; ?>

                    <?php endfor; ?>


                    <?php if ($page < $totalPages): ?>

                        <a
                            href="<?= buildUrl(
                                $search,
                                $status,
                                $page + 1
                            ) ?>"
                        >
                            Next →
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


        <?php else: ?>

            <!-- Empty State -->

            <div class="empty">

                <h3>
                    No Subjects Found
                </h3>

                <p>
                    There are no subjects matching your search or filter.
                </p>

                <br>

                <a
                    href="create.php"
                    class="btn btn-primary"
                >
                    + Add First Subject
                </a>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>