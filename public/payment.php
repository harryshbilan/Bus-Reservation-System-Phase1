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


$fare = fare_breakdown(
    (float)$bus['fare_per_seat'],
    count($selectedSeats)
);


[$base, $serviceFee, $tax, $total] = $fare;



require_once __DIR__ . '/../includes/checkout.php';

// Show the existing receipt if this booking was already saved.
if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
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
        $error = 'Your payment form has expired. Please try again.';
    } elseif (!is_string($method)) {
        $error = 'Please choose Pay at counter.';
    } else {
        try {
            $receipt = create_counter_reservation(
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

            redirect('ticket.php');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log(
                'BUSWAY checkout failed: ' . $exception->getMessage()
            );

            $error = 'We could not save your reservation. '
                . 'No payment was taken. Please try again.';
        }
    }
}

$pageTitle = 'Payment';
$activeStep = 'payment';
require __DIR__ . '/../includes/header.php';
?>

<style>
.payment-screen { max-width: 1050px; margin: 0 auto 48px; color: #21180e; }
.payment-screen .payment-intro { margin: 12px 0 26px; color: #62574a; line-height: 1.7; }
.payment-layout { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(280px,.8fr); gap: 24px; align-items: start; }
.payment-details { display: grid; gap: 24px; }
.payment-card { padding: 28px; background: white; border: 1px solid #ded6cc; border-radius: 16px; min-width: 0; }
.payment-screen h3 { margin: 0; font-size: 22px; }
.payment-screen p { line-height: 1.6; }
.payment-screen a { color: #684000; }
.payment-heading { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 18px; }
.payment-heading a { font-size: 14px; }
.payment-route { font-size: 23px; font-weight: bold; margin: 0 0 18px; }
.payment-meta { margin: 0; }
.payment-meta > div { display: flex; justify-content: space-between; gap: 18px; padding: 12px 0; border-bottom: 1px solid #eee; }
.payment-meta dt { color: #62574a; flex-shrink: 0; }
.payment-meta dd { margin: 0; text-align: right; min-width: 0; overflow-wrap: anywhere; }
.payment-seats { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.payment-seat { padding: 8px 12px; border: 1px solid #e8bd75; background: #fff0d5; border-radius: 6px; color: #684000; font-weight: bold; }
.payment-checkout { position: sticky; top: 24px; border-top: 5px solid #e8940a; }
.payment-prices { margin: 20px 0; }
.payment-prices > div { display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #eee; }
.payment-prices dd { margin: 0; font-weight: bold; white-space: nowrap; }
.payment-prices small { display: block; color: #62574a; margin-top: 6px; }
.payment-total { background: #fff3dc; padding: 18px; border-radius: 10px; }
.payment-total span { display: block; color: #684000; font-size: 14px; }
.payment-total strong { display: block; margin-top: 6px; font: 800 34px Arial,sans-serif; overflow-wrap: anywhere; }
.payment-methods { border: 0; padding: 0; margin: 24px 0; min-width: 0; }
.payment-methods legend { font-weight: bold; margin-bottom: 12px; }
.payment-option { display: flex; align-items: flex-start; gap: 10px; border: 1px solid #ded6cc; border-radius: 8px; padding: 14px; margin-bottom: 10px; }
.payment-option input { margin: 4px 0 0; accent-color: #9b5c00; flex-shrink: 0; }
.payment-option.cash { background: #fffaf0; border: 2px solid #c47c08; cursor: pointer; }
.payment-option small { display: block; margin-top: 5px; line-height: 1.5; color: #62574a; }
.payment-option.unavailable { color: #62574a; background: #f5f4f2; }
.payment-screen .payment-note { font-size: 13px; color: #62574a; line-height: 1.7; }
.payment-screen .payment-note { font-size: 13px; color: #62574a; line-height: 1.7; }
.payment-screen .payment-submit { width: 100%; padding: 16px; background: #e8940a; color: #21180e; border: 0; border-radius: 8px; font: 700 15px Arial,sans-serif; cursor: pointer; line-height: 1.5; }
.payment-submit:disabled { background: #e2e5e9; color: #4c5663; cursor: wait; }
.payment-back { display: inline-block; margin-top: 16px; }
.payment-alert { padding: 18px; margin-bottom: 24px; border-radius: 8px; background: #ffe5df; color: #922d22; }
.payment-steps { padding-left: 22px; margin: 16px 0 0; }
.payment-steps li { padding: 6px 0; line-height: 1.6; }
.payment-screen a:focus-visible, .payment-screen button:focus-visible { outline: 3px solid #684000; outline-offset: 3px; }
@media (max-width: 760px) {
    .payment-layout { grid-template-columns: 1fr; }
    .payment-checkout { position: static; }
    .payment-card { padding: 20px; }
}
@media (max-width: 380px) {
    .payment-meta > div { flex-direction: column; gap: 6px; }
    .payment-meta dd { text-align: left; }
}
</style>

<section class="payment-screen">
    <div class="eyebrow">PAYMENT</div>
    <h2 class="section-title">Complete your reservation</h2>
    <p class="payment-intro">Reserve your seats now and pay in cash at the counter. No payment is collected online.</p>
    <?php if ($error): ?>
        <div class="payment-alert" role="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="payment-layout">
        <div class="payment-details">
            <section class="payment-card" aria-labelledby="payment-trip-title">
                <div class="payment-heading"><h3 id="payment-trip-title">Your trip</h3><a href="fare.php">Review summary</a></div>
                <p class="payment-route"><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></p>
                <dl class="payment-meta">
                    <div><dt>Travel date</dt><dd><?= h(date('D, M j, Y', strtotime($bus['travel_date']))) ?></dd></div>
                    <div><dt>Departure</dt><dd><?= h(date('g:i A', strtotime($bus['departure_time']))) ?></dd></div>
                    <div><dt>Bus</dt><dd><?= h($bus['bus_number']) ?> · <?= h($bus['bus_type']) ?></dd></div>
                    <div><dt>Boarding point</dt><dd><?= h($bus['boarding_point']) ?></dd></div>
                    <div><dt>Passengers</dt><dd><?= count($selectedSeats) ?></dd></div>
                </dl>
                 <p><strong>Selected seats</strong></p>
                <div class="payment-seats">
                    <?php foreach ($selectedSeats as $seat): ?><span class="payment-seat"><?= h($seat) ?></span><?php endforeach; ?>
                </div>
            </section>

            <section class="payment-card" aria-labelledby="payment-contact-title">
                <div class="payment-heading"><h3 id="payment-contact-title">Primary contact</h3><a href="passenger.php">Edit details</a></div>
                <dl class="payment-meta">
                    <div><dt>Name</dt><dd><?= h($passenger['name']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= h($passenger['email']) ?></dd></div>
                    <div><dt>Phone</dt><dd><?= h($passenger['phone']) ?></dd></div>
                </dl>
            </section>

            <section class="payment-card" aria-labelledby="payment-next-title">
                <h3 id="payment-next-title">What happens next?</h3>
                <ol class="payment-steps">
                    <li>Your reservation is saved as <strong>Pending payment</strong>.</li>
                    <li>Keep the booking reference shown on the next page.</li>
                    <li>Present your reference and pay at the counter. Staff will confirm the payment.</li>
                </ol>
            </section>
        </div>

        <aside class="payment-card payment-checkout" aria-labelledby="payment-price-title">
            <h3 id="payment-price-title">Payment summary</h3>
            <dl class="payment-prices">
                <div><dt>Base fare<small>₱<?= number_format((float) $bus['fare_per_seat'], 2) ?> × <?= count($selectedSeats) ?> seat(s)</small></dt><dd>₱<?= number_format($base, 2) ?></dd></div>
                <div><dt>Service fee (5%)</dt><dd>₱<?= number_format($serviceFee, 2) ?></dd></div>
                <div><dt>Tax (8%)</dt><dd>₱<?= number_format($tax, 2) ?></dd></div>
            </dl>
            <div class="payment-total"><span>Total to pay at the counter</span><strong>₱<?= number_format($total, 2) ?></strong></div>
            <p class="payment-note">Service fee and tax are calculated from the base fare and rounded to the nearest peso.</p>

            <form method="post" id="counter-payment-form">
                <input type="hidden" name="checkout_token" value="<?= h($_SESSION['checkout_token']) ?>">
                <fieldset class="payment-methods">
                    <legend>Payment method</legend>
                    <label class="payment-option cash">
                        <input type="radio" name="payment_method" value="Cash" required checked>
                        <span><strong>Pay at counter</strong><small>Cash payment. Your booking stays pending until staff confirm payment.</small></span>
                    </label>
                    <label class="payment-option unavailable">
                        <input type="radio" name="payment_method" value="GCash" disabled>
                        <span>GCash<small>Currently unavailable</small></span>
                    </label>
                    <label class="payment-option unavailable">
                        <input type="radio" name="payment_method" value="Card" disabled>
                        <span>Credit / debit card<small>Currently unavailable</small></span>
                    </label>
                </fieldset>
                <button type="submit" class="payment-submit" id="counter-reserve-button">Reserve — pay at counter →</button>
                <p class="payment-note" id="counter-submit-status" role="status">Submitting creates your reservation. It does not mark it as paid.</p>
            </form>
            <a class="payment-back" href="fare.php">← Back to fare summary</a>
        </aside>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('counter-payment-form');
    const button = document.getElementById('counter-reserve-button');
    const status = document.getElementById('counter-submit-status');
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true;
        button.disabled = true;
        button.textContent = 'Saving your reservation…';
        status.textContent = 'Please wait while your seats are checked and the reservation is saved.';
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        button.disabled = false;
        button.textContent = 'Reserve — pay at counter →';
        status.textContent = 'Submitting creates your reservation. It does not mark it as paid.';
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>