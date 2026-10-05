<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';
require_once __DIR__ . '/../../classes/Auth.php';

$pdo = db();
$database = null;


$studentObj = new Student($pdo);
$authObj = new Auth($pdo);


// =====================================================
// HELPER
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
// GET STUDENT ID
// =====================================================

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}


// =====================================================
// GET STUDENT
// =====================================================

$student = $studentObj->getStudentById($id);

if (!$student) {
    header('Location: index.php');
    exit;
}


// =====================================================
// GET CLASSES
// =====================================================

$classes = $studentObj->getClasses();


// =====================================================
// GET SECTIONS
//
// We load active sections.
// If your sections table contains class_id,
// sections will also be filtered by class.
// =====================================================

$sections = [];

try {

    $sectionStmt = $pdo->query(
        "SELECT id, name, class_id
         FROM sections
         WHERE status = 'active'
         ORDER BY name ASC"
    );

    $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    /*
     * Fallback for installations where the sections
     * table does not contain a status column.
     */
    try {

        $sectionStmt = $pdo->query(
            "SELECT id, name, class_id
             FROM sections
             ORDER BY name ASC"
        );

        $sections = $sectionStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e2) {

        error_log(
            'Sections Loading Error: ' . $e2->getMessage()
        );

        $sections = [];
    }
}


// =====================================================
// VARIABLES
// =====================================================

$errors = [];
$success = '';


