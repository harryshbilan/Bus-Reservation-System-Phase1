<?php
define('BUSWAY_APP', true);

require __DIR__ . '/../includes/bootstrap.php';

require_booking_keys(['travel_date', 'schedule_id'], 'index.php');


$scheduleId = (int) $_SESSION['booking']['schedule_id'];

$bus = get_schedule($scheduleId);

if (!$bus) {
    redirect('routes.php');
}


$travelDate = $_SESSION['booking']['travel_date'];

$passengers = (int) $_SESSION['booking']['passengers'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $selected = $_POST['selected_seats'] ?? [];

    if (count($selected) != $passengers) {
        echo "<script>alert('Please select $passengers seat(s)');</script>";
    } 
    else {

        $_SESSION['booking']['selected_seats'] = $selected;

        redirect('passenger.php');

    }

}


$availableSeats = available_seat_count(
    (int)$bus['bus_id'],
    (int)$bus['total_seats'],
    $travelDate
);


$selectedSeats = $_SESSION['booking']['selected_seats'] ?? [];


$layout = seat_layout(
    (int)$bus['bus_id'],
    (int)$bus['total_seats'],
    $travelDate
);


$pageTitle = 'Seat Selection';
$activeStep = 'seats';


require __DIR__ . '/../includes/header.php';

?>


<div class="max-w-2xl">


<div class="section-head">

<div class="eyebrow">
SEAT SELECTION
</div>


<h2 class="section-title">
BUS <?= h($bus['bus_number']) ?>
</h2>


</div>



<div class="card mb-6">


<h3>
Select your seats
</h3>


<div style="text-align:center;margin:30px 0;">

<div style="margin-bottom:20px;">
DRIVER
</div>

<?php foreach($layout as $row): ?>

<div style="display:flex;justify-content:center;gap:10px;margin-bottom:10px;">

<?php foreach($row as $seat): ?>

<?php 
if($seat):
?>

<button 
type="button"
class="seat-btn <?= $seat['booked'] ? 'booked':'' ?>"
data-seat="<?= $seat['id'] ?>"
>
<?= h($seat['label']) ?>
</button>

<?php
else:
?>

<div></div>

<?php endif; ?>

<?php endforeach; ?>
</div>


<?php endforeach; ?>


</div>


</div>


<div class="btn-row">

<a class="btn-ghost" href="bus.php?schedule_id=<?= $scheduleId ?>">
← BACK
</a>


<form method="POST">

<input 
type="hidden" 
name="selected_seats[]" 
id="selectedSeats"
>

<button type="submit" name="continue" class="btn-primary">
    CONTINUE →
</button>

</form>


<script>

let selectedSeats = [];

document.querySelectorAll('.seat-btn').forEach(button => {

button.addEventListener('click',()=>{

if(button.classList.contains('booked')){
    return;
}


let seat = button.dataset.seat;


if(button.classList.contains('selected')){

button.classList.remove('selected');

selectedSeats = selectedSeats.filter(
id => id != seat
);


}else{

if(selectedSeats.length >= <?= $passengers ?>){
    alert("You can only select <?= $passengers ?> seat(s)");
    return;
}

selectedSeats.push(seat);

button.classList.add('selected');

}


document.getElementById('selectedSeats').value = selectedSeats;


});


});


</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>