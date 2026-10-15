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



require_once __DIR__ . '/../includes/checkout.php';

// Show the existing receipt if this booking was already saved.
if (!empty($_SESSION['booking']['ticket_number'])) {
    redirect('ticket.php');
}

// Create a token to protect the payment form.
$_SESSION['checkout_token'] ??= bin2hex(random_bytes(32));

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['checkout_token'] ?? '';
    $method = $_POST['payment_method'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['checkout_token'], $token)
    ) {
        $error = 'Your payment form has expired. Please try again.';
    } elseif (!is_string($method)) {
        $error = 'Please choose Pay at counter.';
    } else {
        try {
            $receipt = create_counter_reservation(
                $pdo,
                $_SESSION['booking'],
                $method
            );

            $_SESSION['booking']['ticket_number'] =
                $receipt['ticket_number'];

            $_SESSION['booking']['reservation_ids'] =
                $receipt['reservation_ids'];

            $_SESSION['booking']['passenger']['id'] =
                $receipt['passenger_id'];

            unset($_SESSION['checkout_token']);

            redirect('ticket.php');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log(
                'BUSWAY checkout failed: ' . $exception->getMessage()
            );

            $error = 'We could not save your reservation. '
                . 'No payment was taken. Please try again.';
        }
    }
}



$pageTitle = 'Payment';

$activeStep = 'payment';


require __DIR__ . '/../includes/header.php';

?>


<div class="max-w-2xl">

<?php if ($error): ?>
    <div class="error-box" role="alert">
        <?= h($error) ?>
    </div>
<?php endif; ?>

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

<input
    type="hidden"
    name="checkout_token"
    value="<?= h($_SESSION['checkout_token']) ?>"
>

<h3>Select Payment Method</h3>

<p>
    No payment is collected online. Your reservation stays pending
    until payment is confirmed at the counter.
</p>

<label>
    <input
        type="radio"
        name="payment_method"
        value="Cash"
        required
        checked
    >
    Pay at counter (cash)
</label>

<br><br>

<label>
    <input
        type="radio"
        name="payment_method"
        value="GCash"
        disabled
    >
    GCash — coming soon
</label>

<br><br>

<label>
    <input
        type="radio"
        name="payment_method"
        value="Card"
        disabled
    >
    Card — coming soon
</label>

<br><br>

<button type="submit" class="btn-primary">
    RESERVE — PAY AT COUNTER →
</button>


<?php require __DIR__ . '/../includes/footer.php'; ?>