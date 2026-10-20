<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/passenger_details.php';
require_booking_keys(['travel_date', 'schedule_id', 'selected_seats', 'passengers'], 'index.php');

if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
}

$booking = $_SESSION['booking'];
$bus = get_schedule((int) $booking['schedule_id']);

if (!$bus || $bus['travel_date'] !== $booking['travel_date']) {
    redirect('routes.php');
}
$selectedSeats = $booking['selected_seats'];
$passengers = (int) $booking['passengers'];

if (!is_array($selectedSeats) || !$selectedSeats || $passengers < 1
    || count($selectedSeats) !== $passengers
    || count(array_filter($selectedSeats, 'is_string')) !== count($selectedSeats)) {
    redirect('seat.php');
}

$saved = $booking['passenger'] ?? [];
$values = [];

foreach (['name', 'email', 'phone', 'age', 'gender'] as $field) {
    $values[$field] = is_array($saved) && is_string($saved[$field] ?? null) ? $saved[$field] : '';
}
$extraBags = booking_bags($booking);
$maxBags = MAX_BAGS_PER_PASSENGER * $passengers;
$errors = [];

if (empty($_SESSION['passenger_form_token'])) {
    $_SESSION['passenger_form_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['passenger_form_token'], $token)) {
        http_response_code(403);
        exit('This form has expired. Reload the page and try again.');
    }

    foreach (array_keys($values) as $field) {
        $values[$field] = is_string($_POST[$field] ?? null) ? trim($_POST[$field]) : '';
    }

    if ($values['name'] === '' || mb_strlen($values['name']) > 100) {
        $errors['name'] = 'Enter your full name using no more than 100 characters.';
    }

    if (mb_strlen($values['email']) > 100 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address using no more than 100 characters.';
    }
    $phoneDigits = preg_replace('/\D/', '', $values['phone']);

    if (strlen($values['phone']) > 20
        || !preg_match('/^\+?[0-9 ()-]+$/D', $values['phone'])
        || strlen($phoneDigits) < 7 || strlen($phoneDigits) > 15) {
        $errors['phone'] = 'Enter a valid phone number with 7–15 digits. Spaces, brackets, hyphens and a leading + are allowed.';
    }
    $errors = array_merge($errors, passenger_detail_errors($values));
    $postedBags = filter_var($_POST['extra_bags'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => $maxBags]]);

    if ($postedBags === false) {
        $errors['extra_bags'] = "Extra bags must be between 0 and $maxBags.";
    } else {
        $extraBags = $postedBags;
    }

    if (!$errors) {
        $_SESSION['booking']['passenger'] = $values;
        $_SESSION['booking']['extra_bags'] = $extraBags;
        header('Location: fare.php', true, 303);
        exit;
    }
}

[$baseFare, $serviceFee, $tax, $total, $bagFee] = fare_breakdown((float) $bus['fare_per_seat'], count($selectedSeats), $extraBags);
$pageTitle = 'Passenger Information';
$activeStep = 'passenger';
$pageStyles = ['passenger.css'];
require __DIR__ . '/../includes/header.php';
?>
<?php
// One input row: label, field, hint and the server-side error, wired for screen readers.
$field = static function (string $name, string $label, string $input, string $hint = '') use ($errors): string {
    $id = 'passenger-' . $name;
    $out = '<div class="st-field' . ($name === 'name' ? ' px-wide' : '') . '"><label for="' . $id . '">' . h($label) . '</label>' . $input;

    if ($hint !== '') {
        $out .= '<small id="' . $id . '-hint">' . h($hint) . '</small>';
    }

    if (isset($errors[$name])) {
        $out .= '<small id="' . $id . '-error" class="st-error">' . h($errors[$name]) . '</small>';
    }

    return $out . '</div>';
};
$aria = static function (string $name, bool $hint) use ($errors): string {
    $id = 'passenger-' . $name;
    $describedBy = trim(($hint ? "$id-hint " : '') . (isset($errors[$name]) ? "$id-error" : ''));

    return 'aria-invalid="' . (isset($errors[$name]) ? 'true' : 'false') . '"' . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '');
};
?>
<section class="st-page">
  <div class="st-head">
    <span class="bw-kicker">Passenger info</span>
    <h1 class="bw-display">Who's travelling?</h1>
    <p>We only need the main contact for the booking.<?= count($selectedSeats) > 1 ? ' This person looks after all ' . count($selectedSeats) . ' seats.' : '' ?></p>
  </div>

  <?php if ($errors): ?>
    <div class="st-alert" role="alert">A few details need fixing. Everything you typed is still here.</div>
  <?php endif; ?>

  <div class="px-layout">
    <form method="post" class="st-card px-form">
      <input type="hidden" name="csrf_token" value="<?= h($_SESSION['passenger_form_token']) ?>">
      <div class="px-grid">
        <?= $field('name', 'Full name', '<input id="passenger-name" name="name" type="text" autocomplete="name" maxlength="100" required value="' . h($values['name']) . '" placeholder="Juan dela Cruz" ' . $aria('name', true) . '>', 'Use the name on the ID you will show when boarding.') ?>
        <?= $field('phone', 'Contact number', '<input id="passenger-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required value="' . h($values['phone']) . '" placeholder="09XX XXX XXXX" ' . $aria('phone', true) . '>', '+63 format works too.') ?>
        <?= $field('email', 'Email', '<input id="passenger-email" name="email" type="email" autocomplete="email" maxlength="100" required value="' . h($values['email']) . '" placeholder="juan@example.com" ' . $aria('email', true) . '>', 'Your ticket goes here.') ?>
        <?= $field('age', 'Age (optional)', '<input id="passenger-age" name="age" type="number" min="0" max="120" step="1" value="' . h($values['age']) . '" placeholder="28" ' . $aria('age', false) . '>') ?>
        <?php
        $options = '<option value="">Select gender</option>';

foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $option) {
    $options .= '<option value="' . h($option) . '"' . ($values['gender'] === $option ? ' selected' : '') . '>' . h($option) . '</option>';
}
?>
        <?= $field('gender', 'Gender (optional)', '<select id="passenger-gender" name="gender" ' . $aria('gender', false) . '>' . $options . '</select>') ?>
      </div>
      <section class="px-bags" aria-labelledby="bags-title">
        <h2 id="bags-title" class="bw-display">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="7" width="12" height="13" rx="2"/><path d="M9 7V4h6v3M10 11v5M14 11v5M8 20v1M16 20v1"/></svg>
          Luggage add-ons
        </h2>
        <div class="px-bag-row">
          <div>
            <strong>Extra checked bag</strong>
            <small>₱<?= BAG_FEE ?> per additional bag · up to 20 kg · max <?= $maxBags ?></small>
            <p class="px-bag-added" id="bag-added"<?= $extraBags ? '' : ' hidden' ?>>+₱<span id="bag-fee"><?= number_format($extraBags * BAG_FEE) ?></span> added to fare</p>
          </div>
          <div class="px-stepper">
            <button type="button" data-bag="-1" aria-label="One bag fewer">−</button>
            <input id="extra_bags" name="extra_bags" type="number" min="0" max="<?= $maxBags ?>" value="<?= $extraBags ?>" aria-label="Extra checked bags" <?= isset($errors['extra_bags']) ? 'aria-invalid="true"' : '' ?>>
            <button type="button" data-bag="1" aria-label="One bag more">+</button>
          </div>
        </div>
        <?php if (isset($errors['extra_bags'])): ?><small class="st-error"><?= h($errors['extra_bags']) ?></small><?php endif; ?>
        <p class="px-bag-note">Your fare already covers one 7 kg hand-carry and one checked bag up to 15 kg. <a href="discounts.php#baggage" target="_blank">Baggage rules</a></p>
      </section>
      <p class="st-note">Age doesn't apply a discount by itself. Senior, PWD and student discounts are claimed with a valid ID.</p>
      <div class="st-actions">
        <a class="bw-btn-ghost" href="seat.php">← Back</a>
        <button type="submit" class="bw-btn">View fare summary →</button>
      </div>
    </form>

    <aside class="st-card px-trip" aria-label="Booking preview">
      <h2 class="st-card-title">Your booking</h2>
      <p class="px-route bw-display"><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></p>
      <dl class="st-rows single">
        <div><dt>Travel date</dt><dd><?= h(date('M j, Y', strtotime($bus['travel_date']))) ?></dd></div>
        <div><dt>Departure</dt><dd><?= h(date('g:i A', strtotime($bus['departure_time']))) ?></dd></div>
        <div><dt>Bus</dt><dd><?= h($bus['bus_number']) ?> · <?= h($bus['bus_type']) ?></dd></div>
        <div><dt>Passengers</dt><dd><?= $passengers ?></dd></div>
      </dl>
      <div class="st-chips seats px-seats"><?php foreach ($selectedSeats as $seat): ?><span><?= h($seat) ?></span><?php endforeach; ?></div>
      <p class="px-bag-line" id="side-bags"<?= $extraBags ? '' : ' hidden' ?>>Extra bags: <span id="side-bag-count"><?= $extraBags ?></span> × ₱<?= BAG_FEE ?></p>
      <div class="px-total"><span>Estimated total</span><strong class="bw-display" id="side-total" data-base="<?= $total - $bagFee ?>">₱<?= number_format($total, 2) ?></strong></div>
    </aside>
  </div>
</section>
<script>
// Extra bag stepper: keeps the fee line and the side total in step with the count.
(() => {
  const box = document.getElementById('extra_bags');
  const fee = <?= BAG_FEE ?>;
  const peso = n => n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const total = document.getElementById('side-total');
  const render = () => {
    const max = Number(box.max);
    const bags = Math.min(max, Math.max(0, parseInt(box.value, 10) || 0));
    box.value = bags;
    document.getElementById('bag-added').hidden = bags === 0;
    document.getElementById('bag-fee').textContent = (bags * fee).toLocaleString('en-PH');
    document.getElementById('side-bags').hidden = bags === 0;
    document.getElementById('side-bag-count').textContent = bags;
    total.textContent = '₱' + peso(Number(total.dataset.base) + bags * fee);
  };
  document.querySelectorAll('[data-bag]').forEach(b => b.addEventListener('click', () => {
    box.value = (parseInt(box.value, 10) || 0) + Number(b.dataset.bag);
    render();
  }));
  box.addEventListener('input', render);
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
