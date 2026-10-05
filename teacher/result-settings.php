<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
requireTeacher();

$pdo = db();
$database = null;

$teacherUserId = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Logged-in Teacher
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.teacher_id,
        t.user_id,
        t.status
    FROM teachers t
    WHERE t.user_id = ?
    LIMIT 1
");

$stmt->execute([$teacherUserId]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || ($teacher['status'] ?? '') !== 'active') {
    http_response_code(403);
    exit('Access Denied');
}

$teacherId = (int) $teacher['id'];

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
|
| Uses the application-wide session token (includes/auth_check.php).
|
*/

$csrfToken = getCsrfToken();

/*
|--------------------------------------------------------------------------
| Flash Messages
|--------------------------------------------------------------------------
*/

$success = '';
$error = '';

if (isset($_SESSION['result_settings_success'])) {
    $success = (string) $_SESSION['result_settings_success'];
    unset($_SESSION['result_settings_success']);
}

if (isset($_SESSION['result_settings_error'])) {
    $error = (string) $_SESSION['result_settings_error'];
    unset($_SESSION['result_settings_error']);
}

/*
|--------------------------------------------------------------------------
| Teacher Assignments
|--------------------------------------------------------------------------
|
| teacher_classes is the source of truth.
|
| section_id = NULL
|     Teacher is assigned to the whole class.
|
| section_id != NULL
|     Teacher is assigned to a specific section.
|
*/

