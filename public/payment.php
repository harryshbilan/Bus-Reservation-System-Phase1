<?php
define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';

require_booking_keys(
    [
        'travel_date',
        'schedule_id',
        'selected_seats',
        'passenger'
    ],
    'index.php'
);

$scheduleId = (int) $_SESSION['booking']['schedule_id'];

$bus = get_schedule($scheduleId);

if (!$bus) {
    redirect('routes.php');
}

$selectedSeats = $_SESSION['booking']['selected_seats'];

$passenger = $_SESSION['booking']['passenger'];

$extraBags = booking_bags($_SESSION['booking']);
[$base, $serviceFee, $tax, $total, $bagFee] = fare_breakdown((float) $bus['fare_per_seat'], count($selectedSeats), $extraBags);

require_once __DIR__ . '/../includes/checkout.php';

// An online booking that is still unpaid goes back to its payment step.
if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect(!empty($_SESSION['booking']['awaiting_online']) ? 'pay-online.php' : 'ticket.php');
}

// Create a token to protect the payment form.
$_SESSION['checkout_token'] ??= bin2hex(random_bytes(32));

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['checkout_token'] ?? '';
    $method = $_POST['payment_method'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['checkout_token'], $token)
    ) {
        $error = 'That page timed out. Give it another go.';
    } elseif (!in_array($method, ['Cash', 'GCash', 'Card'], true)) {
        $error = 'Pick how you\'d like to pay first.';
    } else {
        try {
            $receipt = create_reservation(
                $pdo,
                $_SESSION['booking'],
                $method
            );

            $_SESSION['booking']['ticket_number'] =
                $receipt['ticket_number'];

            $_SESSION['booking']['reservation_ids'] =
                $receipt['reservation_ids'];

            $_SESSION['booking']['passenger']['id'] =
                $receipt['passenger_id'];

            unset($_SESSION['checkout_token']);

            if ($method !== 'Cash') {
                $_SESSION['booking']['awaiting_online'] = $method;
                redirect('pay-online.php');
            }

            redirect('ticket.php');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log(
                'BUSWAY checkout failed: ' . $exception->getMessage()
            );

            $error = 'Something went wrong saving your reservation. '
                . 'You haven\'t been charged, so please try again.';
        }
    }
}

$pageTitle = 'Payment';
$pageStyles = ['checkout.css'];
$activeStep = 'payment';
require __DIR__ . '/../includes/header.php';
?>

<section class="st-page st-narrow">
  <div class="st-head">
    <span class="bw-kicker">Payment / confirm</span>
    <h1 class="bw-display">Reservation details</h1>
    <p>Almost there. Choose how you'd like to pay.</p>
  </div>
  <?php if ($error): ?>
    <div class="st-alert" role="alert"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="st-card">
    <h2 class="st-card-title">Your trip <a href="fare.php">Review fare</a></h2>
    <dl class="st-rows">
      <div><dt>Bus no.</dt><dd>Bus <?= h($bus['bus_number']) ?></dd></div>
      <div><dt>Seat(s)</dt><dd><?= h(implode(', ', $selectedSeats)) ?></dd></div>
      <div><dt>Route</dt><dd><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></dd></div>
      <div><dt>Travel date</dt><dd><?= h(date('M j, Y', strtotime($bus['travel_date']))) ?></dd></div>
      <div><dt>Departure</dt><dd><?= h(date('g:i A', strtotime($bus['departure_time']))) ?></dd></div>
      <div><dt>Passenger</dt><dd><?= h($passenger['name']) ?></dd></div>
      <div class="pm-wide"><dt>Boarding point</dt><dd><?= h($bus['boarding_point']) ?></dd></div>
      <div class="pm-wide pm-amount"><dt>Amount</dt><dd class="hl">₱<?= number_format($total, 2) ?></dd></div>
    </dl>
    <p class="st-note pm-breakdown">Base fare ₱<?= number_format($base, 2) ?> + service fee ₱<?= number_format($serviceFee, 2) ?> + tax ₱<?= number_format($tax, 2) ?><?= $extraBags ? ' + ' . $extraBags . ' extra bag' . ($extraBags === 1 ? '' : 's') . ' ₱' . number_format($bagFee, 2) : '' ?></p>
  </div>

  <form method="post" id="counter-payment-form">
    <input type="hidden" name="checkout_token" value="<?= h($_SESSION['checkout_token']) ?>">
    <fieldset class="pm-tabs">
      <legend class="bw-sr-only">Payment method</legend>
      <label><input type="radio" name="payment_method" value="Card" checked><span><img src="../assets/images/pay-card.svg" alt="" width="34" height="21">Card</span></label>
      <label><input type="radio" name="payment_method" value="GCash"><span><img src="../assets/images/pay-gcash.svg" alt="" width="34" height="21">GCash</span></label>
      <label><input type="radio" name="payment_method" value="Cash" required><span><img src="../assets/images/pay-cash.svg" alt="" width="34" height="21">At the counter</span></label>
    </fieldset>

    <div class="st-card pm-panel" data-for="Card">
      <strong>Pay by credit or debit card</strong>
      <p>Visa or Mastercard. You'll enter your card on the next page and your ticket is confirmed straight away. Card details are checked, never stored.</p>
    </div>
    <div class="st-card pm-panel" data-for="GCash">
      <strong>Pay with GCash</strong>
      <p>Enter your GCash number on the next page and confirm with the code sent to your phone. Your ticket is confirmed straight away.</p>
    </div>
    <div class="st-card pm-panel" data-for="Cash">
      <strong>Pay in cash at the counter</strong>
      <p>Your seats are reserved now. Show your booking reference at the counter and pay before you board. Staff will mark it paid.</p>
    </div>

    <div class="st-actions">
      <a class="bw-btn-ghost" href="fare.php">← Back</a>
      <button type="submit" class="bw-btn" id="counter-reserve-button">Continue to card payment →</button>
    </div>
    <p class="st-note" id="counter-submit-status" role="status">Your seats are held for you. Nothing is marked as paid until payment goes through.</p>
  </form>
</section>

<script>
(() => {
    const form = document.getElementById('counter-payment-form');
    const button = document.getElementById('counter-reserve-button');
    const status = document.getElementById('counter-submit-status');
    const labels = { Cash: 'Reserve — pay at counter →', GCash: 'Continue to GCash →', Card: 'Continue to card payment →' };
    const idle = 'Your seats are held for you. Nothing is marked as paid until payment goes through.';
    let submitting = false;
    const current = () => form.querySelector('input[name="payment_method"]:checked')?.value || 'Card';
    const refresh = () => { button.textContent = labels[current()]; };
    form.addEventListener('change', refresh);
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        button.disabled = true;
        button.textContent = 'Saving your reservation…';
        status.textContent = 'Hold on, we\'re saving your seats…';
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        button.disabled = false;
        refresh();
        status.textContent = idle;
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>