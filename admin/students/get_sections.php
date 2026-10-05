<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/init.php';
requireAdmin();

require_once __DIR__ . '/../../classes/Student.php';


header('Content-Type: application/json; charset=utf-8');

try {

    $classId = filter_input(
        INPUT_GET,
        'class_id',
        FILTER_VALIDATE_INT
    );

    if (!$classId || $classId <= 0) {

        echo json_encode([
            'success' => false,
            'sections' => [],
            'message' => 'Invalid class.'
        ]);

        exit;
    }


    $database = new Database();

    $pdo = $database->connect();

    $studentObj = new Student($pdo);


    $sections = $studentObj->getSectionsByClass(
        (int) $classId
    );


    echo json_encode([
        'success' => true,
        'sections' => $sections,
        'has_sections' => !empty($sections)
    ], JSON_UNESCAPED_UNICODE);

    exit;


} catch (Throwable $e) {

    error_log(
        'Get Sections Error: ' .
        $e->getMessage()
    );


    http_response_code(500);


    echo json_encode([
        'success' => false,
        'sections' => [],
        'has_sections' => false,
        'message' => 'Unable to load sections.'
    ]);

    exit;
}