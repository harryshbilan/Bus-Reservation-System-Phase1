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
require __DIR__ . '/../includes/header.php';
?>

<style>
.seat-screen { max-width: 1050px; margin: 0 auto 48px; color: #21180e; }
.seat-screen .seat-trip { margin: 12px 0 24px; line-height: 1.7; color: #62574a; }
.seat-workspace { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr); gap: 24px; align-items: start; }
.seat-map-card, .seat-summary-card { padding: 26px; background: #fff; border: 1px solid #ded6cc; border-radius: 16px; }
.seat-screen h3 { margin: 0 0 12px; }
.seat-key { display: flex; flex-wrap: wrap; gap: 12px 18px; margin: 18px 0 26px; font-size: 13px; }
.seat-key span { display: inline-flex; align-items: center; gap: 7px; }
.seat-key i { width: 18px; height: 18px; border: 2px solid #8b7555; border-radius: 5px; background: #fffaf0; }
.seat-key .chosen i { background: #e8940a; border-color: #9b5c00; }
.seat-key .unavailable i { background: #e2e5e9; border-color: #a6adb7; }
.coach-shell { max-width: 340px; margin: auto; padding: 20px 16px; background: #f8f6f2; border: 2px solid #cfc5b5; border-radius: 35px 35px 16px 16px; }
.coach-front { display: flex; justify-content: space-between; align-items: center; padding: 8px 0 20px; margin-bottom: 18px; border-bottom: 2px dashed #cfc5b5; font-size: 12px; color: #62574a; }
.coach-driver { padding: 9px 12px; border: 1px solid #bcb09d; border-radius: 8px; color: #21180e; font-weight: bold; background: white; }
.coach-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 26px minmax(0, 1fr) minmax(0, 1fr); gap: 10px 8px; }
.coach-columns { text-align: center; font-size: 11px; color: #62574a; margin-bottom: 12px; }
.coach-seat { position: relative; min-width: 0; cursor: pointer; }
.coach-seat input { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; }
.coach-seat-face { display: flex; min-height: 48px; align-items: center; justify-content: center; flex-direction: column; gap: 1px; border: 2px solid #8b7555; border-bottom-width: 5px; border-radius: 9px; background: #fffaf0; color: #352514; font: 700 14px Arial, sans-serif; transition: background .12s; }
.coach-seat-face small { font: 700 9px Arial, sans-serif; }.coach-seat input:not(:disabled):not(:checked) + .coach-seat-face small { display: none; }
.coach-seat input:checked + .coach-seat-face { background: #e8940a; border-color: #9b5c00; color: #241500; }
.coach-seat input:disabled + .coach-seat-face { background: #e2e5e9; border-color: #a6adb7; color: #4c5663; cursor: not-allowed; }
.coach-seat input:focus-visible + .coach-seat-face { outline: 3px solid #1760ac; outline-offset: 3px; }
.coach-seat:hover input:not(:disabled):not(:checked) + .coach-seat-face { background: #ffe6b6; }
.seat-screen .seat-summary-card { position: sticky; top: 24px; }
.seat-summary-card p { line-height: 1.6; }
.seat-summary-card dl > div { display: flex; justify-content: space-between; gap: 16px; padding: 12px 0; border-bottom: 1px solid #eee; }
.seat-summary-card dd { margin: 0; font-weight: bold; text-align: right; }
.seat-selection-status { padding: 16px; border-radius: 8px; background: #fff3dc; margin: 20px 0; }
.seat-selection-status strong { display: block; margin-top: 8px; }
.seat-screen .seat-continue { display: block; width: 100%; padding: 15px; border: 0; border-radius: 8px; background: #e8940a; color: #21180e; font: 700 15px Arial, sans-serif; cursor: pointer; }
.seat-screen .seat-continue:disabled { background: #e2e5e9; color: #4c5663; cursor: not-allowed; }
.seat-screen .seat-alert { padding: 16px; margin: 18px 0; background: #ffe5df; color: #8c261b; border-radius: 8px; }
.seat-screen .seat-back { display: inline-block; margin-top: 18px; color: #684000; }
.seat-map-card fieldset { padding: 0; margin: 0; border: 0; min-width: 0; }
.seat-map-card legend { font-size: 20px; font-weight: bold; margin-bottom: 8px; }
.seat-summary-card .seat-note { font-size: 13px; color: #62574a; }
@media (max-width: 760px) {
    .seat-workspace { grid-template-columns: 1fr; }
    .seat-screen .seat-summary-card { position: static; }
    .seat-map-card, .seat-summary-card { padding: 18px; }
}
@media (max-width: 380px) {
    .coach-shell { padding: 18px 8px; }
    .coach-grid { column-gap: 5px; grid-template-columns: minmax(0,1fr) minmax(0,1fr) 18px minmax(0,1fr) minmax(0,1fr); }
}
</style>

<section class="seat-screen">
    <div class="eyebrow">SEAT SELECTION</div>
    <h2 class="section-title">BUS <?= h($bus['bus_number']) ?></h2>
    <p class="seat-trip">
        <?= h($bus['origin']) ?> → <?= h($bus['destination']) ?> ·
        <?= h(date('F j, Y', strtotime($travelDate))) ?> ·
        <?= h(date('g:i A', strtotime($bus['departure_time']))) ?>
    </p>

     <?php if ($error): ?>
        <p class="seat-alert" role="alert"><?= h($error) ?></p>
    <?php endif; ?>
    <?php if ($availableSeats < $passengers): ?>
        <p class="seat-alert" role="alert">This departure no longer has enough seats for your group. <a href="routes.php">Choose another bus.</a></p>
    <?php endif; ?>

    <form method="post" id="coach-form">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['seat_form_token']) ?>">
        <div class="seat-workspace">
            <div class="seat-map-card">
                <fieldset>
                    <legend>Select your seats</legend>
                    <p>Choose <?= $passengers ?> seat<?= $passengers === 1 ? '' : 's' ?>. Tap a selected seat again to remove it.</p>
                    <div class="seat-key" aria-label="Seat legend">
                        <span><i aria-hidden="true"></i>Available</span>
                        <span class="chosen"><i aria-hidden="true"></i>Selected ✓</span>
                        <span class="unavailable"><i aria-hidden="true"></i>Reserved ×</span>
                    </div>
                    <div class="coach-shell">
                        <div class="coach-front"><span class="coach-driver">DRIVER</span><span>FRONT / ENTRY</span></div>
                        <div class="coach-grid coach-columns" aria-hidden="true">
                            <span>A</span><span>B</span><span>Aisle</span><span>C</span><span>D</span>
                        </div>
                        <div class="coach-grid">
                            <?php for ($index = 0; $index < (int) ceil($totalSeats / 4) * 4; $index++): ?>
                                <?php if ($index % 4 === 2): ?><span aria-hidden="true"></span><?php endif; ?>
                                <?php if (!isset($validSeats[$index])): ?>
                                    <span aria-hidden="true"></span>
                                <?php else:
                                    $label = $validSeats[$index];
                                    $booked = in_array($label, $bookedSeats, true);
                                    $checked = in_array($label, $selectedSeats, true);
                                ?>
                                    <label class="coach-seat">
                                    <input
                                     type="checkbox"
                                     name="selected_seats[]"
                                     value="<?= h($label) ?>"
                                     aria-label="Seat <?= h($label) ?><?= $booked ? ', reserved' : '' ?>"
                                    <?= $booked ? 'disabled' : '' ?>
                                    <?= $checked ? 'checked' : '' ?>
                                    >

                                <span class="coach-seat-face">
                                         <?= h($label) ?>
                            <small aria-hidden="true">
                                <?= $booked ? '×' : '✓' ?>
                                   </small>
                            </span>
                                    </label>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    </div>
                </fieldset>
            </div>
            <aside class="seat-summary-card" aria-label="Your seat selection">
                <h3>Your trip</h3>
                <dl>
                    <div><dt>Passengers</dt><dd><?= $passengers ?></dd></div>
                    <div><dt>Seats available</dt><dd><?= $availableSeats ?> / <?= $totalSeats ?></dd></div>
                    <div><dt>Base fare / seat</dt><dd>₱<?= number_format((float) $bus['fare_per_seat'], 2) ?></dd></div>
                </dl>
                <div class="seat-selection-status" aria-live="polite" aria-atomic="true">
                    <span id="coach-count"><?= count($selectedSeats) ?> of <?= $passengers ?> selected</span>
                    <strong id="coach-labels"><?= h($selectedSeats ? implode(', ', $selectedSeats) : 'No seats selected yet') ?></strong>
                </div>
                <p id="coach-message" role="status"></p>
                <button type="submit" class="seat-continue" id="coach-continue"
                    <?= $availableSeats < $passengers ? 'disabled' : '' ?>>Continue to passenger info →</button>
                <p class="seat-note">Selecting seats does not reserve them yet. Availability is checked again when you finish your reservation.</p>
                <a class="seat-back" href="bus.php?schedule_id=<?= $scheduleId ?>">← Back to bus details</a>
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
            ? selected.map(seat => seat.value).join(', ') : 'No seats selected yet';
        next.disabled = selected.length !== maximum;
        next.textContent = selected.length === maximum
            ? 'Continue to passenger info →'
            : `Select ${maximum - selected.length} more seat(s)`;
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
