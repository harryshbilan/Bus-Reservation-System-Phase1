<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Refund Policy | BUSWAY';
require __DIR__ . '/../includes/header.php';
?>

<style>
.policy-page {
    max-width: 1000px;
    margin: 40px auto 70px;
    padding: 0 24px;
    color: #f5f5f5;
}
.policy-hero {
    padding: 35px;
    border-radius: 14px;
    background: #150e09;
    border-left: 5px solid #ff9d00;
    margin-bottom: 30px;
}
.policy-hero .eyebrow {
    color: #ff9d00;
    font-weight: 700;
    letter-spacing: 2px;
}
.policy-hero h1 {
    font-size: clamp(28px, 5vw, 42px);
    margin: 12px 0;
}
.policy-card {
    background: #1a1410;
    border: 1px solid #393029;
    border-radius: 12px;
    padding: 26px;
    margin-bottom: 22px;
}
.policy-card h2 { color: #ff9d00; margin-top: 0; }
.policy-card h3 { color: #fff; margin-top: 24px; }
.policy-card p, .policy-card li {
    color: #ded8d2;
    line-height: 1.85;
}
.policy-card li { margin-bottom: 9px; }
.policy-note {
    padding: 14px 18px;
    background: #251a10;
    border-left: 3px solid #ff9d00;
    line-height: 1.8;
    color: #eee;
}
.refund-example {
    width: 100%;
    border-collapse: collapse;
    margin-top: 18px;
}
.refund-example th, .refund-example td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #393029;
}
.refund-example th { color: #ff9d00; }
</style>

<div class="policy-page">

    <section class="policy-hero">
        <div class="eyebrow">CANCELLATIONS &amp; REFUNDS</div>
        <h1>Refund Policy</h1>
        <p>
            Please review the cancellation deadline, applicable
            deductions, and refund processing information before
            requesting cancellation of a BUSWAY reservation.
        </p>
    </section>

    <section class="policy-card">
        <h2>1. Cancellation Rules &amp; Timeline</h2>

        <h3>Two-Hour Cancellation Deadline</h3>
        <p>
            Under the proposed standard BUSWAY policy, passengers
            must submit cancellation requests through the online
            cancellation portal at least two hours before the
            scheduled departure time.
        </p>

        <p>
            The cancellation request is subject to verification
            of the ticket, payment status, departure time, and
            applicable passenger rights.
        </p>

        <h3>Late Cancellation and No-Show</h3>
        <p>
            Requests submitted less than two hours before departure,
            or after the scheduled departure, may be ineligible
            under the standard policy. Any such restriction must
            remain subject to applicable Philippine law and any
            mandatory refund or passenger protection requirements.
        </p>

        <div class="policy-note">
            Submitting a cancellation request does not itself
            confirm that the reservation has been cancelled or
            that a refund has been issued. Wait for a cancellation
            confirmation or receipt.
        </div>
    </section>

    <section class="policy-card">
        <h2>2. Cancellation Fee &amp; Refund Calculation</h2>

        <h3>Standard Cancellation Deduction</h3>
        <p>
            For eligible cancellations under the standard BUSWAY
            policy, the proposed administrative cancellation fee
            is 10% of the eligible original fare paid. The
            remaining 90% is the proposed refund amount, subject
            to applicable law and the circumstances of the trip.
        </p>

        <table class="refund-example">
            <thead>
                <tr>
                    <th>Fare Paid</th>
                    <th>10% Fee</th>
                    <th>90% Refund</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>₱500.00</td>
                    <td>₱50.00</td>
                    <td>₱450.00</td>
                </tr>
                <tr>
                    <td>₱1,000.00</td>
                    <td>₱100.00</td>
                    <td>₱900.00</td>
                </tr>
                <tr>
                    <td>₱1,500.00</td>
                    <td>₱150.00</td>
                    <td>₱1,350.00</td>
                </tr>
            </tbody>
        </table>

        <p>
            These examples illustrate the proposed standard
            calculation. They are not a guarantee of eligibility
            or of the amount payable in every situation.
        </p>

        <h3>Operator-Cancelled Trips</h3>
        <p>
            If BUSWAY cancels a trip or cannot provide the
            booked service, the passenger should be offered the
            remedies required by applicable law and the
            circumstances of the disruption. A standard
            passenger-initiated cancellation fee should not
            automatically be applied to an operator-caused
            cancellation where it would be unlawful or
            inappropriate.
        </p>
    </section>

    <section class="policy-card">
        <h2>3. Refund Processing Time</h2>
        <p>
            BUSWAY's proposed processing target is one to two
            business days after the cancellation and refund have
            been reviewed and approved. The time for funds to
            appear in the passenger's account may depend on the
            original payment provider and its processing rules.
        </p>

        <ul>
            <li>
                Refunds should be returned through the original
                payment channel where supported.
            </li>
            <li>
                GCash, Maya, and card refunds depend on the
                actual payment method and provider.
            </li>
            <li>
                A cash reservation must be handled according to
                the actual amount collected and the operator's
                approved refund procedure.
            </li>
        </ul>

        <div class="policy-note">
            A displayed refund calculation is not proof that
            money has been transferred. A refund is complete
            only when the appropriate payment or refund process
            confirms it.
        </div>
    </section>

    <section class="policy-card">
        <h2>4. Cancellation Receipt</h2>
        <p>
            After a cancellation has been successfully recorded,
            the system should provide a cancellation receipt
            containing:
        </p>

        <ul>
            <li>Ticket or reservation number.</li>
            <li>Original fare paid.</li>
            <li>Cancellation fee, if applicable.</li>
            <li>Calculated refund amount.</li>
            <li>Cancellation date and status.</li>
            <li>Refund processing status, when applicable.</li>
        </ul>

        <p>
            Keep the receipt for reference and contact BUSWAY
            support if the cancellation status or refund amount
            appears incorrect.
        </p>
    </section>

    <section class="policy-card">
        <h2>5. How to Request a Cancellation</h2>
        <ol>
            <li>Open the BUSWAY cancellation page.</li>
            <li>Enter the required ticket or reservation details.</li>
            <li>Review the cancellation terms and calculated amount.</li>
            <li>Submit the request and wait for confirmation.</li>
            <li>Keep the cancellation receipt for tracking.</li>
        </ol>

        <a href="cancellation.php" class="btn-primary">
            GO TO CANCELLATION PAGE →
        </a>
    </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>