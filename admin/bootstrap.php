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
    // Derive from the request so it works whatever the project folder is named.
    'path' => rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/',
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
        SELECT admin_id, username, display_name, role
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

// Admin-only pages call this instead of require_admin(). Staff get a 403, not the page.
function require_role_admin(): array
{
    $user = require_admin();

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        admin_page_start('Admins only', '', $user);
        echo '<div class="ad-head"><div><span class="ad-kicker">Access denied</span><h1>Admins only</h1>'
            . '<p>This page is for administrators. Ask an admin if you need something here.</p></div></div>'
            . '<p><a class="button" href="index.php">Back to dashboard</a></p>';
        admin_page_end();
        exit;
    }

    return $user;
}

function is_admin_role(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
}

// Record an admin-area action. Logging must never break the action itself.
function admin_log(PDO $pdo, ?int $adminId, string $action, ?string $ticket = null, string $details = ''): void
{
    try {
        $pdo->prepare('INSERT INTO admin_activity (admin_id, action, ticket_number, details) VALUES (?, ?, ?, ?)')
            ->execute([$adminId, $action, $ticket, mb_substr($details, 0, 255)]);
    } catch (PDOException $e) {
        error_log('BUSWAY activity log failed: ' . $e->getMessage());
    }
}
