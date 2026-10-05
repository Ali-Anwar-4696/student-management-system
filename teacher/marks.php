<?php

require_once '../includes/init.php';
require_once '../includes/role_check.php';

requireTeacher();

require_once '../classes/Exam.php';
require_once '../classes/Mark.php';
require_once '../classes/TeacherClass.php';

$database = new Database();
$pdo = $database->connect();

$markManager = new Mark($pdo);

$teacherId = (int) ($_SESSION['teacher_profile_id'] ?? 0);
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($teacherId <= 0) {
    http_response_code(403);
    exit('Teacher profile not found.');
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function examTypeLabel(string $type): string
{
    return match (strtolower(trim($type))) {
        'monthly' => 'Monthly',
        'midterm', 'mid term', 'mid-term' => 'Mid Term',
        'final' => 'Final',
        'quiz' => 'Quiz',
        default => ucfirst($type)
    };
}

function normalizeExamType(string $type): string
{
    $type = strtolower(trim($type));

    return match ($type) {
        'mid term', 'mid-term' => 'midterm',
        default => $type
    };
}

function getGrade(float $obtained, float $total): string
{
    if ($total <= 0) {
        return '-';
    }

    $pct = ($obtained / $total) * 100;

    return match (true) {
        $pct >= 90 => 'A+',
        $pct >= 80 => 'A',
        $pct >= 70 => 'B',
        $pct >= 60 => 'C',
        $pct >= 50 => 'D',
        $pct >= 40 => 'E',
        default => 'F'
    };
}

function getGradeColor(string $grade): string
{
    return match ($grade) {
        'A+', 'A' => 'grade-a',
        'B' => 'grade-b',
        'C' => 'grade-c',
        'D', 'E' => 'grade-d',
        'F' => 'grade-f',
        default => 'grade-none'
    };
}

function getPassFail(float $obtained, float $total): string
{
    if ($total <= 0) {
        return '-';
    }

    $pct = ($obtained / $total) * 100;

    return $pct >= 40 ? 'Pass' : 'Fail';
}

function getPassFailClass(float $obtained, float $total): string
{
    if ($total <= 0) {
        return 'status-default';
    }

    $pct = ($obtained / $total) * 100;

    return $pct >= 40 ? 'status-pass' : 'status-fail';
}

/*
|--------------------------------------------------------------------------
| FETCH TEACHER ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        tc.class_id,
        c.name AS class_name,
        tc.section_id,
        s.name AS section_name,
        tc.subject_id,
        sub.name AS subject_name,
        sub.code AS subject_code
    FROM teacher_classes tc
    JOIN classes c ON c.id = tc.class_id
    LEFT JOIN sections s ON s.id = tc.section_id
    JOIN subjects sub ON sub.id = tc.subject_id
    WHERE tc.teacher_id = :teacher_id
    ORDER BY c.name, s.name, sub.name
");

$stmt->execute([
    ':teacher_id' => $teacherId
]);

$teacherAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allowedClasses = [];
$allowedSections = [];
$allowedSubjects = [];

foreach ($teacherAssignments as $row) {

    $cid = (int) $row['class_id'];
    $sid = $row['section_id'] !== null ? (int) $row['section_id'] : null;
    $subid = (int) $row['subject_id'];

    if (!isset($allowedClasses[$cid])) {
        $allowedClasses[$cid] = [
            'id' => $cid,
            'name' => $row['class_name']
        ];
    }

    if ($sid !== null && !isset($allowedSections[$sid])) {
        $allowedSections[$sid] = [
            'id' => $sid,
            'name' => $row['section_name'],
            'class_id' => $cid
        ];
    }

    if (!isset($allowedSubjects[$subid])) {
        $allowedSubjects[$subid] = [
            'id' => $subid,
            'name' => $row['subject_name'],
            'code' => $row['subject_code']
        ];
    }
}

/*
 * If the teacher has a Whole Class assignment (section_id IS NULL),
 * expose all sections belonging to that class in the section filter.
 * This keeps the dropdown useful while still enforcing authorization.
 */
$wholeClassIds = [];
foreach ($teacherAssignments as $assignment) {
    if ($assignment['section_id'] === null) {
        $wholeClassIds[(int) $assignment['class_id']] = true;
    }
}

if ($wholeClassIds !== []) {
    $placeholders = [];
    $params = [];
    foreach (array_keys($wholeClassIds) as $i => $classId) {
        $key = ':wc_class_' . $i;
        $placeholders[] = $key;
        $params[$key] = $classId;
    }

    $sectionStmt = $pdo->prepare(
        "SELECT id, name, class_id FROM sections
         WHERE class_id IN (" . implode(',', $placeholders) . ")
         ORDER BY name ASC"
    );
    $sectionStmt->execute($params);

    foreach ($sectionStmt->fetchAll(PDO::FETCH_ASSOC) as $sectionRow) {
        $sid = (int) $sectionRow['id'];
        $allowedSections[$sid] = [
            'id' => $sid,
            'name' => $sectionRow['name'],
            'class_id' => (int) $sectionRow['class_id']
        ];
    }
}

/*
|--------------------------------------------------------------------------
| CSRF PROTECTION
|--------------------------------------------------------------------------
|
| Uses the application-wide session token (includes/auth_check.php)
| so every form shares one central token.
|
*/

$marksCsrfToken = getCsrfToken();

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$classFilter = (
    isset($_GET['class_id']) &&
    ctype_digit((string) $_GET['class_id'])
)
    ? (int) $_GET['class_id']
    : '';

$sectionFilter = (
    isset($_GET['section_id']) &&
    ctype_digit((string) $_GET['section_id'])
)
    ? (int) $_GET['section_id']
    : '';

$subjectFilter = (
    isset($_GET['subject_id']) &&
    ctype_digit((string) $_GET['subject_id'])
)
    ? (int) $_GET['subject_id']
    : '';

$examFilter = (
    isset($_GET['exam_id']) &&
    ctype_digit((string) $_GET['exam_id'])
)
    ? (int) $_GET['exam_id']
    : '';

$search = trim((string) ($_GET['search'] ?? ''));

/*
|--------------------------------------------------------------------------
| VALIDATE TEACHER ASSIGNMENT
|--------------------------------------------------------------------------
*/

$validCombo = false;

if ($classFilter !== '' && $subjectFilter !== '') {

    foreach ($teacherAssignments as $row) {

        if (
            (int) $row['class_id'] === $classFilter &&
            (int) $row['subject_id'] === $subjectFilter &&
            (
                $sectionFilter === '' ||
                $row['section_id'] === null ||
                (int) $row['section_id'] === $sectionFilter
            )
        ) {
            $validCombo = true;
            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH EXAMS
|--------------------------------------------------------------------------
*/

$exams = [];

if ($validCombo) {

    $examStmt = $pdo->prepare("
        SELECT
            e.id,
            e.name,
            e.type,
            e.start_date,
            e.end_date,
            e.status
        FROM exams e
        WHERE e.class_id = :class_id
        ORDER BY e.start_date DESC, e.id DESC
    ");

    $examStmt->execute([
        ':class_id' => $classFilter
    ]);

    $exams = $examStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| SELECTED EXAM
|--------------------------------------------------------------------------
*/

$examInfo = null;

if ($validCombo && $examFilter !== '') {

    $examStmt = $pdo->prepare("
        SELECT *
        FROM exams
        WHERE id = :exam_id
          AND class_id = :class_id
        LIMIT 1
    ");

    $examStmt->execute([
        ':exam_id' => $examFilter,
        ':class_id' => $classFilter
    ]);

    $examInfo = $examStmt->fetch(PDO::FETCH_ASSOC);

    if (!$examInfo) {
        $examFilter = '';
    }
}

/*
|--------------------------------------------------------------------------
| RESULT SETTINGS
|
| Exact section setting has priority.
| If no exact section setting exists, Whole Class setting is used.
|--------------------------------------------------------------------------
*/

$resultSetting = null;

if (
    $validCombo &&
    $classFilter !== '' &&
    $subjectFilter !== ''
) {

    /*
     * First try exact section setting.
     */
    if ($sectionFilter !== '') {

        $settingStmt = $pdo->prepare("
            SELECT
                id,
                teacher_id,
                class_id,
                section_id,
                subject_id,
                assignment_total,
                attendance_total,
                midterm_total,
                final_total,
                passing_percentage,
                status
            FROM result_settings
            WHERE teacher_id = :teacher_id
              AND class_id = :class_id
              AND section_id = :section_id
              AND subject_id = :subject_id
              AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
        ");

        $settingStmt->execute([
            ':teacher_id' => $teacherId,
            ':class_id' => $classFilter,
            ':section_id' => $sectionFilter,
            ':subject_id' => $subjectFilter
        ]);

        $resultSetting = $settingStmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
     * If exact section setting doesn't exist,
     * use Whole Class setting.
     */
    if (!$resultSetting) {

        $settingStmt = $pdo->prepare("
            SELECT
                id,
                teacher_id,
                class_id,
                section_id,
                subject_id,
                assignment_total,
                attendance_total,
                midterm_total,
                final_total,
                passing_percentage,
                status
            FROM result_settings
            WHERE teacher_id = :teacher_id
              AND class_id = :class_id
              AND section_id IS NULL
              AND subject_id = :subject_id
              AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
        ");

        $settingStmt->execute([
            ':teacher_id' => $teacherId,
            ':class_id' => $classFilter,
            ':subject_id' => $subjectFilter
        ]);

        $resultSetting = $settingStmt->fetch(PDO::FETCH_ASSOC);
    }
}

/*
|--------------------------------------------------------------------------
| DETERMINE TOTAL MARKS
|
| Authoritative source: exam_subjects.total_marks
| This is the maximum marks for this specific exam subject.
| Result settings determine WEIGHT/CONTRIBUTION to final result,
| not the actual exam's maximum marks.
|--------------------------------------------------------------------------
*/

$configuredTotal = null;
$configuredComponent = '';
$examSubjectConfigured = false;

$selectedExamType = '';

if ($examInfo) {

    $selectedExamType = normalizeExamType(
        (string) ($examInfo['type'] ?? '')
    );

    // Get authoritative total marks from exam_subjects
    $examSubjectStmt = $pdo->prepare("
        SELECT total_marks, pass_marks
        FROM exam_subjects
        WHERE exam_id = :exam_id AND subject_id = :subject_id
        LIMIT 1
    ");
    $examSubjectStmt->execute([
        ':exam_id' => $examFilter,
        ':subject_id' => $subjectFilter
    ]);
    $examSubject = $examSubjectStmt->fetch(PDO::FETCH_ASSOC);

    $examSubjectConfigured = $examSubject !== false;

    if ($examSubject) {
        $configuredTotal = (float) $examSubject['total_marks'];
        $configuredComponent = $examSubject['pass_marks'] !== null
            ? (float) $examSubject['pass_marks']
            : 0;
    }

    /*
     * exam_subjects is the ONLY authoritative source of the maximum marks.
     *
     * If no row exists, the admin has not configured this subject on this
     * exam yet. $configuredTotal stays null so the entry is disabled below
     * (total = 0) instead of guessing a wrong maximum such as 100.
     */
}

/*
|--------------------------------------------------------------------------
| FETCH STUDENTS + MARKS
|--------------------------------------------------------------------------
*/

$students = [];

$totalStudents = 0;
$marksEntered = 0;
$marksPending = 0;
$averageMarks = 0;

if (
    $validCombo &&
    $examFilter !== '' &&
    $examInfo
) {

    $params = [
        ':class_id' => $classFilter,
        ':subject_id' => $subjectFilter,
        ':exam_id' => $examFilter
    ];

    $sectionCondition = '';

    if ($sectionFilter !== '') {

        $sectionCondition = "
            AND s.section_id = :section_id
        ";

        $params[':section_id'] = $sectionFilter;
    }

    $searchCondition = '';

    if ($search !== '') {

        $searchCondition = "
            AND (
                s.name LIKE :search
                OR s.student_id LIKE :search
                OR s.father_name LIKE :search
            )
        ";

        $params[':search'] = '%' . $search . '%';
    }

    /*
     * IMPORTANT:
     * We read existing marks without requiring them to match
     * the current result setting total.
     *
     * This allows old marks to remain visible.
     */
    $sql = "
        SELECT
            s.id AS student_pk,
            s.student_id,
            s.name AS student_name,
            s.father_name,
            s.phone,

            m.id AS mark_id,
            m.total_marks,
            m.obtained_marks,

            CASE
                WHEN m.id IS NOT NULL THEN 'entered'
                ELSE 'pending'
            END AS mark_status

        FROM students s

        LEFT JOIN marks m
            ON m.student_id = s.id
            AND m.exam_id = :exam_id
            AND m.subject_id = :subject_id

        WHERE s.class_id = :class_id
            AND s.status = 'active'
            {$sectionCondition}
            {$searchCondition}

        ORDER BY s.name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStudents = count($students);

    $totalObtained = 0;
    $enteredCount = 0;

    foreach ($students as $student) {

        if ($student['mark_status'] === 'entered') {

            $marksEntered++;

            $totalObtained += (float) $student['obtained_marks'];

            $enteredCount++;
        }
    }

    $marksPending = $totalStudents - $marksEntered;

    $averageMarks = $enteredCount > 0
        ? round($totalObtained / $enteredCount, 2)
        : 0;

    /*
     * exam_subjects is authoritative. When it is missing there is no
     * trustworthy maximum, so the component is disabled (total = 0)
     * instead of falling back to an invented number.
     */
    if ($configuredTotal === null) {
        $configuredTotal = 0;
    }

    /*
     * If configured total is zero, this component is disabled.
     */
    $configuredTotal = max(0, (float) $configuredTotal);
}

/*
|--------------------------------------------------------------------------
| HANDLE MARKS SAVE
|--------------------------------------------------------------------------
*/

$saveMessage = '';
$saveStatus = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_marks'])
) {

    try {

        $pdo->beginTransaction();

        $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
        if (
            $postedCsrf === '' ||
            !validateCsrfToken($postedCsrf)
        ) {
            throw new Exception('Invalid security token. Please refresh the page and try again.');
        }

        $examIdPost = (int) ($_POST['exam_id'] ?? 0);
        $subjectIdPost = (int) ($_POST['subject_id'] ?? 0);
        $classIdPost = (int) ($_POST['class_id'] ?? 0);
        $sectionIdPost = (
            isset($_POST['section_id']) &&
            ctype_digit((string) $_POST['section_id'])
        )
            ? (int) $_POST['section_id']
            : null;

        if ($examIdPost <= 0) {
            throw new Exception('Invalid exam selected.');
        }

        if ($subjectIdPost <= 0) {
            throw new Exception('Invalid subject selected.');
        }

        if ($classIdPost <= 0) {
            throw new Exception('Invalid class selected.');
        }

        /*
         * Verify teacher owns the exact assignment.
         */
        $verifySql = "
            SELECT 1
            FROM teacher_classes
            WHERE teacher_id = :teacher_id
              AND class_id = :class_id
              AND subject_id = :subject_id
        ";

        $verifyParams = [
            ':teacher_id' => $teacherId,
            ':class_id' => $classIdPost,
            ':subject_id' => $subjectIdPost
        ];

        if ($sectionIdPost !== null) {

            /*
             * An exact section assignment OR a Whole Class assignment
             * authorizes marks for the selected section.
             */
            $verifySql .= "
                AND (section_id = :section_id OR section_id IS NULL)
            ";

            $verifyParams[':section_id'] = $sectionIdPost;

        }
        /*
         * With no section selected, the class/subject assignment itself
         * is enough to open the list. Student filtering below controls
         * which students are displayed.
         */

        $verifySql .= " LIMIT 1";

        $verifyStmt = $pdo->prepare($verifySql);
        $verifyStmt->execute($verifyParams);

        if (!$verifyStmt->fetch()) {
            throw new Exception(
                'Unauthorized to enter marks for this class/subject.'
            );
        }

        /*
         * Verify exam belongs to selected class.
         */
        $examVerifyStmt = $pdo->prepare("
            SELECT *
            FROM exams
            WHERE id = :exam_id
              AND class_id = :class_id
            LIMIT 1
        ");

        $examVerifyStmt->execute([
            ':exam_id' => $examIdPost,
            ':class_id' => $classIdPost
        ]);

        $postExam = $examVerifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$postExam) {
            throw new Exception('Selected exam does not belong to this class.');
        }

        $postExamType = normalizeExamType(
            (string) ($postExam['type'] ?? '')
        );

        /*
         * Authoritative total marks from exam_subjects during SAVE.
         *
         * Never trust the total coming from hidden form fields.
         * exam_subjects.total_marks is the authoritative source, and it is
         * also the source used by the student result service. When the exam
         * subject is not configured there is no trustworthy maximum, so the
         * save is refused instead of storing an invented value such as 100.
         */
        $saveTotal = $markManager->getExamSubjectTotal($examIdPost, $subjectIdPost);

        if ($saveTotal === null) {
            throw new Exception(
                'This subject is not configured for the selected exam. '
                . 'Ask the administrator to add this subject to the exam '
                . 'before entering marks.'
            );
        }

        $saveTotal = max(0, (float) $saveTotal);

        /*
         * Prevent entering marks with invalid total.
         */
        if ($saveTotal <= 0) {
            throw new Exception(
                'Invalid total marks configuration for this exam subject.'
            );
        }

        $postedMarks = [];

        if (!empty($_POST['marks']) && is_array($_POST['marks'])) {
            $postedMarks = $_POST['marks'];
        }

        /*
         * The mobile inputs are always present in the DOM and are therefore
         * always submitted, usually as empty strings. Only merge a mobile
         * value that actually carries marks: an empty mobile field must
         * never erase what the teacher typed in the desktop table.
         */
        if (!empty($_POST['mobile_marks']) && is_array($_POST['mobile_marks'])) {
            foreach ($_POST['mobile_marks'] as $studentPk => $mobileData) {
                if (!is_array($mobileData)) {
                    continue;
                }

                if (trim((string) ($mobileData['obtained'] ?? '')) === '') {
                    continue;
                }

                $postedMarks[$studentPk] = $mobileData;
            }
        }

        $savedCount = 0;

        if ($postedMarks !== []) {

            /*
             * Server-side authorization for EVERY posted student.
             *
             * The student must be active and belong to the posted class
             * (and to the posted section when one was selected), AND the
             * student's own section must be covered by this teacher's
             * assignment (exact section or whole-class assignment).
             *
             * This closes the gap where omitting section_id skipped the
             * section check entirely and let a Section A teacher write
             * marks for students of Section B.
             */
            $studentCheckSql = "
                SELECT st.id
                FROM students st
                WHERE st.id = :student_id
                  AND st.class_id = :class_id
                  AND st.status = 'active'
            ";

            if ($sectionIdPost !== null) {
                $studentCheckSql .= "
                    AND st.section_id = :section_id
                ";
            }

            $studentCheckSql .= "
                  AND EXISTS (
                        SELECT 1
                        FROM teacher_classes tc
                        WHERE tc.teacher_id = :teacher_id
                          AND tc.class_id = st.class_id
                          AND tc.subject_id = :subject_id
                          AND (
                                tc.section_id IS NULL
                                OR tc.section_id = st.section_id
                          )
                  )
                LIMIT 1
            ";

            $studentCheckStmt = $pdo->prepare($studentCheckSql);

            foreach ($postedMarks as $studentPk => $markData) {

                if (!ctype_digit((string) $studentPk)) {
                    continue;
                }

                $studentIdPost = (int) $studentPk;

                $studentParams = [
                    ':student_id' => $studentIdPost,
                    ':class_id' => $classIdPost,
                    ':teacher_id' => $teacherId,
                    ':subject_id' => $subjectIdPost
                ];

                if ($sectionIdPost !== null) {
                    $studentParams[':section_id'] = $sectionIdPost;
                }

                $studentCheckStmt->execute($studentParams);

                if (!$studentCheckStmt->fetch()) {
                    throw new Exception(
                        'Unauthorized to enter marks for one or more selected students.'
                    );
                }

                $obtainedRaw = trim(
                    (string) ($markData['obtained'] ?? '')
                );

                /*
                 * Empty input means no change for that student.
                 */
                if ($obtainedRaw === '') {
                    continue;
                }

                if (!is_numeric($obtainedRaw)) {
                    throw new Exception(
                        'Marks must contain valid numeric values.'
                    );
                }

                $obtainedVal = (float) $obtainedRaw;

                if ($obtainedVal < 0) {
                    throw new Exception(
                        'Obtained marks cannot be negative.'
                    );
                }

                if ($obtainedVal > $saveTotal) {
                    throw new Exception(
                        'Obtained marks cannot be greater than the configured total marks of '
                        . rtrim(rtrim(number_format($saveTotal, 2, '.', ''), '0'), '.')
                        . '.'
                    );
                }

                /*
                 * Written through the canonical Mark::save() pipeline —
                 * the exact same writer the admin marks interface uses.
                 * The unique key on (exam_id, student_id, subject_id)
                 * makes this an update, never a duplicate row.
                 */
                $saved = $markManager->save(
                    $examIdPost,
                    $studentIdPost,
                    $subjectIdPost,
                    $saveTotal,
                    $obtainedVal,
                    $userId
                );

                if (!$saved) {
                    throw new Exception(
                        'Marks could not be saved for one of the selected students.'
                    );
                }

                $savedCount++;
            }
        }

        $pdo->commit();

        /*
         * Never claim success when nothing was actually written.
         */
        if ($savedCount > 0) {
            $saveMessage = 'Marks saved successfully.';
            $saveStatus = 'success';
            $savedFlag = 1;
        } else {
            $saveMessage = 'No marks were entered, so nothing was saved.';
            $saveStatus = 'warning';
            $savedFlag = 0;
        }

        /*
         * Preserve all filters after saving.
         */
        $query = http_build_query([
            'class_id' => $classIdPost,
            'section_id' => $sectionIdPost,
            'subject_id' => $subjectIdPost,
            'exam_id' => $examIdPost,
            'search' => $search,
            'saved' => $savedFlag
        ]);

        header(
            'Location: ' .
            $_SERVER['PHP_SELF'] .
            '?' .
            $query
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $saveMessage = 'Error: ' . $e->getMessage();
        $saveStatus = 'error';
    }
}

if (isset($_GET['saved'])) {

    if ((string) $_GET['saved'] === '0') {
        $saveMessage = 'No marks were entered, so nothing was saved.';
        $saveStatus = 'warning';
    } else {
        $saveMessage = 'Marks saved successfully.';
        $saveStatus = 'success';
    }
}

/*
|--------------------------------------------------------------------------
| DISPLAY TOTAL
|--------------------------------------------------------------------------
*/

$displayTotal = $configuredTotal !== null
    ? (float) $configuredTotal
    : 0;

$displayTotalFormatted = rtrim(
    rtrim(
        number_format($displayTotal, 2, '.', ''),
        '0'
    ),
    '.'
);

$isConfiguredComponent =
    $examInfo &&
    in_array(
        normalizeExamType((string) ($examInfo['type'] ?? '')),
        ['midterm', 'final'],
        true
    );

$hasResultSetting = !empty($resultSetting);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=5.0"
    >

    <title>Teacher - Marks Entry</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #e0e7ff;
            --primary-soft: #eef2ff;

            --success: #10b981;
            --success-soft: #d1fae5;

            --warning: #f59e0b;
            --warning-soft: #fef3c7;

            --danger: #ef4444;
            --danger-soft: #fee2e2;

            --info: #0ea5e9;
            --info-soft: #e0f2fe;

            --gray-900: #111827;
            --gray-800: #1f2937;
            --gray-700: #374151;
            --gray-600: #4b5563;
            --gray-500: #6b7280;
            --gray-400: #9ca3af;
            --gray-300: #d1d5db;
            --gray-200: #e5e7eb;
            --gray-100: #f3f4f6;
            --gray-50: #f9fafb;

            --white: #ffffff;

            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 16px;

            --shadow-sm:
                0 1px 2px 0 rgb(0 0 0 / 0.05);

            --shadow:
                0 1px 3px 0 rgb(0 0 0 / 0.1),
                0 1px 2px -1px rgb(0 0 0 / 0.1);

            --shadow-md:
                0 4px 6px -1px rgb(0 0 0 / 0.1),
                0 2px 4px -2px rgb(0 0 0 / 0.1);

            --shadow-lg:
                0 10px 15px -3px rgb(0 0 0 / 0.1),
                0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family:
                'Inter',
                system-ui,
                -apple-system,
                sans-serif;

            background: var(--gray-50);
            color: var(--gray-800);

            line-height: 1.5;

            -webkit-font-smoothing: antialiased;
        }

        .page-wrapper {
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Alert */

        .alert {
            padding: 14px 18px;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 12px;
        }

        .alert-success {
            background: var(--success-soft);
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: var(--danger-soft);
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            line-height: 1;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* Header */

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 20px;
            margin-bottom: 24px;

            flex-wrap: wrap;
        }

        .page-header-left {
            flex: 1;
            min-width: 0;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;

            font-size: 12px;
            color: var(--gray-500);

            margin-bottom: 8px;

            font-weight: 500;
        }

        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb-sep {
            color: var(--gray-400);
        }

        .breadcrumb-current {
            color: var(--primary);
            font-weight: 600;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;

            color: var(--gray-900);

            letter-spacing: -0.025em;
            line-height: 1.2;
        }

        .page-desc {
            margin-top: 6px;

            color: var(--gray-500);
            font-size: 14px;

            max-width: 650px;
        }

        .teacher-pill {
            display: flex;
            align-items: center;
            gap: 10px;

            padding: 10px 16px;

            background: var(--white);

            border: 1px solid var(--gray-200);
            border-radius: 999px;

            box-shadow: var(--shadow-sm);

            flex-shrink: 0;
        }

        .teacher-pill-avatar {
            width: 36px;
            height: 36px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: var(--white);

            display: grid;
            place-items: center;

            font-size: 13px;
            font-weight: 700;

            flex-shrink: 0;
        }

        .teacher-pill-name {
            font-size: 13px;
            font-weight: 700;

            color: var(--gray-800);
        }

        .teacher-pill-meta {
            font-size: 12px;
            color: var(--gray-500);
        }

        /* Stats */

        .stats-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--white);

            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);

            padding: 20px;

            position: relative;
            overflow: hidden;

            transition:
                transform .2s,
                box-shadow .2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card::before {
            content: '';

            position: absolute;

            top: 0;
            left: 0;
            right: 0;

            height: 3px;

            background: var(--primary);

            opacity: 0;

            transition: opacity .2s;
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card.entered::before {
            background: var(--success);
        }

        .stat-card.pending::before {
            background: var(--warning);
        }

        .stat-card.avg::before {
            background: var(--info);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 12px;
        }

        .stat-icon-wrap {
            width: 40px;
            height: 40px;

            border-radius: var(--radius);

            display: grid;
            place-items: center;

            font-size: 18px;

            background: var(--primary-soft);
            color: var(--primary);
        }

        .stat-card.entered .stat-icon-wrap {
            background: var(--success-soft);
            color: #047857;
        }

        .stat-card.pending .stat-icon-wrap {
            background: var(--warning-soft);
            color: #b45309;
        }

        .stat-card.avg .stat-icon-wrap {
            background: var(--info-soft);
            color: #0369a1;
        }

        .stat-badge {
            font-size: 11px;
            font-weight: 600;

            padding: 3px 8px;

            border-radius: 999px;

            background: var(--gray-100);
            color: var(--gray-500);
        }

        .stat-value {
            font-size: 30px;
            font-weight: 800;

            color: var(--gray-900);

            line-height: 1;

            letter-spacing: -0.02em;
        }

        .stat-label {
            margin-top: 6px;

            font-size: 13px;

            color: var(--gray-500);

            font-weight: 500;
        }

        /* Filters */

        .filter-bar {
            background: var(--white);

            border: 1px solid var(--gray-200);
            border-radius: var(--radius-lg);

            padding: 16px 20px;

            margin-bottom: 24px;

            box-shadow: var(--shadow-sm);
        }

        .filter-form {
            display: flex;
            align-items: flex-end;

            gap: 12px;

            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;

            gap: 6px;

            min-width: 0;

            flex: 1;
        }

        .filter-group.search {
            flex: 2;
            min-width: 220px;
        }

        .filter-group.select {
            flex: 1;
            min-width: 170px;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;

            color: var(--gray-700);
        }

        .filter-input-wrap {
            position: relative;
        }

        .filter-input-icon {
            position: absolute;

            left: 12px;
            top: 50%;

            transform: translateY(-50%);

            color: var(--gray-400);

            font-size: 14px;

            pointer-events: none;
        }

        .form-control {
            width: 100%;

            height: 44px;

            border: 1px solid var(--gray-300);

            border-radius: var(--radius);

            padding: 0 12px;

            font-size: 14px;

            color: var(--gray-800);

            background: var(--white);

            outline: none;

            transition:
                border-color .15s,
                box-shadow .15s;

            appearance: none;
            -webkit-appearance: none;
        }

        .form-control:focus {
            border-color: var(--primary);

            box-shadow:
                0 0 0 3px var(--primary-soft);
        }

        .form-control.search {
            padding-left: 38px;
        }

        select.form-control {
            padding-right: 32px;

            background-image:
                url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");

            background-repeat: no-repeat;

            background-position:
                right 12px center;

            cursor: pointer;
        }

        .filter-actions {
            display: flex;

            gap: 8px;

            flex-shrink: 0;
        }

        /* Buttons */

        .btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 6px;

            height: 44px;

            padding: 0 18px;

            border-radius: var(--radius);

            border: 1px solid transparent;

            font-size: 13px;
            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

            white-space: nowrap;

            transition: all .15s;

            touch-action: manipulation;
        }

        .btn-primary {
            background:
                linear-gradient(
                    135deg,
                    var(--primary),
                    var(--primary-dark)
                );

            color: var(--white);

            box-shadow:
                0 4px 12px
                rgba(99, 102, 241, .25);
        }

        .btn-primary:hover {
            transform: translateY(-1px);

            box-shadow:
                0 6px 16px
                rgba(99, 102, 241, .35);
        }

        .btn-secondary {
            background: var(--white);

            color: var(--gray-700);

            border-color: var(--gray-300);
        }

        .btn-secondary:hover {
            background: var(--gray-50);

            border-color: var(--gray-400);
        }

        .btn-success {
            background:
                linear-gradient(
                    135deg,
                    #34d399,
                    var(--success)
                );

            color: var(--white);

            box-shadow:
                0 4px 12px
                rgba(16, 185, 129, .25);
        }

        .btn-success:hover {
            transform: translateY(-1px);

            box-shadow:
                0 6px 16px
                rgba(16, 185, 129, .35);
        }

        .btn-sm {
            height: 36px;

            padding: 0 14px;

            font-size: 12px;
        }

        /* Result Settings Info */

        .settings-info {
            margin: 0 24px 16px;

            border: 1px solid var(--primary-light);

            background: var(--primary-soft);

            border-radius: var(--radius);

            padding: 14px 16px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 16px;

            flex-wrap: wrap;
        }

        .settings-info-left {
            display: flex;

            align-items: center;

            gap: 12px;
        }

        .settings-icon {
            width: 40px;
            height: 40px;

            border-radius: 10px;

            background: var(--white);

            display: grid;
            place-items: center;

            color: var(--primary);

            font-size: 18px;

            flex-shrink: 0;
        }

        .settings-title {
            font-size: 13px;
            font-weight: 800;

            color: var(--gray-900);
        }

        .settings-description {
            margin-top: 2px;

            font-size: 12px;

            color: var(--gray-600);
        }

        .total-chip {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 8px 13px;

            border-radius: 999px;

            background: var(--white);

            border: 1px solid var(--primary-light);

            color: var(--primary-dark);

            font-size: 13px;

            font-weight: 800;

            white-space: nowrap;
        }

        .total-chip strong {
            font-size: 16px;
        }

        .warning-info {
            margin: 0 24px 16px;

            border: 1px solid #fde68a;

            background: #fffbeb;

            color: #92400e;

            border-radius: var(--radius);

            padding: 13px 16px;

            font-size: 13px;

            display: flex;

            align-items: center;

            gap: 10px;
        }

        /* Content Card */

        .content-card {
            background: var(--white);

            border: 1px solid var(--gray-200);

            border-radius: var(--radius-lg);

            box-shadow: var(--shadow-sm);

            overflow: hidden;
        }

        .content-card-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 16px;

            padding: 18px 24px;

            border-bottom: 1px solid var(--gray-200);

            flex-wrap: wrap;
        }

        .content-card-title {
            display: flex;

            align-items: center;

            gap: 12px;
        }

        .content-card-icon {
            width: 40px;
            height: 40px;

            border-radius: var(--radius);

            background: var(--primary-soft);

            color: var(--primary);

            display: grid;
            place-items: center;

            font-size: 16px;

            font-weight: 700;

            flex-shrink: 0;
        }

        .content-card-title h2 {
            font-size: 16px;
            font-weight: 700;

            color: var(--gray-900);
        }

        .content-card-title p {
            font-size: 13px;

            color: var(--gray-500);

            margin-top: 1px;
        }

        .result-badge {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 5px 12px;

            border-radius: 999px;

            background: var(--primary-soft);

            color: var(--primary-dark);

            font-size: 12px;

            font-weight: 700;

            flex-shrink: 0;
        }

        /* Marks */

        .marks-form {
            margin: 0;
        }

        .marks-toolbar {
            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 16px;

            padding: 16px 24px;

            border-bottom: 1px solid var(--gray-200);

            background: var(--gray-50);

            flex-wrap: wrap;
        }

        .toolbar-info {
            display: flex;

            align-items: center;

            flex-wrap: wrap;

            gap: 6px;

            font-size: 14px;

            color: var(--gray-600);

            font-weight: 500;
        }

        .toolbar-info strong {
            color: var(--gray-900);
        }

        .toolbar-total {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            margin-left: 8px;

            padding: 7px 12px;

            border-radius: 8px;

            background: var(--white);

            border: 1px solid var(--gray-200);

            color: var(--gray-700);

            font-weight: 700;
        }

        .toolbar-total strong {
            color: var(--primary-dark);
            font-size: 16px;
        }

        /* Table */

        .table-responsive {
            width: 100%;

            overflow-x: auto;

            -webkit-overflow-scrolling: touch;
        }

        .table-responsive::-webkit-scrollbar {
            height: 6px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: transparent;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--gray-300);

            border-radius: 3px;
        }

        .data-table {
            width: 100%;

            min-width: 900px;

            border-collapse: collapse;

            font-size: 14px;
        }

        .data-table thead th {
            padding: 14px 20px;

            text-align: left;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 0.04em;

            color: var(--gray-500);

            background: var(--gray-50);

            border-bottom: 1px solid var(--gray-200);

            white-space: nowrap;
        }

        .data-table tbody td {
            padding: 14px 20px;

            border-bottom: 1px solid var(--gray-100);

            color: var(--gray-700);

            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background .12s;
        }

        .data-table tbody tr:hover {
            background: #fafbff;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Student */

        .student-cell {
            display: flex;

            align-items: center;

            gap: 12px;

            min-width: 0;
        }

        .student-avatar {
            width: 40px;
            height: 40px;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #c7d2fe,
                    #a5b4fc
                );

            color: var(--primary-dark);

            display: grid;
            place-items: center;

            font-size: 14px;
            font-weight: 700;

            flex-shrink: 0;
        }

        .student-info {
            min-width: 0;
        }

        .student-name {
            font-weight: 700;

            color: var(--gray-900);

            font-size: 14px;

            word-break: break-word;
        }

        .student-meta {
            font-size: 12px;

            color: var(--gray-500);

            margin-top: 2px;
        }

        /* Mark */

        .mark-input-wrap {
            display: flex;

            align-items: center;

            gap: 8px;
        }

        .mark-input {
            width: 90px;

            height: 40px;

            border: 1px solid var(--gray-300);

            border-radius: var(--radius-sm);

            padding: 0 10px;

            font-size: 14px;

            font-weight: 600;

            color: var(--gray-900);

            text-align: center;

            outline: none;

            transition:
                border-color .15s,
                box-shadow .15s;
        }

        .mark-input:focus {
            border-color: var(--primary);

            box-shadow:
                0 0 0 3px var(--primary-soft);
        }

        .mark-input:disabled {
            background: var(--gray-100);

            color: var(--gray-400);

            cursor: not-allowed;
        }

        .mark-total {
            font-size: 13px;

            color: var(--gray-500);

            font-weight: 500;

            white-space: nowrap;
        }

        .mark-status {
            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 4px 10px;

            border-radius: 999px;

            font-size: 11px;

            font-weight: 700;

            white-space: nowrap;
        }

        .status-entered {
            background: var(--success-soft);
            color: #065f46;
        }

        .status-pending {
            background: var(--warning-soft);
            color: #92400e;
        }

        .status-pass {
            background: var(--success-soft);
            color: #065f46;
        }

        .status-fail {
            background: var(--danger-soft);
            color: #991b1b;
        }

        .status-default {
            background: var(--gray-100);
            color: var(--gray-600);
        }

        /* Grade */

        .grade {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            min-width: 36px;

            height: 28px;

            padding: 0 8px;

            border-radius: 6px;

            font-size: 12px;

            font-weight: 800;
        }

        .grade-a {
            background: var(--success-soft);
            color: #065f46;
        }

        .grade-b {
            background: #dbeafe;
            color: #1e40af;
        }

        .grade-c {
            background: #fef9c3;
            color: #854d0e;
        }

        .grade-d {
            background: #ffedd5;
            color: #9a3412;
        }

        .grade-f {
            background: var(--danger-soft);
            color: #991b1b;
        }

        .grade-none {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        /* Mobile */

        .mobile-cards {
            display: none;

            padding: 16px;
        }

        .m-card {
            background: var(--white);

            border: 1px solid var(--gray-200);

            border-radius: var(--radius-lg);

            padding: 16px;

            margin-bottom: 12px;
        }

        .m-card:last-child {
            margin-bottom: 0;
        }

        .m-card-header {
            display: flex;

            align-items: flex-start;

            justify-content: space-between;

            gap: 12px;

            margin-bottom: 14px;
        }

        .m-card-body {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 12px;

            padding-top: 14px;

            border-top: 1px solid var(--gray-100);
        }

        .m-field {
            display: flex;

            flex-direction: column;

            gap: 6px;
        }

        .m-field-label {
            font-size: 11px;

            color: var(--gray-500);

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .03em;
        }

        .m-field-value {
            font-size: 14px;

            color: var(--gray-800);

            font-weight: 600;
        }

        /* Empty */

        .empty-state {
            text-align: center;

            padding: 64px 24px;
        }

        .empty-icon {
            width: 72px;
            height: 72px;

            border-radius: var(--radius-lg);

            background: var(--primary-soft);

            color: var(--primary);

            display: grid;

            place-items: center;

            font-size: 28px;

            margin: 0 auto 20px;
        }

        .empty-state h3 {
            font-size: 18px;

            font-weight: 700;

            color: var(--gray-900);
        }

        .empty-state p {
            color: var(--gray-500);

            font-size: 14px;

            margin-top: 8px;

            max-width: 500px;

            margin-left: auto;
            margin-right: auto;

            line-height: 1.6;
        }

        /* Save */

        .save-bar {
            position: sticky;

            bottom: 0;

            left: 0;
            right: 0;

            background: var(--white);

            border-top: 1px solid var(--gray-200);

            padding: 16px 24px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 16px;

            box-shadow:
                0 -4px 20px
                rgba(0,0,0,.06);

            z-index: 100;
        }

        .save-bar-info {
            font-size: 14px;

            color: var(--gray-600);
        }

        .save-bar-info strong {
            color: var(--gray-900);
        }

        /* Responsive */

        @media (max-width: 1199px) {

            .stats-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }

        @media (max-width: 991px) {

            .page-wrapper {
                padding: 20px;
            }

            .page-title {
                font-size: 24px;
            }

            .filter-form {
                flex-wrap: wrap;
            }

            .filter-group.search,
            .filter-group.select {
                flex: 1 1 calc(50% - 6px);

                min-width: 200px;
            }

            .filter-actions {
                width: 100%;

                justify-content: flex-end;
            }
        }

        @media (max-width: 767px) {

            .page-wrapper {
                padding: 16px;
            }

            .page-header {
                margin-bottom: 20px;
            }

            .page-title {
                font-size: 22px;
            }

            .page-desc {
                font-size: 13px;
            }

            .teacher-pill {
                width: 100%;

                justify-content: flex-start;
            }

            .stats-grid {
                grid-template-columns:
                    repeat(2, 1fr);

                gap: 12px;

                margin-bottom: 20px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 26px;
            }

            .stat-label {
                font-size: 12px;
            }

            .filter-bar {
                padding: 14px 16px;
            }

            .filter-group.search,
            .filter-group.select {
                flex: 1 1 100%;

                min-width: 0;
            }

            .filter-actions {
                width: 100%;

                display: grid;

                grid-template-columns:
                    1fr 1fr;
            }

            .filter-actions .btn {
                width: 100%;
            }

            .form-control {
                height: 48px;

                font-size: 16px;
            }

            .btn {
                height: 48px;
            }

            .content-card-header {
                padding: 16px 20px;
            }

            .table-responsive {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .marks-toolbar {
                padding: 14px 16px;
            }

            .settings-info,
            .warning-info {
                margin-left: 16px;
                margin-right: 16px;
            }

            .save-bar {
                flex-direction: column;

                align-items: stretch;

                padding: 14px 16px;
            }

            .save-bar .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {

            .page-wrapper {
                padding: 12px;
            }

            .stats-grid {
                gap: 8px;
            }

            .stat-card {
                padding: 14px;

                border-radius: var(--radius);
            }

            .stat-icon-wrap {
                width: 36px;
                height: 36px;

                font-size: 16px;
            }

            .stat-value {
                font-size: 22px;
            }

            .stat-label {
                font-size: 11px;
            }

            .filter-actions {
                grid-template-columns: 1fr;
            }

            .m-card {
                padding: 14px;
            }

            .m-card-body {
                grid-template-columns: 1fr;
            }

            .mark-input {
                width: 100%;
            }

            .settings-info {
                align-items: flex-start;
            }
        }

        @media (hover: none) and (pointer: coarse) {

            .btn,
            .form-control,
            .mark-input {
                min-height: 44px;
            }

            .stat-card:hover {
                transform: none;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;

                transition: none !important;
            }
        }

        .font-mono {
            font-family:
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Monaco,
                Consolas,
                monospace;
        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <!-- Alert -->

    <?php if ($saveMessage): ?>

        <div
            class="alert alert-<?= e($saveStatus) ?>"
            id="saveAlert"
        >

            <span>
                <?= e($saveMessage) ?>
            </span>

            <button
                type="button"
                class="alert-close"
                onclick="this.parentElement.remove()"
            >
                ×
            </button>

        </div>

    <?php endif; ?>


    <!-- Header -->

    <header class="page-header">

        <div class="page-header-left">

            <nav class="breadcrumb">

                <a href="dashboard.php">
                    Teacher Portal
                </a>

                <span class="breadcrumb-sep">
                    /
                </span>

                <span class="breadcrumb-current">
                    Marks Entry
                </span>

            </nav>

            <h1 class="page-title">
                Marks Entry
            </h1>

            <p class="page-desc">
                Enter and manage student marks using the assessment structure
                configured for your class, section and subject.
            </p>

        </div>

        <div class="teacher-pill">

            <div class="teacher-pill-avatar">
                T
            </div>

            <div>

                <div class="teacher-pill-name">
                    Teacher Workspace
                </div>

                <div class="teacher-pill-meta">

                    <?= count($allowedClasses) ?>

                    <?= count($allowedClasses) === 1 ? 'assigned class' : 'assigned classes' ?>

                </div>

            </div>

        </div>

    </header>


    <!-- Stats -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    👥
                </div>

                <span class="stat-badge">
                    Total
                </span>

            </div>

            <div class="stat-value">
                <?= $totalStudents ?>
            </div>

            <div class="stat-label">
                Total Students
            </div>

        </div>


        <div class="stat-card entered">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    ✓
                </div>

                <span class="stat-badge">
                    Done
                </span>

            </div>

            <div class="stat-value">
                <?= $marksEntered ?>
            </div>

            <div class="stat-label">
                Marks Entered
            </div>

        </div>


        <div class="stat-card pending">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    ⏳
                </div>

                <span class="stat-badge">
                    Pending
                </span>

            </div>

            <div class="stat-value">
                <?= $marksPending ?>
            </div>

            <div class="stat-label">
                Pending Entry
            </div>

        </div>


        <div class="stat-card avg">

            <div class="stat-header">

                <div class="stat-icon-wrap">
                    📊
                </div>

                <span class="stat-badge">
                    Avg
                </span>

            </div>

            <div class="stat-value">
                <?= $averageMarks ?>
            </div>

            <div class="stat-label">
                Class Average
            </div>

        </div>

    </div>


    <!-- Filters -->

    <div class="filter-bar">

        <form
            method="get"
            action="<?= e($_SERVER['PHP_SELF']) ?>"
            class="filter-form"
            id="filterForm"
        >

            <div class="filter-group search">

                <label
                    class="filter-label"
                    for="search"
                >
                    Search students
                </label>

                <div class="filter-input-wrap">

                    <span class="filter-input-icon">
                        ⌕
                    </span>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        class="form-control search"
                        value="<?= e($search) ?>"
                        placeholder="Name, Roll # or Father name..."
                        autocomplete="off"
                    >

                </div>

            </div>


            <div class="filter-group select">

                <label
                    class="filter-label"
                    for="class_id"
                >
                    Class *
                </label>

                <select
                    id="class_id"
                    name="class_id"
                    class="form-control"
                    required
                    onchange="document.getElementById('filterForm').submit()"
                >

                    <option value="">
                        Select Class
                    </option>

                    <?php foreach ($allowedClasses as $class): ?>

                        <option
                            value="<?= (int) $class['id'] ?>"
                            <?= $classFilter === (int) $class['id'] ? 'selected' : '' ?>
                        >
                            <?= e($class['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group select">

                <label
                    class="filter-label"
                    for="section_id"
                >
                    Section
                </label>

                <select
                    id="section_id"
                    name="section_id"
                    class="form-control"
                    onchange="document.getElementById('filterForm').submit()"
                >

                    <option value="">
                        All Sections
                    </option>

                    <?php foreach ($allowedSections as $sec): ?>

                        <?php
                        if (
                            $classFilter !== '' &&
                            $sec['class_id'] !== $classFilter
                        ) {
                            continue;
                        }
                        ?>

                        <option
                            value="<?= (int) $sec['id'] ?>"
                            <?= $sectionFilter === (int) $sec['id'] ? 'selected' : '' ?>
                        >
                            <?= e($sec['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group select">

                <label
                    class="filter-label"
                    for="subject_id"
                >
                    Subject *
                </label>

                <select
                    id="subject_id"
                    name="subject_id"
                    class="form-control"
                    required
                    onchange="document.getElementById('filterForm').submit()"
                >

                    <option value="">
                        Select Subject
                    </option>

                    <?php foreach ($allowedSubjects as $sub): ?>

                        <option
                            value="<?= (int) $sub['id'] ?>"
                            <?= $subjectFilter === (int) $sub['id'] ? 'selected' : '' ?>
                        >
                            <?= e($sub['name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group select">

                <label
                    class="filter-label"
                    for="exam_id"
                >
                    Exam *
                </label>

                <select
                    id="exam_id"
                    name="exam_id"
                    class="form-control"
                    required
                    onchange="document.getElementById('filterForm').submit()"
                >

                    <option value="">
                        Select Exam
                    </option>

                    <?php foreach ($exams as $exam): ?>

                        <option
                            value="<?= (int) $exam['id'] ?>"
                            <?= $examFilter === (int) $exam['id'] ? 'selected' : '' ?>
                        >

                            <?= e($exam['name']) ?>

                            (<?= e(examTypeLabel((string) $exam['type'])) ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-actions">

                <a
                    href="<?= e($_SERVER['PHP_SELF']) ?>"
                    class="btn btn-secondary"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- Marks Card -->

    <div class="content-card">

        <div class="content-card-header">

            <div class="content-card-title">

                <div class="content-card-icon">
                    📝
                </div>

                <div>

                    <h2>
                        Student Marks
                    </h2>

                    <p>
                        Enter marks for selected class, subject and exam
                    </p>

                </div>

            </div>

            <span class="result-badge">

                <?= $totalStudents ?>

                <?= $totalStudents === 1 ? 'student' : 'students' ?>

            </span>

        </div>


        <?php if (!$validCombo || $examFilter === '' || !$examInfo): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    📝
                </div>

                <h3>
                    Select filters to view students
                </h3>

                <p>
                    Please select a class, section, subject and exam
                    from the filters above to load the student list
                    for marks entry.
                </p>

            </div>


        <?php elseif (empty($students)): ?>

            <div class="empty-state">

                <div class="empty-icon">
                    👤
                </div>

                <h3>
                    No students found
                </h3>

                <p>

                    No active students found for the selected class
                    <?= $sectionFilter !== '' ? 'and section' : '' ?>.

                    Try changing your filters.

                </p>

            </div>


        <?php else: ?>


            <!-- Result Settings Information -->

            <?php if ($isConfiguredComponent && $hasResultSetting): ?>

                <div class="settings-info">

                    <div class="settings-info-left">

                        <div class="settings-icon">
                            ⚙
                        </div>

                        <div>

                            <div class="settings-title">

                                <?= e($configuredComponent) ?>
                                Assessment Configuration

                            </div>

                            <div class="settings-description">

                                Total marks are controlled by the
                                Result Settings for this class,
                                section and subject.

                            </div>

                        </div>

                    </div>

                    <div class="total-chip">

                        Total Marks:

                        <strong>
                            <?= e($displayTotalFormatted) ?>
                        </strong>

                    </div>

                </div>

            <?php elseif ($isConfiguredComponent && !$hasResultSetting): ?>

                <div class="warning-info">

                    ⚠

                    <span>
                        Result Settings are not configured for this
                        class/section/subject. Configure the assessment
                        totals before entering <?= e(examTypeLabel($selectedExamType)) ?> marks.
                    </span>

                </div>

            <?php endif; ?>


            <!-- Subject not configured on this exam -->

            <?php if ($examInfo && !$examSubjectConfigured): ?>

                <div class="warning-info">

                    ⚠

                    <span>
                        <strong>
                            <?= e(
                                $allowedSubjects[$subjectFilter]['name']
                                ?? 'This subject'
                            ) ?>
                        </strong>

                        is not configured for this exam, so its maximum
                        marks are unknown and marks entry is disabled.
                        Ask the administrator to add this subject to
                        the exam (Admin → Exams → Manage Subjects) first.
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="post"
                action="<?= e($_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']) ?>"
                class="marks-form"
                id="marksForm"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($marksCsrfToken) ?>"
                >

                <input
                    type="hidden"
                    name="save_marks"
                    value="1"
                >

                <input
                    type="hidden"
                    name="class_id"
                    value="<?= (int) $classFilter ?>"
                >

                <input
                    type="hidden"
                    name="section_id"
                    value="<?= $sectionFilter !== '' ? (int) $sectionFilter : '' ?>"
                >

                <input
                    type="hidden"
                    name="exam_id"
                    value="<?= (int) $examFilter ?>"
                >

                <input
                    type="hidden"
                    name="subject_id"
                    value="<?= (int) $subjectFilter ?>"
                >

                <input
                    type="hidden"
                    name="total_marks"
                    value="<?= e($displayTotalFormatted) ?>"
                >


                <!-- Toolbar -->

                <div class="marks-toolbar">

                    <div class="toolbar-info">

                        <span>
                            Exam:
                        </span>

                        <strong>

                            <?php
                            $selectedExamName = 'Selected Exam';

                            foreach ($exams as $exam) {

                                if ((int) $exam['id'] === $examFilter) {

                                    $selectedExamName = $exam['name'];

                                    break;
                                }
                            }
                            ?>

                            <?= e($selectedExamName) ?>

                        </strong>

                        <span>|</span>

                        <span>
                            Subject:
                        </span>

                        <strong>
                            <?= e(
                                $allowedSubjects[$subjectFilter]['name']
                                ?? 'Selected Subject'
                            ) ?>
                        </strong>

                        <span class="toolbar-total">

                            Total Marks:

                            <strong>
                                <?= e($displayTotalFormatted) ?>
                            </strong>

                        </span>

                    </div>


                    <button
                        type="button"
                        class="btn btn-sm btn-secondary"
                        onclick="fillAll('')"
                    >
                        Clear All
                    </button>

                </div>


                <!-- Desktop -->

                <div class="table-responsive">

                    <table class="data-table">

                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Roll #
                                </th>

                                <th>
                                    Obtained Marks
                                </th>

                                <th>
                                    Grade
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php
                            $counter = 1;

                            foreach ($students as $student):

                                /*
                                 * IMPORTANT:
                                 *
                                 * Existing obtained marks remain visible,
                                 * but their denominator is the current
                                 * configured total.
                                 */
                                $obtained =
                                    $student['obtained_marks'] !== null
                                        ? (float) $student['obtained_marks']
                                        : '';

                                $total = $displayTotal;

                                /*
                                 * If old marks are greater than the newly
                                 * configured total, don't silently alter them.
                                 * Show them, but flag the value visually.
                                 */
                                $markExceedsCurrentTotal =
                                    $obtained !== '' &&
                                    $total > 0 &&
                                    $obtained > $total;

                                $grade =
                                    (
                                        $obtained !== '' &&
                                        !$markExceedsCurrentTotal
                                    )
                                        ? getGrade($obtained, $total)
                                        : '-';

                                $pf =
                                    (
                                        $obtained !== '' &&
                                        !$markExceedsCurrentTotal
                                    )
                                        ? getPassFail($obtained, $total)
                                        : '-';

                                $pfClass =
                                    (
                                        $obtained !== '' &&
                                        !$markExceedsCurrentTotal
                                    )
                                        ? getPassFailClass(
                                            $obtained,
                                            $total
                                        )
                                        : 'status-default';

                                $gradeColor =
                                    (
                                        $obtained !== '' &&
                                        !$markExceedsCurrentTotal
                                    )
                                        ? getGradeColor($grade)
                                        : 'grade-none';

                            ?>

                                <tr>

                                    <td class="font-mono">
                                        <?= $counter++ ?>
                                    </td>


                                    <td>

                                        <div class="student-cell">

                                            <div class="student-avatar">

                                                <?= e(
                                                    strtoupper(
                                                        substr(
                                                            $student['student_name']
                                                            ?? 'S',
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>

                                            <div class="student-info">

                                                <div class="student-name">

                                                    <?= e(
                                                        $student['student_name']
                                                        ?? 'Unknown'
                                                    ) ?>

                                                </div>

                                                <div class="student-meta">

                                                    <?= e(
                                                        $student['father_name']
                                                        ?? ''
                                                    ) ?>

                                                </div>

                                            </div>

                                        </div>

                                    </td>


                                    <td class="font-mono">

                                        <?= e(
                                            $student['student_id']
                                            ?? '-'
                                        ) ?>

                                    </td>


                                    <td>

                                        <div class="mark-input-wrap">

                                            <input
                                                type="number"
                                                name="marks[<?= (int) $student['student_pk'] ?>][obtained]"
                                                data-student-id="<?= (int) $student['student_pk'] ?>"
                                                class="mark-input font-mono"
                                                value="<?= $obtained !== '' ? e($obtained) : '' ?>"
                                                min="0"
                                                max="<?= e($displayTotalFormatted) ?>"
                                                step="0.01"
                                                placeholder="--"
                                                <?= $displayTotal <= 0 ? 'disabled' : '' ?>
                                                onchange="updateRow(
                                                    this,
                                                    <?= json_encode($displayTotal) ?>
                                                )"
                                            >

                                            <span class="mark-total">
                                                /<?= e($displayTotalFormatted) ?>
                                            </span>

                                        </div>

                                        <?php if ($markExceedsCurrentTotal): ?>

                                            <div
                                                style="
                                                    margin-top:6px;
                                                    font-size:11px;
                                                    color:#b91c1c;
                                                    font-weight:600;
                                                "
                                            >
                                                Existing mark exceeds current
                                                configured total.
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <span
                                            class="grade <?= e($gradeColor) ?>"
                                            data-grade-cell="<?= (int) $student['student_pk'] ?>"
                                        >
                                            <?= e($grade) ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span
                                            class="mark-status <?= e($pfClass) ?>"
                                            data-status-cell="<?= (int) $student['student_pk'] ?>"
                                        >
                                            <?= e($pf) ?>
                                        </span>

                                        <?php if ($student['mark_status'] === 'entered'): ?>
                                            <span
                                                class="badge bg-success ms-1"
                                                data-edit-btn="<?= (int) $student['student_pk'] ?>"
                                            >
                                                Edit
                                            </span>
                                        <?php else: ?>
                                            <span
                                                class="badge bg-warning ms-1"
                                                data-enter-btn="<?= (int) $student['student_pk'] ?>"
                                            >
                                                Enter
                                            </span>
                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <!-- Mobile -->

                <div class="mobile-cards">

                    <?php
                    $counter = 1;

                    foreach ($students as $student):

                        $obtained =
                            $student['obtained_marks'] !== null
                                ? (float) $student['obtained_marks']
                                : '';

                        $total = $displayTotal;

                        $markExceedsCurrentTotal =
                            $obtained !== '' &&
                            $total > 0 &&
                            $obtained > $total;

                        $grade =
                            (
                                $obtained !== '' &&
                                !$markExceedsCurrentTotal
                            )
                                ? getGrade($obtained, $total)
                                : '-';

                        $pf =
                            (
                                $obtained !== '' &&
                                !$markExceedsCurrentTotal
                            )
                                ? getPassFail($obtained, $total)
                                : '-';

                        $pfClass =
                            (
                                $obtained !== '' &&
                                !$markExceedsCurrentTotal
                            )
                                ? getPassFailClass(
                                    $obtained,
                                    $total
                                )
                                : 'status-default';

                        $gradeColor =
                            (
                                $obtained !== '' &&
                                !$markExceedsCurrentTotal
                            )
                                ? getGradeColor($grade)
                                : 'grade-none';
                    ?>

                        <div class="m-card">

                            <div class="m-card-header">

                                <div class="student-cell">

                                    <div class="student-avatar">

                                        <?= e(
                                            strtoupper(
                                                substr(
                                                    $student['student_name']
                                                    ?? 'S',
                                                    0,
                                                    1
                                                )
                                            )
                                        ) ?>

                                    </div>

                                    <div class="student-info">

                                        <div class="student-name">

                                            <?= e(
                                                $student['student_name']
                                                ?? 'Unknown'
                                            ) ?>

                                        </div>

                                        <div class="student-meta">

                                            Roll:
                                            <?= e(
                                                $student['student_id']
                                                ?? '-'
                                            ) ?>

                                            •

                                            <?= e(
                                                $student['father_name']
                                                ?? ''
                                            ) ?>

                                        </div>

                                    </div>

                                </div>


                                <span
                                    class="mark-status
                                    <?= $student['mark_status'] === 'entered'
                                        ? 'status-entered'
                                        : 'status-pending'
                                    ?>"
                                >

                                    <?= $student['mark_status'] === 'entered'
                                        ? 'Entered'
                                        : 'Pending'
                                    ?>

                                </span>

                            </div>


                            <div class="m-card-body">

                                <div class="m-field">

                                    <div class="m-field-label">
                                        Obtained Marks
                                    </div>

                                    <input
                                        type="number"
                                        name="mobile_marks[<?= (int) $student['student_pk'] ?>][obtained]"
                                        data-student-id="<?= (int) $student['student_pk'] ?>"
                                        class="mark-input font-mono"
                                        style="width:100%;"
                                        value="<?= $obtained !== '' ? e($obtained) : '' ?>"
                                        min="0"
                                        max="<?= e($displayTotalFormatted) ?>"
                                        step="0.01"
                                        placeholder="Enter marks..."
                                        <?= $displayTotal <= 0 ? 'disabled' : '' ?>
                                        onchange="updateRow(
                                            this,
                                            <?= json_encode($displayTotal) ?>
                                        )"
                                    >

                                </div>


                                <div class="m-field">

                                    <div class="m-field-label">
                                        Total Marks
                                    </div>

                                    <div class="m-field-value">

                                        <?= e($displayTotalFormatted) ?>

                                    </div>

                                </div>


                                <div class="m-field">

                                    <div class="m-field-label">
                                        Grade
                                    </div>

                                    <div class="m-field-value">

                                        <span
                                            class="grade <?= e($gradeColor) ?>"
                                            data-grade-cell="<?= (int) $student['student_pk'] ?>"
                                        >
                                            <?= e($grade) ?>
                                        </span>

                                    </div>

                                </div>


                                <div class="m-field">

                                    <div class="m-field-label">
                                        Result
                                    </div>

                                    <div class="m-field-value">

                                        <span
                                            class="mark-status <?= e($pfClass) ?>"
                                            data-status-cell="<?= (int) $student['student_pk'] ?>"
                                        >
                                            <?= e($pf) ?>
                                        </span>

                                        <?php if ($student['mark_status'] === 'entered'): ?>
                                            <span class="badge bg-success ms-1">Edit</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning ms-1">Enter</span>
                                        <?php endif; ?>

                                    </div>

                                </div>

                            </div>


                            <?php if ($markExceedsCurrentTotal): ?>

                                <div
                                    style="
                                        margin-top:12px;
                                        padding-top:12px;
                                        border-top:1px solid #fee2e2;
                                        color:#b91c1c;
                                        font-size:12px;
                                        font-weight:600;
                                    "
                                >
                                    Existing mark exceeds the current
                                    configured total.
                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endforeach; ?>

                </div>


                <!-- Save Bar -->

                <div class="save-bar">

                    <div class="save-bar-info">

                        <strong>
                            <?= $marksEntered ?>
                        </strong>

                        of

                        <strong>
                            <?= $totalStudents ?>
                        </strong>

                        marks entered

                        <?php if ($marksPending > 0): ?>

                            •
                            <strong>
                                <?= $marksPending ?>
                            </strong>

                            pending

                        <?php endif; ?>

                    </div>


                    <button
                        type="submit"
                        class="btn btn-success"
                        <?= $displayTotal <= 0 ? 'disabled' : '' ?>
                    >
                        💾 Save All Marks
                    </button>

                </div>

            </form>

        <?php endif; ?>

    </div>

</div>


<script>

(function () {

    'use strict';


    /*
    |--------------------------------------------------------------------------
    | ALERT
    |--------------------------------------------------------------------------
    */

    const alertEl =
        document.getElementById('saveAlert');

    if (alertEl) {

        setTimeout(() => {

            alertEl.style.transition =
                'opacity .4s';

            alertEl.style.opacity = '0';

            setTimeout(() => {

                alertEl.remove();

            }, 400);

        }, 4000);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE GRADE / RESULT
    |--------------------------------------------------------------------------
    */

    window.updateRow = function (
        input,
        totalMarks
    ) {

        let obtained =
            parseFloat(input.value);

        const row =
            input.closest('tr') ||
            input.closest('.m-card');

        if (!row) {
            return;
        }

        const gradeCell =
            row.querySelector(
                '[data-grade-cell]'
            );

        const statusCell =
            row.querySelector(
                '[data-status-cell]'
            );


        /*
         * Empty input.
         */

        if (
            isNaN(obtained) ||
            input.value.trim() === ''
        ) {

            if (gradeCell) {

                gradeCell.textContent = '-';

                gradeCell.className =
                    'grade grade-none';
            }

            if (statusCell) {

                statusCell.textContent = '-';

                statusCell.className =
                    'mark-status status-default';
            }

            return;
        }


        /*
         * Clamp.
         */

        if (obtained < 0) {

            obtained = 0;

            input.value = '0';
        }

        if (obtained > totalMarks) {

            obtained = totalMarks;

            input.value = totalMarks;
        }


        if (totalMarks <= 0) {

            if (gradeCell) {

                gradeCell.textContent = '-';

                gradeCell.className =
                    'grade grade-none';
            }

            if (statusCell) {

                statusCell.textContent = '-';

                statusCell.className =
                    'mark-status status-default';
            }

            return;
        }


        const pct =
            (obtained / totalMarks) * 100;


        let grade = 'F';

        if (pct >= 90) {
            grade = 'A+';
        } else if (pct >= 80) {
            grade = 'A';
        } else if (pct >= 70) {
            grade = 'B';
        } else if (pct >= 60) {
            grade = 'C';
        } else if (pct >= 50) {
            grade = 'D';
        } else if (pct >= 40) {
            grade = 'E';
        }


        const passed =
            pct >= 40;


        if (gradeCell) {

            gradeCell.textContent =
                grade;

            gradeCell.className =
                'grade ' +
                getGradeClass(grade);
        }


        if (statusCell) {

            statusCell.textContent =
                passed
                    ? 'Pass'
                    : 'Fail';

            statusCell.className =
                'mark-status ' +
                (
                    passed
                        ? 'status-pass'
                        : 'status-fail'
                );
        }

    };


    /*
    |--------------------------------------------------------------------------
    | GRADE CLASS
    |--------------------------------------------------------------------------
    */

    function getGradeClass(grade) {

        if (
            grade === 'A+' ||
            grade === 'A'
        ) {
            return 'grade-a';
        }

        if (grade === 'B') {
            return 'grade-b';
        }

        if (grade === 'C') {
            return 'grade-c';
        }

        if (
            grade === 'D' ||
            grade === 'E'
        ) {
            return 'grade-d';
        }

        return 'grade-f';
    }


    /*
    |--------------------------------------------------------------------------
    | CLEAR ALL
    |--------------------------------------------------------------------------
    */

    window.fillAll = function (value) {

        if (
            !confirm(
                'Are you sure you want to clear all entered marks?'
            )
        ) {
            return;
        }

        document
            .querySelectorAll('.mark-input')
            .forEach(input => {

                if (input.disabled) {
                    return;
                }

                input.value = value;

                input.dispatchEvent(
                    new Event('change')
                );

            });

    };


    /*
    |--------------------------------------------------------------------------
    | UNSAVED CHANGES
    |--------------------------------------------------------------------------
    */

    let formChanged = false;

    const form =
        document.getElementById(
            'marksForm'
        );

    if (form) {

        form.addEventListener(
            'input',
            (event) => {
                formChanged = true;

                /*
                 * Every student is rendered twice: once in the desktop
                 * table and once in the hidden mobile card list, and BOTH
                 * are submitted (marks[...] and mobile_marks[...]).
                 *
                 * The server merges them, so if the two copies disagree
                 * (teacher edits one while the other still holds the old
                 * saved value) the wrong number is stored. Mirror every
                 * edit into the matching input so both copies are always
                 * identical.
                 */
                const target = event.target;

                if (
                    !target ||
                    !target.matches ||
                    !target.matches('.mark-input')
                ) {
                    return;
                }

                const studentId = target.getAttribute('data-student-id');

                if (!studentId) {
                    return;
                }

                const isMobile = !!target.closest('.mobile-cards');

                const counterpart = document.querySelector(
                    (isMobile ? '.table-responsive' : '.mobile-cards') +
                    ' .mark-input[data-student-id="' + studentId + '"]'
                );

                if (counterpart && !counterpart.disabled) {
                    counterpart.value = target.value;
                }
            }
        );

        form.addEventListener(
            'submit',
            () => {
                document
                    .querySelectorAll('.mobile-cards .mark-input')
                    .forEach(mobileInput => {

                        /*
                         * The mobile inputs are hidden on desktop and are
                         * submitted as empty strings. Only push a mobile
                         * value into the desktop input when it actually
                         * contains marks — otherwise an empty mobile field
                         * would erase what the teacher typed in the table.
                         */
                        const mobileValue = (mobileInput.value || '').trim();

                        if (mobileValue === '') {
                            return;
                        }

                        const studentId = mobileInput.getAttribute('data-student-id');
                        if (!studentId) return;
                        const desktopInput = document.querySelector(
                            '.table-responsive .mark-input[data-student-id=\"' + studentId + '\"]'
                        );
                        if (desktopInput) {
                            desktopInput.value = mobileValue;
                        }
                    });

                formChanged = false;
            }
        );

        window.addEventListener(
            'beforeunload',
            (e) => {

                if (formChanged) {

                    e.preventDefault();

                    e.returnValue = '';
                }

            }
        );

    }

})();

</script>

</body>

</html>
