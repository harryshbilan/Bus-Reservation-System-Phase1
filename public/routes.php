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

<?php foreach ($buses as $bus): 
$notEnough = $bus['available_seats'] < $passengers;
?>

<div class="bus-card">

    <div class="bus-header">

        <div>
            <div class="operator">
                <?= h($bus['bus_type']) ?>
            </div>

            <div class="bus-number">
                <?= h($bus['bus_number']) ?>
            </div>

            <span class="<?= badge_class($bus['bus_type']) ?>">
                <?= strtoupper($bus['bus_type']) ?>
            </span>

        </div>

        <div class="route-time">

            <div>
                <h2><?= h($bus['departure_time']) ?></h2>
                <small><?= h($bus['origin']) ?></small>
            </div>

            <div class="duration">
              <?= floor($bus['duration_minutes'] / 60) ?>h 
              <?= $bus['duration_minutes'] % 60 ?>m
            </div>

            <div>
                <h2><?= h($bus['arrival_time']) ?></h2>
                <small><?= h($bus['destination']) ?></small>
            </div>

        </div>

        <div class="fare-area">

            <div class="fare">
                <?= peso((int)$bus['fare']) ?>
            </div>

            <small>per seat</small>
            <a 
            class="select-btn <?= $notEnough ? 'disabled':'' ?>"
            href="<?= $notEnough ? '#' : 'bus.php?schedule_id='.(int)$bus['schedule_id'] ?>">
          
            <?= $notEnough ? 'FULL':'SELECT →' ?>
            </a>

        </div>

    </div>

    <div class="bus-footer">

        <div class="amenities">

        <?php foreach(explode(',', $bus['amenities']) as $a): ?>

            <span>
                <?= h($a) ?>
            </span>

        <?php endforeach; ?>
        </div>

        <div class="seats">
            <?= (int)$bus['available_seats'] ?> seats available
        </div>

    </div>
</div>

<?php endforeach; ?>
</div>
         
<a class="link-back" href="index.php">&larr; Modify search</a>

<?php require __DIR__ . '/../includes/footer.php'; ?>
