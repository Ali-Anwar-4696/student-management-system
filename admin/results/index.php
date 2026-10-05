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

$selectedStudentId = isset($_GET['student_id']) && ctype_digit((string) $_GET['student_id'])
    ? (int) $_GET['student_id']
    : 0;

// =====================================================
// LOAD EXAMS
// =====================================================

$exams = $examManager->list('', '', '');

$selectedExam = null;
$results = [];
$selectedStudentResult = null;
$selectedStudent = null;

// =====================================================
// LOAD SELECTED EXAM
// =====================================================

if ($selectedExamId > 0) {

    $selectedExam = $examManager->getById($selectedExamId);

    if ($selectedExam !== null) {

        // Get students belonging to exam class
        $stmt = $pdo->prepare("
            SELECT
                st.id,
                st.student_id,
                st.name,
                st.status,
                c.name AS class_name,
                sec.name AS section_name
            FROM students st
            INNER JOIN classes c
                ON st.class_id = c.id
            LEFT JOIN sections sec
                ON st.section_id = sec.id
            WHERE st.class_id = :class_id
              AND st.status = 'active'
            ORDER BY st.name ASC
        ");

        $stmt->execute([
            ':class_id' => $selectedExam['class_id']
        ]);

        $students = $stmt->fetchAll();

        // =================================================
        // CALCULATE RESULTS
        // =================================================

        foreach ($students as $student) {

            $result = $markManager->calculateResult(
                $selectedExamId,
                (int) $student['id']
            );

            if ($result !== null) {

                $results[] = [
                    'student' => $student,
                    'result' => $result
                ];
            }
        }

        // =================================================
        // SELECTED STUDENT RESULT
        // =================================================

        if ($selectedStudentId > 0) {

            $stmt = $pdo->prepare("
                SELECT
                    st.id,
                    st.student_id,
                    st.name,
                    st.status,
                    c.name AS class_name,
                    sec.name AS section_name
                FROM students st
                INNER JOIN classes c
                    ON st.class_id = c.id
                LEFT JOIN sections sec
                    ON st.section_id = sec.id
                WHERE st.id = :student_id
                LIMIT 1
            ");

            $stmt->execute([
                ':student_id' => $selectedStudentId
            ]);

            $selectedStudent = $stmt->fetch();

            if ($selectedStudent !== false) {

                $selectedStudentResult = $markManager->calculateResult(
                    $selectedExamId,
                    $selectedStudentId
                );

                if ($selectedStudentResult === null) {
                    $selectedStudent = null;
                }
            }
        }
    }
}

// =====================================================
// RESULT STATISTICS
// =====================================================

$totalResults = count($results);

$passed = 0;
$failed = 0;
$totalPercentage = 0;

foreach ($results as $row) {

    if ($row['result']['status'] === 'Pass') {
        $passed++;
    } else {
        $failed++;
    }

    $totalPercentage += (float) $row['result']['percentage'];
}

$averagePercentage = $totalResults > 0
    ? round($totalPercentage / $totalResults, 2)
    : 0;

$passPercentage = $totalResults > 0
    ? round(($passed / $totalResults) * 100, 1)
    : 0;

layout_start('Result Management', 'results');

?>

<style>

    .result-hero {
        border-radius: 18px;
        background: linear-gradient(135deg, #312e81 0%, #1e1b4b 100%);
        color: #fff;
        overflow: hidden;
        position: relative;
    }

    .result-hero::after {
        content: "";
        position: absolute;
        width: 250px;
        height: 250px;
        border-radius: 50%;
        background: rgba(255,255,255,.05);
        right: -80px;
        top: -100px;
    }

    .stat-box {
        background: #fff;
        border: 1px solid rgba(0,0,0,.07);
        border-radius: 15px;
        padding: 18px;
        height: 100%;
    }

    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef2ff;
        color: #4f46e5;
        font-size: 19px;
    }

    .student-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef2ff;
        color: #4f46e5;
        font-weight: 700;
    }

    .result-table th {
        white-space: nowrap;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .result-table td {
        vertical-align: middle;
    }

    .result-card {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
    }

    .result-card-header {
        background: linear-gradient(135deg, #312e81, #4338ca);
        color: #fff;
    }

    .subject-row td {
        padding-top: 13px;
        padding-bottom: 13px;
    }

    .grade-box {
        width: 70px;
        height: 70px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.15);
        font-size: 30px;
        font-weight: 800;
    }

    .percentage-circle {
        width: 95px;
        height: 95px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eef2ff;
        color: #3730a3;
        font-size: 20px;
        font-weight: 800;
        margin: auto;
    }

    .empty-state {
        padding: 65px 20px;
        text-align: center;
    }

    .empty-icon {
        width: 72px;
        height: 72px;
        border-radius: 20px;
        background: #f1f5f9;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 29px;
    }

    @media print {

        .app-sidebar,
        .app-topbar,
        .no-print,
        .btn {
            display: none !important;
        }

        .app-main {
            margin: 0 !important;
        }

        .app-content {
            padding: 0 !important;
        }

        .result-card {
            box-shadow: none !important;
        }
    }

</style>

<!-- =====================================================
     PAGE HEADER
===================================================== -->

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">

    <div>

        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-award me-2"></i>
            Result Management
        </h1>

        <p class="text-muted mb-0">
            View calculated examination results and student performance.
        </p>

    </div>

</div>

<!-- =====================================================
     EXAM SELECTOR
===================================================== -->

<div class="card shadow-sm border-0 mb-4 no-print">

    <div class="card-body p-4">

        <div class="row align-items-end g-3">

            <div class="col-lg-8">

                <label class="form-label fw-semibold">
                    Select Examination
                </label>

                <select
                    id="examSelector"
                    class="form-select form-select-lg"
                >

                    <option value="">
                        -- Select an exam --
                    </option>

                    <?php foreach ($exams as $exam): ?>

                        <option
                            value="<?= (int) $exam['id'] ?>"
                            <?= $selectedExamId === (int) $exam['id'] ? 'selected' : '' ?>
                        >

                            <?= e($exam['name']) ?>
                            —
                            <?= e($exam['class_name']) ?>
                            (<?= e(ucfirst($exam['type'])) ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-lg-4">

                <button
                    type="button"
                    class="btn btn-primary btn-lg w-100"
                    onclick="openSelectedExam()"
                >
                    <i class="bi bi-arrow-right-circle me-2"></i>
                    View Results
                </button>

            </div>

        </div>

    </div>

</div>

<?php if ($selectedExam === null): ?>

    <!-- =================================================
         EMPTY STATE
    ================================================== -->

    <div class="card shadow-sm border-0">

        <div class="empty-state">

            <div class="empty-icon mb-3">
                <i class="bi bi-award"></i>
            </div>

            <h4 class="fw-bold">
                Select an Examination
            </h4>

            <p class="text-muted mb-0">
                Select an exam above to view student results.
            </p>

        </div>

    </div>

<?php else: ?>

    <!-- =================================================
         HERO
    ================================================== -->

    <div class="card result-hero shadow-sm mb-4">

        <div class="card-body p-4 position-relative">

            <div class="row align-items-center g-4">

                <div class="col-lg-7">

                    <div class="small text-white-50 mb-2">
                        EXAMINATION RESULTS
                    </div>

                    <h2 class="h3 fw-bold mb-2">
                        <?= e($selectedExam['name']) ?>
                    </h2>

                    <div class="d-flex flex-wrap gap-3 text-white-50">

                        <span>
                            <i class="bi bi-building me-1"></i>
                            <?= e($selectedExam['class_name']) ?>
                        </span>

                        <span>
                            <i class="bi bi-journal-text me-1"></i>
                            <?= e(ucfirst($selectedExam['type'])) ?>
                        </span>

                        <?php if (!empty($selectedExam['start_date'])): ?>

                            <span>
                                <i class="bi bi-calendar-event me-1"></i>
                                <?= e($selectedExam['start_date']) ?>
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

                <div class="col-lg-5">

                    <div class="row g-2">

                        <div class="col-4">

                            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">

                                <div class="fs-4 fw-bold">
                                    <?= $totalResults ?>
                                </div>

                                <div class="small text-white-50">
                                    Results
                                </div>

                            </div>

                        </div>

                        <div class="col-4">

                            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">

                                <div class="fs-4 fw-bold">
                                    <?= $passed ?>
                                </div>

                                <div class="small text-white-50">
                                    Passed
                                </div>

                            </div>

                        </div>

                        <div class="col-4">

                            <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center">

                                <div class="fs-4 fw-bold">
                                    <?= $averagePercentage ?>%
                                </div>

                                <div class="small text-white-50">
                                    Average
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- =================================================
         STATISTICS
    ================================================== -->

    <div class="row g-3 mb-4 no-print">

        <div class="col-md-3">

            <div class="stat-box shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>
                        <div class="text-muted small">
                            Total Results
                        </div>

                        <div class="fs-3 fw-bold">
                            <?= $totalResults ?>
                        </div>
                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="stat-box shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="text-muted small">
                            Passed
                        </div>

                        <div class="fs-3 fw-bold text-success">
                            <?= $passed ?>
                        </div>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="stat-box shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="text-muted small">
                            Failed
                        </div>

                        <div class="fs-3 fw-bold text-danger">
                            <?= $failed ?>
                        </div>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-x-circle"></i>
                    </div>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="stat-box shadow-sm">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <div class="text-muted small">
                            Pass Rate
                        </div>

                        <div class="fs-3 fw-bold">
                            <?= $passPercentage ?>%
                        </div>

                    </div>

                    <div class="stat-icon">
                        <i class="bi bi-graph-up"></i>
                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- =================================================
         SELECTED STUDENT RESULT
    ================================================== -->

    <?php if ($selectedStudentResult !== null && $selectedStudent !== null): ?>

        <div class="card result-card shadow-sm mb-4">

            <div class="result-card-header p-4">

                <div class="d-flex justify-content-between align-items-center gap-3">

                    <div>

                        <div class="small text-white-50 mb-1">
                            STUDENT RESULT
                        </div>

                        <h2 class="h4 fw-bold mb-1">
                            <?= e($selectedStudent['name']) ?>
                        </h2>

                        <div class="text-white-50">

                            <?= e($selectedStudent['student_id']) ?>

                            <?php if (!empty($selectedStudent['section_name'])): ?>

                                · <?= e($selectedStudent['section_name']) ?>

                            <?php endif; ?>

                        </div>

                    </div>

                    <div class="grade-box">

                        <?= e($selectedStudentResult['grade']) ?>

                    </div>

                </div>

            </div>

            <div class="card-body p-4">

                <div class="row align-items-center g-4 mb-4">

                    <div class="col-md-4 text-center">

                        <div class="percentage-circle">

                            <?= e((string) $selectedStudentResult['percentage']) ?>%

                        </div>

                        <div class="small text-muted mt-2">
                            Overall Percentage
                        </div>

                    </div>

                    <div class="col-md-8">

                        <div class="row g-3">

                            <div class="col-6">

                                <div class="border rounded-3 p-3">

                                    <div class="text-muted small">
                                        Total Marks
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= number_format($selectedStudentResult['total_marks'], 2) ?>
                                    </div>

                                </div>

                            </div>

                            <div class="col-6">

                                <div class="border rounded-3 p-3">

                                    <div class="text-muted small">
                                        Obtained Marks
                                    </div>

                                    <div class="fs-4 fw-bold">
                                        <?= number_format($selectedStudentResult['obtained_marks'], 2) ?>
                                    </div>

                                </div>

                            </div>

                            <div class="col-12">

                                <?php if ($selectedStudentResult['status'] === 'Pass'): ?>

                                    <div class="alert alert-success mb-0">

                                        <i class="bi bi-check-circle me-2"></i>

                                        <strong>Pass</strong>

                                        — Student has successfully passed this examination.

                                    </div>

                                <?php else: ?>

                                    <div class="alert alert-danger mb-0">

                                        <i class="bi bi-x-circle me-2"></i>

                                        <strong>Fail</strong>

                                        — Student did not meet the required passing criteria.

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

                <h3 class="h6 fw-bold mb-3">
                    Subject-wise Performance
                </h3>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>
                                <th>Subject</th>
                                <th>Total Marks</th>
                                <th>Obtained</th>
                                <th>Pass Marks</th>
                                <th>Percentage</th>
                                <th>Status</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($selectedStudentResult['subjects'] as $subject): ?>

                            <?php

                            $subjectTotal = (float) $subject['total_marks'];
                            $subjectObtained = (float) $subject['obtained_marks'];
                            $subjectPass = $subject['pass_marks'] !== null
                                ? (float) $subject['pass_marks']
                                : ($subjectTotal * .4);

                            $subjectPercentage = $subjectTotal > 0
                                ? round(($subjectObtained / $subjectTotal) * 100, 1)
                                : 0;

                            $subjectPassed = $subjectObtained >= $subjectPass;

                            ?>

                            <tr class="subject-row">

                                <td class="fw-semibold">
                                    <?= e($subject['subject_name']) ?>
                                </td>

                                <td>
                                    <?= number_format($subjectTotal, 2) ?>
                                </td>

                                <td class="fw-bold">
                                    <?= number_format($subjectObtained, 2) ?>
                                </td>

                                <td>
                                    <?= number_format($subjectPass, 2) ?>
                                </td>

                                <td>
                                    <?= $subjectPercentage ?>%
                                </td>

                                <td>

                                    <?php if ($subjectPassed): ?>

                                        <span class="badge text-bg-success">
                                            <i class="bi bi-check me-1"></i>
                                            Pass
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-danger">
                                            <i class="bi bi-x me-1"></i>
                                            Fail
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

            <div class="card-footer bg-white p-3 text-end no-print">

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    onclick="window.print()"
                >
                    <i class="bi bi-printer me-2"></i>
                    Print Result
                </button>

            </div>

        </div>

    <?php endif; ?>

    <!-- =================================================
         ALL STUDENT RESULTS
    ================================================== -->

    <div class="card shadow-sm border-0">

        <div class="card-header bg-white p-3 no-print">

            <div class="row align-items-center g-3">

                <div class="col-lg-6">

                    <h2 class="h6 fw-bold mb-1">
                        <i class="bi bi-list-check me-2"></i>
                        Student Results
                    </h2>

                    <small class="text-muted">
                        Automatically calculated from entered marks.
                    </small>

                </div>

                <div class="col-lg-6">

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            id="studentSearch"
                            class="form-control"
                            placeholder="Search student..."
                        >

                    </div>

                </div>

            </div>

        </div>

        <?php if ($results === []): ?>

            <div class="empty-state">

                <div class="empty-icon mb-3">
                    <i class="bi bi-bar-chart"></i>
                </div>

                <h4 class="fw-bold">
                    No Results Available
                </h4>

                <p class="text-muted mb-0">
                    Marks have not been entered for any student in this examination yet.
                </p>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table table-hover mb-0 result-table">

                    <thead class="table-light">

                        <tr>

                            <th class="px-4">
                                #
                            </th>

                            <th>
                                Student
                            </th>

                            <th>
                                Class
                            </th>

                            <th class="text-center">
                                Total
                            </th>

                            <th class="text-center">
                                Obtained
                            </th>

                            <th class="text-center">
                                Percentage
                            </th>

                            <th class="text-center">
                                Grade
                            </th>

                            <th class="text-center">
                                Result
                            </th>

                            <th class="text-end pe-4 no-print">
                                Action
                            </th>

                        </tr>

                    </thead>

                    <tbody id="resultTableBody">

                    <?php $counter = 1; ?>

                    <?php foreach ($results as $row): ?>

                        <?php

                        $student = $row['student'];
                        $result = $row['result'];

                        ?>

                        <tr
                            class="result-row"
                            data-search="<?= e(strtolower(
                                $student['name'] . ' ' . $student['student_id']
                            )) ?>"
                        >

                            <td class="px-4 text-muted">
                                <?= $counter++ ?>
                            </td>

                            <td>

                                <div class="d-flex align-items-center gap-3">

                                    <div class="student-avatar">

                                        <?= e(
                                            strtoupper(
                                                substr($student['name'], 0, 1)
                                            )
                                        ) ?>

                                    </div>

                                    <div>

                                        <div class="fw-semibold">
                                            <?= e($student['name']) ?>
                                        </div>

                                        <small class="text-muted">
                                            <?= e($student['student_id']) ?>
                                        </small>

                                    </div>

                                </div>

                            </td>

                            <td>

                                <?= e($student['class_name']) ?>

                                <?php if (!empty($student['section_name'])): ?>

                                    <small class="text-muted d-block">
                                        Section <?= e($student['section_name']) ?>
                                    </small>

                                <?php endif; ?>

                            </td>

                            <td class="text-center">
                                <?= number_format($result['total_marks'], 2) ?>
                            </td>

                            <td class="text-center fw-bold">
                                <?= number_format($result['obtained_marks'], 2) ?>
                            </td>

                            <td class="text-center">
                                <?= e((string) $result['percentage']) ?>%
                            </td>

                            <td class="text-center">

                                <span class="badge text-bg-primary fs-6">
                                    <?= e($result['grade']) ?>
                                </span>

                            </td>

                            <td class="text-center">

                                <?php if ($result['status'] === 'Pass'): ?>

                                    <span class="badge text-bg-success">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Pass
                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-danger">
                                        <i class="bi bi-x-circle me-1"></i>
                                        Fail
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="text-end pe-4 no-print">

                                <a
                                    href="<?= e(
                                        url(
                                            'admin/results/index.php'
                                        )
                                        . '?exam_id='
                                        . $selectedExamId
                                        . '&student_id='
                                        . (int) $student['id']
                                    ) ?>"
                                    class="btn btn-sm btn-outline-primary"
                                >

                                    <i class="bi bi-eye me-1"></i>
                                    View

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <tr id="noSearchResults" style="display:none;">

                        <td
                            colspan="9"
                            class="text-center text-muted py-5"
                        >

                            <i class="bi bi-search fs-2 d-block mb-2"></i>

                            No students match your search.

                        </td>

                    </tr>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

<?php endif; ?>

<script>

function openSelectedExam() {

    const selector = document.getElementById('examSelector');

    const examId = selector.value;

    if (!examId) {

        alert('Please select an examination first.');

        return;
    }

    window.location.href =
        '<?= e(url('admin/results/index.php')) ?>?exam_id='
        + encodeURIComponent(examId);
}


// =====================================================
// STUDENT SEARCH
// =====================================================

const searchInput = document.getElementById('studentSearch');

if (searchInput) {

    searchInput.addEventListener('input', function () {

        const search = this.value.toLowerCase().trim();

        const rows = document.querySelectorAll('.result-row');

        let visible = 0;

        rows.forEach(function (row) {

            const value = row.dataset.search || '';

            if (value.includes(search)) {

                row.style.display = '';

                visible++;

            } else {

                row.style.display = 'none';

            }

        });

        const empty = document.getElementById('noSearchResults');

        if (empty) {

            empty.style.display =
                visible === 0 ? '' : 'none';

        }

    });

}

</script>

<?php layout_end(); ?>

