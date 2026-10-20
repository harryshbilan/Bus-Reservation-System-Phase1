<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date', 'passengers'], 'book.php');

$booking = $_SESSION['booking'];
$origin = $booking['origin'] ?? '';
$destination = $booking['destination'] ?? '';
$travelDate = $booking['travel_date'];
$passengers = (int) $booking['passengers'];

if ($passengers < 1 || $passengers > 40) {
    header('Location: book.php');
    exit;
}

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

$amenityList = static fn (array $bus): array => array_values(array_filter(array_map('trim', explode(',', (string) $bus['amenities']))));

// Filter options come from the buses actually on this route.
$typeOptions = array_values(array_unique(array_column($buses, 'bus_type')));
sort($typeOptions);
$amenityOptions = $buses ? array_values(array_unique(array_merge(...array_map($amenityList, $buses)))) : [];
sort($amenityOptions);
$fares = array_map('floatval', array_column($buses, 'fare_per_seat'));
$fareFloor = $fares ? (int) floor(min($fares)) : 0;
$fareCeiling = $fares ? (int) ceil(max($fares)) : 0;

$filterType = is_string($_GET['type'] ?? null) && in_array($_GET['type'], $typeOptions, true) ? $_GET['type'] : '';
$filterAmenity = is_string($_GET['amenity'] ?? null) && in_array($_GET['amenity'], $amenityOptions, true) ? $_GET['amenity'] : '';
$filterMaxFare = filter_var($_GET['max_fare'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => $fareFloor, 'max_range' => $fareCeiling]]) ?: $fareCeiling;
$filtersOn = $filterType !== '' || $filterAmenity !== '' || $filterMaxFare < $fareCeiling;

$routeTrips = array_values(array_filter($buses, function (array $bus) use ($filterType, $filterAmenity, $filterMaxFare, $amenityList): bool {
    return ($filterType === '' || $bus['bus_type'] === $filterType)
        && ($filterAmenity === '' || in_array($filterAmenity, $amenityList($bus), true))
        && (float) $bus['fare_per_seat'] <= $filterMaxFare;
}));

$sortLink = static function (string $key) use ($filterType, $filterAmenity, $filterMaxFare, $fareCeiling): string {
    $query = ['sort' => $key];

    if ($filterType !== '') {
        $query['type'] = $filterType;
    }

    if ($filterAmenity !== '') {
        $query['amenity'] = $filterAmenity;
    }

    if ($filterMaxFare < $fareCeiling) {
        $query['max_fare'] = $filterMaxFare;
    }

    return 'routes.php?' . http_build_query($query);
};

