<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date', 'schedule_id', 'selected_seats', 'passengers'], 'index.php');

if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
}
$booking = $_SESSION['booking'];
$bus = get_schedule((int) $booking['schedule_id']);

if (!$bus || $bus['travel_date'] !== $booking['travel_date']) {
    redirect('routes.php');
}
$selectedSeats = $booking['selected_seats'];

if (!is_array($selectedSeats) || !$selectedSeats
    || count($selectedSeats) !== (int) $booking['passengers']
    || count(array_filter($selectedSeats, 'is_string')) !== count($selectedSeats)) {
    redirect('seat.php');
}
$passenger = $booking['passenger'] ?? [];

foreach (['name', 'email', 'phone'] as $field) {
    if (!is_array($passenger) || !is_string($passenger[$field] ?? null)
        || trim($passenger[$field]) === '') {
        redirect('passenger.php');
    }
}
$seatCount = count($selectedSeats);
$farePerSeat = (float) $bus['fare_per_seat'];
$extraBags = booking_bags($booking);
[$baseFare, $serviceFee, $tax, $total, $bagFee] = fare_breakdown($farePerSeat, $seatCount, $extraBags);
$zone = new DateTimeZone('Asia/Manila');
$departure = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['departure_time'], $zone);
$arrival = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['arrival_time'], $zone);

if ($arrival < $departure) {
    $arrival = $arrival->modify('+1 day');
}
$pageTitle = 'Fare Summary';
$activeStep = 'fare';
$pageStyles = ['fare.css'];
require __DIR__ . '/../includes/header.php';
?>
<section class="st-page st-narrow">
  <div class="st-head">
    <span class="bw-kicker">Fare summary</span>
    <h1 class="bw-display">Your fare</h1>
    <p>Have one last look. Nothing is charged until the next step.</p>
  </div>

  <div class="st-card fr-card">
    <div class="fr-trip">
      <div class="bw-display fr-route"><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></div>
      <div class="fr-meta">Bus <?= h($bus['bus_number']) ?> · <?= h($departure->format('g:i A')) ?> · <?= h($departure->format('D, M j, Y')) ?></div>
    </div>
    <div class="fr-body">
      <div class="fr-seats">
        <span>Seat<?= $seatCount === 1 ? '' : 's' ?>: <?= h(implode(', ', $selectedSeats)) ?></span>
        <a href="seat.php">Change</a>
      </div>
      <dl class="fr-lines">
        <div class="main"><dt>Base fare (<?= $seatCount ?> × ₱<?= number_format($farePerSeat, 0) ?>)</dt><dd>₱<?= number_format($baseFare, 2) ?></dd></div>
        <div><dt>Service fee (5%)</dt><dd>₱<?= number_format($serviceFee, 2) ?></dd></div>
        <div><dt>Tax (8%)</dt><dd>₱<?= number_format($tax, 2) ?></dd></div>
        <?php if ($extraBags): ?><div><dt>Extra checked bags (<?= $extraBags ?> × ₱<?= BAG_FEE ?>) <a href="passenger.php">Change</a></dt><dd>₱<?= number_format($bagFee, 2) ?></dd></div><?php endif; ?>
      </dl>
      <div class="fr-total">
        <span>Total</span>
        <strong class="bw-display">₱<?= number_format($total, 2) ?></strong>
      </div>
    </div>
  </div>

  <div class="st-card">
    <h2 class="st-card-title">Trip details</h2>
    <dl class="st-rows">
      <div><dt>Departure</dt><dd><?= h($departure->format('g:i A')) ?></dd></div>
      <div><dt>Arrival</dt><dd><?= h($arrival->format('g:i A')) ?><?= $arrival->format('Y-m-d') !== $departure->format('Y-m-d') ? ' (next day)' : '' ?></dd></div>
      <div><dt>Bus type</dt><dd><?= h($bus['bus_type']) ?></dd></div>
      <div><dt>Passengers</dt><dd><?= $seatCount ?></dd></div>
      <div class="fr-wide"><dt>Boarding point</dt><dd><?= h($bus['boarding_point']) ?></dd></div>
    </dl>
  </div>

  <div class="st-card">
    <h2 class="st-card-title">Primary contact <a href="passenger.php">Edit</a></h2>
    <dl class="st-rows">
      <div><dt>Name</dt><dd><?= h($passenger['name']) ?></dd></div>
      <div><dt>Phone</dt><dd><?= h($passenger['phone']) ?></dd></div>
      <div class="fr-wide"><dt>Email</dt><dd><?= h($passenger['email']) ?></dd></div>
      <div><dt>Age</dt><dd><?= h((string) ($passenger['age'] ?? '') !== '' ? (string) $passenger['age'] : '—') ?></dd></div>
      <div><dt>Gender</dt><dd><?= h(($passenger['gender'] ?? '') !== '' ? $passenger['gender'] : '—') ?></dd></div>
    </dl>
    <?php if ($seatCount > 1): ?><p class="st-note">This person is the contact for all <?= $seatCount ?> seats.</p><?php endif; ?>
  </div>

  <div class="st-actions">
    <a class="bw-btn-ghost" href="passenger.php">← Back</a>
    <a class="bw-btn" href="payment.php">Confirm →</a>
  </div>
  <p class="st-note">Fees and tax are worked out from the base fare and rounded to the nearest peso.</p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
