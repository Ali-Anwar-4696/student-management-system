<?php

require_once '../includes/init.php';
require_once '../includes/role_check.php';

requireTeacher();

require_once '../classes/Exam.php';
require_once '../classes/TeacherClass.php';

$database = new Database();
$pdo = $database->connect();

$examModel = new Exam($pdo);
$teacherClassModel = new TeacherClass($pdo);

$teacherId = (int) ($_SESSION['teacher_profile_id'] ?? 0);

if ($teacherId <= 0) {
    http_response_code(403);
    exit('Teacher profile not found.');
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$classFilter = trim((string) ($_GET['class_id'] ?? ''));

$allowedStatuses = ['upcoming', 'active', 'completed'];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

/*
|--------------------------------------------------------------------------
| TEACHER ASSIGNMENTS
|--------------------------------------------------------------------------
*/

$teacherAssignments = $teacherClassModel->getTeacherClasses($teacherId);

$allowedClasses = [];
$allowedClassSubjects = [];

foreach ($teacherAssignments as $assignment) {
    $classId = (int) ($assignment['class_id'] ?? 0);
    $subjectId = (int) ($assignment['subject_id'] ?? 0);

    if ($classId <= 0) {
        continue;
    }

    if (!isset($allowedClasses[$classId])) {
        $allowedClasses[$classId] = [
            'id'   => $classId,
            'name' => $assignment['class_name'] ?? 'Class'
        ];
    }

    if ($subjectId > 0) {
        $allowedClassSubjects[$classId][$subjectId] = [
            'id'     => $subjectId,
            'name'   => $assignment['subject_name'] ?? 'Subject',
            'code'   => $assignment['subject_code'] ?? ''
        ];
    }
}

/*
|--------------------------------------------------------------------------
| VALIDATE CLASS FILTER
|--------------------------------------------------------------------------
*/

if (
    $classFilter !== '' &&
    (!ctype_digit($classFilter) || !isset($allowedClasses[(int) $classFilter]))
) {
    $classFilter = '';
}

/*
|--------------------------------------------------------------------------
| FETCH EXAMS
|--------------------------------------------------------------------------
*/

$allExams = $examModel->list($search, $status, $classFilter);

/*
|--------------------------------------------------------------------------
| SECURITY SCOPE
|--------------------------------------------------------------------------
*/

$exams = [];

foreach ($allExams as $exam) {
    $examClassId = (int) ($exam['class_id'] ?? 0);

    if (!isset($allowedClasses[$examClassId])) {
        continue;
    }

    $exam['all_subjects'] = [];
    $exam['teacher_subjects'] = [];

    try {
        $subjects = $examModel->getSubjects((int) $exam['id']);

        foreach ($subjects as $subject) {
            $subjectId = (int) ($subject['subject_id'] ?? 0);
            $exam['all_subjects'][] = $subject;

            if (isset($allowedClassSubjects[$examClassId][$subjectId])) {
                $exam['teacher_subjects'][] = $subject;
            }
        }
    } catch (Throwable $e) {
        $exam['all_subjects'] = [];
        $exam['teacher_subjects'] = [];
    }

    $exams[] = $exam;
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalExams = count($exams);
$upcomingExams = 0;
$activeExams = 0;
$completedExams = 0;

foreach ($exams as $exam) {
    switch ($exam['status'] ?? '') {
        case 'upcoming':  $upcomingExams++;  break;
        case 'active':    $activeExams++;    break;
        case 'completed': $completedExams++; break;
    }
}

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function examStatusLabel(string $status): string
{
    return match ($status) {
        'upcoming'  => 'Upcoming',
        'active'    => 'Active',
        'completed' => 'Completed',
        default     => ucfirst($status)
    };
}

function examTypeLabel(string $type): string
{
    return match ($type) {
        'monthly' => 'Monthly',
        'midterm' => 'Mid Term',
        'final'   => 'Final',
        'quiz'    => 'Quiz',
        'other'   => 'Other',
        default   => ucfirst($type)
    };
}

function formatExamDate(?string $date): string
{
    if (!$date) {
        return 'Not scheduled';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('d M Y', $timestamp) : 'Invalid date';
}

function examDateRange(array $exam): string
{
    $start = $exam['start_date'] ?? null;
    $end   = $exam['end_date']   ?? null;

    if (!$start && !$end) {
        return 'Not scheduled';
    }

    if ($start && $end) {
        if (date('Y-m-d', strtotime($start)) === date('Y-m-d', strtotime($end))) {
            return formatExamDate($start);
        }
        return formatExamDate($start) . ' – ' . formatExamDate($end);
    }

    return formatExamDate($start ?: $end);
}

function statusClass(string $status): string
{
    return match ($status) {
        'upcoming'  => 'status-upcoming',
        'active'    => 'status-active',
        'completed' => 'status-completed',
        default     => 'status-default'
    };
}

function statusIcon(string $status): string
{
    return match ($status) {
        'upcoming'  => '⏳',
        'active'    => '▶',
        'completed' => '✓',
        default     => '●'
    };
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Teacher Exams</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            --gray-900: #111827;
            --gray-800: #1f2937;
            --gray-700: #374151;
            --gray-600: #4b5563;
            --gray-500: #6b7280;
            --gray-400: #9ca3af;
            --gray-300: #d1d5db;
            --gray-200: #e5e7eb;
            --gray-100: #f3f4f6;
            --gray-50:  #f9fafb;
            --white: #ffffff;
            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* =====================================================
           LAYOUT
        ===================================================== */
        .page-wrapper {
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* =====================================================
           HEADER
        ===================================================== */
        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .page-header-left { flex: 1; min-width: 0; }
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--gray-500);
            margin-bottom: 8px;
            font-weight: 500;
        }
        .breadcrumb a { color: var(--gray-500); text-decoration: none; }
        .breadcrumb a:hover { color: var(--primary); }
        .breadcrumb-sep { color: var(--gray-400); }
        .breadcrumb-current { color: var(--primary); font-weight: 600; }
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
            max-width: 560px;
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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

        /* =====================================================
           STATS GRID
        ===================================================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
            transition: transform .2s, box-shadow .2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--primary);
            opacity: 0;
            transition: opacity .2s;
        }
        .stat-card:hover::before { opacity: 1; }
        .stat-card.upcoming::before { background: var(--warning); }
        .stat-card.active::before   { background: var(--success); }
        .stat-card.completed::before{ background: var(--gray-400); }

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
        .stat-card.upcoming .stat-icon-wrap { background: var(--warning-soft); color: #b45309; }
        .stat-card.active   .stat-icon-wrap { background: var(--success-soft); color: #047857; }
        .stat-card.completed .stat-icon-wrap { background: var(--gray-100); color: var(--gray-600); }

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

        /* =====================================================
           FILTER BAR
        ===================================================== */
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
        .filter-group.search { flex: 2; min-width: 220px; }
        .filter-group.select { flex: 1; min-width: 170px; }
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
            transition: border-color .15s, box-shadow .15s;
            appearance: none;
            -webkit-appearance: none;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        .form-control.search {
            padding-left: 38px;
        }
        select.form-control {
            padding-right: 32px;
            background-image: url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%236b7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            cursor: pointer;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* =====================================================
           BUTTONS
        ===================================================== */
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            box-shadow: 0 4px 12px rgba(99,102,241,.25);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(99,102,241,.35);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-secondary {
            background: var(--white);
            color: var(--gray-700);
            border-color: var(--gray-300);
        }
        .btn-secondary:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }
        .btn-sm {
            height: 36px;
            padding: 0 14px;
            font-size: 12px;
        }

        /* =====================================================
           CONTENT CARD
        ===================================================== */
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

        /* =====================================================
           TABLE
        ===================================================== */
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
            min-width: 800px;
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
            padding: 16px 20px;
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

        /* Exam cell */
        .exam-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .exam-cell-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--primary-soft), var(--primary-light));
            color: var(--primary-dark);
            display: grid;
            place-items: center;
            font-size: 16px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .exam-cell-info { min-width: 0; }
        .exam-cell-name {
            font-weight: 700;
            color: var(--gray-900);
            font-size: 14px;
            word-break: break-word;
        }
        .exam-cell-type {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 2px;
        }

        .cell-text {
            color: var(--gray-700);
            font-weight: 500;
            word-break: break-word;
        }
        .cell-date {
            color: var(--gray-600);
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }

        /* Chips */
        .chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            max-width: 240px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            background: var(--primary-soft);
            color: var(--primary-dark);
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--primary-light);
            white-space: nowrap;
        }
        .chip-more {
            background: var(--gray-100);
            color: var(--gray-600);
            border-color: var(--gray-200);
        }

        /* Status */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .status-upcoming {
            background: var(--warning-soft);
            color: #92400e;
        }
        .status-upcoming .status-dot { background: var(--warning); }
        .status-active {
            background: var(--success-soft);
            color: #065f46;
        }
        .status-active .status-dot { background: var(--success); }
        .status-completed {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        .status-completed .status-dot { background: var(--gray-500); }
        .status-default {
            background: var(--gray-100);
            color: var(--gray-600);
        }
        .status-default .status-dot { background: var(--gray-400); }

        /* =====================================================
           MOBILE CARDS
        ===================================================== */
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
            transition: box-shadow .2s;
        }
        .m-card:last-child { margin-bottom: 0; }
        .m-card:active { box-shadow: var(--shadow-md); }

        .m-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }
        .m-card-exam {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }
        .m-card-exam .exam-cell-icon {
            width: 40px;
            height: 40px;
            font-size: 14px;
        }
        .m-card-title {
            font-weight: 700;
            color: var(--gray-900);
            font-size: 14px;
            word-break: break-word;
            line-height: 1.35;
        }
        .m-card-sub {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 2px;
        }

        .m-card-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding-top: 14px;
            border-top: 1px solid var(--gray-100);
        }
        .m-card-field {
            background: var(--gray-50);
            padding: 10px 12px;
            border-radius: var(--radius);
        }
        .m-card-field-label {
            font-size: 11px;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .m-card-field-value {
            font-size: 13px;
            color: var(--gray-800);
            font-weight: 600;
            margin-top: 4px;
            word-break: break-word;
        }

        .m-card-section {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid var(--gray-100);
        }
        .m-card-section-label {
            font-size: 11px;
            color: var(--gray-500);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }
        .m-card .chip-list {
            max-width: none;
        }
        .m-card-footer {
            margin-top: 14px;
        }
        .m-card-footer .btn {
            width: 100%;
        }

        /* =====================================================
           EMPTY STATE
        ===================================================== */
        .empty-state {
            text-align: center;
            padding: 64px 24px;
        }
        .empty-icon {
            width: 72px;
            height: 72px;
            border-radius: var(--radius-xl);
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
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }
        .empty-state .btn {
            margin-top: 20px;
        }

        /* =====================================================
           MODAL
        ===================================================== */
        .modal {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
        }
        .modal.open { display: flex; }
        .modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(17,24,39,.65);
            backdrop-filter: blur(4px);
        }
        .modal-panel {
            position: relative;
            width: 100%;
            max-width: 560px;
            max-height: 88vh;
            background: var(--white);
            border-radius: 20px 20px 0 0;
            box-shadow: var(--shadow-xl);
            display: flex;
            flex-direction: column;
            animation: modalSlideUp .25s ease-out;
        }
        @keyframes modalSlideUp {
            from { transform: translateY(100%); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            flex-shrink: 0;
        }
        .modal-header-info {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .modal-header-info .exam-cell-icon {
            width: 48px;
            height: 48px;
            font-size: 18px;
            flex-shrink: 0;
        }
        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--gray-900);
            word-break: break-word;
            line-height: 1.3;
        }
        .modal-subtitle {
            font-size: 13px;
            color: var(--gray-500);
            margin-top: 2px;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: var(--radius);
            border: 1px solid var(--gray-200);
            background: var(--white);
            color: var(--gray-500);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            transition: all .15s;
        }
        .modal-close:hover {
            background: var(--gray-50);
            color: var(--gray-800);
        }
        .modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            flex: 1;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
        }
        .detail-item {
            padding: 14px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
        }
        .detail-item-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .detail-item-value {
            margin-top: 6px;
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-800);
            word-break: break-word;
        }
        .modal-section-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 12px;
        }
        .subject-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .subject-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            background: var(--white);
            flex-wrap: wrap;
        }
        .subject-item-name {
            font-weight: 700;
            color: var(--gray-800);
            font-size: 14px;
            word-break: break-word;
        }
        .subject-item-code {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 2px;
        }
        .subject-item-marks {
            text-align: right;
            flex-shrink: 0;
        }
        .subject-item-marks strong {
            font-size: 14px;
            color: var(--gray-800);
        }
        .subject-item-marks span {
            font-size: 12px;
            color: var(--gray-500);
            display: block;
            margin-top: 2px;
        }
        .modal-alert {
            padding: 14px;
            border-radius: var(--radius);
            background: var(--warning-soft);
            color: #92400e;
            font-size: 13px;
            line-height: 1.6;
            border: 1px solid #fde68a;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            flex-shrink: 0;
        }

        /* =====================================================
           RESPONSIVE
        ===================================================== */
        @media (min-width: 768px) {
            .modal {
                align-items: center;
                padding: 24px;
            }
            .modal-panel {
                border-radius: var(--radius-xl);
                max-height: 85vh;
                animation: modalFadeIn .2s ease-out;
            }
            @keyframes modalFadeIn {
                from { opacity: 0; transform: scale(.97); }
                to   { opacity: 1; transform: scale(1); }
            }
        }

        @media (max-width: 1199px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 991px) {
            .page-wrapper { padding: 20px; }
            .page-title { font-size: 24px; }
            .filter-form { flex-wrap: wrap; }
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
            .page-wrapper { padding: 16px; }
            .page-header { margin-bottom: 20px; }
            .page-title { font-size: 22px; }
            .page-desc { font-size: 13px; }
            .teacher-pill { width: 100%; justify-content: flex-start; }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 20px;
            }
            .stat-card { padding: 16px; }
            .stat-value { font-size: 26px; }
            .stat-label { font-size: 12px; }
            .filter-bar { padding: 14px 16px; }
            .filter-group.search,
            .filter-group.select {
                flex: 1 1 100%;
                min-width: 0;
            }
            .filter-actions {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            .filter-actions .btn { width: 100%; }
            .form-control { height: 48px; font-size: 16px; }
            .btn { height: 48px; }
            .content-card-header { padding: 16px 20px; }
            .table-responsive { display: none; }
            .mobile-cards { display: block; }
            .detail-grid { grid-template-columns: 1fr; gap: 10px; }
        }

        @media (max-width: 480px) {
            .page-wrapper { padding: 12px; }
            .stats-grid { gap: 8px; }
            .stat-card { padding: 14px; border-radius: var(--radius); }
            .stat-icon-wrap { width: 36px; height: 36px; font-size: 16px; }
            .stat-value { font-size: 22px; }
            .stat-label { font-size: 11px; }
            .filter-actions { grid-template-columns: 1fr; }
            .m-card { padding: 14px; }
            .m-card-body { grid-template-columns: 1fr; }
            .m-card-field {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .m-card-field-value { margin-top: 0; text-align: right; }
            .subject-item { flex-direction: column; align-items: flex-start; gap: 8px; }
            .subject-item-marks {
                text-align: left;
                width: 100%;
                padding-top: 8px;
                border-top: 1px solid var(--gray-100);
                display: flex;
                justify-content: space-between;
            }
        }

        /* Touch optimization */
        @media (hover: none) and (pointer: coarse) {
            .btn, .form-control { min-height: 44px; }
            .stat-card:hover { transform: none; }
        }

        /* Reduced motion */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation: none !important;
                transition: none !important;
            }
        }

        /* Utility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- HEADER -->
    <header class="page-header">
        <div class="page-header-left">
            <nav class="breadcrumb">
                <a href="dashboard.php">Teacher Portal</a>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-current">Exams</span>
            </nav>
            <h1 class="page-title">Examinations</h1>
            <p class="page-desc">
                View and manage examination schedules for all your assigned classes and subjects.
            </p>
        </div>
        <div class="teacher-pill">
            <div class="teacher-pill-avatar">T</div>
            <div>
                <div class="teacher-pill-name">Teacher Workspace</div>
                <div class="teacher-pill-meta">
                    <?= count($allowedClasses) ?> assigned <?= count($allowedClasses) === 1 ? 'class' : 'classes' ?>
                </div>
            </div>
        </div>
    </header>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon-wrap">E</div>
                <span class="stat-badge">All</span>
            </div>
            <div class="stat-value"><?= $totalExams ?></div>
            <div class="stat-label">Total Exams</div>
        </div>
        <div class="stat-card upcoming">
            <div class="stat-header">
                <div class="stat-icon-wrap">⏳</div>
                <span class="stat-badge">Scheduled</span>
            </div>
            <div class="stat-value"><?= $upcomingExams ?></div>
            <div class="stat-label">Upcoming</div>
        </div>
        <div class="stat-card active">
            <div class="stat-header">
                <div class="stat-icon-wrap">▶</div>
                <span class="stat-badge">Current</span>
            </div>
            <div class="stat-value"><?= $activeExams ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card completed">
            <div class="stat-header">
                <div class="stat-icon-wrap">✓</div>
                <span class="stat-badge">Done</span>
            </div>
            <div class="stat-value"><?= $completedExams ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="filter-bar">
        <form method="get" action="<?= e($_SERVER['PHP_SELF']) ?>" class="filter-form">
            <div class="filter-group search">
                <label class="filter-label" for="search">Search exams</label>
                <div class="filter-input-wrap">
                    <span class="filter-input-icon">⌕</span>
                    <input
                        type="search"
                        id="search"
                        name="search"
                        class="form-control search"
                        value="<?= e($search) ?>"
                        placeholder="Search by exam name..."
                        autocomplete="off"
                    >
                </div>
            </div>
            <div class="filter-group select">
                <label class="filter-label" for="class_id">Class</label>
                <select id="class_id" name="class_id" class="form-control">
                    <option value="">All assigned classes</option>
                    <?php foreach ($allowedClasses as $class): ?>
                        <option
                            value="<?= (int) $class['id'] ?>"
                            <?= $classFilter === (string) $class['id'] ? 'selected' : '' ?>
                        >
                            <?= e($class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group select">
                <label class="filter-label" for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">All statuses</option>
                    <option value="upcoming"  <?= $status === 'upcoming'  ? 'selected' : '' ?>>Upcoming</option>
                    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="<?= e($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Clear</a>
            </div>
        </form>
    </div>

    <!-- EXAMS LIST -->
    <div class="content-card">
        <div class="content-card-header">
            <div class="content-card-title">
                <div class="content-card-icon">E</div>
                <div>
                    <h2>Examination Schedule</h2>
                    <p>Exams within your teaching scope</p>
                </div>
            </div>
            <span class="result-badge">
                <?= $totalExams ?> <?= $totalExams === 1 ? 'result' : 'results' ?>
            </span>
        </div>

        <?php if (empty($exams)): ?>

            <div class="empty-state">
                <div class="empty-icon">E</div>
                <h3>No examinations found</h3>
                <p>
                    No exams match your current filters, or no exams have been created for your assigned classes yet.
                </p>
                <?php if ($search !== '' || $status !== '' || $classFilter !== ''): ?>
                    <a href="<?= e($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- DESKTOP TABLE -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Examination</th>
                            <th>Class</th>
                            <th>Schedule</th>
                            <th>Your Subjects</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam):
                            $examId = (int) ($exam['id'] ?? 0);
                            $modalId = 'exam-modal-' . $examId;
                            $examStatus = (string) ($exam['status'] ?? '');
                            $teacherSubjects = $exam['teacher_subjects'] ?? [];
                        ?>
                        <tr>
                            <td>
                                <div class="exam-cell">
                                    <div class="exam-cell-icon">E</div>
                                    <div class="exam-cell-info">
                                        <div class="exam-cell-name"><?= e($exam['name'] ?? 'Untitled Exam') ?></div>
                                        <div class="exam-cell-type"><?= e(examTypeLabel((string) ($exam['type'] ?? 'other'))) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="cell-text"><?= e($exam['class_name'] ?? '—') ?></td>
                            <td class="cell-date"><?= e(examDateRange($exam)) ?></td>
                            <td>
                                <div class="chip-list">
                                    <?php if (!empty($teacherSubjects)): ?>
                                        <?php foreach (array_slice($teacherSubjects, 0, 3) as $subject): ?>
                                            <span class="chip"><?= e($subject['subject_name'] ?? 'Subject') ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($teacherSubjects) > 3): ?>
                                            <span class="chip chip-more">+<?= count($teacherSubjects) - 3 ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="chip chip-more">None assigned</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-pill <?= e(statusClass($examStatus)) ?>">
                                    <span class="status-dot"></span>
                                    <?= e(examStatusLabel($examStatus)) ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" data-open-modal="<?= e($modalId) ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- MOBILE CARDS -->
            <div class="mobile-cards">
                <?php foreach ($exams as $exam):
                    $examId = (int) ($exam['id'] ?? 0);
                    $modalId = 'exam-modal-' . $examId;
                    $examStatus = (string) ($exam['status'] ?? '');
                    $teacherSubjects = $exam['teacher_subjects'] ?? [];
                ?>
                <article class="m-card">
                    <div class="m-card-header">
                        <div class="m-card-exam">
                            <div class="exam-cell-icon">E</div>
                            <div>
                                <div class="m-card-title"><?= e($exam['name'] ?? 'Untitled Exam') ?></div>
                                <div class="m-card-sub"><?= e(examTypeLabel((string) ($exam['type'] ?? 'other'))) ?></div>
                            </div>
                        </div>
                        <span class="status-pill <?= e(statusClass($examStatus)) ?>">
                            <span class="status-dot"></span>
                            <?= e(examStatusLabel($examStatus)) ?>
                        </span>
                    </div>
                    <div class="m-card-body">
                        <div class="m-card-field">
                            <div class="m-card-field-label">Class</div>
                            <div class="m-card-field-value"><?= e($exam['class_name'] ?? '—') ?></div>
                        </div>
                        <div class="m-card-field">
                            <div class="m-card-field-label">Schedule</div>
                            <div class="m-card-field-value"><?= e(examDateRange($exam)) ?></div>
                        </div>
                    </div>
                    <div class="m-card-section">
                        <div class="m-card-section-label">Your Assigned Subjects</div>
                        <div class="chip-list">
                            <?php if (!empty($teacherSubjects)): ?>
                                <?php foreach (array_slice($teacherSubjects, 0, 4) as $subject): ?>
                                    <span class="chip"><?= e($subject['subject_name'] ?? 'Subject') ?></span>
                                <?php endforeach; ?>
                                <?php if (count($teacherSubjects) > 4): ?>
                                    <span class="chip chip-more">+<?= count($teacherSubjects) - 4 ?> more</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="chip chip-more">No assigned subject</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="m-card-footer">
                        <button type="button" class="btn btn-secondary" data-open-modal="<?= e($modalId) ?>">
                            View Exam Details
                        </button>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- MODALS -->
<?php if (!empty($exams)): ?>
<?php foreach ($exams as $exam):
    $examId = (int) ($exam['id'] ?? 0);
    $modalId = 'exam-modal-' . $examId;
    $examStatus = (string) ($exam['status'] ?? '');
    $teacherSubjects = $exam['teacher_subjects'] ?? [];
?>
<div class="modal" id="<?= e($modalId) ?>" aria-hidden="true">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="<?= e($modalId) ?>-title">
        <div class="modal-header">
            <div class="modal-header-info">
                <div class="exam-cell-icon">E</div>
                <div>
                    <div class="modal-title" id="<?= e($modalId) ?>-title"><?= e($exam['name'] ?? 'Untitled Exam') ?></div>
                    <div class="modal-subtitle"><?= e(examTypeLabel((string) ($exam['type'] ?? 'other'))) ?></div>
                </div>
            </div>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">×</button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label">Class</div>
                    <div class="detail-item-value"><?= e($exam['class_name'] ?? '—') ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Status</div>
                    <div class="detail-item-value">
                        <span class="status-pill <?= e(statusClass($examStatus)) ?>">
                            <span class="status-dot"></span>
                            <?= e(examStatusLabel($examStatus)) ?>
                        </span>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Start Date</div>
                    <div class="detail-item-value"><?= e(formatExamDate($exam['start_date'] ?? null)) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">End Date</div>
                    <div class="detail-item-value"><?= e(formatExamDate($exam['end_date'] ?? null)) ?></div>
                </div>
            </div>

            <h4 class="modal-section-title">Your Assigned Subjects</h4>
            <?php if (!empty($teacherSubjects)): ?>
                <div class="subject-list">
                    <?php foreach ($teacherSubjects as $subject): ?>
                    <div class="subject-item">
                        <div>
                            <div class="subject-item-name"><?= e($subject['subject_name'] ?? 'Subject') ?></div>
                            <div class="subject-item-code"><?= e($subject['subject_code'] ?? 'No code') ?></div>
                        </div>
                        <div class="subject-item-marks">
                            <strong><?= e((string) ($subject['total_marks'] ?? 0)) ?> Marks</strong>
                            <span>Pass: <?= e((string) ($subject['pass_marks'] ?? 0)) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="modal-alert">
                    This exam belongs to one of your assigned classes, but none of its subjects are currently assigned to you.
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Close</button>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>
(function () {
    'use strict';

    let activeModal = null;
    let scrollY = 0;

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        if (activeModal) closeModal();

        scrollY = window.scrollY || window.pageYOffset;

        document.body.style.position = 'fixed';
        document.body.style.top = '-' + scrollY + 'px';
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
        document.body.style.overflow = 'hidden';

        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        activeModal = modal;

        const closeBtn = modal.querySelector('.modal-close');
        if (closeBtn) closeBtn.focus();
    }

    function closeModal() {
        if (!activeModal) return;

        activeModal.classList.remove('open');
        activeModal.setAttribute('aria-hidden', 'true');
        activeModal = null;

        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';
        document.body.style.overflow = '';
        window.scrollTo(0, scrollY);
    }

    document.addEventListener('click', function (e) {
        const openBtn = e.target.closest('[data-open-modal]');
        if (openBtn) {
            openModal(openBtn.getAttribute('data-open-modal'));
            return;
        }
        const closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) {
            closeModal();
            return;
        }
        if (e.target.classList.contains('modal-backdrop')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && activeModal) {
            closeModal();
        }
    });
})();
</script>

</body>
</html>