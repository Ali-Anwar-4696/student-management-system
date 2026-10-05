<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../classes/Fee.php';

requireAdmin();

$pdo = db();
$feeManager = new Fee($pdo);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function redirectToFees(array $params = []): never
{
    $url = url('admin/fees/index.php');

    if ($params !== []) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}

function money(float $amount): string
{
    return number_format($amount, 2);
}

/*
|--------------------------------------------------------------------------
| Refresh overdue fees
|--------------------------------------------------------------------------
*/

$feeManager->refreshOverdue();

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$studentFilter = trim((string) ($_GET['student_id'] ?? ''));
$studentQuery = trim((string) ($_GET['student_q'] ?? ''));

if (!in_array($status, ['pending', 'partial', 'paid', 'overdue'], true)) {
    $status = '';
}

// -------------------------------------------------
// Pagination
// -------------------------------------------------

$perPage = 25;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page);

// -------------------------------------------------
// Filter preservation across POST redirects
// -------------------------------------------------

$currentFilters = static function (array $extra = []): array {
    $params = [];

    if (isset($GLOBALS['search']) && $GLOBALS['search'] !== '') {
        $params['search'] = $GLOBALS['search'];
    }
    if (isset($GLOBALS['status']) && $GLOBALS['status'] !== '') {
        $params['status'] = $GLOBALS['status'];
    }
    if (isset($GLOBALS['studentFilter']) && $GLOBALS['studentFilter'] !== '') {
        $params['student_id'] = $GLOBALS['studentFilter'];
    }
    if (isset($GLOBALS['studentQuery']) && $GLOBALS['studentQuery'] !== '') {
        $params['student_q'] = $GLOBALS['studentQuery'];
    }

    return array_merge($params, $extra);
};

