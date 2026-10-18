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
if (!is_array($selectedSeats) || !$selectedSeats
    || count($selectedSeats) !== (int) $booking['passengers']
    || count(array_filter($selectedSeats, 'is_string')) !== count($selectedSeats)) {
    redirect('seat.php');
}
$passenger = $booking['passenger'] ?? [];
foreach (['name', 'email', 'phone'] as $field) {
    if (!is_array($passenger) || !is_string($passenger[$field] ?? null)
        || trim($passenger[$field]) === '') {
        redirect('passenger.php');
    }
}
$seatCount = count($selectedSeats);
$farePerSeat = (float) $bus['fare_per_seat'];
[$baseFare, $serviceFee, $tax, $total] = fare_breakdown($farePerSeat, $seatCount);
$zone = new DateTimeZone('Asia/Manila');
$departure = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['departure_time'], $zone);
$arrival = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['arrival_time'], $zone);
if ($arrival < $departure) {
    $arrival = $arrival->modify('+1 day');
}
$pageTitle = 'Fare Summary';
$activeStep = 'fare';
require __DIR__ . '/../includes/header.php';
?>
<style>
.fare-review { max-width: 1050px; margin: 0 auto 48px; color: #21180e; }
.fare-review .review-intro { margin: 12px 0 26px; color: #62574a; line-height: 1.7; }
.review-layout { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(280px,.8fr); gap: 24px; align-items: start; }
.review-details { display: grid; gap: 24px; }
.review-card { background: #fff; padding: 28px; border: 1px solid #ded6cc; border-radius: 16px; min-width: 0; }
.review-heading { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
.fare-review h3 { margin: 0; font-size: 22px; }
.fare-review a { color: #684000; }
.review-heading a { font-size: 14px; }
.review-route { font-size: 24px; line-height: 1.4; font-weight: bold; margin: 0 0 20px; }
.review-meta { margin: 0; }
.review-meta > div { display: flex; justify-content: space-between; gap: 18px; padding: 12px 0; border-bottom: 1px solid #eee; }
.review-meta dt { color: #62574a; flex-shrink: 0; }
.review-meta dd { margin: 0; text-align: right; overflow-wrap: anywhere; min-width: 0; }
.review-seat-heading { margin: 22px 0 12px; font-weight: bold; }
.review-seats { display: flex; flex-wrap: wrap; gap: 8px; }
.review-seat { padding: 8px 12px; border: 1px solid #e8bd75; background: #fff0d5; color: #684000; border-radius: 6px; font-weight: bold; }
.review-price { position: sticky; top: 24px; border-top: 5px solid #e8940a; }
.review-price dl { margin: 24px 0; }
.review-price dl > div { display: flex; justify-content: space-between; gap: 16px; padding: 14px 0; border-bottom: 1px solid #eee; }
.review-price dd { margin: 0; font-weight: bold; white-space: nowrap; }
.review-price small { display: block; margin-top: 6px; color: #62574a; font-size: 13px; }
.review-total { padding: 20px; border-radius: 10px; background: #fff3dc; }
.review-total span { display: block; font-size: 14px; color: #684000; }
.review-total strong { display: block; margin-top: 6px; font: 800 34px Arial,sans-serif; overflow-wrap: anywhere; }
.fare-review .review-note { font-size: 13px; color: #62574a; line-height: 1.7; margin: 16px 0; }
.fare-review a.review-next { display: block; margin-top: 22px; padding: 16px; text-align: center; text-decoration: none; background: #e8940a; color: #21180e; border-radius: 8px; font-weight: bold; line-height: 1.5; }
.review-back { display: inline-block; margin-top: 18px; }
.fare-review a:focus-visible { outline: 3px solid #684000; outline-offset: 4px; }
@media (max-width: 760px) {
    .review-layout { grid-template-columns: 1fr; }
    .review-price { position: static; }
    .review-card { padding: 20px; }
}
@media (max-width: 380px) {
    .review-meta > div { flex-direction: column; gap: 6px; }
    .review-meta dd { text-align: left; }
}
</style>

<section class="fare-review">
    <div class="eyebrow">FARE SUMMARY</div>
    <h2 class="section-title">Review your booking</h2>
    <p class="review-intro">Check your trip, contact details and total before continuing.</p>

    <div class="review-layout">
        <div class="review-details">
            <section class="review-card" aria-labelledby="review-trip-title">
                <div class="review-heading"><h3 id="review-trip-title">Your trip</h3></div>
                <p class="review-route"><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></p>
                <dl class="review-meta">
                    <div><dt>Travel date</dt><dd><?= h($departure->format('l, M j, Y')) ?></dd></div>
                    <div><dt>Departure</dt><dd><?= h($departure->format('g:i A')) ?></dd></div>
                    <div><dt>Scheduled arrival</dt><dd><?= h($arrival->format('g:i A')) ?><?= $arrival->format('Y-m-d') !== $departure->format('Y-m-d') ? ' (next day)' : '' ?></dd></div>
                    <div><dt>Bus</dt><dd><?= h($bus['bus_number']) ?> · <?= h($bus['bus_type']) ?></dd></div>
                    <div><dt>Boarding point</dt><dd><?= h($bus['boarding_point']) ?></dd></div>
                    <div><dt>Passengers</dt><dd><?= $seatCount ?></dd></div>
                </dl>
                <div class="review-heading review-seat-heading">
                    <span>Selected seats</span><a href="seat.php">Change seats</a>
                </div>
                <div class="review-seats">
                    <?php foreach ($selectedSeats as $seat): ?>
                        <span class="review-seat"><?= h($seat) ?></span>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="review-card" aria-labelledby="review-contact-title">
                <div class="review-heading">
                    <h3 id="review-contact-title">Primary contact</h3>
                    <a href="passenger.php">Edit details</a>
                </div>
                <dl class="review-meta">
                    <div><dt>Full name</dt><dd><?= h($passenger['name']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= h($passenger['email']) ?></dd></div>
                    <div><dt>Phone</dt><dd><?= h($passenger['phone']) ?></dd></div>
                    <div><dt>Age on travel date</dt><dd><?= h((string) ($passenger['age'] ?? '') !== '' ? (string) $passenger['age'] : 'Not provided') ?></dd></div>
                    <div><dt>Gender</dt><dd><?= h(($passenger['gender'] ?? '') !== '' ? $passenger['gender'] : 'Not provided') ?></dd></div>
                </dl>
                <?php if ($seatCount > 1): ?>
                    <p class="review-note">This is the contact person for all <?= $seatCount ?> selected seats.</p>
                <?php endif; ?>
            </section>
        </div>

        <aside class="review-card review-price" aria-labelledby="review-price-title">
            <h3 id="review-price-title">Fare breakdown</h3>
            <dl>
                <div>
                    <dt>Base fare<small>₱<?= number_format($farePerSeat, 2) ?> × <?= $seatCount ?> seat<?= $seatCount === 1 ? '' : 's' ?></small></dt>
                    <dd>₱<?= number_format($baseFare, 2) ?></dd>
                </div>
                <div><dt>Service fee (5%)</dt><dd>₱<?= number_format($serviceFee, 2) ?></dd></div>
                 <div><dt>Tax (8%)</dt><dd>₱<?= number_format($tax, 2) ?></dd></div>
            </dl>
            <div class="review-total">
                <span>Total for <?= $seatCount ?> passenger<?= $seatCount === 1 ? '' : 's' ?></span>
                <strong>₱<?= number_format($total, 2) ?></strong>
            </div>
            <p class="review-note">Service fee and tax are calculated from the base fare and rounded to the nearest peso.</p>
            <a class="review-next" href="payment.php">Continue to payment →</a>
            <p class="review-note">No payment has been collected yet. Seat availability is checked again when you complete your reservation.</p>
            <a class="review-back" href="passenger.php">← Back to passenger details</a>
        </aside>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
