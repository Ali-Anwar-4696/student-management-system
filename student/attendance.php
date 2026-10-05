<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';

requireStudent();

$pdo = db();

$student = current_student($pdo);

layout_start('My Attendance', 'attendance');


/*
|--------------------------------------------------------------------------
| Student Profile Check
|--------------------------------------------------------------------------
*/

if (!$student) {
    ?>

    <style>
        .student-attendance {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .attendance-empty-card {
            width: 100%;
            max-width: 580px;
            padding: 45px 30px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf3;
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .08);
        }

        .attendance-empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 24px;
            background: #fff7e8;
            color: #f59e0b;
            font-size: 32px;
        }

        .attendance-empty-card h2 {
            color: #182033;
            font-size: 24px;
            font-weight: 800;
        }

        .attendance-empty-card p {
            color: #7b8498;
            line-height: 1.7;
        }
    </style>

    <div class="student-attendance">

        <div class="attendance-empty-card">

            <div class="attendance-empty-icon">
                <i class="bi bi-person-exclamation"></i>
            </div>

            <h2 class="mb-3">
                Student Profile Not Found
            </h2>

            <p class="mb-0">
                Your student profile is not linked yet.
                Please contact the administrator.
            </p>

        </div>

    </div>

    <?php

    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Student Status
|--------------------------------------------------------------------------
*/

$studentStatus = strtolower(
    trim((string) ($student['status'] ?? 'pending'))
);


/*
|--------------------------------------------------------------------------
| Only Active Students
|--------------------------------------------------------------------------
*/

if ($studentStatus !== 'active') {
    ?>

    <style>
        .student-attendance {
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .attendance-status-card {
            width: 100%;
            max-width: 600px;
            padding: 45px 30px;
            text-align: center;
            background: #fff;
            border: 1px solid #e8ebf3;
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, .08);
        }

        .attendance-status-icon {
            width: 82px;
            height: 82px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 25px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 33px;
        }

        .attendance-status-card h2 {
            color: #182033;
            font-size: 24px;
            font-weight: 800;
        }

        .attendance-status-card p {
            color: #7b8498;
            line-height: 1.7;
        }

        .attendance-status-badge {
            display: inline-flex;
            padding: 8px 15px;
            border-radius: 50px;
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
        }
    </style>

    <div class="student-attendance">

        <div class="attendance-status-card">

            <div class="attendance-status-icon">
                <i class="bi bi-person-lock"></i>
            </div>

            <h2 class="mb-3">
                Attendance Unavailable
            </h2>

            <p class="mb-3">
                Attendance is available only for active students.
            </p>

            <span class="attendance-status-badge">
                <?= e(ucfirst($studentStatus)) ?>
            </span>

        </div>

    </div>

    <?php

    layout_end();
    exit;
}


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$month = trim((string) ($_GET['month'] ?? ''));

if (
    $month !== '' &&
    !preg_match('/^\d{4}-\d{2}$/', $month)
) {
    $month = '';
}


/*
|--------------------------------------------------------------------------
| Build Attendance Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.student_id,
        a.class_id,
        a.section_id,
        a.subject_id,
        a.date,
        a.status,
        a.marked_by,
        a.created_at,

        c.name AS class_name,
        sec.name AS section_name,
        sub.name AS subject_name

    FROM attendance a

    LEFT JOIN classes c
        ON c.id = a.class_id

    LEFT JOIN sections sec
        ON sec.id = a.section_id

    LEFT JOIN subjects sub
        ON sub.id = a.subject_id

    WHERE a.student_id = :student_id
";


$params = [
    ':student_id' => (int) $student['id']
];


if ($month !== '') {

    $sql .= "
        AND DATE_FORMAT(a.date, '%Y-%m') = :month
    ";

    $params[':month'] = $month;
}


$sql .= "
    ORDER BY a.date DESC, a.id DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Calculate Statistics
|--------------------------------------------------------------------------
*/

$total = count($attendance);

$present = 0;

$absent = 0;

$late = 0;

$other = 0;