/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_post_csrf();

    /*
    |--------------------------------------------------------------------------
    | Create Fee
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['create_fee'])) {

        $studentId = isset($_POST['student_id'])
            ? (int) $_POST['student_id']
            : 0;

        $feeType = trim((string) ($_POST['fee_type'] ?? ''));
        $amount = isset($_POST['amount'])
            ? (float) $_POST['amount']
            : 0;

        $dueDate = trim((string) ($_POST['due_date'] ?? ''));

        if ($studentId <= 0) {
            flash_set('danger', 'Please select a student.');
            redirectToFees($currentFilters());
        }

        if ($feeType === '') {
            flash_set('danger', 'Please enter a fee type.');
            redirectToFees($currentFilters());
        }

        if ($amount <= 0) {
            flash_set('danger', 'Fee amount must be greater than zero.');
            redirectToFees($currentFilters());
        }

        if ($dueDate === '') {
            flash_set('danger', 'Please select a due date.');
            redirectToFees($currentFilters());
        }

        // A malformed date must be rejected here; otherwise it is stored
        // as the zero date and corrupts overdue/pending calculations.
        $dueDateParsed = DateTime::createFromFormat('Y-m-d', $dueDate);

        if (
            $dueDateParsed === false
            || $dueDateParsed->format('Y-m-d') !== $dueDate
        ) {
            flash_set('danger', 'Please enter a valid due date.');
            redirectToFees($currentFilters());
        }

        if ($feeManager->duplicateExists(
            $studentId,
            $feeType,
            $dueDate
        )) {
            flash_set(
                'warning',
                'A fee with the same type and due date already exists for this student.'
            );

            redirectToFees($currentFilters());
        }

        try {
            $created = $feeManager->create([
                'student_id' => $studentId,
                'fee_type' => $feeType,
                'amount' => $amount,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            if ($created) {
                flash_set('success', 'Fee has been created successfully.');
            } else {
                flash_set('danger', 'Unable to create fee.');
            }
        } catch (Throwable $e) {
            flash_set('danger', 'An error occurred while creating the fee.');
        }

        redirectToFees($currentFilters());
    }

    /*
    |--------------------------------------------------------------------------
    | Add Payment
    |--------------------------------------------------------------------------
    */

    if (isset($_POST['add_payment'])) {

        $feeId = isset($_POST['fee_id'])
            ? (int) $_POST['fee_id']
            : 0;

        $studentId = isset($_POST['student_id'])
            ? (int) $_POST['student_id']
            : 0;

        $amount = isset($_POST['payment_amount'])
            ? (float) $_POST['payment_amount']
            : 0;

        $paymentDate = trim(
            (string) ($_POST['payment_date'] ?? date('Y-m-d'))
        );

        $paymentMethod = trim(
            (string) ($_POST['payment_method'] ?? '')
        );

        $reference = trim(
            (string) ($_POST['transaction_reference'] ?? '')
        );

        if ($feeId <= 0 || $studentId <= 0) {
            flash_set('danger', 'Invalid fee or student.');
            redirectToFees();
        }

        if ($amount <= 0) {
            flash_set('danger', 'Payment amount must be greater than zero.');
            redirectToFees();
        }

        if ($paymentDate === '') {
            flash_set('danger', 'Please select payment date.');
            redirectToFees();
        }

        $paymentDateParsed = DateTime::createFromFormat(
            'Y-m-d',
            $paymentDate
        );

        if (
            !$paymentDateParsed
            || $paymentDateParsed->format('Y-m-d') !== $paymentDate
        ) {
            flash_set('danger', 'Please enter a valid payment date.');
            redirectToFees();
        }

        if ($paymentDate > date('Y-m-d')) {
            flash_set('danger', 'Payment date cannot be in the future.');
            redirectToFees();
        }

        if (!in_array(
            $paymentMethod,
            ['cash', 'bank', 'online', 'card', 'other'],
            true
        )) {
            flash_set('danger', 'Invalid payment method.');
            redirectToFees();
        }

        $fee = $feeManager->getById($feeId);

        if ($fee === null) {
            flash_set('danger', 'Fee record not found.');
            redirectToFees();
        }

        $alreadyPaid = 0.0;

        foreach ($feeManager->paymentsForFee($feeId) as $payment) {
            $alreadyPaid += (float) $payment['amount'];
        }

        $remaining = max(
            (float) $fee['amount'] - $alreadyPaid,
            0
        );

        if ($remaining <= 0) {
            flash_set('warning', 'This fee is already fully paid.');
            redirectToFees();
        }

        if ($amount > $remaining) {
            flash_set(
                'warning',
                'Payment cannot be greater than the remaining amount.'
            );

            redirectToFees();
        }

        try {
            $saved = $feeManager->addPayment(
                $feeId,
                $studentId,
                $amount,
                $paymentDate,
                $paymentMethod,
                $reference !== '' ? $reference : null
            );

            if ($saved) {

                /*
                | Non-blocking notification — silent no-op until
                | mail_enabled/sms_enabled are configured.
                */
                notify_student(
                    (int) $fee['student_id'],
                    'Fee payment recorded',
                    "Hello {name},\n\n"
                    . "A payment of " . number_format($amount, 2)
                    . " has been recorded for \""
                    . (string) ($fee['fee_type'] ?? 'fee')
                    . "\" (due " . (string) ($fee['due_date'] ?? 'n/a')
                    . ").\n\n- School Administration",
                    'Payment of ' . number_format($amount, 2)
                    . ' recorded for '
                    . (string) ($fee['fee_type'] ?? 'your fees') . '.'
                );

                /*
                | Mirror the event into the in-app notifications centre.
                | Mail/SMS stay silent until configured, so without this
                | the student and guardian would see no record at all.
                */
                notify_student_in_app(
                    (int) $fee['student_id'],
                    'Fee payment recorded',
                    'Hello {name}, a payment of '
                    . number_format($amount, 2)
                    . ' has been recorded for "'
                    . (string) ($fee['fee_type'] ?? 'fee')
                    . '" (due ' . (string) ($fee['due_date'] ?? 'n/a')
                    . '). Thank you.',
                    'fee',
                    'normal',
                    'student/fees.php',
                    isset($_SESSION['user_id'])
                        ? (int) $_SESSION['user_id']
                        : null
                );

                flash_set(
                    'success',
                    'Payment has been recorded successfully.'
                );
            } else {
                flash_set(
                    'danger',
                    'Unable to record payment.'
                );
            }
        } catch (Throwable $e) {
            flash_set(
                'danger',
                'An error occurred while recording payment.'
            );
        }

        redirectToFees();
    }
}

