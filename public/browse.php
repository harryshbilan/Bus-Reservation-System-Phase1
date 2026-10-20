<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$zone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $zone);
$today = $now->format('Y-m-d');
$error = null;
$trips = [];

function browse_text(array $input, string $key, string $default = ''): string
{
    return isset($input[$key]) && is_string($input[$key])
        ? trim($input[$key]) : $default;
}

$periods = [
    'today' => 'Today',
    'tomorrow' => 'Tomorrow',
    'week' => 'Next 7 Days',
    'month' => 'This Month',
    'date' => 'Choose a Date',
];
$period = browse_text($_GET, 'period', 'month');

if (!isset($periods[$period])) {
    $period = 'month';
}
$chosenDate = browse_text($_GET, 'date', $today);
$parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $chosenDate, $zone);

if (!$parsedDate || $parsedDate->format('Y-m-d') !== $chosenDate || $chosenDate < $today) {
    $chosenDate = $today;

    if ($period === 'date') {
        $error = 'Please choose today or a later valid date.';
    }
}

$start = $today;
$end = $today;

switch ($period) {
    case 'tomorrow':
        $start = $end = $now->modify('+1 day')->format('Y-m-d');
        break;
    case 'week':
        $end = $now->modify('+6 days')->format('Y-m-d');
        break;
    case 'month':
        $end = $now->modify('last day of this month')->format('Y-m-d');
        break;
    case 'date':
        $start = $end = $chosenDate;
        break;
}

$origin = browse_text($_GET, 'origin');
$destination = browse_text($_GET, 'destination');
$passengers = filter_var(
    $_GET['passengers'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 40]]
) ?: 1;

if (empty($_SESSION['browse_token'])) {
    $_SESSION['browse_token'] = bin2hex(random_bytes(32));
}

// Availability belongs to a departure, not every trip made by the same bus.
$tripSql = "
    SELECT s.schedule_id, s.travel_date, s.departure_time, s.arrival_time,
           s.fare, b.bus_number, b.bus_type, b.total_seats,
           rt.origin, rt.destination,
           GREATEST(0, b.total_seats - COALESCE(occupied.seats, 0)) AS available_seats
    FROM schedules s
    JOIN buses b ON b.bus_id = s.bus_id
    JOIN routes rt ON rt.route_id = s.route_id
    LEFT JOIN (
     SELECT schedule_id, COUNT(DISTINCT seat_number) AS seats
        FROM reservations
        WHERE status IN ('PENDING', 'PAID')
        GROUP BY schedule_id
    ) occupied ON occupied.schedule_id = s.schedule_id
";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = browse_text($_POST, 'csrf_token');

    if (!hash_equals($_SESSION['browse_token'], $token)) {
        http_response_code(403);
        exit('This form has expired. Reload the page and try again.');
    }

    $scheduleId = filter_var(
        $_POST['schedule_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $partySize = filter_var(
        $_POST['passengers'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 40]]
    );

    if (!$scheduleId || !$partySize) {
        $error = 'Please choose a valid trip and passenger count.';
    } else {
        try {
            $stmt = $pdo->prepare($tripSql . '
                WHERE s.schedule_id = ?
                  AND TIMESTAMP(s.travel_date, s.departure_time) > ?
            ');
            $stmt->execute([$scheduleId, $now->format('Y-m-d H:i:s')]);
            $selected = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$selected || (int) $selected['available_seats'] < $partySize) {
                $error = 'This trip is no longer available for your group. Please choose another.';
            } else {
                // Start a fresh booking draft with authoritative database values.
                // Existing saved reservations are not changed.
                $_SESSION['booking'] = [
                    'origin' => $selected['origin'],
                    'destination' => $selected['destination'],
                    'travel_date' => $selected['travel_date'],
                    'passengers' => $partySize,
                    'schedule_id' => (int) $selected['schedule_id'],
                ];
                header('Location: bus.php?schedule_id=' . (int) $selected['schedule_id'], true, 303);
                exit;
            }
        } catch (PDOException $exception) {
            error_log('BUSWAY browse selection: ' . $exception->getMessage());
            $error = 'This trip could not be opened. Please try again.';
        }
    }
}

try {
    $stmt = $pdo->prepare($tripSql . '
        WHERE s.travel_date BETWEEN ? AND ?
          AND TIMESTAMP(s.travel_date, s.departure_time) > ?
          AND LOCATE(?, rt.origin) > 0
          AND LOCATE(?, rt.destination) > 0
          AND b.total_seats - COALESCE(occupied.seats, 0) >= ?
        ORDER BY s.travel_date, s.departure_time, rt.origin, rt.destination, s.schedule_id
    ');
    $stmt->execute([$start, $end, $now->format('Y-m-d H:i:s'), $origin, $destination, $passengers]);
    $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('BUSWAY browse list: ' . $exception->getMessage());
    $error = 'Available trips could not be loaded. Please try again.';
}

$total = count($trips);
$pageSize = 25;
$pages = max(1, (int) ceil($total / $pageSize));
$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
) ?: 1;
$page = min($page, $pages);
$visibleTrips = array_slice($trips, ($page - 1) * $pageSize, $pageSize);
$query = [
    'period' => $period, 'date' => $chosenDate, 'origin' => $origin,
    'destination' => $destination, 'passengers' => $passengers,
];
$pageTitle = 'Browse Available Buses';
$activeStep = null;
$pageStyles = ['browse.css'];
require __DIR__ . '/../includes/header.php';
?>

