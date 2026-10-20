<?php
define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/mailer.php';

require_booking_keys(['ticket_number', 'awaiting_online'], 'payment.php');
header('Cache-Control: no-store');

$ticket = $_SESSION['booking']['ticket_number'];
$method = $_SESSION['booking']['awaiting_online'];

if (!is_string($ticket) || !in_array($method, ['GCash', 'Card'], true)) {
    unset($_SESSION['booking']['awaiting_online']);
    redirect('ticket.php');
}

// Only unpaid rows for this method are payable here.
$stmt = $pdo->prepare("
    SELECT p.amount, r.seat_number,
           GREATEST(0, " . ONLINE_HOLD_MINUTES . " * 60 - TIMESTAMPDIFF(SECOND, r.created_at, NOW())) AS seconds_left
    FROM payments p
    JOIN reservations r ON r.reservation_id = p.reservation_id
    WHERE r.ticket_number = ? AND r.status = 'PENDING'
      AND p.status = 'PENDING' AND p.payment_method = ?
");
$stmt->execute([$ticket, $method]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    // Already paid, cancelled or switched to cash.
    unset($_SESSION['booking']['awaiting_online']);
    redirect('ticket.php');
}

$amount = array_sum(array_map('floatval', array_column($rows, 'amount')));
$seats = array_column($rows, 'seat_number');
$secondsLeft = (int) min(array_column($rows, 'seconds_left'));

$_SESSION['pay_token'] ??= bin2hex(random_bytes(32));

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['pay_token'] ?? '';
    $action = $_POST['action'] ?? 'pay';

    if (!is_string($token) || !hash_equals($_SESSION['pay_token'], $token)) {
        $errors[] = 'That page timed out. Give it another go.';
    } elseif ($action === 'counter') {
        try {
            switch_booking_to_cash($pdo, $ticket);
            unset($_SESSION['booking']['awaiting_online'], $_SESSION['pay_token']);
            redirect('ticket.php');
        } catch (PDOException $e) {
            error_log('BUSWAY switch to cash failed: ' . $e->getMessage());
            $errors[] = 'We couldn\'t switch to cash just now. Please try again.';
        }
    } else {
        $details = [];

        if ($method === 'Card') {
            [$errors, $details] = validate_card_input($_POST);
            $old = ['card_name' => $details['name'], 'card_expiry' => trim((string) ($_POST['card_expiry'] ?? ''))];
        } else {
            $mobile = normalize_ph_mobile((string) ($_POST['gcash_mobile'] ?? ''));
            $old = ['gcash_mobile' => (string) ($_POST['gcash_mobile'] ?? '')];

            if ($mobile === null) {
                $errors[] = 'Enter the mobile number registered to your GCash, like 0917 123 4567.';
            }
            $otp = trim((string) ($_POST['gcash_otp'] ?? ''));

            if (!preg_match('/^\d{6}$/D', $otp)) {
                $errors[] = 'Enter the 6 digit authentication code.';
            }
            $details = ['mobile' => $mobile, 'otp' => $otp];
        }

        if (!$errors) {
            $result = gateway_charge($method, $amount, $details);

            if (!$result['approved']) {
                $errors[] = $result['message'];
            } else {
                try {
                    mark_booking_paid($pdo, $ticket, $method, $result);
                    send_ticket_email($pdo, $ticket);
                    unset($_SESSION['booking']['awaiting_online'], $_SESSION['pay_token']);
                    redirect('ticket.php');
                } catch (DomainException $e) {
                    $errors[] = $e->getMessage();
                } catch (Throwable $e) {
                    error_log('BUSWAY online payment save failed: ' . $e->getMessage());
                    $errors[] = 'Your payment went through, but we couldn\'t save it on our side. '
                        . 'Please show reference ' . $ticket . ' at the counter and we\'ll fix it.';
                }
            }
        }
    }
}

$pageTitle = 'Pay online';
$pageStyles = ['checkout.css'];
$activeStep = 'payment';
require __DIR__ . '/../includes/header.php';
?>


