<?php
define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';

require_booking_keys(
    [
        'travel_date',
        'schedule_id',
        'selected_seats',
        'passenger'
    ],
    'index.php'
);


$scheduleId = (int) $_SESSION['booking']['schedule_id'];

$bus = get_schedule($scheduleId);


if (!$bus) {
    redirect('routes.php');
}


$selectedSeats = $_SESSION['booking']['selected_seats'];

$passenger = $_SESSION['booking']['passenger'];


$fare = fare_breakdown(
    (float)$bus['fare_per_seat'],
    count($selectedSeats)
);


[$base, $serviceFee, $tax, $total] = $fare;



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $_SESSION['booking']['payment_method'] = $_POST['payment_method'];

    $_SESSION['booking']['payment_status'] = 'PAID';


    redirect('ticket.php');

}



$pageTitle = 'Payment';

$activeStep = 'payment';


require __DIR__ . '/../includes/header.php';

?>


<div class="max-w-2xl">

<div class="section-head">

<div class="eyebrow">
PAYMENT
</div>

<h2 class="section-title">
PAYMENT SUMMARY
</h2>

</div>



<div class="card mb-6">


<h3>
Booking Details
</h3>


<p>
Bus <?= h($bus['bus_number']) ?>
</p>


<p>
<?= h($bus['origin']) ?> → <?= h($bus['destination']) ?>
</p>


<p>
Seats:
<?= h(implode(', ', $selectedSeats)) ?>
</p>


</div>




<div class="card mb-6">


<h3>
Passenger
</h3>


<p>
Name:
<?= h($passenger['name']) ?>
</p>


<p>
Email:
<?= h($passenger['email']) ?>
</p>


<p>
Phone:
<?= h($passenger['phone']) ?>
</p>


</div>




<div class="card mb-6">


<h3>
Fare Breakdown
</h3>


<p>
Base Fare:
<?= peso($base) ?>
</p>


<p>
Service Fee:
<?= peso($serviceFee) ?>
</p>


<p>
Tax:
<?= peso($tax) ?>
</p>


<hr>


<h3>
TOTAL:
<?= peso($total) ?>
</h3>


</div>




<div class="card mb-6">


<form method="POST">


<h3>
Select Payment Method
</h3>


<label>
<input type="radio" name="payment_method" value="Cash" required>
Cash
</label>


<br>


<label>
<input type="radio" name="payment_method" value="GCash">
GCash
</label>


<br>


<label>
<input type="radio" name="payment_method" value="Card">
Card
</label>



<br><br>


<button class="btn-primary">
PAY NOW →
</button>


</form>


</div>


</div>


<?php require __DIR__ . '/../includes/footer.php'; ?>