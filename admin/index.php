<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
require __DIR__ . '/ticket_monitor.php';
require_once __DIR__ . '/layout.php';

$error = null;
$totals = $toCollect = $departures = $recent = [];
$collected = ['Cash' => 0.0, 'GCash' => 0.0, 'Card' => 0.0];
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

try {
    $totals = ticket_monitor_totals($pdo);

    // Cash bookings that still need collecting, soonest departure first.
    $q = $pdo->prepare('SELECT * FROM (' . ticket_monitor_sql() . ') t
        WHERE status = \'PENDING\' AND FIND_IN_SET(\'Cash\', REPLACE(payment_methods, \', \', \',\')) > 0
          AND TIMESTAMP(travel_date, departure_time) >= ?
        ORDER BY travel_date, departure_time LIMIT 6');
    $q->execute([$now->format('Y-m-d H:i:s')]);
    $toCollect = $q->fetchAll(PDO::FETCH_ASSOC);

    // Next departures and how full they are.
    $q = $pdo->prepare("
        SELECT s.schedule_id, s.travel_date, s.departure_time, b.bus_number, b.total_seats,
               rt.origin, rt.destination,
               (SELECT COUNT(*) FROM reservations r
                 WHERE r.schedule_id = s.schedule_id AND r.status IN ('PENDING', 'PAID')) AS booked
        FROM schedules s
        JOIN buses b ON b.bus_id = s.bus_id
        JOIN routes rt ON rt.route_id = s.route_id
        WHERE TIMESTAMP(s.travel_date, s.departure_time) >= ?
        ORDER BY s.travel_date, s.departure_time, rt.origin
        LIMIT 8
    ");
    $q->execute([$now->format('Y-m-d H:i:s')]);
    $departures = $q->fetchAll(PDO::FETCH_ASSOC);

    // Money actually received, by how it was paid.
    foreach ($pdo->query("SELECT payment_method, SUM(amount) AS total FROM payments WHERE status = 'PAID' GROUP BY payment_method") as $row) {
        $collected[$row['payment_method']] = (float) $row['total'];
    }

    $recent = $pdo->query('SELECT * FROM (' . ticket_monitor_sql() . ') t ORDER BY newest_id DESC LIMIT 5')
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('BUSWAY admin dashboard: ' . $e->getMessage());
    $error = 'Dashboard figures could not be loaded. Refresh to try again.';
}

$hour = (int) $now->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

admin_page_start('Dashboard', 'dashboard', $admin);
?>
<div class="ad-head">
    <div>
        <span class="ad-kicker"><?= admin_h($now->format('l, F j')) ?></span>
        <h1><?= $greeting ?>, <?= admin_h($admin['display_name']) ?></h1>
        <p>Here's where bookings stand right now. Counts are booking references, not seats.</p>
    </div>
    <div class="ad-actions">
        <a class="button" href="tickets.php?status=PENDING">Pending tickets</a>
        <a class="button ghost" href="index.php">Refresh</a>
    </div>
</div>

<?php if ($error): ?>
    <p class="error" role="alert"><?= admin_h($error) ?></p>
<?php else: ?>
    <?php monitor_cards($totals, is_admin_role($admin)); ?>

    <div class="ad-grid">
        <div>
            <section class="panel">
                <h2>Cash to collect</h2>
                <?php if ($toCollect): ?>
                    <ul class="ad-list">
                        <?php foreach ($toCollect as $t): ?>
                            <li>
                                <span>
                                    <a class="ad-ref" href="ticket.php?ticket=<?= rawurlencode($t['ticket_number']) ?>"><?= admin_h($t['ticket_number']) ?></a>
                                    <span class="ad-sub"><?= admin_h($t['passenger_name']) ?> · <?= admin_h($t['route']) ?> · <?= admin_h(admin_when($t['travel_date'], $t['departure_time'])) ?> · <a href="discount.php?ticket=<?= rawurlencode($t['ticket_number']) ?>">Discount</a></span>
                                </span>
                                <a class="button" href="confirm_payment.php?ticket=<?= rawurlencode($t['ticket_number']) ?>">₱<?= number_format((float) $t['amount_due'], 2) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ad-empty">Nothing waiting. Every upcoming cash booking is paid.</p>
                <?php endif; ?>
            </section>

            <section class="panel">
                <h2>Next departures</h2>
                <?php if ($departures): ?>
                    <ul class="ad-list">
                        <?php foreach ($departures as $d):
                            $pct = $d['total_seats'] > 0 ? min(100, round($d['booked'] / $d['total_seats'] * 100)) : 0;
                            ?>
                            <li>
                                <span>
                                    <strong><?= admin_h($d['origin'] . ' → ' . $d['destination']) ?></strong>
                                    <span class="ad-sub">Bus <?= admin_h($d['bus_number']) ?> · <?= admin_h(admin_when($d['travel_date'], $d['departure_time'])) ?></span>
                                </span>
                                <span title="<?= (int) $d['booked'] ?> of <?= (int) $d['total_seats'] ?> seats booked">
                                    <span class="ad-bar<?= $pct >= 100 ? ' full' : '' ?>"><i style="width: <?= $pct ?>%"></i></span>
                                    <span class="ad-sub"><?= (int) $d['booked'] ?>/<?= (int) $d['total_seats'] ?> booked</span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="ad-empty">No departures scheduled. Add schedules to keep the site bookable.</p>
                <?php endif; ?>
            </section>
        </div>

        <div>
            <?php if (is_admin_role($admin)): ?>
            <section class="panel">
                <h2>Money received</h2>
                <div class="ad-money">
                    <?php foreach ($collected as $method => $amount): ?>
                        <div><span><?= $method === 'Card' ? 'Credit / debit card' : admin_h($method) ?></span><strong>₱<?= number_format($amount, 0) ?></strong></div>
                    <?php endforeach; ?>
                </div>
                <p class="ad-empty">All-time payments marked paid. Refunds aren't tracked here.</p>
            </section>
            <?php endif; ?>

            <section class="panel">
                <h2>Latest bookings</h2>
                <ul class="ad-list">
                    <?php foreach ($recent as $t): ?>
                        <li>
                            <span>
                                <a class="ad-ref" href="ticket.php?ticket=<?= rawurlencode($t['ticket_number']) ?>"><?= admin_h($t['ticket_number']) ?></a>
                                <span class="ad-sub"><?= admin_h($t['passenger_name']) ?> · <?= admin_h($t['route']) ?></span>
                            </span>
                            <span class="status <?= admin_h($t['status']) ?>"><?= admin_h($t['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><li class="ad-empty">No bookings yet.</li><?php endif; ?>
                </ul>
            </section>

            <section class="panel ad-quick">
                <a href="tickets.php">Find a ticket <small>Search by reference, name, email or bus</small></a>
                <?php if (is_admin_role($admin)): ?>
                <a href="staff.php">Staff accounts <small>Add staff, reset passwords, set roles</small></a>
                <a href="activity.php">Activity log <small>Who confirmed, discounted or deleted what</small></a>
                <a href="fleet.php">Fleet &amp; departures <small>Buses, operators and schedules</small></a>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
<?php admin_page_end(); ?>
