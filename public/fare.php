<?php
define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';

require_booking_keys(
    [
        'travel_date',
        'schedule_id',
        'selected_seats'
    ],
    'index.php'
);


$scheduleId = (int) $_SESSION['booking']['schedule_id'];

$bus = get_schedule($scheduleId);


if (!$bus) {
    redirect('routes.php');
}


$selectedSeats = $_SESSION['booking']['selected_seats'];


$passenger = $_SESSION['booking']['passenger'] ?? [];


$seatCount = count($selectedSeats);


[
    $baseFare,
    $serviceFee,
    $tax,
    $total
] = fare_breakdown(
    (float)$bus['fare_per_seat'],
    $seatCount
);


$pageTitle = "Fare Summary";

$activeStep = "fare";


require __DIR__ . '/../includes/header.php';

?>


<div class="max-w-2xl">


<div class="section-head">

<div class="eyebrow">
FARE SUMMARY
</div>


<h2 class="section-title">
BOOKING SUMMARY
</h2>

</div>



<div class="card mb-6">


<h3>
Bus Details
</h3>


<p>
<strong>
Bus <?= h($bus['bus_number']) ?>
</strong>
</p>


<p>
<?= h($bus['origin']) ?>
→
<?= h($bus['destination']) ?>
</p>


<p>
Travel Date:
<?= h(date('M j, Y', strtotime($_SESSION['booking']['travel_date']))) ?>
</p>


</div>




<div class="card mb-6">


<h3>
Passenger Information
</h3>


<p>
Name:
<?= h($passenger['name'] ?? '') ?>
</p>

<p>
Email:
<?= h($passenger['email'] ?? '') ?>
</p>


<p>
Phone:
<?= h($passenger['phone'] ?? '') ?>
</p>

<p>
Selected Seats:

<?= h(implode(', ', $selectedSeats)) ?>

</p>


</div>



<div class="card mb-6">


<h3>
Fare Breakdown
</h3>



<div class="info-row">
<span>
Base Fare
</span>

<span>
<?= peso($baseFare) ?>
</span>

</div>



<div class="info-row">

<span>
Service Fee
</span>

<span>
<?= peso($serviceFee) ?>
</span>

</div>


<div class="info-row">

<span>
Tax
</span>

<span>
<?= peso($tax) ?>
</span>

</div>


<hr>


<div class="info-row">

<strong>
TOTAL
</strong>


<strong>
<?= peso($total) ?>
</strong>


</div>


</div>



<div class="btn-row">


<a href="passenger.php" class="btn-ghost">
← BACK
</a>


<a href="payment.php" class="btn-primary">
CONTINUE TO PAYMENT →
</a>


</div>


</div>



<?php require __DIR__ . '/../includes/footer.php'; ?>