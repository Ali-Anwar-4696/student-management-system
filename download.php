<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
requireAuth();

$relative = trim((string) ($_GET['file'] ?? ''));
$relative = str_replace('\\', '/', $relative);
$relative = ltrim($relative, '/');

if ($relative === '' || str_contains($relative, '..')) {
    http_response_code(400);
    exit('Invalid file.');
}

if (!str_starts_with($relative, 'uploads/assignments/')) {
    http_response_code(403);
    exit('Access denied.');
}

$basename = basename($relative);
if ($basename === '' || $basename === '.' || $basename === '..') {
    http_response_code(400);
    exit('Invalid file.');
}

$storedPath = 'uploads/assignments/' . $basename;
$absolute = dirname(__FILE__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);

if (!is_file($absolute)) {
    http_response_code(404);
    exit('File not found.');
}

$pdo = db();
$role = currentRole();
$userId = (int) ($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT sub.id, sub.student_id, sub.assignment_id, a.teacher_id
     FROM submissions sub
     INNER JOIN assignments a ON a.id = sub.assignment_id
     WHERE sub.file_path = :path
     LIMIT 1'
);
$stmt->execute([':path' => $storedPath]);
$record = $stmt->fetch();

if (!$record) {
    http_response_code(404);
    exit('File not found.');
}

$allowed = false;

if ($role === 'admin') {
    $allowed = true;
} elseif ($role === 'student') {
    $student = current_student($pdo);
    $allowed = $student !== null && (int) $student['id'] === (int) $record['student_id'];
} elseif ($role === 'teacher') {
    $teacher = current_teacher($pdo);
    $allowed = $teacher !== null && (int) $teacher['id'] === (int) $record['teacher_id'];
}

if (!$allowed) {
    denyAccess('You are not allowed to download this file.');
}

$mime = 'application/octet-stream';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($absolute);
    if (is_string($detected) && $detected !== '') {
        $mime = $detected;
    }
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . rawurlencode($basename) . '"');
header('Content-Length: ' . (string) filesize($absolute));
header('Cache-Control: private, no-store');

readfile($absolute);
exit;
