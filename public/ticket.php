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

?>
<div class="container">

    <h1 class="mb-4">Booking Confirmed!</h1>


    <div class="card mb-4">

        <h3>Ticket Information</h3>

        <p>
            <strong>Ticket Number:</strong>
            <?= h($ticket['ticket_number'] ?? 'N/A') ?>
        </p>


        <p>
            <strong>Status:</strong>
            <?= h($ticket['status'] ?? 'PAID') ?>
        </p>

    </div>


    <div class="card mb-4">

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



    <div class="card mb-4">

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



    <div class="card mb-4">

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


</div>


<?php require __DIR__ . '/../includes/footer.php'; ?>

require __DIR__ . '/../includes/header.php';

?>