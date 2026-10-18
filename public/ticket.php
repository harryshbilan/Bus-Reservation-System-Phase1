<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['ticket_number'], 'index.php');
header('Cache-Control: no-store');
// Read only the reference held in this passenger's session.
$reference = $_SESSION['booking']['ticket_number'];
if (!is_string($reference)) { redirect('index.php'); }
$stmt = $pdo->prepare("
    SELECT r.reservation_id, r.seat_number, r.status, r.passenger_id, r.schedule_id,
           pax.name, pax.email, pax.phone, pax.age, pax.gender, b.bus_number, b.bus_type,
           rt.origin, rt.destination, rt.boarding_point,
           s.travel_date, s.departure_time, s.arrival_time
    FROM reservations r
    JOIN passengers pax ON pax.passenger_id = r.passenger_id
    JOIN schedules s ON s.schedule_id = r.schedule_id
    JOIN buses b ON b.bus_id = s.bus_id
    JOIN routes rt ON rt.route_id = s.route_id
    WHERE r.ticket_number = ?
    ORDER BY CAST(r.seat_number AS UNSIGNED), r.seat_number, r.reservation_id
");
$stmt->execute([$reference]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$reservations) { redirect('index.php'); }
$ticket = $reservations[0];
foreach ($reservations as $reservation) {
    if ($reservation['passenger_id'] != $ticket['passenger_id'] || $reservation['schedule_id'] != $ticket['schedule_id']) {
        http_response_code(409);
        exit('This booking needs staff review. Please contact the counter with your booking reference.');
    }
}
// Aggregate payments separately so payment rows cannot duplicate seat labels.
$stmt = $pdo->prepare("
    SELECT p.amount, p.status, p.payment_method
    FROM payments p JOIN reservations r ON r.reservation_id = p.reservation_id
    WHERE r.ticket_number = ?
");
$stmt->execute([$reference]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$recordedTotal = $amountPaid = $amountPending = 0.0;
$methods = [];
foreach ($payments as $payment) {
    $amount = (float) $payment['amount'];
    $recordedTotal += $amount;
    if ($payment['status'] === 'PAID') { $amountPaid += $amount; }
    if ($payment['status'] === 'PENDING') { $amountPending += $amount; }
    $methods[] = $payment['payment_method'];
}
$statuses = array_unique(array_column($reservations, 'status'));
$status = count($statuses) === 1 ? $ticket['status'] : 'REVIEW REQUIRED';
[$title, $message, $statusClass] = match ($status) {
    'PENDING' => ['Reservation pending payment', 'Please present this reference and pay at the counter. This reservation is not proof of payment.', 'pending'],
    'PAID' => ['Booking confirmed', 'Your booking is marked paid. Keep this ticket and present it when boarding.', 'paid'],
    'CANCELLED' => ['Reservation cancelled', 'This reservation is no longer active and cannot be used for boarding.', 'cancelled'],
    default => ['Booking needs review', 'Please contact the counter with your booking reference.', 'cancelled'],
};
$zone = new DateTimeZone('Asia/Manila');
$departure = new DateTimeImmutable($ticket['travel_date'] . ' ' . $ticket['departure_time'], $zone);
$arrival = new DateTimeImmutable($ticket['travel_date'] . ' ' . $ticket['arrival_time'], $zone);
if ($arrival < $departure) { $arrival = $arrival->modify('+1 day'); }
$seats = array_unique(array_column($reservations, 'seat_number'));
$pageTitle = 'Ticket';
$activeStep = 'ticket';
require __DIR__ . '/../includes/header.php';
?>
<style>
.booking-receipt { max-width:950px; margin:0 auto 48px; padding:32px; border:1px solid #ded6cc; border-top:5px solid #e8940a; border-radius:16px; background:white; color:#21180e; }
.booking-receipt h1 { font-size:30px; line-height:1.3; margin:16px 0; }
.booking-receipt h3 { font-size:20px; margin:0 0 18px; }
.booking-receipt p { line-height:1.6; }
.receipt-state { display:inline-block; padding:8px 14px; border-radius:20px; font-size:13px; font-weight:bold; }
.receipt-state.pending { background:#fff0c2; color:#704c00; }
.receipt-state.paid { background:#dff3e4; color:#1d6030; }
.receipt-state.cancelled { background:#ffe3df; color:#922d22; }
.receipt-reference { background:#f8f6f2; padding:18px; border-radius:10px; margin:24px 0; }
.receipt-reference span { display:block; font-size:13px; color:#62574a; margin-bottom:8px; }
.receipt-reference strong { font:700 19px monospace; overflow-wrap:anywhere; }
.receipt-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:28px; padding:24px 0; border-top:1px dashed #b7ac9d; }
.receipt-meta { margin:0; }
.receipt-meta > div { margin-bottom:16px; }
.receipt-meta dt { color:#62574a; font-size:13px; margin-bottom:5px; }
.receipt-meta dd { margin:0; overflow-wrap:anywhere; }
.receipt-seats { display:flex; flex-wrap:wrap; gap:8px; }
.receipt-seats span { padding:7px 11px; border:1px solid #e8bd75; background:#fff0d5; border-radius:6px; font-weight:bold; }
.receipt-payment { padding:22px; background:#fffaf0; border:1px solid #eadcc4; border-radius:10px; }
.receipt-payment dl > div { display:flex; justify-content:space-between; gap:18px; padding:10px 0; }
.receipt-payment dd { margin:0; font-weight:bold; text-align:right; }
.receipt-payment .receipt-due { border-top:1px solid #ded6cc; margin-top:8px; padding-top:16px; font-size:22px; font-weight:bold; }
.booking-receipt .receipt-note { font-size:13px; color:#62574a; line-height:1.7; }
.receipt-actions { display:flex; flex-wrap:wrap; align-items:center; gap:20px; margin-top:24px; }
.receipt-actions button { padding:14px 22px; background:#e8940a; border:0; border-radius:8px; color:#21180e; font:700 15px Arial,sans-serif; cursor:pointer; }
.receipt-actions a { color:#684000; }
.receipt-actions :focus-visible { outline:3px solid #684000; outline-offset:3px; }
@media(max-width:650px) { .receipt-grid { grid-template-columns:1fr; gap:14px; } .booking-receipt { padding:20px; } }
@media print {
    @page { margin:12mm; }
    .main-header, .booking-progress, .footer, .receipt-actions { display:none !important; }
    body, .page-container { background:white !important; margin:0 !important; padding:0 !important; }
    .booking-receipt { max-width:none; margin:0; padding:12px; border:1px solid #999; border-radius:0; font-size:11pt; box-shadow:none; }
    .booking-receipt h1 { font-size:22pt; }
    .receipt-grid { grid-template-columns:1fr 1fr; gap:20px; padding:16px 0; }
    .receipt-reference, .receipt-payment, .receipt-grid > section { break-inside:avoid; }
}
</style>
<article class="booking-receipt" aria-label="Booking receipt">
    <div class="eyebrow">BUSWAY · BOOKING RECEIPT</div>
    <h1><?= h($title) ?></h1>
    <span class="receipt-state <?= h($statusClass) ?>"><?= h($status) ?></span>
    <p><?= h($message) ?></p>
    <div class="receipt-reference"><span>BOOKING REFERENCE — KEEP THIS FOR THE COUNTER</span><strong><?= h($reference) ?></strong></div>
    <div class="receipt-grid">
        <section>
            <h3>Trip details</h3>
            <dl class="receipt-meta">
                <div><dt>Route</dt><dd><strong><?= h($ticket['origin']) ?> → <?= h($ticket['destination']) ?></strong></dd></div>
                <div><dt>Departure</dt><dd><?= h($departure->format('D, M j, Y · g:i A')) ?></dd></div>
                <div><dt>Scheduled arrival</dt><dd><?= h($arrival->format('D, M j, Y · g:i A')) ?></dd></div>
                <div><dt>Bus</dt><dd><?= h($ticket['bus_number']) ?> · <?= h($ticket['bus_type']) ?></dd></div>
                <div><dt>Boarding point</dt><dd><?= h($ticket['boarding_point']) ?></dd></div>
            </dl>
            </section>
        <section>
            <h3>Primary contact</h3>
            <dl class="receipt-meta">
                <div><dt>Full name</dt><dd><?= h($ticket['name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= h($ticket['email']) ?></dd></div>
                <div><dt>Phone</dt><dd><?= h($ticket['phone']) ?></dd></div>
                    <div><dt>Age on travel date</dt><dd><?= h((string) ($ticket['age'] ?? '') !== '' ? (string) $ticket['age'] : 'Not provided') ?></dd></div>
                    <div><dt>Gender</dt><dd><?= h(($ticket['gender'] ?? '') !== '' ? $ticket['gender'] : 'Not provided') ?></dd></div>
                <div><dt>Passengers</dt><dd><?= count($seats) ?></dd></div>
                <div><dt>Seats</dt><dd class="receipt-seats"><?php foreach ($seats as $seat): ?><span><?= h($seat) ?></span><?php endforeach; ?></dd></div>
            </dl>
        </section>
    </div>
    <section class="receipt-payment">
        <h3>Payment record</h3>
        <dl>
            <div><dt>Payment method</dt><dd><?= h($methods ? implode(', ', array_unique($methods)) : 'Not recorded') ?></dd></div>
            <div><dt>Recorded booking amount</dt><dd>₱<?= number_format($recordedTotal, 2) ?></dd></div>
            <div><dt>Amount recorded as paid</dt><dd>₱<?= number_format($amountPaid, 2) ?></dd></div>
            <?php if ($status === 'PENDING'): ?>
                <div class="receipt-due"><dt>Amount due</dt><dd>₱<?= number_format($amountPending, 2) ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if ($status === 'CANCELLED'): ?>
            <p class="receipt-note">Payment amounts show the saved payment history. This receipt does not confirm that a refund has been issued.</p>
        <?php endif; ?>
        <p class="receipt-note">These amounts come from saved payment records. Refresh this page after staff confirm your payment to see the updated status.</p>
    </section>
    <div class="receipt-actions">
        <button type="button" onclick="window.print()">Print <?= $status === 'PAID' ? 'ticket' : 'reservation' ?></button>
        <a href="index.php">Book another trip</a>
    </div>
</article>
<?php require __DIR__ . '/../includes/footer.php'; ?>