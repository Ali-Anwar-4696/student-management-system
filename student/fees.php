<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../classes/Fee.php';

requireStudent();

$pdo = db();
$student = current_student($pdo);

if (!$student) {
    exit('Student profile not found.');
}

/*
 * Refresh overdue marking for this student's fees so a
 * past-due fee never keeps showing as "pending".
 */
$feeModel = new Fee($pdo);
$feeModel->refreshOverdue((int) $student['id']);

/*
 * The real payment date lives on fee_payments, not on the
 * fee row itself.
 */
$stmt = $pdo->prepare("
    SELECT
        f.id,
        f.fee_type,
        f.amount,
        f.due_date,
        f.status,
        (
            SELECT MAX(p.payment_date)
            FROM fee_payments p
            WHERE p.fee_id = f.id
        ) AS payment_date
    FROM fees f
    WHERE f.student_id = ?
    ORDER BY f.due_date DESC
");
$stmt->execute([(int)$student['id']]);
$fees = $stmt->fetchAll(PDO::FETCH_ASSOC);

layout_start('My Fees', 'fees');
?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">Fee Records</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fee Type</th>
                        <th>Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Payment Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fees)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">No fee records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fees as $fee): ?>
                            <tr>
                                <td><?= htmlspecialchars($fee['fee_type']) ?></td>
                                <td>$<?= number_format((float)$fee['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($fee['due_date']) ?></td>
                                <td>
                                    <?php if ($fee['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($fee['status'] === 'partial'): ?>
                                        <span class="badge bg-info">Partial</span>
                                    <?php elseif ($fee['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $fee['payment_date'] ? htmlspecialchars(date('M d, Y', strtotime($fee['payment_date']))) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php layout_end(); ?>
