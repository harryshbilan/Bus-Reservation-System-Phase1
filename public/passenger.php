<?php

define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';


require_booking_keys(
    ['travel_date','schedule_id','selected_seats'],
    'index.php'
);


$scheduleId = (int) $_SESSION['booking']['schedule_id'];

$bus = get_schedule($scheduleId);


if(!$bus){
    redirect('routes.php');
}


$selectedSeats = $_SESSION['booking']['selected_seats'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $_SESSION['booking']['passenger'] = [
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone']
    ];

    redirect('fare.php');

}


$pageTitle = 'Passenger Information';
$activeStep = 'details';


require __DIR__ . '/../includes/header.php';

?>


<div class="max-w-2xl">

<div class="section-head">

<div class="eyebrow">
PASSENGER INFO
</div>

<h2 class="section-title">
Passenger Details
</h2>

</div>



<div class="card">


<form method="POST">


<label>
Full Name
</label>

<input 
type="text"
name="name"
required
>


<br><br>


<label>
Email
</label>


<input
type="email"
name="email"
required
>


<br><br>


<label>
Phone Number
</label>


<input
type="text"
name="phone"
required
>


<br><br>


<div>
Selected Seats:

<?php foreach($selectedSeats as $seat): ?>

<span>
<?= h($seat) ?>
</span>

<?php endforeach; ?>

</div>


<br>


<button class="btn-primary">
CONTINUE →
</button>


</form>


</div>


</div>



<?php require __DIR__ . '/../includes/footer.php'; ?>