foreach ($attendance as $record) {

    $status = strtolower(
        trim((string) ($record['status'] ?? ''))
    );

    if ($status === 'present') {

        $present++;

    } elseif ($status === 'absent') {

        $absent++;

    } elseif ($status === 'late') {

        $late++;

    } else {

        $other++;
    }
}


$percentage = $total > 0
    ? ($present / $total) * 100
    : 0;


/*
|--------------------------------------------------------------------------
| Subject Statistics
|--------------------------------------------------------------------------
*/

$subjectStats = [];


foreach ($attendance as $record) {

    $subjectId = $record['subject_id'];

    $subjectKey = $subjectId !== null
        ? (string) $subjectId
        : 'general';


    if (!isset($subjectStats[$subjectKey])) {

        $subjectStats[$subjectKey] = [
            'name' => !empty($record['subject_name'])
                ? (string) $record['subject_name']
                : 'General Attendance',

            'total' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
        ];
    }


    $subjectStats[$subjectKey]['total']++;


    $status = strtolower(
        trim((string) ($record['status'] ?? ''))
    );


    if ($status === 'present') {

        $subjectStats[$subjectKey]['present']++;

    } elseif ($status === 'absent') {

        $subjectStats[$subjectKey]['absent']++;

    } elseif ($status === 'late') {

        $subjectStats[$subjectKey]['late']++;
    }
}


/*
|--------------------------------------------------------------------------
| Format Month
|--------------------------------------------------------------------------
*/

$selectedMonthLabel = 'All Attendance';

