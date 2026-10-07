<?php

require_once __DIR__ . '/register_user.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $dbPassword = getenv('DB_PASSWORD');

    if ($dbPassword === false || $dbPassword === '') {
        throw new RuntimeException('Set DB_PASSWORD before running the test.');
    }

    $mydb = new mysqli(
        '127.0.0.1',
        'auth_app',
        $dbPassword,
        'auth_project'
    );

    $mydb->set_charset('utf8mb4');

    $data = [
        'username' => 'testuser',
        'password' => 'ExamplePassword123!',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'testuser@example.com'
    ];

    $result = registerUser($mydb, $data);

    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;

    $mydb->close();
    exit($result['success'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'Test failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
