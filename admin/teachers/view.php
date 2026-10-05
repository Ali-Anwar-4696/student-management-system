<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';
require_once __DIR__ . '/../../classes/TeacherSubject.php';
require_once __DIR__ . '/../../classes/TeacherClass.php';

$pdo = db();
$database = null;

$teacher = new Teacher($pdo);
$teacherSubject = new TeacherSubject($pdo);
$teacherClass = new TeacherClass($pdo);


// =====================================================
// GET TEACHER ID
// =====================================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('Invalid teacher ID.');
}


// =====================================================
// GET TEACHER
// =====================================================

$teacherData = $teacher->getTeacherById($id);

if ($teacherData === null) {
    die('Teacher not found.');
}


// =====================================================
// GET ASSIGNED SUBJECTS
// =====================================================

$assignedSubjects = $teacherSubject->getTeacherSubjects($id);


// =====================================================
// GET ASSIGNED CLASSES
// =====================================================

$assignedClasses = $teacherClass->getTeacherClasses($id);


// =====================================================
// SAFE OUTPUT
// =====================================================

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars(
            $value ?? '',
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


// =====================================================
// DATE FORMAT
// =====================================================

function formatDate(?string $date): string
{
    if (empty($date)) {
        return 'Not provided';
    }

    return date('d M Y', strtotime($date));
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

<title>Teacher Profile</title>

<style>

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }

    body {
        font-family: Arial, sans-serif;
        background: #f5f7fb;
        color: #1f2937;
        padding: 30px;
    }

    .container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        gap: 15px;
    }

    .top-bar h1 {
        font-size: 28px;
        margin-bottom: 5px;
    }

    .top-bar p {
        color: #6b7280;
        font-size: 14px;
    }

    .btn {
        display: inline-block;
        padding: 10px 16px;
        border-radius: 7px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
    }

    .btn-back {
        background: #e5e7eb;
        color: #111827;
    }

    .btn-edit {
        background: #2563eb;
        color: white;
    }

    .btn-subjects {
        background: #ede9fe;
        color: #6d28d9;
    }

    .btn-classes {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .profile-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .profile-header {
        background: #2563eb;
        color: white;
        padding: 30px;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .avatar {
        width: 75px;
        height: 75px;
        border-radius: 50%;
        background: white;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        font-weight: bold;
    }

    .profile-header h2 {
        font-size: 24px;
        margin-bottom: 6px;
    }

    .profile-header p {
        opacity: 0.9;
    }

    .profile-body {
        padding: 30px;
    }

    .section-title {
        font-size: 18px;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e5e7eb;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .info-item {
        background: #f9fafb;
        padding: 16px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
    }

    .label {
        display: block;
        color: #6b7280;
        font-size: 13px;
        margin-bottom: 6px;
    }

    .value {
        font-size: 15px;
        font-weight: 600;
        word-break: break-word;
    }

    .address {
        grid-column: 1 / -1;
    }

    .status {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        text-transform: capitalize;
    }

    .status-active {
        background: #dcfce7;
        color: #166534;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }


    /* =====================================================
       ASSIGNED SUBJECTS
    ===================================================== */

    .subjects-section {
        margin-top: 35px;
    }

    .subjects-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }

    .subjects-header .section-title {
        margin-bottom: 0;
        flex: 1;
    }

    .table-wrapper {
        overflow-x: auto;
    }

    .subjects-table,
    .classes-table {
        width: 100%;
        border-collapse: collapse;
    }

    .subjects-table th,
    .subjects-table td,
    .classes-table th,
    .classes-table td {
        padding: 13px 12px;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }

    .subjects-table th,
    .classes-table th {
        background: #f9fafb;
        color: #374151;
        font-size: 13px;
    }

    .subjects-table td,
    .classes-table td {
        font-size: 14px;
    }

    .subject-name,
    .class-name {
        font-weight: 600;
        color: #111827;
    }

    .subject-code {
        color: #6b7280;
    }

    .empty-subjects,
    .empty-classes {
        padding: 25px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        text-align: center;
        color: #6b7280;
    }


    /* =====================================================
       ASSIGNED CLASSES
    ===================================================== */

    .classes-section {
        margin-top: 35px;
    }

    .classes-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
    }

    .classes-header .section-title {
        margin-bottom: 0;
        flex: 1;
    }

    .section-badge {
        display: inline-block;
        padding: 5px 10px;
        border-radius: 20px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: bold;
    }


    /* =====================================================
       ACTIONS
    ===================================================== */

    .actions {
        margin-top: 30px;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }


    /* =====================================================
       RESPONSIVE
    ===================================================== */

    @media (max-width: 700px) {

        body {
            padding: 15px;
        }

        .top-bar {
            flex-direction: column;
            align-items: flex-start;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .address {
            grid-column: auto;
        }

        .profile-header {
            padding: 20px;
        }

        .profile-body {
            padding: 20px;
        }

        .actions {
            justify-content: flex-start;
            flex-wrap: wrap;
        }

        .subjects-header,
        .classes-header {
            flex-direction: column;
            align-items: flex-start;
        }

    }

</style>

</head>

<body>

<div class="container">

<!-- =====================================================
     TOP BAR
====================================================== -->

<div class="top-bar">

    <div>

        <h1>Teacher Profile</h1>

        <p>
            View complete teacher information
        </p>

    </div>

    <a
        href="index.php"
        class="btn btn-back"
    >
        ← Teachers
    </a>

</div>


<!-- =====================================================
     PROFILE CARD
====================================================== -->

<div class="profile-card">


    <!-- PROFILE HEADER -->

    <div class="profile-header">

        <div class="avatar">

            <?= e(
                strtoupper(
                    substr(
                        $teacherData['name'],
                        0,
                        1
                    )
                )
            ) ?>

        </div>

        <div>

            <h2>
                <?= e($teacherData['name']) ?>
            </h2>

            <p>
                Teacher ID:
                <?= e($teacherData['teacher_id']) ?>
            </p>

        </div>

    </div>


    <!-- PROFILE BODY -->

    <div class="profile-body">


        <!-- =================================================
             TEACHER INFORMATION
        ================================================== -->

        <h3 class="section-title">
            Teacher Information
        </h3>


        <div class="info-grid">


            <!-- TEACHER ID -->

            <div class="info-item">

                <span class="label">
                    Teacher ID
                </span>

                <span class="value">
                    <?= e($teacherData['teacher_id']) ?>
                </span>

            </div>


            <!-- NAME -->

            <div class="info-item">

                <span class="label">
                    Teacher Name
                </span>

                <span class="value">
                    <?= e($teacherData['name']) ?>
                </span>

            </div>


            <!-- PHONE -->

            <div class="info-item">

                <span class="label">
                    Phone
                </span>

                <span class="value">

                    <?= $teacherData['phone']
                        ? e($teacherData['phone'])
                        : 'Not provided'
                    ?>

                </span>

            </div>


            <!-- EMAIL -->

            <div class="info-item">

                <span class="label">
                    Email
                </span>

                <span class="value">

                    <?= $teacherData['email']
                        ? e($teacherData['email'])
                        : 'Not provided'
                    ?>

                </span>

            </div>


            <!-- JOINING DATE -->

            <div class="info-item">

                <span class="label">
                    Joining Date
                </span>

                <span class="value">
                    <?= formatDate($teacherData['joining_date']) ?>
                </span>

            </div>


            <!-- STATUS -->

            <div class="info-item">

                <span class="label">
                    Status
                </span>

                <span class="value">

                    <?php if (
                        $teacherData['status'] === 'active'
                    ): ?>

                        <span class="status status-active">
                            Active
                        </span>

                    <?php else: ?>

                        <span class="status status-inactive">
                            Inactive
                        </span>

                    <?php endif; ?>

                </span>

            </div>


            <!-- ADDRESS -->

            <div class="info-item address">

                <span class="label">
                    Address
                </span>

                <span class="value">

                    <?= $teacherData['address']
                        ? nl2br(
                            e($teacherData['address'])
                        )
                        : 'Not provided'
                    ?>

                </span>

            </div>


            <!-- CREATED -->

            <div class="info-item">

                <span class="label">
                    Created At
                </span>

                <span class="value">
                    <?= formatDate(
                        $teacherData['created_at']
                    ) ?>
                </span>

            </div>


            <!-- UPDATED -->

            <div class="info-item">

                <span class="label">
                    Last Updated
                </span>

                <span class="value">
                    <?= formatDate(
                        $teacherData['updated_at']
                    ) ?>
                </span>

            </div>


        </div>


        <!-- =================================================
             ASSIGNED SUBJECTS
        ================================================== -->

        <div class="subjects-section">

            <div class="subjects-header">

                <h3 class="section-title">
                    Assigned Subjects
                </h3>

                <a
                    href="assign-subjects.php?teacher_id=<?= (int) $teacherData['id'] ?>"
                    class="btn btn-subjects"
                >
                    Manage Subjects
                </a>

            </div>


            <?php if (!empty($assignedSubjects)): ?>

                <div class="table-wrapper">

                    <table class="subjects-table">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Subject</th>

                                <th>Code</th>

                                <th>Status</th>

                                <th>Assigned On</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach (
                            $assignedSubjects
                            as $index => $subject
                        ): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td class="subject-name">

                                    <?= e(
                                        $subject['subject_name']
                                    ) ?>

                                </td>

                                <td class="subject-code">

                                    <?= !empty(
                                        $subject['subject_code']
                                    )
                                        ? e(
                                            $subject['subject_code']
                                        )
                                        : '-'
                                    ?>

                                </td>

                                <td>

                                    <?php if (
                                        $subject['subject_status']
                                        === 'active'
                                    ): ?>

                                        <span
                                            class="status status-active"
                                        >
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status status-inactive"
                                        >
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?= formatDate(
                                        $subject['created_at']
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-subjects">

                    No subjects have been assigned to this teacher yet.

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             ASSIGNED CLASSES
        ================================================== -->

        <div class="classes-section">

            <div class="classes-header">

                <h3 class="section-title">
                    Assigned Classes
                </h3>

                <a
                    href="assign-classes.php?teacher_id=<?= (int) $teacherData['id'] ?>"
                    class="btn btn-classes"
                >
                    Manage Classes
                </a>

            </div>


            <?php if (!empty($assignedClasses)): ?>

                <div class="table-wrapper">

                    <table class="classes-table">

                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Class</th>

                                <th>Section</th>

                                <th>Subject</th>

                                <th>Code</th>

                                <th>Assigned On</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach (
                            $assignedClasses
                            as $index => $class
                        ): ?>

                            <tr>

                                <td>
                                    <?= $index + 1 ?>
                                </td>

                                <td class="class-name">

                                    <?= e(
                                        $class['class_name']
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        $class['section_name']
                                    ) ?>

                                </td>

                                <td>

                                    <?= e(
                                        $class['subject_name']
                                    ) ?>

                                </td>

                                <td class="subject-code">

                                    <?= !empty(
                                        $class['subject_code']
                                    )
                                        ? e(
                                            $class['subject_code']
                                        )
                                        : '-'
                                    ?>

                                </td>

                                <td>

                                    <?= formatDate(
                                        $class['created_at']
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-classes">

                    No classes have been assigned to this teacher yet.

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             ACTIONS
        ================================================== -->

        <div class="actions">

            <a
                href="index.php"
                class="btn btn-back"
            >
                ← Back
            </a>

            <a
                href="edit.php?id=<?= (int) $teacherData['id'] ?>"
                class="btn btn-edit"
            >
                Edit Teacher
            </a>

        </div>


    </div>

</div>

</div>

</body>

</html>
