<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($script !== '' && realpath($script) === realpath(__FILE__)) {
        http_response_code(403);
        exit('Forbidden.');
    }
}

require_once dirname(__DIR__) . '/config/Database.php';

$pdo = (new Database())->connect();

// Run all migrations in order
$migrationFiles = [
    dirname(__DIR__) . '/database/migrations/002_exam_subjects_notifications.sql',
    dirname(__DIR__) . '/database/migrations/003_marks_audit_fields.sql',
    dirname(__DIR__) . '/database/migrations/004_teacher_assignment_sync.sql',
    dirname(__DIR__) . '/database/migrations/005_result_settings_notifications_alignment.sql',
    dirname(__DIR__) . '/database/migrations/006_parent_role.sql',
    dirname(__DIR__) . '/database/migrations/007_timetable.sql',
    dirname(__DIR__) . '/database/migrations/008_schema_alignment.sql',
];

foreach ($migrationFiles as $sqlFile) {
    if (!file_exists($sqlFile)) {
        continue;
    }

    $sql = file_get_contents($sqlFile);

    if ($sql === false) {
        throw new RuntimeException('Migration file not found: ' . $sqlFile);
    }

    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));

    foreach ($statements as $statement) {
        // Strip full-line SQL comments. Without this, every chunk that
        // begins with a "--" comment line was skipped entirely, which
        // silently dropped the first statement of each migration file.
        $lines = array_filter(
            array_map('trim', explode("\n", $statement)),
            static fn (string $line): bool => !str_starts_with($line, '--')
        );
        $statement = trim(implode("\n", $lines));

        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // Ignore "duplicate column" errors for idempotent migrations.
            // Note: MariaDB reports a repeated index as "Duplicate key name"
            // (MySQL says "Duplicate index"), so both wordings are covered.
            if (strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate index') === false &&
                strpos($e->getMessage(), 'Duplicate key name') === false &&
                strpos($e->getMessage(), 'Duplicate foreign key') === false) {
                throw $e;
            }
        }
    }
}

if (PHP_SAPI === 'cli') {
    echo "Migration applied successfully.\n";
}
