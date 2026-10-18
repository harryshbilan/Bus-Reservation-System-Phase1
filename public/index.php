<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
$zone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $zone);
$today = $now->format('Y-m-d');
function home_text(array $source, string $key, string $fallback = ''): string {
    return is_string($source[$key] ?? null) ? trim($source[$key]) : $fallback;
}
$saved = is_array($_SESSION['booking'] ?? null) ? $_SESSION['booking'] : [];
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $saved;
$origin = home_text($input, 'origin');
$destination = home_text($input, 'destination');
$travelDate = home_text($input, 'travel_date', $today);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $travelDate < $today) $travelDate = $today;
$passengers = filter_var($input['passengers'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>40]]);
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $travelDate, $zone);
    if ($origin === '' || $destination === '' || mb_strlen($origin) > 100 || mb_strlen($destination) > 100) {
        $error = 'Enter a departure city and a destination, using up to 100 characters each.';
    } elseif (strcasecmp($origin, $destination) === 0) {
        $error = 'Choose different departure and destination cities.';
    } elseif (!$parsed || $parsed->format('Y-m-d') !== $travelDate || $travelDate < $today) {
        $error = 'Choose a valid travel date, today or later.';
    } elseif ($passengers === false) {
        $error = 'Choose between 1 and 40 passengers.';
    } else {
        $_SESSION['booking'] = ['origin'=>$origin,'destination'=>$destination,'travel_date'=>$travelDate,'passengers'=>$passengers];
        redirect('routes.php');
    }
}
$passengers = $passengers === false ? 1 : $passengers;
$occupied = [];
foreach ($pdo->query("SELECT schedule_id, COUNT(DISTINCT seat_number) AS occupied FROM reservations WHERE status IN ('PENDING','PAID') GROUP BY schedule_id") as $row) {
    $occupied[(int)$row['schedule_id']] = (int)$row['occupied'];
}
$cities = [];
$upcoming = [];
foreach (get_schedules() as $trip) {
    $cities[$trip['origin']] = true;
    $cities[$trip['destination']] = true;
    $trip['available'] = max(0, (int)$trip['total_seats'] - ($occupied[(int)$trip['schedule_id']] ?? 0));
    if ($trip['travel_date'].' '.$trip['departure_time'] > $now->format('Y-m-d H:i:s') && $trip['available'] > 0) $upcoming[] = $trip;
}
usort($upcoming, fn($a,$b) => [$a['travel_date'],$a['departure_time'],$a['schedule_id']] <=> [$b['travel_date'],$b['departure_time'],$b['schedule_id']]);
$cityNames = array_keys($cities);
sort($cityNames, SORT_NATURAL | SORT_FLAG_CASE);
$destinationTrips = [];
foreach ($upcoming as $trip) {
    if (!isset($destinationTrips[$trip['destination']])) $destinationTrips[$trip['destination']] = $trip;
}
$destinations = array_slice(array_values($destinationTrips),0,4);
$previews = array_slice($upcoming,0,3);
$photos = ['baguio'=>'baguio.jpg','baguio city'=>'baguio.jpg','vigan'=>'vigan.jpg','cebu'=>'cebu.jpg','cebu city'=>'cebu.jpg'];
$pageTitle = 'BUSWAY — Your next journey starts here';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/homepage.css">
<script src="../assets/js/homepage.js" defer></script>
<div class="bw-home">
<section class="bh-hero">
  <img class="bh-hero-photo" src="../assets/images/hero.jpg" alt="" fetchpriority="high">
  <div class="bh-hero-shade"></div>
  <div class="bh-hero-inner">
    <div class="bh-hero-copy">
      <span class="bh-kicker">A little further. A little more to discover.</span>
      <h1>Good journeys<br>start with<br><em>a great plan.</em></h1>
      <p>City escapes. Familiar places. New memories.<br>Find your bus, choose your seat, and enjoy what's next.</p>
      <a href="#explore" class="bh-explore">Find your next stop <span aria-hidden="true">↘</span></a>
      <div class="bh-hero-note"><span aria-hidden="true">01 /</span> Your next chapter starts on the road.</div>
    </div>
    <div class="bh-search" id="journey-details">
      <div class="bh-search-top"><span class="bh-kicker">LET'S GET YOU THERE</span><span class="bh-small-badge">Book a bus</span></div>
      <h2>Where are we going?</h2>
      <p>Find a departure that fits your day.</p>
      <?php if ($error): ?><div class="bh-error" role="alert"><?= h($error) ?></div><?php endif; ?>
      <form method="post" action="index.php" id="bh-search-form">
        <div class="bh-route-fields">
          <div class="bh-field"><label for="origin">From</label><input id="origin" name="origin" list="bh-cities" maxlength="100" placeholder="Departure city" required value="<?= h($origin) ?>" autocomplete="off"></div>
          <button type="button" class="bh-swap" id="bh-swap" aria-label="Swap departure and destination" title="Swap cities">⇅</button>
          <div class="bh-field"><label for="destination">To</label><input id="destination" name="destination" list="bh-cities" maxlength="100" placeholder="Your next destination" required value="<?= h($destination) ?>" autocomplete="off"></div>
        </div>
        <datalist id="bh-cities"><?php foreach ($cityNames as $city): ?><option value="<?= h($city) ?>"><?php endforeach; ?></datalist>
        <div class="bh-field-row">
          <div class="bh-field"><label for="travel_date">Travel date</label><input id="travel_date" type="date" name="travel_date" min="<?= h($today) ?>" value="<?= h($travelDate) ?>" required></div>
          <div class="bh-field"><label for="passengers">Passengers</label><input id="passengers" name="passengers" type="number" min="1" max="40" value="<?= $passengers ?>" required></div>
        </div>
        <button class="bh-button" type="submit">Find my bus <span aria-hidden="true">→</span></button>
      </form>
      <a class="bh-browse" href="browse.php">Flexible dates? Browse available buses →</a>
      <p class="bh-search-note">Choose your seats and review the total before reserving.</p>
    </div>
  </div>
