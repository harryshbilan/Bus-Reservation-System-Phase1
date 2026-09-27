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
        r.seat_number,
        r.status,
        p.payment_method,
        p.amount
    FROM reservations r
    JOIN payments p 
        ON r.reservation_id = p.reservation_id
    WHERE r.passenger_id = ?
    ORDER BY r.reservation_id DESC
    LIMIT 1
");


$stmt->execute([
    $passenger['id'] ?? 0
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
                <?= h($passenger['name']) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= h($passenger['email']) ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= h($passenger['phone']) ?>
            </p>

        </div>



        <!-- TRIP -->
        <div class="ticket-section">

            <h3>Trip Details</h3>

            <p>
                <strong>Bus:</strong>
                <?= h($bus['bus_name'] ?? 'BUSWAY') ?>
            </p>


            <p>
                <strong>Route:</strong>

                <?= h($bus['origin']) ?>

                →

                <?= h($bus['destination']) ?>

            </p>


            <p>
                <strong>Seats:</strong>

                <?php foreach($selectedSeats as $seat): ?>

                    <?= h($seat) ?>

                <?php endforeach; ?>

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

            <button onclick="window.print()">
                Print Ticket
            </button>


            <a href="index.php">
                Book Another Trip
            </a>

        </div>


    </div>

</div>