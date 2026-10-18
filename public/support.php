<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Help & Support';
require __DIR__ . '/../includes/header.php';
?>
<style>
.bw-help {
    max-width: 1000px;
    margin: 40px auto 70px;
    padding: 0 24px;
    color: #f5f5f5;
}

.bw-help-hero {
    padding: 36px;
    margin-bottom: 28px;
    border-left: 5px solid #ff9d00;
    border-radius: 16px;
    background: #150e09;
}

.bw-help .eyebrow,
.bw-help-label {
    color: #ff9d00;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 2px;
}

.bw-help h1 {
    margin: 14px 0;
    color: #fff;
    font-size: clamp(30px, 5vw, 44px);
}

.bw-help h2 {
    margin: 12px 0 18px;
    color: #ff9d00;
    font-size: 25px;
}

.bw-help p,
.bw-help li {
    color: #ded8d2;
    line-height: 1.8;
}

.bw-help article {
    padding: 28px;
    margin: 20px 0;
    border: 1px solid #393029;
    border-radius: 14px;
    background: #1a1410;
    scroll-margin-top: 24px;
}

.bw-help article:target {
    border-color: #ff9d00;
}

.bw-help a {
    color: #ffc15c;
    text-underline-offset: 4px;
}

.bw-help a:hover {
    color: #fff;
}

.bw-help-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.bw-help-hero .bw-help-links {
    margin-top: 24px;
}

.bw-help-hero .bw-help-links a {
    padding: 11px 18px;
    border: 1px solid #765324;
    border-radius: 10px;
    background: #251a10;
    text-decoration: none;
}

.bw-help-hero .bw-help-links a:hover {
    background: #ff9d00;
    color: #150e09;
}

.bw-help-note {
    padding: 18px 20px;
    border-left: 3px solid #ff9d00;
    border-radius: 0 10px 10px 0;
    background: #251a10;
}

.bw-help-note ul {
    margin-bottom: 0;
    padding-left: 22px;
}

.bw-faq {
    padding: 18px 0;
    border-bottom: 1px solid #393029;
}

.bw-faq:last-child {
    border-bottom: 0;
}

.bw-faq summary {
    color: #f5f5f5;
    font-weight: 600;
    line-height: 1.6;
    cursor: pointer;
}

.bw-faq summary::marker {
    color: #ff9d00;
}

.bw-faq[open] summary,
.bw-faq summary:hover {
    color: #ffc15c;
}

.bw-faq p {
    margin: 12px 0;
}

.bw-help-steps {
    padding-left: 24px;
}

.bw-help-steps li {
    padding-left: 6px;
    margin-bottom: 16px;
}

.bw-help-steps li::marker {
    color: #ff9d00;
    font-weight: 700;
}

.bw-help a:focus-visible,
.bw-help summary:focus-visible {
    outline: 3px solid #ff9d00;
    outline-offset: 5px;
}

@media (max-width: 600px) {
    .bw-help {
        margin-top: 24px;
        padding: 0 16px;
    }

    .bw-help-hero,
    .bw-help article {
        padding: 24px 20px;
    }
}
</style>


<section class="bw-help">
  <div class="bw-help-hero">
    <div class="eyebrow">PASSENGER SUPPORT</div>
    <h1>How can we help?</h1>
    <p>
        Find answers about your reservation, get help with your
        journey, or learn what to do if you left something behind.
    </p>

    <nav class="bw-help-links" aria-label="Support topics">
        <a href="#contact">Contact Us</a>
        <a href="#faqs">FAQs</a>
        <a href="#lost-found">Lost &amp; Found</a>
    </nav>
</div>

<article id="contact">
    <span class="bw-help-label">CONTACT US</span>
    <h2>Help with your booking</h2>
    <p>
        For assistance with a reservation or payment, speak with
        staff at your boarding terminal.
    </p>

    <div class="bw-help-note">
        <strong>Have these details ready</strong>
        <ul>
            <li>Your booking reference</li>
            <li>The name used for the reservation</li>
            <li>Your route and travel date</li>
        </ul>
    </div>

    <p>
        For departure changes or boarding questions, ask the
        operator or terminal handling your trip.
    </p>

    <a href="cancellation.php">Find or cancel a reservation →</a>
</article>

<article id="faqs">
    <span class="bw-help-label">QUICK ANSWERS</span>
    <h2>Frequently asked questions</h2>

    <details class="bw-faq">
        <summary>How do I reserve a seat?</summary>
        <p>
            Search for your route and travel date, choose a bus,
            select your seats, and enter your passenger details.
            Review the fare and complete the reservation.
        </p>
    </details>

    <details class="bw-faq">
        <summary>Why does my ticket say “Pending”?</summary>
        <p>
            Your payment has not yet been confirmed. For a cash
            reservation, bring your booking reference to the counter.
            Staff will confirm payment after receiving it.
        </p>
    </details>

    <details class="bw-faq">
        <summary>Does selecting a seat reserve it immediately?</summary>
        <p>
            No. Seat availability is checked again when you finish
            your reservation. If a seat becomes unavailable, you
            will need to select another one.
        </p>
    </details>

    <details class="bw-faq">
        <summary>What if there are no buses for my chosen date?</summary>
        <p>
            Browse other dates or review the alternative trips
            shown in your search results.
        </p>
        <a href="browse.php">Browse schedules →</a>
    </details>

    <details class="bw-faq">
        <summary>How can I cancel my reservation?</summary>
        <p>
            Open the cancellation page and enter your booking
            reference and booking email. Review your reservation
            and the cancellation information before confirming.
        </p>
        <a href="cancellation.php">Manage reservation →</a>
    </details>

    <details class="bw-faq">
        <summary>Does cancelling automatically issue a refund?</summary>
        <p>
            Cancelling a reservation does not automatically issue
            a refund. Review the refund policy and ask staff about
            your payment.
        </p>
        <a href="refund.php">Read refund policy →</a>
    </details>
</article>

<article id="lost-found">
    <span class="bw-help-label">LOST &amp; FOUND</span>
    <h2>Left something on the bus?</h2>
    <p>
        Contact the operator or speak with staff at your arrival
        or boarding terminal as soon as possible.
    </p>

    <ol class="bw-help-steps">
        <li>
            <strong>Identify your trip.</strong>
            Prepare your route, travel date, departure time,
            bus number and seat number.
        </li>
        <li>
            <strong>Describe the item.</strong>
            Include its colour, brand, distinguishing features
            and where you last saw it.
        </li>
        <li>
            <strong>Ask about collection.</strong>
            Staff can explain how to identify and collect an
            item if it has been found.
        </li>
    </ol>

    <p class="bw-help-note">
        This page provides guidance. It does not submit a lost-item
        report. Please contact terminal or operator staff directly.
    </p>
</article>

