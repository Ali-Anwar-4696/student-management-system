<?php

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';

// =====================================================
// DATABASE CONNECTION
// =====================================================

$pdo = db();
$database = null;



// =====================================================
// STUDENT OBJECT
// =====================================================

$student = new Student($pdo);


// =====================================================
// GET SEARCH VALUE
// =====================================================

$search = trim($_GET['search'] ?? '');


// =====================================================
// GET FILTER VALUES
// =====================================================

$status = trim($_GET['status'] ?? '');

$classId = trim($_GET['class_id'] ?? '');


// =====================================================
// PAGINATION
// =====================================================

$perPage = 10;

$page = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($page < 1) {
    $page = 1;
}


// =====================================================
// TOTAL STUDENTS
// =====================================================

$totalStudents = $student->countStudents(
    $search,
    $status,
    $classId
);


// =====================================================
// TOTAL PAGES
// =====================================================

$totalPages = max(
    1,
    (int) ceil($totalStudents / $perPage)
);


// =====================================================
// PREVENT INVALID PAGE
// =====================================================

if ($page > $totalPages) {
    $page = $totalPages;
}


// =====================================================
// OFFSET
// =====================================================

$offset = ($page - 1) * $perPage;


// =====================================================
// GET STUDENTS
// =====================================================

$students = $student->getStudents(
    $search,
    $status,
    $classId,
    $perPage,
    $offset
);


// =====================================================
// GET CLASSES
// =====================================================

$classes = $student->getClasses();


// =====================================================
// SHOWING RANGE
// =====================================================

$showingFrom = $totalStudents > 0
    ? $offset + 1
    : 0;

$showingTo = min(
    $offset + $perPage,
    $totalStudents
);


// =====================================================
// PAGINATION URL
// =====================================================

