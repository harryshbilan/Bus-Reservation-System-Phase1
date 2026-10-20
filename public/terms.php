<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Terms & Conditions | BUSWAY';
$pageStyles = ['terms.css'];
require __DIR__ . '/../includes/header.php';
?>

<div class="policy-page">

    <section class="policy-hero">
        <div class="eyebrow">TRAVEL AGREEMENT</div>
        <h1>Terms &amp; Conditions</h1>
        <p>
            These terms describe the general rules governing
            reservations and travel with BUSWAY Philippines Inc.
            Please review them before booking.
        </p>
    </section>

    <section class="policy-card">
        <h2>1. Booking &amp; Reservation</h2>

        <h3>Reservation Status</h3>
        <p>
            Submitting a reservation through the BUSWAY website
            does not automatically mean that payment has been
            received. A reservation remains pending until the
            required payment verification has been completed.
            Passengers should check their ticket and reservation
            status before travelling.
        </p>

        <h3>Seat Selection</h3>
        <p>
            Seats are assigned according to availability and
            successful reservation processing. Selecting a seat
            on screen does not guarantee that it is reserved until
            the system confirms the booking.
        </p>
        <p>
            If an operational issue, vehicle substitution, or
            safety requirement makes the original seat unavailable,
            BUSWAY may arrange an appropriate alternative and
            should inform the passenger as soon as reasonably possible.
        </p>

        <h3>Electronic Tickets</h3>
        <p>
            An electronic ticket or reservation record is evidence
            of the booking details shown on it. Passengers should
            present their ticket reference, printed ticket, or
            accessible digital copy and valid identification when
            requested by terminal staff.
        </p>
    </section>

    <section class="policy-card">
        <h2>2. Passenger Obligations</h2>

        <h3>Terminal Arrival</h3>
        <p>
            Passengers should arrive at the designated terminal
            at least 30 minutes before the scheduled departure
            to allow time for check-in and boarding procedures.
        </p>
        <p>
            Passengers who arrive after boarding closes may miss
            their trip. Any no-show or missed-departure outcome
            will be handled under the applicable ticket terms
            and refund rules.
        </p>

        <h3>Accurate Passenger Information</h3>
        <p>
            Passengers are responsible for providing accurate
            names, telephone numbers, email addresses, travel
            dates, and other requested booking details. Errors
            should be reported to BUSWAY support as soon as
            possible.
        </p>

        <h3>Identification and Discount Eligibility</h3>
        <p>
            Passengers claiming a statutory discount must satisfy
            the applicable eligibility rules and present acceptable
            proof when required. BUSWAY may correct a fare where
            the claimed discount cannot lawfully be applied.
        </p>
    </section>

    <section class="policy-card">
        <h2>3. Schedules, Delays &amp; Disruptions</h2>
        <p>
            Published departure and arrival times are estimates
            and may be affected by traffic, weather, road closures,
            mechanical problems, government instructions, and
            other circumstances beyond reasonable operational
            control.
        </p>
        <p>
            If a trip is materially changed or cancelled by the
            operator, passengers should contact BUSWAY for
            information about available alternatives and any
            refund or other remedy required by applicable law.
        </p>
    </section>

    <section class="policy-card">
        <h2>4. Payments &amp; Reservation Security</h2>
        <ul>
            <li>
                A reservation is not considered paid merely
                because a booking reference has been generated.
            </li>
            <li>
                Passengers must follow the payment instructions
                provided for their selected booking.
            </li>
            <li>
                Passengers should keep their ticket reference
                and avoid sharing sensitive booking information
                publicly.
            </li>
            <li>
                BUSWAY staff must verify payments before changing
                a reservation to a paid or confirmed status.
            </li>
        </ul>

        <div class="policy-note">
            A website booking reference is not proof that an
            online payment was successfully processed. Payment
            confirmation must be based on the actual payment
            method and transaction status.
        </div>
    </section>

    <section class="policy-card">
        <h2>5. Cancellations &amp; Refunds</h2>
        <p>
            Cancellation requests are subject to the applicable
            cancellation deadline, fees, refund eligibility, and
            processing rules described in the Refund Policy.
        </p>
        <p>
            Nothing in these terms is intended to remove rights
            that passengers have under applicable Philippine law.
        </p>
        <a href="refund.php" class="btn-primary">
            READ REFUND POLICY →
        </a>
    </section>

    <section class="policy-card">
        <h2>6. Customer Support</h2>
        <p>
            For questions about a booking, schedule change,
            payment, or ticket, passengers should contact BUSWAY
            through its designated support channels or visit
            the terminal before departure.
        </p>
    </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>