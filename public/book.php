<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/trips.php';

$zone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $zone);
$today = $now->format('Y-m-d');

function book_text(array $source, string $key, string $fallback = ''): string
{
    return is_string($source[$key] ?? null) ? trim($source[$key]) : $fallback;
}

// Start from the last search so "Modify search" keeps what was typed.
$saved = is_array($_SESSION['booking'] ?? null) ? $_SESSION['booking'] : [];
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : array_merge($saved, $_GET);
$origin = book_text($input, 'origin');
$destination = book_text($input, 'destination');
$travelDate = book_text($input, 'travel_date', $today);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $travelDate < $today) {
    $travelDate = $today;
}
$passengers = filter_var($input['passengers'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 40]]);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDate, $zone);

    if ($origin === '' || $destination === '' || mb_strlen($origin) > 100 || mb_strlen($destination) > 100) {
        $error = 'Enter where you are leaving from and where you are going.';
    } elseif (strcasecmp($origin, $destination) === 0) {
        $error = 'Your origin and destination are the same. Pick a different destination.';
    } elseif (!$parsed || $parsed->format('Y-m-d') !== $travelDate || $travelDate < $today) {
        $error = 'Choose a travel date from today onwards.';
    } elseif ($passengers === false) {
        $error = 'You can book for 1 to 40 passengers at a time.';
    } else {
        $_SESSION['booking'] = ['origin' => $origin, 'destination' => $destination, 'travel_date' => $travelDate, 'passengers' => $passengers];
        redirect('routes.php');
    }
}
$passengers = $passengers === false ? 1 : $passengers;

$routes = upcoming_routes($pdo, $now);
$cities = route_cities($pdo);

$weekEnd = $now->modify('+7 days')->format('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM schedules WHERE TIMESTAMP(travel_date, departure_time) BETWEEN ? AND ?");
$stmt->execute([$now->format('Y-m-d H:i:s'), $weekEnd]);
$departuresThisWeek = (int) $stmt->fetchColumn();

// Group upcoming routes by destination for the cards at the bottom.
$destinations = [];

foreach ($routes as $route) {
    $key = $route['destination'];
    $destinations[$key] ??= ['name' => $key, 'origins' => [], 'lowest_fare' => (float) $route['lowest_fare'], 'departures' => 0];
    $destinations[$key]['origins'][] = $route['origin'];
    $destinations[$key]['lowest_fare'] = min($destinations[$key]['lowest_fare'], (float) $route['lowest_fare']);
    $destinations[$key]['departures'] += (int) $route['departures'];
}
$profiles = destination_profiles();
$profileFor = static function (string $city) use ($profiles): ?array {
    foreach ($profiles as $keyword => $profile) {
        if (stripos($city, $keyword) !== false) {
            return $profile;
        }
    }

    return null;
};
// Places we have a photo for go first, then the busiest.
uasort($destinations, fn ($a, $b) => [$profileFor($b['name']) !== null, $b['departures']] <=> [$profileFor($a['name']) !== null, $a['departures']]);
$destinations = array_slice($destinations, 0, 6);

$pageTitle = 'Book a trip | BUSWAY';
$pageStyles = ['book.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="bk-page">
  <section class="bk-banner">
    <img src="<?= h(image_url('hero.jpg')) ?>" alt="" fetchpriority="high">
    <div class="bk-banner-copy">
      <span class="bw-kicker">Book your trip</span>
      <h1 class="bw-display">Find your<br><em>bus route</em></h1>
      <p>Pick your route and date. We'll show you every bus with seats left.</p>
    </div>
  </section>

  <section class="bk-form-card" id="journey-details" aria-labelledby="journey-title">
    <h2 id="journey-title" class="bw-display">Journey details</h2>
    <?php if ($error): ?><div class="bk-error" role="alert"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="book.php" id="bk-form">
      <div class="bk-grid">
        <div class="bk-field">
          <label for="origin">Origin</label>
          <input id="origin" name="origin" list="bk-cities" maxlength="100" placeholder="City of departure" required value="<?= h($origin) ?>" autocomplete="off">
        </div>
        <div class="bk-field">
          <label for="destination">Destination</label>
          <input id="destination" name="destination" list="bk-cities" maxlength="100" placeholder="City of arrival" required value="<?= h($destination) ?>" autocomplete="off">
        </div>
        <div class="bk-field">
          <label for="travel_date">Travel date</label>
          <input id="travel_date" type="date" name="travel_date" min="<?= h($today) ?>" value="<?= h($travelDate) ?>" required>
        </div>
        <div class="bk-field">
          <label for="passengers">Passengers</label>
          <div class="bk-stepper">
            <button type="button" data-step="-1" aria-label="One passenger fewer">−</button>
            <input id="passengers" name="passengers" type="number" min="1" max="40" value="<?= (int) $passengers ?>" required>
            <button type="button" data-step="1" aria-label="One passenger more">+</button>
            <span>max 40 passengers</span>
          </div>
        </div>
      </div>
      <datalist id="bk-cities"><?php foreach ($cities as $city): ?><option value="<?= h($city) ?>"><?php endforeach; ?></datalist>
      <button class="bw-btn" type="submit">Search bus →</button>
      <a class="bk-browse" href="browse.php">Not sure of the date? Browse every departure</a>
    </form>
  </section>

  <section class="bk-stats" aria-label="BUSWAY at a glance">
    <div><strong><?= count($routes) ?></strong><span>Routes running</span></div>
    <div><strong><?= count($cities) ?></strong><span>Cities served</span></div>
    <div><strong><?= $departuresThisWeek ?></strong><span>Departures this week</span></div>
  </section>

  <?php if ($destinations): ?>
  <section class="bk-popular" aria-labelledby="popular-title">
    <span class="bw-kicker">Explore the Philippines</span>
    <h2 id="popular-title" class="bw-display">Popular destinations</h2>
    <div class="bk-dest-grid">
      <?php foreach ($destinations as $dest):
          $profile = $profileFor($dest['name']);
          $link = 'browse.php?' . http_build_query(['destination' => $dest['name'], 'period' => 'month']);
          $origins = array_unique($dest['origins']);
          ?>
        <a class="bk-dest" href="<?= h($link) ?>">
          <div class="bk-dest-photo<?= $profile ? '' : ' plain' ?>">
            <?php if ($profile): ?><img src="<?= h(image_url($profile[1])) ?>" alt="" loading="lazy"><?php else: ?><b aria-hidden="true"><?= h(mb_substr($dest['name'], 0, 1)) ?></b><?php endif; ?>
            <span>From <?= h(implode(' · ', array_slice($origins, 0, 2))) ?></span>
          </div>
          <div class="bk-dest-body">
            <h3 class="bw-display"><?= h($dest['name']) ?></h3>
            <p><?= h($profile[3] ?? ($dest['departures'] . ' departures coming up')) ?></p>
            <small>Fares from ₱<?= number_format($dest['lowest_fare'], 0) ?></small>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<script>
// Passenger − / + buttons; the number box still works on its own.
document.querySelectorAll('.bk-stepper [data-step]').forEach(button => {
  button.addEventListener('click', () => {
    const box = document.getElementById('passengers');
    const next = (parseInt(box.value, 10) || 1) + Number(button.dataset.step);
    box.value = Math.min(40, Math.max(1, next));
  });
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
