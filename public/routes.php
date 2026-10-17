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
require __DIR__ . '/../includes/header.php';
?>

<div class="scoped-timetable">
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

<?php if (!$buses): ?>
    <div class="empty-box">
        <div class="empty-title">
            NO AVAILABLE BUSES AT THE MOMENT
        </div>

        <p class="empty-hint">
            No upcoming departure with enough seats was found for
            <?= h($origin) ?> → <?= h($destination) ?>
            on <?= h(date('F j, Y', strtotime($travelDate))) ?>.
        </p>
    </div>

    <?php if ($alternatives): ?>
        <section class="alternative-trips">
            <h3>HERE ARE OTHER AVAILABLE BUSES YOU CAN BOOK</h3>

            <p>
                These trips may use a different route or travel date.
                Check the details before choosing.
                Showing up to 12 options with enough seats for
                <?= (int) $passengers ?>
                passenger<?= $passengers > 1 ? 's' : '' ?>.
            </p>

            <div class="alternative-grid">
                <?php foreach ($alternatives as $alternative): ?>
                    <article class="alternative-card">
                        <h4>
                            <?= h($alternative['origin']) ?>
                            →
                            <?= h($alternative['destination']) ?>
                        </h4>

                        <p>
                            Bus <?= h($alternative['bus_number']) ?>
                            · <?= h($alternative['bus_type']) ?>
                        </p>

                        <dl>
                            <div>
                                <dt>Travel date</dt>
                                <dd>
                                    <?= h(date(
                                        'D, M j, Y',
                                        strtotime($alternative['travel_date'])
                                    )) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Departure</dt>
                                <dd>
                                    <?= h(date(
                                        'g:i A',
                                        strtotime($alternative['departure_time'])
                                    )) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Available seats</dt>
                                <dd>
                                    <?= (int) $alternative['available_seats'] ?>
                                    of <?= (int) $alternative['total_seats'] ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Base fare per seat</dt>
                                <dd>
                                    ₱<?= number_format(
                                        (float) $alternative['fare_per_seat'],
                                        2
                                    ) ?>
                                </dd>
                            </div>
                        </dl>

                        <!-- Reuse the existing search flow so the booking
                             receives the correct route and travel date. -->
                        <form method="post" action="index.php">
                            <input
                                type="hidden"
                                name="origin"
                                value="<?= h($alternative['origin']) ?>"
                            >
                            <input
                                type="hidden"
                                name="destination"
                                value="<?= h($alternative['destination']) ?>"
                            >
                            <input
                                type="hidden"
                                name="travel_date"
                                value="<?= h($alternative['travel_date']) ?>"
                            >
                            <input
                                type="hidden"
                                name="passengers"
                                value="<?= (int) $passengers ?>"
                            >

                            <button type="submit" class="btn-primary">
                                VIEW THIS ROUTE →
                            </button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>
        <p class="empty-hint">
            There are currently no upcoming trips with enough seats
            for your group. Please try again later.
        </p>
    <?php endif; ?>
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
                <?= date("h:i A", strtotime($bus['departure_time'])) ?>
                <small><?= h($bus['origin']) ?></small>
            </div>

            <div class="duration">
              <?= floor($bus['duration_minutes'] / 60) ?>h 
              <?= $bus['duration_minutes'] % 60 ?>m
            </div>

            <div>
                <?= date("h:i A", strtotime($bus['arrival_time'])) ?>
                <small><?= h($bus['destination']) ?></small>
            </div>

        </div>

        <div class="fare-area">

            <div class="fare">
                <?= peso((int)$bus['fare_per_seat']) ?>
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
         
<a class="modify-search-btn" href="index.php">&larr;
     Modify Search</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
