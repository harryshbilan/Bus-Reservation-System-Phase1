<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date'], 'index.php');

$booking = $_SESSION['booking'];
$origin = $booking['origin'] ?? '';
$destination = $booking['destination'] ?? '';
$travelDate = $booking['travel_date'];
$passengers = (int) $booking['passengers'];

$sort = $_GET['sort'] ?? 'departure';
if (!in_array($sort, ['departure', 'fare', 'duration'], true)) {
    $sort = 'departure';
}

$buses = sort_schedules(search_schedules($origin, $destination), $sort);
// Mirrors the original prototype: if the origin/destination filter matches nothing, show all buses instead.
if (count($buses) === 0) {
    $buses = sort_schedules(get_schedules(), $sort);
}
foreach ($buses as &$bus) {
    $bus['available_seats'] = available_seat_count((int) $bus['bus_id'], (int) $bus['total_seats'], $travelDate);
}
unset($bus);

$pageTitle = 'Available Buses';
$activeStep = 'routes';
require __DIR__ . '/../includes/header.php';
?>

<div class="bus-row-inner mb-6" style="align-items:flex-start;">
  <div>
    <div class="eyebrow">AVAILABLE BUSES</div>
    <h2 class="section-title">
      <?= h(strtoupper($origin)) ?>
      <?php if ($destination !== ''): ?><span class="accent" style="margin:0 12px;">&rarr;</span><?= h(strtoupper($destination)) ?><?php endif; ?>
    </h2>
    <div style="color:var(--muted);margin-top:6px;font-size:14px;">
      <?= h(date('l, F j', strtotime($travelDate))) ?> &middot; <?= $passengers ?> passenger<?= $passengers > 1 ? 's' : '' ?>
    </div>
  </div>
  <div class="sort-row">
    <span class="sort-label">SORT:</span>
    <?php foreach (['departure' => 'DEPARTURE', 'fare' => 'FARE', 'duration' => 'DURATION'] as $key => $label): ?>
      <a class="sort-btn <?= $sort === $key ? 'active' : '' ?>" href="routes.php?sort=<?= $key ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (count($buses) === 0): ?>
  <div class="empty-box">
    <div class="empty-title">NO BUSES FOUND</div>
    <div class="empty-hint">Try: Manila, Cubao, PITX, or Cebu City as origin.</div>
  </div>
<?php endif; ?>

<div class="bus-list">
  <?php foreach ($buses as $bus): $notEnough = $bus['available_seats'] < $passengers; ?>
    <?php if ($notEnough): ?>
      <div class="bus-row full">
    <?php else: ?>
      <a class="bus-row" href="bus.php?schedule_id=<?= (int) $bus['schedule_id'] ?>">
    <?php endif; ?>
      <div class="bus-row-inner">
        <div class="bus-cluster">
          <div>
            <div class="bus-num-label">BUS NO.</div>
            <div class="bus-num"><?= h($bus['bus_number']) ?></div>
            <span class="<?= badge_class($bus['bus_type']) ?>"><?= strtoupper($bus['bus_type']) ?></span>
          </div>
          <div>
            <div class="bus-time"><?= h($bus['departure_time']) ?></div>
            <div class="bus-place"><?= h($bus['origin']) ?></div>
          </div>
          <div class="bus-duration">
            <div class="line">&mdash;&mdash;&mdash;&mdash; <?= format_duration((int) $bus['duration_minutes']) ?> &mdash;&mdash;&mdash;&mdash;</div>
            <div class="arrow">&rarr;</div>
          </div>
          <div>
            <div class="bus-time"><?= h($bus['arrival_time']) ?></div>
            <div class="bus-place"><?= h($bus['destination']) ?></div>
          </div>
        </div>
        <div class="flex-wrap gap-6" style="align-items:center;">
          <div>
            <div class="flex-wrap gap-1 mb-4">
              <?php foreach (explode(',', $bus['amenities']) as $a): ?>
                <span class="amenity sm"><?= h($a) ?></span>
              <?php endforeach; ?>
            </div>
            <div class="seats-left <?= ($notEnough || $bus['available_seats'] < 10) ? 'low' : '' ?>">
              <?= (int) $bus['available_seats'] ?> seats available<?= $notEnough ? ' — need ' . $passengers : '' ?>
            </div>
          </div>
          <div class="text-right">
            <div class="bus-fare"><?= peso($bus['fare_per_seat']) ?></div>
            <div class="bus-fare-unit">per seat</div>
          </div>
          <div class="bus-select-pill <?= $notEnough ? 'full' : '' ?>"><?= $notEnough ? 'FULL' : 'SELECT &rarr;' ?></div>
        </div>
      </div>
    <?= $notEnough ? '</div>' : '</a>' ?>
  <?php endforeach; ?>
</div>

<a class="link-back" href="index.php">&larr; Modify search</a>

<?php require __DIR__ . '/../includes/footer.php'; ?>
