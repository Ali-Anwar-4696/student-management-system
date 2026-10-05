<?php

declare(strict_types=1);

/*
| GET api/fees.php
| admin:   search + status + student_id filters
| student: own fees (forced)
| parent:  linked children's fees (student_id optional to narrow)
|
| params: search, status (pending|partial|paid|overdue), student_id
*/

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../classes/Fee.php';

api_require_get();
api_require_auth();

$role = currentRole();

if (!in_array($role, ['admin', 'student', 'parent'], true)) {
    api_fail(403, 'You do not have access to this endpoint.');
}

$pdo = db();
$feeObj = new Fee($pdo);

$search = api_str('search');
$status = api_str('status');
$studentId = api_int('student_id');

$feeRows = [];

if ($role === 'student') {

    $me = current_student();

    if (!$me) {
        api_fail(404, 'No student profile linked to your account.');
    }

    $feeRows = $feeObj->list($search, $status, (string) $me['id']);

} elseif ($role === 'parent') {

    $childIds = api_parent_child_ids();

    if ($studentId > 0) {
        api_assert_student_access($studentId);
        $childIds = [$studentId];
    }

    foreach ($childIds as $childId) {
        foreach ($feeObj->list($search, $status, (string) $childId) as $row) {
            $feeRows[] = $row;
        }
    }

} else {

    // admin
    $feeRows = $feeObj->list(
        $search,
        $status,
        $studentId > 0 ? (string) $studentId : ''
    );
}

// ---- amounts ----
$totals = ['billed' => 0.0, 'paid' => 0.0, 'remaining' => 0.0];

foreach ($feeRows as &$row) {
    $amount = (float) ($row['amount'] ?? 0);
    $paid = (float) ($row['paid_amount'] ?? 0);

    $row['remaining'] = round(max($amount - $paid, 0), 2);
    $row['amount'] = round($amount, 2);
    $row['paid_amount'] = round($paid, 2);

    $totals['billed'] += $amount;
    $totals['paid'] += $paid;
}
unset($row);

$totals['remaining'] = max($totals['billed'] - $totals['paid'], 0);

api_ok(['fees' => $feeRows], [
    'count'  => count($feeRows),
    'totals' => [
        'billed'    => round($totals['billed'], 2),
        'paid'      => round($totals['paid'], 2),
        'remaining' => round($totals['remaining'], 2),
    ],
]);
