<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../includes/layout.php';

requireAdmin();

$pdo = db();

require_once __DIR__ . '/../../classes/Exam.php';
require_once __DIR__ . '/../../classes/Mark.php';

$examManager = new Exam($pdo);
$markManager = new Mark($pdo);

$selectedExamId = isset($_GET['exam_id']) && ctype_digit((string) $_GET['exam_id'])
    ? (int) $_GET['exam_id']
    : 0;

$selectedSubjectId = isset($_GET['subject_id']) && ctype_digit((string) $_GET['subject_id'])
    ? (int) $_GET['subject_id']
    : 0;

$message = '';
$messageType = '';

// =====================================================
// HANDLE ADMIN CORRECTION / OVERRIDE
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_correction'])) {

    require_post_csrf();

    $correctionExamId = (int) ($_POST['exam_id'] ?? 0);
    $correctionStudentId = (int) ($_POST['student_id'] ?? 0);
    $correctionSubjectId = (int) ($_POST['subject_id'] ?? 0);
    $correctionMarks = (float) ($_POST['obtained_marks'] ?? -1);

    if ($correctionExamId <= 0 || $correctionStudentId <= 0 || $correctionSubjectId <= 0) {
        $message = 'Invalid exam, student or subject selected.';
        $messageType = 'danger';
    } else {
        // Get authoritative total from exam_subjects
        $examSubjectStmt = $pdo->prepare("
            SELECT total_marks FROM exam_subjects
            WHERE exam_id = :exam_id AND subject_id = :subject_id
            LIMIT 1
        ");
        $examSubjectStmt->execute([
            ':exam_id' => $correctionExamId,
            ':subject_id' => $correctionSubjectId
        ]);
        $examSubject = $examSubjectStmt->fetch(PDO::FETCH_ASSOC);

        $totalMarks = $examSubject ? (float) $examSubject['total_marks'] : 100;

        if ($correctionMarks < 0 || $correctionMarks > $totalMarks) {
            $message = 'Obtained marks must be between 0 and ' . $totalMarks . '.';
            $messageType = 'danger';
        } else {
            $adminUserId = (int) ($_SESSION['user_id'] ?? 0);
            $saved = $markManager->save(
                $correctionExamId,
                $correctionStudentId,
                $correctionSubjectId,
                $totalMarks,
                $correctionMarks,
                $adminUserId
            );

            if ($saved) {
                $message = 'Mark corrected successfully.';
                $messageType = 'success';
            } else {
                $message = 'Unable to correct mark.';
                $messageType = 'danger';
            }
        }
    }
}

// =====================================================
// LOAD EXAMS
// =====================================================

$exams = $examManager->list('', '', '');

// =====================================================
// LOAD SELECTED EXAM
// =====================================================

$selectedExam = null;
$examSubjects = [];
$students = [];
$marks = [];

