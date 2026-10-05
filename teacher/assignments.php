<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireTeacher();

require_once __DIR__ . '/../classes/Assignment.php';
require_once __DIR__ . '/../classes/TeacherClass.php';

$pdo = db();
$database = null;


// =====================================================
// OBJECTS
// =====================================================

$assignmentObject = new Assignment($pdo);
$teacherClassObject = new TeacherClass($pdo);


// =====================================================
// HELPER
// =====================================================

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


// =====================================================
// GET LOGGED-IN TEACHER
// =====================================================

$userId = (int) $_SESSION['user_id'];

$teacherStmt = $pdo->prepare("
    SELECT
        id,
        teacher_id,
        name
    FROM teachers
    WHERE user_id = :user_id
      AND status = 'active'
    LIMIT 1
");

$teacherStmt->execute([
    ':user_id' => $userId
]);

$teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die('Teacher profile not found.');
}

$teacherId = (int) $teacher['id'];


// =====================================================
// GET TEACHER CLASS ASSIGNMENTS
// =====================================================

$teacherClassAssignments =
    $teacherClassObject->getTeacherClasses($teacherId);


// =====================================================
// BUILD AUTHORIZED DATA
// =====================================================
//
// Structure:
//
// Subject
//   └── Class
//        ├── Whole Class
//        └── Specific Sections
//
// teacher_classes is the ONLY authorization source.
// =====================================================

$authorizedData = [];

foreach ($teacherClassAssignments as $assignment) {

    $assignedSubjectId =
        (int) ($assignment['subject_id'] ?? 0);

    $assignedClassId =
        (int) ($assignment['class_id'] ?? 0);

    if (
        $assignedSubjectId <= 0 ||
        $assignedClassId <= 0
    ) {
        continue;
    }

    $assignedSectionId =
        $assignment['section_id'] !== null
            ? (int) $assignment['section_id']
            : null;


    // =================================================
    // SUBJECT
    // =================================================

    if (!isset($authorizedData[$assignedSubjectId])) {

        $authorizedData[$assignedSubjectId] = [

            'subject_id' =>
                $assignedSubjectId,

            'subject_name' =>
                (string) (
                    $assignment['subject_name']
                    ?? 'Unknown Subject'
                ),

            'subject_code' =>
                (string) (
                    $assignment['subject_code']
                    ?? ''
                ),

            'classes' => []

        ];
    }


    // =================================================
    // CLASS
    // =================================================

    if (
        !isset(
            $authorizedData[
                $assignedSubjectId
            ]['classes'][
                $assignedClassId
            ]
        )
    ) {

        $authorizedData[
            $assignedSubjectId
        ]['classes'][
            $assignedClassId
        ] = [

            'class_id' =>
                $assignedClassId,

            'class_name' =>
                (string) (
                    $assignment['class_name']
                    ?? 'Unknown Class'
                ),

            'whole_class' =>
                false,

            'sections' => []

        ];
    }


    // =================================================
    // WHOLE CLASS
    // =================================================

    if ($assignedSectionId === null) {

        $authorizedData[
            $assignedSubjectId
        ]['classes'][
            $assignedClassId
        ]['whole_class'] = true;

        continue;
    }


    // =================================================
    // SPECIFIC SECTION
    // =================================================

    $sectionExists = false;

    foreach (
        $authorizedData[
            $assignedSubjectId
        ]['classes'][
            $assignedClassId
        ]['sections']
        as $existingSection
    ) {

        if (
            (int) $existingSection['section_id'] ===
            $assignedSectionId
        ) {

            $sectionExists = true;

            break;
        }
    }


    if (!$sectionExists) {

        $authorizedData[
            $assignedSubjectId
        ]['classes'][
            $assignedClassId
        ]['sections'][] = [

            'section_id' =>
                $assignedSectionId,

            'section_name' =>
                (string) (
                    $assignment['section_name']
                    ?? 'Section'
                )

        ];
    }
}


// =====================================================
// RE-INDEX FOR JAVASCRIPT
// =====================================================

$authorizedData = array_values(
    $authorizedData
);

foreach (
    $authorizedData
    as &$subjectData
) {

    $subjectData['classes'] =
        array_values(
            $subjectData['classes']
        );
}

unset($subjectData);


// =====================================================
// GET TEACHER ASSIGNMENTS
// =====================================================

$teacherAssignments =
    $assignmentObject->getTeacherAssignments(
        $teacherId
    );


// =====================================================
// CSRF TOKEN
// =====================================================

