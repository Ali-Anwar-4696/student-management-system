<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Assignment.php';

requireAdmin();

$pdo = db();
$assignmentManager = new Assignment($pdo);

// =====================================================
// FILTERS
// =====================================================

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$classId = isset($_GET['class_id']) && ctype_digit((string) $_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;
$teacherId = isset($_GET['teacher_id']) && ctype_digit((string) $_GET['teacher_id'])
    ? (int) $_GET['teacher_id']
    : 0;

// =====================================================
// GET ALL ASSIGNMENTS (with teacher info)
// =====================================================

$sql = "
    SELECT
        a.id,
        a.teacher_id,
        a.subject_id,
        a.class_id,
        a.section_id,
        a.title,
        a.description,
        a.due_date,
        a.status,
        a.created_at,
        a.updated_at,

        t.name AS teacher_name,
        t.teacher_id AS teacher_code,

        s.name AS subject_name,
        s.code AS subject_code,

        c.name AS class_name,

        COALESCE(sec.name, 'Whole Class') AS section_name,

        (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id) AS submission_count,

        (SELECT COUNT(*) FROM submissions sub WHERE sub.assignment_id = a.id AND sub.status = 'graded') AS graded_count

    FROM assignments a

    INNER JOIN teachers t
        ON a.teacher_id = t.id

    INNER JOIN subjects s
        ON a.subject_id = s.id

    INNER JOIN classes c
        ON a.class_id = c.id

    LEFT JOIN sections sec
        ON a.section_id = sec.id
        AND sec.class_id = a.class_id

    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    /*
    | PDO runs with emulated prepares off, so a named placeholder may
    | only appear once per statement. Reusing :search here used to raise
    | a 500 on this page whenever a search was submitted.
    */
    $sql .= " AND (a.title LIKE :search1 OR t.name LIKE :search2 OR s.name LIKE :search3 OR c.name LIKE :search4)";
    $params[':search1'] = '%' . $search . '%';
    $params[':search2'] = '%' . $search . '%';
    $params[':search3'] = '%' . $search . '%';
    $params[':search4'] = '%' . $search . '%';
}

if (in_array($status, ['active', 'closed'], true)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $status;
}

if ($classId > 0) {
    $sql .= " AND a.class_id = :class_id";
    $params[':class_id'] = $classId;
}

if ($teacherId > 0) {
    $sql .= " AND a.teacher_id = :teacher_id";
    $params[':teacher_id'] = $teacherId;
}

// =====================================================
// PAGINATION
//
// The list previously rendered every assignment on the page, so both the
// response size and the render cost grew with the whole table. It is now
// served one page at a time with an explicit row count.
// =====================================================

$assignmentsPerPage = 25;

$assignmentsPage = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

$assignmentsPage = max(1, $assignmentsPage);

/*
| The filter clause is shared so the row count, the status breakdown and
| the page of rows can never disagree about which rows are in scope.
*/

$filterSql = '';

if ($search !== '') {
    $filterSql .= " AND (a.title LIKE :search1 OR t.name LIKE :search2 OR s.name LIKE :search3 OR c.name LIKE :search4)";
}

if (in_array($status, ['active', 'closed'], true)) {
    $filterSql .= " AND a.status = :status";
}

if ($classId > 0) {
    $filterSql .= " AND a.class_id = :class_id";
}

if ($teacherId > 0) {
    $filterSql .= " AND a.teacher_id = :teacher_id";
}

$fromSql = "
    FROM assignments a

    INNER JOIN teachers t
        ON a.teacher_id = t.id

    INNER JOIN subjects s
        ON a.subject_id = s.id

    INNER JOIN classes c
        ON a.class_id = c.id

    LEFT JOIN sections sec
        ON a.section_id = sec.id
        AND sec.class_id = a.class_id
";

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) " . $fromSql . " WHERE 1 = 1 " . $filterSql
);

$countStmt->execute($params);

$totalAssignments = (int) $countStmt->fetchColumn();

$assignmentsPages = max(
    1,
    (int) ceil($totalAssignments / $assignmentsPerPage)
);

if ($assignmentsPage > $assignmentsPages) {
    $assignmentsPage = $assignmentsPages;
}

$sql .= " ORDER BY a.created_at DESC, a.id DESC";
$sql .= " LIMIT " . (int) $assignmentsPerPage;
$sql .= " OFFSET " . (int) (($assignmentsPage - 1) * $assignmentsPerPage);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// GET CLASSES FOR FILTER
// =====================================================