<section class="browse-page">
    <a href="index.php">← Back to home</a>
    <h1>BROWSE AVAILABLE BUSES</h1>
    <p>Explore upcoming trips and choose your travel date. Viewing this page does not reserve a seat.</p>

    <nav class="browse-tabs" aria-label="Browse by travel period">
        <?php foreach ($periods as $key => $label): ?>
            <a href="browse.php?<?= h(http_build_query(array_replace($query, ['period' => $key]))) ?>"
               <?= $period === $key ? 'aria-current="page"' : '' ?>><?= h($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <form class="browse-filters" method="get" action="browse.php">
        <div>
            <label for="browse-period">Travel period</label>
            <select id="browse-period" name="period">
                <?php foreach ($periods as $key => $label): ?>
                    <option value="<?= h($key) ?>" <?= $period === $key ? 'selected' : '' ?>><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="browse-date">Date (for Choose a Date)</label>
            <input id="browse-date" type="date" name="date" min="<?= h($today) ?>" value="<?= h($chosenDate) ?>">
        </div>
        <div>
            <label for="browse-origin">Origin</label>
            <input id="browse-origin" name="origin" value="<?= h($origin) ?>" placeholder="Any origin">
        </div>
        <div>
            <label for="browse-destination">Destination</label>
            <input id="browse-destination" name="destination" value="<?= h($destination) ?>" placeholder="Any destination">
        </div>
        <div>
            <label for="browse-passengers">Passengers</label>
            <input id="browse-passengers" type="number" name="passengers" min="1" max="40" value="<?= (int) $passengers ?>" required>
        </div>
        <button type="submit">Apply filters</button>
        <a href="browse.php">Clear filters</a>
    </form>

    <?php if ($error): ?>
        <p class="browse-error" role="alert"><?= h($error) ?></p>
    <?php endif; ?>

    <?php if (!$error || $trips): ?>
        <p>
            <?= h($start) ?> to <?= h($end) ?> · <?= $total ?> available departure<?= $total === 1 ? '' : 's' ?>
            for <?= (int) $passengers ?> passenger<?= $passengers === 1 ? '' : 's' ?>.
            This Month includes today through month-end; Next 7 Days includes today.
        </p>
        <?php if (!$trips): ?>
            <div class="browse-empty">
                <h2>NO AVAILABLE DEPARTURES FOR THIS PERIOD</h2>
                <p>Try Tomorrow, This Month, another date, or clear the route filters.</p>
                </div>
        <?php else: ?>
            <p>Seat availability can change. View trip starts a new booking draft; seats are checked again at checkout.</p>
            <div class="browse-table-wrap" tabindex="0" role="region" aria-label="Available trips; scroll horizontally on small screens">
                <table class="browse-table">
                    <thead><tr>
                        <th scope="col">Route</th><th scope="col">Departure</th>
                        <th scope="col">Bus</th><th scope="col">Seats available</th>
                        <th scope="col">Base fare / seat</th><th scope="col">Choose</th>
                    </tr></thead>
                    <tbody>
                    <?php $lastDate = null; ?>
                    <?php foreach ($visibleTrips as $trip): ?>
                        <?php if ($trip['travel_date'] !== $lastDate): ?>
                            <tr class="browse-date"><th colspan="6">
                                <?= h((new DateTimeImmutable($trip['travel_date'], $zone))->format('l, F j, Y')) ?>
                            </th></tr>
                            <?php $lastDate = $trip['travel_date']; ?>
                        <?php endif; ?>
                        <tr>
                            <td><?= h($trip['origin']) ?> → <?= h($trip['destination']) ?></td>
                            <td><?= h((new DateTimeImmutable($trip['departure_time'], $zone))->format('g:i A')) ?></td>
                            <td><?= h($trip['bus_number']) ?><small><?= h($trip['bus_type']) ?></small></td>
                            <td><span class="browse-seats"><?= (int) $trip['available_seats'] ?> of <?= (int) $trip['total_seats'] ?></span></td>
                            <td>₱<?= number_format((float) $trip['fare'], 2) ?></td>
                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['browse_token']) ?>">
                                    <input type="hidden" name="schedule_id" value="<?= (int) $trip['schedule_id'] ?>">
                                    <input type="hidden" name="passengers" value="<?= (int) $passengers ?>">
                                    <button type="submit">View trip →</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav class="browse-pagination" aria-label="Trip pages">
                <?php if ($page > 1): ?>
                    <a href="browse.php?<?= h(http_build_query($query + ['page' => $page - 1])) ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?> · up to 25 departures per page</span>
                <?php if ($page < $pages): ?>
                    <a href="browse.php?<?= h(http_build_query($query + ['page' => $page + 1])) ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>