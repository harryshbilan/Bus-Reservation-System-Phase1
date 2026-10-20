
<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Discounts & Policies | BUSWAY';
$pageStyles = ['discounts.css'];
require __DIR__ . '/../includes/header.php';
?>

<div class="policy-page">

    <section class="policy-hero">
        <div class="eyebrow">PASSENGER GUIDELINES</div>
        <h1>Discounts &amp; Policies</h1>
        <p>
            BUSWAY Philippines Inc. aims to provide accessible,
            safe, and convenient bus travel. Review our passenger
            discounts, baggage allowance, and child travel rules
            before completing your reservation.
        </p>
        <div class="policy-updated">
            BUSWAY · Passenger Policy
        </div>
    </section>

    <section class="policy-card">
        <h2>1. Statutory Fare Discounts</h2>

        <p>
            Eligible passengers may receive applicable discounts
            in accordance with Philippine laws and regulations.
        </p>

        <h3>Eligible Passengers</h3>
        <ul>
            <li>
                <strong>Senior citizens:</strong>
                Eligible senior citizens may receive the legally
                required discount upon presentation of valid
                identification and compliance with applicable rules.
            </li>
            <li>
                <strong>Persons with Disabilities (PWDs):</strong>
                Eligible PWD passengers may claim the applicable
                statutory discount using a valid PWD identification.
            </li>
            <li>
                <strong>Students:</strong>
                Eligible students may claim the applicable student
                fare discount upon presenting valid student
                identification and satisfying the applicable
                legal requirements.
            </li>
        </ul>

        <div class="policy-note">
            The standard statutory discount is generally 20% of
            the eligible fare for qualified senior citizens and
            PWDs. Qualified students may receive a 20% discount
            on eligible public land transportation fares, subject
            to applicable laws, conditions, and exceptions.
            The discount must be calculated according to the
            rules applicable to the passenger and trip.
        </div>

        <h3>Verification Requirements</h3>
        <ul>
            <li>
                Senior citizens must present valid senior citizen
                identification or other legally accepted proof.
            </li>
            <li>
                PWD passengers must present valid PWD identification
                or other legally accepted proof.
            </li>
            <li>
                Students must present valid student identification
                and any additional proof required by applicable rules.
            </li>
            <li>
                Identification may be checked during booking,
                terminal verification, or boarding when required.
            </li>
        </ul>

        <h3>Non-Transferability</h3>
        <p>
            A personal statutory discount may only be used by the
            eligible passenger. It cannot be transferred to another
            passenger or used to claim another person's entitlement.
        </p>
    </section>

    <section class="policy-card" id="baggage">
        <h2>2. Baggage Allowance &amp; Cargo Rules</h2>

        <h3>Free Hand Luggage</h3>
        <p>
            Each ticketed passenger may bring one hand-luggage item
            weighing up to 7 kg into the passenger cabin, subject
            to available space and applicable safety requirements.
        </p>

        <h3>Checked Baggage</h3>
        <p>
            Each passenger may bring one checked bag weighing up
            to 15 kg in the bus luggage compartment, subject to
            the operator's baggage handling rules.
        </p>

        <h3>Extra Checked Bags</h3>
        <p>
            Need more room? Add extra checked bags of up to 20 kg each
            for ₱120 per bag on the Passenger Info step when you book,
            up to 3 extra bags per passenger.
        </p>

        <h3>Excess and Oversized Baggage</h3>
        <p>
            Items exceeding the stated weight limits or standard
            dimensions, including large boxes and bicycles, may
            require additional freight charges. Charges and
            acceptance must be confirmed at the terminal before
            the item is accepted.
        </p>

        <h3>Prohibited Items</h3>
        <p>
            Flammable materials, explosives, illegal firearms,
            dangerous chemicals, illegal narcotics, and other
            prohibited or unsafe goods are not permitted.
            All baggage remains subject to applicable law and
            transport safety requirements.
        </p>
    </section>

    <section class="policy-card">
        <h2>3. Child &amp; Infant Travel Policy</h2>

        <h3>Infants Under 2 Years</h3>
        <p>
            Under BUSWAY's proposed policy, an infant under two
            years old may travel without a separate seat charge
            when seated on an accompanying adult's lap, provided
            this arrangement complies with applicable safety
            requirements and operator rules.
        </p>

        <h3>Children Aged 2 Years and Above</h3>
        <p>
            Children aged two years and above generally require
            their own seat and ticket. An eligible child may
            receive an applicable student discount if all
            statutory requirements are satisfied.
        </p>
    </section>

    <section class="policy-card">
        <h2>4. Passenger Responsibilities</h2>
        <ul>
            <li>Provide accurate passenger and contact information.</li>
            <li>Present valid discount identification when required.</li>
            <li>Follow baggage restrictions and terminal instructions.</li>
            <li>Arrive early enough to complete check-in before departure.</li>
            <li>Contact BUSWAY support if clarification is needed.</li>
        </ul>
    </section>

    <section class="policy-card">
        <h2>Questions About Our Policies?</h2>
        <p>
            Please contact BUSWAY support or ask terminal staff
            to confirm discount eligibility, baggage charges,
            and travel requirements before departure.
        </p>
        <a href="terms.php" class="btn-primary">
            READ TERMS &amp; CONDITIONS →
        </a>
    </section>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
