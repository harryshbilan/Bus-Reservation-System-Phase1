
<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

/*
|--------------------------------------------------------------------------
| BUSWAY ABOUT PAGE
|--------------------------------------------------------------------------
| Uses the existing bootstrap, database connection, schedule functions,
| header, footer, and booking form from your project.
*/

$pageTitle = 'About BUSWAY Philippines Inc.';

/*
|--------------------------------------------------------------------------
| LOAD ROUTES FROM THE EXISTING DATABASE
|--------------------------------------------------------------------------
| The route finder uses the origin and destination values already stored
| in the schedules returned by get_schedules().
*/

$schedules = [];
$origins = [];
$destinations = [];
$routePairs = [];
$routeError = false;

try {
    $schedules = get_schedules();

    foreach ($schedules as $schedule) {
        $origin = trim((string) ($schedule['origin'] ?? ''));
        $destination = trim((string) ($schedule['destination'] ?? ''));

        if ($origin === '' || $destination === '') {
            continue;
        }

        $origins[$origin] = $origin;
        $destinations[$destination] = $destination;

        $key = strtolower($origin . '|' . $destination);

        $routePairs[$key] = [
            'origin' => $origin,
            'destination' => $destination
        ];
    }

    natcasesort($origins);
    natcasesort($destinations);

    $routePairs = array_values($routePairs);
} catch (Throwable $e) {
    // The informational sections can still display if the database
    // is temporarily unavailable.
    $routeError = true;
}

require __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="../assets/css/about.css">

