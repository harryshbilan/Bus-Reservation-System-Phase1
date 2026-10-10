<?php

define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';


require_booking_keys(
    [
        'schedule_id',
        'selected_seats',
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
    ORDER BY r.reservation_id DESC
    LIMIT 1
");

$stmt->execute([
    $_SESSION['booking']['ticket_number']
]);


$ticket = $stmt->fetch();


$pageTitle = 'Ticket';
$activeStep = 'ticket';

require __DIR__ . '/../includes/header.php';
?>
<div class="ticket-container">

    <div class="ticket-card">

        <!-- HEADER -->
        <div class="ticket-header">
            <h1>Booking Confirmed!</h1>
            <p>Your reservation has been successfully completed.</p>

            <div class="ticket-status">
                <?= h($ticket['status'] ?? 'PAID') ?>
            </div>
        </div>


        <!-- TICKET INFORMATION -->
        <div class="ticket-section">

            <h3>Ticket Information</h3>

            <p>
                <strong>Ticket Number:</strong>
                <?= h($ticket['ticket_number'] ?? 'N/A') ?>
            </p>

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
        <div class="ticket-section">

            <h3>Payment Details</h3>


            <p>
                <strong>Payment Method:</strong>

                <?= h($ticket['payment_method'] ?? 'N/A') ?>

            </p>


            <p>
                <strong>Total Amount:</strong>

                <?= peso($ticket['amount'] ?? 0) ?>

            </p>


        </div>


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