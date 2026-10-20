<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date', 'schedule_id', 'passengers'], 'index.php');

if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
}

$scheduleId = (int) $_SESSION['booking']['schedule_id'];
$bus = get_schedule($scheduleId);
$travelDate = $_SESSION['booking']['travel_date'];
$passengers = (int) $_SESSION['booking']['passengers'];
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));

if (!$bus || $bus['travel_date'] !== $travelDate || $passengers < 1 || $passengers > 40) {
    redirect('routes.php');
}

if ($bus['travel_date'] . ' ' . $bus['departure_time'] <= $now->format('Y-m-d H:i:s')) {
    redirect('routes.php');
}

$totalSeats = (int) $bus['total_seats'];
$validSeats = [];
for ($number = 0; $number < $totalSeats; $number++) {
    $validSeats[] = (intdiv($number, 4) + 1) . 'ABCD'[$number % 4];
}

$stmt = $pdo->prepare("
    SELECT DISTINCT seat_number
    FROM reservations
    WHERE schedule_id = ? AND status IN ('PENDING', 'PAID')
");
$stmt->execute([$scheduleId]);
$bookedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);
$availableLabels = array_values(array_diff($validSeats, $bookedSeats));
$availableSeats = count($availableLabels);
$error = null;

if (empty($_SESSION['seat_form_token'])) {
    $_SESSION['seat_form_token'] = bin2hex(random_bytes(32));
}

$saved = $_SESSION['booking']['selected_seats'] ?? [];
$selectedSeats = is_array($saved)
    ? array_values(array_intersect($availableLabels, array_filter($saved, 'is_string')))
    : [];

if (count($selectedSeats) > $passengers) {
    $selectedSeats = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['seat_form_token'], $token)) {
        http_response_code(403);
        exit('This form has expired. Reload the page and try again.');
    }

    $submitted = $_POST['selected_seats'] ?? [];
    $wellFormed = is_array($submitted)
        && count(array_filter($submitted, 'is_string')) === count($submitted);

    if (!$wellFormed) {
        $error = 'Please choose seats from the seat map.';
        $selectedSeats = [];
    } else {
        $selectedSeats = array_values(array_intersect($availableLabels, $submitted));

        if (count($submitted) !== $passengers) {
            $error = "Please select exactly $passengers seat(s).";
        } elseif (count(array_unique($submitted)) !== count($submitted)
            || array_diff($submitted, $validSeats)) {
            $error = 'Please choose different, valid seats from the seat map.';
        } elseif (array_intersect($submitted, $bookedSeats)) {
            $error = 'A selected seat has just been reserved. Please choose another seat.';
        } else {
            $_SESSION['booking']['selected_seats'] = $selectedSeats;
            redirect('passenger.php');
        }
    }
}

$pageTitle = 'Seat Selection';
$activeStep = 'seat';
$pageStyles = ['seat.css'];
require __DIR__ . '/../includes/header.php';
?>