function paginationUrl(
    int $page,
    string $search,
    string $status,
    string $classId
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

    if ($classId !== '') {
        $params['class_id'] = $classId;
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

    <title>Students | Student Management</title>


    <!-- =================================================
         GOOGLE FONT
    ================================================== -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        body {

            font-family: 'Inter', sans-serif;

            background: #f5f7fb;

            color: #172033;
        }


        /* =================================================
           PAGE
        ================================================== */

        .page-wrapper {

            min-height: 100vh;

            padding: 32px;
        }


        .container {

            max-width: 1250px;

            margin: auto;
        }


        /* =================================================
           HEADER
        ================================================== */

        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }


        .header-left {

            display: flex;

            align-items: center;

            gap: 14px;
        }


        .header-icon {

            width: 52px;

            height: 52px;

            background: #4f46e5;

            color: white;

            border-radius: 14px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 24px;

            box-shadow:
                0 8px 20px
                rgba(79, 70, 229, 0.20);
        }


        .page-title h1 {

            font-size: 26px;

            font-weight: 800;
        }


        .page-title p {

            margin-top: 5px;

            color: #7b8498;

            font-size: 14px;
        }


        /* =================================================
           ADD BUTTON
        ================================================== */

        .add-btn {

            text-decoration: none;

            background: #4f46e5;

            color: white;

            padding: 12px 18px;

            border-radius: 10px;

            font-size: 14px;

            font-weight: 600;

            box-shadow:
                0 6px 15px
                rgba(79, 70, 229, 0.20);

            transition: 0.2s ease;
        }


        .add-btn:hover {

            background: #4338ca;

            transform: translateY(-1px);
        }


        /* =================================================
           STATS
        ================================================== */

        .stats-card {

            background: white;

            border: 1px solid #e7eaf0;

            border-radius: 15px;

            padding: 20px;

            margin-bottom: 20px;

            display: flex;

            align-items: center;

            gap: 15px;

            box-shadow:
                0 5px 20px
                rgba(20, 30, 55, 0.04);
        }


        .stats-icon {

            width: 45px;

            height: 45px;

            border-radius: 12px;

            background: #eef2ff;

            color: #4f46e5;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 20px;
        }


        .stats-label {

            color: #8991a3;

            font-size: 12px;

            margin-bottom: 3px;
        }


        .stats-number {

            font-size: 20px;

            font-weight: 800;
        }


        /* =================================================
           SEARCH & FILTER CARD
        ================================================== */

        .filter-card {

            background: white;

            border: 1px solid #e7eaf0;

            border-radius: 15px;

            padding: 20px;

            margin-bottom: 20px;

            box-shadow:
                0 5px 20px
                rgba(20, 30, 55, 0.04);
        }


        .filter-title {

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 18px;
        }


        /* =================================================
           SEARCH SECTION
        ================================================== */

        .search-section {

            margin-bottom: 20px;

            padding-bottom: 20px;

            border-bottom: 1px solid #edf0f4;
        }


        .section-label {

            display: block;

            font-size: 12px;

            font-weight: 700;

            color: #6b7280;

            margin-bottom: 8px;
        }


        .search-form {

            display: flex;

            gap: 10px;
        }


        .search-input-wrapper {

            position: relative;

            flex: 1;
        }


        .search-icon {

            position: absolute;

            left: 13px;

            top: 50%;

            transform: translateY(-50%);

            color: #9ca3af;

            font-size: 15px;
        }


        .search-input {

            padding-left: 40px !important;
        }


        /* =================================================
           FILTER SECTION
        ================================================== */

        .filter-section {

            display: flex;

            align-items: flex-end;

            gap: 12px;
        }


        .filter-field {

            flex: 1;
        }


        .filter-field select {

            width: 100%;
        }


        .filter-actions {

            display: flex;

            gap: 8px;
        }


        input,
        select {

            width: 100%;

            padding: 11px 13px;

            border: 1px solid #dfe3ea;

            border-radius: 9px;

            outline: none;

            font-family: inherit;

            font-size: 13px;

            background: white;

            color: #374151;

            transition: 0.2s ease;
        }


        input:focus,
        select:focus {

            border-color: #6366f1;

            box-shadow:
                0 0 0 3px
                rgba(99, 102, 241, 0.10);
        }


        /* =================================================
           SEARCH BUTTON
        ================================================== */

        .search-btn {

            border: none;

            background: #4f46e5;

            color: white;

            padding: 11px 20px;

            border-radius: 9px;

            font-family: inherit;

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            white-space: nowrap;
        }


        .search-btn:hover {

            background: #4338ca;
        }


        /* =================================================
           FILTER BUTTON
        ================================================== */

        .filter-btn {

            border: none;

            background: #0f766e;

            color: white;

            padding: 11px 18px;

            border-radius: 9px;

            font-family: inherit;

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            white-space: nowrap;
        }


        .filter-btn:hover {

            background: #115e59;
        }


        /* =================================================
           RESET BUTTON
        ================================================== */

        .reset-btn {

            text-decoration: none;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 11px 18px;

            border: 1px solid #dfe3ea;

            border-radius: 9px;

            color: #4b5563;

            font-size: 13px;

            font-weight: 600;

            background: white;

            white-space: nowrap;
        }


        .reset-btn:hover {

            background: #f8f9fb;
        }


        /* =================================================
           TABLE CARD
        ================================================== */

        .table-card {

            background: white;

            border: 1px solid #e7eaf0;

            border-radius: 15px;

            overflow: hidden;

            box-shadow:
                0 5px 25px
                rgba(20, 30, 55, 0.05);
        }


        .table-header {

            padding: 18px 22px;

            border-bottom: 1px solid #edf0f4;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .table-header h2 {

            font-size: 15px;

            font-weight: 700;
        }


        .table-header span {

            color: #8991a3;

            font-size: 12px;
        }


        .table-wrapper {

            overflow-x: auto;
        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 900px;
        }


        th {

            background: #fafbfc;

            color: #6b7280;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.4px;

            font-weight: 700;

            text-align: left;

            padding: 14px 18px;

            border-bottom: 1px solid #edf0f4;
        }


        td {

            padding: 15px 18px;

            font-size: 13px;

            color: #374151;

            border-bottom: 1px solid #f0f2f5;

            vertical-align: middle;
        }


        tbody tr {

            transition: 0.15s ease;
        }


        tbody tr:hover {

            background: #fafbff;
        }


        tbody tr:last-child td {

            border-bottom: none;
        }


        /* =================================================
           STUDENT
        ================================================== */

        .student-info {

            display: flex;

            align-items: center;

            gap: 11px;
        }


        .avatar {

            width: 38px;

            height: 38px;

            border-radius: 10px;

            background: #eef2ff;

            color: #4f46e5;

            display: flex;

            align-items: center;

            justify-content: center;

            font-weight: 700;

            font-size: 13px;
        }


        .student-name {

            font-weight: 700;

            color: #1f2937;

            margin-bottom: 3px;
        }


        .student-id {

            color: #9ca3af;

            font-size: 11px;
        }


        .father-name {

            font-weight: 500;

            color: #4b5563;
        }


        .class-name {

            font-weight: 600;

            color: #4f46e5;
        }


        /* =================================================
           STATUS
        ================================================== */

        .status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;

            text-transform: capitalize;
        }


        .status-dot {

            width: 6px;

            height: 6px;

            border-radius: 50%;

            background: currentColor;
        }


        .status-active {

            background: #ecfdf3;

            color: #087443;
        }


        .status-inactive {

            background: #f3f4f6;

            color: #6b7280;
        }


        .status-graduated {

            background: #eff6ff;

            color: #2563eb;
        }


        .status-left {

            background: #fff1f2;

            color: #be123c;
        }


        /* =================================================
           ACTIONS
        ================================================== */

        .actions {

            display: flex;

            gap: 6px;
        }


        .action-btn {

            width: 32px;

            height: 32px;

            border-radius: 8px;

            display: flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            font-size: 13px;

            border: 1px solid #e5e7eb;

            background: white;

            transition: 0.2s ease;
        }


        .view-btn {

            color: #2563eb;
        }


        .edit-btn {

            color: #d97706;
        }


        .delete-btn {

            color: #dc2626;
        }


        .action-btn:hover {

            background: #f8f9fb;

            transform: translateY(-1px);
        }


        /* =================================================
           EMPTY STATE
        ================================================== */

        .empty-state {

            padding: 60px 20px;

            text-align: center;
        }


        .empty-icon {

            font-size: 40px;

            margin-bottom: 12px;
        }


        .empty-state h3 {

            font-size: 16px;

            margin-bottom: 6px;
        }


        .empty-state p {

            color: #8991a3;

            font-size: 13px;
        }


        /* =================================================
           PAGINATION
        ================================================== */

        .pagination-area {

            padding: 18px 22px;

            border-top: 1px solid #edf0f4;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;
        }


        .pagination-info {

            color: #8991a3;

            font-size: 12px;
        }


        .pagination {

            display: flex;

            gap: 5px;

            align-items: center;
        }


        .page-link {

            min-width: 32px;

            height: 32px;

            padding: 0 9px;

            display: flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            border: 1px solid #e1e5eb;

            border-radius: 7px;

            color: #4b5563;

            font-size: 12px;

            font-weight: 600;

            background: white;
        }


        .page-link:hover {

            background: #f5f7ff;

            border-color: #c7c9ff;

            color: #4f46e5;
        }


        .page-link.active {

            background: #4f46e5;

            border-color: #4f46e5;

            color: white;
        }


        .page-link.disabled {

            color: #c4c8d0;

            pointer-events: none;
        }


        .dots {

            color: #9ca3af;

            padding: 0 3px;

            font-size: 12px;
        }


        /* =================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 900px) {

            .filter-section {

                flex-wrap: wrap;
            }

            .filter-field {

                min-width: 200px;
            }
        }


        @media (max-width: 700px) {

            .page-wrapper {

                padding: 18px;
            }


            .page-header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;
            }


            .add-btn {

                width: 100%;

                text-align: center;
            }


            .search-form {

                flex-direction: column;
            }


            .search-btn {

                width: 100%;
            }


            .filter-section {

                flex-direction: column;

                align-items: stretch;
            }


            .filter-field {

                width: 100%;
            }


            .filter-actions {

                width: 100%;
            }


            .filter-actions button,
            .filter-actions a {

                flex: 1;
            }


            .pagination-area {

                flex-direction: column;

                align-items: flex-start;
            }


            .pagination {

                width: 100%;

                overflow-x: auto;

                padding-bottom: 3px;
            }
        }


        @media (max-width: 450px) {

            .page-wrapper {

                padding: 12px;
            }


            .page-title h1 {

                font-size: 21px;
            }


            .header-icon {

                width: 45px;

                height: 45px;
            }
        }

    </style>

</head>


<body>


<div class="page-wrapper">

<div class="container">


    <!-- =================================================
         FLASH MESSAGE (approve / deactivate / delete ...)
    ================================================== -->

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


    <!-- =================================================
         PAGE HEADER
    ================================================== -->

    <div class="page-header">

        <div class="header-left">

            <div class="header-icon">
                👨‍🎓
            </div>

            <div class="page-title">

                <h1>Students</h1>

                <p>
                    Manage and view all students
                </p>

            </div>

        </div>


        <a
            href="../students/create.php"
            class="add-btn"
        >
            + Add Student
        </a>


        <?php
        $studentExportQuery = http_build_query(array_filter(
            [
                'search'   => $search,
                'status'   => $status,
                'class_id' => $classId,
            ],
            static fn ($v): bool => $v !== ''
        ));
        ?>

        <a
            href="export.php<?= $studentExportQuery !== '' ? '?' . e($studentExportQuery) : '' ?>"
            class="add-btn"
            style="background: #0f766e; margin-left: 8px;"
            title="Download the current filtered list as CSV"
        >
            ⬇ Export CSV
        </a>

    </div>


    <!-- =================================================
         TOTAL STUDENTS
    ================================================== -->

    <div class="stats-card">

        <div class="stats-icon">
            👥
        </div>

        <div>

            <div class="stats-label">
                Total Students
            </div>

            <div class="stats-number">
                <?= number_format($totalStudents) ?>
            </div>

        </div>

    </div>


    <!-- =================================================
         SEARCH & FILTER CARD
    ================================================== -->

    <div class="filter-card">

        <div class="filter-title">
            Search & Filters
        </div>


        <!-- =================================================
             SEARCH
        ================================================== -->

        <div class="search-section">

            <span class="section-label">
                Search Student
            </span>


            <form
                method="GET"
                class="search-form"
            >

                <div class="search-input-wrapper">

                    <span class="search-icon">
                        🔍
                    </span>

                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search by ID, name, father name, phone or email..."
                        value="<?= htmlspecialchars($search) ?>"
                    >

                </div>


                <button
                    type="submit"
                    class="search-btn"
                >
                    🔍 Search
                </button>

            </form>

        </div>


        <!-- =================================================
             FILTER
        ================================================== -->

        <div class="filter-section">

            <div class="filter-field">

                <span class="section-label">
                    Student Status
                </span>

                <select id="statusFilter">

                    <option value="">
                        All Status
                    </option>

                    <option
                        value="pending"
                        <?= $status === 'pending' ? 'selected' : '' ?>
                    >
                        Pending Approval
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

                    <option
                        value="graduated"
                        <?= $status === 'graduated' ? 'selected' : '' ?>
                    >
                        Graduated
                    </option>

                    <option
                        value="left"
                        <?= $status === 'left' ? 'selected' : '' ?>
                    >
                        Left
                    </option>

                </select>

            </div>


            <div class="filter-field">

                <span class="section-label">
                    Student Class
                </span>

                <select id="classFilter">

                    <option value="">
                        All Classes
                    </option>

                    <?php foreach ($classes as $class): ?>

                        <option
                            value="<?= (int) $class['id'] ?>"
                            <?= $classId == $class['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars($class['name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-actions">

                <button
                    type="button"
                    class="filter-btn"
                    onclick="applyFilters()"
                >
                    🔽 Filter
                </button>


                <a
                    href="index.php"
                    class="reset-btn"
                >
                    ↻ Reset
                </a>

            </div>

        </div>

    </div>


    <!-- =================================================
         STUDENT TABLE
    ================================================== -->

    <div class="table-card">


        <div class="table-header">

            <h2>
                Student List
            </h2>

            <span>
                <?= number_format($totalStudents) ?> records found
            </span>

        </div>


        <?php if (!empty($students)): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Student
                            </th>

                            <th>
                                Father Name
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Class
                            </th>

                            <th>
                                Admission Date
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


                    <?php foreach ($students as $row): ?>


                        <?php

                        $studentName =
                            $row['name'] ?? '';

                        $initial =
                            strtoupper(
                                substr(
                                    $studentName,
                                    0,
                                    1
                                )
                            );

                        $studentStatus =
                            $row['status'] ?? 'active';

                        ?>


                        <tr>


                            <!-- Student -->

                            <td>

                                <div class="student-info">

                                    <div class="avatar">

                                        <?= htmlspecialchars($initial) ?>

                                    </div>

                                    <div>

                                        <div class="student-name">

                                            <?= htmlspecialchars(
                                                $row['name']
                                            ) ?>

                                        </div>

                                        <div class="student-id">

                                            ID:
                                            <?= htmlspecialchars(
                                                $row['student_id']
                                            ) ?>

                                        </div>

                                    </div>

                                </div>

                            </td>


                            <!-- Father -->

                            <td>

                                <span class="father-name">

                                    <?= htmlspecialchars(
                                        $row['father_name']
                                    ) ?>

                                </span>

                            </td>


                            <!-- Phone -->

                            <td>

                                <?= $row['phone']
                                    ? htmlspecialchars($row['phone'])
                                    : '<span style="color:#9ca3af;">—</span>' ?>

                            </td>


                            <!-- Class -->

                            <td>

                                <?php if (!empty($row['class_name'])): ?>

                                    <span class="class-name">

                                        <?= htmlspecialchars(
                                            $row['class_name']
                                        ) ?>

                                    </span>

                                <?php elseif (!empty($row['class_id'])): ?>

                                    <span>
                                        Class #<?= (int) $row['class_id'] ?>
                                    </span>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">
                                        Not Assigned
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Admission Date -->

                            <td>

                                <?php if (!empty($row['admission_date'])): ?>

                                    <?= date(
                                        'd M Y',
                                        strtotime(
                                            $row['admission_date']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span style="color:#9ca3af;">
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <span
                                    class="status status-<?= htmlspecialchars(
                                        $studentStatus
                                    ) ?>"
                                >

                                    <span class="status-dot"></span>

                                    <?= htmlspecialchars(
                                        ucfirst($studentStatus)
                                    ) ?>

                                </span>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="actions">


                                    <a
                                        href="view.php?id=<?= (int) $row['id'] ?>"
                                        class="action-btn view-btn"
                                        title="View Student"
                                    >
                                        👁
                                    </a>


                                    <a
                                        href="edit.php?id=<?= (int) $row['id'] ?>"
                                        class="action-btn edit-btn"
                                        title="Edit Student"
                                    >
                                        ✎
                                    </a>


                                    <a
                                        href="delete.php?id=<?= (int) $row['id'] ?>"
                                        class="action-btn delete-btn"
                                        title="Delete Student"
                                        onclick="return confirm('Are you sure you want to delete this student?');"
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


        <?php else: ?>


            <!-- =================================================
                 EMPTY STATE
            ================================================== -->

            <div class="empty-state">

                <div class="empty-icon">
                    🎓
                </div>

                <h3>
                    No Students Found
                </h3>

                <p>
                    Try changing your search or filter,
                    or add a new student.
                </p>

            </div>


        <?php endif; ?>


        <!-- =================================================
             PAGINATION
        ================================================== -->

        <?php if ($totalStudents > 0): ?>


            <div class="pagination-area">


                <div class="pagination-info">

                    Showing

                    <strong>
                        <?= $showingFrom ?>
                    </strong>

                    to

                    <strong>
                        <?= $showingTo ?>
                    </strong>

                    of

                    <strong>
                        <?= number_format($totalStudents) ?>
                    </strong>

                    students

                </div>


                <div class="pagination">


                    <!-- Previous -->

                    <?php if ($page > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                paginationUrl(
                                    $page - 1,
                                    $search,
                                    $status,
                                    $classId
                                )
                            ) ?>"
                            class="page-link"
                        >
                            ‹
                        </a>

                    <?php else: ?>

                        <span class="page-link disabled">
                            ‹
                        </span>

                    <?php endif; ?>


                    <!-- Page Numbers -->

                    <?php

                    $startPage = max(
                        1,
                        $page - 2
                    );

                    $endPage = min(
                        $totalPages,
                        $page + 2
                    );

                    ?>


                    <?php if ($startPage > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                paginationUrl(
                                    1,
                                    $search,
                                    $status,
                                    $classId
                                )
                            ) ?>"
                            class="page-link"
                        >
                            1
                        </a>


                        <?php if ($startPage > 2): ?>

                            <span class="dots">
                                ...
                            </span>

                        <?php endif; ?>

                    <?php endif; ?>


                    <?php for (
                        $i = $startPage;
                        $i <= $endPage;
                        $i++
                    ): ?>


                        <a
                            href="<?= htmlspecialchars(
                                paginationUrl(
                                    $i,
                                    $search,
                                    $status,
                                    $classId
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


                    <?php if ($endPage < $totalPages): ?>


                        <?php if ($endPage < $totalPages - 1): ?>

                            <span class="dots">
                                ...
                            </span>

                        <?php endif; ?>


                        <a
                            href="<?= htmlspecialchars(
                                paginationUrl(
                                    $totalPages,
                                    $search,
                                    $status,
                                    $classId
                                )
                            ) ?>"
                            class="page-link"
                        >
                            <?= $totalPages ?>
                        </a>


                    <?php endif; ?>


                    <!-- Next -->

                    <?php if ($page < $totalPages): ?>

                        <a
                            href="<?= htmlspecialchars(
                                paginationUrl(
                                    $page + 1,
                                    $search,
                                    $status,
                                    $classId
                                )
                            ) ?>"
                            class="page-link"
                        >
                            ›
                        </a>

                    <?php else: ?>

                        <span class="page-link disabled">
                            ›
                        </span>

                    <?php endif; ?>


                </div>

            </div>


        <?php endif; ?>


    </div>


</div>

</div>


<!-- =====================================================
     FILTER JAVASCRIPT
====================================================== -->

<script>

function applyFilters() {

    const status =
        document.getElementById('statusFilter').value;

    const classId =
        document.getElementById('classFilter').value;


    const params = new URLSearchParams();


    if (status !== '') {

        params.set(
            'status',
            status
        );
    }


    if (classId !== '') {

        params.set(
            'class_id',
            classId
        );
    }


    window.location.href =
        'index.php?' + params.toString();
}

</script>


</body>

</html>