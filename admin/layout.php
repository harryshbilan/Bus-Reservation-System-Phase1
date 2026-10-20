<?php
if (!defined('BUSWAY_ADMIN')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Shared admin page top: head, header with navigation, and the opening <main>. */
function admin_page_start(string $title, string $active, array $admin, string $mainClass = ''): void
{
    // [href, label, admin only?]
    $links = [
        'dashboard' => ['index.php', 'Dashboard', false],
        'tickets' => ['tickets.php', 'Tickets', false],
        'fleet' => ['fleet.php', 'Fleet', true],
        'timetables' => ['published-services.php', 'Timetables', true],
        'staff' => ['staff.php', 'Staff', true],
        'activity' => ['activity.php', 'Activity', true],
        'cleanup' => ['booking-cleanup.php', 'Cleanup', true],
    ];
    $isAdmin = ($admin['role'] ?? '') === 'admin';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= admin_h($title) ?> | BUSWAY Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Outfit:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="../assets/css/monitor.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<header class="admin-topbar">
    <a class="admin-brand" href="index.php">
        <span class="admin-logo"><img src="../assets/images/logo.jpg" alt="" width="44" height="44"></span>
        <span class="admin-brand-text">
            <strong>BUSWAY</strong>
            <small>Admin workspace</small>
        </span>
    </a>
    <nav class="admin-navigation" aria-label="Admin navigation">
        <?php foreach ($links as $key => [$href, $label, $adminOnly]): ?>
            <?php if ($adminOnly && !$isAdmin) {
                continue;
            } ?>
            <a href="<?= $href ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-user">
        <a class="admin-public-link" href="../public/index.php" target="_blank" rel="noopener">Passenger site ↗</a>
        <span class="admin-user-name" title="Signed in"><?= admin_h($admin['display_name']) ?> · <?= $isAdmin ? 'Admin' : 'Staff' ?></span>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
            <button type="submit" class="admin-signout">Sign out</button>
        </form>
    </div>
</header>
<main<?= $mainClass !== '' ? ' class="' . admin_h($mainClass) . '"' : '' ?>>
    <?php
}

function admin_page_end(): void
{
    ?>
</main>
</body>
</html>
    <?php
}

/** "Oct 20, 2026 · 5:00 AM" from separate date and time columns. */
function admin_when(?string $date, ?string $time = null): string
{
    if (!$date) {
        return '—';
    }
    $stamp = strtotime($date . ($time ? ' ' . $time : ''));

    return $time ? date('M j, Y · g:i A', $stamp) : date('M j, Y', $stamp);
}