<section class="st-page">
  <div class="st-head">
    <span class="bw-kicker">Seat selection</span>
    <h1 class="bw-display">Select your seats</h1>
    <p><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?> · <?= h(date('D, M j, Y', strtotime($travelDate))) ?> · <?= h(date('g:i A', strtotime($bus['departure_time']))) ?></p>
  </div>

  <?php if ($error): ?>
    <p class="st-alert" role="alert"><?= h($error) ?></p>
  <?php endif; ?>
  <?php if ($availableSeats < $passengers): ?>
    <p class="st-alert" role="alert">This bus no longer has enough seats for your group. <a href="routes.php">Choose another bus.</a></p>
  <?php endif; ?>

  <form method="post" id="coach-form">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['seat_form_token']) ?>">
    <div class="sx-layout">
      <div>
        <div class="sx-key" aria-label="Seat legend">
          <span class="booked"><i aria-hidden="true"></i>Booked</span>
          <span><i aria-hidden="true"></i>Available</span>
          <span class="chosen"><i aria-hidden="true"></i>Selected</span>
        </div>
        <fieldset class="st-card sx-map">
          <legend class="st-card-title">Seat layout · Bus <?= h($bus['bus_number']) ?></legend>
          <p class="sx-hint">Pick <?= $passengers ?> seat<?= $passengers === 1 ? '' : 's' ?>. Tap a seat again to let it go.</p>
          <div class="sx-front">
            <span>Driver</span>
            <span class="sx-wheel" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/></svg></span>
          </div>
          <div class="sx-grid sx-cols" aria-hidden="true">
            <span></span><span>A</span><span>B</span><span></span><span>C</span><span>D</span>
          </div>
          <div class="sx-grid">
            <?php for ($index = 0; $index < (int) ceil($totalSeats / 4) * 4; $index++): ?>
              <?php if ($index % 4 === 0): ?><span class="sx-row" aria-hidden="true"><?= intdiv($index, 4) + 1 ?></span><?php endif; ?>
              <?php if ($index % 4 === 2): ?><span aria-hidden="true"></span><?php endif; ?>
              <?php if (!isset($validSeats[$index])): ?>
                <span aria-hidden="true"></span>
              <?php else:
                  $label = $validSeats[$index];
                  $booked = in_array($label, $bookedSeats, true);
                  $checked = in_array($label, $selectedSeats, true);
                  ?>
                <label class="sx-seat">
                  <input type="checkbox" name="selected_seats[]" value="<?= h($label) ?>"
                    aria-label="Seat <?= h($label) ?><?= $booked ? ', already booked' : '' ?>"
                    <?= $booked ? 'disabled' : '' ?> <?= $checked ? 'checked' : '' ?>>
                  <span><?= h($label) ?></span>
                </label>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
        </fieldset>
      </div>

      <aside class="st-card sx-summary" aria-label="Your seat selection">
        <h2 class="st-card-title">Summary</h2>
        <dl class="st-rows single">
          <div><dt>Bus no.</dt><dd>Bus <?= h($bus['bus_number']) ?></dd></div>
          <div><dt>Route</dt><dd><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></dd></div>
          <div><dt>Departure</dt><dd><?= h(date('g:i A', strtotime($bus['departure_time']))) ?></dd></div>
          <div><dt>Seats left</dt><dd><?= $availableSeats ?> of <?= $totalSeats ?></dd></div>
          <div><dt>Fare / seat</dt><dd>₱<?= number_format((float) $bus['fare_per_seat'], 2) ?></dd></div>
        </dl>
        <div class="sx-status" aria-live="polite" aria-atomic="true">
          <span id="coach-count"><?= count($selectedSeats) ?> of <?= $passengers ?> selected</span>
          <strong id="coach-labels"><?= h($selectedSeats ? implode(', ', $selectedSeats) : 'None yet') ?></strong>
        </div>
        <p id="coach-message" class="sx-message" role="status"></p>
        <button type="submit" class="bw-btn sx-continue" id="coach-continue" <?= $availableSeats < $passengers ? 'disabled' : '' ?>>Continue →</button>
        <p class="st-note">Seats aren't held until you reserve on the payment step.</p>
        <a class="sx-back" href="bus.php?schedule_id=<?= $scheduleId ?>">← Back to bus details</a>
      </aside>
    </div>
  </form>
</section>

<script>
(() => {
    const form = document.getElementById('coach-form');
    const seats = Array.from(form.querySelectorAll('input[name="selected_seats[]"]:not(:disabled)'));
    const maximum = <?= $passengers ?>;
    const next = document.getElementById('coach-continue');
    const message = document.getElementById('coach-message');
    const chosen = () => seats.filter(seat => seat.checked);
    function render() {
        const selected = chosen();
        document.getElementById('coach-count').textContent = `${selected.length} of ${maximum} selected`;
        document.getElementById('coach-labels').textContent = selected.length
            ? selected.map(seat => seat.value).join(', ') : 'None yet';
        next.disabled = selected.length !== maximum;
        next.textContent = selected.length === maximum
            ? 'Continue →'
            : `Select ${maximum - selected.length} more`;
    }

    seats.forEach(seat => seat.addEventListener('change', () => {
        message.textContent = '';
        if (chosen().length > maximum) {
            seat.checked = false;
            message.textContent = `You can select ${maximum} seat(s). Deselect one to choose a different seat.`;
        }
        render();
    }));

    form.addEventListener('submit', event => {
        if (chosen().length !== maximum) {
            event.preventDefault();
            message.textContent = `Please select exactly ${maximum} seat(s).`;
        }
    });
    render();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