/*
|--------------------------------------------------------------------------
| Students
|--------------------------------------------------------------------------
*/

$students = $feeManager->studentsForPicker(
    $studentQuery,
    100
);

$studentPickerTotal = count($students);

/*
|--------------------------------------------------------------------------
| Fee List (paged)
|--------------------------------------------------------------------------
*/

$totalFiltered = $feeManager->countFiltered(
    $search,
    $status,
    $studentFilter
);

$totalPages = max(1, (int) ceil($totalFiltered / $perPage));

if ($page > $totalPages) {
    $page = $totalPages;
}

$fees = $feeManager->list(
    $search,
    $status,
    $studentFilter,
    $perPage,
    ($page - 1) * $perPage
);

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalBilled = 0.0;
$totalPaid = 0.0;
$totalOutstanding = 0.0;

$pendingCount = 0;
$partialCount = 0;
$paidCount = 0;
$overdueCount = 0;

foreach ($fees as $fee) {

    $amount = (float) $fee['amount'];
    $paid = (float) $fee['paid_amount'];
    $outstanding = max($amount - $paid, 0);

    $totalBilled += $amount;
    $totalPaid += $paid;
    $totalOutstanding += $outstanding;

    switch ($fee['status']) {
        case 'pending':
            $pendingCount++;
            break;

        case 'partial':
            $partialCount++;
            break;

        case 'paid':
            $paidCount++;
            break;

        case 'overdue':
            $overdueCount++;
            break;
    }
}

/*
|--------------------------------------------------------------------------
| Selected Fee
|--------------------------------------------------------------------------
*/

$selectedFeeId = isset($_GET['view'])
    && ctype_digit((string) $_GET['view'])
    ? (int) $_GET['view']
    : 0;

$selectedFee = null;
$selectedPayments = [];
$selectedRemaining = 0.0;

if ($selectedFeeId > 0) {

    $selectedFee = $feeManager->getById($selectedFeeId);

    if ($selectedFee !== null) {

        $selectedPayments = $feeManager->paymentsForFee(
            $selectedFeeId
        );

        $selectedPaid = 0.0;

        foreach ($selectedPayments as $payment) {
            $selectedPaid += (float) $payment['amount'];
        }

        $selectedRemaining = max(
            (float) $selectedFee['amount'] - $selectedPaid,
            0
        );
    }
}

layout_start('Fee Management', 'fees');

?>

