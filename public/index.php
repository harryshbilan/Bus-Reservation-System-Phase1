<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $travelDate = $_POST['travel_date'] ?? '';
    if ($travelDate === '' || $travelDate < date('Y-m-d')) {
        $error = 'Please choose a valid travel date (today or later).';
    } else {

        $_SESSION['booking'] = [
            'origin' => trim($_POST['origin'] ?? ''),
            'destination' => trim($_POST['destination'] ?? ''),
            'travel_date' => $travelDate,
            'passengers' => max(1, min(40, (int) ($_POST['passengers'] ?? 1))),
        ];
        redirect('routes.php');
    }
}

$booking = $_SESSION['booking'];
$origin = $booking['origin'] ?? '';
$destination = $booking['destination'] ?? '';
$travelDate = $booking['travel_date'] ?? '';
$passengers = $booking['passengers'] ?? 1;

$pageTitle = 'Search';
$activeStep = null;
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">

<div class="hero-overlay">

<div class="eyebrow accent">
THE PHILIPPINES AWAITS
</div>

<h1 class="hero-title">
DISCOVER THE PHILIPPINES,
<br>
<span class="accent">ONE ROUTE AT A TIME</span>
</h1>

<p class="hero-sub mb-6">
Comfortable, safe, and affordable bus travel connecting you to destinations across the archipelago.
</p>

<?php if ($error): ?>
  <div class="error-box">
    <div>
      <div class="etitle">MISSING INFORMATION</div>
      <div class="emsg"><?= h($error) ?></div>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-title">JOURNEY DETAILS</div>
  <form method="post" action="index.php">
    <div class="grid-2 mb-6">
      <div>
        <label class="label" for="origin">ORIGIN</label>
        <input class="input" type="text" id="origin" name="origin" placeholder="City of departure" value="<?= h($origin) ?>">
      </div>
      <div>
        <label class="label" for="destination">DESTINATION</label>
        <input class="input" type="text" id="destination" name="destination" placeholder="City of arrival" value="<?= h($destination) ?>">
      </div>
      <div>
        <label class="label" for="travel_date">TRAVEL DATE</label>
        <input class="input" type="date" id="travel_date" name="travel_date" value="<?= h($travelDate) ?>" min="<?= date('Y-m-d') ?>" required>
      </div>
      <div>
        <label class="label">PASSENGERS</label>
        <div class="counter">
          <button type="button" id="dec-btn" onclick="adjustPassengers(-1,1,40)" <?= $passengers <= 1 ? 'disabled' : '' ?>>&minus;</button>
          <div class="value" id="passengers-display"><?= (int) $passengers ?></div>
          <button type="button" id="inc-btn" onclick="adjustPassengers(1,1,40)" <?= $passengers >= 40 ? 'disabled' : '' ?>>+</button>
          <input type="hidden" name="passengers" id="passengers" value="<?= (int) $passengers ?>">
          <span class="hint">max 40</span>
        </div>
      </div>
    </div>
    <button type="submit" class="btn-primary">SEARCH BUS &rarr;</button>
  </form>
</div>

</div>
</section>

<div class="stats-grid">
  <div class="stats-item"><div class="stats-value">240+</div><div class="stats-label">ROUTES DAILY</div></div>
  <div class="stats-item"><div class="stats-value">80+</div><div class="stats-label">CITIES SERVED</div></div>
  <div class="stats-item"><div class="stats-value">8.1&#9733;</div><div class="stats-label">AVG RATING</div></div>
</div>

<section class="destinations">

    <div class="section-head">
        <div class="eyebrow accent">
            POPULAR DESTINATIONS
        </div>

        <h2 class="section-title">
            WHERE WILL YOU GO?
        </h2>

        <p>
            From island paradise to mountain haven — the Philippines has a destination for every traveler.
        </p>
    </div>


    <div class="destination-grid">

        <div class="destination-card">
            <img src="../assets/images/palawan.jpg">

            <div class="destination-overlay">
                <span>ISLAND PARADISE</span>
                <h3>EL NIDO, PALAWAN</h3>
                <p>Crystal waters and beautiful beaches</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/siargao.jpg">

            <div class="destination-overlay">
                <span>SURF CAPITAL</span>
                <h3>SIARGAO</h3>
                <p>Surf, sun, and island adventures</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/coron.jpg">

            <div class="destination-overlay">
                <span>DIVE DESTINATION</span>
                <h3>CORON, PALAWAN</h3>
                <p>Explore crystal clear waters</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/baguio.jpg">

            <div class="destination-overlay">
                <span>SUMMER CAPITAL</span>
                <h3>BAGUIO CITY</h3>
                <p>Cool weather and mountain views</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/vigan.jpg">

            <div class="destination-overlay">
                <span>HERITAGE CITY</span>
                <h3>VIGAN, ILOCOS SUR</h3>
                <p>Walk through history</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/cebu.jpg">

            <div class="destination-overlay">
                <span>QUEEN CITY</span>
                <h3>CEBU CITY</h3>
                <p>Culture, beaches, and adventure</p>
            </div>
        </div>


    </div>

</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
