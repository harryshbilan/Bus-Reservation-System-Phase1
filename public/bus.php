<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date', 'passengers'], 'index.php');

if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
}
$scheduleId = filter_var($_GET['schedule_id'] ?? $_SESSION['booking']['schedule_id'] ?? null, FILTER_VALIDATE_INT);
$bus = $scheduleId ? get_schedule($scheduleId) : null;
$travelDate = $_SESSION['booking']['travel_date'];
$passengers = (int) $_SESSION['booking']['passengers'];

if (!$bus || $bus['travel_date'] !== $travelDate || $passengers < 1 || $passengers > 40) {
    redirect('routes.php');
}
$zone = new DateTimeZone('Asia/Manila');
$departure = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['departure_time'], $zone);
$arrival = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['arrival_time'], $zone);

if ($arrival < $departure) {
    $arrival = $arrival->modify('+1 day');
}

if ($departure <= new DateTimeImmutable('now', $zone)) {
    redirect('routes.php');
}
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT seat_number) FROM reservations WHERE schedule_id = ? AND status IN ('PENDING', 'PAID')");
$stmt->execute([$scheduleId]);
$availableSeats = max(0, (int) $bus['total_seats'] - (int) $stmt->fetchColumn());

if ($availableSeats < $passengers) {
    redirect('routes.php');
}

if ((int) ($_SESSION['booking']['schedule_id'] ?? 0) !== $scheduleId) {
    unset($_SESSION['booking']['selected_seats']);
}
$_SESSION['booking']['schedule_id'] = $scheduleId;
$_SESSION['booking']['origin'] = $bus['origin'];
$_SESSION['booking']['destination'] = $bus['destination'];
[$base, $fee, $tax, $total] = fare_breakdown((float) $bus['fare_per_seat'], $passengers);
$amenities = array_filter(array_map('trim', explode(',', $bus['amenities'])));
$pageTitle = 'Bus Selection';
$activeStep = 'bus';
$pageStyles = ['bus.css'];
require __DIR__ . '/../includes/header.php';
?>
<?php $typeClass = strtolower((string) $bus['bus_type']); ?>
<section class="st-page st-narrow">
  <div class="st-head">
    <span class="bw-kicker">Bus selection<?= $bus['operator'] ? ' · ' . h($bus['operator']) : '' ?></span>
    <h1 class="bw-display">Bus <?= h($bus['bus_number']) ?> selected</h1>
    <p>Check the times and where to board, then pick your seats.</p>
  </div>

  <div class="st-card accent">
    <div class="bs-top">
      <div>
        <div class="bs-num bw-display">Bus <?= h($bus['bus_number']) ?></div>
        <span class="st-badge <?= in_array($typeClass, ['express', 'premium'], true) ? $typeClass : '' ?>"><?= h($bus['bus_type']) ?></span>
      </div>
      <div class="bs-fare">
        <strong class="bw-display">₱<?= number_format((float) $bus['fare_per_seat'], 0) ?></strong>
        <span>per seat</span>
      </div>
    </div>
    <dl class="st-rows">
      <?php if ($bus['operator']): ?><div><dt>Operator</dt><dd><?= h($bus['operator']) ?></dd></div><?php endif; ?>
      <div><dt>Route</dt><dd><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></dd></div>
      <div><dt>Travel date</dt><dd><?= h($departure->format('M j, Y')) ?></dd></div>
      <div><dt>Departure</dt><dd><?= h($departure->format('g:i A')) ?></dd></div>
      <div><dt>Arrival</dt><dd><?= h($arrival->format('g:i A')) ?><?= $arrival->format('Y-m-d') !== $departure->format('Y-m-d') ? ' <small>(next day)</small>' : '' ?></dd></div>
      <div><dt>Duration</dt><dd><?= h(format_duration((int) $bus['duration_minutes'])) ?></dd></div>
      <div><dt>Seats left</dt><dd><?= $availableSeats ?> of <?= (int) $bus['total_seats'] ?></dd></div>
      <div class="bs-wide"><dt>Boarding point</dt><dd><?= h($bus['boarding_point'] ?: $bus['origin']) ?></dd></div>
    </dl>
  </div>

  <div class="st-card">
    <h2 class="st-card-title">On board</h2>
    <div class="st-chips">
      <?php foreach ($amenities as $amenity): ?><span><?= h($amenity) ?></span><?php endforeach; ?>
      <?php if (!$amenities): ?><span>No amenities listed</span><?php endif; ?>
    </div>
  </div>

  <div class="st-card bs-estimate">
    <span>Estimated total for <?= $passengers ?> passenger<?= $passengers === 1 ? '' : 's' ?>, fees and tax included</span>
    <strong class="bw-display">₱<?= number_format($total, 2) ?></strong>
  </div>

  <div class="st-actions">
    <a class="bw-btn-ghost" href="routes.php">← Back</a>
    <a class="bw-btn" href="seat.php">Choose your seats →</a>
  </div>
  <p class="st-note">Seats are checked again when you reserve, so pick them soon.</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
