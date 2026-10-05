<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

requireStudent();

$pdo = db();
$student = current_student($pdo);

if (!$student) {
    exit('Student profile not found.');
}

layout_start('My Profile', 'profile');
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center">
                <i class="bi bi-person-circle fs-4 me-2 text-primary"></i>
                <h5 class="card-title mb-0">Student Profile Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr>
                            <th class="w-25 bg-light">Student ID</th>
                            <td><?= htmlspecialchars($student['student_id'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Full Name</th>
                            <td><?= htmlspecialchars($student['name'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Father's Name</th>
                            <td><?= htmlspecialchars($student['father_name'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Date of Birth</th>
                            <td><?= htmlspecialchars($student['date_of_birth'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Gender</th>
                            <td><?= htmlspecialchars(ucfirst($student['gender'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Email Address</th>
                            <td><?= htmlspecialchars($student['email'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Phone Number</th>
                            <td><?= htmlspecialchars($student['phone'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Address</th>
                            <td><?= nl2br(htmlspecialchars($student['address'] ?? '')) ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Account Status</th>
                            <td>
                                <?php if (($student['status'] ?? '') === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><?= htmlspecialchars(ucfirst($student['status'] ?? 'Unknown')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php layout_end(); ?>