<div class="bw-about">

    <!-- =====================================================
         HERO SECTION
    ====================================================== -->
    <section class="about-hero">
        <div class="about-hero-content">

            <span class="about-eyebrow">
                ABOUT BUSWAY PHILIPPINES INC.
            </span>

            <h1>
                Every journey<br>
                <span>starts with trust.</span>
            </h1>

            <p>
                Discover who we are, explore our connected destinations,
                find terminal information, and learn about opportunities
                to become part of the BUSWAY team.
            </p>

            <div class="about-hero-actions">
                <a href="#our-story" class="about-btn about-btn-primary">
                    Discover Our Story →
                </a>

                <a href="book.php"
                   class="about-btn about-btn-outline">
                    Book a Trip
                </a>
            </div>

            <div class="about-hero-note">
                SAFE TRAVEL · RELIABLE SERVICE · CONVENIENT BOOKING
            </div>

        </div>

        <div class="about-hero-art" aria-hidden="true">
            <div class="about-road"></div>

            <div class="about-road-label">
                <span>YOUR NEXT DESTINATION</span>
                <strong>Starts here.</strong>
            </div>

            <div class="about-floating-card">
                <span class="about-floating-icon">↗</span>
                <div>
                    <strong>Travel with confidence</strong>
                    <small>Plan your next journey with BUSWAY</small>
                </div>
            </div>
        </div>
    </section>


    <!-- =====================================================
         QUICK NAVIGATION
    ====================================================== -->
    <nav class="about-quick-nav" aria-label="About page sections">

        <a href="#our-story">
            <span>01</span>
            Our Story
        </a>

        <a href="#terminal-directory">
            <span>02</span>
            Terminals
        </a>

        <a href="#route-map">
            <span>03</span>
            Route Map
        </a>

        <a href="#careers">
            <span>04</span>
            Careers
        </a>

    </nav>


    <!-- =====================================================
         1. OUR STORY
    ====================================================== -->
    <section class="about-section" id="our-story">

        <div class="about-section-heading">
            <div>
                <span class="about-eyebrow">01 / OUR STORY</span>
                <h2>Moving people.<br>Connecting possibilities.</h2>
            </div>

            <p>
                BUSWAY Philippines Inc. aims to make intercity and
                provincial travel easier to plan through reliable
                transportation services and convenient online booking.
            </p>
        </div>

        <div class="about-story-grid">

            <article class="about-story-main">
                <span class="about-card-label">WHO WE ARE</span>

                <h3>
                    More than a ride.<br>
                    A better travel experience.
                </h3>

                <p>
                    We believe every journey should begin with clear
                    information, a straightforward booking process,
                    and confidence in the travel arrangements.
                </p>

                <p>
                    Through the BUSWAY reservation platform, passengers
                    can explore available trips, review schedules,
                    select seats, provide passenger details, and
                    manage their reservations.
                </p>

                <a href="book.php"
                   class="about-text-link">
                    Plan your next trip →
                </a>
            </article>

            <article class="about-mission-card">
                <span class="about-card-label">OUR MISSION</span>

                <div class="about-big-symbol" aria-hidden="true">✳</div>

                <h3>Make every journey simpler and more dependable.</h3>

                <p>
                    To support safe, reliable, and convenient intercity
                    and provincial travel through passenger-focused
                    service and accessible booking technology.
                </p>
            </article>

            <article class="about-vision-card">
                <span class="about-card-label">OUR VISION</span>

                <h3>A connected Philippines, one journey at a time.</h3>

                <p>
                    We aspire to provide a trusted travel experience
                    supported by modern service standards, useful
                    digital tools, and a commitment to continuous
                    improvement.
                </p>
            </article>

        </div>


        <div class="about-subheading">
            <span class="about-eyebrow">WHAT GUIDES US</span>
            <h3>Our core values</h3>
            <p>
                The principles that should guide every passenger
                interaction and every part of the travel experience.
            </p>
        </div>

        <div class="about-values-grid">

            <article class="about-value-card">
                <div class="about-value-icon">01</div>
                <h3>Safety First</h3>
                <p>
                    Prioritize passenger well-being, responsible
                    operations, vehicle inspections, and safety
                    procedures.
                </p>
            </article>

            <article class="about-value-card">
                <div class="about-value-icon">02</div>
                <h3>Punctuality</h3>
                <p>
                    Present clear schedules and encourage dependable
                    departure planning and timely communication.
                </p>
            </article>

            <article class="about-value-card">
                <div class="about-value-icon">03</div>
                <h3>Modern Standards</h3>
                <p>
                    Support well-maintained vehicles, quality service,
                    and continuous operational improvement.
                </p>
            </article>

            <article class="about-value-card">
                <div class="about-value-icon">04</div>
                <h3>Accessible Technology</h3>
                <p>
                    Make trip information, route searches, and booking
                    tools straightforward and convenient to use.
                </p>
            </article>

        </div>

    </section>


    <!-- =====================================================
         2. TERMINAL DIRECTORY
    ====================================================== -->
    <section class="about-section about-section-alt"
             id="terminal-directory">

        <div class="about-section-heading">
            <div>
                <span class="about-eyebrow">02 / TERMINAL DIRECTORY</span>
                <h2>Know where<br>your journey begins.</h2>
            </div>

            <p>
                Review terminal information before travelling.
                Confirm your exact boarding location and arrival
                arrangements using your booking confirmation.
            </p>
        </div>

        <div class="about-notice">
            <span class="about-notice-icon">i</span>

            <p>
                <strong>Important:</strong>
                The destinations below are directory examples, not
                verified BUSWAY terminal branches. Official addresses,
                opening hours, and contact numbers must be confirmed
                before this directory is published as company information.
            </p>
        </div>

        <?php
        $terminals = [
            [
                'city' => 'Baguio',
                'region' => 'Luzon',
                'description' => 'Explore routes serving the Cordillera region.',
                'address' => 'Official terminal address to be confirmed.',
                'hours' => 'Confirm operating hours before travelling.',
                'phone' => 'Contact number to be confirmed.'
            ],
            [
                'city' => 'Vigan',
                'region' => 'Luzon',
                'description' => 'Plan a trip to the historic Ilocos region.',
                'address' => 'Official terminal address to be confirmed.',
                'hours' => 'Confirm operating hours before travelling.',
                'phone' => 'Contact number to be confirmed.'
            ],
            [
                'city' => 'Cebu',
                'region' => 'Visayas',
                'description' => 'Explore connections within the Visayas.',
                'address' => 'Official terminal address to be confirmed.',
                'hours' => 'Confirm operating hours before travelling.',
                'phone' => 'Contact number to be confirmed.'
            ],
            [
                'city' => 'Palawan',
                'region' => 'MIMAROPA',
                'description' => 'Check regional travel and transfer arrangements.',
                'address' => 'Official terminal address to be confirmed.',
                'hours' => 'Confirm operating hours before travelling.',
                'phone' => 'Contact number to be confirmed.'
            ],
            [
                'city' => 'Siargao',
                'region' => 'Mindanao',
                'description' => 'Check local transport and onward connections.',
                'address' => 'Official terminal address to be confirmed.',
                'hours' => 'Confirm operating hours before travelling.',
                'phone' => 'Contact number to be confirmed.'
            ],
            [
                'city' => 'Manila',
                'region' => 'Luzon',
                'description' => 'Review your departure details before leaving.',
                'address' => 'Official terminal address to be confirmed.',
                'hours' => 'Confirm operating hours before travelling.',
                'phone' => 'Contact number to be confirmed.'
            ]
        ];
