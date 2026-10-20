<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Payment gateway for GCash and card payments.
 *
 * This is a SANDBOX gateway: nothing is charged anywhere. It checks the
 * details the way a real gateway would and returns an approve/decline result.
 * To go live, replace the body of gateway_charge() with a call to a provider
 * (e.g. PayMongo) and keep the same return shape.
 *
 * Sandbox test values:
 *   Card 4242 4242 4242 4242 approves, 4000 0000 0000 0002 declines.
 *   GCash code 123456 approves, anything else is rejected.
 */

const GATEWAY_SANDBOX_OTP = '123456';

function luhn_valid(string $digits): bool
{
    $sum = 0;

    foreach (array_reverse(str_split($digits)) as $i => $d) {
        $n = (int) $d;

        if ($i % 2 === 1) {
            $n *= 2;

            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
    }

    return $sum % 10 === 0;
}

function card_brand(string $digits): string
{
    return match (true) {
        $digits[0] === '4' => 'Visa',
        (bool) preg_match('/^(5[1-5]|2[2-7])/', $digits) => 'Mastercard',
        (bool) preg_match('/^3[47]/', $digits) => 'Amex',
        default => 'Card',
    };
}

/** Normalise a PH mobile number to 09XXXXXXXXX, or null if it is not one. */
function normalize_ph_mobile(string $raw): ?string
{
    $n = preg_replace('/[\s\-()]/', '', $raw);

    if (preg_match('/^(?:\+63|63)(9\d{9})$/D', $n, $m)) {
        return '0' . $m[1];
    }

    return preg_match('/^09\d{9}$/D', $n) ? $n : null;
}

/** Validate the card form. Returns [errors, cleaned fields]. */
function validate_card_input(array $in): array
{
    $errors = [];
    $name = trim((string) ($in['card_name'] ?? ''));
    $number = preg_replace('/[\s-]/', '', (string) ($in['card_number'] ?? ''));
    $expiry = trim((string) ($in['card_expiry'] ?? ''));
    $cvv = trim((string) ($in['card_cvv'] ?? ''));

    if ($name === '' || mb_strlen($name) > 60) {
        $errors[] = 'Add the name as it appears on the card.';
    }

    if (!preg_match('/^\d{13,19}$/D', $number) || !luhn_valid($number)) {
        $errors[] = 'That card number doesn\'t look right. Mind checking it?';
    }

    if (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*(\d{2})$/D', $expiry, $m)) {
        $errors[] = 'Add the expiry date as MM/YY.';
    } elseif (date('Ym', mktime(0, 0, 0, (int) $m[1], 1, 2000 + (int) $m[2])) < date('Ym')) {
        $errors[] = 'That card has expired. Try a different one.';
    }

    if (!preg_match('/^\d{3,4}$/D', $cvv)) {
        $errors[] = 'Add the 3 or 4 digit security code on your card.';
    }

    return [$errors, ['name' => $name, 'number' => $number]];
}

/**
 * Charge a payment. $method is 'GCash' or 'Card'; $details holds the already
 * validated fields. Never receives or returns the CVV.
 *
 * @return array{approved: bool, reference: ?string, hint: ?string, message: string}
 */
function gateway_charge(string $method, float $amount, array $details): array
{
    $reference = 'SBX-' . strtoupper(bin2hex(random_bytes(6)));

    if ($method === 'Card') {
        $number = $details['number'];
        $hint = card_brand($number) . ' •••• ' . substr($number, -4);

        if ($number === '4000000000000002') {
            return ['approved' => false, 'reference' => null, 'hint' => $hint,
                'message' => 'Your bank declined that card. Try another card, or pay another way.'];
        }

        return ['approved' => true, 'reference' => $reference, 'hint' => $hint, 'message' => 'Card payment approved.'];
    }

    if ($method === 'GCash') {
        $hint = 'GCash ' . substr($details['mobile'], 0, 4) . '•••' . substr($details['mobile'], -4);

        if (!hash_equals(GATEWAY_SANDBOX_OTP, (string) ($details['otp'] ?? ''))) {
            return ['approved' => false, 'reference' => null, 'hint' => $hint,
                'message' => 'That code isn\'t right. Check it and try again.'];
        }

        return ['approved' => true, 'reference' => $reference, 'hint' => $hint, 'message' => 'GCash payment approved.'];
    }

    return ['approved' => false, 'reference' => null, 'hint' => null, 'message' => 'Unsupported payment method.'];
}

/**
 * Record an approved online payment: marks every payment and reservation of
 * the booking PAID in one transaction. Only touches PENDING rows of $method.
 */
function mark_booking_paid(PDO $pdo, string $ticket, string $method, array $result): void
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT p.payment_id, r.reservation_id FROM payments p
             JOIN reservations r ON r.reservation_id = p.reservation_id
             WHERE r.ticket_number = ? AND r.status = 'PENDING'
               AND p.status = 'PENDING' AND p.payment_method = ? FOR UPDATE"
        );
        $stmt->execute([$ticket, $method]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) {
            throw new DomainException('This booking has already been paid or changed.');
        }
        $pay = $pdo->prepare(
            "UPDATE payments SET status = 'PAID', payment_reference = ?, account_hint = ?, paid_at = NOW()
             WHERE payment_id = ? AND status = 'PENDING'"
        );
        $res = $pdo->prepare("UPDATE reservations SET status = 'PAID' WHERE reservation_id = ? AND status = 'PENDING'");

        foreach ($rows as $row) {
            $pay->execute([$result['reference'], $result['hint'], $row['payment_id']]);
            $res->execute([$row['reservation_id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/** Switch an unpaid online booking to pay-at-counter. */
function switch_booking_to_cash(PDO $pdo, string $ticket): void
{
    $stmt = $pdo->prepare(
        "UPDATE payments p JOIN reservations r ON r.reservation_id = p.reservation_id
         SET p.payment_method = 'Cash'
         WHERE r.ticket_number = ? AND p.status = 'PENDING' AND r.status = 'PENDING'"
    );
    $stmt->execute([$ticket]);
}

/** Minutes an unpaid GCash/card booking keeps its seats before they are released. */
const ONLINE_HOLD_MINUTES = 15;

/** Release seats from online bookings that were never paid within the hold time. */
function expire_stale_online_bookings(PDO $pdo): void
{
    try {
        $pdo->prepare(
            "UPDATE reservations r JOIN payments p ON p.reservation_id = r.reservation_id
             SET r.status = 'CANCELLED', p.status = 'CANCELLED'
             WHERE r.status = 'PENDING' AND p.status = 'PENDING'
               AND p.payment_method IN ('GCash', 'Card')
               AND r.created_at < (NOW() - INTERVAL " . ONLINE_HOLD_MINUTES . " MINUTE)"
        )->execute();
    } catch (PDOException $e) {
        error_log('BUSWAY hold expiry failed: ' . $e->getMessage());
    }
}
