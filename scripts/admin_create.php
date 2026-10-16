<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

define('BUSWAY_APP', true);
require __DIR__ . '/../includes/database.php';

try {
    $input = json_decode(
        stream_get_contents(STDIN),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $username = trim($input['username'] ?? '');
    $displayName = trim($input['display_name'] ?? '');
    $password = $input['password'] ?? '';

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username)) {
        throw new InvalidArgumentException(
            'Username must contain 3–50 letters, numbers or underscores.'
        );
    }

    if ($displayName === '' || mb_strlen($displayName) > 100) {
        throw new InvalidArgumentException(
            'Enter a display name between 1 and 100 characters.'
        );
    }

    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new InvalidArgumentException(
            'Use a password between 12 and 72 bytes.'
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO admin_users
            (username, password_hash, display_name)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $displayName
    ]);

    echo "Admin account created successfully.\n";
} catch (PDOException $error) {
    fwrite(
        STDERR,
        "Account creation failed. Check whether the username already exists and the database is available.\n"
    );
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}