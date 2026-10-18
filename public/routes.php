<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date', 'passengers'], 'index.php');

$booking = $_SESSION['booking'];
$origin = $booking['origin'] ?? '';
$destination = $booking['destination'] ?? '';
$travelDate = $booking['travel_date'];
$passengers = (int) $booking['passengers'];
if ($passengers < 1 || $passengers > 40) { header('Location: index.php'); exit; }

$sort = $_GET['sort'] ?? 'departure';
if (!in_array($sort, ['departure', 'fare', 'duration'], true)) {
    $sort = 'departure';
}

$buses = sort_schedules(
    search_schedules($origin, $destination, $travelDate),
    $sort
);

// Validate that each result matches the requested route.
$buses = array_values(array_filter($buses, function ($bus) use ($origin, $destination) {
    return strcasecmp(trim((string) ($bus['origin'] ?? '')), trim($origin)) === 0
        && strcasecmp(trim((string) ($bus['destination'] ?? '')), trim($destination)) === 0;
}));

// Count occupied seats separately for each scheduled departure.
$seatStmt = $pdo->query("
    SELECT schedule_id, COUNT(DISTINCT seat_number) AS occupied
    FROM reservations
    WHERE status IN ('PENDING', 'PAID')
    GROUP BY schedule_id
");

$occupiedSeats = [];

foreach ($seatStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $occupiedSeats[(int) $row['schedule_id']] = (int) $row['occupied'];
}

$now = (new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Manila')
))->format('Y-m-d H:i:s');

$bookableSchedules = [];

foreach (get_schedules() as $schedule) {
    $departure = $schedule['travel_date']
        . ' ' . $schedule['departure_time'];

    // Do not recommend departures that have already left.
    if ($departure <= $now) {
        continue;
    }

    $scheduleId = (int) $schedule['schedule_id'];

    $schedule['available_seats'] = max(
        0,
        (int) $schedule['total_seats']
            - ($occupiedSeats[$scheduleId] ?? 0)
    );

    // Only show trips with enough seats for the requested group.
    if ($schedule['available_seats'] < $passengers) {
        continue;
    }

    $bookableSchedules[$scheduleId] = $schedule;
}

// Keep matching search results only when they are still bookable.
$matchingBuses = [];

foreach ($buses as $bus) {
    $scheduleId = (int) $bus['schedule_id'];

    if (isset($bookableSchedules[$scheduleId])) {
        $matchingBuses[] = $bookableSchedules[$scheduleId];
    }
}

$buses = $matchingBuses;
$alternatives = [];

if (!$buses) {
    $alternatives = array_values($bookableSchedules);

    // Prefer the requested date, then the requested origin.
    // Within those groups, show the earliest departures first.
    usort(
        $alternatives,
        function (array $a, array $b) use ($travelDate, $origin): int {
            $rankA = [
                $a['travel_date'] === $travelDate ? 0 : 1,
                strcasecmp(trim($a['origin']), trim($origin)) === 0 ? 0 : 1,
                $a['travel_date'],
                $a['departure_time'],
                (int) $a['schedule_id'],
            ];

            $rankB = [
                $b['travel_date'] === $travelDate ? 0 : 1,
                strcasecmp(trim($b['origin']), trim($origin)) === 0 ? 0 : 1,
                $b['travel_date'],
                $b['departure_time'],
                (int) $b['schedule_id'],
            ];

            return $rankA <=> $rankB;
        }
    );

    $alternatives = array_slice($alternatives, 0, 12);
}

