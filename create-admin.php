<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| create-admin.php — CLI-only administrator installer
|--------------------------------------------------------------------------
|
| This script used to run in the browser with a hard-coded admin
| password, which is a security risk. It now:
|
|   1. Refuses to run over HTTP (404).
|   2. Contains no built-in credentials.
|   3. Requires the password to be supplied on the command line
|      and stores only password_hash() output.
|
| Usage:
|   php create-admin.php --email=admin@example.com --password=YourStrongP@ss [--name="Full Name"]
|
| Create the account, then delete this file if you do not need it again.
|
*/

if (PHP_SAPI !== 'cli') {
    // Do not reveal that this endpoint exists.
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/config/Database.php';

/**
 * Minimal command line option parser (--key=value or --key "value").
 */
function parse_cli_options(array $argv): array
{
    $options = [];

    for ($i = 1, $count = count($argv); $i < $count; $i++) {
        $arg = (string) $argv[$i];

        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $arg = substr($arg, 2);
        $eq = strpos($arg, '=');

        if ($eq !== false) {
            $options[substr($arg, 0, $eq)] = substr($arg, $eq + 1);
            continue;
        }

        // --key "value"
        if (isset($argv[$i + 1]) && !str_starts_with((string) $argv[$i + 1], '--')) {
            $options[$arg] = (string) $argv[++$i];
            continue;
        }

        $options[$arg] = true;
    }

    return $options;
}

$options = parse_cli_options($argv);

$email    = strtolower(trim((string) ($options['email'] ?? '')));
$password = (string) ($options['password'] ?? '');
$name     = trim((string) ($options['name'] ?? 'Administrator'));

$errors = [];

if ($email === '') {
    $errors[] = '--email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'The provided email address is not valid.';
}

if ($password === '') {
    $errors[] = '--password is required.';
} elseif (strlen($password) < 8) {
    $errors[] = 'Password must be at least 8 characters long.';
} elseif (strlen($password) > 72) {
    $errors[] = 'Password must not exceed 72 characters.';
}

if ($errors !== []) {
    fwrite(STDERR, "Cannot create administrator:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - {$error}\n");
    }
    fwrite(STDERR, "\nUsage:\n");
    fwrite(STDERR, "  php create-admin.php --email=admin@example.com --password=YourStrongP@ss [--name=\"Full Name\"]\n");
    exit(1);
}

try {
    $database = new Database();
    $pdo = $database->connect();

    // -------------------------------------------------
    // ALREADY EXISTS?
    // -------------------------------------------------

    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);

    if ($check->fetchColumn()) {
        fwrite(STDERR, "An account with this email already exists.\n");
        exit(1);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($hash === false) {
        fwrite(STDERR, "Unable to hash the password.\n");
        exit(1);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password, role, status)
         VALUES (:name, :email, :password, :admin, :active)'
    );

    $stmt->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':password' => $hash,
        ':admin'    => 'admin',
        ':active'   => 'active',
    ]);

    echo "Administrator created successfully.\n";
    echo "  Email: {$email}\n";
    echo "  Role:  admin\n";
    echo "\nSign in at auth/login.php and delete this file if you no longer need it.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed to create administrator: ' . $e->getMessage() . "\n");
    exit(1);
}
