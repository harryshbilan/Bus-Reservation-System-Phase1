<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

const BAG_FEE = 120;
const MAX_BAGS_PER_PASSENGER = 3;

/** Escape text before printing it into HTML. */
function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Whole-peso amount, e.g. ₱1,243. */
function peso($amount): string
{
    return '₱' . number_format((float) $amount, 0);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Send the visitor back to an earlier step when the booking draft is missing something. */
function require_booking_keys(array $keys, string $fallbackUrl): void
{
    foreach ($keys as $key) {
        if (!isset($_SESSION['booking'][$key]) || $_SESSION['booking'][$key] === '') {
            redirect($fallbackUrl);
        }
    }
}

/** 330 → "5h 30m". */
function format_duration(int $minutes): string
{
    return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
}

/** Returns [base, service fee, tax, total, bag fee]. Extra bags are a flat add-on, not taxed. */
function fare_breakdown(float $farePerSeat, int $seatCount, int $extraBags = 0): array
{
    $base = $farePerSeat * $seatCount;
    $serviceFee = round($base * 0.05);
    $tax = round($base * 0.08);
    $bagFee = max(0, $extraBags) * BAG_FEE;
    $total = $base + $serviceFee + $tax + $bagFee;

    return [$base, $serviceFee, $tax, $total, $bagFee];
}

/** Extra bags saved in the booking draft, kept within 0..3 per passenger. */
function booking_bags(array $booking): int
{
    $bags = filter_var($booking['extra_bags'] ?? 0, FILTER_VALIDATE_INT);
    $max = MAX_BAGS_PER_PASSENGER * max(1, (int) ($booking['passengers'] ?? 1));

    return $bags === false ? 0 : max(0, min($bags, $max));
}

/** Returns [10% cancellation fee, 90% refund]. */
function cancellation_breakdown(float $totalFare): array
{
    return [round($totalFare * 0.1), round($totalFare * 0.9)];
}

/** One booking found by reference and email, with the amount actually paid on it. */
function find_database_reservation(string $ticketNumber, string $email): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            (
                SELECT COALESCE(SUM(CASE WHEN pay_all.status = 'PAID' THEN pay_all.amount ELSE 0 END), 0)
                FROM reservations r_all
                LEFT JOIN payments pay_all ON pay_all.reservation_id = r_all.reservation_id
                WHERE r_all.ticket_number = r.ticket_number
            ) AS total_fare,
            pay.payment_method,
            r.*,
            p.name,
            p.email,
            p.phone,
            s.travel_date,
            s.departure_time,
            b.bus_number,
            CONCAT(rt.origin, ' → ', rt.destination) AS route
        FROM reservations r
        JOIN passengers p ON p.passenger_id = r.passenger_id
        JOIN schedules s ON s.schedule_id = r.schedule_id
        JOIN buses b ON b.bus_id = s.bus_id
        JOIN routes rt ON rt.route_id = s.route_id
        LEFT JOIN payments pay ON pay.reservation_id = r.reservation_id
        WHERE r.ticket_number = ?
          AND p.email = ?
        LIMIT 1
    ");
    $stmt->execute([$ticketNumber, $email]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Cancel every seat on a booking; unpaid payment rows are cancelled with it. */
function cancel_database_reservation(string $ticketNumber): void
{
    global $pdo;

    $pdo->beginTransaction();

    try {
        $pdo->prepare("
            UPDATE reservations
            SET status = 'CANCELLED'
            WHERE ticket_number = ?
              AND status IN ('PENDING', 'PAID')
        ")->execute([$ticketNumber]);

        $pdo->prepare("
            UPDATE payments p
            JOIN reservations r ON r.reservation_id = p.reservation_id
            SET p.status = 'CANCELLED'
            WHERE r.ticket_number = ?
              AND r.status = 'CANCELLED'
              AND p.status = 'PENDING'
        ")->execute([$ticketNumber]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $error;
    }
}