$stmt = $pdo->prepare("
    SELECT
        tc.id AS assignment_id,
        tc.class_id,
        tc.section_id,
        tc.subject_id,

        c.name AS class_name,
        sec.name AS section_name,
        sub.name AS subject_name

    FROM teacher_classes tc

    INNER JOIN classes c
        ON c.id = tc.class_id

    INNER JOIN subjects sub
        ON sub.id = tc.subject_id

    LEFT JOIN sections sec
        ON sec.id = tc.section_id

    WHERE tc.teacher_id = ?

    ORDER BY
        sub.name ASC,
        c.name ASC,
        CASE
            WHEN tc.section_id IS NULL THEN 0
            ELSE 1
        END,
        sec.name ASC
");

$stmt->execute([$teacherId]);

$teacherAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Build Authorized Structure
|--------------------------------------------------------------------------
*/

$authorizedData = [];

foreach ($teacherAssignments as $assignment) {

    $subjectId = (int) $assignment['subject_id'];
    $classId   = (int) $assignment['class_id'];

    $sectionId = $assignment['section_id'] !== null
        ? (int) $assignment['section_id']
        : null;

    if (!isset($authorizedData[$subjectId])) {

        $authorizedData[$subjectId] = [
            'id' => $subjectId,
            'name' => (string) $assignment['subject_name'],
            'classes' => []
        ];
    }

    if (!isset(
        $authorizedData[$subjectId]['classes'][$classId]
    )) {

        $authorizedData[$subjectId]['classes'][$classId] = [
            'id' => $classId,
            'name' => (string) $assignment['class_name'],
            'whole_class' => false,
            'sections' => []
        ];
    }

    if ($sectionId === null) {

        $authorizedData[$subjectId]['classes'][$classId]['whole_class'] = true;

    } else {

        $exists = false;

        foreach (
            $authorizedData[$subjectId]['classes'][$classId]['sections']
            as $existingSection
        ) {

            if (
                (int) $existingSection['id'] ===
                $sectionId
            ) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {

            $authorizedData[$subjectId]['classes'][$classId]['sections'][] = [
                'id' => $sectionId,
                'name' => (string) $assignment['section_name']
            ];
        }
    }
}

/*
|--------------------------------------------------------------------------
| Data For JavaScript
|--------------------------------------------------------------------------
*/

$subjectsForJs = [];

foreach ($authorizedData as $subject) {

    $classesForJs = [];

    foreach ($subject['classes'] as $class) {

        $classesForJs[] = [
            'id' => $class['id'],
            'name' => $class['name'],
            'whole_class' => $class['whole_class'],
            'sections' => $class['sections']
        ];
    }

    $subjectsForJs[] = [
        'id' => $subject['id'],
        'name' => $subject['name'],
        'classes' => $classesForJs
    ];
}

/*
|--------------------------------------------------------------------------
| Selected Configuration
|--------------------------------------------------------------------------
*/

$selectedSubject = isset($_GET['subject_id'])
    ? (int) $_GET['subject_id']
    : 0;

$selectedClass = isset($_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;

$selectedSectionRaw = $_GET['section_id'] ?? '';

$selectedSection = (
    $selectedSectionRaw === '' ||
    $selectedSectionRaw === 'whole'
)
    ? null
    : (int) $selectedSectionRaw;

/*
|--------------------------------------------------------------------------
| Find Selected Assignment
|--------------------------------------------------------------------------
*/

$selectedAssignment = null;

foreach ($teacherAssignments as $assignment) {

    $assignmentSubjectId =
        (int) $assignment['subject_id'];

    $assignmentClassId =
        (int) $assignment['class_id'];

    $assignmentSectionId =
        $assignment['section_id'] !== null
            ? (int) $assignment['section_id']
            : null;

    if (
        $assignmentSubjectId === $selectedSubject &&
        $assignmentClassId === $selectedClass
    ) {

        if (
            $assignmentSectionId === $selectedSection
        ) {

            $selectedAssignment = $assignment;
            break;
        }

        /*
        |--------------------------------------------------------------------------
        | Whole Class Authorization Can Cover A Section
        |--------------------------------------------------------------------------
        */

        if (
            $selectedSection !== null &&
            $assignmentSectionId === null
        ) {

            $selectedAssignment = $assignment;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Selected Section Label
|--------------------------------------------------------------------------
*/

$selectedSectionLabel = 'Whole Class';

if ($selectedSection !== null) {

    foreach ($teacherAssignments as $assignment) {

        if (
            (int) $assignment['class_id'] ===
                $selectedClass &&
            (int) $assignment['subject_id'] ===
                $selectedSubject &&
            $assignment['section_id'] !== null &&
            (int) $assignment['section_id'] ===
                $selectedSection
        ) {

            $selectedSectionLabel =
                (string) $assignment['section_name'];

            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Existing Result Setting
|--------------------------------------------------------------------------
*/

$currentSetting = null;

if (
    $selectedSubject > 0 &&
    $selectedClass > 0 &&
    $selectedAssignment !== null
) {

    /*
    |--------------------------------------------------------------------------
    | Exact Section Setting
    |--------------------------------------------------------------------------
    */

    if ($selectedSection !== null) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM result_settings
            WHERE teacher_id = ?
              AND class_id = ?
              AND subject_id = ?
              AND section_id = ?

            ORDER BY
                CASE
                    WHEN status = 'active' THEN 0
                    ELSE 1
                END,
                id DESC

            LIMIT 1
        ");

        $stmt->execute([
            $teacherId,
            $selectedClass,
            $selectedSubject,
            $selectedSection
        ]);

        $currentSetting =
            $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | Whole Class Setting
    |--------------------------------------------------------------------------
    */

    if ($currentSetting === null) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM result_settings
            WHERE teacher_id = ?
              AND class_id = ?
              AND subject_id = ?
              AND section_id IS NULL

            ORDER BY
                CASE
                    WHEN status = 'active' THEN 0
                    ELSE 1
                END,
                id DESC

            LIMIT 1
        ");

        $stmt->execute([
            $teacherId,
            $selectedClass,
            $selectedSubject
        ]);

        $currentSetting =
            $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

/*
|--------------------------------------------------------------------------
| POST - Save Result Configuration
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $postedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    /*
    |--------------------------------------------------------------------------
    | CSRF Validation
    |--------------------------------------------------------------------------
    */

    if (
        $postedToken === '' ||
        !validateCsrfToken($postedToken)
    ) {

        $error =
            'Security verification failed. Please refresh the page and try again.';

    } else {

        $subjectId = (int) (
            $_POST['subject_id'] ?? 0
        );

        $classId = (int) (
            $_POST['class_id'] ?? 0
        );

        $sectionRaw =
            (string) ($_POST['section_id'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | SECTION SCOPING
        |--------------------------------------------------------------------------
        |
        | section_id is part of the configuration key:
        |
        |   NULL          -> whole-class configuration (applies to every section)
        |   <section id>  -> configuration for one specific section only
        |
        | Class 9-A and 9-B can therefore carry different weights at the
        | same time. The value used to be forced to NULL here, which made
        | per-section configuration impossible to save.
        |
        */

        if ($sectionRaw === '' || $sectionRaw === 'whole') {

            $sectionId = null;

        } elseif (ctype_digit($sectionRaw) && (int) $sectionRaw > 0) {

            $sectionId = (int) $sectionRaw;

        } else {

            // Invalid input: rejected during validation below.
            $sectionId = -1;
        }

        /*
        |--------------------------------------------------------------------------
        | Read Component Maximum Marks
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | These are NOT student obtained marks.
        |
        | These values define the maximum contribution of each
        | component to the final result.
        |
        */

        $attendanceTotal = (float) (
            $_POST['attendance_total'] ?? 0
        );

        $assignmentTotal = (float) (
            $_POST['assignment_total'] ?? 0
        );

        $midtermTotal = (float) (
            $_POST['midterm_total'] ?? 0
        );

        $finalTotal = (float) (
            $_POST['final_total'] ?? 0
        );

        $passingPercent = (float) (
            $_POST['passing_percentage'] ?? 40
        );

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        // The section must really belong to the selected class.
        if ($sectionId !== null && $sectionId > 0) {

            $sectionCheck = $pdo->prepare("
                SELECT id
                FROM sections
                WHERE id = ?
                  AND class_id = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $sectionCheck->execute([
                $sectionId,
                $classId
            ]);

            if (!$sectionCheck->fetchColumn()) {

                // Reported through the chain below.
                $sectionId = -1;
            }
        }

        if (
            $subjectId <= 0 ||
            $classId <= 0
        ) {

            $error =
                'Please select a Subject and Class before saving.';

        } elseif ($sectionId === -1) {

            $error =
                'Invalid section selected. The section must be active and belong to the selected class.';

        } elseif (
            $attendanceTotal < 0 ||
            $assignmentTotal < 0 ||
            $midtermTotal < 0 ||
            $finalTotal < 0
        ) {

            $error =
                'Maximum marks cannot be negative.';

        } elseif (
            $passingPercent < 0 ||
            $passingPercent > 100
        ) {

            $error =
                'Passing percentage must be between 0 and 100.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Authorization
            |--------------------------------------------------------------------------
            */

            if ($sectionId === null) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM teacher_classes

                    WHERE teacher_id = ?
                      AND class_id = ?
                      AND subject_id = ?
                      AND section_id IS NULL

                    LIMIT 1
                ");

                $stmt->execute([
                    $teacherId,
                    $classId,
                    $subjectId
                ]);

            } else {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM teacher_classes

                    WHERE teacher_id = ?
                      AND class_id = ?
                      AND subject_id = ?

                      AND (
                          section_id = ?
                          OR section_id IS NULL
                      )

                    LIMIT 1
                ");

                $stmt->execute([
                    $teacherId,
                    $classId,
                    $subjectId,
                    $sectionId
                ]);
            }

            $authorized =
                (bool) $stmt->fetchColumn();

            if (!$authorized) {

                $error =
                    'You are not authorized to configure this Subject, Class and Section.';

            } else {

                try {

                    $pdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | Find Existing Exact Setting
                    |--------------------------------------------------------------------------
                    */

                    if ($sectionId === null) {

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM result_settings

                            WHERE teacher_id = ?
                              AND class_id = ?
                              AND subject_id = ?
                              AND section_id IS NULL

                            ORDER BY id DESC
                            LIMIT 1
                        ");

                        $stmt->execute([
                            $teacherId,
                            $classId,
                            $subjectId
                        ]);

                    } else {

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM result_settings

                            WHERE teacher_id = ?
                              AND class_id = ?
                              AND subject_id = ?
                              AND section_id = ?

                            ORDER BY id DESC
                            LIMIT 1
                        ");

                        $stmt->execute([
                            $teacherId,
                            $classId,
                            $subjectId,
                            $sectionId
                        ]);
                    }

                    $existingId =
                        $stmt->fetchColumn();

                    /*
                    |--------------------------------------------------------------------------
                    | Update
                    |--------------------------------------------------------------------------
                    */

                    if ($existingId) {

                        $stmt = $pdo->prepare("
                            UPDATE result_settings

                            SET
                                attendance_total = ?,
                                assignment_total = ?,
                                midterm_total = ?,
                                final_total = ?,
                                passing_percentage = ?,
                                status = 'active',
                                updated_at = NOW()

                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $attendanceTotal,
                            $assignmentTotal,
                            $midtermTotal,
                            $finalTotal,
                            $passingPercent,
                            (int) $existingId
                        ]);

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Insert
                        |--------------------------------------------------------------------------
                        */

                        $stmt = $pdo->prepare("
                            INSERT INTO result_settings (

                                teacher_id,
                                class_id,
                                section_id,
                                subject_id,

                                attendance_total,
                                assignment_total,
                                midterm_total,
                                final_total,

                                passing_percentage,
                                status,

                                created_at,
                                updated_at

                            ) VALUES (
                                ?, ?, ?, ?,
                                ?, ?, ?, ?,
                                ?, 'active',
                                NOW(),
                                NOW()
                            )
                        ");

                        $stmt->execute([
                            $teacherId,
                            $classId,
                            $sectionId,
                            $subjectId,

                            $attendanceTotal,
                            $assignmentTotal,
                            $midtermTotal,
                            $finalTotal,

                            $passingPercent
                        ]);
                    }

                    $pdo->commit();

                    $_SESSION['result_settings_success'] =
                        'Result configuration saved successfully.';

                    /*
                    |--------------------------------------------------------------------------
                    | Redirect
                    |--------------------------------------------------------------------------
                    */

                    $redirect =
                        'result-settings.php?' .
                        'subject_id=' .
                        $subjectId .
                        '&class_id=' .
                        $classId;

                    if ($sectionId !== null) {

                        $redirect .=
                            '&section_id=' .
                            $sectionId;
                    }

                    header(
                        'Location: ' . $redirect
                    );

                    exit;

                } catch (Throwable $exception) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Do not expose raw DB error to teacher
                    |--------------------------------------------------------------------------
                    */

                    error_log(
                        'Result Settings Error: ' .
                        $exception->getMessage()
                    );

                    $error =
                        'Unable to save the result configuration. Please try again.';
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Preserve POST Values When Validation Fails
    |--------------------------------------------------------------------------
    */

    if ($error !== '') {

        $attendanceValue = e(
            (string) ($_POST['attendance_total'] ?? '')
        );

        $assignmentValue = e(
            (string) ($_POST['assignment_total'] ?? '')
        );

        $midtermValue = e(
            (string) ($_POST['midterm_total'] ?? '')
        );

        $finalValue = e(
            (string) ($_POST['final_total'] ?? '')
        );

        $passingValue = e(
            (string) ($_POST['passing_percentage'] ?? '40')
        );
    }
}

/*
|--------------------------------------------------------------------------
| Form Values
|--------------------------------------------------------------------------
*/

if (!isset($attendanceValue)) {

    $attendanceValue =
        $currentSetting !== null
            ? (string) $currentSetting['attendance_total']
            : '0';
}

if (!isset($assignmentValue)) {

    $assignmentValue =
        $currentSetting !== null
            ? (string) $currentSetting['assignment_total']
            : '0';
}

if (!isset($midtermValue)) {

    $midtermValue =
        $currentSetting !== null
            ? (string) $currentSetting['midterm_total']
            : '0';
}

if (!isset($finalValue)) {

    $finalValue =
        $currentSetting !== null
            ? (string) $currentSetting['final_total']
            : '0';
}

if (!isset($passingValue)) {

    $passingValue =
        $currentSetting !== null
            ? (string) $currentSetting['passing_percentage']
            : '40';
}

/*
|--------------------------------------------------------------------------
| Total Configured Marks
|--------------------------------------------------------------------------
*/

$configuredTotal =
    (float) $attendanceValue +
    (float) $assignmentValue +
    (float) $midtermValue +
    (float) $finalValue;

$configuredTotalDisplay =
    number_format(
        $configuredTotal,
        2,
        '.',
        ''
    );

if (str_ends_with($configuredTotalDisplay, '.00')) {
    $configuredTotalDisplay =
        substr(
            $configuredTotalDisplay,
            0,
            -3
        );
}

/*
|--------------------------------------------------------------------------
| Current Configuration Status
|--------------------------------------------------------------------------
*/

$hasCurrentSetting =
    $currentSetting !== null;

$subjectName = '';
$className = '';

if ($selectedAssignment !== null) {

    $subjectName =
        (string) $selectedAssignment['subject_name'];

    $className =
        (string) $selectedAssignment['class_name'];
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

<title>Result Configuration | Teacher Portal</title>

<style>

    :root {
        --bg: #f5f7fb;
        --card: #ffffff;
        --border: #e5e9f0;
        --text: #172033;
        --muted: #6b7280;
        --primary: #1f2937;
        --primary-hover: #111827;
        --success-bg: #ecfdf3;
        --success-border: #b7ebc6;
        --success-text: #176b35;
        --error-bg: #fff1f2;
        --error-border: #fecdd3;
        --error-text: #be123c;
        --blue-bg: #eff6ff;
        --blue-border: #bfdbfe;
        --blue-text: #1e40af;
        --soft: #f8fafc;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        background: var(--bg);
        color: var(--text);
        font-family:
            Inter,
            ui-sans-serif,
            system-ui,
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            sans-serif;
    }

    button,
    input,
    select {
        font: inherit;
    }

    .page {
        width: min(1180px, calc(100% - 32px));
        margin: 0 auto;
        padding: 32px 0 60px;
    }

    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 24px;
        margin-bottom: 26px;
    }

    .header-left {
        min-width: 0;
    }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        margin-bottom: 8px;
        color: #64748b;
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .page-header h1 {
        margin: 0;
        font-size: clamp(26px, 4vw, 34px);
        line-height: 1.15;
        letter-spacing: -.7px;
        font-weight: 850;
    }

    .page-header p {
        margin: 9px 0 0;
        color: var(--muted);
        font-size: 14px;
        line-height: 1.6;
        max-width: 690px;
    }

    .dashboard-btn {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 42px;
        padding: 0 15px;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #fff;
        color: #334155;
        text-decoration: none;
        font-size: 13px;
        font-weight: 750;
        transition: .18s ease;
    }

    .dashboard-btn:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    */

    .alert {
        display: flex;
        align-items: flex-start;
        gap: 11px;
        padding: 14px 16px;
        margin-bottom: 20px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.5;
        font-weight: 650;
    }

    .alert.success {
        color: var(--success-text);
        background: var(--success-bg);
        border: 1px solid var(--success-border);
    }

    .alert.error {
        color: var(--error-text);
        background: var(--error-bg);
        border: 1px solid var(--error-border);
    }

    .alert-icon {
        font-size: 16px;
        line-height: 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Main Card
    |--------------------------------------------------------------------------
    */

    .card {
        overflow: hidden;
        margin-bottom: 20px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow:
            0 4px 16px rgba(15, 23, 42, .035);
    }

    .card-header {
        padding: 21px 24px;
        border-bottom: 1px solid #edf0f4;
    }

    .card-header-row {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
    }

    .step {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 25px;
        height: 25px;
        margin-right: 9px;
        border-radius: 8px;
        background: #eef2f7;
        color: #475569;
        font-size: 12px;
        font-weight: 850;
        vertical-align: middle;
    }

    .card-header h2 {
        display: inline;
        margin: 0;
        font-size: 17px;
        font-weight: 820;
    }

    .card-header p {
        margin: 7px 0 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .card-body {
        padding: 24px;
    }

    /*
    |--------------------------------------------------------------------------
    | Selection
    |--------------------------------------------------------------------------
    */

    .selection-grid {
        display: grid;
        grid-template-columns:
            minmax(0, 1.1fr)
            minmax(0, 1fr)
            minmax(0, 1fr);
        gap: 15px;
    }

    .field {
        min-width: 0;
    }

    .field label {
        display: block;
        margin-bottom: 7px;
        color: #374151;
        font-size: 12px;
        font-weight: 800;
    }

    .required {
        color: #dc2626;
    }

    .control {
        width: 100%;
        height: 45px;
        padding: 0 12px;
        border: 1px solid #d8dee8;
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: #172033;
        font-size: 14px;
        transition: .18s ease;
    }

    .control:focus {
        border-color: #94a3b8;
        box-shadow:
            0 0 0 3px rgba(100, 116, 139, .10);
    }

    .field-help {
        margin-top: 6px;
        color: #8a94a5;
        font-size: 11px;
        line-height: 1.4;
    }

    .selected-context {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 19px;
        padding: 12px 14px;
        border: 1px solid #e6eaf0;
        border-radius: 10px;
        background: var(--soft);
        color: #64748b;
        font-size: 12px;
    }

    .context-label {
        color: #475569;
        font-weight: 800;
    }

    .context-pill {
        display: inline-flex;
        align-items: center;
        min-height: 25px;
        padding: 3px 9px;
        border-radius: 7px;
        background: #fff;
        border: 1px solid #e2e8f0;
        color: #334155;
        font-weight: 700;
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration Intro
    |--------------------------------------------------------------------------
    */

    .configuration-intro {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 18px;
        align-items: center;
        margin-bottom: 20px;
        padding: 16px;
        border: 1px solid var(--blue-border);
        border-radius: 12px;
        background: var(--blue-bg);
    }

    .configuration-intro strong {
        display: block;
        margin-bottom: 4px;
        color: var(--blue-text);
        font-size: 13px;
    }

    .configuration-intro p {
        margin: 0;
        color: #475569;
        font-size: 12px;
        line-height: 1.55;
    }

    .configuration-badge {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 11px;
        border-radius: 9px;
        background: #fff;
        border: 1px solid #dbe4f2;
        color: #475569;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    /*
    |--------------------------------------------------------------------------
    | Assessment Cards
    |--------------------------------------------------------------------------
    */

    .assessment-grid {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .assessment-card {
        position: relative;
        padding: 17px;
        border: 1px solid #e3e7ee;
        border-radius: 13px;
        background: #fff;
        transition: .18s ease;
    }

    .assessment-card:hover {
        border-color: #cfd6e1;
        box-shadow:
            0 5px 15px rgba(15, 23, 42, .045);
    }

    .assessment-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .assessment-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: #f1f5f9;
        font-size: 16px;
    }

    .assessment-number {
        color: #94a3b8;
        font-size: 10px;
        font-weight: 800;
    }

    .assessment-title {
        margin: 0;
        color: #1e293b;
        font-size: 14px;
        font-weight: 820;
    }

    .assessment-description {
        min-height: 49px;
        margin: 5px 0 13px;
        color: #7b8494;
        font-size: 11px;
        line-height: 1.5;
    }

    .marks-label {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .marks-control-wrap {
        position: relative;
    }

    .marks-control {
        width: 100%;
        height: 46px;
        padding: 0 55px 0 12px;
        border: 1px solid #d8dee8;
        border-radius: 9px;
        outline: none;
        background: #fdfefe;
        color: #172033;
        font-size: 17px;
        font-weight: 800;
        transition: .18s ease;
    }

    .marks-control:focus {
        border-color: #94a3b8;
        background: #fff;
        box-shadow:
            0 0 0 3px rgba(100, 116, 139, .10);
    }

    .marks-unit {
        position: absolute;
        top: 50%;
        right: 12px;
        transform: translateY(-50%);
        color: #8a94a5;
        font-size: 11px;
        font-weight: 750;
        pointer-events: none;
    }

    .assessment-note {
        margin-top: 8px;
        color: #94a3b8;
        font-size: 10px;
        line-height: 1.45;
    }

    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    .result-summary {
        display: grid;
        grid-template-columns: 1fr auto;
        align-items: center;
        gap: 20px;
        margin-top: 20px;
        padding: 17px 18px;
        border: 1px solid #e3e8ef;
        border-radius: 12px;
        background: #f8fafc;
    }

    .summary-title {
        margin: 0 0 4px;
        color: #334155;
        font-size: 13px;
        font-weight: 800;
    }

    .summary-description {
        margin: 0;
        color: #8a94a5;
        font-size: 11px;
        line-height: 1.5;
    }

    .summary-value {
        color: #172033;
        font-size: 23px;
        font-weight: 900;
        white-space: nowrap;
    }

    /*
    |--------------------------------------------------------------------------
    | Passing Percentage
    |--------------------------------------------------------------------------
    */

    .passing-section {
        display: grid;
        grid-template-columns:
            minmax(0, 330px)
            1fr;
        align-items: end;
        gap: 22px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #edf0f4;
    }

    .passing-info {
        padding-bottom: 2px;
    }

    .passing-info strong {
        display: block;
        margin-bottom: 4px;
        color: #334155;
        font-size: 12px;
    }

    .passing-info span {
        color: #8a94a5;
        font-size: 11px;
        line-height: 1.5;
    }

    /*
    |--------------------------------------------------------------------------
    | Formula Box
    |--------------------------------------------------------------------------
    */

    .formula-box {
        display: grid;
        grid-template-columns:
            auto 1fr;
        gap: 12px;
        margin-top: 20px;
        padding: 15px;
        border: 1px solid #e7eaf0;
        border-radius: 11px;
        background: #fff;
    }

    .formula-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border-radius: 8px;
        background: #f1f5f9;
        font-size: 14px;
    }

    .formula-box strong {
        display: block;
        margin-bottom: 4px;
        color: #334155;
        font-size: 12px;
    }

    .formula-box p {
        margin: 0;
        color: #7b8494;
        font-size: 11px;
        line-height: 1.55;
    }

    .formula-code {
        display: inline-block;
        margin-top: 6px;
        padding: 5px 8px;
        border-radius: 6px;
        background: #f8fafc;
        color: #475569;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 10px;
        font-weight: 700;
    }

    /*
    |--------------------------------------------------------------------------
    | Footer Actions
    |--------------------------------------------------------------------------
    */

    .form-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        margin-top: 22px;
    }

    .save-info {
        color: #8a94a5;
        font-size: 11px;
        line-height: 1.5;
    }

    .save-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 210px;
        height: 46px;
        padding: 0 18px;
        border: 0;
        border-radius: 10px;
        background: var(--primary);
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: .18s ease;
    }

    .save-btn:hover {
        background: var(--primary-hover);
    }

    .save-btn:disabled {
        opacity: .65;
        cursor: not-allowed;
    }

    /*
    |--------------------------------------------------------------------------
    | Empty State
    |--------------------------------------------------------------------------
    */

    .empty-state {
        padding: 65px 25px;
        text-align: center;
    }

    .empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 58px;
        height: 58px;
        margin: 0 auto 15px;
        border-radius: 15px;
        background: #f1f5f9;
        font-size: 25px;
    }

    .empty-state h2 {
        margin: 0 0 7px;
        color: #273248;
        font-size: 18px;
    }

    .empty-state p {
        max-width: 470px;
        margin: 0 auto;
        color: #7b8494;
        font-size: 13px;
        line-height: 1.6;
    }

    /*
    |--------------------------------------------------------------------------
    | No Selection
    |--------------------------------------------------------------------------
    */

    .no-selection {
        margin-top: 20px;
        padding: 20px;
        border: 1px dashed #d8dee8;
        border-radius: 12px;
        background: #fafbfc;
        text-align: center;
    }

    .no-selection strong {
        display: block;
        margin-bottom: 5px;
        color: #475569;
        font-size: 13px;
    }

    .no-selection span {
        color: #8a94a5;
        font-size: 11px;
    }

    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 950px) {

        .assessment-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .selection-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .selection-grid .field:first-child {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 680px) {

        .page {
            width: min(100% - 22px, 1180px);
            padding-top: 22px;
        }

        .page-header {
            flex-direction: column;
        }

        .dashboard-btn {
            width: 100%;
            justify-content: center;
        }

        .selection-grid,
        .assessment-grid,
        .passing-section {
            grid-template-columns: 1fr;
        }

        .selection-grid .field:first-child {
            grid-column: auto;
        }

        .configuration-intro {
            grid-template-columns: 1fr;
        }

        .configuration-badge {
            width: fit-content;
        }

        .result-summary {
            grid-template-columns: 1fr;
        }

        .form-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .save-btn {
            width: 100%;
        }

        .card-header,
        .card-body {
            padding: 18px;
        }
    }

</style>

</head>

<body>

<div class="page">

    <!-- =========================================================
         PAGE HEADER
    ========================================================== -->

    <header class="page-header">

        <div class="header-left">

            <div class="eyebrow">
                ⚙ Teacher Portal
                <span>•</span>
                Academic Results
            </div>

            <h1>
                Result Configuration
            </h1>

            <p>
                Define how Attendance, Assignments, Midterm and
                Final Exam contribute to the final result for
                your assigned subject and class.
            </p>

        </div>

        <a
            href="dashboard.php"
            class="dashboard-btn"
        >
            ← Dashboard
        </a>

    </header>


    <!-- =========================================================
         FLASH MESSAGES
    ========================================================== -->

    <?php if ($success !== ''): ?>

        <div class="alert success">

            <span class="alert-icon">✓</span>

            <div>
                <?= e($success) ?>
            </div>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert error">

            <span class="alert-icon">!</span>

            <div>
                <?= e($error) ?>
            </div>

        </div>

    <?php endif; ?>


    <?php if (empty($teacherAssignments)): ?>

        <!-- =====================================================
             EMPTY STATE
        ====================================================== -->

        <div class="card">

            <div class="empty-state">

                <div class="empty-icon">
                    📚
                </div>

                <h2>
                    No Teaching Assignment Found
                </h2>

                <p>
                    You currently do not have a Subject, Class or
                    Section assigned to your teacher account.
                    Once an administrator assigns your teaching
                    workload, it will appear here.
                </p>

            </div>

        </div>

    <?php else: ?>


        <!-- =====================================================
             STEP 1 — SELECT ACADEMIC CONTEXT
        ====================================================== -->

        <div class="card">

            <div class="card-header">

                <span class="step">1</span>

                <h2>
                    Select Academic Context
                </h2>

                <p>
                    Choose the exact Subject, Class and Section
                    whose result structure you want to configure.
                </p>

            </div>

            <div class="card-body">

                <div class="selection-grid">

                    <!-- Subject -->

                    <div class="field">

                        <label for="subject_id">
                            Subject
                            <span class="required">*</span>
                        </label>

                        <select
                            id="subject_id"
                            class="control"
                        >

                            <option value="">
                                Select Subject
                            </option>

                            <?php foreach (
                                $subjectsForJs
                                as $subject
                            ): ?>

                                <option
                                    value="<?= (int) $subject['id'] ?>"
                                    <?= $selectedSubject ===
                                        (int) $subject['id']
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($subject['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="field-help">
                            Only subjects assigned to you are shown.
                        </div>

                    </div>


                    <!-- Class -->

                    <div class="field">

                        <label for="class_id">
                            Class
                            <span class="required">*</span>
                        </label>

                        <select
                            id="class_id"
                            class="control"
                        >

                            <option value="">
                                Select Class
                            </option>

                        </select>

                        <div class="field-help">
                            Classes are filtered by the selected subject.
                        </div>

                    </div>


                    <!-- Section -->

                    <div class="field">

                        <label for="section_id">
                            Section
                        </label>

                        <select
                            id="section_id"
                            class="control"
                        >

                            <option value="">
                                Whole Class
                            </option>

                        </select>

                        <div class="field-help">
                            Choose a specific section or configure the whole class.
                        </div>

                    </div>

                </div>


                <?php if (
                    $selectedSubject > 0 &&
                    $selectedClass > 0 &&
                    $selectedAssignment !== null
                ): ?>

                    <div class="selected-context">

                        <span class="context-label">
                            Configuring:
                        </span>

                        <span class="context-pill">
                            <?= e($subjectName) ?>
                        </span>

                        <span>
                            →
                        </span>

                        <span class="context-pill">
                            <?= e($className) ?>
                        </span>

                        <span>
                            →
                        </span>

                        <span class="context-pill">
                            <?= e($selectedSectionLabel) ?>
                        </span>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <?php if (
            $selectedSubject > 0 &&
            $selectedClass > 0 &&
            $selectedAssignment !== null
        ): ?>


            <!-- =================================================
                 STEP 2 — RESULT STRUCTURE
            ================================================== -->

            <form
                method="POST"
                id="settingsForm"
                novalidate
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="subject_id"
                    id="form_subject_id"
                    value="<?= (int) $selectedSubject ?>"
                >

                <input
                    type="hidden"
                    name="class_id"
                    id="form_class_id"
                    value="<?= (int) $selectedClass ?>"
                >

                <input
                    type="hidden"
                    name="section_id"
                    id="form_section_id"
                    value="<?= $selectedSection !== null
                        ? (int) $selectedSection
                        : '' ?>"
                >


                <div class="card">

                    <div class="card-header">

                        <span class="step">2</span>

                        <h2>
                            Result Structure
                        </h2>

                        <p>
                            Set the maximum marks that each assessment
                            contributes to the final result.
                        </p>

                    </div>


                    <div class="card-body">


                        <!-- Configuration Explanation -->

                        <div class="configuration-intro">

                            <div>

                                <strong>
                                    What do these marks mean?
                                </strong>

                                <p>
                                    The numbers below are
                                    <b>Maximum Contribution Marks</b>.
                                    They are not a student's obtained marks.
                                    Student obtained marks will come later
                                    from Assignment Grading, Exams and
                                    Attendance records.
                                </p>

                            </div>

                            <div class="configuration-badge">
                                ✓ Teacher Controlled
                            </div>

                        </div>


                        <!-- Assessment Cards -->

                        <div class="assessment-grid">


                            <!-- Attendance -->

                            <div class="assessment-card">

                                <div class="assessment-top">

                                    <div class="assessment-icon">
                                        📅
                                    </div>

                                    <div class="assessment-number">
                                        01
                                    </div>

                                </div>

                                <h3 class="assessment-title">
                                    Attendance
                                </h3>

                                <p class="assessment-description">
                                    Attendance percentage will be
                                    calculated automatically from
                                    attendance records.
                                </p>

                                <label
                                    class="marks-label"
                                    for="attendance_total"
                                >
                                    Maximum Contribution
                                </label>

                                <div class="marks-control-wrap">

                                    <input
                                        type="number"
                                        class="marks-control component-input"
                                        name="attendance_total"
                                        id="attendance_total"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="<?= e(
                                            $attendanceValue
                                        ) ?>"
                                        required
                                    >

                                    <span class="marks-unit">
                                        marks
                                    </span>

                                </div>

                                <div class="assessment-note">
                                    Example: 10 marks means 100% attendance
                                    can contribute up to 10 marks.
                                </div>

                            </div>


                            <!-- Assignments -->

                            <div class="assessment-card">

                                <div class="assessment-top">

                                    <div class="assessment-icon">
                                        📝
                                    </div>

                                    <div class="assessment-number">
                                        02
                                    </div>

                                </div>

                                <h3 class="assessment-title">
                                    Assignments
                                </h3>

                                <p class="assessment-description">
                                    Graded assignment submissions will
                                    automatically contribute to this
                                    component.
                                </p>

                                <label
                                    class="marks-label"
                                    for="assignment_total"
                                >
                                    Maximum Contribution
                                </label>

                                <div class="marks-control-wrap">

                                    <input
                                        type="number"
                                        class="marks-control component-input"
                                        name="assignment_total"
                                        id="assignment_total"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="<?= e(
                                            $assignmentValue
                                        ) ?>"
                                        required
                                    >

                                    <span class="marks-unit">
                                        marks
                                    </span>

                                </div>

                                <div class="assessment-note">
                                    Example: All graded assignments can
                                    contribute up to 20 marks.
                                </div>

                            </div>


                            <!-- Midterm -->

                            <div class="assessment-card">

                                <div class="assessment-top">

                                    <div class="assessment-icon">
                                        📚
                                    </div>

                                    <div class="assessment-number">
                                        03
                                    </div>

                                </div>

                                <h3 class="assessment-title">
                                    Midterm
                                </h3>

                                <p class="assessment-description">
                                    Midterm performance will be converted
                                    into this component's configured
                                    contribution.
                                </p>

                                <label
                                    class="marks-label"
                                    for="midterm_total"
                                >
                                    Maximum Contribution
                                </label>

                                <div class="marks-control-wrap">

                                    <input
                                        type="number"
                                        class="marks-control component-input"
                                        name="midterm_total"
                                        id="midterm_total"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="<?= e(
                                            $midtermValue
                                        ) ?>"
                                        required
                                    >

                                    <span class="marks-unit">
                                        marks
                                    </span>

                                </div>

                                <div class="assessment-note">
                                    Example: Midterm can contribute up to
                                    30 marks.
                                </div>

                            </div>


                            <!-- Final -->

                            <div class="assessment-card">

                                <div class="assessment-top">

                                    <div class="assessment-icon">
                                        🎓
                                    </div>

                                    <div class="assessment-number">
                                        04
                                    </div>

                                </div>

                                <h3 class="assessment-title">
                                    Final Exam
                                </h3>

                                <p class="assessment-description">
                                    Final examination performance will
                                    contribute according to this setting.
                                </p>

                                <label
                                    class="marks-label"
                                    for="final_total"
                                >
                                    Maximum Contribution
                                </label>

                                <div class="marks-control-wrap">

                                    <input
                                        type="number"
                                        class="marks-control component-input"
                                        name="final_total"
                                        id="final_total"
                                        min="0"
                                        step="0.01"
                                        inputmode="decimal"
                                        value="<?= e(
                                            $finalValue
                                        ) ?>"
                                        required
                                    >

                                    <span class="marks-unit">
                                        marks
                                    </span>

                                </div>

                                <div class="assessment-note">
                                    Example: Final exam can contribute up
                                    to 40 marks.
                                </div>

                            </div>

                        </div>


                        <!-- Total -->

                        <div class="result-summary">

                            <div>

                                <p class="summary-title">
                                    Total Configured Marks
                                </p>

                                <p class="summary-description">
                                    This is the combined maximum contribution
                                    of all four components. It is not required
                                    to equal 100.
                                </p>

                            </div>

                            <div
                                class="summary-value"
                                id="totalDisplay"
                            >
                                <?= e(
                                    $configuredTotalDisplay
                                ) ?>
                                marks
                            </div>

                        </div>


                        <!-- Passing Percentage -->

                        <div class="passing-section">

                            <div class="field">

                                <label
                                    for="passing_percentage"
                                >
                                    Passing Percentage
                                    <span class="required">*</span>
                                </label>

                                <input
                                    type="number"
                                    class="control"
                                    name="passing_percentage"
                                    id="passing_percentage"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    inputmode="decimal"
                                    value="<?= e(
                                        $passingValue
                                    ) ?>"
                                    required
                                >

                                <div class="field-help">
                                    Example: 40 means students need at least
                                    40% to be marked as Pass.
                                </div>

                            </div>


                            <div class="passing-info">

                                <strong>
                                    Pass / Fail Rule
                                </strong>

                                <span>
                                    The system will compare the student's
                                    final calculated percentage with this
                                    passing percentage.
                                </span>

                            </div>

                        </div>


                        <!-- Calculation Explanation -->

                        <div class="formula-box">

                            <div class="formula-icon">
                                ∑
                            </div>

                            <div>

                                <strong>
                                    How the final result will work
                                </strong>

                                <p>
                                    Each component produces an obtained
                                    percentage from its actual academic data.
                                    The system then converts that performance
                                    into the maximum contribution configured
                                    above.
                                </p>

                                <span class="formula-code">
                                    Final Percentage =
                                    Total Obtained Contribution ÷
                                    Total Configured Contribution × 100
                                </span>

                            </div>

                        </div>


                        <!-- Footer -->

                        <div class="form-footer">

                            <div class="save-info">

                                <?php if ($hasCurrentSetting): ?>

                                    ✓ Existing configuration loaded.
                                    Saving will update this configuration.

                                <?php else: ?>

                                    New configuration for this
                                    Subject / Class / Section.

                                <?php endif; ?>

                            </div>

                            <button
                                type="submit"
                                class="save-btn"
                                id="saveBtn"
                            >
                                <span>✓</span>
                                Save Result Configuration
                            </button>

                        </div>

                    </div>

                </div>

            </form>


        <?php else: ?>


            <!-- =================================================
                 NO CONFIGURATION SELECTED
            ================================================== -->

            <div class="card">

                <div class="no-selection">

                    <strong>
                        Select Subject and Class to Continue
                    </strong>

                    <span>
                        Choose your academic context above to configure
                        its result structure.
                    </span>

                </div>

            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Authorized Teacher Data
|--------------------------------------------------------------------------
*/

const authorizedData = <?= json_encode(
    $subjectsForJs,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;

const selectedSubject =
    <?= (int) $selectedSubject ?>;

const selectedClass =
    <?= (int) $selectedClass ?>;

const selectedSection =
    <?= $selectedSection === null
        ? 'null'
        : (int) $selectedSection ?>;


/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const subjectSelect =
    document.getElementById('subject_id');

const classSelect =
    document.getElementById('class_id');

const sectionSelect =
    document.getElementById('section_id');

const formSubject =
    document.getElementById('form_subject_id');

const formClass =
    document.getElementById('form_class_id');

const formSection =
    document.getElementById('form_section_id');

const settingsForm =
    document.getElementById('settingsForm');


/*
|--------------------------------------------------------------------------
| Find Subject
|--------------------------------------------------------------------------
*/

function getSubject(subjectId) {

    return authorizedData.find(function(subject) {

        return Number(subject.id) ===
            Number(subjectId);

    }) || null;
}


/*
|--------------------------------------------------------------------------
| Find Class
|--------------------------------------------------------------------------
*/

function getClass(subject, classId) {

    if (!subject) {
        return null;
    }

    return subject.classes.find(function(item) {

        return Number(item.id) ===
            Number(classId);

    }) || null;
}


/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/

function loadClasses(
    subjectId,
    preserveSelection = false
) {

    if (!classSelect) {
        return;
    }

    classSelect.innerHTML =
        '<option value="">Select Class</option>';

    if (sectionSelect) {

        sectionSelect.innerHTML =
            '<option value="">Whole Class</option>';
    }

    if (formClass) {
        formClass.value = '';
    }

    if (formSection) {
        formSection.value = '';
    }

    const subject =
        getSubject(subjectId);

    if (!subject) {
        return;
    }

    subject.classes.forEach(function(item) {

        const option =
            document.createElement('option');

        option.value = item.id;
        option.textContent = item.name;

        if (
            preserveSelection &&
            Number(item.id) ===
            Number(selectedClass)
        ) {

            option.selected = true;
        }

        classSelect.appendChild(option);
    });

    if (
        preserveSelection &&
        selectedClass > 0
    ) {

        loadSections(
            selectedClass,
            true
        );
    }
}


/*
|--------------------------------------------------------------------------
| Load Sections
|--------------------------------------------------------------------------
*/

function loadSections(
    classId,
    preserveSelection = false
) {

    if (!sectionSelect) {
        return;
    }

    sectionSelect.innerHTML =
        '<option value="">Whole Class</option>';

    if (formSection) {
        formSection.value = '';
    }

    const subject =
        getSubject(
            subjectSelect
                ? subjectSelect.value
                : ''
        );

    const classData =
        getClass(
            subject,
            classId
        );

    if (!classData) {
        return;
    }

    classData.sections.forEach(function(section) {

        const option =
            document.createElement('option');

        option.value = section.id;
        option.textContent = section.name;

        if (
            preserveSelection &&
            selectedSection !== null &&
            Number(section.id) ===
            Number(selectedSection)
        ) {

            option.selected = true;
        }

        sectionSelect.appendChild(option);
    });

    updateHiddenFields();
}


/*
|--------------------------------------------------------------------------
| Hidden Form Values
|--------------------------------------------------------------------------
*/

function updateHiddenFields() {

    if (formSubject && subjectSelect) {

        formSubject.value =
            subjectSelect.value || '';
    }

    if (formClass && classSelect) {

        formClass.value =
            classSelect.value || '';
    }

    if (formSection && sectionSelect) {

        formSection.value =
            sectionSelect.value || '';
    }
}


/*
|--------------------------------------------------------------------------
| Navigate To Configuration
|--------------------------------------------------------------------------
*/

function navigateToConfiguration() {

    if (
        !subjectSelect ||
        !classSelect
    ) {
        return;
    }

    if (
        !subjectSelect.value ||
        !classSelect.value
    ) {
        return;
    }

    const url =
        new URL(
            window.location.href
        );

    url.searchParams.set(
        'subject_id',
        subjectSelect.value
    );

    url.searchParams.set(
        'class_id',
        classSelect.value
    );

    if (
        sectionSelect &&
        sectionSelect.value
    ) {

        url.searchParams.set(
            'section_id',
            sectionSelect.value
        );

    } else {

        url.searchParams.delete(
            'section_id'
        );
    }

    window.location.href =
        url.toString();
}


/*
|--------------------------------------------------------------------------
| Subject Change
|--------------------------------------------------------------------------
*/

if (subjectSelect) {

    subjectSelect.addEventListener(
        'change',
        function() {

            const subject =
                getSubject(this.value);

            loadClasses(
                this.value,
                false
            );

            if (
                subject &&
                subject.classes.length > 0
            ) {

                classSelect.value =
                    subject.classes[0].id;

                loadSections(
                    subject.classes[0].id,
                    false
                );

                updateHiddenFields();

                navigateToConfiguration();

            } else {

                updateHiddenFields();
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| Class Change
|--------------------------------------------------------------------------
*/

if (classSelect) {

    classSelect.addEventListener(
        'change',
        function() {

            loadSections(
                this.value,
                false
            );

            updateHiddenFields();

            if (
                subjectSelect &&
                subjectSelect.value &&
                this.value
            ) {

                navigateToConfiguration();
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| Section Change
|--------------------------------------------------------------------------
*/

if (sectionSelect) {

    sectionSelect.addEventListener(
        'change',
        function() {

            updateHiddenFields();

            if (
                subjectSelect &&
                classSelect &&
                subjectSelect.value &&
                classSelect.value
            ) {

                navigateToConfiguration();
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| Total Configured Marks Calculator
|--------------------------------------------------------------------------
*/

const componentInputs =
    document.querySelectorAll(
        '.component-input'
    );

const totalDisplay =
    document.getElementById(
        'totalDisplay'
    );


function formatMarks(number) {

    if (
        Number.isInteger(number)
    ) {

        return String(number);
    }

    return number.toFixed(2)
        .replace(/\.00$/, '')
        .replace(/(\.\d)0$/, '$1');
}


function calculateConfiguredTotal() {

    if (!totalDisplay) {
        return;
    }

    let total = 0;

    componentInputs.forEach(
        function(input) {

            const value =
                parseFloat(input.value);

            if (
                Number.isFinite(value) &&
                value >= 0
            ) {

                total += value;
            }
        }
    );

    totalDisplay.textContent =
        formatMarks(total) +
        ' marks';
}


/*
|--------------------------------------------------------------------------
| Input Events
|--------------------------------------------------------------------------
*/

componentInputs.forEach(
    function(input) {

        input.addEventListener(
            'input',
            calculateConfiguredTotal
        );
    }
);


/*
|--------------------------------------------------------------------------
| Form Validation
|--------------------------------------------------------------------------
*/

if (settingsForm) {

    settingsForm.addEventListener(
        'submit',
        function(event) {

            updateHiddenFields();

            if (
                !formSubject ||
                !formClass ||
                !formSubject.value ||
                !formClass.value
            ) {

                event.preventDefault();

                alert(
                    'Please select Subject and Class.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Component Validation
            |--------------------------------------------------------------------------
            */

            for (
                const input of componentInputs
            ) {

                const value =
                    parseFloat(input.value);

                if (
                    !Number.isFinite(value) ||
                    value < 0
                ) {

                    event.preventDefault();

                    alert(
                        'Maximum contribution marks must be zero or greater.'
                    );

                    input.focus();

                    return;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | At Least One Component
            |--------------------------------------------------------------------------
            */

            let total = 0;

            componentInputs.forEach(
                function(input) {

                    const value =
                        parseFloat(input.value) || 0;

                    total += value;
                }
            );

            if (total <= 0) {

                event.preventDefault();

                alert(
                    'Please configure at least one assessment component with marks greater than 0.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Passing Percentage
            |--------------------------------------------------------------------------
            */

            const passingInput =
                document.getElementById(
                    'passing_percentage'
                );

            const passing =
                passingInput
                    ? parseFloat(
                        passingInput.value
                    )
                    : NaN;

            if (
                !Number.isFinite(passing) ||
                passing < 0 ||
                passing > 100
            ) {

                event.preventDefault();

                alert(
                    'Passing percentage must be between 0 and 100.'
                );

                if (passingInput) {
                    passingInput.focus();
                }

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent Double Submit
            |--------------------------------------------------------------------------
            */

            const saveBtn =
                document.getElementById(
                    'saveBtn'
                );

            if (saveBtn) {

                saveBtn.disabled = true;

                saveBtn.innerHTML =
                    '<span>✓</span> Saving...';
            }
        }
    );
}


/*
|--------------------------------------------------------------------------
| Initial Page State
|--------------------------------------------------------------------------
*/

if (
    subjectSelect &&
    selectedSubject > 0
) {

    loadClasses(
        selectedSubject,
        true
    );
}

calculateConfiguredTotal();

</script>

</body>

</html>