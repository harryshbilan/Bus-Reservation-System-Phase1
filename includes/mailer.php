<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Email the passenger their ticket once payment is confirmed.
 * Uses PHP mail(); on a local XAMPP without a mail server this quietly fails
 * and is logged, so a mail problem never blocks a booking.
 */
function send_ticket_email(PDO $pdo, string $ticket): bool
{
    $stmt = $pdo->prepare("
        SELECT pax.name, pax.email, rt.origin, rt.destination, s.travel_date, s.departure_time,
               rt.boarding_point, b.bus_number, GROUP_CONCAT(r.seat_number ORDER BY r.seat_number SEPARATOR ', ') AS seats,
               (SELECT SUM(p.amount) FROM payments p JOIN reservations r2 ON r2.reservation_id = p.reservation_id
                 WHERE r2.ticket_number = ?) AS total
        FROM reservations r
        JOIN passengers pax ON pax.passenger_id = r.passenger_id
        JOIN schedules s ON s.schedule_id = r.schedule_id
        JOIN routes rt ON rt.route_id = s.route_id
        JOIN buses b ON b.bus_id = s.bus_id
        WHERE r.ticket_number = ? AND r.status = 'PAID'
        GROUP BY pax.name, pax.email, rt.origin, rt.destination, s.travel_date, s.departure_time, rt.boarding_point, b.bus_number
    ");
    $stmt->execute([$ticket, $ticket]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$t || !filter_var($t['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $when = date('D, M j, Y', strtotime($t['travel_date'])) . ' at ' . date('g:i A', strtotime($t['departure_time']));
    $body = "Hi {$t['name']},\r\n\r\n"
        . "You're all set! Your payment went through and your seats are confirmed.\r\n\r\n"
        . "Booking reference: $ticket\r\n"
        . "Trip: {$t['origin']} to {$t['destination']}\r\n"
        . "Departure: $when\r\n"
        . "Boarding point: {$t['boarding_point']}\r\n"
        . "Bus: {$t['bus_number']}\r\n"
        . "Seats: {$t['seats']}\r\n"
        . 'Total paid: PHP ' . number_format((float) $t['total'], 2) . "\r\n\r\n"
        . "Show this reference when you board. Have a safe trip!\r\n\r\nBUSWAY\r\n";

    $headers = "From: BUSWAY <no-reply@busway.local>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $sent = @mail($t['email'], 'Your BUSWAY ticket ' . $ticket, $body, $headers);

    if (!$sent) {
        error_log('BUSWAY ticket email not sent for ' . $ticket);
    }

    return $sent;
}
