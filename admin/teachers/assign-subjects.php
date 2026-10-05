<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Teacher.php';
require_once __DIR__ . '/../../classes/TeacherSubject.php';

$pdo = db();
$database = null;


$teacherModel = new Teacher($pdo);
$teacherSubjectModel = new TeacherSubject($pdo);

$teacherId = filter_input(INPUT_GET, 'teacher_id', FILTER_VALIDATE_INT);

if (!$teacherId || $teacherId <= 0) {
    die("Invalid teacher ID.");
}

/*
|--------------------------------------------------------------------------
| Get Teacher
|--------------------------------------------------------------------------
*/

$teacher = $teacherModel->getTeacherById($teacherId);

if (!$teacher) {
    die("Teacher not found.");
}

/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$successMessage = $_GET['success'] ?? '';

/*
|--------------------------------------------------------------------------
| READ-ONLY SCREEN
|--------------------------------------------------------------------------
|
| Subjects are never assigned on their own: an assignment must always
| name a class (and section where the class has sections), because
| teacher_classes is the single authoritative authorization record.
|
| New assignments are created through assign-classes.php, which
| performs the full class/section/subject validation.
|
| This screen only LISTS the derived subjects and links to removal.
|
*//*
|--------------------------------------------------------------------------
| Get Teacher Assigned Subjects
|--------------------------------------------------------------------------
*/

$assignedSubjects = $teacherSubjectModel->getTeacherSubjects(
    $teacherId
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Assign Subjects</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .header {
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .header p {
            margin: 0;
            color: #6b7280;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        }

        .teacher-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .info-box {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
        }

        .info-box strong {
            display: block;
            margin-bottom: 5px;
            color: #374151;
        }

        .info-box span {
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
            background: white;
        }

        .btn {
            border: none;
            padding: 11px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 12px 15px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #f9fafb;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 600;
        }

        .remove-btn {
            display: inline-block;
            padding: 7px 12px;
            background: #fee2e2;
            color: #b91c1c;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }

        .remove-btn:hover {
            background: #fecaca;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #6b7280;
        }

        @media (max-width: 700px) {

            body {
                padding: 15px;
            }

            .teacher-info {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 18px;
            }

            .header h1 {
                font-size: 24px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <!-- =========================================================
         Header
    ========================================================== -->

    <div class="header">

        <h1>Assign Subjects</h1>

        <p>
            Manage subjects assigned to this teacher.
        </p>

        <a href="index.php" class="back">
            ← Teachers
        </a>

    </div>


    <!-- =========================================================
         Teacher Information
    ========================================================== -->

    <div class="card">

        <h2>Teacher Information</h2>

        <div class="teacher-info">

            <div class="info-box">

                <strong>Teacher ID</strong>

                <span>
                    <?= htmlspecialchars(
                        (string) $teacher['teacher_id']
                    ) ?>
                </span>

            </div>

            <div class="info-box">

                <strong>Teacher Name</strong>

                <span>
                    <?= htmlspecialchars(
                        (string) $teacher['name']
                    ) ?>
                </span>

            </div>

        </div>

    </div>


    <!-- =========================================================
         Messages
    ========================================================== -->

    <?php if ($successMessage !== ''): ?>

        <div class="success">
            <?= htmlspecialchars($successMessage) ?>
        </div>

    <?php endif; ?>


    <!-- =========================================================
         Assign New Subject (links to the authoritative form)
    ========================================================== -->

    <div class="card">

        <h2>Assign New Subject</h2>

        <p style="color:#6b7280; margin-top:0;">
            A subject is always assigned together with a class
            (and a section, when the class has sections), so the
            teacher's authorization is never ambiguous.
        </p>

        <a
            class="btn btn-primary"
            href="assign-classes.php?teacher_id=<?= (int) $teacherId ?>"
        >
            + Assign Subject to a Class / Section
        </a>

    </div>


    <!-- =========================================================
         Assigned Subjects
    ========================================================== -->

    <div class="card">

        <h2>Assigned Subjects</h2>

        <div class="table-wrapper">

            <?php if (!empty($assignedSubjects)): ?>

                <table>

                    <thead>

                        <tr>

                            <th>#</th>

                            <th>Subject</th>

                            <th>Code</th>

                            <th>Status</th>

                            <th>Assigned On</th>

                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach (
                        $assignedSubjects as $index => $assigned
                    ): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $assigned['subject_name']
                                ) ?>
                            </td>

                            <td>
                                <?= !empty($assigned['subject_code'])
                                    ? htmlspecialchars(
                                        (string) $assigned['subject_code']
                                    )
                                    : '-'
                                ?>
                            </td>

                            <td>

                                <span class="status">

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            (string) $assigned['subject_status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= htmlspecialchars(
                                    (string) $assigned['created_at']
                                ) ?>

                            </td>

                            <td>

                                <a
                                    href="remove-subject.php?id=<?= (int) $assigned['id'] ?>"
                                    class="remove-btn"
                                    onclick="return confirm('Are you sure you want to remove this subject from this teacher?');"
                                >
                                    Remove
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            <?php else: ?>

                <div class="empty">

                    No subjects assigned to this teacher yet.

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

</body>

</html>