?>

        <div class="about-terminal-grid">

            <?php foreach ($terminals as $terminal): ?>

                <article class="about-terminal-card">

                    <div class="about-terminal-top">
                        <span class="about-terminal-region">
                            <?= h($terminal['region']) ?>
                        </span>

                        <span class="about-terminal-arrow"
                              aria-hidden="true">↗</span>
                    </div>

                    <h3><?= h($terminal['city']) ?></h3>

                    <p class="about-terminal-description">
                        <?= h($terminal['description']) ?>
                    </p>

                    <div class="about-terminal-detail">
                        <span aria-hidden="true">⌖</span>
                        <div>
                            <strong>Address</strong>
                            <p><?= h($terminal['address']) ?></p>
                        </div>
                    </div>

                    <div class="about-terminal-detail">
                        <span aria-hidden="true">◷</span>
                        <div>
                            <strong>Operating hours</strong>
                            <p><?= h($terminal['hours']) ?></p>
                        </div>
                    </div>

                    <div class="about-terminal-detail">
                        <span aria-hidden="true">☎</span>
                        <div>
                            <strong>Contact</strong>
                            <p><?= h($terminal['phone']) ?></p>
                        </div>
                    </div>

                </article>

            <?php endforeach; ?>

        </div>

        <p class="about-small-note">
            A destination appearing in this directory does not
            automatically mean that BUSWAY operates a terminal there.
            Check the available schedules below before making plans.
        </p>

    </section>


    <!-- =====================================================
         3. ROUTE MAP AND ORIGIN / DESTINATION FINDER
    ====================================================== -->
    <section class="about-section" id="route-map">

        <div class="about-section-heading">
            <div>
                <span class="about-eyebrow">03 / ROUTE MAP</span>
                <h2>Find your route.<br>Discover your next stop.</h2>
            </div>

            <p>
                Explore the origin and destination pairs currently
                recorded in the BUSWAY schedule database.
            </p>
        </div>


        <!-- Route finder -->
        <div class="about-route-finder">

            <div class="about-route-finder-heading">
                <span class="about-card-label">ROUTE FINDER</span>
                <h3>Where would you like to go?</h3>
                <p>
                    Choose a departure and destination, then continue
                    to the existing booking page.
                </p>
            </div>

            <?php if ($routeError): ?>

                <div class="about-empty-state">
                    <h4>Routes are temporarily unavailable.</h4>
                    <p>
                        Check your MySQL connection and route data,
                        then reload this page.
                    </p>
                </div>

            <?php elseif (empty($routePairs)): ?>

                <div class="about-empty-state">
                    <h4>No routes have been added yet.</h4>
                    <p>
                        Add valid routes and schedules to your existing
                        database to populate this finder.
                    </p>
                    <a href="book.php"
                       class="about-text-link">
                        Visit the booking page →
                    </a>
                </div>

            <?php else: ?>

                <form action="book.php"
                      method="post"
                      class="about-route-form">

                    <div class="about-route-fields">

                        <div class="about-form-field">
                            <label for="about-origin">
                                Departure city
                            </label>

                            <select id="about-origin"
                                    name="origin"
                                    required>

                                <option value="">Choose origin</option>

                                <?php foreach ($origins as $city): ?>
                                    <option value="<?= h($city) ?>">
                                        <?= h($city) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                        <button type="button"
                                id="about-swap"
                                class="about-swap-button"
                                aria-label="Swap departure and destination">
                            ⇄
                        </button>

                        <div class="about-form-field">
                            <label for="about-destination">
                                Destination
                            </label>

                            <select id="about-destination"
                                    name="destination"
                                    required>

                                <option value="">Choose destination</option>

                                <?php foreach ($destinations as $city): ?>
                                    <option value="<?= h($city) ?>">
                                        <?= h($city) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </div>

                    </div>

                    <div class="about-route-fields about-route-fields-bottom">

                        <div class="about-form-field">
                            <label for="about-date">Travel date</label>

                            <input type="date"
                                   id="about-date"
                                   name="travel_date"
                                   min="<?= h(date('Y-m-d')) ?>"
                                   value="<?= h(date('Y-m-d')) ?>"
                                   required>
                        </div>

                        <div class="about-form-field">
                            <label for="about-passengers">
                                Passengers
                            </label>

                            <select id="about-passengers"
                                    name="passengers"
                                    required>

                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?= $i ?>">
                                        <?= $i ?>
                                        <?= $i === 1 ? 'passenger' : 'passengers' ?>
                                    </option>
                                <?php endfor; ?>

                            </select>
                        </div>

                    </div>

                    <button type="submit" class="about-btn about-btn-primary about-search-button">
                        Search Available Buses →
                    </button>

                    <p class="about-form-note">
                        Available trips depend on your selected date,
                        route, and the schedules stored in the database.
                    </p>

                </form>

            <?php endif; ?>

        </div>


        <!-- Route network visualization -->
        <div class="about-network-heading">
            <div>
                <span class="about-eyebrow">YOUR CONNECTED DESTINATIONS</span>
                <h3>BUSWAY route network</h3>
            </div>

            <span class="about-network-count">
                <?= count($routePairs) ?> recorded route pairs
            </span>
        </div>

        <div class="about-network">

            <div class="about-network-region">
                <span class="about-network-dot"></span>
                <div>
                    <strong>LUZON</strong>
                    <small>North and central destinations</small>
                </div>
            </div>

            <div class="about-network-line"></div>

            <div class="about-network-region">
                <span class="about-network-dot"></span>
                <div>
                    <strong>VISAYAS</strong>
                    <small>Central Philippine destinations</small>
                </div>
            </div>

            <div class="about-network-line"></div>

            <div class="about-network-region">
                <span class="about-network-dot"></span>
                <div>
                    <strong>MINDANAO</strong>
                    <small>Southern destinations</small>
                </div>
            </div>

        </div>

        <p class="about-small-note">
            Regional labels are illustrative, not a confirmation that
            BUSWAY currently connects these regions. The live route
            list below comes from your schedule data. The regional
            graphic is a visual guide, not a geographic map.
        </p>


        <!-- Database-backed route list -->
        <div class="about-route-list">

            <div class="about-route-list-heading">
                <h3>Recorded route pairs</h3>
                <span><?= count($routePairs) ?> routes</span>
            </div>

            <?php if (!empty($routePairs)): ?>

                <div class="about-route-table-wrap">
                    <table class="about-route-table">

                        <thead>
                            <tr>
                                <th>Origin</th>
                                <th>Destination</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($routePairs as $route): ?>

                                <tr>
                                    <td><?= h($route['origin']) ?></td>

                                    <td>
                                        <span class="about-route-arrow">→</span>
                                        <?= h($route['destination']) ?>
                                    </td>

                                    <td>
                                        <button
                                            type="button"
                                            class="about-use-route"
                                            data-origin="<?= h($route['origin']) ?>"
                                            data-destination="<?= h($route['destination']) ?>">
                                            Use route
                                        </button>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php else: ?>

                <p class="about-empty-inline">
                    No route pairs are available to display.
                </p>

            <?php endif; ?>

        </div>

    </section>


    <!-- =====================================================
         4. CAREERS
    ====================================================== -->
    <section class="about-section about-section-alt"
             id="careers">

        <div class="about-section-heading">
            <div>
                <span class="about-eyebrow">04 / CAREERS</span>
                <h2>Build your future<br>with BUSWAY.</h2>
            </div>

            <p>
                A dependable travel experience takes teamwork.
                Explore the types of roles that help support passengers,
                vehicles, terminals, and reservation operations.
            </p>
        </div>

        <div class="about-career-intro">

            <div>
                <span class="about-card-label">GROW WITH US</span>

                <h3>
                    Good journeys are made by good people.
                </h3>

                <p>
                    We value professionalism, responsibility,
                    teamwork, customer care, and a willingness
                    to learn and improve.
                </p>
            </div>

            <div class="about-career-highlight">
                <span aria-hidden="true">✳</span>
                <p>
                    Help create a smoother travel experience
                    for passengers and communities.
                </p>
            </div>

        </div>


        <div class="about-subheading">
            <span class="about-eyebrow">CAREER OPPORTUNITIES</span>
            <h3>Explore our job categories</h3>
            <p>
                These are example role categories, not confirmed
                current vacancies.
            </p>
        </div>


        <?php
