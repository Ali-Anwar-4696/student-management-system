<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Section.php';

$pdo = db();
$section = new Section($pdo);


// =====================================================
// SEARCH & FILTERS
// =====================================================

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$classId = null;

if (isset($_GET['class_id']) && $_GET['class_id'] !== '') {
    $validatedClassId = filter_var(
        $_GET['class_id'],
        FILTER_VALIDATE_INT
    );

    if ($validatedClassId !== false && $validatedClassId > 0) {
        $classId = $validatedClassId;
    }
}


// =====================================================
// PAGINATION
// =====================================================

$limit = 10;

$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT
);

if ($page === false || $page < 1) {
    $page = 1;
}

$totalSections = $section->countSections(
    $search,
    $status,
    $classId
);

$totalPages = max(
    1,
    (int) ceil($totalSections / $limit)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;


// =====================================================
// GET DATA
// =====================================================

$sections = $section->getSections(
    $search,
    $status,
    $classId,
    $limit,
    $offset
);

$classes = $section->getActiveClasses();


// =====================================================
// STATISTICS
// =====================================================

$totalAll = $section->countSections();

$totalActive = $section->countSections(
    '',
    'active'
);

$totalInactive = $section->countSections(
    '',
    'inactive'
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Sections Management</title>

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
            width: 95%;
            max-width: 1300px;
            margin: 40px auto;
        }

        /* =========================================
           HEADER
        ========================================= */

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

        /* =========================================
           SUCCESS MESSAGE
        ========================================= */

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* =========================================
           STAT CARDS
        ========================================= */

        .stats {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);

            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.06);
        }

        .stat-label {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
        }

        /* =========================================
           FILTER BOX
        ========================================= */

        .filter-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.06);
        }

        .filter-form {
            display: grid;

            grid-template-columns:
                2fr 1fr 1fr auto auto;

            gap: 12px;
        }

        .form-control {
            width: 100%;
            padding: 11px 13px;

            border: 1px solid #d1d5db;
            border-radius: 8px;

            font-size: 14px;
            outline: none;
        }

        .form-control:focus {
            border-color: #2563eb;
        }

        .btn-search {
            background: #111827;
            color: white;
        }

        .btn-reset {
            background: #e5e7eb;
            color: #111827;
        }

        /* =========================================
           TABLE
        ========================================= */

        .table-card {
            background: white;
            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, 0.06);

            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            background: #f9fafb;
            color: #374151;
            font-size: 13px;
            text-align: left;
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
        }

        td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        tr:hover td {
            background: #fafafa;
        }

        .section-name {
            font-weight: 600;
            color: #111827;
        }

        .class-name {
            color: #4b5563;
        }

        /* =========================================
           STATUS
        ========================================= */

        .badge {
            display: inline-block;
            padding: 5px 10px;
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
            gap: 7px;
            flex-wrap: wrap;
        }

        .action-btn {
            text-decoration: none;
            padding: 7px 11px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .view-btn {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .edit-btn {
            background: #fef3c7;
            color: #92400e;
        }

        .delete-btn {
            background: #fee2e2;
            color: #b91c1c;
        }

        /* =========================================
           EMPTY STATE
        ========================================= */

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #6b7280;
            margin-bottom: 20px;
        }

        /* =========================================
           PAGINATION
        ========================================= */

        .pagination {
            display: flex;
            justify-content: center;
            gap: 7px;
            padding: 20px;
            flex-wrap: wrap;
        }

        .page-link {
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 7px;

            background: #f3f4f6;
            color: #374151;

            font-size: 13px;
        }

        .page-link:hover {
            background: #e5e7eb;
        }

        .page-link.active {
            background: #2563eb;
            color: white;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 900px) {

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {

            .container {
                width: 92%;
                margin: 25px auto;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .page-title h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================================
         HEADER
    ========================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>Sections</h1>

            <p>
                Manage sections and assign them to classes
            </p>

        </div>

        <a
            href="create.php"
            class="btn btn-primary"
        >
            + Add Section
        </a>

    </div>


    <!-- =========================================
         SUCCESS MESSAGE
    ========================================== -->

    <?php if (isset($_GET['success']) && $_GET['success'] !== ''): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars(
                (string) $_GET['success'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================
         STATISTICS
    ========================================== -->

    <div class="stats">

        <div class="stat-card">

            <div class="stat-label">
                Total Sections
            </div>

            <div class="stat-number">
                <?= $totalAll ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Active Sections
            </div>

            <div class="stat-number">
                <?= $totalActive ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Inactive Sections
            </div>

            <div class="stat-number">
                <?= $totalInactive ?>
            </div>

        </div>

    </div>


    <!-- =========================================
         FILTERS
    ========================================== -->

    <div class="filter-box">

        <form
            method="GET"
            class="filter-form"
        >

            <input
                type="text"
                name="search"
                class="form-control"
                placeholder="Search section or class..."
                value="<?= htmlspecialchars(
                    $search,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


            <select
                name="class_id"
                class="form-control"
            >

                <option value="">
                    All Classes
                </option>

                <?php foreach ($classes as $class): ?>

                    <option
                        value="<?= (int) $class['id'] ?>"
                        <?= $classId === (int) $class['id']
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars(
                            $class['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <select
                name="status"
                class="form-control"
            >

                <option value="">
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
                class="btn btn-search"
            >
                Search
            </button>


            <a
                href="index.php"
                class="btn btn-reset"
            >
                Reset
            </a>

        </form>

    </div>


    <!-- =========================================
         TABLE
    ========================================== -->

    <div class="table-card">

        <?php if (empty($sections)): ?>

            <div class="empty-state">

                <h3>
                    No Sections Found
                </h3>

                <p>
                    No sections match your current search or filters.
                </p>

                <a
                    href="create.php"
                    class="btn btn-primary"
                >
                    + Add Section
                </a>

            </div>

        <?php else: ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Section
                            </th>

                            <th>
                                Class
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

                        <?php foreach ($sections as $index => $item): ?>

                            <tr>

                                <td>
                                    <?= $offset + $index + 1 ?>
                                </td>


                                <td>

                                    <div class="section-name">

                                        <?= htmlspecialchars(
                                            $item['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <div class="class-name">

                                        <?= htmlspecialchars(
                                            $item['class_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <?php if ($item['status'] === 'active'): ?>

                                        <span class="badge badge-active">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-inactive">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                $item['created_at']
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>


                                <td>

                                    <div class="actions">

                                        <a
                                            href="view.php?id=<?= (int) $item['id'] ?>"
                                            class="action-btn view-btn"
                                        >
                                            View
                                        </a>

                                        <a
                                            href="edit.php?id=<?= (int) $item['id'] ?>"
                                            class="action-btn edit-btn"
                                        >
                                            Edit
                                        </a>

                                        <a
                                            href="delete.php?id=<?= (int) $item['id'] ?>"
                                            class="action-btn delete-btn"
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


            <!-- =====================================
                 PAGINATION
            ====================================== -->

            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <?php

                        $query = http_build_query([
                            'page'     => $i,
                            'search'   => $search,
                            'status'   => $status,
                            'class_id' => $classId
                        ]);

                        ?>

                        <a
                            href="index.php?<?= htmlspecialchars(
                                $query,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            class="page-link <?= $page === $i
                                ? 'active'
                                : '' ?>"
                        >

                            <?= $i ?>

                        </a>

                    <?php endfor; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>