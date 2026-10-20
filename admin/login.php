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

            if ($admin && !$signedIn) {
                admin_log($pdo, (int) $admin['admin_id'], 'sign_in_failed', null, !(int) $admin['is_active'] ? 'Disabled account' : ((int) $admin['is_locked'] ? 'Attempt while locked' : 'Wrong password'));
            }

            if ($signedIn) {
                // Replace the session ID after successful authentication.
                session_regenerate_id(true);

                $_SESSION = [
                    'admin_id' => (int) $admin['admin_id'],
                    'last_activity' => time(),
                    'csrf_token' => bin2hex(random_bytes(32))
                ];

                admin_log($pdo, (int) $admin['admin_id'], 'signed_in');

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
    <title>Staff sign in | BUSWAY Admin</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Outfit:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="../assets/css/monitor.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<main class="ad-login">
    <div class="ad-login-card">
        <span class="admin-logo" style="display: block"><img src="../assets/images/logo.jpg" alt="" width="72" height="72"></span>
        <h1>BUSWAY Admin</h1>
        <p>Staff sign in to manage bookings and payments.</p>

        <?php if ($error): ?>
            <p class="error" role="alert"><?= admin_h($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
            <label for="username">Username</label>
            <input id="username" name="username" value="<?= admin_h($username) ?>" maxlength="50" autocomplete="username" required autofocus>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" maxlength="72" autocomplete="current-password" required>
            <button type="submit">Sign in</button>
        </form>
        <p class="muted" style="font-size: 13px; text-align: center; margin: 14px 0 0">Five wrong tries locks the account for 15 minutes.</p>
        <a class="ad-back" href="../public/index.php">← Back to BUSWAY</a>
    </div>
</main>
</body>
</html>
