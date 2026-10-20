<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['ticket_number'], 'index.php');
header('Cache-Control: no-store');
// Read only the reference held in this passenger's session.
$reference = $_SESSION['booking']['ticket_number'];

if (!is_string($reference)) {
    redirect('index.php');
}
$stmt = $pdo->prepare("
    SELECT r.reservation_id, r.seat_number, r.status, r.passenger_id, r.schedule_id,
           pax.name, pax.email, pax.phone, pax.age, pax.gender, b.bus_number, b.bus_type, o.operator_name,
           rt.origin, rt.destination, rt.boarding_point,
           s.travel_date, s.departure_time, s.arrival_time
    FROM reservations r
    JOIN passengers pax ON pax.passenger_id = r.passenger_id
    JOIN schedules s ON s.schedule_id = r.schedule_id
    JOIN buses b ON b.bus_id = s.bus_id
    LEFT JOIN bus_operators o ON o.operator_id = b.operator_id
    JOIN routes rt ON rt.route_id = s.route_id
    WHERE r.ticket_number = ?
    ORDER BY CAST(r.seat_number AS UNSIGNED), r.seat_number, r.reservation_id
");
$stmt->execute([$reference]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$reservations) {
    redirect('index.php');
}
$ticket = $reservations[0];

foreach ($reservations as $reservation) {
    if ($reservation['passenger_id'] != $ticket['passenger_id'] || $reservation['schedule_id'] != $ticket['schedule_id']) {
        http_response_code(409);
        exit('This booking needs staff review. Please drop by the counter with your booking reference and we\'ll sort it out.');
    }
}
// Aggregate payments separately so payment rows cannot duplicate seat labels.
$stmt = $pdo->prepare("
    SELECT p.amount, p.status, p.payment_method, p.payment_reference, p.account_hint
    FROM payments p JOIN reservations r ON r.reservation_id = p.reservation_id
    WHERE r.ticket_number = ?
");
$stmt->execute([$reference]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$recordedTotal = $amountPaid = $amountPending = 0.0;
$methods = [];
$gatewayRef = $accountHint = null;

foreach ($payments as $payment) {
    $amount = (float) $payment['amount'];
    $recordedTotal += $amount;

    if ($payment['status'] === 'PAID') {
        $amountPaid += $amount;
    }

    if ($payment['status'] === 'PENDING') {
        $amountPending += $amount;
    }
    $methods[] = $payment['payment_method'];
    $gatewayRef ??= $payment['payment_reference'];
    $accountHint ??= $payment['account_hint'];
}
$stmt = $pdo->prepare('SELECT extra_bags, bag_fee FROM booking_extras WHERE ticket_number = ?');
$stmt->execute([$reference]);
$extras = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['extra_bags' => 0, 'bag_fee' => 0];
$statuses = array_unique(array_column($reservations, 'status'));
$status = count($statuses) === 1 ? $ticket['status'] : 'REVIEW REQUIRED';
[$title, $message, $statusClass] = match ($status) {
    'PENDING' => ['Your seats are held, payment still to go', 'Bring this reference to the counter and pay there to lock in your trip. Until then, this isn\'t a paid ticket.', 'pending'],
    'PAID' => ['You\'re all set!', 'Payment received. Keep this ticket handy and show it when you board.', 'paid'],
    'CANCELLED' => ['This reservation was cancelled', 'It can\'t be used for boarding anymore.', 'cancelled'],
    default => ['Booking needs review', 'Please drop by the counter with your booking reference and we\'ll sort it out.', 'cancelled'],
};
$zone = new DateTimeZone('Asia/Manila');
$departure = new DateTimeImmutable($ticket['travel_date'] . ' ' . $ticket['departure_time'], $zone);
$arrival = new DateTimeImmutable($ticket['travel_date'] . ' ' . $ticket['arrival_time'], $zone);

if ($arrival < $departure) {
    $arrival = $arrival->modify('+1 day');
}
$seats = array_unique(array_column($reservations, 'seat_number'));
$pageTitle = 'Ticket';
$pageStyles = ['checkout.css'];
$activeStep = 'ticket';
require __DIR__ . '/../includes/header.php';
?>
<?php $nextDay = $arrival->format('Y-m-d') !== $departure->format('Y-m-d'); ?>
<section class="st-page st-narrow tk-page">
  <div class="tk-banner <?= h($statusClass) ?>">
    <span class="tk-icon" aria-hidden="true"><?= $statusClass === 'paid' ? '✓' : ($statusClass === 'pending' ? '!' : '✕') ?></span>
    <span class="tk-state"><?= h($status === 'PAID' ? 'Booking confirmed' : ($status === 'PENDING' ? 'Payment pending' : $status)) ?></span>
    <h1 class="bw-display"><?= h($title) ?></h1>
    <p><?= h($message) ?></p>
  </div>

  <article class="tk-ticket" aria-label="Booking receipt">
    <header class="tk-strip">
      <span>BUSWAY ticket</span>
      <span>Ref <?= h($reference) ?></span>
    </header>
    <div class="tk-body">
      <div class="tk-journey">
        <div>
          <span class="tk-label">From</span>
          <strong class="bw-display"><?= h($ticket['origin']) ?></strong>
          <em class="bw-display"><?= h($departure->format('g:i A')) ?></em>
        </div>
        <div class="tk-mid" aria-hidden="true">→<small><?= h(format_duration((int) (($arrival->getTimestamp() - $departure->getTimestamp()) / 60))) ?></small></div>
        <div>
          <span class="tk-label">To</span>
          <strong class="bw-display"><?= h($ticket['destination']) ?></strong>
          <em class="bw-display"><?= h($arrival->format('g:i A')) ?><?= $nextDay ? ' +1' : '' ?></em>
        </div>
      </div>

      <dl class="tk-grid">
        <div><dt>Travel date</dt><dd><?= h($departure->format('D, M j, Y')) ?></dd></div>
        <div><dt>Bus</dt><dd><?= h(trim(($ticket['operator_name'] ?? '') . ' ' . $ticket['bus_number'])) ?> · <?= h($ticket['bus_type']) ?></dd></div>
        <div><dt>Seat<?= count($seats) === 1 ? '' : 's' ?></dt><dd><?= h(implode(', ', $seats)) ?></dd></div>
        <div><dt>Passenger</dt><dd><?= h($ticket['name']) ?></dd></div>
        <div><dt>Contact</dt><dd><?= h($ticket['phone']) ?></dd></div>
        <div><dt>Age / gender</dt><dd><?= h((string) ($ticket['age'] ?? '') !== '' ? (string) $ticket['age'] : '—') ?> / <?= h(($ticket['gender'] ?? '') !== '' ? $ticket['gender'] : '—') ?></dd></div>
        <div><dt>Extra bags</dt><dd><?= (int) $extras['extra_bags'] ? (int) $extras['extra_bags'] . ' × 20 kg (₱' . number_format((float) $extras['bag_fee'], 2) . ')' : 'None' ?></dd></div>
        <div class="tk-wide"><dt>Boarding point</dt><dd><?= h($ticket['boarding_point']) ?></dd></div>
        <div class="tk-wide"><dt>Email</dt><dd><?= h($ticket['email']) ?></dd></div>
      </dl>

      <div class="tk-ref">
        <span class="tk-label">Booking reference: show this at the counter or gate</span>
        <strong><?= h($reference) ?></strong>
      </div>

      <div class="tk-pay">
        <span class="tk-label">Payment · <?= h($status) ?></span>
        <dl>
          <div><dt>Method</dt><dd><?= h($methods ? implode(', ', array_unique($methods)) : 'Not recorded') ?></dd></div>
          <?php if ($accountHint): ?><div><dt>Paid with</dt><dd><?= h($accountHint) ?></dd></div><?php endif; ?>
          <?php if ($gatewayRef): ?><div><dt>Payment ref.</dt><dd><?= h($gatewayRef) ?></dd></div><?php endif; ?>
          <div><dt>Booking total</dt><dd>₱<?= number_format($recordedTotal, 2) ?></dd></div>
          <div><dt>Paid so far</dt><dd class="hl">₱<?= number_format($amountPaid, 2) ?></dd></div>
          <?php if ($status === 'PENDING'): ?><div><dt>Amount due</dt><dd class="due">₱<?= number_format($amountPending, 2) ?></dd></div><?php endif; ?>
        </dl>
        <?php if ($status === 'CANCELLED'): ?>
          <p class="st-note">These figures are your payment history. A refund, if any, isn't shown here.</p>
        <?php elseif ($status === 'PENDING'): ?>
          <p class="st-note">Paid at the counter? Refresh this page once staff confirm it and the status will update.</p>
        <?php endif; ?>
      </div>
    </div>
  </article>

  <div class="tk-actions">
    <button type="button" class="bw-btn" onclick="window.print()">Print <?= $status === 'PAID' ? 'e-ticket' : 'reservation' ?></button>
    <a class="bw-btn-ghost" href="book.php">Book another trip</a>
    <?php if ($status === 'PAID' || $status === 'PENDING'): ?>
      <a class="bw-btn-ghost tk-cancel" href="cancellation.php">Cancel this booking</a>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