if (
    empty($_SESSION['csrf_token'])
) {

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

$csrfToken =
    $_SESSION['csrf_token'];


// =====================================================
// FORM VARIABLES
// =====================================================

$errors = [];

$title = '';
$description = '';

$subjectId = '';
$classId = '';
$sectionId = '';

$dueDate = '';


// =====================================================
// CREATE ASSIGNMENT
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // =================================================
    // CSRF
    // =================================================

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $csrfToken,
            (string) $_POST['csrf_token']
        )
    ) {

        $errors[] =
            'Invalid security token. Please refresh the page and try again.';
    }


    // =================================================
    // FORM DATA
    // =================================================

    $title = trim(
        (string) (
            $_POST['title']
            ?? ''
        )
    );

    $description = trim(
        (string) (
            $_POST['description']
            ?? ''
        )
    );

    $subjectId = trim(
        (string) (
            $_POST['subject_id']
            ?? ''
        )
    );

    $classId = trim(
        (string) (
            $_POST['class_id']
            ?? ''
        )
    );

    $sectionId = trim(
        (string) (
            $_POST['section_id']
            ?? ''
        )
    );

    $dueDate = trim(
        (string) (
            $_POST['due_date']
            ?? ''
        )
    );


    // =================================================
    // TITLE VALIDATION
    // =================================================

    if ($title === '') {

        $errors[] =
            'Assignment title is required.';

    } elseif (mb_strlen($title) > 200) {

        $errors[] =
            'Assignment title cannot exceed 200 characters.';
    }


    // =================================================
    // DESCRIPTION VALIDATION
    // =================================================

    if (
        mb_strlen($description) > 10000
    ) {

        $errors[] =
            'Assignment instructions cannot exceed 10,000 characters.';
    }


    // =================================================
    // SUBJECT VALIDATION
    // =================================================

    if (
        $subjectId === '' ||
        !ctype_digit($subjectId) ||
        (int) $subjectId <= 0
    ) {

        $errors[] =
            'Please select a valid subject.';
    }


    // =================================================
    // CLASS VALIDATION
    // =================================================

    if (
        $classId === '' ||
        !ctype_digit($classId) ||
        (int) $classId <= 0
    ) {

        $errors[] =
            'Please select a valid class.';
    }


    // =================================================
    // SECTION VALIDATION
    // =================================================

    $sectionIdValue = null;

    if ($sectionId !== '') {

        if (
            !ctype_digit($sectionId) ||
            (int) $sectionId <= 0
        ) {

            $errors[] =
                'Please select a valid section.';

        } else {

            $sectionIdValue =
                (int) $sectionId;
        }
    }


    // =================================================
    // DATE VALIDATION
    // =================================================

    if ($dueDate === '') {

        $errors[] =
            'Due date is required.';

    } else {

        $dateObject =
            DateTime::createFromFormat(
                'Y-m-d',
                $dueDate
            );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $dueDate
        ) {

            $errors[] =
                'Please enter a valid due date.';
        }
    }


    // =================================================
    // AUTHORIZATION
    // =================================================

    if (empty($errors)) {

        if ($sectionIdValue === null) {

            // =============================================
            // WHOLE CLASS
            // =============================================

            $verifyStmt = $pdo->prepare("
                SELECT id
                FROM teacher_classes
                WHERE teacher_id = :teacher_id
                  AND subject_id = :subject_id
                  AND class_id = :class_id
                  AND section_id IS NULL
                LIMIT 1
            ");

            $verifyStmt->execute([

                ':teacher_id' =>
                    $teacherId,

                ':subject_id' =>
                    (int) $subjectId,

                ':class_id' =>
                    (int) $classId

            ]);

            if (!$verifyStmt->fetch()) {

                $errors[] =
                    'You are not assigned to this subject and whole class.';
            }

        } else {

            // =============================================
            // SPECIFIC SECTION
            // =============================================
            //
            // A Whole Class assignment also authorizes
            // assignment creation for its sections.
            //

            $verifyStmt = $pdo->prepare("
                SELECT id
                FROM teacher_classes
                WHERE teacher_id = :teacher_id
                  AND subject_id = :subject_id
                  AND class_id = :class_id
                  AND (
                        section_id = :section_id
                        OR section_id IS NULL
                  )
                LIMIT 1
            ");

            $verifyStmt->execute([

                ':teacher_id' =>
                    $teacherId,

                ':subject_id' =>
                    (int) $subjectId,

                ':class_id' =>
                    (int) $classId,

                ':section_id' =>
                    $sectionIdValue

            ]);

            if (!$verifyStmt->fetch()) {

                $errors[] =
                    'You are not assigned to this subject, class and section.';
            }
        }
    }


    // =================================================
    // DUPLICATE CHECK
    // =================================================

    if (empty($errors)) {

        $duplicate =
            $assignmentObject->assignmentExists(
                $teacherId,
                (int) $subjectId,
                (int) $classId,
                $sectionIdValue,
                $title
            );

        if ($duplicate) {

            if ($sectionIdValue === null) {

                $errors[] =
                    'An assignment with the same title already exists for this subject and whole class.';

            } else {

                $errors[] =
                    'An assignment with the same title already exists for this subject, class and section.';
            }
        }
    }


    // =================================================
    // SAVE
    // =================================================

    if (empty($errors)) {

        try {

            $created =
                $assignmentObject->createAssignment([

                    'teacher_id' =>
                        $teacherId,

                    'subject_id' =>
                        (int) $subjectId,

                    'class_id' =>
                        (int) $classId,

                    'section_id' =>
                        $sectionIdValue,

                    'title' =>
                        $title,

                    'description' =>
                        $description !== ''
                            ? $description
                            : null,

                    'due_date' =>
                        $dueDate,

                    'status' =>
                        'active'

                ]);


            if ($created) {

                header(
                    'Location: assignments.php?success=1'
                );

                exit;
            }


            $errors[] =
                'Assignment could not be created. Please try again.';

        } catch (PDOException $e) {

            $errors[] =
                'Database error. Assignment could not be created.';
        }
    }
}


// =====================================================
// SUCCESS
// =====================================================

$success =
    isset($_GET['success']) &&
    $_GET['success'] === '1';


// =====================================================
// JSON
// =====================================================

$authorizedJson =
    json_encode(
        $authorizedData,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    );

if ($authorizedJson === false) {

    $authorizedJson = '[]';
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
    Assignments | Teacher Portal
</title>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Inter,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;
    background:
        linear-gradient(
            135deg,
            #f8fafc 0%,
            #eef2ff 100%
        );
    color: #172033;
}

.page-wrapper {
    min-height: 100vh;
    padding: 35px 20px 60px;
}

.page-container {
    max-width: 1100px;
    margin: auto;
}

.top-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    gap: 15px;
}

