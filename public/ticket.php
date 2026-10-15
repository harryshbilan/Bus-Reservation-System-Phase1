<?php

define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';


require_booking_keys(
    [
        'schedule_id',
        'selected_seats',
        'ticket_number',
        'passenger'
    ],
    'index.php'
);


$scheduleId = (int) $_SESSION['booking']['schedule_id'];

$bus = get_schedule($scheduleId);

$selectedSeats = $_SESSION['booking']['selected_seats'];

$passenger = $_SESSION['booking']['passenger'];


// Get latest reservation ticket
$stmt = $pdo->prepare("
    SELECT 
        r.ticket_number,
        GROUP_CONCAT(
        r.seat_number
        ORDER BY
        CAST(
            LEFT(r.seat_number, LENGTH(r.seat_number) - 1)
            AS UNSIGNED
        ),
        RIGHT(r.seat_number, 1)
        SEPARATOR ', '
        ) AS seats,
        r.status,
        b.bus_number,
        s.travel_date,
        s.departure_time,
        p.payment_method,
        SUM(p.amount) AS amount,
        pax.name AS passenger_name,
        pax.email AS passenger_email,
        pax.phone AS passenger_phone
    FROM reservations r

    JOIN payments p 
        ON r.reservation_id = p.reservation_id

    JOIN schedules s
        ON r.schedule_id = s.schedule_id

    JOIN buses b
        ON s.bus_id = b.bus_id

    JOIN passengers pax
    ON r.passenger_id = pax.passenger_id

    WHERE r.ticket_number = ?

    GROUP BY 
    r.ticket_number,
    r.status,
    b.bus_number,
    s.travel_date,
    s.departure_time,
    p.payment_method,
    pax.name,
    pax.email,
    pax.phone
    ORDER BY MAX(r.reservation_id) DESC
    LIMIT 1
");

$stmt->execute([
    $_SESSION['booking']['ticket_number']
]);


$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    redirect('index.php');
}

$isPaid = $ticket['status'] === 'PAID';
$isPending = $ticket['status'] === 'PENDING';


$pageTitle = 'Ticket';
$activeStep = 'ticket';

require __DIR__ . '/../includes/header.php';
?>
<div class="ticket-container">

    <div class="ticket-card">

        <!-- HEADER -->
        <div class="ticket-header">
    <h1>
        <?= $isPaid
            ? 'Booking Confirmed!'
            : ($isPending
                ? 'Reservation Pending Payment'
                : 'Reservation Cancelled') ?>
    </h1>

    <p>
        <?= $isPaid
            ? 'Your payment has been recorded.'
            : ($isPending
                ? 'No payment has been collected. Please pay at the counter. This is an unpaid reservation, not a paid boarding ticket.'
                : 'This reservation is no longer active.') ?>
    </p>

    <div class="ticket-status">
        <?= h($ticket['status']) ?>
    </div>
</div>

        <!-- PASSENGER -->
        <div class="ticket-section">

            <h3>Passenger Details</h3>

            <p>
                <strong>Name:</strong>
                <?= h($ticket['passenger_name'] ?? 'N/A') ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= h($ticket['passenger_email'] ?? 'N/A') ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= h($ticket['passenger_phone'] ?? 'N/A') ?>
            </p>

        </div>



        <!-- TRIP -->
        <div class="ticket-section">

            <h3>Trip Details</h3>

            <p>
                <strong>Bus:</strong>
                <?= h($ticket['bus_number'] ?? 'N/A') ?>
            </p>


            <p>
                <strong>Route:</strong>

                <?= h($bus['origin']) ?>

                →

                <?= h($bus['destination']) ?>

            </p>

            <p>
                <strong>Date:</strong>
        <?= h($ticket['travel_date'] ?? 'N/A') ?>
        </p>

    <p>
        <strong>Departure:</strong>
        <?= h($ticket['departure_time'] ?? 'N/A') ?>
    </p>

            <p>
                <strong>Seats:</strong>

                <?= h($ticket['seats'] ?? 'N/A') ?>

            </p>


        </div>



        <!-- PAYMENT -->
    <p>
        <strong>Payment Method:</strong>
        <?= h($ticket['payment_method'] ?? 'N/A') ?>
    </p>

    <p>
        <strong>Base Fare:</strong>
        <?= peso($baseFare) ?>
    </p>

    <p>
        <strong>Service Fee (5%):</strong>
        <?= peso($serviceFee) ?>
    </p>

    <p>
        <strong>Tax (8%):</strong>
        <?= peso($tax) ?>
    </p>

    <hr>

        <p>
    <strong> <?= $isPaid
        ? 'Total Paid:'
            : ($isPending
                ? 'Amount Due:'
                : 'Original Amount:') ?>


    </strong>
    <?= peso($ticket['amount'] ?? 0) ?>
    </p>


        <!-- BUTTONS -->
        <div class="ticket-actions">

            <button onclick="window.print()" class="btn-primary">
                Print Ticket
            </button>


            <a href="index.php">
                Book Another Trip
            </a>

        </div>


    </div>

</div>