<section class="st-page pay-online">
    <div class="st-head">
        <span class="bw-kicker">Payment</span>
        <h1 class="bw-display"><?= $method === 'GCash' ? 'Pay with GCash' : 'Pay by card' ?></h1>
    </div>
    <div class="st-card pay-card">
        <p>Seat<?= count($seats) > 1 ? 's' : '' ?> <?= h(implode(', ', $seats)) ?> held under reference <strong><?= h($ticket) ?></strong>.</p>

        <div class="pay-sandbox">
            <strong>Test mode.</strong> No real money moves.
            <?php if ($method === 'Card'): ?>
                Use card <code>4242 4242 4242 4242</code> with any future expiry and any 3 digit code. <code>4000 0000 0000 0002</code> is declined.
            <?php else: ?>
                Use any valid mobile number and the code <code><?= h(GATEWAY_SANDBOX_OTP) ?></code>.
            <?php endif; ?>
        </div>

        <p class="pay-hold">Your seats are held for <strong id="hold-timer" data-seconds="<?= $secondsLeft ?>"><?= ONLINE_HOLD_MINUTES ?> minutes</strong>. After that they go back on sale.</p>

        <div class="pay-amount"><span>Total to pay</span><strong>₱<?= number_format($amount, 2) ?></strong></div>

        <?php if ($errors): ?>
            <div class="st-alert" role="alert">
                <ul><?php foreach ($errors as $message): ?><li><?= h($message) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <form method="post" id="pay-form" autocomplete="off" novalidate>
            <input type="hidden" name="pay_token" value="<?= h($_SESSION['pay_token']) ?>">

            <?php if ($method === 'Card'): ?>
                <div class="st-field">
                    <label for="card_name">Name on card</label>
                    <input id="card_name" name="card_name" type="text" maxlength="60" autocomplete="cc-name" value="<?= h($old['card_name'] ?? '') ?>" required>
                </div>
                <div class="st-field">
                    <label for="card_number">Card number</label>
                    <input id="card_number" name="card_number" type="text" inputmode="numeric" maxlength="23" autocomplete="cc-number" placeholder="1234 5678 9012 3456" required>
                </div>
                <div class="pay-row">
                    <div class="st-field">
                        <label for="card_expiry">Expiry</label>
                        <input id="card_expiry" name="card_expiry" type="text" inputmode="numeric" maxlength="5" autocomplete="cc-exp" placeholder="MM/YY" value="<?= h($old['card_expiry'] ?? '') ?>" required>
                    </div>
                    <div class="st-field">
                        <label for="card_cvv">Security code</label>
                        <input id="card_cvv" name="card_cvv" type="password" inputmode="numeric" maxlength="4" autocomplete="cc-csc" placeholder="123" required>
                    </div>
                </div>
            <?php else: ?>
                <div class="st-field">
                    <label for="gcash_mobile">GCash mobile number</label>
                    <input id="gcash_mobile" name="gcash_mobile" type="tel" inputmode="tel" maxlength="16" autocomplete="tel" placeholder="0917 123 4567" value="<?= h($old['gcash_mobile'] ?? '') ?>" required>
                </div>
                <div class="st-field">
                    <label for="gcash_otp">Authentication code</label>
                    <input id="gcash_otp" name="gcash_otp" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="6 digits" required>
                    <small>In a live setup GCash sends this code to your phone.</small>
                </div>
            <?php endif; ?>

            <button type="submit" class="bw-btn pay-submit" id="pay-button">Pay ₱<?= number_format($amount, 2) ?></button>
            <p class="pay-secure">Card numbers and security codes are never saved.</p>
        </form>

        <form method="post" class="pay-alt">
            <input type="hidden" name="pay_token" value="<?= h($_SESSION['pay_token']) ?>">
            <input type="hidden" name="action" value="counter">
            Prefer to pay in person? <button type="submit">Pay cash at the counter instead</button>
        </form>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('pay-form');
    const button = document.getElementById('pay-button');
    const label = button.textContent;
    let busy = false;

    // Card number groups of four, expiry as MM/YY.
    const number = document.getElementById('card_number');
    if (number) {
        number.addEventListener('input', () => {
            number.value = number.value.replace(/\D/g, '').slice(0, 19).replace(/(.{4})/g, '$1 ').trim();
        });
    }
    const expiry = document.getElementById('card_expiry');
    if (expiry) {
        expiry.addEventListener('input', () => {
            const d = expiry.value.replace(/\D/g, '').slice(0, 4);
            expiry.value = d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
        });
    }

    // Hold countdown.
    const timer = document.getElementById('hold-timer');
    let left = parseInt(timer.dataset.seconds, 10);
    const tick = () => {
        if (left <= 0) { window.location.reload(); return; }
        timer.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0');
        left -= 1;
    };
    tick();
    setInterval(tick, 1000);

    form.addEventListener('submit', event => {
        if (busy) { event.preventDefault(); return; }
        busy = true;
        button.disabled = true;
        button.textContent = 'Processing payment…';
    });
    window.addEventListener('pageshow', () => {
        busy = false;
        button.disabled = false;
        button.textContent = label;
    });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
