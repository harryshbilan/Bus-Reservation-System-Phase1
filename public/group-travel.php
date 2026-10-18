<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Group Travel | BUSWAY';
require __DIR__ . '/../includes/header.php';
?>

<style>
.group-travel {
    max-width: 1000px;
    margin: 40px auto 70px;
    padding: 0 24px;
    color: #f5f5f5;
}

.group-travel-hero,
.group-travel-card {
    padding: 32px;
    margin-bottom: 24px;
    border-radius: 16px;
    background: #1a1410;
    border: 1px solid #393029;
    scroll-margin-top: 24px;
}

.group-travel-hero {
    background: #150e09;
    border-left: 5px solid #ff9d00;
}

.group-travel-label {
    color: #ff9d00;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
}

.group-travel h1 {
    margin: 16px 0;
    color: #fff;
    font-size: clamp(30px, 5vw, 44px);
}

.group-travel h2 {
    margin: 12px 0 18px;
    color: #ff9d00;
    font-size: 28px;
}

.group-travel h3 {
    color: #fff;
    font-size: 18px;
}

.group-travel p,
.group-travel li {
    color: #ded8d2;
    line-height: 1.8;
}

.group-travel li {
    margin-bottom: 8px;
}

.group-travel-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.group-travel-actions a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 12px 20px;
    border: 1px solid #ff9d00;
    border-radius: 10px;
    background: #ff9d00;
    color: #150e09;
    font-weight: 600;
    text-decoration: none;
}

.group-travel-actions a:hover {
    background: #ffc15c;
    border-color: #ffc15c;
}

.group-travel-actions a.secondary {
    background: transparent;
    color: #ffc15c;
    border-color: #765324;
}

.group-travel-actions a.secondary:hover {
    background: #352411;
}

.group-travel-note {
    padding: 16px 20px;
    background: #251a10;
    border-left: 3px solid #ff9d00;
    border-radius: 0 10px 10px 0;
}

.group-travel-card:target {
    border-color: #ff9d00;
}

.group-travel a:focus-visible,
.group-travel summary:focus-visible {
    outline: 3px solid #ff9d00;
    outline-offset: 5px;
}

.group-travel details {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #393029;
}

.group-travel summary {
    color: #ffc15c;
    font-weight: 600;
    line-height: 1.6;
    cursor: pointer;
}

@media (max-width: 600px) {
    .group-travel {
        margin-top: 24px;
        padding: 0 16px;
    }

    .group-travel-hero,
    .group-travel-card {
        padding: 24px 20px;
    }

    .group-travel-actions a {
        width: 100%;
    }
}
</style>

<section class="group-travel">
    <div class="group-travel-hero">
        <span class="group-travel-label">TRAVEL TOGETHER</span>
        <h1>More people. Shared journeys.</h1>

        <p>
            Planning a family getaway, school outing or company trip?
            Explore the difference between hiring a whole bus and
            reserving seats on a scheduled departure.
        </p>

        <nav class="group-travel-actions" aria-label="Group travel options">
            <a href="#charter">Explore bus charter</a>
            <a class="secondary" href="#groups">Reserve group seats</a>
        </nav>
    </div>

    <article class="group-travel-card" id="charter">
        <span class="group-travel-label">A WHOLE BUS FOR YOUR GROUP</span>
        <h2>Bus charter</h2>

        <p>
            A charter means arranging a whole bus for your group,
            with an itinerary agreed with the operator. Availability,
            pickup arrangements and pricing need to be confirmed
            directly with staff.
        </p>

        <h3>Prepare your trip details</h3>

        <ul>
            <li>Your preferred travel date and departure time</li>
            <li>Pickup location and destination</li>
            <li>Number of passengers</li>
            <li>Whether you need a one-way or return journey</li>
            <li>Planned stops and any accessibility requirements</li>
        </ul>

        <p class="group-travel-note">
            Online charter requests are not available yet.
            Speak with terminal or operator staff to ask about
            a quotation. Viewing this page does not reserve a bus.
        </p>

        <div class="group-travel-actions">
            <a href="support.php#contact">How to contact staff →</a>
        </div>

        <details>
            <summary>What should I confirm before agreeing to a charter?</summary>
            <p>
                Confirm the itinerary, seating capacity, total price,
                included charges, payment arrangements and cancellation
                terms with the operator.
            </p>
        </details>
    </article>

    <article class="group-travel-card" id="groups">
        <span class="group-travel-label">SEATS ON A SCHEDULED TRIP</span>
        <h2>Group reservations</h2>

        <p>
            Travelling on the same route and departure?
            Choose the number of passengers when searching,
            then select available seats for your group.
        </p>

        <h3>How to book together</h3>

        <ol>
            <li>Choose your route, travel date and passenger count.</li>
            <li>Select a departure with enough available seats.</li>
            <li>Choose your seats and enter the booking contact details.</li>
            <li>Review the fare and finish your reservation.</li>
        </ol>

        <p class="group-travel-note">
            Seats together depend on availability. Selecting seats
            does not reserve them until you complete the reservation.
            A pending cash reservation still requires payment confirmation.
        </p>

        <div class="group-travel-actions">
            <a href="index.php#journey-details">Find a trip for your group →</a>
            <a class="secondary" href="browse.php">Browse other dates</a>
        </div>

        <details>
            <summary>What if there are not enough seats for everyone?</summary>
            <p>
                Try another departure or travel date. For a larger group
                or a custom itinerary, ask operator staff about charter
                availability before making separate reservations.
            </p>
        </details>
    </article>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>