if ($month !== '') {

    $monthTimestamp = strtotime($month . '-01');

    if ($monthTimestamp !== false) {

        $selectedMonthLabel = date(
            'F Y',
            $monthTimestamp
        );
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function attendance_status_class(string $status): string
{
    return match (strtolower(trim($status))) {

        'present' => 'status-present',

        'absent' => 'status-absent',

        'late' => 'status-late',

        default => 'status-other',
    };
}


function attendance_status_icon(string $status): string
{
    return match (strtolower(trim($status))) {

        'present' => 'bi-check-circle-fill',

        'absent' => 'bi-x-circle-fill',

        'late' => 'bi-clock-fill',

        default => 'bi-question-circle-fill',
    };
}

?>

<style>

/* =========================================================
   STUDENT ATTENDANCE
========================================================= */

.student-attendance-page {

    --primary: #5b5ce2;
    --primary-dark: #4748c9;

    --text: #182033;
    --muted: #7b8498;

    --border: #e8ebf3;

    --surface: #ffffff;

    width: 100%;
    max-width: 1500px;

    margin: 0 auto;

    padding: 5px 0 35px;

    color: var(--text);
}


/* =========================================================
   HERO
========================================================= */

.attendance-hero {

    position: relative;

    overflow: hidden;

    padding: 28px 30px;

    margin-bottom: 20px;

    border-radius: 27px;

    color: #fff;

    background:
        linear-gradient(
            135deg,
            #4f46e5,
            #6366f1 50%,
            #7c3aed
        );

    box-shadow:
        0 20px 50px
        rgba(79,70,229,.18);
}


.attendance-hero::before {

    content: "";

    position: absolute;

    width: 210px;
    height: 210px;

    border-radius: 50%;

    right: -70px;
    top: -110px;

    background:
        rgba(255,255,255,.09);
}


.attendance-hero::after {

    content: "";

    position: absolute;

    width: 130px;
    height: 130px;

    border-radius: 50%;

    right: 180px;
    bottom: -85px;

    background:
        rgba(255,255,255,.06);
}


.attendance-hero-content {

    position: relative;

    z-index: 2;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;
}


.attendance-title-area {

    display: flex;

    align-items: center;

    gap: 15px;

    min-width: 0;
}


.attendance-hero-icon {

    width: 58px;
    height: 58px;

    flex: 0 0 58px;

    display: flex;

    align-items: center;
    justify-content: center;

    border-radius: 18px;

    background:
        rgba(255,255,255,.14);

    border:
        1px solid
        rgba(255,255,255,.18);

    font-size: 24px;
}


.attendance-eyebrow {

    margin-bottom: 4px;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: .13em;

    text-transform: uppercase;

    opacity: .7;
}


.attendance-title {

    margin: 0;

    font-size: clamp(23px, 3vw, 31px);

    font-weight: 850;

    letter-spacing: -.5px;
}


.attendance-subtitle {

    margin-top: 5px;

    color:
        rgba(255,255,255,.75);

    font-size: 12px;
}


.student-mini {

    display: flex;

    align-items: center;

    gap: 9px;

    padding: 9px 13px;

    border-radius: 50px;

    background:
        rgba(255,255,255,.12);

    border:
        1px solid
        rgba(255,255,255,.16);

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;
}


/* =========================================================
   FILTER
========================================================= */

.attendance-filter {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 15px;

    padding: 15px 18px;

    margin-bottom: 20px;

    background: #fff;

    border:
        1px solid
        var(--border);

    border-radius: 18px;

    box-shadow:
        0 7px 25px
        rgba(30,41,59,.04);
}


.filter-label {

    display: flex;

    align-items: center;

    gap: 9px;

    color: var(--text);

    font-size: 12px;

    font-weight: 800;
}


.filter-label i {

    color: var(--primary);

    font-size: 17px;
}


.filter-form {

    display: flex;

    align-items: center;

    gap: 8px;
}


.filter-form input {

    width: 170px;

    height: 38px;

    padding: 7px 11px;

    border:
        1px solid
        #dfe3eb;

    border-radius: 10px;

    color: var(--text);

    background: #fff;

    font-size: 12px;

    outline: none;
}


.filter-form input:focus {

    border-color:
        var(--primary);

    box-shadow:
        0 0 0 3px
        rgba(91,92,226,.10);
}


.filter-btn {

    height: 38px;

    padding: 0 14px;

    border: 0;

    border-radius: 10px;

    color: #fff;

    background: var(--primary);

    font-size: 11px;

    font-weight: 750;

    text-decoration: none;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 6px;
}


.filter-btn:hover {

    color: #fff;

    background: var(--primary-dark);
}


.clear-filter {

    width: 38px;
    height: 38px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border:
        1px solid
        #e2e5eb;

    border-radius: 10px;

    color: #6b7280;

    background: #fff;

    text-decoration: none;
}


.clear-filter:hover {

    color: #ef4444;

    background: #fff5f5;
}


/* =========================================================
   STATISTICS
========================================================= */

.attendance-stats {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 15px;

    margin-bottom: 20px;
}


.attendance-stat {

    position: relative;

    overflow: hidden;

    min-height: 118px;

    padding: 19px;

    border:
        1px solid
        var(--border);

    border-radius: 19px;

    background: #fff;

    box-shadow:
        0 7px 25px
        rgba(30,41,59,.045);

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.attendance-stat:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 14px 35px
        rgba(30,41,59,.08);
}


.attendance-stat-content {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 10px;
}


.stat-label {

    color: var(--muted);

    font-size: 10px;

    font-weight: 750;

    margin-bottom: 7px;
}


.stat-number {

    color: var(--text);

    font-size: 24px;

    line-height: 1;

    font-weight: 850;
}


.stat-small {

    margin-top: 7px;

    color: #a0a8b8;

    font-size: 9px;
}


.attendance-stat-icon {

    width: 43px;
    height: 43px;

    flex: 0 0 43px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 13px;

    font-size: 17px;
}


.icon-purple {

    color: #5b5ce2;
    background: #f0f0ff;
}


.icon-green {

    color: #059669;
    background: #ecfdf5;
}


.icon-red {

    color: #dc2626;
    background: #fef2f2;
}


.icon-orange {

    color: #d97706;
    background: #fffbeb;
}


/* =========================================================
   MAIN GRID
========================================================= */

.attendance-main-grid {

    display: grid;

    grid-template-columns:
        minmax(0, 1.65fr)
        minmax(280px, .85fr);

    gap: 20px;
}


/* =========================================================
   CARD
========================================================= */

.attendance-card {

    min-width: 0;

    overflow: hidden;

    background: #fff;

    border:
        1px solid
        var(--border);

    border-radius: 22px;

    box-shadow:
        0 8px 28px
        rgba(30,41,59,.045);
}


.attendance-card-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    padding: 21px 22px 15px;
}


.attendance-card-title {

    margin: 0;

    color: var(--text);

    font-size: 15px;

    font-weight: 820;
}


.attendance-card-subtitle {

    margin-top: 4px;

    color: var(--muted);

    font-size: 10px;
}


.record-count {

    padding: 6px 10px;

    border-radius: 50px;

    color: var(--primary);

    background: #f1f1ff;

    font-size: 10px;

    font-weight: 800;

    white-space: nowrap;
}


/* =========================================================
   ATTENDANCE TABLE
========================================================= */

.attendance-table-wrap {

    width: 100%;

    overflow-x: auto;

    -webkit-overflow-scrolling: touch;
}


.attendance-table {

    width: 100%;

    min-width: 560px;

    margin: 0;

    border-collapse: collapse;
}


.attendance-table th {

    padding: 12px 20px;

    color: #969eae;

    background: #fafbfc;

    border-top:
        1px solid
        #f0f2f5;

    border-bottom:
        1px solid
        #edf0f4;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: .07em;

    text-transform: uppercase;

    white-space: nowrap;
}


.attendance-table td {

    padding: 14px 20px;

    color: var(--text);

    border-bottom:
        1px solid
        #f0f2f5;

    font-size: 11px;

    vertical-align: middle;
}


.attendance-table tbody tr {

    transition:
        background .18s ease;
}


.attendance-table tbody tr:hover {

    background: #fafbff;
}


.date-cell {

    display: flex;

    align-items: center;

    gap: 9px;

    font-weight: 750;

    white-space: nowrap;
}


.date-icon {

    width: 31px;
    height: 31px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 9px;

    color: var(--primary);

    background: #f2f2ff;

    font-size: 12px;
}


.subject-name {

    font-weight: 700;

    color: var(--text);
}


.general-subject {

    color: #8b95a7;

    font-style: italic;

    font-weight: 600;
}


.status-badge {

    display: inline-flex;

    align-items: center;

    gap: 5px;

    padding: 6px 9px;

    border-radius: 50px;

    font-size: 9px;

    font-weight: 800;

    white-space: nowrap;
}


.status-present {

    color: #047857;

    background: #ecfdf5;
}


.status-absent {

    color: #b91c1c;

    background: #fef2f2;
}


.status-late {

    color: #b45309;

    background: #fffbeb;
}


.status-other {

    color: #475569;

    background: #f1f5f9;
}


/* =========================================================
   SUBJECT STATISTICS
========================================================= */

.subject-list {

    padding: 0 19px 18px;
}


.subject-row {

    padding: 14px 0;

    border-bottom:
        1px solid
        #f0f2f6;
}


.subject-row:last-child {

    border-bottom: 0;
}


.subject-top {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 10px;

    margin-bottom: 8px;
}


.subject-name-small {

    min-width: 0;

    color: var(--text);

    font-size: 11px;

    font-weight: 750;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.subject-percent {

    color: var(--primary);

    font-size: 11px;

    font-weight: 850;
}


.progress-track {

    width: 100%;

    height: 6px;

    overflow: hidden;

    border-radius: 50px;

    background: #eef0f5;
}


.progress-fill {

    height: 100%;

    border-radius: inherit;

    background:
        linear-gradient(
            90deg,
            #5b5ce2,
            #8b5cf6
        );
}


.subject-meta {

    display: flex;

    align-items: center;

    gap: 8px;

    margin-top: 7px;

    color: #98a0b0;

    font-size: 9px;
}


.subject-meta span {

    display: inline-flex;

    align-items: center;

    gap: 3px;
}


/* =========================================================
   EMPTY
========================================================= */

.attendance-no-data {

    padding: 55px 20px;

    text-align: center;
}


.no-data-icon {

    width: 65px;
    height: 65px;

    margin: 0 auto 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 20px;

    color: #8d97a9;

    background: #f7f8fb;

    font-size: 25px;
}


.no-data-title {

    color: var(--text);

    font-size: 14px;

    font-weight: 800;

    margin-bottom: 5px;
}


.no-data-text {

    color: var(--muted);

    font-size: 10px;

    margin: 0 auto;

    max-width: 380px;

    line-height: 1.6;
}


/* =========================================================
   MOBILE
========================================================= */

@media (max-width: 991.98px) {

    .attendance-main-grid {

        grid-template-columns: 1fr;
    }

    .attendance-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }
}


@media (max-width: 767.98px) {

    .student-attendance-page {

        padding:
            2px 0
            25px;
    }


    .attendance-hero {

        padding: 22px;

        border-radius: 22px;
    }


    .attendance-hero-content {

        align-items: flex-start;

        flex-direction: column;
    }


    .attendance-hero-icon {

        width: 50px;
        height: 50px;

        flex-basis: 50px;

        border-radius: 15px;

        font-size: 21px;
    }


    .attendance-title {

        font-size: 24px;
    }


    .student-mini {

        align-self: flex-start;
    }


    .attendance-filter {

        align-items: flex-start;

        flex-direction: column;

        padding: 14px;

        border-radius: 16px;
    }


    .filter-form {

        width: 100%;
    }


    .filter-form input {

        flex: 1;

        width: auto;

        min-width: 0;
    }


    .attendance-stats {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));

        gap: 10px;
    }


    .attendance-stat {

        min-height: 105px;

        padding: 15px;

        border-radius: 16px;
    }


    .stat-number {

        font-size: 21px;
    }


    .attendance-stat-icon {

        width: 38px;
        height: 38px;

        flex-basis: 38px;

        border-radius: 11px;

        font-size: 15px;
    }


    .attendance-card {

        border-radius: 19px;
    }


    .attendance-card-header {

        padding:
            18px 16px
            13px;
    }


    .attendance-table th {

        padding:
            10px 14px;
    }


    .attendance-table td {

        padding:
            12px 14px;
    }


    .subject-list {

        padding:
            0 16px
            15px;
    }
}


