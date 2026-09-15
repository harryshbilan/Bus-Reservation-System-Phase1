<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $travelDate = $_POST['travel_date'] ?? '';
    if ($travelDate === '' || $travelDate < date('Y-m-d')) {
        $error = 'Please choose a valid travel date (today or later).';
    } else {
        // Fresh search clears any earlier bus/seat/passenger selection.
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

<div class="eyebrow accent">BUS RESERVATION</div>
<h1 class="hero">SEARCH /<br><span class="accent">RESERVE A BUS</span></h1>
<p class="hero-sub mb-6">Enter your journey details to find available buses.</p>

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

<div class="stats-grid">
  <div class="stats-item"><div class="stats-value">240+</div><div class="stats-label">ROUTES DAILY</div></div>
  <div class="stats-item"><div class="stats-value">80+</div><div class="stats-label">CITIES SERVED</div></div>
  <div class="stats-item"><div class="stats-value">4.8&#9733;</div><div class="stats-label">AVG RATING</div></div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
