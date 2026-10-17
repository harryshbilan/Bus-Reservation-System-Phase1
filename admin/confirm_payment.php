<?php
define('BUSWAY_ADMIN', true);
require __DIR__ . '/bootstrap.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Confirm Cash Payment | BUSWAY Admin</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f7f3ee;
            color: #1c1208;
            font-family: Arial, sans-serif;
        }
        main {
            max-width: 720px;
            margin: 40px auto;
            padding: 28px;
            background: white;
            border-top: 5px solid #e8940a;
            border-radius: 8px;
        }
        a { color: #684000; }
        p { line-height: 1.6; overflow-wrap: anywhere; }
        .error { padding: 16px; background: #ffe3df; color: #922d22; }
        .success { padding: 16px; background: #dff3e4; color: #1d6030; }
        .amount { font-size: 26px; font-weight: bold; }
        label { display: block; margin: 24px 0; line-height: 1.6; }
        button {
            padding: 14px 20px;
            border: 0;
            border-radius: 5px;
            background: #e8940a;
            color: #1c1208;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
    </style>
</head>
<body>
<main>
    <a href="reservations.php">← Back to reservations</a>
    <h1>Confirm cash payment</h1>

    <?php if ($success): ?>
        <p class="success" role="status">
            Cash payment recorded successfully.
            Booking <?= admin_h($ticket) ?> is now PAID.
        </p>
    <?php else: ?>
        <?php if ($error): ?>
            <p class="error" role="alert"><?= admin_h($error) ?></p>
        <?php endif; ?>

        <?php if ($review): ?>
            <p><strong>Reference:</strong> <?= admin_h($ticket) ?></p>
            <p><strong>Passenger:</strong> <?= admin_h($details['name']) ?></p>
            <p>
                <strong>Trip:</strong>
                <?= admin_h($details['origin']) ?> →
                <?= admin_h($details['destination']) ?><br>
                Bus <?= admin_h($details['bus_number']) ?> ·
                <?= admin_h($details['travel_date']) ?> at
                <?= admin_h($details['departure_time']) ?>
            </p>
            <p>
                <strong>Seats:</strong>
                <?= admin_h(implode(
                    ', ',
                    array_column($review['reservations'], 'seat_number')
                )) ?>
            </p>
            <p class="amount">
                Cash to collect:
                ₱<?= number_format($review['total_cents'] / 100, 2) ?>
            </p>

            <form method="post">
                <input type="hidden" name="csrf_token"
                       value="<?= admin_h(admin_csrf_token()) ?>">
                <input type="hidden" name="fingerprint"
                       value="<?= admin_h($review['fingerprint']) ?>">

                <label>
                    <input type="checkbox" name="cash_received"
                           value="yes" required>
                    I have received the full cash amount shown above.
                </label>

                <button type="submit">Confirm cash received</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>