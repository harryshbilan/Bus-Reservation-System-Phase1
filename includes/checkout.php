<?php
if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); }

/** Create an unpaid counter reservation. Only verified payment confirmation may mark it PAID. */
function create_counter_reservation(PDO $pdo, array $booking, string $method): array
{
    if ($method !== 'Cash') {
        throw new InvalidArgumentException('Online payments are not available yet. Please choose Pay at counter.');
    }
    $passenger = $booking['passenger'] ?? [];
    foreach (['name' => 100, 'email' => 100, 'phone' => 20] as $field => $limit) {
        if (!is_string($passenger[$field] ?? null) || trim($passenger[$field]) === '' || mb_strlen($passenger[$field]) > $limit) {
            throw new InvalidArgumentException('Please return to Passenger Information and enter valid contact details.');
        }
        $passenger[$field] = trim($passenger[$field]);
    }
    if (!filter_var($passenger['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid passenger email address.');
    }
    $seats = $booking['selected_seats'] ?? [];
    if (!is_array($seats) || !$seats || count($seats) !== (int) ($booking['passengers'] ?? 0)) {
        throw new InvalidArgumentException('Please return to Seat Selection and select the requested number of seats.');
    }
    foreach ($seats as $seat) {
        if (!is_string($seat) || !preg_match('/^[1-9][0-9]*[A-D]$/D', $seat)) {
            throw new InvalidArgumentException('Invalid seat selection. Please select your seats again.');
        }
    }
    if (count(array_unique($seats)) !== count($seats)) {
        throw new InvalidArgumentException('Each selected seat must be different.');
    }

    $pdo->beginTransaction();
    try {
        // Serialize checkouts for this departure before checking occupied seats.
        $stmt = $pdo->prepare('SELECT s.schedule_id, s.travel_date, s.fare, b.total_seats FROM schedules s JOIN buses b ON b.bus_id = s.bus_id WHERE s.schedule_id = ? FOR UPDATE');
        $stmt->execute([(int) ($booking['schedule_id'] ?? 0)]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule || $schedule['travel_date'] !== ($booking['travel_date'] ?? '') || $schedule['travel_date'] < date('Y-m-d')) {
            throw new InvalidArgumentException('This departure does not match your travel date or is no longer available. Please search again.');
        }
        foreach ($seats as $seat) {
            $number = (((int) substr($seat, 0, -1)) - 1) * 4 + strpos('ABCD', substr($seat, -1)) + 1;
            if ($number > (int) $schedule['total_seats']) {
                throw new InvalidArgumentException('A selected seat does not exist on this bus.');
            }
        }
        $stmt = $pdo->prepare("SELECT seat_number FROM reservations WHERE schedule_id = ? AND status IN ('PENDING', 'PAID') FOR UPDATE");
        $stmt->execute([$schedule['schedule_id']]);
        if (array_intersect($seats, $stmt->fetchAll(PDO::FETCH_COLUMN))) {
            throw new InvalidArgumentException('A selected seat has just been reserved. Please choose another seat.');
        }

        $stmt = $pdo->prepare('INSERT INTO passengers (name, email, phone) VALUES (?, ?, ?)');
        $stmt->execute([$passenger['name'], $passenger['email'], $passenger['phone']]);
        $passengerId = (int) $pdo->lastInsertId();
        $ticket = 'BW-' . strtoupper(bin2hex(random_bytes(12)));
        $reservationIds = [];
        [, , , $total] = fare_breakdown((float) $schedule['fare'], count($seats));
        $totalCents = (int) round($total * 100);
        $perSeatCents = intdiv($totalCents, count($seats));
        $reservation = $pdo->prepare("INSERT INTO reservations (ticket_number, passenger_id, schedule_id, seat_number, status) VALUES (?, ?, ?, ?, 'PENDING')");
        $payment = $pdo->prepare("INSERT INTO payments (reservation_id, amount, payment_method, status) VALUES (?, ?, 'Cash', 'PENDING')");
        foreach (array_values($seats) as $index => $seat) {
            $reservation->execute([$ticket, $passengerId, $schedule['schedule_id'], $seat]);
            $id = (int) $pdo->lastInsertId();
            $reservationIds[] = $id;
            $cents = $perSeatCents + ($index < $totalCents % count($seats) ? 1 : 0);
            $payment->execute([$id, number_format($cents / 100, 2, '.', '')]);
        }
        $pdo->commit();
        return ['ticket_number' => $ticket, 'reservation_ids' => $reservationIds, 'passenger_id' => $passengerId];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}