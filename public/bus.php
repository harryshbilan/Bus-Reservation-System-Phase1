<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date'], 'index.php');

$scheduleId = (int) ($_GET['schedule_id'] ?? $_SESSION['booking']['schedule_id'] ?? 0);
$bus = get_schedule($scheduleId);
if (!$bus) {
    redirect('routes.php');
}

$travelDate = $_SESSION['booking']['travel_date'];
$passengers = (int) $_SESSION['booking']['passengers'];
$availableSeats = available_seat_count((int) $bus['bus_id'], (int) $bus['total_seats'], $travelDate);
if ($availableSeats < $passengers) {
    redirect('routes.php');
}

if (($_SESSION['booking']['schedule_id'] ?? null) !== $scheduleId) {
    unset($_SESSION['booking']['selected_seats']);
}
$_SESSION['booking']['schedule_id'] = $scheduleId;

$pageTitle = 'Bus Selection';
$activeStep = 'bus';
require __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl">
  <div class="section-head">
    <div class="eyebrow">BUS SELECTION</div>
    <h2 class="section-title">BUS <?= h($bus['bus_number']) ?> SELECTED</h2>
  </div>

  <div class="card accent-border mb-6">
    <div class="bus-row-inner mb-6" style="align-items:flex-start;">
      <div>
        <div style="font-family:var(--font-display);font-size:52px;font-weight:800;color:var(--text);line-height:1;">
          Bus <?= h($bus['bus_number']) ?>
        </div>
        <span class="<?= badge_class($bus['bus_type']) ?>"><?= strtoupper($bus['bus_type']) ?></span>
      </div>
      <div style="font-family:var(--font-display);font-size:44px;font-weight:800;color:var(--accent);line-height:1;text-align:right;">
        <?= peso($bus['fare_per_seat']) ?>
        <div style="font-size:13px;font-weight:400;color:var(--muted);">per seat</div>
      </div>
    </div>
    <div style="border-top:1px solid var(--border);padding-top:20px;">
      <div class="info-grid">
        <?php
        $rows = [
            ['Route', $bus['origin'] . ' → ' . $bus['destination']],
            ['Departure', $bus['departure_time']],
            ['Arrival', $bus['arrival_time']],
            ['Duration', format_duration((int) $bus['duration_minutes'])],
            ['Total Seats', (string) $bus['total_seats']],
            ['Available Seats', (string) $availableSeats],
            ['Travel Date', date('M j, Y', strtotime($travelDate))],
            ['Boarding Point', $bus['boarding_point']],
        ];
        foreach ($rows as [$k, $v]):
        ?>
          <div class="info-row"><span class="k"><?= h($k) ?></span><span class="v"><?= h($v) ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="card tight mb-6">
    <div class="flex-wrap gap-2">
      <?php foreach (explode(',', $bus['amenities']) as $a): ?>
        <span class="amenity"><?= h($a) ?></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="btn-row">

    <a class="btn-ghost" href="routes.php">
        &larr; BACK
    </a>

    <a class="btn-primary" href="seat.php">
        CONTINUE TO SEAT SELECTION →
    </a>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