$jobs = [
    [
        'number' => '01',
        'title' => 'Bus Drivers',
        'description' => 'Support safe driving, responsible passenger transport, and scheduled departures.',
        'skills' => 'Valid qualifications, defensive driving, safety awareness'
    ],
    [
        'number' => '02',
        'title' => 'Maintenance Technicians',
        'description' => 'Help inspect, maintain, and repair vehicles to support safe operations.',
        'skills' => 'Mechanical knowledge, troubleshooting, attention to detail'
    ],
    [
        'number' => '03',
        'title' => 'Terminal Staff',
        'description' => 'Assist passengers with boarding information, schedules, and ticketing procedures.',
        'skills' => 'Communication, organization, customer service'
    ],
    [
        'number' => '04',
        'title' => 'Customer Support',
        'description' => 'Help answer passenger questions about bookings, reservations, and travel policies.',
        'skills' => 'Clear communication, patience, problem-solving'
    ],
    [
        'number' => '05',
        'title' => 'IT Personnel',
        'description' => 'Support website maintenance, reservation technology, and technical troubleshooting.',
        'skills' => 'Technical knowledge, testing, troubleshooting'
    ],
    [
        'number' => '06',
        'title' => 'Operations Personnel',
        'description' => 'Assist with scheduling, service coordination, records, and daily operations.',
        'skills' => 'Planning, coordination, data accuracy'
    ]
];
?>

        <div class="about-jobs-grid">

            <?php foreach ($jobs as $job): ?>

                <article class="about-job-card">

                    <div class="about-job-top">
                        <span><?= h($job['number']) ?></span>
                        <span class="about-job-symbol" aria-hidden="true">↗</span>
                    </div>

                    <h3><?= h($job['title']) ?></h3>

                    <p><?= h($job['description']) ?></p>

                    <div class="about-job-skills">
                        <strong>Relevant skills</strong>
                        <span><?= h($job['skills']) ?></span>
                    </div>

                </article>

            <?php endforeach; ?>

        </div>


        <div class="about-benefits-panel">

            <div class="about-benefits-heading">
                <span class="about-eyebrow">LIFE AT BUSWAY</span>
                <h3>A culture built around people.</h3>
                <p>
                    The following are workplace goals and potential
                    benefits, subject to the company's actual
                    employment policies.
                </p>
            </div>

            <div class="about-benefit">
                <span>✓</span>
                <div>
                    <strong>Safety and training</strong>
                    <p>Role-appropriate training and safe working practices.</p>
                </div>
            </div>

            <div class="about-benefit">
                <span>✓</span>
                <div>
                    <strong>Professional development</strong>
                    <p>Opportunities to develop relevant skills and experience.</p>
                </div>
            </div>

            <div class="about-benefit">
                <span>✓</span>
                <div>
                    <strong>Teamwork and respect</strong>
                    <p>A professional environment that values cooperation.</p>
                </div>
            </div>

            <div class="about-benefit">
                <span>✓</span>
                <div>
                    <strong>Employee benefits</strong>
                    <p>Benefits and compensation according to official company policy and employment terms.</p>
                </div>
            </div>

        </div>


        <div class="about-application">

            <div>
                <span class="about-eyebrow">INTERESTED IN JOINING?</span>

                <h3>Take the next step in your career.</h3>

                <p>
                    Prepare your updated résumé and a short introduction
                    describing your experience, skills, and preferred role.
                    Contact the authorized BUSWAY recruitment team using
                    its verified official contact channel.
                </p>

                <p class="about-application-note">
                    No application form or résumé submission is connected
                    to this page yet. Add an official recruitment email
                    or application system before accepting applications.
                </p>
            </div>

            <a href="support.php" class="about-btn about-btn-primary">
                Visit Support →
            </a>

        </div>

    </section>


    <!-- =====================================================
         FINAL CALL TO ACTION
    ====================================================== -->
    <section class="about-final-cta">

        <span class="about-eyebrow">YOUR NEXT JOURNEY STARTS HERE</span>

        <h2>Ready to find your next destination?</h2>

        <p>
            Explore available trips, review schedules,
            and begin your booking with BUSWAY.
        </p>

        <a href="book.php"
           class="about-btn about-btn-primary">
            Start Booking →
        </a>

    </section>

