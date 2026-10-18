<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_booking_keys(['travel_date', 'passengers'], 'index.php');
if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
}
$scheduleId = filter_var($_GET['schedule_id'] ?? $_SESSION['booking']['schedule_id'] ?? null, FILTER_VALIDATE_INT);
$bus = $scheduleId ? get_schedule($scheduleId) : null;
$travelDate = $_SESSION['booking']['travel_date'];
$passengers = (int) $_SESSION['booking']['passengers'];
if (!$bus || $bus['travel_date'] !== $travelDate || $passengers < 1 || $passengers > 40) {
    redirect('routes.php');
}
$zone = new DateTimeZone('Asia/Manila');
$departure = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['departure_time'], $zone);
$arrival = new DateTimeImmutable($bus['travel_date'] . ' ' . $bus['arrival_time'], $zone);
if ($arrival < $departure) { $arrival = $arrival->modify('+1 day'); }
if ($departure <= new DateTimeImmutable('now', $zone)) { redirect('routes.php'); }
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT seat_number) FROM reservations WHERE schedule_id = ? AND status IN ('PENDING', 'PAID')");
$stmt->execute([$scheduleId]);
$availableSeats = max(0, (int) $bus['total_seats'] - (int) $stmt->fetchColumn());
if ($availableSeats < $passengers) { redirect('routes.php'); }
if ((int) ($_SESSION['booking']['schedule_id'] ?? 0) !== $scheduleId) {
    unset($_SESSION['booking']['selected_seats']);
}
$_SESSION['booking']['schedule_id'] = $scheduleId;
$_SESSION['booking']['origin'] = $bus['origin'];
$_SESSION['booking']['destination'] = $bus['destination'];
[$base, $fee, $tax, $total] = fare_breakdown((float) $bus['fare_per_seat'], $passengers);
$amenities = array_filter(array_map('trim', explode(',', $bus['amenities'])));
$pageTitle = 'Bus Selection';
$activeStep = 'bus';
require __DIR__ . '/../includes/header.php';
?>
<style>
.trip-preview { max-width:1050px; margin:0 auto 48px; color:#21180e; }
.trip-preview p { line-height:1.7; }
.trip-preview .tp-intro { color:#62574a; margin:12px 0 26px; }
.tp-layout { display:grid; grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr); gap:24px; align-items:start; }
.tp-card { background:white; border:1px solid #ded6cc; padding:28px; border-radius:16px; min-width:0; }
.tp-card h3 { margin:0 0 16px; font-size:24px; overflow-wrap:anywhere; }
.tp-type { display:inline-block; padding:6px 12px; border-radius:20px; background:#fff0d5; color:#684000; font-weight:bold; margin-bottom:22px; }
.tp-route { font-size:24px; font-weight:bold; margin:0 0 18px; }
.tp-times { display:grid; grid-template-columns:1fr 1fr; gap:16px; background:#f8f6f2; padding:20px; border-radius:10px; }
.tp-times span, .tp-times strong, .tp-times small { display:block; }
.tp-times span, .tp-times small { font-size:13px; color:#62574a; }
.tp-times strong { font:700 26px Arial,sans-serif; margin:8px 0; }
.tp-meta { margin:16px 0 22px; }
.tp-meta > div { display:flex; justify-content:space-between; gap:18px; padding:12px 0; border-bottom:1px solid #eee; }
.tp-meta dt { color:#62574a; }
.tp-meta dd { margin:0; text-align:right; overflow-wrap:anywhere; }
.tp-amenities { display:flex; flex-wrap:wrap; gap:8px; margin-top:12px; }
.tp-amenities span { padding:8px 12px; border-radius:6px; background:#f6f1e9; color:#403323; border:1px solid #ded6cc; }
.tp-price { position:sticky; top:24px; border-top:5px solid #e8940a; }
.tp-price-main { font:800 36px Arial,sans-serif; margin:12px 0 4px; }
.trip-preview .tp-note { font-size:13px; color:#62574a; }
.tp-availability { padding:14px; background:#dff3e4; color:#1d6030; border-radius:8px; margin:22px 0; }
.tp-total { padding:16px; background:#fff3dc; border-radius:8px; }
.tp-total strong { display:block; margin-top:8px; font-size:25px; }
.trip-preview a { color:#684000; }
.trip-preview a.tp-next { display:block; padding:16px; margin-top:22px; background:#e8940a; color:#21180e; border-radius:8px; text-align:center; text-decoration:none; font-weight:bold; }
.tp-back { display:inline-block; margin-top:18px; }
.trip-preview a:focus-visible { outline:3px solid #684000; outline-offset:3px; }
@media(max-width:760px) { .tp-layout { grid-template-columns:1fr; } .tp-price { position:static; } .tp-card { padding:20px; } }
</style>
<section class="trip-preview">
    <div class="eyebrow">BUS SELECTION</div>
    <h2 class="section-title">Review your selected bus</h2>
    <p class="tp-intro">Check the departure and boarding point, then choose your seats.</p>
    <div class="tp-layout">
        <section class="tp-card" aria-label="Selected departure">
           <h3>Bus <?= h($bus['bus_number']) ?></h3>
            <span class="tp-type"><?= h($bus['bus_type']) ?></span>
            <p class="tp-route"><?= h($bus['origin']) ?> → <?= h($bus['destination']) ?></p>
            <div class="tp-times">
                <div><span>Departure</span><strong><?= h($departure->format('g:i A')) ?></strong><small><?= h($departure->format('D, M j, Y')) ?></small></div>
                <div><span>Scheduled arrival</span><strong><?= h($arrival->format('g:i A')) ?></strong><small><?= h($arrival->format('D, M j, Y')) ?></small></div>
            </div>
            <dl class="tp-meta">
                <div><dt>Duration</dt><dd><?= h(format_duration((int) $bus['duration_minutes'])) ?></dd></div>
                <div><dt>Boarding point</dt><dd><?= h($bus['boarding_point']) ?></dd></div>
                <div><dt>Total seats</dt><dd><?= (int) $bus['total_seats'] ?></dd></div>
            </dl>
            <strong>Onboard amenities</strong>
            <div class="tp-amenities">
                <?php foreach ($amenities as $amenity): ?><span><?= h($amenity) ?></span><?php endforeach; ?>
                <?php if (!$amenities): ?><span>No amenities listed</span><?php endif; ?>
            </div>
        </section>
        <aside class="tp-card tp-price" aria-label="Fare and availability">
            <h3>Your fare</h3>
            <div class="tp-price-main">₱<?= number_format((float) $bus['fare_per_seat'], 2) ?></div>
            <span class="tp-note">Base fare per seat</span>
            <div class="tp-availability"><strong><?= $availableSeats ?> seats available</strong> out of <?= (int) $bus['total_seats'] ?></div>
            <div class="tp-total">Estimated total for <?= $passengers ?> passenger<?= $passengers === 1 ? '' : 's' ?><strong>₱<?= number_format($total, 2) ?></strong></div>
            <p class="tp-note">Includes the current service fee and tax. The full breakdown appears before payment.</p>
            <a class="tp-next" href="seat.php">Choose your seats →</a>
            <p class="tp-note">Seats are checked again when your reservation is saved.</p>
            <a class="tp-back" href="routes.php">← Back to search results</a>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
