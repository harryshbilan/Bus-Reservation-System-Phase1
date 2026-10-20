<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_role_admin();
require_once __DIR__ . '/layout.php';

$error = null;
$buses = [];
$schedules = [];
$selected = null;
$id = filter_var($_GET['bus'] ?? null, FILTER_VALIDATE_INT);
$showPast = ($_GET['past'] ?? '') === '1';
$now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');

try {
    $q = $pdo->prepare("
        SELECT b.bus_id, b.bus_number, b.bus_type, b.total_seats, b.amenities, o.operator_name,
               GROUP_CONCAT(DISTINCT CONCAT(rt.origin, ' → ', rt.destination) SEPARATOR ', ') AS routes,
               SUM(TIMESTAMP(s.travel_date, s.departure_time) >= ?) AS upcoming
        FROM buses b
        LEFT JOIN bus_operators o ON o.operator_id = b.operator_id
        LEFT JOIN schedules s ON s.bus_id = b.bus_id
        LEFT JOIN routes rt ON rt.route_id = s.route_id
        GROUP BY b.bus_id
        ORDER BY o.operator_name, b.bus_number
    ");
    $q->execute([$now]);
    $buses = $q->fetchAll(PDO::FETCH_ASSOC);

    if ($id) {
        foreach ($buses as $bus) {
            if ((int) $bus['bus_id'] === $id) {
                $selected = $bus;
            }
        }
        $q = $pdo->prepare("
            SELECT s.travel_date, s.departure_time, s.arrival_time, s.fare, rt.origin, rt.destination, rt.boarding_point,
                   (SELECT COUNT(*) FROM reservations r WHERE r.schedule_id = s.schedule_id AND r.status IN ('PENDING', 'PAID')) AS booked
            FROM schedules s
            JOIN routes rt ON rt.route_id = s.route_id
            WHERE s.bus_id = ? AND (TIMESTAMP(s.travel_date, s.departure_time) >= ? OR ?)
            ORDER BY s.travel_date, s.departure_time
            LIMIT 120
        ");
        $q->execute([$id, $now, $showPast ? 1 : 0]);
        $schedules = $q->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('BUSWAY admin fleet: ' . $e->getMessage());
    $error = 'Fleet records could not be loaded.';
}

$operators = array_count_values(array_map(fn ($b) => $b['operator_name'] ?? 'No operator', $buses));

admin_page_start('Fleet', 'fleet', $admin, 'fleet-page');
?>
<div class="ad-head">
    <div>
        <span class="ad-kicker">Fleet overview</span>
        <h1>Fleet &amp; departures</h1>
        <p><?= count($buses) ?> buses across <?= count($operators) ?> operators. Pick a bus to see its schedule and how full each trip is.</p>
    </div>
    <div class="ad-actions"><a class="button ghost" href="published-services.php">Published timetables →</a></div>
</div>

<p class="notice">The operators and fleet numbers are fictional sample data for this project, not verified operator services.</p>

<?php if ($error): ?>
    <p class="error" role="alert"><?= admin_h($error) ?></p>
<?php else: ?>
    <?php if ($selected): ?>
        <section class="panel" id="fleet-departures">
            <div class="ad-head" style="margin-bottom: 12px">
                <div>
                    <span class="ad-kicker"><?= admin_h($selected['operator_name'] ?? 'No operator') ?></span>
                    <h2 style="margin: 0">Bus <?= admin_h($selected['bus_number']) ?> · <?= admin_h($selected['bus_type']) ?>, <?= (int) $selected['total_seats'] ?> seats</h2>
                </div>
                <div class="ad-actions">
                    <a class="button ghost" href="fleet.php?bus=<?= (int) $id ?>&amp;past=<?= $showPast ? '0' : '1' ?>#fleet-departures"><?= $showPast ? 'Upcoming only' : 'Include past trips' ?></a>
                    <a class="button ghost" href="fleet.php">Close</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Route</th><th>Departs</th><th>Arrives</th><th>Fare</th><th>Booked</th></tr></thead>
                    <tbody>
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td><?= admin_h(date('D, M j, Y', strtotime($s['travel_date']))) ?></td>
                                <td><?= admin_h($s['origin'] . ' → ' . $s['destination']) ?><small><?= admin_h($s['boarding_point']) ?></small></td>
                                <td><?= admin_h(date('g:i A', strtotime($s['departure_time']))) ?></td>
                                <td><?= admin_h(date('g:i A', strtotime($s['arrival_time']))) ?><?= $s['arrival_time'] < $s['departure_time'] ? ' <small>next day</small>' : '' ?></td>
                                <td>₱<?= number_format((float) $s['fare'], 2) ?></td>
                                <td><?= (int) $s['booked'] ?> / <?= (int) $selected['total_seats'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$schedules): ?>
                            <tr><td colspan="6"><?= $showPast ? 'This bus has no schedules.' : 'No upcoming trips for this bus.' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Bus</th><th>Operator</th><th>Type</th><th>Seats</th><th>Route</th><th>Upcoming trips</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($buses as $b): ?>
                    <tr<?= $id && (int) $b['bus_id'] === $id ? ' style="background:#fff5e2"' : '' ?>>
                        <td><strong><?= admin_h($b['bus_number']) ?></strong></td>
                        <td><?= admin_h($b['operator_name'] ?? '—') ?></td>
                        <td><?= admin_h($b['bus_type']) ?><small><?= admin_h(str_replace(',', ', ', $b['amenities'])) ?></small></td>
                        <td><?= (int) $b['total_seats'] ?></td>
                        <td><?= admin_h($b['routes'] ?: '—') ?></td>
                        <td><?= (int) $b['upcoming'] ?></td>
                        <td>
                            <a class="fleet-view" href="fleet.php?bus=<?= (int) $b['bus_id'] ?>#fleet-departures" aria-label="<?= admin_h('View departures for bus ' . $b['bus_number']) ?>">Departures →</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <details class="fleet-references">
        <summary>Official operator &amp; terminal references</summary>
        <p>Use published operator details when preparing new fleet records. These links don't verify the sample departures above.</p>
        <ul>
            <li><a href="https://www.victoryliner.com/" target="_blank" rel="noopener noreferrer">Victory Liner: official booking and service information</a></li>
            <li><a href="https://jacliner.com/schedules" target="_blank" rel="noopener noreferrer">JAC Liner: published daily schedules</a></li>
            <li><a href="https://pitx.ph/routes-guide/" target="_blank" rel="noopener noreferrer">PITX: official routes and terminal guide</a></li>
        </ul>
    </details>
<?php endif; ?>
<?php admin_page_end(); ?>
