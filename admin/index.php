<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
require __DIR__ . '/ticket_monitor.php';
$error = null;
$totals = [];
try {
    $totals = ticket_monitor_totals($pdo);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Monitoring totals could not be loaded.';
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Dashboard | BUSWAY Admin</title>
        <link rel="stylesheet" href="../assets/css/monitor.css" />
    </head>
    <body>
        <header>
            <strong>BUSWAY Admin</strong>
            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>" />
                <button>Sign out</button>
            </form>
        </header>
        <main>
            <h1>Welcome, <?= admin_h($admin['display_name']) ?>!</h1>
            <p class="muted">
                Your booking activity at a glance. Totals cover all dates and count booking
                references, not individual seats.
            </p>
            <?php if($error): ?>
            <p class="error"><?= admin_h($error) ?></p>
            <?php else: monitor_cards($totals); endif; ?>
            <div class="two-column">
                <section class="panel">
                    <h2>Tickets & payments</h2>
                    <p>Search every ticket, view its details and review pending cash payments.</p>
                    <a class="button" href="tickets.php">Open ticket monitor →</a>
                    <p><a href="reservations.php">Existing reservation management</a></p>
                </section>
                <section class="panel">
                    <h2>Fleet & departures</h2>
                    <p>
                        See registered buses and the routes, terminals and times stored in BUSWAY.
                    </p>
                    <a class="button" href="fleet.php">View fleet →</a>
                </section>
            </div>
            <p class="muted">
                Cancelled booking values are historical amounts, not refund totals. Recorded
                payments on cancelled bookings remain visible in ticket details.
            </p>
            <p>
                <a href="index.php">Refresh totals</a>
                ·
                <a href="../public/index.php">View passenger website</a>
            </p>
        </main>
    </body>
</html>
