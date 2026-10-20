<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$admin = require_admin();
$error = null;
$review = null;

$ticket = $_GET['ticket'] ?? '';

if (!is_string($ticket) || !preg_match('/^BW-[A-F0-9]{1,64}$/D', $ticket)) {
    http_response_code(400);
    exit('Invalid booking reference.');
}

// Read and validate the whole booking.
// During confirmation, lock its records until the transaction finishes.
function load_cash_booking(PDO $pdo, string $ticket, bool $lock): array
{
    $stmt = $pdo->prepare("
        SELECT reservation_id, passenger_id, schedule_id,
               seat_number, status
        FROM reservations
        WHERE ticket_number = ?
        ORDER BY reservation_id
    " . ($lock ? ' FOR UPDATE' : ''));

    $stmt->execute([$ticket]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$reservations) {
        throw new DomainException('This booking was not found.');
    }

    $first = $reservations[0];

    foreach ($reservations as $reservation) {
        if ($reservation['status'] !== 'PENDING') {
            throw new DomainException(
                'This booking is no longer pending. Return to reservations.'
            );
        }

        if (
            $reservation['passenger_id'] != $first['passenger_id'] ||
            $reservation['schedule_id'] != $first['schedule_id']
        ) {
            throw new DomainException(
                'This reference contains inconsistent booking details.'
            );
        }
    }

    $ids = array_column($reservations, 'reservation_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT payment_id, reservation_id, amount, payment_method, status
        FROM payments
        WHERE reservation_id IN ($placeholders)
        ORDER BY payment_id
    " . ($lock ? ' FOR UPDATE' : ''));

    $stmt->execute($ids);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($payments) !== count($reservations)) {
        throw new DomainException(
            'The payment records need checking before confirmation.'
        );
    }

    $seen = [];
    $totalCents = 0;

    foreach ($payments as $payment) {
        $reservationId = $payment['reservation_id'];

        if (isset($seen[$reservationId])) {
            throw new DomainException(
                'A reservation has duplicate payment records.'
            );
        }

        $seen[$reservationId] = true;

        if (
            $payment['status'] !== 'PENDING' ||
            $payment['payment_method'] !== 'Cash'
        ) {
            throw new DomainException(
                'Only pending cash payments can be confirmed here.'
            );
        }

        $amount = (string) $payment['amount'];

        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
            throw new DomainException('A payment amount is invalid.');
        }

        $cents = ((int) $parts[1] * 100)
            + (int) str_pad($parts[2] ?? '', 2, '0');

        if ($cents <= 0) {
            throw new DomainException('A payment amount must be positive.');
        }

        $totalCents += $cents;
    }

    return [
        'reservations' => $reservations,
        'payments' => $payments,
        'total_cents' => $totalCents,
        'fingerprint' => hash(
            'sha256',
            json_encode([$reservations, $payments], JSON_THROW_ON_ERROR)
        ),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_verify_csrf();

    try {
        if (($_POST['cash_received'] ?? '') !== 'yes') {
            throw new DomainException(
                'Confirm that you received the full cash amount.'
            );
        }

        $pdo->beginTransaction();
        $current = load_cash_booking($pdo, $ticket, true);

        $fingerprint = $_POST['fingerprint'] ?? '';

        if (
            !is_string($fingerprint) ||
            !hash_equals($current['fingerprint'], $fingerprint)
        ) {
            throw new DomainException(
                'The booking changed. Review the updated details before confirming.'
            );
        }

        $audit = $pdo->prepare("
            INSERT INTO payment_confirmations
                (payment_id, admin_id, amount)
            VALUES (?, ?, ?)
        ");

        $markPayment = $pdo->prepare("
            UPDATE payments
            SET status = 'PAID'
            WHERE payment_id = ? AND status = 'PENDING'
        ");

        $markReservation = $pdo->prepare("
            UPDATE reservations
            SET status = 'PAID'
            WHERE reservation_id = ? AND status = 'PENDING'
        ");

        foreach ($current['payments'] as $payment) {
            $audit->execute([
                $payment['payment_id'],
                $admin['admin_id'],
                $payment['amount'],
            ]);

            $markPayment->execute([$payment['payment_id']]);

            if ($markPayment->rowCount() !== 1) {
                throw new RuntimeException('Payment update failed.');
            }
        }

        foreach ($current['reservations'] as $reservation) {
            $markReservation->execute([$reservation['reservation_id']]);

            if ($markReservation->rowCount() !== 1) {
                throw new RuntimeException('Reservation update failed.');
            }
        }

        $pdo->commit();

        admin_log($pdo, (int) $admin['admin_id'], 'cash_confirmed', $ticket, 'Cash received: PHP ' . number_format($current['total_cents'] / 100, 2));

        $_SESSION['payment_confirmation_success'] = $ticket;

        header(
            'Location: confirm_payment.php?ticket=' . rawurlencode($ticket),
            true,
            303
        );
        exit;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof DomainException) {
            $error = $exception->getMessage();
        } else {
            error_log('BUSWAY confirmation failed: ' . $exception->getMessage());
            $error = 'Payment could not be confirmed. No changes were saved.';
        }
    }
}

$success = ($_SESSION['payment_confirmation_success'] ?? null) === $ticket;

if ($success) {
    unset($_SESSION['payment_confirmation_success']);
} else {
    try {
        $review = load_cash_booking($pdo, $ticket, false);
        $first = $review['reservations'][0];

        $stmt = $pdo->prepare("
            SELECT p.name, rt.origin, rt.destination,
                   s.travel_date, s.departure_time, b.bus_number
            FROM passengers p
            JOIN schedules s ON s.schedule_id = ?
            JOIN routes rt ON rt.route_id = s.route_id
            JOIN buses b ON b.bus_id = s.bus_id
            WHERE p.passenger_id = ?
        ");

        $stmt->execute([$first['schedule_id'], $first['passenger_id']]);
        $details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$details) {
            throw new DomainException('The trip details could not be found.');
        }
    } catch (Throwable $exception) {
        $review = null;

        if ($exception instanceof DomainException) {
            $error = $exception->getMessage();
        } else {
            error_log('BUSWAY payment review failed: ' . $exception->getMessage());
            $error = 'Payment details could not be loaded.';
        }
    }
}

admin_page_start('Confirm cash payment', 'tickets', $admin);
?>
<p><a href="ticket.php?ticket=<?= rawurlencode($ticket) ?>">← Back to ticket</a></p>
<div class="ad-head">
    <div>
        <span class="ad-kicker">Counter payment</span>
        <h1>Confirm cash payment</h1>
        <p>Only confirm once the full amount is in your hands. This marks the booking paid.</p>
    </div>
</div>

<?php if ($success): ?>
    <p class="notice success" role="status">Cash recorded. Booking <span class="ad-ref"><?= admin_h($ticket) ?></span> is now PAID.</p>
    <p class="ad-actions">
        <a class="button" href="ticket.php?ticket=<?= rawurlencode($ticket) ?>">View ticket</a>
        <a class="button ghost" href="tickets.php?status=PENDING">Next pending ticket</a>
    </p>
<?php else: ?>
    <?php if ($error): ?>
        <p class="error" role="alert"><?= admin_h($error) ?></p>
    <?php endif; ?>

    <?php if ($review): ?>
        <div class="ad-detail">
            <section class="panel">
                <h2>Booking</h2>
                <dl>
                    <div><dt>Reference</dt><dd class="ad-ref"><?= admin_h($ticket) ?></dd></div>
                    <div><dt>Passenger</dt><dd><?= admin_h($details['name']) ?></dd></div>
                    <div><dt>Trip</dt><dd><?= admin_h($details['origin']) ?> → <?= admin_h($details['destination']) ?></dd></div>
                    <div><dt>Bus</dt><dd><?= admin_h($details['bus_number']) ?></dd></div>
                    <div><dt>Departure</dt><dd><?= admin_h(admin_when($details['travel_date'], $details['departure_time'])) ?></dd></div>
                    <div><dt>Seats</dt><dd><?= admin_h(implode(', ', array_column($review['reservations'], 'seat_number'))) ?></dd></div>
                </dl>
            </section>
            <section class="panel">
                <h2>Cash to collect</h2>
                <p style="font: 800 44px var(--ad-display); margin: 8px 0 18px; color: var(--ad-amber-dark)">₱<?= number_format($review['total_cents'] / 100, 2) ?></p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= admin_h(admin_csrf_token()) ?>">
                    <input type="hidden" name="fingerprint" value="<?= admin_h($review['fingerprint']) ?>">
                    <label style="display: flex; gap: 10px; align-items: flex-start; font-weight: 500">
                        <input type="checkbox" name="cash_received" value="yes" required style="margin-top: 4px">
                        I have received the full cash amount shown above.
                    </label>
                    <button type="submit" style="width: 100%; margin-top: 14px">Confirm cash received</button>
                </form>
            </section>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php admin_page_end(); ?>
