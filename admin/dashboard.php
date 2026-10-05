<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
requireAdmin();

$pdo = db();

$counts = [
    'students' => (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'teachers' => (int) $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
    'classes' => (int) $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'subjects' => (int) $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn(),
    'assignments' => (int) $pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn(),
    'exams' => (int) $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn(),
];

require_once __DIR__ . '/../classes/Fee.php';
$fee = new Fee($pdo);
$fee->refreshOverdue();
$feeSummary = $fee->summary();

$recentStudents = $pdo->query("SELECT student_id, name, status, created_at FROM students ORDER BY id DESC LIMIT 5")->fetchAll();

// Chart data: students per class (pending + active count as enrolled)
$studentsPerClass = $pdo->query("
    SELECT c.name AS class_name, COUNT(s.id) AS total
    FROM classes c
    LEFT JOIN students s ON s.class_id = c.id AND s.status IN ('pending','active')
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
")->fetchAll();

layout_start('Admin Dashboard', 'dashboard');
?>
<div class="row g-3 mb-4">
    <?php foreach ([['students','Students','people'],['teachers','Teachers','person-badge'],['classes','Classes','building'],['subjects','Subjects','book'],['assignments','Assignments','journal'],['exams','Exams','award']] as $card): ?>
        <div class="col-6 col-lg-2">
            <div class="card stat-card p-3">
                <div class="text-muted small"><i class="bi bi-<?= e($card[2]) ?>"></i> <?= e($card[1]) ?></div>
                <div class="fs-3 fw-bold"><?= (int) $counts[$card[0]] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card stat-card p-3">
            <h2 class="h6">Students per class</h2>
            <canvas id="studentsPerClassChart" height="150"></canvas>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card stat-card p-3">
            <h2 class="h6">Fee collection</h2>
            <canvas id="feeCollectionChart" height="190"></canvas>
        </div>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card stat-card p-3">
            <h2 class="h6">Fee overview</h2>
            <p class="mb-1">Billed: <?= number_format($feeSummary['billed'], 2) ?></p>
            <p class="mb-1">Collected: <?= number_format($feeSummary['collected'], 2) ?></p>
            <p class="mb-0">Outstanding: <?= number_format($feeSummary['outstanding'], 2) ?></p>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card stat-card p-3">
            <h2 class="h6">Recent students</h2>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>ID</th><th>Name</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentStudents as $row): ?>
                        <tr>
                            <td><?= e($row['student_id']) ?></td>
                            <td><?= e($row['name']) ?></td>
                            <td><?= e($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($recentStudents === []): ?>
                        <tr><td colspan="3" class="text-muted">No students yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    var classLabels = <?= json_encode(array_column($studentsPerClass, 'class_name')) ?>;
    var classTotals = <?= json_encode(array_map('intval', array_column($studentsPerClass, 'total'))) ?>;
    var el1 = document.getElementById('studentsPerClassChart');

    if (el1) {
        new Chart(el1, {
            type: 'bar',
            data: {
                labels: classLabels,
                datasets: [{
                    label: 'Students',
                    data: classTotals,
                    backgroundColor: '#6366f1',
                    borderRadius: 6
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    var collected = <?= json_encode(round((float) $feeSummary['collected'], 2)) ?>;
    var outstanding = <?= json_encode(round((float) $feeSummary['outstanding'], 2)) ?>;
    var el2 = document.getElementById('feeCollectionChart');

    if (el2) {
        if (collected > 0 || outstanding > 0) {
            new Chart(el2, {
                type: 'doughnut',
                data: {
                    labels: ['Collected', 'Outstanding'],
                    datasets: [{
                        data: [collected, outstanding],
                        backgroundColor: ['#16a34a', '#f59e0b']
                    }]
                },
                options: {
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        } else {
            el2.replaceWith(Object.assign(document.createElement('p'), {
                className: 'text-muted small mb-0',
                textContent: 'No fee data yet.'
            }));
        }
    }
})();
</script>
<?php layout_end(); ?>
