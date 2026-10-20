<?php

function registerUser(mysqli $db, array $data): array
{
    // Require all signup fields to contain text.
    $required = [
        'username', 'password', 'first_name', 'last_name', 'email'
    ];

    foreach ($required as $field) {
        if (!isset($data[$field]) || !is_string($data[$field])
            || trim($data[$field]) === '') {
            return [
                'success' => false,
                'message' => "Missing or invalid field: $field"
            ];
        }
    }

    $username = trim($data['username']);
    $firstName = trim($data['first_name']);
    $lastName = trim($data['last_name']);
    $email = trim($data['email']);
    $password = $data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    // Conservative byte limits that fit the database columns.
    if (strlen($username) > 191 || strlen($firstName) > 100
        || strlen($lastName) > 100 || strlen($email) > 254) {
        return ['success' => false, 'message' => 'A field is too long.'];
    }

    // Bcrypt accepts at most 72 bytes; reject rather than truncate.
    if (strlen($password) < 8 || strlen($password) > 72
        || strpos($password, "\0") !== false) {
        return [
            'success' => false,
            'message' => 'Password must be 8–72 bytes without null characters.'
        ];
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $db->prepare(
            'INSERT INTO users
             (username, password_hash, first_name, last_name, email)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->bind_param(
            'sssss',
            $username,
            $passwordHash,
            $firstName,
            $lastName,
            $email
        );

        $stmt->execute();
        $userId = $db->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'message' => 'Account created.',
            'user_id' => $userId
        ];
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            return [
                'success' => false,
                'message' => 'Username or email already exists.'
            ];
        }

        error_log($e->getMessage());
        return ['success' => false, 'message' => 'Database error.'];
    }
}