$pageTitle = 'Available Buses';
$activeStep = 'routes';
$displayTrips = $buses ? $routeTrips : $alternatives;
$zone = new DateTimeZone('Asia/Manila');
$pageStyles = ['routes.css'];
require __DIR__ . '/../includes/header.php';
?>
<section class="rt-page" aria-labelledby="results-title">
  <div class="rt-top">
    <div>
      <span class="bw-kicker">Available buses</span>
      <h1 id="results-title" class="bw-display"><?= h($origin) ?> <span class="rt-arrow" aria-label="to">→</span> <?= h($destination) ?></h1>
      <p class="rt-sub"><?= h(date('l, F j, Y', strtotime($travelDate))) ?> · <?= $passengers ?> passenger<?= $passengers === 1 ? '' : 's' ?> · <a href="book.php">Modify search</a></p>
    </div>
    <?php if ($buses): ?>
      <nav class="rt-sort" aria-label="Sort trips">
        <span>Sort:</span>
        <?php foreach (['departure' => 'Departure', 'fare' => 'Fare', 'duration' => 'Duration'] as $key => $label): ?>
          <a href="<?= h($sortLink($key)) ?>"<?= $sort === $key ? ' aria-current="true"' : '' ?>><?= $label ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
  </div>

  <?php if ($buses): ?>
    <form class="rt-filters" method="get" action="routes.php" id="rt-filters">
      <input type="hidden" name="sort" value="<?= h($sort) ?>">
      <fieldset>
        <legend>Bus type</legend>
        <div class="rt-pills">
          <label><input type="radio" name="type" value=""<?= $filterType === '' ? ' checked' : '' ?>><span>All</span></label>
          <?php foreach ($typeOptions as $type): ?>
            <label><input type="radio" name="type" value="<?= h($type) ?>"<?= $filterType === $type ? ' checked' : '' ?>><span><?= h($type) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <?php if ($fareCeiling > $fareFloor): ?>
        <div class="rt-filter">
          <label for="rt-max-fare">Max fare: <output id="rt-fare-out" for="rt-max-fare"><?= $filterMaxFare >= $fareCeiling ? 'Any' : '₱' . number_format($filterMaxFare) ?></output></label>
          <input type="range" id="rt-max-fare" name="max_fare" min="<?= $fareFloor ?>" max="<?= $fareCeiling ?>" step="1" value="<?= $filterMaxFare ?>">
        </div>
      <?php endif; ?>
      <?php if ($amenityOptions): ?>
        <div class="rt-filter">
          <label for="rt-amenity">Amenity</label>
          <select id="rt-amenity" name="amenity">
            <option value="">All</option>
            <?php foreach ($amenityOptions as $amenity): ?>
              <option value="<?= h($amenity) ?>"<?= $filterAmenity === $amenity ? ' selected' : '' ?>><?= h($amenity) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="rt-filter-actions">
        <button type="submit" class="bw-btn-ghost rt-apply">Apply</button>
        <a class="bw-btn-ghost" href="<?= h('routes.php?sort=' . $sort) ?>">Reset filters</a>
      </div>
    </form>
  <?php else: ?>
    <div class="rt-notice" role="status">
      <h2 class="bw-display">No buses on this route right now</h2>
      <p>Nothing on your route has enough seats for your group.
      <?= $alternatives ? 'Here are other buses you can book. They may leave from a different city or on a different date, so check the details.' : 'Try another route, or <a href="browse.php">browse other dates</a>.' ?></p>
    </div>
  <?php endif; ?>

  <?php if ($buses && !$routeTrips): ?>
    <div class="rt-empty">
      <h2 class="bw-display">No buses match these filters</h2>
      <p><a href="<?= h('routes.php?sort=' . $sort) ?>">Reset the filters</a> to see all <?= count($buses) ?> trips.</p>
    </div>
  <?php endif; ?>

  <div class="rt-list">
  <?php foreach ($displayTrips as $bus):
      $departure = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['departure_time'], $zone);
      $arrival = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['arrival_time'], $zone);

      if ($arrival < $departure) {
          $arrival = $arrival->modify('+1 day');
      }
      $minutes = (int) (($arrival->getTimestamp() - $departure->getTimestamp()) / 60);
      $nextDay = $arrival->format('Y-m-d') !== $departure->format('Y-m-d');
      $seatsLeft = (int) $bus['available_seats'];
      $type = strtolower((string) $bus['bus_type']);
      ?>
    <article class="rt-card">
      <div class="rt-bus">
        <span class="rt-bus-label"><?= h($bus['operator'] ?: 'Bus no.') ?></span>
        <h2 class="bw-display"><?= h($bus['bus_number']) ?></h2>
        <span class="rt-type <?= in_array($type, ['express', 'premium'], true) ? $type : '' ?>"><?= h($bus['bus_type']) ?></span>
      </div>
      <div class="rt-journey">
        <div class="rt-stop">
          <strong class="bw-display"><?= h($departure->format('g:i')) ?><small><?= h($departure->format('A')) ?></small></strong>
          <span><?= h($bus['origin']) ?></span>
        </div>
        <div class="rt-duration">
          <span><?= h(format_duration($minutes)) ?></span>
          <i aria-hidden="true"></i>
        </div>
        <div class="rt-stop">
          <strong class="bw-display"><?= h($arrival->format('g:i')) ?><small><?= h($arrival->format('A')) ?></small><?php if ($nextDay): ?><sup title="Arrives the next day">+1</sup><?php endif; ?></strong>
          <span><?= h($bus['destination']) ?></span>
        </div>
      </div>
      <div class="rt-extras">
        <?php if ($amenities = $amenityList($bus)): ?>
          <div class="rt-amenities"><?php foreach ($amenities as $amenity): ?><span><?= h($amenity) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <span class="rt-seats<?= $seatsLeft < 10 ? ' low' : '' ?>"><?= $seatsLeft ?> seat<?= $seatsLeft === 1 ? '' : 's' ?> left</span>
        <?php if (!$buses): ?><span class="rt-when"><?= h($departure->format('D, M j')) ?></span><?php endif; ?>
      </div>
      <div class="rt-price">
        <strong class="bw-display">₱<?= number_format((float) $bus['fare_per_seat'], 0) ?></strong>
        <span>per seat</span>
      </div>
      <?php if ($buses): ?>
        <a class="bw-btn rt-select" href="bus.php?schedule_id=<?= (int) $bus['schedule_id'] ?>" aria-label="Select bus <?= h($bus['bus_number']) ?> leaving <?= h($departure->format('g:i A')) ?>">Select →</a>
      <?php else: ?>
        <form method="post" action="book.php">
          <input type="hidden" name="origin" value="<?= h($bus['origin']) ?>">
          <input type="hidden" name="destination" value="<?= h($bus['destination']) ?>">
          <input type="hidden" name="travel_date" value="<?= h($bus['travel_date']) ?>">
          <input type="hidden" name="passengers" value="<?= $passengers ?>">
          <button class="bw-btn rt-select" type="submit">View route →</button>
        </form>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  </div>

  <p class="rt-footnote">Times are Philippine time. Service fee and tax show on the fare summary. Seats are checked again when you reserve.</p>
  <a class="rt-back" href="book.php">← Modify search</a>
</section>

<?php if ($buses): ?>
<script>
// Apply filters as soon as they change; the Apply button stays for no-JS.
(() => {
  const form = document.getElementById('rt-filters');
  const range = document.getElementById('rt-max-fare');
  const out = document.getElementById('rt-fare-out');
  form.querySelector('.rt-apply').hidden = true;
  form.addEventListener('change', () => form.submit());
  if (range) {
    range.addEventListener('input', () => {
      out.textContent = range.value === range.max ? 'Any' : '₱' + Number(range.value).toLocaleString();
    });
  }
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