$classes = $pdo->query("SELECT id, name FROM classes WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// GET TEACHERS FOR FILTER
// =====================================================

$teachers = $pdo->query("SELECT id, teacher_id, name FROM teachers WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// STATISTICS
// =====================================================

// Status breakdown is counted over the whole filtered set, not just the
// rows that happen to land on the current page.

$statusStatsStmt = $pdo->prepare(
    "SELECT a.status, COUNT(*) AS total "
    . $fromSql
    . " WHERE 1 = 1 "
    . $filterSql
    . " GROUP BY a.status"
);

$statusStatsStmt->execute($params);

$activeAssignments = 0;
$closedAssignments = 0;

foreach ($statusStatsStmt->fetchAll(PDO::FETCH_ASSOC) as $statusStat) {
    if ($statusStat['status'] === 'active') {
        $activeAssignments += (int) $statusStat['total'];
    } else {
        $closedAssignments += (int) $statusStat['total'];
    }
}

layout_start('Assignments', 'assignments');
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="text-muted small"><i class="bi bi-journal-text"></i> Total</div>
            <div class="fs-3 fw-bold"><?= $totalAssignments ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="text-muted small"><i class="bi bi-check-circle"></i> Active</div>
            <div class="fs-3 fw-bold text-success"><?= $activeAssignments ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card p-3">
            <div class="text-muted small"><i class="bi bi-x-circle"></i> Closed</div>
            <div class="fs-3 fw-bold text-danger"><?= $closedAssignments ?></div>
        </div>
    </div>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
        <input class="form-control" name="search" value="<?= e($search) ?>" placeholder="Search assignments...">
    </div>
    <div class="col-md-2">
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="class_id" class="form-select">
            <option value="">All classes</option>
            <?php foreach ($classes as $class): ?>
                <option value="<?= (int) $class['id'] ?>" <?= $classId === (int) $class['id'] ? 'selected' : '' ?>><?= e($class['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <select name="teacher_id" class="form-select">
            <option value="">All teachers</option>
            <?php foreach ($teachers as $teacher): ?>
                <option value="<?= (int) $teacher['id'] ?>" <?= $teacherId === (int) $teacher['id'] ? 'selected' : '' ?>><?= e($teacher['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-outline-primary w-100">Filter</button>
    </div>
</form>

<div class="card stat-card p-3 table-responsive">
    <table class="table mb-0">
        <thead>
            <tr>
                <th>Title</th>
                <th>Teacher</th>
                <th>Subject</th>
                <th>Class</th>
                <th>Section</th>
                <th>Due Date</th>
                <th>Submissions</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($assignments)): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No assignments found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?= e($assignment['title']) ?></td>
                        <td><?= e($assignment['teacher_name']) ?></td>
                        <td><?= e($assignment['subject_name']) ?></td>
                        <td><?= e($assignment['class_name']) ?></td>
                        <td><?= e($assignment['section_name']) ?></td>
                        <td><?= e(date('M d, Y', strtotime($assignment['due_date']))) ?></td>
                        <td>
                            <?= (int) $assignment['graded_count'] ?> / <?= (int) $assignment['submission_count'] ?>
                        </td>
                        <td>
                            <?php if ($assignment['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Closed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($totalAssignments > $assignmentsPerPage): ?>

        <?php
        $assignmentPageQs = static function (int $target) use ($search, $status, $classId, $teacherId): string {
            $params = [];

            if ($search !== '') {
                $params['search'] = $search;
            }

            if ($status !== '') {
                $params['status'] = $status;
            }

            if ($classId > 0) {
                $params['class_id'] = $classId;
            }

            if ($teacherId > 0) {
                $params['teacher_id'] = $teacherId;
            }

            $params['page'] = $target;

            return '?' . http_build_query($params);
        };
        ?>

        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">

            <small class="text-muted">

                Showing
                <?= (int) (($assignmentsPage - 1) * $assignmentsPerPage + 1) ?>–<?= min($totalAssignments, $assignmentsPage * $assignmentsPerPage) ?>
                of <?= $totalAssignments ?>
                assignments (page <?= $assignmentsPage ?> of <?= $assignmentsPages ?>)

            </small>

            <nav aria-label="Assignment list pages">

                <ul class="pagination pagination-sm mb-0">

                    <li class="page-item <?= $assignmentsPage <= 1 ? 'disabled' : '' ?>">

                        <a class="page-link" href="<?= htmlspecialchars($assignmentPageQs(max(1, $assignmentsPage - 1))) ?>">
                            Previous
                        </a>

                    </li>

                    <?php
                    $assignFrom = max(1, $assignmentsPage - 2);
                    $assignTo = min($assignmentsPages, $assignmentsPage + 2);

                    for ($p = $assignFrom; $p <= $assignTo; $p++):
                    ?>

                        <li class="page-item <?= $p === $assignmentsPage ? 'active' : '' ?>">

                            <a class="page-link" href="<?= htmlspecialchars($assignmentPageQs($p)) ?>">
                                <?= $p ?>
                            </a>

                        </li>

                    <?php endfor; ?>

                    <li class="page-item <?= $assignmentsPage >= $assignmentsPages ? 'disabled' : '' ?>">

                        <a class="page-link" href="<?= htmlspecialchars($assignmentPageQs(min($assignmentsPages, $assignmentsPage + 1))) ?>">
                            Next
                        </a>

                    </li>

                </ul>

            </nav>

        </div>

    <?php endif; ?>
</div>

<?php layout_end(); ?>