.brand-area {
    display: flex;
    align-items: center;
    gap: 13px;
}

.brand-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );
    color: white;
    font-size: 22px;
    box-shadow:
        0 8px 20px
        rgba(37,99,235,.25);
}

.brand-text h1 {
    margin: 0;
    font-size: 21px;
    font-weight: 750;
}

.brand-text p {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 13px;
}

.dashboard-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 17px;
    border-radius: 9px;
    text-decoration: none;
    background: white;
    color: #334155;
    border: 1px solid #e2e8f0;
    font-size: 14px;
    font-weight: 600;
    transition: .2s ease;
}

.dashboard-btn:hover {
    border-color: #cbd5e1;
    transform: translateY(-1px);
    box-shadow:
        0 5px 15px
        rgba(15,23,42,.08);
}

.main-card,
.list-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    overflow: hidden;
    box-shadow:
        0 15px 45px
        rgba(15,23,42,.08);
}

.main-card {
    margin-bottom: 28px;
}

.card-header {
    padding: 30px 35px;
    border-bottom: 1px solid #edf0f4;
    background:
        linear-gradient(
            135deg,
            #ffffff,
            #f8faff
        );
}

.header-content {
    display: flex;
    align-items: flex-start;
    gap: 17px;
}

.header-icon {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 23px;
}

.card-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 750;
}

.card-header p {
    margin: 7px 0 0;
    color: #64748b;
    font-size: 14px;
    line-height: 1.5;
}

.teacher-box {
    margin: 25px 35px 0;
    padding: 17px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    border: 1px solid #dbeafe;
    background: #f8fbff;
    border-radius: 11px;
}

.teacher-left {
    display: flex;
    align-items: center;
    gap: 13px;
}

.teacher-avatar {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #2563eb;
    color: white;
    font-weight: 700;
    font-size: 15px;
}

.teacher-details strong {
    display: block;
    font-size: 14px;
}

.teacher-details span {
    display: block;
    margin-top: 3px;
    font-size: 12px;
    color: #64748b;
}

.teacher-badge {
    padding: 6px 11px;
    border-radius: 20px;
    background: #dcfce7;
    color: #15803d;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.alert {
    margin: 25px 35px 0;
    padding: 15px 17px;
    border-radius: 10px;
    font-size: 13px;
}

.alert-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

.alert-success-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.success-icon {
    width: 27px;
    height: 27px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #22c55e;
    color: white;
    font-weight: bold;
}

.alert-error {
    background: #fff7f7;
    border: 1px solid #fecaca;
    color: #991b1b;
}

.alert-error strong {
    display: block;
    margin-bottom: 7px;
}

.alert-error ul {
    margin: 0;
    padding-left: 20px;
}

.alert-error li {
    margin-bottom: 4px;
}

.form-area {
    padding: 32px 35px 35px;
}

.section-label {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 21px;
}

.section-label-icon {
    width: 31px;
    height: 31px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #f1f5f9;
    font-size: 14px;
}

.section-label h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
}

.section-label span {
    color: #94a3b8;
    font-size: 12px;
}

.form-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 22px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.full-width {
    grid-column: 1 / -1;
}

label {
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 650;
    color: #334155;
}

.required {
    color: #ef4444;
    margin-left: 2px;
}

.optional {
    color: #94a3b8;
    font-size: 11px;
    font-weight: 500;
    margin-left: 4px;
}

.input-wrapper {
    position: relative;
}

.input-icon {
    position: absolute;
    left: 13px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 15px;
    pointer-events: none;
    z-index: 2;
}

input,
select,
textarea {
    width: 100%;
    border: 1px solid #dbe1e8;
    background: #fff;
    border-radius: 9px;
    color: #172033;
    font-family: inherit;
    font-size: 14px;
    outline: none;
    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}