<style>
    .fee-hero {
        border-radius: 18px;
        background: linear-gradient(135deg, #0f766e 0%, #134e4a 100%);
        color: #fff;
        overflow: hidden;
        position: relative;
    }

    .fee-hero::after {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(255,255,255,.06);
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
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f0fdfa;
        color: #0f766e;
        font-size: 20px;
    }

    .fee-card {
        border: 0;
        border-radius: 18px;
    }

    .student-avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #f0fdfa;
        color: #0f766e;
        font-weight: 700;
    }

    .fee-table th {
        white-space: nowrap;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .fee-table td {
        vertical-align: middle;
    }

    .amount-main {
        font-weight: 700;
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

    .detail-box {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 16px;
        height: 100%;
    }

    .payment-history {
        max-height: 350px;
        overflow-y: auto;
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
    }
</style>


<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 no-print">

    <div>
        <h1 class="h3 fw-bold mb-1">
            <i class="bi bi-cash-stack me-2"></i>
            Fee Management
        </h1>

        <p class="text-muted mb-0">
            Manage student fees, payments, outstanding balances and fee records.
        </p>
    </div>

    <button
        type="button"
        class="btn btn-primary"
        data-bs-toggle="modal"
        data-bs-target="#createFeeModal"
    >
        <i class="bi bi-plus-circle me-2"></i>
        Create Fee
    </button>

</div>


<!-- =========================================================
     SUMMARY
========================================================= -->

<div class="row g-3 mb-4">

    <div class="col-md-3">
        <div class="stat-box shadow-sm">

            <div class="d-flex justify-content-between align-items-center">

                <div>
                    <div class="text-muted small">
                        Total Billed
                    </div>

                    <div class="fs-4 fw-bold">
                        <?= money($totalBilled) ?>
                    </div>
                </div>

                <div class="stat-icon">
                    <i class="bi bi-receipt"></i>
                </div>

            </div>

        </div>
    </div>


    <div class="col-md-3">
        <div class="stat-box shadow-sm">

            <div class="d-flex justify-content-between align-items-center">

                <div>
                    <div class="text-muted small">
                        Collected
                    </div>

                    <div class="fs-4 fw-bold text-success">
                        <?= money($totalPaid) ?>
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
                        Outstanding
                    </div>

                    <div class="fs-4 fw-bold text-danger">
                        <?= money($totalOutstanding) ?>
                    </div>
                </div>

                <div class="stat-icon">
                    <i class="bi bi-wallet2"></i>
                </div>

            </div>

        </div>
    </div>


    <div class="col-md-3">
        <div class="stat-box shadow-sm">

            <div class="d-flex justify-content-between align-items-center">

                <div>
                    <div class="text-muted small">
                        Overdue
                    </div>

                    <div class="fs-4 fw-bold text-danger">
                        <?= $overdueCount ?>
                    </div>
                </div>

                <div class="stat-icon">
                    <i class="bi bi-exclamation-circle"></i>
                </div>

            </div>

        </div>
    </div>

</div>


<!-- =========================================================
     STATUS OVERVIEW
========================================================= -->

<div class="card fee-card shadow-sm mb-4 no-print">

    <div class="card-body">

        <div class="row g-3">

            <div class="col-6 col-md-3">

                <div class="border rounded-3 p-3">

                    <div class="small text-muted">
                        Pending
                    </div>

                    <div class="fs-4 fw-bold text-warning">
                        <?= $pendingCount ?>
                    </div>

                </div>

            </div>


            <div class="col-6 col-md-3">

                <div class="border rounded-3 p-3">

                    <div class="small text-muted">
                        Partial
                    </div>

                    <div class="fs-4 fw-bold text-primary">
                        <?= $partialCount ?>
                    </div>

                </div>

            </div>


            <div class="col-6 col-md-3">

                <div class="border rounded-3 p-3">

                    <div class="small text-muted">
                        Paid
                    </div>

                    <div class="fs-4 fw-bold text-success">
                        <?= $paidCount ?>
                    </div>

                </div>

            </div>


            <div class="col-6 col-md-3">

                <div class="border rounded-3 p-3">

                    <div class="small text-muted">
                        Overdue
                    </div>

                    <div class="fs-4 fw-bold text-danger">
                        <?= $overdueCount ?>
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =========================================================
     FILTERS
========================================================= -->

<div class="card shadow-sm border-0 mb-4 no-print">

    <div class="card-body p-4">

        <form method="get">

            <div class="row g-3 align-items-end">

                <div class="col-lg-5">

                    <label class="form-label fw-semibold">
                        Search
                    </label>

                    <div class="input-group">

                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            value="<?= e($search) ?>"
                            placeholder="Student name, ID or fee type..."
                        >

                    </div>

                </div>


                <div class="col-lg-3">

                    <label class="form-label fw-semibold">
                        Status
                    </label>

                    <select name="status" class="form-select">

                        <option value="">
                            All Statuses
                        </option>

                        <option
                            value="pending"
                            <?= $status === 'pending' ? 'selected' : '' ?>
                        >
                            Pending
                        </option>

                        <option
                            value="partial"
                            <?= $status === 'partial' ? 'selected' : '' ?>
                        >
                            Partial
                        </option>

                        <option
                            value="paid"
                            <?= $status === 'paid' ? 'selected' : '' ?>
                        >
                            Paid
                        </option>

                        <option
                            value="overdue"
                            <?= $status === 'overdue' ? 'selected' : '' ?>
                        >
                            Overdue
                        </option>

                    </select>

                </div>


                <div class="col-lg-2">

                    <label class="form-label fw-semibold">
                        Student
                    </label>

                    <select name="student_id" class="form-select">

                        <option value="">
                            All Students
                        </option>

                        <?php foreach ($students as $student): ?>

                            <option
                                value="<?= (int) $student['id'] ?>"
                                <?= $studentFilter === (string) $student['id'] ? 'selected' : '' ?>
                            >
                                <?= e($student['name']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-lg-2 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary flex-grow-1"
                    >
                        <i class="bi bi-funnel me-1"></i>
                        Filter
                    </button>

                    <a
                        href="<?= e(url('admin/fees/index.php')) ?>"
                        class="btn btn-outline-secondary"
                        title="Reset"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     SELECTED FEE DETAIL
========================================================= -->

<?php if ($selectedFee !== null): ?>

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header bg-white p-4">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <div class="small text-muted">
                        FEE DETAILS
                    </div>

                    <h2 class="h5 fw-bold mb-0">
                        <?= e($selectedFee['fee_type']) ?>
                    </h2>

                </div>

                <div class="no-print">

                    <a
                        href="<?= e(url('admin/fees/index.php')) ?>"
                        class="btn btn-sm btn-outline-secondary"
                    >
                        <i class="bi bi-x-lg me-1"></i>
                        Close
                    </a>

                </div>

            </div>

        </div>


        <div class="card-body p-4">

            <div class="row g-3 mb-4">

                <div class="col-md-3">

                    <div class="detail-box">

                        <div class="small text-muted">
                            Student
                        </div>

                        <div class="fw-bold">
                            <?= e($selectedFee['student_name']) ?>
                        </div>

                        <small class="text-muted">
                            <?= e($selectedFee['student_code']) ?>
                        </small>

                    </div>

                </div>


                <div class="col-md-2">

                    <div class="detail-box">

                        <div class="small text-muted">
                            Fee Amount
                        </div>

                        <div class="fs-5 fw-bold">
                            <?= money((float) $selectedFee['amount']) ?>
                        </div>

                    </div>

                </div>


                <div class="col-md-2">

                    <div class="detail-box">

                        <div class="small text-muted">
                            Paid
                        </div>

                        <div class="fs-5 fw-bold text-success">
                            <?= money(
                                (float) $selectedFee['amount'] - $selectedRemaining
                            ) ?>
                        </div>

                    </div>

                </div>


                <div class="col-md-2">

                    <div class="detail-box">

                        <div class="small text-muted">
                            Remaining
                        </div>

                        <div class="fs-5 fw-bold text-danger">
                            <?= money($selectedRemaining) ?>
                        </div>

                    </div>

                </div>


                <div class="col-md-3">

                    <div class="detail-box">

                        <div class="small text-muted">
                            Due Date
                        </div>

                        <div class="fw-bold">
                            <?= e($selectedFee['due_date']) ?>
                        </div>

                        <div class="mt-1">
                            <?php
                            $badgeClass = match ($selectedFee['status']) {
                                'paid' => 'text-bg-success',
                                'partial' => 'text-bg-primary',
                                'overdue' => 'text-bg-danger',
                                default => 'text-bg-warning',
                            };
                            ?>

                            <span class="badge <?= $badgeClass ?>">
                                <?= e(ucfirst($selectedFee['status'])) ?>
                            </span>

                        </div>

                    </div>

                </div>

            </div>


            <div class="d-flex justify-content-between align-items-center mb-3">

                <h3 class="h6 fw-bold mb-0">
                    <i class="bi bi-clock-history me-2"></i>
                    Payment History
                </h3>

                <?php if ($selectedRemaining > 0): ?>

                    <button
                        type="button"
                        class="btn btn-sm btn-success no-print"
                        data-bs-toggle="modal"
                        data-bs-target="#paymentModal"
                        data-fee-id="<?= (int) $selectedFee['id'] ?>"
                        data-student-id="<?= (int) $selectedFee['student_id'] ?>"
                        data-student-name="<?= e($selectedFee['student_name']) ?>"
                        data-remaining="<?= e((string) $selectedRemaining) ?>"
                    >
                        <i class="bi bi-plus-circle me-1"></i>
                        Record Payment
                    </button>

                <?php endif; ?>

            </div>


            <?php if ($selectedPayments === []): ?>

                <div class="alert alert-light border">
                    <i class="bi bi-info-circle me-2"></i>
                    No payments have been recorded for this fee yet.
                </div>

            <?php else: ?>

                <div class="table-responsive payment-history">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">

                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($selectedPayments as $payment): ?>

                            <tr>

                                <td>
                                    <?= e($payment['payment_date']) ?>
                                </td>

                                <td class="fw-bold text-success">
                                    <?= money((float) $payment['amount']) ?>
                                </td>

                                <td>
                                    <?= e(
                                        ucfirst(
                                            (string) $payment['payment_method']
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $payment['transaction_reference']
                                        ?: '—'
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>


<!-- =========================================================
     FEE LIST
========================================================= -->

<div class="card shadow-sm border-0 fee-card">

    <div class="card-header bg-white p-3">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h2 class="h6 fw-bold mb-1">
                    <i class="bi bi-list-check me-2"></i>
                    Fee Records
                </h2>

                <small class="text-muted">
                    <?= count($fees) ?> fee record(s) found.
                </small>

            </div>

        </div>

    </div>


    <?php if ($fees === []): ?>

        <div class="empty-state">

            <div class="empty-icon mb-3">
                <i class="bi bi-cash-stack"></i>
            </div>

            <h4 class="fw-bold">
                No Fee Records
            </h4>

            <p class="text-muted mb-3">
                No fees match the current filters.
            </p>

            <button
                type="button"
                class="btn btn-primary no-print"
                data-bs-toggle="modal"
                data-bs-target="#createFeeModal"
            >
                <i class="bi bi-plus-circle me-2"></i>
                Create First Fee
            </button>

        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table table-hover mb-0 fee-table">

                <thead class="table-light">

                    <tr>

                        <th class="px-4">
                            Student
                        </th>

                        <th>
                            Fee Type
                        </th>

                        <th>
                            Due Date
                        </th>

                        <th class="text-end">
                            Amount
                        </th>

                        <th class="text-end">
                            Paid
                        </th>

                        <th class="text-end">
                            Remaining
                        </th>

                        <th class="text-center">
                            Status
                        </th>

                        <th class="text-end pe-4 no-print">
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($fees as $fee): ?>

                    <?php
                    $feeAmount = (float) $fee['amount'];
                    $paidAmount = (float) $fee['paid_amount'];
                    $remainingAmount = max(
                        $feeAmount - $paidAmount,
                        0
                    );

                    $badgeClass = match ($fee['status']) {
                        'paid' => 'text-bg-success',
                        'partial' => 'text-bg-primary',
                        'overdue' => 'text-bg-danger',
                        default => 'text-bg-warning',
                    };

                    $initial = strtoupper(
                        substr(
                            trim((string) $fee['student_name']),
                            0,
                            1
                        )
                    );
                    ?>

                    <tr>

                        <td class="px-4">

                            <div class="d-flex align-items-center gap-3">

                                <div class="student-avatar">
                                    <?= e($initial) ?>
                                </div>

                                <div>

                                    <div class="fw-semibold">
                                        <?= e($fee['student_name']) ?>
                                    </div>

                                    <small class="text-muted">
                                        <?= e($fee['student_code']) ?>
                                    </small>

                                </div>

                            </div>

                        </td>


                        <td>

                            <span class="fw-semibold">
                                <?= e($fee['fee_type']) ?>
                            </span>

                        </td>


                        <td>

                            <?= e($fee['due_date']) ?>

                        </td>


                        <td class="text-end">

                            <span class="amount-main">
                                <?= money($feeAmount) ?>
                            </span>

                        </td>


                        <td class="text-end text-success fw-semibold">

                            <?= money($paidAmount) ?>

                        </td>


                        <td class="text-end">

                            <?php if ($remainingAmount > 0): ?>

                                <span class="text-danger fw-bold">
                                    <?= money($remainingAmount) ?>
                                </span>

                            <?php else: ?>

                                <span class="text-success fw-bold">
                                    0.00
                                </span>

                            <?php endif; ?>

                        </td>


                        <td class="text-center">

                            <span class="badge <?= $badgeClass ?>">

                                <?php if ($fee['status'] === 'paid'): ?>

                                    <i class="bi bi-check-circle me-1"></i>

                                <?php elseif ($fee['status'] === 'overdue'): ?>

                                    <i class="bi bi-exclamation-circle me-1"></i>

                                <?php elseif ($fee['status'] === 'partial'): ?>

                                    <i class="bi bi-hourglass-split me-1"></i>

                                <?php else: ?>

                                    <i class="bi bi-clock me-1"></i>

                                <?php endif; ?>

                                <?= e(ucfirst($fee['status'])) ?>

                            </span>

                        </td>


                        <td class="text-end pe-4 no-print">

                            <div class="d-flex justify-content-end gap-1">

                                <a
                                    href="<?= e(
                                        url('admin/fees/index.php')
                                        . '?view='
                                        . (int) $fee['id']
                                    ) ?>"
                                    class="btn btn-sm btn-outline-primary"
                                    title="View details"
                                >
                                    <i class="bi bi-eye"></i>
                                </a>


                                <?php if ($remainingAmount > 0): ?>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-success payment-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#paymentModal"
                                        data-fee-id="<?= (int) $fee['id'] ?>"
                                        data-student-id="<?= (int) $fee['student_id'] ?>"
                                        data-student-name="<?= e($fee['student_name']) ?>"
                                        data-remaining="<?= e((string) $remainingAmount) ?>"
                                        title="Record payment"
                                    >
                                        <i class="bi bi-cash"></i>
                                    </button>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

            <?php if ($totalFiltered > $perPage): ?>

                <?php
                $qs = static function (int $p) use ($search, $status, $studentFilter, $studentQuery): string {
                    $params = [];
                    if ($search !== '') {
                        $params['search'] = $search;
                    }
                    if ($status !== '') {
                        $params['status'] = $status;
                    }
                    if ($studentFilter !== '') {
                        $params['student_id'] = $studentFilter;
                    }
                    if ($studentQuery !== '') {
                        $params['student_q'] = $studentQuery;
                    }
                    $params['page'] = $p;
                    return '?' . http_build_query($params);
                };
                ?>

                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">

                    <small class="text-muted">
                        Showing
                        <?= (int) (($page - 1) * $perPage + 1) ?>–<?= min($totalFiltered, $page * $perPage) ?>
                        of <?= $totalFiltered ?>
                        fee records (page <?= $page ?> of <?= $totalPages ?>)
                    </small>

                    <nav aria-label="Fee list pages">

                        <ul class="pagination pagination-sm mb-0">

                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link"
                                   href="<?= e($qs(max(1, $page - 1))) ?>">
                                    Previous
                                </a>
                            </li>

                            <?php
                            $from = max(1, $page - 2);
                            $to = min($totalPages, $page + 2);
                            for ($p = $from; $p <= $to; $p++):
                            ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link"
                                       href="<?= e($qs($p)) ?>">
                                        <?= $p ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link"
                                   href="<?= e($qs(min($totalPages, $page + 1))) ?>">
                                    Next
                                </a>
                            </li>

                        </ul>

                    </nav>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>


<!-- =========================================================
     CREATE FEE MODAL
========================================================= -->

<div
    class="modal fade"
    id="createFeeModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    <i class="bi bi-plus-circle me-2"></i>
                    Create Fee
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="post">
                <?= csrf_field() ?>

                <div class="modal-body">

                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Student
                        </label>

                        <select
                            name="student_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                -- Select Student --
                            </option>

                            <?php foreach ($students as $student): ?>

                                <option value="<?= (int) $student['id'] ?>">

                                    <?= e($student['name']) ?>
                                    —
                                    <?= e($student['student_id']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">

                            <?php if ($studentQuery === ''): ?>

                                Showing the first <?= $studentPickerTotal ?> active students.
                                Use the search box above to narrow the list by
                                name or student ID.

                            <?php else: ?>

                                <?= $studentPickerTotal ?> match(es) for
                                “<?= e($studentQuery) ?>”.

                            <?php endif; ?>

                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Fee Type
                        </label>

                        <input
                            type="text"
                            name="fee_type"
                            class="form-control"
                            placeholder="e.g. Monthly Fee, Admission Fee"
                            maxlength="150"
                            required
                        >

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Amount
                            </label>

                            <input
                                type="number"
                                name="amount"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Due Date
                            </label>

                            <input
                                type="date"
                                name="due_date"
                                class="form-control"
                                value="<?= e(date('Y-m-d')) ?>"
                                required
                            >

                        </div>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        name="create_fee"
                        value="1"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-check-circle me-2"></i>
                        Create Fee
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =========================================================
     PAYMENT MODAL
========================================================= -->

<div
    class="modal fade"
    id="paymentModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cash-coin me-2"></i>
                    Record Payment
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form method="post">
                <?= csrf_field() ?>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="fee_id"
                        id="paymentFeeId"
                    >

                    <input
                        type="hidden"
                        name="student_id"
                        id="paymentStudentId"
                    >


                    <div class="alert alert-light border">

                        <div class="small text-muted">
                            Student
                        </div>

                        <div
                            class="fw-bold"
                            id="paymentStudentName"
                        >
                            —
                        </div>

                        <div class="small text-muted mt-2">
                            Remaining Amount
                        </div>

                        <div
                            class="fs-5 fw-bold text-danger"
                            id="paymentRemaining"
                        >
                            0.00
                        </div>

                    </div>


                    <div class="mb-3">

                        <label class="form-label fw-semibold">
                            Payment Amount
                        </label>

                        <input
                            type="number"
                            name="payment_amount"
                            id="paymentAmount"
                            class="form-control"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Payment Date
                            </label>

                            <input
                                type="date"
                                name="payment_date"
                                class="form-control"
                                value="<?= e(date('Y-m-d')) ?>"
                                required
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label fw-semibold">
                                Payment Method
                            </label>

                            <select
                                name="payment_method"
                                class="form-select"
                                required
                            >

                                <option value="cash">
                                    Cash
                                </option>

                                <option value="bank">
                                    Bank
                                </option>

                                <option value="online">
                                    Online
                                </option>

                                <option value="card">
                                    Card
                                </option>

                                <option value="other">
                                    Other
                                </option>

                            </select>

                        </div>

                    </div>


                    <div class="mt-3">

                        <label class="form-label fw-semibold">
                            Transaction Reference
                            <span class="text-muted fw-normal">
                                (Optional)
                            </span>
                        </label>

                        <input
                            type="text"
                            name="transaction_reference"
                            class="form-control"
                            maxlength="150"
                            placeholder="Receipt / transaction number"
                        >

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        name="add_payment"
                        value="1"
                        class="btn btn-success"
                    >
                        <i class="bi bi-check-circle me-2"></i>
                        Record Payment
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const paymentModal = document.getElementById('paymentModal');

    if (paymentModal) {

        paymentModal.addEventListener('show.bs.modal', function (event) {

            const button = event.relatedTarget;

            if (!button) {
                return;
            }

            const feeId = button.getAttribute('data-fee-id') || '';
            const studentId = button.getAttribute('data-student-id') || '';
            const studentName = button.getAttribute('data-student-name') || '';
            const remaining = button.getAttribute('data-remaining') || '0';

            document.getElementById('paymentFeeId').value = feeId;
            document.getElementById('paymentStudentId').value = studentId;
            document.getElementById('paymentStudentName').textContent = studentName;

            const remainingNumber = parseFloat(remaining) || 0;

            document.getElementById('paymentRemaining').textContent =
                remainingNumber.toFixed(2);

            const amountInput = document.getElementById('paymentAmount');

            amountInput.value = '';
            amountInput.max = remainingNumber.toFixed(2);
        });
    }

});
</script>


<?php layout_end(); ?>