@media (max-width: 480px) {

    .attendance-title-area {

        align-items: flex-start;
    }


    .attendance-title {

        font-size: 21px;
    }


    .attendance-subtitle {

        font-size: 10px;
    }


    .attendance-stats {

        grid-template-columns: 1fr;
    }


    .attendance-stat {

        min-height: 92px;
    }


    .attendance-filter {

        margin-bottom: 14px;
    }


    .filter-form {

        display: grid;

        grid-template-columns:
            minmax(0, 1fr)
            auto
            38px;

        width: 100%;
    }


    .filter-form input {

        width: 100%;
    }


    .filter-btn {

        padding: 0 11px;
    }


    .record-count {

        font-size: 9px;
    }


    .attendance-card-header {

        align-items: flex-start;
    }


    .attendance-card-title {

        font-size: 14px;
    }


    .attendance-table {

        min-width: 510px;
    }
}


@media (max-width: 360px) {

    .attendance-hero {

        padding: 18px;

        border-radius: 19px;
    }


    .attendance-title {

        font-size: 19px;
    }


    .attendance-hero-icon {

        width: 45px;
        height: 45px;

        flex-basis: 45px;
    }


    .student-mini {

        font-size: 9px;

        padding:
            7px 10px;
    }


    .attendance-filter {

        padding: 11px;
    }


    .filter-form {

        grid-template-columns:
            minmax(0, 1fr)
            38px;
    }


    .filter-btn {

        font-size: 0;

        width: 38px;

        padding: 0;
    }


    .filter-btn i {

        margin: 0 !important;

        font-size: 14px;
    }


    .clear-filter {

        display: none;
    }
}