</section>
<div class="bh-benefits">
  <div><span>01</span><p><strong>Find your route</strong><small>Compare upcoming departures</small></p></div>
  <div><span>02</span><p><strong>Make it your seat</strong><small>Choose from available seats</small></p></div>
  <div><span>03</span><p><strong>Plan with clarity</strong><small>Review the fare before booking</small></p></div>
</div>
<section class="bh-section" id="explore">
  <div class="bh-section-head"><div><span class="bh-kicker">FOLLOW YOUR CURIOSITY</span><h2>A change of scenery<br>is closer than you think.</h2></div><p>Explore destinations with upcoming departures.<br>Your next stop is yours to choose.</p></div>
  <div class="bh-destinations">
    <?php foreach ($destinations as $i=>$trip):
      $photo = $photos[strtolower($trip['destination'])] ?? 'hero.jpg';
      $link = 'browse.php?'.http_build_query(['period'=>'date','date'=>$trip['travel_date'],'destination'=>$trip['destination']]);
    ?>
    <a class="bh-destination" href="<?= h($link) ?>">
      <img src="../assets/images/<?= h($photo) ?>" alt="" loading="lazy" width="500" height="600">
      <span class="bh-destination-number">0<?= $i+1 ?> / NEXT STOP</span>
      <div><h3><?= h($trip['destination']) ?></h3><p>Explore departures <span aria-hidden="true">↗</span></p></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (!$destinations): ?><p class="bh-empty">New journeys are on the way. Browse other dates or check back for upcoming departures.</p><?php endif; ?>
</section>
<section class="bh-departures">
  <div class="bh-section">
    <div class="bh-section-head"><div><span class="bh-kicker">ON THE HORIZON</span><h2>Next buses out.</h2></div><a class="bh-text-link" href="browse.php">Explore all schedules ↗</a></div>
    <div class="bh-trip-grid">
    <?php foreach ($previews as $trip): ?>
      <article class="bh-trip">
        <div class="bh-trip-top"><span><?= h($trip['bus_type']) ?></span><small><?= (int)$trip['available'] ?> seats available</small></div>
        <h3><?= h($trip['origin']) ?> <span aria-hidden="true">→</span> <?= h($trip['destination']) ?></h3>
        <p><?= h((new DateTimeImmutable($trip['travel_date'].' '.$trip['departure_time'],$zone))->format('D, M j · g:i A')) ?></p>
        <div class="bh-trip-bottom"><div><strong>₱<?= number_format((float)$trip['fare_per_seat'],2) ?></strong><small>Base fare / seat</small></div>
        <form action="index.php" method="post">
          <input type="hidden" name="origin" value="<?= h($trip['origin']) ?>"><input type="hidden" name="destination" value="<?= h($trip['destination']) ?>">
          <input type="hidden" name="travel_date" value="<?= h($trip['travel_date']) ?>"><input type="hidden" name="passengers" value="1">
          <button type="submit" aria-label="View trips from <?= h($trip['origin']) ?> to <?= h($trip['destination']) ?>">View trips ↗</button>
        </form></div>
      </article>
    <?php endforeach; ?>
    </div>
    <?php if (!$previews): ?><p class="bh-empty">No upcoming departures with available seats right now.</p><?php endif; ?>
    <p class="bh-caption">Times are in Philippine time. Availability can change. Service fees and tax appear in your fare summary.</p>
  </div>
</section>
<section class="bh-section bh-guide">
  <div><span class="bh-kicker">LESS GUESSWORK. MORE GOING.</span><h2>From “let's go”<br>to your next stop.</h2><p>A few simple steps to get your journey organized.</p><a class="bh-text-link" href="support.php">Need a hand? Visit help & support ↗</a></div>
  <div class="bh-steps">
    <article><span>01</span><div><h3>Find a trip that fits</h3><p>Set your route and travel date, then compare times and base fares.</p></div></article>
    <article><span>02</span><div><h3>Pick your seat. Make your plan.</h3><p>Add passenger details and review the complete fare before reserving.</p></div></article>
    <article><span>03</span><div><h3>Keep your reference ready</h3><p>Pay at the counter. Your reservation stays pending until staff confirms payment.</p></div></article>
  </div>
</section>
<section class="bh-final"><span class="bh-kicker">YOUR NEXT CHAPTER</span><h2>Take the scenic route<br>to your next memory.</h2><a class="bh-button" href="#journey-details">Let's find your bus <span aria-hidden="true">↑</span></a></section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>