input,
select {
    height: 46px;
    padding: 0 13px;
}

input.has-icon,
select.has-icon {
    padding-left: 40px;
}

textarea {
    min-height: 135px;
    padding: 13px;
    resize: vertical;
    line-height: 1.6;
}

input::placeholder,
textarea::placeholder {
    color: #a0a9b8;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #3b82f6;
    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.10);
}

select:disabled {
    background: #f8fafc;
    color: #94a3b8;
    cursor: not-allowed;
}

.help-text {
    margin-top: 6px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.4;
}

.section-status {
    margin-top: 7px;
    padding: 8px 10px;
    border-radius: 7px;
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    line-height: 1.4;
}

.form-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding-top: 25px;
    border-top: 1px solid #edf0f4;
}

.security-note {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
    font-size: 11px;
}

.security-icon {
    color: #22c55e;
    font-size: 14px;
}

.actions {
    display: flex;
    gap: 10px;
}

.btn {
    min-height: 44px;
    padding: 0 19px;
    border-radius: 9px;
    border: none;
    font-family: inherit;
    font-size: 13px;
    font-weight: 650;
    text-decoration: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: .2s ease;
}

.btn-cancel {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-cancel:hover {
    background: #e2e8f0;
}

.btn-primary {
    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );
    color: white;
    box-shadow:
        0 5px 14px
        rgba(37,99,235,.22);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow:
        0 8px 20px
        rgba(37,99,235,.28);
}

.list-header {
    padding: 26px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid #edf0f4;
    background:
        linear-gradient(
            135deg,
            #ffffff,
            #f8faff
        );
}

.list-title-area {
    display: flex;
    align-items: center;
    gap: 13px;
}

.list-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 18px;
}

.list-title-area h2 {
    margin: 0;
    font-size: 19px;
    font-weight: 750;
}

.list-title-area p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 12px;
}

