<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
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
foreach (['name', 'email', 'phone'] as $field) {
    $values[$field] = is_array($saved) && is_string($saved[$field] ?? null) ? $saved[$field] : '';
}
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
    if (!$errors) {
        $_SESSION['booking']['passenger'] = $values;
        header('Location: fare.php', true, 303);
        exit;
    }
}

[$baseFare, $serviceFee, $tax, $total] = fare_breakdown((float) $bus['fare_per_seat'], count($selectedSeats));
$pageTitle = 'Passenger Information';
$activeStep = 'passenger';
require __DIR__ . '/../includes/header.php';
?>

<style>
.passenger-screen { max-width: 1050px; margin: 0 auto 48px; color: #21180e; }
.passenger-screen .passenger-intro { line-height: 1.7; color: #62574a; margin: 12px 0 26px; }
.passenger-workspace { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(260px,.8fr); gap: 24px; align-items: start; }
.passenger-form-card, .passenger-trip-card { background: #fff; padding: 28px; border: 1px solid #ded6cc; border-radius: 16px; }
.passenger-screen h3 { margin: 0 0 12px; font-size: 22px; }
.passenger-screen p { line-height: 1.6; }
.passenger-field { margin: 24px 0; }
.passenger-field label { display: block; font-weight: bold; margin-bottom: 9px; }
.passenger-field input { display: block; box-sizing: border-box; width: 100%; min-height: 50px; padding: 12px 14px; border: 1px solid #9b8d79; border-radius: 8px; background: #fffdfa; color: #21180e; font: inherit; }
.passenger-field input:focus { outline: 3px solid #f5d398; outline-offset: 2px; border-color: #855000; }
.passenger-field input[aria-invalid="true"] { border-color: #a52c20; }
.passenger-screen .passenger-hint { display: block; font-size: 13px; line-height: 1.6; color: #62574a; margin: 7px 0 0; }
.passenger-screen .passenger-error { color: #922d22; font-size: 14px; margin: 8px 0 0; }
.passenger-alert { padding: 16px; margin-bottom: 22px; background: #ffe5df; border-radius: 8px; color: #922d22; }
.passenger-submit { display: block; width: 100%; padding: 15px; border: 0; border-radius: 8px; background: #e8940a; color: #21180e; font: 700 15px Arial,sans-serif; cursor: pointer; }
.passenger-submit:focus-visible { outline: 3px solid #684000; outline-offset: 3px; }
.passenger-back { display: inline-block; margin-top: 18px; color: #684000; }
.passenger-trip-card { position: sticky; top: 24px; }
.passenger-trip-card dl { margin: 18px 0; }
.passenger-trip-card dl > div { display: flex; justify-content: space-between; gap: 18px; padding: 12px 0; border-bottom: 1px solid #eee; }
.passenger-trip-card dt { color: #62574a; }
.passenger-trip-card dd { margin: 0; text-align: right; font-weight: bold; overflow-wrap: anywhere; }
.passenger-seats { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 22px; }
.passenger-seat-tag { display: inline-block; padding: 8px 12px; background: #fff0d5; border: 1px solid #e8bd75; border-radius: 6px; font-weight: bold; color: #684000; }
.passenger-total { display: flex; justify-content: space-between; gap: 16px; padding: 16px; background: #fff3dc; border-radius: 8px; font-weight: bold; }


@media (max-width: 760px) {
    .passenger-workspace { grid-template-columns: 1fr; }
    .passenger-trip-card { position: static; }
    .passenger-form-card, .passenger-trip-card { padding: 20px; }
}
</style>

<section class="passenger-screen">
    <div class="eyebrow">PASSENGER INFO</div>
    <h2 class="section-title">Passenger Details</h2>
    <p class="passenger-intro">Enter the primary passenger's contact details for this booking. For a group, this person is the contact for all selected seats.</p>

    <?php if ($errors): ?>
        <div class="passenger-alert" role="alert">Please correct the highlighted fields. Your entered details have been kept.</div>
    <?php endif; ?>

    <div class="passenger-workspace">
        <form method="post" class="passenger-form-card">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['passenger_form_token']) ?>">
            <h3>Contact information</h3>
            <p class="passenger-hint">All three fields are required.</p>

            <div class="passenger-field">
                <label for="passenger-name">Full name</label>
                <input id="passenger-name" name="name" type="text" autocomplete="name" maxlength="100" required
                    value="<?= h($values['name']) ?>" placeholder="Enter your full name"
                    aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>"
                    aria-describedby="passenger-name-hint<?= isset($errors['name']) ? ' passenger-name-error' : '' ?>">
                <p id="passenger-name-hint" class="passenger-hint">Use the name you will present when boarding.</p>
                <?php if (isset($errors['name'])): ?><p id="passenger-name-error" class="passenger-error"><?= h($errors['name']) ?></p><?php endif; ?>
            </div>
            <div class="passenger-field">
                <label for="passenger-email">Email address</label>
                <input id="passenger-email" name="email" type="email" autocomplete="email" maxlength="100" required
                    value="<?= h($values['email']) ?>" placeholder="you@example.com"
                    aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>"
                    aria-describedby="passenger-email-hint<?= isset($errors['email']) ? ' passenger-email-error' : '' ?>">
                <p id="passenger-email-hint" class="passenger-hint">Use an address you can access. Keep it for identifying your booking.</p>
                <?php if (isset($errors['email'])): ?><p id="passenger-email-error" class="passenger-error"><?= h($errors['email']) ?></p><?php endif; ?>
            </div>

            <div class="passenger-field">
                <label for="passenger-phone">Phone number</label>
                <input id="passenger-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" required
                    value="<?= h($values['phone']) ?>" placeholder="09XX XXX XXXX"
                    aria-invalid="<?= isset($errors['phone']) ? 'true' : 'false' ?>"
                    aria-describedby="passenger-phone-hint<?= isset($errors['phone']) ? ' passenger-phone-error' : '' ?>">
                <p id="passenger-phone-hint" class="passenger-hint">Local or international format, including +63, is accepted.</p>
                <?php if (isset($errors['phone'])): ?><p id="passenger-phone-error" class="passenger-error"><?= h($errors['phone']) ?></p><?php endif; ?>
            </div>

            <button type="submit" class="passenger-submit">Continue to fare summary →</button>
            <p class="passenger-hint">You can review the fare before completing your reservation.</p>
            <a href="seat.php" class="passenger-back">← Back to seat selection</a>
        </form>
        <aside class="passenger-trip-card" aria-label="Booking preview">
            <h3>Your booking</h3>
            <p><strong><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></strong></p>
            <dl>
                <div><dt>Travel date</dt><dd><?= h(date('M j, Y', strtotime($bus['travel_date']))) ?></dd></div>
                <div><dt>Departure</dt><dd><?= h(date('g:i A', strtotime($bus['departure_time']))) ?></dd></div>
                <div><dt>Bus</dt><dd><?= h($bus['bus_number']) ?></dd></div>
                <div><dt>Bus type</dt><dd><?= h($bus['bus_type']) ?></dd></div>
                <div><dt>Passengers</dt><dd><?= $passengers ?></dd></div>
            </dl>
            <strong>Selected seats</strong>
            <div class="passenger-seats">
                <?php foreach ($selectedSeats as $seat): ?>
                    <span class="passenger-seat-tag"><?= h($seat) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="passenger-total"><span>Estimated total</span><span>₱<?= number_format($total, 2) ?></span></div>
            <p class="passenger-hint">Includes the current service fee and tax. Seats are checked again when your reservation is saved.</p>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>