</div>


<!-- =========================================================
     ROUTE FINDER INTERACTIONS
========================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const originSelect = document.getElementById('about-origin');
    const destinationSelect = document.getElementById('about-destination');
    const swapButton = document.getElementById('about-swap');

    /*
     * Swap the selected origin and destination.
     */
    if (swapButton && originSelect && destinationSelect) {
        swapButton.addEventListener('click', function () {

            const oldOrigin = originSelect.value;
            const oldDestination = destinationSelect.value;

            originSelect.value = oldDestination;
            destinationSelect.value = oldOrigin;

        });
    }

    /*
     * Selecting "Use route" in the route table fills in the
     * departure and destination fields above.
     */
    document.querySelectorAll('.about-use-route').forEach(function (button) {

        button.addEventListener('click', function () {

            const origin = this.dataset.origin;
            const destination = this.dataset.destination;

            if (!originSelect || !destinationSelect) {
                return;
            }

            originSelect.value = origin;
            destinationSelect.value = destination;

            const finder = document.querySelector('.about-route-finder');

            if (finder) {
                finder.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            originSelect.focus();

        });

    });

    /*
     * Prevent choosing the same city as both origin and destination.
     */
    if (originSelect && destinationSelect) {

        originSelect.addEventListener('change', function () {

            if (originSelect.value === destinationSelect.value) {
                destinationSelect.value = '';
            }

        });

        destinationSelect.addEventListener('change', function () {

            if (destinationSelect.value === originSelect.value) {
                destinationSelect.value = '';
            }

        });

    }

});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