$pageTitle = 'Available Buses';
$activeStep = 'routes';
$displayTrips = $buses ?: $alternatives;
$zone = new DateTimeZone('Asia/Manila');
require __DIR__ . '/../includes/header.php';
?>
<style>
.results-page{max-width:1120px;margin:36px auto 56px;padding:0 20px;color:#21190f}
.results-page *{box-sizing:border-box}
.results-page .rp-top{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;margin-bottom:24px}
.results-page h1{font-size:clamp(28px,4vw,40px);line-height:1.2;margin:8px 0 12px}
.results-page .rp-muted{color:#6b6259;font-size:14px;line-height:1.6}
.results-page .rp-label{color:#8c5905;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px}
.results-page .rp-actions{display:flex;gap:10px;flex-wrap:wrap}
.results-page .rp-link{display:inline-flex;justify-content:center;align-items:center;text-decoration:none;color:#704604;background:white;border:1px solid #d8ccba;border-radius:9px;padding:11px 16px;font-weight:600;font-size:14px}
.results-page .rp-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin:24px 0 16px}
.results-page .rp-sort{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:14px}
.results-page .rp-sort a{padding:8px 12px;background:#fff;border:1px solid #ded6ca;border-radius:8px;color:#554839;text-decoration:none}
.results-page .rp-sort a[aria-current="true"]{background:#fff0ce;border-color:#e79805;color:#704604;font-weight:700}
.results-page .rp-list{display:grid;gap:18px}
.results-page .rp-card{background:#fff;border:1px solid #e1d7c9;border-radius:18px;overflow:hidden}
.results-page .rp-main{display:grid;grid-template-columns:minmax(150px,1fr) minmax(310px,2fr) minmax(160px,1fr);gap:26px;align-items:center;padding:26px}
.results-page .rp-card h2{font-size:22px;margin:10px 0;overflow-wrap:anywhere}
.results-page .rp-type{display:inline-block;border-radius:6px;background:#fff3da;color:#805000;padding:5px 10px;font-size:12px;font-weight:700}
.results-page .rp-journey{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:start}
.results-page .rp-journey>div:first-child{grid-column:1;grid-row:1}
.results-page .rp-journey>div:last-child{grid-column:2;grid-row:1}
.results-page .rp-journey strong{display:block;font-size:24px;white-space:nowrap}
.results-page .rp-journey span{display:block;font-size:14px;margin:5px 0;line-height:1.5}
.results-page .rp-duration{grid-column:1/-1;grid-row:2;justify-self:center;margin-top:8px;padding:8px 16px;border:1px solid #edd7ad;border-radius:999px;background:#fff4dd;color:#704604;font-size:14px;font-weight:600;text-align:center}

.results-page .rp-price{text-align:right}
.results-page .rp-price strong{font-size:28px;display:block}
.results-page .rp-primary{display:block;width:100%;margin-top:12px;padding:13px 16px;background:#ed9700;border:1px solid #d28300;border-radius:9px;color:#21190f;font-size:14px;text-align:center;text-decoration:none;cursor:pointer;font-family:inherit;font-weight:700}
.results-page .rp-bottom{padding:16px 26px;background:#fdfbf7;border-top:1px solid #eee7dc;display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
.results-page .rp-amenities{display:flex;gap:7px;flex-wrap:wrap}
.results-page .rp-amenities span{font-size:12px;background:white;border:1px solid #e3dacd;border-radius:6px;padding:5px 9px}
.results-page .rp-seats{font-size:13px;color:#286044;background:#eaf5ed;border-radius:7px;padding:7px 10px}
.results-page .rp-notice{padding:24px;background:#fff4dc;border:1px solid #ecd5a4;border-radius:14px;line-height:1.6}
.results-page .rp-notice h2{font-size:22px;margin:0 0 8px}
.results-page .rp-notice p{margin:0}
.results-page .rp-footnote{margin-top:20px}
.results-page a:focus-visible,.results-page button:focus-visible{outline:3px solid #754500;outline-offset:3px}
@media(max-width:900px){.results-page .rp-main{grid-template-columns:1fr 2fr}.results-page .rp-price{grid-column:1/-1;text-align:left;display:flex;align-items:center;justify-content:space-between;gap:20px}.results-page .rp-price form{min-width:180px}.results-page .rp-price>a{width:auto;min-width:180px}.results-page .rp-primary{margin-top:0}}
@media(max-width:600px){.results-page{padding:0 16px;margin-top:24px}.results-page .rp-main{display:flex;flex-direction:column;align-items:stretch;padding:20px;gap:20px}.results-page .rp-journey{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.results-page .rp-journey>div:first-child{grid-column:1;grid-row:1}
.results-page .rp-journey>div:last-child{grid-column:2;grid-row:1}
.results-page .rp-journey strong{font-size:20px}.results-page .rp-price{display:block;border-top:1px solid #eee7dc;padding-top:16px}.results-page .rp-primary{margin-top:12px}.results-page .rp-price>a{width:100%}.results-page .rp-bottom{padding:16px 20px}}
.results-page .rp-journey > div:first-child,
.results-page .rp-journey > div:last-child {
    text-align: center;
    min-width: 0;
}
</style>
<section class="results-page" aria-labelledby="results-title">
  <div class="rp-top">
    <div>
      <div class="rp-label">Available buses</div>
      <h1 id="results-title"><?= h($origin) ?> → <?= h($destination) ?></h1>
      <div class="rp-muted"><?= h(date('l, F j, Y', strtotime($travelDate))) ?> · <?= $passengers ?> passenger<?= $passengers === 1 ? '' : 's' ?></div>
    </div>
    <div class="rp-actions">
      <a class="rp-link" href="index.php">← Modify search</a>
      <a class="rp-link" href="browse.php">Browse other dates</a>
    </div>
  </div>

  <?php if (!$buses): ?>
    <div class="rp-notice" role="status">
      <h2>No available buses at the moment</h2>
      <p>No upcoming departure has enough seats for your search.
      <?= $alternatives ? 'Here are other available buses you can book. These may have a different route or date—check the details before continuing.' : 'Try another route or browse other dates to plan your trip.' ?></p>
    </div>
  <?php endif; ?>

  <div class="rp-toolbar">
    <strong><?= count($displayTrips) ?> <?= $buses ? 'matching' : 'alternative' ?> trip<?= count($displayTrips) === 1 ? '' : 's' ?><?= !$buses && $alternatives ? ' (up to 12 shown)' : '' ?></strong>
    <?php if ($buses): ?>
      <nav class="rp-sort" aria-label="Sort trips">
        <span>Sort by</span>
        <?php foreach (['departure'=>'Departure', 'fare'=>'Lowest fare', 'duration'=>'Shortest trip'] as $key=>$label): ?>
          <a href="routes.php?sort=<?= $key ?>" aria-current="<?= $sort === $key ? 'true' : 'false' ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>

  <div class="rp-list">
  <?php foreach ($displayTrips as $bus):
      $departure = new DateTimeImmutable($bus['travel_date'].' '.$bus['departure_time'], $zone);
      $arrival = new DateTimeImmutable($bus['travel_date'].' '.$bus['arrival_time'], $zone);
      if ($arrival < $departure) { $arrival = $arrival->modify('+1 day'); }
      $minutes = (int) (($arrival->getTimestamp() - $departure->getTimestamp()) / 60);
      $hours = intdiv($minutes, 60);
      $remainingMinutes = $minutes % 60;
      $durationParts = [];
      if ($hours > 0) { $durationParts[] = $hours . ($hours === 1 ? ' hour' : ' hours'); }
      if ($remainingMinutes > 0 || $hours === 0) { $durationParts[] = $remainingMinutes . ($remainingMinutes === 1 ? ' minute' : ' minutes'); }
      $durationLabel = implode(' ', $durationParts);
      $amenities = array_filter(array_map('trim', explode(',', (string) $bus['amenities'])));
  ?>
    <article class="rp-card">
      <div class="rp-main">
        <div>
          <span class="rp-type"><?= h($bus['bus_type']) ?></span>
          <h2>Bus <?= h($bus['bus_number']) ?></h2>
          <div class="rp-muted">Boarding point<br><?= h($bus['boarding_point'] ?: $bus['origin']) ?></div>
        </div>
        <div class="rp-journey">
          <div><strong><?= h($departure->format('g:i A')) ?></strong><span><?= h($bus['origin']) ?></span><span class="rp-muted"><?= h($departure->format('D, M j, Y')) ?></span></div>
          <div class="rp-duration">Travel time: <?= h($durationLabel) ?></div>
          <div><strong><?= h($arrival->format('g:i A')) ?></strong><span><?= h($bus['destination']) ?></span><span class="rp-muted"><?= h($arrival->format('D, M j, Y')) ?></span></div>
        </div>
        <div class="rp-price">
          <div><strong>₱<?= number_format((float) $bus['fare_per_seat'], 2) ?></strong><span class="rp-muted">Base fare / seat</span></div>
          <?php if ($buses): ?>
            <a class="rp-primary" href="bus.php?schedule_id=<?= (int) $bus['schedule_id'] ?>">View bus →</a>
          <?php else: ?>
            <form method="post" action="index.php">
              <input type="hidden" name="origin" value="<?= h($bus['origin']) ?>">
              <input type="hidden" name="destination" value="<?= h($bus['destination']) ?>">
              <input type="hidden" name="travel_date" value="<?= h($bus['travel_date']) ?>">
              <input type="hidden" name="passengers" value="<?= $passengers ?>">
              <button class="rp-primary" type="submit">View this route →</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="rp-bottom">
        <div class="rp-amenities"><?php foreach ($amenities as $amenity): ?><span><?= h($amenity) ?></span><?php endforeach; ?></div>
        <span class="rp-seats"><?= (int) $bus['available_seats'] ?> of <?= (int) $bus['total_seats'] ?> seats available</span>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
  <p class="rp-muted rp-footnote">Times are in Philippine time. Service fees and tax are shown in your fare summary. Seat availability is checked again when you finish your reservation.</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>