if ($selectedExamId > 0) {

    $selectedExam = $examManager->getById($selectedExamId);

    if ($selectedExam !== null) {

        $examSubjects = $examManager->getSubjects($selectedExamId);

        // Filter by subject if selected
        if ($selectedSubjectId > 0) {
            $examSubjects = array_filter($examSubjects, function($es) use ($selectedSubjectId) {
                return (int) $es['subject_id'] === $selectedSubjectId;
            });
        }

        // Students belonging to exam class
        $stmt = $pdo->prepare("
            SELECT
                st.id,
                st.student_id,
                st.name,
                st.status,
                c.name AS class_name,
                sec.name AS section_name
            FROM students st
            INNER JOIN classes c ON st.class_id = c.id
            LEFT JOIN sections sec ON st.section_id = sec.id
            WHERE st.class_id = :class_id
              AND st.status = 'active'
            ORDER BY st.name ASC
        ");

        $stmt->execute([
            ':class_id' => $selectedExam['class_id']
        ]);

        $students = $stmt->fetchAll();

        // Existing marks for selected exam (with audit info)
        $marks = $markManager->getForExamWithAudit($selectedExamId);
    }
}

// =====================================================
// CREATE EASY LOOKUP FOR EXISTING MARKS
// =====================================================

$markLookup = [];

foreach ($marks as $mark) {

    $studentId = (int) $mark['student_id'];
    $subjectId = (int) $mark['subject_id'];

    $markLookup[$studentId][$subjectId] = $mark;
}

// =====================================================
// STATISTICS
// =====================================================

$totalStudents = count($students);
$totalSubjects = count($examSubjects);
$totalEntries = count($marks);

$possibleEntries = $totalStudents * $totalSubjects;

$completionPercentage = $possibleEntries > 0
    ? round(($totalEntries / $possibleEntries) * 100, 1)
    : 0;

/*
|--------------------------------------------------------------------------
| CSV EXPORT — recorded marks for the selected exam (and subject filter)
|--------------------------------------------------------------------------
|
| Read-only download of what is shown; GET is fine for that, the page
| still requires an authenticated admin session.
|
*/

if (trim((string) ($_GET['export'] ?? '')) === 'csv') {

    require_once __DIR__ . '/../../includes/export.php';

    $exportMarks = $marks;

    if ($selectedSubjectId > 0) {
        $exportMarks = array_values(array_filter(
            $exportMarks,
            static fn (array $m): bool
                => (int) $m['subject_id'] === $selectedSubjectId
        ));
    }

    $exportExamName = $selectedExam !== null
        ? (string) $selectedExam['name']
        : '';

    export_csv(
        'marks_exam_' . $selectedExamId . '_' . date('Ymd_His') . '.csv',
        [
            'Exam',
            'Student ID',
            'Student Name',
            'Subject',
            'Obtained Marks',
            'Total Marks',
            'Updated By',
            'Updated At',
        ],
        (static function () use ($exportMarks, $exportExamName): iterable {
            foreach ($exportMarks as $m) {
                yield [
                    $exportExamName,
                    $m['student_code'] ?? '',
                    $m['student_name'] ?? '',
                    $m['subject_name'] ?? '',
                    $m['obtained_marks'] ?? '',
                    $m['total_marks'] ?? '',
                    $m['updated_by'] ?? '',
                    $m['updated_at'] ?? '',
                ];
            }
        })()
    );

    // export_csv() exits.
}

layout_start('Marks Management', 'marks');
?>

<!-- =====================================================
     FLASH MESSAGE
===================================================== -->

<?php if ($message !== ''): ?>

    <div class="alert alert-<?= e($messageType) ?> alert-dismissible fade show shadow-sm">
        <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

<?php endif; ?>

<!-- =====================================================
     EXAM SELECTOR
===================================================== -->

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="row align-items-end g-3">
            <div class="col-lg-6">
                <label class="form-label fw-semibold">Select Examination</label>
                <select class="form-select form-select-lg" id="examSelector">
                    <option value="">-- Select an exam --</option>
                    <?php foreach ($exams as $exam): ?>
                        <option value="<?= (int) $exam['id'] ?>" <?= $selectedExamId === (int) $exam['id'] ? 'selected' : '' ?>>
                            <?= e($exam['name']) ?> — <?= e($exam['class_name']) ?> (<?= e(ucfirst($exam['type'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-4">
                <label class="form-label fw-semibold">Filter by Subject</label>
                <select class="form-select" id="subjectSelector">
                    <option value="">All Subjects</option>
                    <?php foreach ($examManager->getSubjects($selectedExamId) as $subject): ?>
                        <option value="<?= (int) $subject['subject_id'] ?>" <?= $selectedSubjectId === (int) $subject['subject_id'] ? 'selected' : '' ?>>
                            <?= e($subject['subject_name']) ?> (<?= e($subject['total_marks']) ?> marks)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-2">
                <button type="button" class="btn btn-primary btn-lg w-100" onclick="openSelectedExam()">
                    <i class="bi bi-arrow-right-circle me-2"></i>View
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedExam === null): ?>

    <div class="card shadow-sm border-0">
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-clipboard-check fs-1 text-muted"></i>
            </div>
            <h4 class="fw-bold">Select an Examination</h4>
            <p class="text-muted mb-0">Choose an exam above to view and monitor student marks.</p>
        </div>
    </div>

<?php else: ?>

    <!-- =================================================
         EXAM HERO
    ================================================== -->

    <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #1f2937 0%, #111827 100%); color: #fff;">
        <div class="card-body p-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-7">
                    <div class="small text-white-50 mb-2">MARKS MONITORING</div>
                    <h2 class="h3 fw-bold mb-2"><?= e($selectedExam['name']) ?></h2>
                    <div class="d-flex flex-wrap gap-3 text-white-50">
                        <span><i class="bi bi-building me-1"></i><?= e($selectedExam['class_name']) ?></span>
                        <span><i class="bi bi-journal-text me-1"></i><?= e(ucfirst($selectedExam['type'])) ?></span>
                        <?php if (!empty($selectedExam['start_date'])): ?>
                            <span><i class="bi bi-calendar-event me-1"></i><?= e($selectedExam['start_date']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3">
                        <a
                            class="btn btn-sm btn-outline-light"
                            href="<?= e('?' . http_build_query(array_filter([
                                'exam_id'   => $selectedExamId,
                                'subject_id'=> $selectedSubjectId > 0 ? $selectedSubjectId : null,
                                'export'    => 'csv',
                            ], static fn ($v): bool => $v !== null))) ?>"
                            title="Download recorded marks for this exam as CSV"
                        >
                            <i class="bi bi-download me-1"></i>Export CSV
                        </a>
                    </div>

                </div>
                <div class="col-lg-5">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">
                                <div class="fs-4 fw-bold"><?= $totalStudents ?></div>
                                <div class="small text-white-50">Students</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">
                                <div class="fs-4 fw-bold"><?= $totalSubjects ?></div>
                                <div class="small text-white-50">Subjects</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">
                                <div class="fs-4 fw-bold"><?= $completionPercentage ?>%</div>
                                <div class="small text-white-50">Complete</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =================================================
         SUBJECTS
    ================================================== -->

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h6 fw-bold mb-1"><i class="bi bi-book me-2"></i>Exam Subjects</h2>
                    <small class="text-muted">Subjects configured for this examination.</small>
                </div>
                <span class="badge text-bg-primary"><?= $totalSubjects ?> Subjects</span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($examSubjects === []): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-book fs-2 d-block mb-2"></i>
                    No subjects have been added to this exam yet.
                    <div class="mt-2">Add subjects from the Exam Management section first.</div>
                </div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($examSubjects as $subject): ?>
                        <div class="border rounded-3 px-3 py-2 bg-light">
                            <div class="fw-semibold"><?= e($subject['subject_name']) ?></div>
                            <small class="text-muted">
                                <?= e($subject['subject_code'] ?? '') ?> ·
                                <?= number_format((float) $subject['total_marks'], 2) ?> Marks ·
                                Pass <?= number_format((float) $subject['pass_marks'], 2) ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- =================================================
         MARKS TABLE
    ================================================== -->

    <?php if ($students === [] || $examSubjects === []): ?>

        <div class="card shadow-sm border-0">
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-people fs-1 text-muted"></i>
                </div>
                <h4 class="fw-bold">Marks Not Available</h4>
                <p class="text-muted mb-0">
                    <?php if ($students === []): ?>
                        No active students found in this exam's class.
                    <?php elseif ($examSubjects === []): ?>
                        No subjects are assigned to this exam.
                    <?php endif; ?>
                </p>
            </div>
        </div>

    <?php else: ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white p-3">
                <div class="row align-items-center g-2">
                    <div class="col-lg-6">
                        <h2 class="h6 fw-bold mb-1"><i class="bi bi-clipboard-data me-2"></i>Student Marks</h2>
                        <small class="text-muted">View and monitor marks entered by teachers. Existing marks can be corrected inline — corrections are saved on the canonical mark record with audit info.</small>
                    </div>
                    <div class="col-lg-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="studentSearch" class="form-control" placeholder="Search student by name or ID...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-4">Student</th>
                                <?php foreach ($examSubjects as $subject): ?>
                                    <th class="text-center">
                                        <div><?= e($subject['subject_name']) ?></div>
                                        <small class="text-muted fw-normal">/ <?= number_format((float) $subject['total_marks'], 0) ?></small>
                                    </th>
                                <?php endforeach; ?>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody id="studentTableBody">
                            <?php foreach ($students as $student): ?>
                                <?php
                                $studentId = (int) $student['id'];
                                $studentTotal = 0;
                                $studentObtained = 0;
                                $hasFail = false;
                                $hasMarks = false;

                                foreach ($examSubjects as $subject) {
                                    $subjectId = (int) $subject['subject_id'];
                                    if (isset($markLookup[$studentId][$subjectId])) {
                                        $existing = $markLookup[$studentId][$subjectId];
                                        $studentTotal += (float) $existing['total_marks'];
                                        $studentObtained += (float) $existing['obtained_marks'];
                                        $hasMarks = true;
                                        if ((float) $existing['obtained_marks'] < (float) $subject['pass_marks']) {
                                            $hasFail = true;
                                        }
                                    }
                                }

                                $studentPercentage = $studentTotal > 0
                                    ? round(($studentObtained / $studentTotal) * 100, 1)
                                    : 0;
                                ?>
                                <tr class="student-row" data-search="<?= e(strtolower($student['name'] . ' ' . $student['student_id'])) ?>">
                                    <td class="px-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #eef2ff; color: #4f46e5; font-weight: 700;">
                                                <?= e(strtoupper(substr($student['name'], 0, 1))) ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= e($student['name']) ?></div>
                                                <small class="text-muted">
                                                    <?= e($student['student_id']) ?>
                                                    <?php if (!empty($student['section_name'])): ?>
                                                        · <?= e($student['section_name']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <?php foreach ($examSubjects as $subject): ?>
                                        <?php
                                        $subjectId = (int) $subject['subject_id'];
                                        $totalMarks = (float) $subject['total_marks'];
                                        $existingMarks = $markLookup[$studentId][$subjectId] ?? null;
                                        $obtained = $existingMarks !== null ? (float) $existingMarks['obtained_marks'] : null;
                                        ?>
                                        <td class="text-center">
                                            <?php if ($obtained !== null): ?>
                                                <div class="fw-semibold <?= $obtained >= (float) $subject['pass_marks'] ? 'text-success' : 'text-danger' ?>">
                                                    <?= number_format($obtained, 2) ?>
                                                </div>
                                                <small class="text-muted">
                                                    / <?= number_format($totalMarks, 0) ?>
                                                    <?php if ($existingMarks['updated_by']): ?>
                                                        <br><span class="text-muted" style="font-size: 0.7rem;">by user #<?= (int) $existingMarks['updated_by'] ?></span>
                                                    <?php endif; ?>
                                                </small>

                                                <!-- =================================
                                                     ADMIN CORRECTION (canonical save)
                                                ================================== -->

                                                <form
                                                    method="POST"
                                                    class="mt-2 d-flex justify-content-center align-items-center gap-1"
                                                    onsubmit="return confirm('Correct this mark? The change is recorded on the canonical mark record.');"
                                                >
                                                    <?= csrf_field() ?>

                                                    <input type="hidden" name="exam_id" value="<?= (int) $selectedExamId ?>">
                                                    <input type="hidden" name="student_id" value="<?= $studentId ?>">
                                                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">

                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="<?= number_format($totalMarks, 2, '.', '') ?>"
                                                        name="obtained_marks"
                                                        value="<?= e(number_format($obtained, 2, '.', '')) ?>"
                                                        required
                                                        style="width: 84px; padding: 4px 6px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; text-align: center;"
                                                    >

                                                    <button
                                                        type="submit"
                                                        name="admin_correction"
                                                        value="1"
                                                        class="btn btn-sm btn-outline-primary"
                                                        style="padding: 4px 8px; font-size: 0.75rem;"
                                                    >
                                                        Correct
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-center">
                                        <?php if (!$hasMarks): ?>
                                            <span class="badge text-bg-secondary">Not Entered</span>
                                        <?php elseif ($hasFail): ?>
                                            <span class="badge text-bg-danger"><i class="bi bi-x-circle me-1"></i>Fail</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i><?= $studentPercentage ?>%</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noSearchResults" style="display:none;">
                                <td colspan="<?= count($examSubjects) + 2 ?>" class="text-center text-muted py-5">
                                    <i class="bi bi-search fs-2 d-block mb-2"></i>
                                    No students match your search.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>

<script>
function openSelectedExam() {
    const examId = document.getElementById('examSelector').value;
    const subjectId = document.getElementById('subjectSelector').value;
    if (examId) {
        let url = 'index.php?exam_id=' + examId;
        if (subjectId) {
            url += '&subject_id=' + subjectId;
        }
        window.location.href = url;
    }
}

// Student search filter
document.getElementById('studentSearch')?.addEventListener('input', function() {
    const query = this.value.toLowerCase();
    const rows = document.querySelectorAll('.student-row');
    let visibleCount = 0;
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search');
        if (searchData.includes(query)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    document.getElementById('noSearchResults').style.display = visibleCount === 0 ? '' : 'none';
});
</script>

<?php layout_end(); ?>
