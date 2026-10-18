<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
$admin = require_admin();
$reference = is_string($_GET['ticket'] ?? null) ? trim($_GET['ticket']) : '';
$error = null;
$rows = [];
$payments = [];
try {
    $q = $pdo->prepare("SELECT r.reservation_id,r.seat_number,r.status,pax.name,pax.email,pax.phone,
      b.bus_number,rt.origin,rt.destination,rt.boarding_point,s.travel_date,s.departure_time,s.arrival_time
      FROM reservations r JOIN passengers pax ON pax.passenger_id=r.passenger_id
      JOIN schedules s ON s.schedule_id=r.schedule_id JOIN buses b ON b.bus_id=s.bus_id
      JOIN routes rt ON rt.route_id=s.route_id WHERE r.ticket_number=?
      ORDER BY r.schedule_id,CAST(r.seat_number AS UNSIGNED),r.seat_number");
    $q->execute([$reference]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        http_response_code(404);
        $error = 'Ticket not found.';
    } else {
        $q = $pdo->prepare("SELECT p.payment_id,r.seat_number,p.amount,p.payment_method,p.status
          FROM payments p JOIN reservations r ON r.reservation_id=p.reservation_id WHERE r.ticket_number=? ORDER BY p.payment_id");
        $q->execute([$reference]);
        $payments = $q->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Ticket details could not be loaded.';
}
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
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Ticket details | BUSWAY Admin</title>
        <link rel="stylesheet" href="../assets/css/monitor.css" />
    </head>
    <body>
        <header>
            <strong>BUSWAY Admin</strong>
            <a href="tickets.php">← All tickets</a>
        </header>
        <main>
            <?php if($error): ?>
            <p class="error" role="alert"><?= admin_h($error) ?></p>
            <?php else: ?>
            <h1>Ticket details</h1>
            <p style="overflow-wrap: anywhere"><?= admin_h($reference) ?></p>
            <span class="status <?= admin_h($status) ?>"><?= admin_h($status) ?></span>
            <div class="panel">
                <h2>Payment summary</h2>
                <dl>
                    <div>
                        <dt>Original recorded value</dt>
                        <dd>₱<?= number_format($recorded/100,2) ?></dd>
                    </div>
                    <div>
                        <dt>Pending payment</dt>
                        <dd>₱<?= number_format($due/100,2) ?></dd>
                    </div>
                    <div>
                        <dt>Recorded paid</dt>
                        <dd>₱<?= number_format($paid/100,2) ?></dd>
                    </div>
                </dl>
                <?php if($status==='CANCELLED'): ?>
                <p class="notice">
                    This ticket is cancelled. Recorded paid amounts are payment history; they do not
                    confirm that a refund was issued.
                </p>
                <?php endif; ?>
                <?php if($status==='PENDING'): ?>
                <a class="button no-print" href="confirm_payment.php?ticket=<?= rawurlencode($reference) ?>">
                    Review cash payment
                </a>
                <?php endif; ?>
            </div>
            <h2>Passenger & seat records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Passenger</th>
                            <th>Trip / terminal</th>
                            <th>Departure / arrival</th>
                            <th>Seat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rows as $r):
$departure=new DateTimeImmutable($r['travel_date'].' '.$r['departure_time'],new DateTimeZone('Asia/Manila'));
$arrival=new DateTimeImmutable($r['travel_date'].' '.$r['arrival_time'],new DateTimeZone('Asia/Manila'));
if($arrival<$departure)$arrival=$arrival->modify('+1 day');
?>
                        <tr>
                            <td>
                                <?= admin_h($r['name']) ?>
                                <small>
                                    <?= admin_h($r['email']) ?>
                                    <br />
                                    <?= admin_h($r['phone']) ?>
                                </small>
                            </td>
                            <td>
                                <?= admin_h($r['origin'].' → '.$r['destination']) ?>
                                <small>
                                    Bus <?= admin_h($r['bus_number']) ?>
                                    <br />
                                    <?= admin_h($r['boarding_point']) ?>
                                </small>
                            </td>
                            <td>
                                <?= admin_h($departure->format('M j, Y · g:i A')) ?>
                                <small>Arrival: <?= admin_h($arrival->format('M j, Y · g:i A')) ?></small>
                            </td>
                            <td><?= admin_h($r['seat_number']) ?></td>
                            <td><?= admin_h($r['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <h2>Payment records</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Seat</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): ?>
                        <tr>
                            <td><?= (int)$p['payment_id'] ?></td>
                            <td><?= admin_h($p['seat_number']) ?></td>
                            <td><?= admin_h($p['payment_method']) ?></td>
                            <td>₱<?= number_format((float)$p['amount'],2) ?></td>
                            <td><?= admin_h($p['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$payments): ?>
                        <tr><td colspan="5">No payment records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </body>
</html>