</style>


<div class="student-attendance-page">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="attendance-hero">

        <div class="attendance-hero-content">

            <div class="attendance-title-area">

                <div class="attendance-hero-icon">
                    <i class="bi bi-calendar-check-fill"></i>
                </div>

                <div>

                    <div class="attendance-eyebrow">
                        Student Portal
                    </div>

                    <h1 class="attendance-title">
                        My Attendance
                    </h1>

                    <div class="attendance-subtitle">
                        Track your attendance records and academic presence.
                    </div>

                </div>

            </div>


            <div class="student-mini">

                <i class="bi bi-person-circle"></i>

                <?= e(
                    (string) (
                        $student['name']
                        ?? 'Student'
                    )
                ) ?>

            </div>

        </div>

    </section>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <div class="attendance-filter">

        <div class="filter-label">

            <i class="bi bi-funnel-fill"></i>

            <span>
                Attendance Filter
            </span>

        </div>


        <form
            method="get"
            class="filter-form"
        >

            <input
                type="month"
                name="month"
                value="<?= e($month) ?>"
                aria-label="Filter attendance by month"
            >


            <button
                type="submit"
                class="filter-btn"
            >

                <i class="bi bi-search"></i>

                <span>
                    Filter
                </span>

            </button>


            <?php if ($month !== ''): ?>

                <a
                    href="<?= e(
                        url(
                            'student/attendance.php'
                        )
                    ) ?>"
                    class="clear-filter"
                    title="Clear filter"
                >

                    <i class="bi bi-x-lg"></i>

                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="attendance-stats">


        <!-- Total -->

        <div class="attendance-stat">

            <div class="attendance-stat-content">

                <div>

                    <div class="stat-label">
                        Total Records
                    </div>

                    <div class="stat-number">
                        <?= $total ?>
                    </div>

                    <div class="stat-small">
                        <?= e($selectedMonthLabel) ?>
                    </div>

                </div>

                <div class="attendance-stat-icon icon-purple">
                    <i class="bi bi-calendar3"></i>
                </div>

            </div>

        </div>


        <!-- Present -->

        <div class="attendance-stat">

            <div class="attendance-stat-content">

                <div>

                    <div class="stat-label">
                        Present
                    </div>

                    <div class="stat-number">
                        <?= $present ?>
                    </div>

                    <div class="stat-small">
                        Attendance days
                    </div>

                </div>

                <div class="attendance-stat-icon icon-green">
                    <i class="bi bi-check-lg"></i>
                </div>

            </div>

        </div>


        <!-- Absent -->

        <div class="attendance-stat">

            <div class="attendance-stat-content">

                <div>

                    <div class="stat-label">
                        Absent
                    </div>

                    <div class="stat-number">
                        <?= $absent ?>
                    </div>

                    <div class="stat-small">
                        Missed days
                    </div>

                </div>

                <div class="attendance-stat-icon icon-red">
                    <i class="bi bi-x-lg"></i>
                </div>

            </div>

        </div>


        <!-- Percentage -->

        <div class="attendance-stat">

            <div class="attendance-stat-content">

                <div>

                    <div class="stat-label">
                        Attendance Rate
                    </div>

                    <div class="stat-number">
                        <?= number_format(
                            $percentage,
                            1
                        ) ?>%
                    </div>

                    <div class="stat-small">
                        Present / total
                    </div>

                </div>

                <div class="attendance-stat-icon icon-orange">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <div class="attendance-main-grid">


        <!-- =================================================
             ATTENDANCE HISTORY
        ================================================== -->

        <div class="attendance-card">

            <div class="attendance-card-header">

                <div>

                    <h2 class="attendance-card-title">
                        Attendance History
                    </h2>

                    <div class="attendance-card-subtitle">
                        Your attendance records
                    </div>

                </div>

                <div class="record-count">
                    <?= $total ?> Records
                </div>

            </div>


            <?php if (empty($attendance)): ?>

                <div class="attendance-no-data">

                    <div class="no-data-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>

                    <div class="no-data-title">
                        No Attendance Records
                    </div>

                    <p class="no-data-text">
                        No attendance records were found
                        <?= $month !== ''
                            ? 'for ' . e($selectedMonthLabel)
                            : 'for your account'
                        ?>.
                    </p>

                </div>

            <?php else: ?>

                <div class="attendance-table-wrap">

                    <table class="attendance-table">

                        <thead>

                            <tr>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Class
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach (
                                $attendance
                                as $record
                            ): ?>

                                <?php

                                $recordStatus =
                                    (string) (
                                        $record['status']
                                        ?? ''
                                    );

                                ?>

                                <tr>


                                    <!-- Date -->

                                    <td>

                                        <div class="date-cell">

                                            <div class="date-icon">
                                                <i class="bi bi-calendar-event"></i>
                                            </div>

                                            <span>
                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            (string) (
                                                                $record['date']
                                                            )
                                                        )
                                                    )
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- Subject -->

                                    <td>

                                        <?php if (
                                            !empty(
                                                $record['subject_name']
                                            )
                                        ): ?>

                                            <div class="subject-name">
                                                <?= e(
                                                    (string) (
                                                        $record[
                                                            'subject_name'
                                                        ]
                                                    )
                                                ) ?>
                                            </div>

                                        <?php else: ?>

                                            <div class="general-subject">
                                                General Attendance
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- Class -->

                                    <td>

                                        <div class="subject-name">

                                            <?= e(
                                                (string) (
                                                    $record[
                                                        'class_name'
                                                    ]
                                                    ?? 'N/A'
                                                )
                                            ) ?>

                                            <?php if (
                                                !empty(
                                                    $record['section_name']
                                                )
                                            ): ?>

                                                <span class="text-muted">
                                                    /
                                                    <?= e(
                                                        (string) (
                                                            $record[
                                                                'section_name'
                                                            ]
                                                        )
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </td>


                                    <!-- Status -->

                                    <td>

                                        <span
                                            class="status-badge <?= e(
                                                attendance_status_class(
                                                    $recordStatus
                                                )
                                            ) ?>"
                                        >

                                            <i
                                                class="bi <?= e(
                                                    attendance_status_icon(
                                                        $recordStatus
                                                    )
                                                ) ?>"
                                            ></i>

                                            <?= e(
                                                ucfirst(
                                                    strtolower(
                                                        $recordStatus
                                                    )
                                                )
                                            ) ?>

                                        </span>

                                    </td>


                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>


        <!-- =================================================
             SUBJECT SUMMARY
        ================================================== -->

        <div class="attendance-card">

            <div class="attendance-card-header">

                <div>

                    <h2 class="attendance-card-title">
                        Subject Summary
                    </h2>

                    <div class="attendance-card-subtitle">
                        Attendance by subject
                    </div>

                </div>

            </div>


            <?php if (empty($subjectStats)): ?>

                <div class="attendance-no-data">

                    <div class="no-data-icon">
                        <i class="bi bi-bar-chart"></i>
                    </div>

                    <div class="no-data-title">
                        No Subject Data
                    </div>

                    <p class="no-data-text">
                        Subject attendance information
                        will appear here.
                    </p>

                </div>

            <?php else: ?>

                <div class="subject-list">

                    <?php foreach (
                        $subjectStats
                        as $subject
                    ): ?>

                        <?php

                        $subjectTotal =
                            (int) $subject['total'];

                        $subjectPresent =
                            (int) $subject['present'];

                        $subjectPercentage =
                            $subjectTotal > 0
                                ? (
                                    $subjectPresent
                                    / $subjectTotal
                                ) * 100
                                : 0;

                        ?>

                        <div class="subject-row">

                            <div class="subject-top">

                                <div class="subject-name-small">

                                    <?= e(
                                        (string) (
                                            $subject['name']
                                        )
                                    ) ?>

                                </div>

                                <div class="subject-percent">

                                    <?= number_format(
                                        $subjectPercentage,
                                        0
                                    ) ?>%

                                </div>

                            </div>


                            <div class="progress-track">

                                <div
                                    class="progress-fill"
                                    style="width: <?= min(
                                        100,
                                        max(
                                            0,
                                            $subjectPercentage
                                        )
                                    ) ?>%;"
                                ></div>

                            </div>


                            <div class="subject-meta">

                                <span>
                                    <i class="bi bi-check-circle"></i>
                                    <?= $subjectPresent ?>
                                    Present
                                </span>

                                <span>
                                    <i class="bi bi-x-circle"></i>
                                    <?= (int) $subject['absent'] ?>
                                    Absent
                                </span>

                                <?php if (
                                    (int) $subject['late'] > 0
                                ): ?>

                                    <span>
                                        <i class="bi bi-clock"></i>
                                        <?= (int) $subject['late'] ?>
                                        Late
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>


    </div>

</div>


<?php

layout_end();

?>