// =====================================================
// FORM SUBMISSION
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    // =================================================
    // GET FORM DATA
    // =================================================

    $studentId = trim($_POST['student_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $fatherName = trim($_POST['father_name'] ?? '');
    $dateOfBirth = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $classId = trim($_POST['class_id'] ?? '');
    $sectionId = trim($_POST['section_id'] ?? '');
    $admissionDate = trim($_POST['admission_date'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $accountPassword = (string) ($_POST['account_password'] ?? '');


    // =================================================
    // VALIDATION
    // =================================================

    if ($studentId === '') {

        $errors[] = 'Student ID is required.';

    } elseif (strlen($studentId) > 50) {

        $errors[] = 'Student ID must not exceed 50 characters.';
    }


    if ($name === '') {

        $errors[] = 'Student name is required.';

    } elseif (strlen($name) > 100) {

        $errors[] = 'Student name must not exceed 100 characters.';
    }


    /*
     * Father name is optional because students
     * registering through the public registration
     * form do not provide it.
     */
    if (strlen($fatherName) > 100) {

        $errors[] = 'Father name must not exceed 100 characters.';
    }


    if (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $errors[] = 'Please enter a valid email address.';
    }


    // =================================================
    // LOGIN ACCOUNT (PASSWORD)
    // =================================================
    // Students created by an admin may not have a users
    // row yet. In that case the password is REQUIRED and
    // creates the account. Existing accounts may get an
    // optional password reset.
    // =================================================

    $hasAccount = !empty($student['user_id']);

    if ($accountPassword !== '') {

        if (strlen($accountPassword) < 8) {

            $errors[] =
                'Password must be at least 8 characters long.';

        } elseif (strlen($accountPassword) > 72) {

            $errors[] = 'Password must not exceed 72 characters.';
        }
    }

    if (!$hasAccount) {

        if ($accountPassword === '') {

            $errors[] =
                'This student has no login account yet. '
                . 'Set an initial password (minimum 8 characters) to create one.';
        }

        if ($email === '') {

            $errors[] =
                'A login account requires an email address.';
        }
    }


    // =================================================
    // LOGIN EMAIL MUST BE UNIQUE
    // =================================================

    if (
        empty($errors) &&
        $email !== '' &&
        filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $excludeUserId = $hasAccount
            ? (int) $student['user_id']
            : null;

        if (
            $studentObj->emailTakenByAnotherUser(
                $email,
                $excludeUserId
            )
        ) {

            $errors[] =
                'This email is already used by another login account.';
        }
    }


    // =================================================
    // PARENT PORTAL ACCOUNT
    // =================================================
    // parent_email links an existing parent account, or
    // creates a new one when parent_name and
    // parent_password are also provided. The unlink
    // checkbox removes the link (it wins over email).
    // =================================================

    $parentEmail = strtolower(
        trim((string) ($_POST['parent_email'] ?? ''))
    );
    $parentName = trim((string) ($_POST['parent_name'] ?? ''));
    $parentPassword = (string) ($_POST['parent_password'] ?? '');
    $parentUnlink = !empty($_POST['parent_unlink']);

    $parentAction = 'none';   // none | link | unlink | create
    $parentUserId = null;

    if ($parentUnlink) {

        if (!empty($student['parent_user_id'])) {
            $parentAction = 'unlink';
        }

    } elseif ($parentEmail !== '') {

        if (!filter_var($parentEmail, FILTER_VALIDATE_EMAIL)) {

            $errors[] =
                'Please enter a valid parent email address.';

        } else {

            $parentUser = $authObj->findUserByEmail($parentEmail);

            if ($parentUser === null) {

                // New parent account — name + password required.
                $parentCreateValid = true;

                if ($parentName === '') {

                    $errors[] =
                        'Enter the parent full name to create a new parent account.';

                    $parentCreateValid = false;
                }

                if ($parentPassword === '') {

                    $errors[] =
                        'Set an initial parent password (minimum 6 characters) to create a new parent account.';

                    $parentCreateValid = false;

                } elseif (strlen($parentPassword) < 6) {

                    $errors[] =
                        'The parent password must be at least 6 characters.';

                    $parentCreateValid = false;

                } elseif (strlen($parentPassword) > 72) {

                    $errors[] =
                        'The parent password must not exceed 72 characters.';

                    $parentCreateValid = false;
                }

                if ($parentCreateValid) {
                    $parentAction = 'create';
                }

            } elseif ($parentUser['role'] !== 'parent') {

                $errors[] =
                    'That email already belongs to an existing "'
                    . $parentUser['role']
                    . '" account. Use a dedicated parent account email.';

            } elseif (($parentUser['status'] ?? '') !== 'active') {

                $errors[] =
                    'The selected parent account is inactive.';

            } else {

                $parentAction = 'link';
                $parentUserId = (int) $parentUser['id'];
            }
        }
    }


    $allowedGenders = [
        'male',
        'female',
        'other'
    ];

    if (
        $gender !== '' &&
        !in_array($gender, $allowedGenders, true)
    ) {

        $errors[] = 'Invalid gender selected.';
    }


    $allowedStatuses = [
        'pending',
        'active',
        'inactive',
        'graduated',
        'left'
    ];

    if (!in_array($status, $allowedStatuses, true)) {

        $errors[] = 'Invalid status selected.';
    }


    // =================================================
    // APPROVAL LIFECYCLE
    // =================================================
    //
    // Registered -> Pending -> Admin Approval -> Active
    //
    // Editing a student profile must NEVER activate a
    // pending account: only the Approve action (view.php)
    // may perform pending -> active.
    //
    // An existing student can also not be moved back to
    // pending.
    // =================================================

    $currentStatus = (string) ($student['status'] ?? 'active');

    if ($currentStatus === 'pending' && $status !== 'pending') {

        $errors[] =
            'This student is awaiting approval. Use the Approve action to activate the account - editing cannot change its status.';

    } elseif ($currentStatus !== 'pending' && $status === 'pending') {

        $errors[] =
            'An existing student cannot be moved back to pending.';
    }


    // =================================================
    // CLASS VALIDATION
    // =================================================

    if ($classId !== '') {

        if (
            !ctype_digit($classId) ||
            (int)$classId <= 0
        ) {

            $errors[] = 'Invalid class selected.';
        }
    }


    // =================================================
    // SECTION VALIDATION
    // =================================================

    if ($sectionId !== '') {

        if (
            !ctype_digit($sectionId) ||
            (int)$sectionId <= 0
        ) {

            $errors[] = 'Invalid section selected.';
        }
    }


    // =================================================
    // CHECK CLASS + SECTION RELATION
    // =================================================

    if (
        empty($errors) &&
        $classId !== '' &&
        $sectionId !== ''
    ) {

        foreach ($sections as $section) {

            if (
                (int)$section['id'] === (int)$sectionId
            ) {

                /*
                 * If section has a class_id,
                 * make sure it belongs to selected class.
                 */
                if (
                    isset($section['class_id']) &&
                    $section['class_id'] !== null &&
                    $section['class_id'] !== '' &&
                    (int)$section['class_id'] !== (int)$classId
                ) {

                    $errors[] =
                        'Selected section does not belong to the selected class.';
                }

                break;
            }
        }
    }


    // =================================================
    // UPDATE
    // =================================================

    if (empty($errors)) {

        try {

            $data = [

                'student_id' => $studentId,

                'name' => $name,

                'father_name' =>
                    $fatherName !== ''
                        ? $fatherName
                        : null,

                'date_of_birth' =>
                    $dateOfBirth !== ''
                        ? $dateOfBirth
                        : null,

                'gender' =>
                    $gender !== ''
                        ? $gender
                        : null,

                'phone' =>
                    $phone !== ''
                        ? $phone
                        : null,

                'email' =>
                    $email !== ''
                        ? $email
                        : null,

                'address' =>
                    $address !== ''
                        ? $address
                        : null,

                'class_id' =>
                    $classId !== ''
                        ? (int)$classId
                        : null,

                'section_id' =>
                    $sectionId !== ''
                        ? (int)$sectionId
                        : null,

                'admission_date' =>
                    $admissionDate !== ''
                        ? $admissionDate
                        : null,

                'status' => $status,

                // Login account password (create or reset).
                'password' => $accountPassword !== ''
                    ? $accountPassword
                    : null
            ];


            $updated = $studentObj->updateStudent(
                $id,
                $data
            );


            if ($updated) {

                // =========================================
                // PARENT PORTAL LINKAGE
                // =========================================

                $parentNote = '';

                if ($parentAction === 'create') {

                    $parentResult = $authObj->createParentAccount(
                        $parentName,
                        $parentEmail,
                        $parentPassword
                    );

                    if ($parentResult['success'] === true) {

                        $parentUserId = (int) $parentResult['user_id'];
                        $parentAction = 'link';

                    } else {

                        $errors[] = $parentResult['message'];
                    }
                }

                if ($parentAction === 'link' && $parentUserId !== null) {

                    $studentObj->setParentLink($id, $parentUserId);
                    $parentNote = ' Parent login linked.';

                } elseif ($parentAction === 'unlink') {

                    $studentObj->setParentLink($id, null);
                    $parentNote = ' Parent login unlinked.';
                }

                if (empty($errors)) {

                    $success =
                        'Student updated successfully.' . $parentNote;
                }

                /*
                 * Reload updated student.
                 */
                $student =
                    $studentObj->getStudentById($id);

            } else {

                $errors[] =
                    'No changes were saved.';
            }


        } catch (Throwable $e) {

            if ($e instanceof PDOException && $e->getCode() === '23000') {

                $errors[] =
                    'A record with this Student ID or email address already exists.';

            } elseif ($e instanceof RuntimeException) {

                // Lifecycle rule (e.g. pending student must be approved).
                $errors[] = $e->getMessage();

            } else {

                error_log(
                    'Student Update Error: ' .
                    $e->getMessage()
                );

                $errors[] =
                    'Something went wrong while updating the student.';
            }
        }
    }


    // =================================================
    // KEEP FORM VALUES AFTER ERROR
    // =================================================

    if (!empty($errors)) {

        $student['student_id'] = $studentId;

        $student['name'] = $name;

        $student['father_name'] = $fatherName;

        $student['date_of_birth'] =
            $dateOfBirth !== ''
                ? $dateOfBirth
                : null;

        $student['gender'] = $gender;

        $student['phone'] = $phone;

        $student['email'] = $email;

        $student['address'] = $address;

        $student['class_id'] =
            $classId !== ''
                ? $classId
                : null;

        $student['section_id'] =
            $sectionId !== ''
                ? $sectionId
                : null;

        $student['admission_date'] =
            $admissionDate !== ''
                ? $admissionDate
                : null;

        // Keep the user's chosen status, but never repaint a
        // pending student as something else after a failed save
        // (pending can only change through the Approve action).
        if (($student['status'] ?? '') !== 'pending') {
            $student['status'] = $status;
        }
    }
}


// =====================================================
// PARENT ACCOUNT DATA (for the linkage section)
// =====================================================
// Loaded after POST handling so the "currently linked"
// line always reflects the reloaded student row.
// =====================================================

try {
    $parentAccounts = $pdo->query(
        "SELECT id, name, email
         FROM users
         WHERE role = 'parent'
         ORDER BY name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $eParents) {
    error_log('Parent accounts loading error: ' . $eParents->getMessage());
    $parentAccounts = [];
}

$currentParent = null;

if (!empty($student['parent_user_id'])) {
    try {
        $currentParentStmt = $pdo->prepare(
            'SELECT id, name, email, status
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $currentParentStmt->execute([
            ':id' => (int) $student['parent_user_id'],
        ]);
        $currentParent = $currentParentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $eParent) {
        error_log('Current parent loading error: ' . $eParent->getMessage());
        $currentParent = null;
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

    <title>Edit Student - StudentHub</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Inter,
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f9;

            color: #1f2937;
        }


        .container {

            width: 95%;

            max-width: 1100px;

            margin: 40px auto;
        }


        .header {

            background: #ffffff;

            padding: 25px;

            border-radius: 14px;

            margin-bottom: 20px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            box-shadow:
                0 4px 15px rgba(0,0,0,0.06);
        }


        .header h1 {

            margin: 0 0 6px;

            font-size: 26px;
        }


        .header p {

            margin: 0;

            color: #6b7280;
        }


        .buttons {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;
        }


        .btn {

            display: inline-block;

            padding: 10px 16px;

            border-radius: 8px;

            text-decoration: none;

            border: none;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;
        }


        .btn-back {

            background: #e5e7eb;

            color: #111827;
        }


        .btn-save {

            background: #2563eb;

            color: white;
        }


        .btn-save:hover {

            background: #1d4ed8;
        }


        .card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 20px;

            box-shadow:
                0 4px 15px rgba(0,0,0,0.06);
        }


        .card h2 {

            margin-top: 0;

            margin-bottom: 20px;

            font-size: 19px;

            border-bottom:
                1px solid #e5e7eb;

            padding-bottom: 12px;
        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;
        }


        .form-group {

            display: flex;

            flex-direction: column;
        }


        .form-group.full {

            grid-column: 1 / -1;
        }


        label {

            font-weight: 600;

            margin-bottom: 7px;

            font-size: 14px;
        }


        input,
        select,
        textarea {

            width: 100%;

            padding: 11px 12px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            font-size: 14px;

            outline: none;

            background: white;
        }


        input:focus,
        select:focus,
        textarea:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,0.12);
        }


        textarea {

            min-height: 100px;

            resize: vertical;
        }


        .required {

            color: #dc2626;
        }


        .optional {

            color: #9ca3af;

            font-size: 12px;

            font-weight: 400;
        }


        .hint {

            margin-top: 6px;

            color: #6b7280;

            font-size: 12px;
        }


        .alert {

            padding: 14px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;
        }


        .alert-success {

            background: #dcfce7;

            color: #166534;

            border:
                1px solid #bbf7d0;
        }


        .alert ul {

            margin: 0;

            padding-left: 20px;
        }


        .form-actions {

            display: flex;

            justify-content: flex-end;

            gap: 10px;

            margin-top: 10px;
        }


        @media (max-width: 700px) {

            .container {

                width: 92%;

                margin: 20px auto;
            }


            .header {

                flex-direction: column;

                align-items: flex-start;
            }


            .form-grid {

                grid-template-columns: 1fr;
            }


            .form-group.full {

                grid-column: auto;
            }


            .form-actions {

                flex-direction: column;
            }


            .form-actions .btn {

                width: 100%;

                text-align: center;
            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>Edit Student</h1>

            <p>
                Update student profile and academic assignment.
            </p>

        </div>


        <div class="buttons">

            <a
                href="view.php?id=<?= (int)$id ?>"
                class="btn btn-back"
            >
                ← View Student
            </a>


            <a
                href="index.php"
                class="btn btn-back"
            >
                ← Students
            </a>

        </div>

    </div>


    <!-- SUCCESS -->

    <?php if ($success !== ''): ?>

        <div class="alert alert-success">

            <?= e($success) ?>

        </div>

    <?php endif; ?>


    <!-- ERRORS -->

    <?php if (!empty($errors)): ?>

        <div class="alert alert-error">

            <ul>

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= e($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form method="POST">
        <?= csrf_field() ?>


        <!-- PERSONAL INFORMATION -->

        <div class="card">

            <h2>
                Personal Information
            </h2>


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Student ID
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="student_id"
                        value="<?= e($student['student_id']) ?>"
                        maxlength="50"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Student Name
                        <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="name"
                        value="<?= e($student['name']) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>

                        Father Name

                        <span class="optional">
                            (Optional)
                        </span>

                    </label>

                    <input
                        type="text"
                        name="father_name"
                        value="<?= e($student['father_name']) ?>"
                        maxlength="100"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Date of Birth
                    </label>

                    <input
                        type="date"
                        name="date_of_birth"
                        value="<?= e($student['date_of_birth']) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Gender
                    </label>

                    <select name="gender">

                        <option value="">
                            Select Gender
                        </option>

                        <option
                            value="male"
                            <?= ($student['gender'] ?? '') === 'male'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Male
                        </option>

                        <option
                            value="female"
                            <?= ($student['gender'] ?? '') === 'female'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Female
                        </option>

                        <option
                            value="other"
                            <?= ($student['gender'] ?? '') === 'other'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Other
                        </option>

                    </select>

                </div>

            </div>

        </div>


        <!-- CONTACT -->

        <div class="card">

            <h2>
                Contact Information
            </h2>


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Phone
                    </label>

                    <input
                        type="text"
                        name="phone"
                        value="<?= e($student['phone']) ?>"
                        maxlength="30"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        value="<?= e($student['email']) ?>"
                        maxlength="150"
                    >

                </div>


                <!-- LOGIN ACCOUNT PASSWORD -->

                <div class="form-group full">

                    <label>
                        <?= empty($student['user_id'])
                            ? 'Initial Password (creates the login account)'
                            : 'Login Password (optional reset)' ?>
                    </label>

                    <input
                        type="password"
                        name="account_password"
                        minlength="8"
                        maxlength="72"
                        <?= empty($student['user_id'])
                            ? 'required'
                            : '' ?>
                        placeholder="<?= empty($student['user_id'])
                            ? 'Minimum 8 characters - creates the student login account'
                            : 'Leave blank to keep the current password' ?>"
                        autocomplete="new-password"
                    >

                    <small style="color:#6b7280;">
                        <?php if (empty($student['user_id'])): ?>

                            This student has no login account yet.
                            This password creates one.

                        <?php else: ?>

                            Fill this only to reset the student's
                            password (minimum 8 characters).

                        <?php endif; ?>
                    </small>

                </div>


                <div class="form-group full">

                    <label>
                        Address
                    </label>

                    <textarea
                        name="address"
                    ><?= e($student['address']) ?></textarea>

                </div>

            </div>

        </div>


        <!-- PARENT PORTAL ACCOUNT -->

        <div class="card">

            <h2>
                Parent Portal
            </h2>

            <p style="color:#6b7280; font-size:.9rem; margin-bottom:14px;">
                Link a parent login so the parent can follow this
                child's attendance, results and fees from the parent
                dashboard. A parent account can be linked to several
                children.
            </p>


            <div class="form-grid">

                <!-- CURRENT LINK -->

                <div class="form-group full">

                    <label>
                        Currently linked parent
                    </label>

                    <div style="padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#f9fafb; font-size:.92rem;">
                        <?php if ($currentParent !== null): ?>

                            <strong><?= e($currentParent['name']) ?></strong>
                            &lt;<?= e($currentParent['email']) ?>&gt;

                            <?php if (($currentParent['status'] ?? '') !== 'active'): ?>
                                <span style="color:#b91c1c;">(inactive)</span>
                            <?php endif; ?>

                        <?php else: ?>

                            Not linked to a parent account yet.

                        <?php endif; ?>
                    </div>

                </div>


                <!-- PARENT EMAIL -->

                <div class="form-group full">

                    <label>
                        Parent email
                    </label>

                    <input
                        type="email"
                        name="parent_email"
                        list="parentAccountList"
                        maxlength="150"
                        value="<?= e(
                            empty($errors)
                                ? (string) ($currentParent['email'] ?? '')
                                : (string) ($_POST['parent_email'] ?? '')
                        ) ?>"
                        placeholder="Pick an existing parent account or type a new email"
                    >

                    <datalist id="parentAccountList">
                        <?php foreach ($parentAccounts as $parentAccount): ?>
                            <option value="<?= e($parentAccount['email']) ?>">
                                <?= e($parentAccount['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>

                    <small style="color:#6b7280;">
                        Leave blank to keep the current link unchanged.
                    </small>

                </div>


                <!-- PARENT NAME (new account) -->

                <div class="form-group">

                    <label>
                        Parent full name (new accounts only)
                    </label>

                    <input
                        type="text"
                        name="parent_name"
                        maxlength="100"
                        value="<?= e(
                            empty($errors)
                                ? ''
                                : (string) ($_POST['parent_name'] ?? '')
                        ) ?>"
                        placeholder="e.g. Rahim Uddin"
                    >

                </div>


                <!-- PARENT PASSWORD (new account) -->

                <div class="form-group">

                    <label>
                        Initial parent password (new accounts only)
                    </label>

                    <input
                        type="password"
                        name="parent_password"
                        minlength="6"
                        maxlength="72"
                        autocomplete="new-password"
                        placeholder="Minimum 6 characters"
                    >

                    <small style="color:#6b7280;">
                        Used only when the typed email has no account yet.
                    </small>

                </div>


                <!-- UNLINK -->

                <div class="form-group full">

                    <label style="display:flex; gap:8px; align-items:center; font-weight:600;">
                        <input
                            type="checkbox"
                            name="parent_unlink"
                            value="1"
                        >
                        Remove the parent link (keeps the parent account)
                    </label>

                </div>

            </div>

        </div>


        <!-- CLASS INFORMATION -->

        <div class="card">

            <h2>
                Academic Assignment
            </h2>


            <div class="form-grid">


                <!-- CLASS -->

                <div class="form-group">

                    <label>
                        Class
                    </label>

                    <select
                        name="class_id"
                        id="class_id"
                    >

                        <option value="">
                            Select Class
                        </option>


                        <?php foreach ($classes as $class): ?>

                            <option
                                value="<?= (int)$class['id'] ?>"
                                <?= (string)($student['class_id'] ?? '')
                                    === (string)$class['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($class['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="hint">
                        Select the student's class.
                    </div>

                </div>


                <!-- SECTION -->

                <div class="form-group">

                    <label>
                        Section
                    </label>

                    <select
                        name="section_id"
                        id="section_id"
                    >

                        <option value="">
                            Select Section
                        </option>


                        <?php foreach ($sections as $section): ?>

                            <option
                                value="<?= (int)$section['id'] ?>"
                                data-class-id="<?= e(
                                    isset($section['class_id'])
                                        ? (string)$section['class_id']
                                        : ''
                                ) ?>"
                                <?= (string)($student['section_id'] ?? '')
                                    === (string)$section['id']
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= e($section['name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div class="hint">
                        Select the section for the selected class.
                    </div>

                </div>

            </div>

        </div>


        <!-- ADMISSION -->

        <div class="card">

            <h2>
                Admission Information
            </h2>


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Admission Date
                    </label>

                    <input
                        type="date"
                        name="admission_date"
                        value="<?= e($student['admission_date']) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <?php if (($student['status'] ?? '') === 'pending'): ?>

                            <!--
                                Pending students can only be approved
                                through the Approve action on the
                                student view page.
                            -->

                            <option
                                value="pending"
                                selected
                            >
                                Pending approval
                            </option>

                        <?php else: ?>

                        <option
                            value="active"
                            <?= $student['status'] === 'active'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?= $student['status'] === 'inactive'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Inactive
                        </option>

                        <option
                            value="graduated"
                            <?= $student['status'] === 'graduated'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Graduated
                        </option>

                        <option
                            value="left"
                            <?= $student['status'] === 'left'
                                ? 'selected'
                                : ''
                            ?>
                        >
                            Left
                        </option>

                        <?php endif; ?>

                    </select>

                </div>

            </div>

        </div>


        <!-- ACTIONS -->

        <div class="form-actions">

            <a
                href="view.php?id=<?= (int)$id ?>"
                class="btn btn-back"
            >
                Cancel
            </a>


            <button
                type="submit"
                class="btn btn-save"
            >
                Update Student
            </button>

        </div>


    </form>


</div>


<script>

/*
 * Filter sections according to selected class.
 *
 * If sections table has class_id, only sections
 * belonging to that class are shown.
 *
 * If class_id is empty, all sections are shown.
 */

const classSelect =
    document.getElementById('class_id');

const sectionSelect =
    document.getElementById('section_id');


function filterSections() {

    const selectedClass =
        classSelect.value;

    const currentSection =
        sectionSelect.value;


    Array.from(
        sectionSelect.options
    ).forEach(function(option, index) {

        if (index === 0) {
            return;
        }


        const sectionClass =
            option.getAttribute('data-class-id');


        /*
         * If section has no class_id,
         * keep it visible.
         */

        if (
            selectedClass === '' ||
            sectionClass === '' ||
            sectionClass === null
        ) {

            option.hidden = false;

        } else {

            option.hidden =
                sectionClass !== selectedClass;
        }

    });


    /*
     * If currently selected section does not
     * belong to selected class, clear it.
     */

    const selectedOption =
        sectionSelect.options[
            sectionSelect.selectedIndex
        ];


    if (
        selectedOption &&
        selectedOption.hidden
    ) {

        sectionSelect.value = '';
    }
}


classSelect.addEventListener(
    'change',
    filterSections
);


/*
 * Run once when page loads.
 */
filterSections();

</script>


</body>

</html>