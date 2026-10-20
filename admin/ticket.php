<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
require_once __DIR__ . '/layout.php';

$reference = is_string($_GET['ticket'] ?? null) ? trim($_GET['ticket']) : '';
$error = null;
$rows = [];
$payments = [];

try {
    $q = $pdo->prepare("SELECT r.reservation_id, r.seat_number, r.status, r.created_at,
          pax.name, pax.email, pax.phone, pax.age, pax.gender,
          b.bus_number, b.bus_type, o.operator_name,
          rt.origin, rt.destination, rt.boarding_point, s.travel_date, s.departure_time, s.arrival_time
        FROM reservations r
        JOIN passengers pax ON pax.passenger_id = r.passenger_id
        JOIN schedules s ON s.schedule_id = r.schedule_id
        JOIN buses b ON b.bus_id = s.bus_id
        LEFT JOIN bus_operators o ON o.operator_id = b.operator_id
        JOIN routes rt ON rt.route_id = s.route_id
        WHERE r.ticket_number = ?
        ORDER BY r.schedule_id, CAST(r.seat_number AS UNSIGNED), r.seat_number");
    $q->execute([$reference]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        http_response_code(404);
        $error = 'No ticket was found with that reference.';
    } else {
        $q = $pdo->prepare("SELECT p.payment_id, r.seat_number, p.amount, p.payment_method, p.status,
              p.payment_reference, p.account_hint, p.paid_at,
              pc.confirmed_at, au.display_name AS confirmed_by,
              fd.discount_type, fd.id_last4, fd.discount_amount
            FROM payments p
            JOIN reservations r ON r.reservation_id = p.reservation_id
            LEFT JOIN payment_confirmations pc ON pc.payment_id = p.payment_id
            LEFT JOIN admin_users au ON au.admin_id = pc.admin_id
            LEFT JOIN fare_discounts fd ON fd.payment_id = p.payment_id
            WHERE r.ticket_number = ?
            ORDER BY p.payment_id");
        $q->execute([$reference]);
        $payments = $q->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('BUSWAY admin ticket: ' . $e->getMessage());
    $error = 'Ticket details could not be loaded.';
}

$extras = ['extra_bags' => 0, 'bag_fee' => 0];

if ($rows) {
    $q = $pdo->prepare('SELECT extra_bags, bag_fee FROM booking_extras WHERE ticket_number = ?');
    $q->execute([$reference]);
    $extras = $q->fetch(PDO::FETCH_ASSOC) ?: $extras;
}
$extraBags = (int) $extras['extra_bags'];
$statuses = array_unique(array_column($rows, 'status'));
$status = count($statuses) === 1 ? reset($statuses) : 'REVIEW';
$due = $paid = $recorded = 0;

foreach ($payments as $p) {
    $cents = (int) round((float) $p['amount'] * 100);
    $recorded += $cents;

    if ($p['status'] === 'PAID') {
        $paid += $cents;
    }

    if ($p['status'] === 'PENDING') {
        $due += $cents;
    }
}
$methods = array_unique(array_column($payments, 'payment_method'));
$first = $rows[0] ?? null;
$zone = new DateTimeZone('Asia/Manila');

admin_page_start('Ticket ' . $reference, 'tickets', $admin);
?>
<p><a href="tickets.php">← All tickets</a></p>
<?php if ($error): ?>
    <p class="error" role="alert"><?= admin_h($error) ?></p>
<?php else:
    $departure = new DateTimeImmutable($first['travel_date'] . ' ' . $first['departure_time'], $zone);
    $arrival = new DateTimeImmutable($first['travel_date'] . ' ' . $first['arrival_time'], $zone);

    if ($arrival < $departure) {
        $arrival = $arrival->modify('+1 day');
    }
    ?>
    <div class="ad-head">
        <div>
            <span class="ad-kicker">Ticket details</span>
            <h1 class="ad-ref" style="font-size: clamp(22px, 3vw, 30px)"><?= admin_h($reference) ?></h1>
            <p><span class="status <?= admin_h($status) ?>"><?= admin_h($status) ?></span> · booked <?= admin_h(date('M j, Y · g:i A', strtotime($first['created_at']))) ?></p>
        </div>
        <div class="ad-actions no-print">
            <?php if ($status === 'PENDING' && in_array('Cash', $methods, true)): ?>
                <a class="button" href="confirm_payment.php?ticket=<?= rawurlencode($reference) ?>">Confirm cash payment</a>
            <?php endif; ?>
            <?php if ($status === 'PENDING'): ?>
                <a class="button ghost" href="discount.php?ticket=<?= rawurlencode($reference) ?>">Apply discount</a>
            <?php endif; ?>
            <button type="button" class="button ghost" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="ad-detail">
        <div>
            <section class="panel">
                <h2>Trip</h2>
                <dl>
                    <div><dt>Route</dt><dd><?= admin_h($first['origin'] . ' → ' . $first['destination']) ?></dd></div>
                    <div><dt>Bus</dt><dd><?= admin_h(trim(($first['operator_name'] ?? '') . ' ' . $first['bus_number'])) ?> · <?= admin_h($first['bus_type']) ?></dd></div>
                    <div><dt>Departure</dt><dd><?= admin_h($departure->format('D, M j, Y · g:i A')) ?></dd></div>
                    <div><dt>Arrival</dt><dd><?= admin_h($arrival->format('D, M j, Y · g:i A')) ?></dd></div>
                    <div><dt>Boarding point</dt><dd><?= admin_h($first['boarding_point']) ?></dd></div>
                    <div><dt>Seats</dt><dd><?= admin_h(implode(', ', array_column($rows, 'seat_number'))) ?></dd></div>
                    <div><dt>Extra checked bags</dt><dd><?= $extraBags ? $extraBags . ' × 20 kg (₱' . number_format((float) $extras['bag_fee'], 2) . ')' : 'None' ?></dd></div>
                </dl>
            </section>

            <h2>Payment records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>#</th><th>Seat</th><th>Method</th><th>Amount</th><th>Status</th><th>Details</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td><?= (int) $p['payment_id'] ?></td>
                                <td><?= admin_h($p['seat_number']) ?></td>
                                <td><span class="method"><?= admin_h($p['payment_method']) ?></span></td>
                                <td>₱<?= number_format((float) $p['amount'], 2) ?><?php if ($p['discount_type']): ?><small><?= admin_h($p['discount_type']) ?> −₱<?= number_format((float) $p['discount_amount'], 2) ?> · ID …<?= admin_h($p['id_last4']) ?></small><?php endif; ?></td>
                                <td><span class="status <?= admin_h($p['status']) ?>"><?= admin_h($p['status']) ?></span></td>
                                <td>
                                    <?php if ($p['payment_reference']): ?>
                                        <span class="ad-ref"><?= admin_h($p['payment_reference']) ?></span>
                                        <small><?= admin_h($p['account_hint'] ?? '') ?><?= $p['paid_at'] ? ' · ' . admin_h(date('M j, g:i A', strtotime($p['paid_at']))) : '' ?></small>
                                    <?php elseif ($p['confirmed_by']): ?>
                                        Cash received by <?= admin_h($p['confirmed_by']) ?>
                                        <small><?= admin_h(date('M j, Y · g:i A', strtotime($p['confirmed_at']))) ?></small>
                                    <?php else: ?>
                                        <span class="muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?>
                            <tr><td colspan="6">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <aside>
            <section class="panel">
                <h2>Passenger</h2>
                <dl>
                    <div><dt>Name</dt><dd><?= admin_h($first['name']) ?></dd></div>
                    <div><dt>Email</dt><dd><a href="mailto:<?= admin_h($first['email']) ?>"><?= admin_h($first['email']) ?></a></dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:<?= admin_h(preg_replace('/[^0-9+]/', '', $first['phone'])) ?>"><?= admin_h($first['phone']) ?></a></dd></div>
                    <div><dt>Age / gender</dt><dd><?= admin_h(($first['age'] ?? '') !== '' && $first['age'] !== null ? (string) $first['age'] : '—') ?> / <?= admin_h($first['gender'] ?: '—') ?></dd></div>
                </dl>
            </section>
            <section class="panel">
                <h2>Money</h2>
                <dl>
                    <div><dt>Booking total</dt><dd>₱<?= number_format($recorded / 100, 2) ?></dd></div>
                    <div><dt>Still due</dt><dd>₱<?= number_format($due / 100, 2) ?></dd></div>
                    <div><dt>Paid</dt><dd><strong>₱<?= number_format($paid / 100, 2) ?></strong></dd></div>
                </dl>
                <?php if ($status === 'CANCELLED'): ?>
                    <p class="notice">Cancelled. Paid amounts are payment history and don't confirm a refund was issued.</p>
                <?php endif; ?>
            </section>
        </aside>
    </div>
<?php endif; ?>
<?php admin_page_end(); ?>