.assignment-count {
    padding: 7px 12px;
    border-radius: 20px;
    background: #f1f5f9;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.assignment-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

.assignment-table th {
    padding: 14px 18px;
    text-align: left;
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid #e2e8f0;
}

.assignment-table td {
    padding: 17px 18px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}

.assignment-table tbody tr:hover {
    background: #f8fbff;
}

.assignment-number {
    width: 50px;
    color: #94a3b8;
    font-weight: 600;
}

.assignment-title {
    color: #172033;
    font-weight: 700;
    max-width: 220px;
}

.assignment-subtitle {
    margin-top: 4px;
    color: #94a3b8;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}

.subject-name,
.class-name,
.section-name {
    color: #475569;
    font-weight: 600;
}

.due-date {
    color: #475569;
    font-weight: 600;
    white-space: nowrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 750;
    text-transform: uppercase;
    letter-spacing: .3px;
}

.status-active {
    background: #dcfce7;
    color: #15803d;
}

.status-inactive {
    background: #fee2e2;
    color: #b91c1c;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}

.empty-state {
    padding: 50px 30px;
    text-align: center;
    color: #64748b;
}

.empty-state-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: #f1f5f9;
    font-size: 25px;
}

.empty-state strong {
    display: block;
    color: #334155;
    margin-bottom: 6px;
    font-size: 14px;
}

.empty-state span {
    font-size: 12px;
    color: #94a3b8;
}

.page-note {
    text-align: center;
    margin-top: 20px;
    color: #94a3b8;
    font-size: 11px;
}

@media (max-width: 760px) {

    .page-wrapper {
        padding: 20px 12px 40px;
    }

    .top-navigation {
        align-items: flex-start;
    }

    .brand-text h1 {
        font-size: 18px;
    }

    .brand-text p {
        display: none;
    }

    .dashboard-btn {
        padding: 9px 12px;
    }

    .card-header {
        padding: 24px 20px;
    }

    .teacher-box {
        margin: 20px 20px 0;
    }

    .form-area {
        padding: 25px 20px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full-width {
        grid-column: auto;
    }

    .alert {
        margin-left: 20px;
        margin-right: 20px;
    }

    .form-footer {
        align-items: stretch;
        flex-direction: column;
        gap: 18px;
    }

    .security-note {
        justify-content: center;
    }

    .actions {
        width: 100%;
    }

    .actions .btn {
        flex: 1;
    }

    .list-header {
        align-items: flex-start;
        flex-direction: column;
        padding: 22px 20px;
    }

    .assignment-count {
        margin-left: 55px;
    }
}

@media (max-width: 480px) {

    .teacher-box {
        align-items: flex-start;
        flex-direction: column;
    }

    .teacher-badge {
        margin-left: 55px;
    }

    .actions {
        flex-direction: column;
    }

    .actions .btn {
        width: 100%;
    }

    .header-content {
        gap: 12px;
    }

    .header-icon {
        width: 44px;
        height: 44px;
    }

    .card-header h2 {
        font-size: 20px;
    }
}

</style>

</head>


<body>

<div class="page-wrapper">

<div class="page-container">


<!-- =====================================================
     TOP NAVIGATION
===================================================== -->

<div class="top-navigation">

    <div class="brand-area">

        <div class="brand-icon">
            🏫
        </div>

        <div class="brand-text">

            <h1>
                Teacher Portal
            </h1>

            <p>
                Student Management System
            </p>

        </div>

    </div>


    <a
        href="dashboard.php"
        class="dashboard-btn"
    >
        ← Dashboard
    </a>

</div>


<!-- =====================================================
     CREATE ASSIGNMENT
===================================================== -->

<div class="main-card">


<div class="card-header">

    <div class="header-content">

        <div class="header-icon">
            📝
        </div>

        <div>

            <h2>
                Create New Assignment
            </h2>

            <p>
                Create and publish an assignment for
                one of your assigned classes.
            </p>

        </div>

    </div>

</div>


<!-- =====================================================
     TEACHER INFO
===================================================== -->

<div class="teacher-box">

    <div class="teacher-left">

        <div class="teacher-avatar">

            <?= e(
                strtoupper(
                    mb_substr(
                        (string) $teacher['name'],
                        0,
                        1
                    )
                )
            ) ?>

        </div>


        <div class="teacher-details">

            <strong>
                <?= e(
                    (string) $teacher['name']
                ) ?>
            </strong>

            <span>
                Teacher ID:
                <?= e(
                    (string) $teacher['teacher_id']
                ) ?>
            </span>

        </div>

    </div>


    <div class="teacher-badge">
        ● Active Teacher
    </div>

</div>


<!-- =====================================================
     SUCCESS
===================================================== -->

<?php if ($success): ?>

<div class="alert alert-success">

    <div class="alert-success-content">

        <div class="success-icon">
            ✓
        </div>

        <div>

            <strong>
                Assignment created successfully.
            </strong>

            <div>
                The assignment has been saved
                and is now active.
            </div>

        </div>

    </div>

</div>

<?php endif; ?>


<!-- =====================================================
     ERRORS
===================================================== -->

<?php if (!empty($errors)): ?>

<div class="alert alert-error">

    <strong>
        Please fix the following:
    </strong>

    <ul>

        <?php foreach ($errors as $error): ?>

            <li>
                <?= e($error) ?>
            </li>

        <?php endforeach; ?>

    </ul>

</div>

<?php endif; ?>


<!-- =====================================================
     FORM
===================================================== -->

<div class="form-area">


<div class="section-label">

    <div class="section-label-icon">
        📋
    </div>

    <div>

        <h3>
            Assignment Information
        </h3>

        <span>
            Enter the details of the assignment
        </span>

    </div>

</div>


<form
    method="POST"
    action="assignments.php"
    id="assignmentForm"
    novalidate
>


<input
    type="hidden"
    name="csrf_token"
    value="<?= e($csrfToken) ?>"
>


<div class="form-grid">


<!-- =====================================================
     TITLE
===================================================== -->

<div class="form-group full-width">

    <label for="title">

        Assignment Title

        <span class="required">
            *
        </span>

    </label>


    <div class="input-wrapper">

        <span class="input-icon">
            ✏️
        </span>

        <input
            type="text"
            id="title"
            name="title"
            class="has-icon"
            maxlength="200"
            value="<?= e($title) ?>"
            placeholder="e.g. PHP OOP CRUD Project"
            required
        >

    </div>


    <div class="help-text">
        Use a clear and meaningful assignment title.
    </div>

</div>


<!-- =====================================================
     SUBJECT
===================================================== -->

<div class="form-group">

    <label for="subject_id">

        Subject

        <span class="required">
            *
        </span>

    </label>


    <div class="input-wrapper">

        <span class="input-icon">
            📚
        </span>

        <select
            id="subject_id"
            name="subject_id"
            class="has-icon"
            required
        >

            <option value="">
                Select Subject
            </option>


            <?php foreach (
                $authorizedData
                as $subject
            ): ?>

                <option
                    value="<?= (int) $subject['subject_id'] ?>"
                    <?= (
                        (string)
                        $subject['subject_id']
                        ===
                        $subjectId
                    )
                        ? 'selected'
                        : ''
                    ?>
                >

                    <?= e(
                        (string)
                        $subject['subject_name']
                    ) ?>

                    <?php if (
                        !empty(
                            $subject['subject_code']
                        )
                    ): ?>

                        —
                        <?= e(
                            (string)
                            $subject['subject_code']
                        ) ?>

                    <?php endif; ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <div class="help-text">

        Only subjects assigned to you with a
        class/section are available.

    </div>

</div>


<!-- =====================================================
     CLASS
===================================================== -->

<div class="form-group">

    <label for="class_id">

        Class

        <span class="required">
            *
        </span>

    </label>


    <div class="input-wrapper">

        <span class="input-icon">
            🎓
        </span>

        <select
            id="class_id"
            name="class_id"
            class="has-icon"
            required
            disabled
        >

            <option value="">
                Select Subject First
            </option>

        </select>

    </div>


    <div class="help-text">

        Classes are loaded according to
        your selected subject.

    </div>

</div>


<!-- =====================================================
     SECTION
===================================================== -->

<div class="form-group">

    <label for="section_id">

        Section

        <span
            id="sectionRequiredLabel"
            class="required"
        >
            *
        </span>

        <span
            id="sectionOptionalLabel"
            class="optional"
            style="display:none;"
        >
            Optional
        </span>

    </label>


    <div class="input-wrapper">

        <span class="input-icon">
            👥
        </span>

        <select
            id="section_id"
            name="section_id"
            class="has-icon"
            disabled
        >

            <option value="">
                Select Class First
            </option>

        </select>

    </div>


    <div
        class="section-status"
        id="sectionStatus"
    >
        Select a subject and class to see
        your assigned section.
    </div>


    <div class="help-text">

        Whole Class means the assignment applies
        to the complete class. A specific section
        can also be selected when assigned.

    </div>

</div>


<!-- =====================================================
     DUE DATE
===================================================== -->

<div class="form-group">

    <label for="due_date">

        Due Date

        <span class="required">
            *
        </span>

    </label>


    <div class="input-wrapper">

        <span class="input-icon">
            📅
        </span>

        <input
            type="date"
            id="due_date"
            name="due_date"
            class="has-icon"
            value="<?= e($dueDate) ?>"
            min="<?= date('Y-m-d') ?>"
            required
        >

    </div>


    <div class="help-text">
        Select the final submission date.
    </div>

</div>


<!-- =====================================================
     DESCRIPTION
===================================================== -->

<div class="form-group full-width">

    <label for="description">

        Assignment Instructions

        <span class="optional">
            Optional
        </span>

    </label>


    <textarea
        id="description"
        name="description"
        maxlength="10000"
        placeholder="Write assignment instructions, requirements, submission guidelines, etc..."
    ><?= e($description) ?></textarea>


    <div class="help-text">

        Provide clear instructions so students
        understand what they need to submit.

    </div>

</div>


</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<div class="form-footer">

    <div class="security-note">

        <span class="security-icon">
            🔒
        </span>

        <span>
            Assignment data is securely validated
            before saving.
        </span>

    </div>


    <div class="actions">

        <a
            href="dashboard.php"
            class="btn btn-cancel"
        >
            Cancel
        </a>


        <button
            type="submit"
            class="btn btn-primary"
        >

            <span>
                ➕
            </span>

            Create Assignment

        </button>

    </div>

</div>


</form>

</div>

</div>


<!-- =====================================================
     ASSIGNMENT LIST
===================================================== -->

<div class="list-card">


<div class="list-header">

    <div class="list-title-area">

        <div class="list-icon">
            📚
        </div>

        <div>

            <h2>
                My Assignments
            </h2>

            <p>
                Assignments created by you
            </p>

        </div>

    </div>


    <div class="assignment-count">

        <?= count($teacherAssignments) ?>

        Assignment<?= count($teacherAssignments) !== 1 ? 's' : '' ?>

    </div>

</div>


<?php if (empty($teacherAssignments)): ?>

<div class="empty-state">

    <div class="empty-state-icon">
        📝
    </div>

    <strong>
        No assignments found
    </strong>

    <span>
        Create your first assignment using
        the form above.
    </span>

</div>

<?php else: ?>


<div class="table-wrapper">

<table class="assignment-table">

<thead>

<tr>

    <th>#</th>

    <th>Assignment</th>

    <th>Subject</th>

    <th>Class</th>

    <th>Section</th>

    <th>Due Date</th>

    <th>Status</th>

</tr>

</thead>


<tbody>

<?php $counter = 1; ?>

<?php foreach (
    $teacherAssignments
    as $assignment
): ?>

<tr>

<td class="assignment-number">

    <?= $counter++ ?>

</td>


<td>

    <div class="assignment-title">

        <?= e(
            (string) (
                $assignment['title']
                ?? ''
            )
        ) ?>

    </div>


    <?php if (
        !empty(
            $assignment['description']
        )
    ): ?>

        <div class="assignment-subtitle">

            <?= e(
                (string)
                $assignment['description']
            ) ?>

        </div>

    <?php endif; ?>

</td>


<td>

    <div class="subject-name">

        <?= e(
            (string) (
                $assignment['subject_name']
                ?? 'N/A'
            )
        ) ?>

    </div>

</td>


<td>

    <div class="class-name">

        <?= e(
            (string) (
                $assignment['class_name']
                ?? 'N/A'
            )
        ) ?>

    </div>

</td>


<td>

    <div class="section-name">

        <?= e(
            (string) (
                $assignment['section_name']
                ?? 'Whole Class'
            )
        ) ?>

    </div>

</td>


<td>

    <div class="due-date">

        <?php

        $assignmentDueDate =
            $assignment['due_date']
            ?? null;

        if (
            $assignmentDueDate
        ) {

            $timestamp =
                strtotime(
                    (string)
                    $assignmentDueDate
                );

            echo $timestamp
                ? e(
                    date(
                        'd M Y',
                        $timestamp
                    )
                )
                : 'N/A';

        } else {

            echo 'N/A';
        }

        ?>

    </div>

</td>


<td>

<?php

$status =
    (string) (
        $assignment['status']
        ?? 'active'
    );

$statusClass =
    $status === 'active'
        ? 'status-active'
        : 'status-inactive';

?>

<span
    class="status-badge <?= e($statusClass) ?>"
>

    <span class="status-dot"></span>

    <?= e(
        ucfirst($status)
    ) ?>

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>


<?php endif; ?>


</div>


<div class="page-note">

    Student Management System
    &nbsp;•&nbsp;
    Teacher Assignment Management

</div>


</div>

</div>


<script>

// =====================================================
// AUTHORIZED DATA
// =====================================================

const authorizedData =
    <?= $authorizedJson ?>;


// =====================================================
// PREVIOUS VALUES
// =====================================================

const previousSubjectId =
    <?= $subjectId !== ''
        ? (int) $subjectId
        : 0 ?>;

const previousClassId =
    <?= $classId !== ''
        ? (int) $classId
        : 0 ?>;

const previousSectionId =
    <?= $sectionId !== ''
        ? (int) $sectionId
        : 0 ?>;


// =====================================================
// ELEMENTS
// =====================================================

const subjectSelect =
    document.getElementById('subject_id');

const classSelect =
    document.getElementById('class_id');

const sectionSelect =
    document.getElementById('section_id');

const sectionStatus =
    document.getElementById('sectionStatus');

const sectionRequiredLabel =
    document.getElementById(
        'sectionRequiredLabel'
    );

const sectionOptionalLabel =
    document.getElementById(
        'sectionOptionalLabel'
    );

const assignmentForm =
    document.getElementById(
        'assignmentForm'
    );


// =====================================================
// GET SUBJECT DATA
// =====================================================

function getSubjectData() {

    if (!subjectSelect) {
        return null;
    }

    const subjectId =
        parseInt(
            subjectSelect.value || '0',
            10
        );

    if (!subjectId) {
        return null;
    }

    return authorizedData.find(
        function(subject) {

            return (
                parseInt(
                    subject.subject_id,
                    10
                ) === subjectId
            );

        }
    ) || null;
}


// =====================================================
// GET CLASS DATA
// =====================================================

function getClassData() {

    const subjectData =
        getSubjectData();

    if (!subjectData) {
        return null;
    }

    const classId =
        parseInt(
            classSelect.value || '0',
            10
        );

    if (!classId) {
        return null;
    }

    return subjectData.classes.find(
        function(classItem) {

            return (
                parseInt(
                    classItem.class_id,
                    10
                ) === classId
            );

        }
    ) || null;
}


// =====================================================
// RESET CLASS
// =====================================================

function resetClass() {

    classSelect.innerHTML = `
        <option value="">
            Select Subject First
        </option>
    `;

    classSelect.disabled = true;
}


// =====================================================
// RESET SECTION
// =====================================================

function resetSection(
    message = 'Select Class First'
) {

    sectionSelect.innerHTML = `
        <option value="">
            ${message}
        </option>
    `;

    sectionSelect.disabled = true;

    sectionStatus.textContent =
        'Select a subject and class to see your assigned section.';

    sectionRequiredLabel.style.display =
        '';

    sectionOptionalLabel.style.display =
        'none';
}


// =====================================================
// LOAD CLASSES
// =====================================================

function loadClasses(
    selectedClassId = 0,
    selectedSectionId = 0
) {

    resetClass();

    resetSection();


    const subjectData =
        getSubjectData();

    if (!subjectData) {
        return;
    }


    const classes =
        Array.isArray(
            subjectData.classes
        )
            ? subjectData.classes
            : [];


    if (classes.length === 0) {

        classSelect.innerHTML = `
            <option value="">
                No assigned classes
            </option>
        `;

        classSelect.disabled = true;

        return;
    }


    classSelect.innerHTML = `
        <option value="">
            Select Class
        </option>
    `;


    classes.forEach(
        function(classItem) {

            const option =
                document.createElement(
                    'option'
                );

            option.value =
                classItem.class_id;

            option.textContent =
                classItem.class_name;


            if (
                parseInt(
                    selectedClassId,
                    10
                ) ===
                parseInt(
                    classItem.class_id,
                    10
                )
            ) {

                option.selected = true;
            }


            classSelect.appendChild(
                option
            );
        }
    );


    classSelect.disabled = false;


    if (
        parseInt(
            selectedClassId,
            10
        ) > 0
    ) {

        loadSections(
            selectedSectionId
        );
    }
}


// =====================================================
// LOAD SECTIONS
// =====================================================

function loadSections(
    selectedSectionId = 0
) {

    resetSection();


    const classData =
        getClassData();

    if (!classData) {
        return;
    }


    const sections =
        Array.isArray(
            classData.sections
        )
            ? classData.sections
            : [];


    const hasWholeClass =
        classData.whole_class === true;


    // =================================================
    // SPECIFIC SECTIONS
    // =================================================

    if (sections.length > 0) {

        sectionSelect.innerHTML = `
            <option value="">
                Select Section
            </option>
        `;


        // Whole Class option
        if (hasWholeClass) {

            const wholeOption =
                document.createElement(
                    'option'
                );

            wholeOption.value = '';

            wholeOption.textContent =
                'Whole Class / No Section';

            sectionSelect.appendChild(
                wholeOption
            );
        }


        // Specific sections
        sections.forEach(
            function(section) {

                const option =
                    document.createElement(
                        'option'
                    );

                option.value =
                    section.section_id;

                option.textContent =
                    section.section_name;


                if (
                    parseInt(
                        selectedSectionId,
                        10
                    ) ===
                    parseInt(
                        section.section_id,
                        10
                    )
                ) {

                    option.selected =
                        true;
                }


                sectionSelect.appendChild(
                    option
                );
            }
        );


        sectionSelect.disabled = false;


        if (hasWholeClass) {

            sectionRequiredLabel.style.display =
                'none';

            sectionOptionalLabel.style.display =
                'inline';

            sectionStatus.textContent =
                'Whole Class is available. You can also select a specific section assigned to you.';

        } else {

            sectionRequiredLabel.style.display =
                '';

            sectionOptionalLabel.style.display =
                'none';

            sectionStatus.textContent =
                'Please select one of your assigned sections.';
        }


        return;
    }


    // =================================================
    // WHOLE CLASS ONLY
    // =================================================

    if (hasWholeClass) {

        sectionSelect.innerHTML = `
            <option value="">
                Whole Class / No Section
            </option>
        `;

        sectionSelect.value = '';

        sectionSelect.disabled = true;


        sectionRequiredLabel.style.display =
            'none';

        sectionOptionalLabel.style.display =
            'inline';

        sectionStatus.textContent =
            'This subject and class are assigned to you as Whole Class. No section selection is required.';

        return;
    }


    // =================================================
    // NO SECTION
    // =================================================

    sectionSelect.innerHTML = `
        <option value="">
            No section assignment
        </option>
    `;

    sectionSelect.disabled = true;

    sectionRequiredLabel.style.display =
        'none';

    sectionOptionalLabel.style.display =
        'inline';

    sectionStatus.textContent =
        'No section assignment is available for this subject and class.';
}


// =====================================================
// SUBJECT CHANGE
// =====================================================

subjectSelect.addEventListener(
    'change',
    function() {

        loadClasses();

    }
);


// =====================================================
// CLASS CHANGE
// =====================================================

classSelect.addEventListener(
    'change',
    function() {

        loadSections();

    }
);


// =====================================================
// SECTION CHANGE
// =====================================================

sectionSelect.addEventListener(
    'change',
    function() {

        const classData =
            getClassData();

        if (!classData) {
            return;
        }

        if (
            sectionSelect.value === '' &&
            classData.whole_class !== true
        ) {
            return;
        }

    }
);


// =====================================================
// FORM VALIDATION
// =====================================================

if (assignmentForm) {

    assignmentForm.addEventListener(
        'submit',
        function(event) {

            const title =
                document.getElementById(
                    'title'
                );

            const dueDate =
                document.getElementById(
                    'due_date'
                );


            if (
                !subjectSelect.value
            ) {

                event.preventDefault();

                alert(
                    'Please select a subject.'
                );

                subjectSelect.focus();

                return;
            }


            if (
                !classSelect.value
            ) {

                event.preventDefault();

                alert(
                    'Please select a class.'
                );

                classSelect.focus();

                return;
            }


            if (
                !title.value.trim()
            ) {

                event.preventDefault();

                alert(
                    'Please enter assignment title.'
                );

                title.focus();

                return;
            }


            if (
                !dueDate.value
            ) {

                event.preventDefault();

                alert(
                    'Please select due date.'
                );

                dueDate.focus();

                return;
            }


            /*
             * Section is required only when the
             * selected class does not have Whole Class
             * authorization.
             */

            const classData =
                getClassData();

            if (
                classData &&
                classData.whole_class !== true &&
                Array.isArray(
                    classData.sections
                ) &&
                classData.sections.length > 0 &&
                !sectionSelect.value
            ) {

                event.preventDefault();

                alert(
                    'Please select a section.'
                );

                sectionSelect.focus();

                return;
            }

        }
    );
}


// =====================================================
// INITIALIZE
// =====================================================

document.addEventListener(
    'DOMContentLoaded',
    function() {

        if (
            previousSubjectId > 0
        ) {

            subjectSelect.value =
                String(
                    previousSubjectId
                );


            loadClasses(
                previousClassId,
                previousSectionId
            );

        } else {

            resetClass();

            resetSection();
        }

    }
);

</script>

</body>

</html>