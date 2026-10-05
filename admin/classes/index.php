<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/ClassRoom.php';

$pdo = db();
$database = null;

$classRoom = new ClassRoom($pdo);

/*
|--------------------------------------------------------------------------
| Search & Filter
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$limit = 10;

$page = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;

$offset = ($page - 1) * $limit;

/*
|--------------------------------------------------------------------------
| Get Classes
|--------------------------------------------------------------------------
*/

$classes = $classRoom->getClasses(
    $search,
    $status,
    $limit,
    $offset
);

$totalClasses = $classRoom->countClasses(
    $search,
    $status
);

$totalPages = $totalClasses > 0
    ? (int) ceil($totalClasses / $limit)
    : 1;

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalAll = $classRoom->countClasses();

$totalActive = $classRoom->countClasses(
    '',
    'active'
);

$totalInactive = $classRoom->countClasses(
    '',
    'inactive'
);

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Preserve Search / Filter in Pagination
|--------------------------------------------------------------------------
*/

function pageUrl(
    int $page,
    string $search,
    string $status
): string {

    $params = [
        'page' => $page
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

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

    <title>Classes | Student Management System</title>

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
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* =========================
           Header
        ========================= */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .page-title h1 {
            font-size: 30px;
            font-weight: 750;
            color: #111827;
            margin-bottom: 6px;
        }

        .page-title p {
            color: #6b7280;
            font-size: 14px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            background: #2563eb;
            color: #ffffff;

            text-decoration: none;

            padding: 12px 18px;
            border-radius: 10px;

            font-size: 14px;
            font-weight: 600;

            transition: 0.2s ease;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* =========================
           Stats
        ========================= */

        .stats-grid {
            display: grid;
            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 18px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;

            padding: 22px;

            display: flex;
            align-items: center;
            gap: 16px;

            box-shadow:
                0 4px 15px rgba(15, 23, 42, 0.04);
        }

        .stat-icon {
            width: 50px;
            height: 50px;

            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            font-size: 22px;

            background: #eff6ff;
        }

        .stat-info span {
            display: block;
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .stat-info strong {
            display: block;
            font-size: 24px;
            color: #111827;
        }

        /* =========================
           Main Card
        ========================= */

        .main-card {
            background: #ffffff;

            border: 1px solid #e5e7eb;

            border-radius: 16px;

            box-shadow:
                0 4px 15px rgba(15, 23, 42, 0.04);

            overflow: hidden;
        }

        /* =========================
           Filters
        ========================= */

        .filters {
            padding: 20px;

            border-bottom: 1px solid #e5e7eb;

            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .filter-form {
            display: flex;
            gap: 10px;
            flex: 1;

            flex-wrap: wrap;
        }

        .input,
        .select {
            height: 42px;

            border: 1px solid #d1d5db;

            border-radius: 9px;

            padding: 0 13px;

            font-size: 14px;

            background: #ffffff;

            outline: none;

            transition: 0.2s ease;
        }

        .input {
            min-width: 280px;
            flex: 1;
        }

        .input:focus,
        .select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .select {
            min-width: 150px;
        }

        .btn-search {
            height: 42px;

            border: none;

            background: #111827;
            color: #ffffff;

            padding: 0 17px;

            border-radius: 9px;

            font-size: 14px;
            font-weight: 600;

            cursor: pointer;
        }

        .btn-search:hover {
            background: #1f2937;
        }

        .btn-reset {
            height: 42px;

            display: inline-flex;
            align-items: center;

            padding: 0 15px;

            border-radius: 9px;

            background: #f3f4f6;
            color: #374151;

            text-decoration: none;

            font-size: 14px;
            font-weight: 600;
        }

        .btn-reset:hover {
            background: #e5e7eb;
        }

        /* =========================
           Table
        ========================= */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background: #f9fafb;
        }

        th {
            text-align: left;

            padding: 15px 20px;

            font-size: 12px;

            text-transform: uppercase;
            letter-spacing: 0.04em;

            color: #6b7280;

            font-weight: 700;

            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 17px 20px;

            font-size: 14px;

            border-bottom: 1px solid #f0f1f3;

            vertical-align: middle;
        }

        tbody tr:hover {
            background: #fafbff;
        }

        .class-name {
            font-weight: 700;
            color: #111827;
        }

        .description {
            max-width: 350px;

            color: #6b7280;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .date {
            color: #6b7280;
            font-size: 13px;
        }

        /* =========================
           Status
        ========================= */

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 12px;
            font-weight: 700;

            text-transform: capitalize;
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
           Actions
        ========================= */

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .action-btn {
            width: 35px;
            height: 35px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            text-decoration: none;

            font-size: 15px;

            transition: 0.2s ease;
        }

        .view-btn {
            background: #eff6ff;
            color: #2563eb;
        }

        .view-btn:hover {
            background: #dbeafe;
        }

        .edit-btn {
            background: #f3f4f6;
            color: #374151;
        }

        .edit-btn:hover {
            background: #e5e7eb;
        }

        .delete-btn {
            background: #fef2f2;
            color: #dc2626;
        }

        .delete-btn:hover {
            background: #fee2e2;
        }

        /* =========================
           Empty State
        ========================= */

        .empty-state {
            text-align: center;
            padding: 65px 20px;
        }

        .empty-icon {
            width: 70px;
            height: 70px;

            margin: 0 auto 18px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #eff6ff;

            font-size: 30px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #111827;
            margin-bottom: 7px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 14px;
        }

        /* =========================
           Pagination
        ========================= */

        .pagination-area {
            padding: 18px 20px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 15px;

            border-top: 1px solid #e5e7eb;
        }

        .pagination-info {
            color: #6b7280;
            font-size: 13px;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .page-link {
            min-width: 36px;
            height: 36px;

            padding: 0 10px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 8px;

            text-decoration: none;

            border: 1px solid #e5e7eb;

            color: #374151;

            background: #ffffff;

            font-size: 13px;
            font-weight: 600;
        }

        .page-link:hover {
            background: #f3f4f6;
        }

        .page-link.active {
            background: #2563eb;
            color: #ffffff;
            border-color: #2563eb;
        }

        .page-link.disabled {
            opacity: 0.45;
            pointer-events: none;
        }

        /* =========================
           Responsive
        ========================= */

        @media (max-width: 900px) {

            .container {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns:
                    repeat(3, 1fr);
            }

            .pagination-area {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 700px) {

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .btn-primary {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                width: 100%;
                flex-direction: column;
            }

            .input,
            .select,
            .btn-search,
            .btn-reset {
                width: 100%;
                min-width: 100%;
            }

            .container {
                padding: 15px;
            }

            .page-title h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================
         Header
    ========================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>Classes</h1>

            <p>
                Manage school classes and their information.
            </p>

        </div>

        <a
            href="create.php"
            class="btn-primary"
        >
            <span>＋</span>
            Add Class
        </a>

    </div>


    <!-- =========================
         Statistics
    ========================== -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-icon">
                🎓
            </div>

            <div class="stat-info">

                <span>Total Classes</span>

                <strong>
                    <?= $totalAll ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ✓
            </div>

            <div class="stat-info">

                <span>Active Classes</span>

                <strong>
                    <?= $totalActive ?>
                </strong>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                !
            </div>

            <div class="stat-info">

                <span>Inactive Classes</span>

                <strong>
                    <?= $totalInactive ?>
                </strong>

            </div>

        </div>

    </div>


    <!-- =========================
         Main Card
    ========================== -->

    <div class="main-card">

        <!-- Filters -->

        <div class="filters">

            <form
                method="GET"
                class="filter-form"
            >

                <input
                    type="text"
                    name="search"
                    class="input"
                    placeholder="Search class name or description..."
                    value="<?= e($search) ?>"
                >

                <select
                    name="status"
                    class="select"
                >

                    <option
                        value=""
                        <?= $status === ''
                            ? 'selected'
                            : '' ?>
                    >
                        All Status
                    </option>

                    <option
                        value="active"
                        <?= $status === 'active'
                            ? 'selected'
                            : '' ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $status === 'inactive'
                            ? 'selected'
                            : '' ?>
                    >
                        Inactive
                    </option>

                </select>

                <button
                    type="submit"
                    class="btn-search"
                >
                    Search
                </button>

                <?php if (
                    $search !== '' ||
                    $status !== ''
                ): ?>

                    <a
                        href="index.php"
                        class="btn-reset"
                    >
                        Reset
                    </a>

                <?php endif; ?>

            </form>

        </div>


        <!-- =========================
             Table
        ========================== -->

        <?php if (!empty($classes)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Description
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $classes as $index => $class
                    ): ?>

                        <tr>

                            <td>
                                <?= $offset + $index + 1 ?>
                            </td>

                            <td>

                                <div class="class-name">

                                    <?= e(
                                        (string) $class['name']
                                    ) ?>

                                </div>

                            </td>

                            <td>

                                <div class="description">

                                    <?=
                                        !empty($class['description'])
                                            ? e(
                                                (string)
                                                $class['description']
                                            )
                                            : 'No description'
                                    ?>

                                </div>

                            </td>

                            <td>

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

                            </td>

                            <td>

                                <span class="date">

                                    <?= e(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                (string)
                                                $class['created_at']
                                            )
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <div class="actions">

                                    <a
                                        href="view.php?id=<?= (int) $class['id'] ?>"
                                        class="action-btn view-btn"
                                        title="View Class"
                                    >
                                        👁
                                    </a>

                                    <a
                                        href="edit.php?id=<?= (int) $class['id'] ?>"
                                        class="action-btn edit-btn"
                                        title="Edit Class"
                                    >
                                        ✏
                                    </a>

                                    <a
                                        href="delete.php?id=<?= (int) $class['id'] ?>"
                                        class="action-btn delete-btn"
                                        title="Delete Class"
                                    >
                                        🗑
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- =========================
                 Pagination
            ========================== -->

            <div class="pagination-area">

                <div class="pagination-info">

                    Showing
                    <strong>
                        <?= $offset + 1 ?>
                    </strong>

                    to

                    <strong>
                        <?= min(
                            $offset + count($classes),
                            $totalClasses
                        ) ?>
                    </strong>

                    of

                    <strong>
                        <?= $totalClasses ?>
                    </strong>

                    classes

                </div>


                <?php if ($totalPages > 1): ?>

                    <div class="pagination">

                        <!-- Previous -->

                        <a
                            href="<?= $page > 1
                                ? e(
                                    pageUrl(
                                        $page - 1,
                                        $search,
                                        $status
                                    )
                                )
                                : '#' ?>"
                            class="page-link
                                <?= $page <= 1
                                    ? 'disabled'
                                    : '' ?>"
                        >
                            ‹
                        </a>


                        <!-- Pages -->

                        <?php for (
                            $i = 1;
                            $i <= $totalPages;
                            $i++
                        ): ?>

                            <?php

                            /*
                             * Keep pagination clean
                             * when there are many pages.
                             */

                            if (
                                $totalPages > 7 &&
                                $i !== 1 &&
                                $i !== $totalPages &&
                                abs($i - $page) > 2
                            ) {
                                continue;
                            }

                            ?>

                            <a
                                href="<?= e(
                                    pageUrl(
                                        $i,
                                        $search,
                                        $status
                                    )
                                ) ?>"
                                class="page-link
                                    <?= $i === $page
                                        ? 'active'
                                        : '' ?>"
                            >
                                <?= $i ?>
                            </a>

                        <?php endfor; ?>


                        <!-- Next -->

                        <a
                            href="<?= $page < $totalPages
                                ? e(
                                    pageUrl(
                                        $page + 1,
                                        $search,
                                        $status
                                    )
                                )
                                : '#' ?>"
                            class="page-link
                                <?= $page >= $totalPages
                                    ? 'disabled'
                                    : '' ?>"
                        >
                            ›
                        </a>

                    </div>

                <?php endif; ?>

            </div>


        <?php else: ?>

            <!-- =========================
                 Empty State
            ========================== -->

            <div class="empty-state">

                <div class="empty-icon">
                    🎓
                </div>

                <?php if (
                    $search !== '' ||
                    $status !== ''
                ): ?>

                    <h3>
                        No classes found
                    </h3>

                    <p>
                        No classes match your current
                        search or filter.
                    </p>

                <?php else: ?>

                    <h3>
                        No classes available
                    </h3>

                    <p>
                        Start by adding your first class.
                    </p>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>

