<?php
if (!defined('BUSWAY_ADMIN')) {
    http_response_code(403);
    exit('Forbidden');
}

// Keep admin sessions separate from passenger booking sessions.
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_name('BUSWAY_ADMIN_SESSION');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/Bus-Reservation-System-Phase1/admin/',
    'secure' => !empty($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/database.php';

// Escape text before displaying it in HTML.
function admin_h(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

// Create a token for admin forms.
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Check that a submitted form has the correct token.
function admin_verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        $expected === '' ||
        !hash_equals($expected, $token)
    ) {
        http_response_code(403);
        exit('Your form has expired. Reload the page and try again.');
    }
}

// Require a signed-in, active admin account.
function require_admin(): array
{
    global $pdo;

    $adminId = $_SESSION['admin_id'] ?? null;
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);

    if (!$adminId || time() - $lastActivity > 1800) {
        unset($_SESSION['admin_id'], $_SESSION['last_activity']);
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT admin_id, username, display_name
        FROM admin_users
        WHERE admin_id = ?
          AND is_active = 1
    ");

    $stmt->execute([$adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        unset($_SESSION['admin_id'], $_SESSION['last_activity']);
        header('Location: login.php');
        exit;
    }

    $_SESSION['last_activity'] = time();

    return $admin;
}