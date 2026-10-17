<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();

    $username = is_string($_POST['username'] ?? null)
        ? trim($_POST['username'])
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    $error = 'Unable to sign in. Check your details or try again later.';

    if (
        preg_match('/^[A-Za-z0-9_]{3,50}$/D', $username)
        && strlen($password) >= 12
        && strlen($password) <= 72
    ) {
        try {
            $pdo->beginTransaction();

            // Lock this account while checking and updating login attempts.
            $stmt = $pdo->prepare("
                SELECT *,
                    (locked_until IS NOT NULL
                     AND locked_until > NOW()) AS is_locked
                FROM admin_users
                WHERE username = ?
                FOR UPDATE
            ");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            $signedIn = false;

            if (
                $admin
                && (int) $admin['is_active'] === 1
                && !(int) $admin['is_locked']
            ) {
                if (password_verify($password, $admin['password_hash'])) {
                    $stmt = $pdo->prepare("
                        UPDATE admin_users
                        SET failed_login_attempts = 0,
                            locked_until = NULL
                        WHERE admin_id = ?
                    ");
                    $stmt->execute([$admin['admin_id']]);

                    $signedIn = true;
                } else {
                    // An expired lock starts a fresh attempt count.
                    $attempts = $admin['locked_until'] !== null
                        ? 1
                        : (int) $admin['failed_login_attempts'] + 1;

                    $stmt = $pdo->prepare("
                        UPDATE admin_users
                        SET failed_login_attempts = ?,
                            locked_until = CASE
                                WHEN ? >= 5
                                THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                ELSE NULL
                            END
                        WHERE admin_id = ?
                    ");

                    $stmt->execute([
                        $attempts,
                        $attempts,
                        $admin['admin_id']
                    ]);
                }
            }

            $pdo->commit();

            if ($signedIn) {
                // Replace the session ID after successful authentication.
                session_regenerate_id(true);

                $_SESSION = [
                    'admin_id' => (int) $admin['admin_id'],
                    'last_activity' => time(),
                    'csrf_token' => bin2hex(random_bytes(32))
                ];

                header('Location: index.php');
                exit;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('BUSWAY admin login failed: ' . $exception->getMessage());
            $error = 'Sign-in is temporarily unavailable. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | BUSWAY</title>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f7f3ee;
            color: #1c1208;
            font-family: Arial, sans-serif;
        }
        main {
            width: 100%;
            max-width: 420px;
            padding: 32px;
            background: white;
            border-top: 5px solid #e8940a;
            border-radius: 8px;
            box-shadow: 0 8px 30px #00000012;
        }
        h1 { margin-top: 0; }
        label { display: block; margin: 20px 0 8px; }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #aaa;
            border-radius: 4px;
            font: inherit;
        }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 4px;
            background: #e8940a;
            color: #1c1208;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
        }
        .error { padding: 12px; background: #fff0ee; color: #9b211b; }
        a { color: #684000; }
    </style>
</head>
<body>
<main>
    <h1>BUSWAY Admin</h1>
    <p>Sign in to manage bookings and schedules.</p>

    <?php if ($error): ?>
        <p class="error" role="alert"><?= admin_h($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input
            type="hidden"
            name="csrf_token"
            value="<?= admin_h(admin_csrf_token()) ?>"
        >

        <label for="username">Username</label>
        <input
            id="username"
            name="username"
            value="<?= admin_h($username) ?>"
            maxlength="50"
            autocomplete="username"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            maxlength="72"
            autocomplete="current-password"
            required
        >

        <button type="submit">Sign in</button>
    </form>

    <p><a href="../public/index.php">← Back to BUSWAY</a></p>
</main>
</body>
</html>