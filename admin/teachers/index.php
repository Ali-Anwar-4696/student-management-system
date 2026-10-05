<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';

$pdo = db();
$database = null;

$teacher = new Teacher($pdo);

// =====================================================
// SEARCH / STATUS FILTER / PAGINATION
// =====================================================

$search = trim((string) ($_GET['q'] ?? ''));

$status = trim((string) ($_GET['status'] ?? ''));

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = '';
}

$perPage = 10;

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;

if ($page < 1) {
    $page = 1;
}

$totalTeachers = $teacher->countTeachers($search, $status);

$totalPages = max(1, (int) ceil($totalTeachers / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

$teachers = $teacher->getTeachers($search, $status, $perPage, $offset);

$hasFilters = ($search !== '' || $status !== '');

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Teachers | Student Management System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1250px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* ==============================
           HEADER
        ============================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
        }

        .page-title h1 {
            margin: 0;
            font-size: 28px;
        }

        .page-title p {
            margin: 7px 0 0;
            color: #6b7280;
            font-size: 14px;
        }

        /* ==============================
           BUTTON
        ============================== */

        .btn {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 7px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #2563eb;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* ==============================
           CARD
        ============================== */

        .card {
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        /* ==============================
           TABLE
        ============================== */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        td {
            font-size: 14px;
        }

        tbody tr:hover td {
            background: #f9fafb;
        }

        /* ==============================
           TEACHER ID
        ============================== */

        .teacher-id {
            font-weight: 600;
            color: #2563eb;
        }

        /* ==============================
           STATUS
        ============================== */

        .status {
            display: inline-block;
            padding: 5px 11px;
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

        /* ==============================
           ACTIONS
        ============================== */

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .action-btn {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }

        .view {
            background: #e0f2fe;
            color: #0369a1;
        }

        .edit {
            background: #fef3c7;
            color: #92400e;
        }

        .subjects {
            background: #ede9fe;
            color: #6d28d9;
        }

        /* ==============================
           CLASSES ACTION
        ============================== */

        .classes {
            background: #dcfce7;
            color: #166534;
        }

        .delete {
            background: #fee2e2;
            color: #b91c1c;
        }

        .view:hover {
            background: #bae6fd;
        }

        .edit:hover {
            background: #fde68a;
        }

        .subjects:hover {
            background: #ddd6fe;
        }

        .classes:hover {
            background: #bbf7d0;
        }

        .delete:hover {
            background: #fecaca;
        }

        /* ==============================
           EMPTY STATE
        ============================== */

        .empty-state {
            text-align: center;
            padding: 70px 20px;
        }

        .empty-state h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .empty-state p {
            margin: 0 0 20px;
            color: #6b7280;
        }

        /* ==============================
           RESPONSIVE
        ============================== */

        @media (max-width: 700px) {

            .container {
                margin: 25px auto;
                padding: 0 12px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header .btn {
                width: 100%;
                text-align: center;
            }

            th,
            td {
                padding: 12px;
            }

        }

    </style>

</head>


<body>

<div class="container">

    <!-- =========================================
         FLASH MESSAGE (deactivate / delete ...)
    ========================================== -->

    <?php $flashMessage = flash_get(); ?>

    <?php if ($flashMessage !== null): ?>

        <div
            style="margin-bottom:16px; padding:12px 16px; border-radius:8px; font-size:14px;
                   <?php if (($flashMessage['type'] ?? '') === 'success'): ?>
                       background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46;
                   <?php else: ?>
                       background:#fef2f2; border:1px solid #fecaca; color:#991b1b;
                   <?php endif; ?>"
        >
            <?= e((string) ($flashMessage['message'] ?? '')) ?>
        </div>

    <?php endif; ?>


    <!-- =========================================
         PAGE HEADER
    ========================================== -->

    <div class="page-header">

        <div class="page-title">

            <h1>Teachers</h1>

            <p>
                Manage teacher profiles and information
            </p>

        </div>

        <a
            href="create.php"
            class="btn btn-primary"
        >
            + Add Teacher
        </a>

    </div>


    <!-- =========================================
         SEARCH + STATUS FILTER
    ========================================== -->

    <form
        method="GET"
        action="index.php"
        style="background:#fff; border-radius:12px; padding:16px 20px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,.06); display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;"
    >

        <div style="flex:1; min-width:220px;">

            <label
                for="searchInput"
                style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#374151;"
            >
                Search
            </label>

            <input
                type="search"
                id="searchInput"
                name="q"
                value="<?= e($search) ?>"
                placeholder="Teacher ID, name, phone or email"
                style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;"
            >

        </div>

        <div style="min-width:170px;">

            <label
                for="statusInput"
                style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#374151;"
            >
                Status
            </label>

            <select
                id="statusInput"
                name="status"
                style="width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; background:#fff;"
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

        </div>

        <div style="display:flex; gap:8px;">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Search
            </button>

            <a
                href="index.php"
                class="btn btn-primary"
                style="background:#e5e7eb; color:#111827;"
            >
                Reset
            </a>

            <?php
            $teacherExportQuery = http_build_query(array_filter(
                [
                    'q'      => $search,
                    'status' => $status,
                ],
                static fn ($v): bool => $v !== ''
            ));
            ?>

            <a
                href="export.php<?= $teacherExportQuery !== '' ? '?' . e($teacherExportQuery) : '' ?>"
                class="btn btn-primary"
                style="background:#0f766e;"
                title="Download the current filtered list as CSV"
            >
                Export CSV
            </a>

        </div>

    </form>


    <!-- =========================================
         TEACHERS CARD
    ========================================== -->

    <div class="card">

        <?php if (!empty($teachers)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Teacher ID</th>

                            <th>Name</th>

                            <th>Phone</th>

                            <th>Email</th>

                            <th>Joining Date</th>

                            <th>Status</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php foreach ($teachers as $index => $row): ?>

                        <tr>

                            <!-- Serial Number -->

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <!-- Teacher ID -->

                            <td>

                                <span class="teacher-id">

                                    <?= htmlspecialchars(
                                        (string) $row['teacher_id'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </span>

                            </td>


                            <!-- Name -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) $row['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <!-- Phone -->

                            <td>

                                <?= !empty($row['phone'])
                                    ? htmlspecialchars(
                                        (string) $row['phone'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '-' ?>

                            </td>


                            <!-- Email -->

                            <td>

                                <?= !empty($row['email'])
                                    ? htmlspecialchars(
                                        (string) $row['email'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '-' ?>

                            </td>


                            <!-- Joining Date -->

                            <td>

                                <?= !empty($row['joining_date'])
                                    ? htmlspecialchars(
                                        (string) $row['joining_date'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '-' ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <?php if ($row['status'] === 'active'): ?>

                                    <span class="status status-active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status status-inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="actions">

                                    <!-- View -->

                                    <a
                                        href="view.php?id=<?= (int) $row['id'] ?>"
                                        class="action-btn view"
                                    >
                                        View
                                    </a>


                                    <!-- Edit -->

                                    <a
                                        href="edit.php?id=<?= (int) $row['id'] ?>"
                                        class="action-btn edit"
                                    >
                                        Edit
                                    </a>


                                    <!-- Assign Subjects -->

                                    <a
                                        href="assign-subjects.php?teacher_id=<?= (int) $row['id'] ?>"
                                        class="action-btn subjects"
                                    >
                                        Subjects
                                    </a>


                                    <!-- Assign Classes & Sections -->

                                    <a
                                        href="assign-classes.php?teacher_id=<?= (int) $row['id'] ?>"
                                        class="action-btn classes"
                                    >
                                        Classes
                                    </a>


                                    <!-- Delete -->

                                    <a
                                        href="delete.php?id=<?= (int) $row['id'] ?>"
                                        class="action-btn delete"
                                        onclick="return confirm('Are you sure you want to delete this teacher?');"
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


            <!-- =========================================
                 FOOTER: COUNT + PAGINATION
            ========================================== -->

            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:16px 20px; border-top:1px solid #e5e7eb; font-size:14px; color:#6b7280;">

                <span>
                    Showing
                    <?= (int) $offset + count($teachers) ?>
                    of
                    <?= (int) $totalTeachers ?>
                    teachers
                    <?php if ($hasFilters): ?>
                        (filtered)
                    <?php endif; ?>
                </span>

                <?php if ($totalPages > 1): ?>

                    <div style="display:flex; gap:8px; align-items:center;">

                        <?php if ($page > 1): ?>

                            <a
                                href="index.php?<?= e(http_build_query(array_filter(['q' => $search, 'status' => $status, 'page' => $page - 1]))) ?>"
                                style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151;"
                            >
                                &larr; Previous
                            </a>

                        <?php endif; ?>

                        <span>
                            Page <?= (int) $page ?>
                            of <?= (int) $totalPages ?>
                        </span>

                        <?php if ($page < $totalPages): ?>

                            <a
                                href="index.php?<?= e(http_build_query(array_filter(['q' => $search, 'status' => $status, 'page' => $page + 1]))) ?>"
                                style="padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; text-decoration:none; color:#374151;"
                            >
                                Next &rarr;
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>


        <?php else: ?>

            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <div class="empty-state">

                <h3>
                    <?= $hasFilters
                        ? 'No Teachers Match Your Search'
                        : 'No Teachers Found' ?>
                </h3>

                <p>
                    <?php if ($hasFilters): ?>

                        Try a different keyword or clear the filters.

                    <?php else: ?>

                        There are currently no teachers in the system.

                    <?php endif; ?>
                </p>

                <?php if ($hasFilters): ?>

                    <a
                        href="index.php"
                        class="btn btn-primary"
                    >
                        Clear Filters
                    </a>

                <?php else: ?>

                    <a
                        href="create.php"
                        class="btn btn-primary"
                    >
                        + Add First Teacher
                    </a>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>