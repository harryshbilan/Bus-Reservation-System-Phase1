<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $travelDate = $_POST['travel_date'] ?? '';
    if ($travelDate === '' || $travelDate < date('Y-m-d')) {
        $error = 'Please choose a valid travel date (today or later).';
    } else {

        $_SESSION['booking'] = [
            'origin' => trim($_POST['origin'] ?? ''),
            'destination' => trim($_POST['destination'] ?? ''),
            'travel_date' => $travelDate,
            'passengers' => max(1, min(40, (int) ($_POST['passengers'] ?? 1))),
        ];
        redirect('routes.php');
    }
}

$booking = $_SESSION['booking'];
$origin = $booking['origin'] ?? '';
$destination = $booking['destination'] ?? '';
$travelDate = $booking['travel_date'] ?? '';
$passengers = $booking['passengers'] ?? 1;

$pageTitle = 'Search';
$activeStep = null;
require __DIR__ . '/../includes/header.php';
?>

<section class="hero">

<div class="hero-overlay">

<div class="eyebrow accent">
THE PHILIPPINES AWAITS
</div>

<h1 class="hero-title">
DISCOVER THE PHILIPPINES,
<br>
<span class="accent">ONE ROUTE AT A TIME</span>
</h1>

<p class="hero-sub mb-6">
Comfortable, safe, and affordable bus travel connecting you to destinations across the archipelago.
</p>

<?php if ($error): ?>
  <div class="error-box">
    <div>
      <div class="etitle">MISSING INFORMATION</div>
      <div class="emsg"><?= h($error) ?></div>
    </div>
  </div>
<?php endif; ?>

<div class="card" id="journey-details">
  <div class="card-title">JOURNEY DETAILS</div>
  <form method="post" action="index.php">
    <div class="grid-2 mb-6">
      <div>
        <label class="label" for="origin">ORIGIN</label>
        <input class="input" type="text" id="origin" name="origin" placeholder="City of departure" value="<?= h($origin) ?>">
      </div>
      <div>
        <label class="label" for="destination">DESTINATION</label>
        <input class="input" type="text" id="destination" name="destination" placeholder="City of arrival" value="<?= h($destination) ?>">
      </div>
      <div>
        <label class="label" for="travel_date">TRAVEL DATE</label>
        <input class="input" type="date" id="travel_date" name="travel_date" value="<?= h($travelDate) ?>" min="<?= date('Y-m-d') ?>" required>
      </div>
      <div>
        <label class="label">PASSENGERS</label>
        <div class="counter">
          <button type="button" id="dec-btn" onclick="adjustPassengers(-1,1,40)" <?= $passengers <= 1 ? 'disabled' : '' ?>>&minus;</button>
          <div class="value" id="passengers-display"><?= (int) $passengers ?></div>
          <button type="button" id="inc-btn" onclick="adjustPassengers(1,1,40)" <?= $passengers >= 40 ? 'disabled' : '' ?>>+</button>
          <input type="hidden" name="passengers" id="passengers" value="<?= (int) $passengers ?>">
          <span class="hint">max 40</span>
        </div>
      </div>
    </div>
    <button type="submit" class="btn-primary">SEARCH BUS &rarr;</button>
  </form>
</div>

</div>
</section>

<div class="stats-grid">
  <div class="stats-item"><div class="stats-value">240+</div><div class="stats-label">ROUTES DAILY</div></div>
  <div class="stats-item"><div class="stats-value">80+</div><div class="stats-label">CITIES SERVED</div></div>
  <div class="stats-item"><div class="stats-value">8.1&#9733;</div><div class="stats-label">AVG RATING</div></div>
</div>

<section class="destinations">

    <div class="section-head">
        <div class="eyebrow accent">
            POPULAR DESTINATIONS
        </div>

        <h2 class="section-title">
            WHERE WILL YOU GO?
        </h2>

        <p>
            From island paradise to mountain haven — the Philippines has a destination for every traveler.
        </p>
    </div>

    <div class="destination-grid">

        <div class="destination-card">
            <img src="../assets/images/el nido palawan.jpg">

            <div class="destination-overlay">
                <span>ISLAND PARADISE</span>
                <h3>EL NIDO, PALAWAN</h3>
                <p>Crystal waters and beautiful beaches</p>
            </div>
        </div>

        <div class="destination-card">
            <img src="../assets/images/siargao.jpg">

            <div class="destination-overlay">
                <span>SURF CAPITAL</span>
                <h3>SIARGAO</h3>
                <p>Surf, sun, and island adventures</p>
            </div>
        </div>

        <div class="destination-card">
            <img src="../assets/images/coron palawan.jpg">

            <div class="destination-overlay">
                <span>DIVE DESTINATION</span>
                <h3>CORON, PALAWAN</h3>
                <p>Explore crystal clear waters</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/baguio.jpg">

            <div class="destination-overlay">
                <span>SUMMER CAPITAL</span>
                <h3>BAGUIO CITY</h3>
                <p>Cool weather and mountain views</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/vigan.jpg">

            <div class="destination-overlay">
                <span>HERITAGE CITY</span>
                <h3>VIGAN, ILOCOS SUR</h3>
                <p>Walk through history</p>
            </div>
        </div>


        <div class="destination-card">
            <img src="../assets/images/cebu.jpg">

            <div class="destination-overlay">
                <span>QUEEN CITY</span>
                <h3>CEBU CITY</h3>
                <p>Culture, beaches, and adventure</p>
            </div>
        </div>


    </div>

</section>

<section class="promos">

    <div class="section-head">
        <div class="eyebrow accent">
            LIMITED TIME OFFERS
        </div>

        <h2 class="section-title">
            PROMOS & DEALS
        </h2>
    </div>

    <div class="promo-container">

        <!-- Main Promo -->

        <div class="main-promo">

            <div class="promo-topline">
                <img class="promo-logo" src="../assets/images/logo.jpg" alt="BUSWAY logo">

                <div class="promo-code">
                    🎟 PROMO CODE: We_Love_NEU
                </div>
            </div>

            <h3>
                10% OFF<br>
                YOUR NEXT TRIP
            </h3>

            <p>
                Valid on all routes and bus types.
                Use code We_Love_NEU at checkout.
            </p>

            <a class="btn-primary" href="#journey-details">
                BOOK NOW →
            </a>

        </div>

        <!-- Small Cards -->

        <div class="small-promos">


            <div class="promo-card">

                <span>
                    👴
                </span>

                <div>
                    <small>
                        SENIOR / PWD
                    </small>

                    <h4>
                        20% DISCOUNT
                    </h4>

                    <p>
                        Present valid ID before boarding.
                    </p>

                </div>

            </div>

            <div class="promo-card">

                <span>
                    👥
                </span>

                <div>

                    <small>
                        GROUP BOOKING
                    </small>

                    <h4>
                        GROUP OF 10+
                    </h4>

                    <p>
                        Enjoy special group rates.
                    </p>

                </div>

            </div>



            <div class="promo-card">

                <span>
                    🌅
                </span>

                <div>

                    <small>
                        EARLY BIRD
                    </small>

                    <h4>
                        BOOK 7 DAYS AHEAD
                    </h4>

                    <p>
                        Reserve early and save more.
                    </p>
                </div>
            </div>
        </div>
    </div>

</section>
<section class="why-busway">

    <div class="why-header">
        <h2>WHY CHOOSE BUSWAY?</h2>
        <p>Trusted by millions of Filipinos for comfortable inter-city travel.</p>
    </div>


    <div class="why-grid">

        <div class="why-item">
            <div class="why-icon">🛡️</div>
            <h3>SAFE & INSURED</h3>
            <p>
                All buses fully inspected and drivers professionally trained.
            </p>
        </div>


        <div class="why-item">
            <div class="why-icon">💰</div>
            <h3>BEST VALUE</h3>
            <p>
                Guaranteed lowest fares with no hidden booking fees.
            </p>
        </div>


        <div class="why-item">
            <div class="why-icon">⏱️</div>
            <h3>ON TIME</h3>
            <p>
                Industry-leading on-time departure rate across all routes.
            </p>
        </div>


        <div class="why-item">
            <div class="why-icon">🌱</div>
            <h3>NATIONWIDE</h3>
            <p>
                Over 200 routes connecting cities, towns, and provinces.
            </p>
        </div>

    </div>

</section>
<section class="bus-lines">

    <div class="section-head">

        <div class="eyebrow accent">
            OUR PARTNER OPERATORS
        </div>

        <h2 class="section-title">
            BUS TRANSIT LINES
        </h2>

        <p>
            BUSWAY partners with accredited operators covering Luzon,
            Visayas, and Mindanao.
        </p>

    </div>

    <div class="bus-grid">


        <div class="bus-card red">

            <h3>AGILA LINER</h3>

            <em>
                "Soar Above the Rest"
            </em>

            <p>
                5 buses available • Manila → Baguio
                <br>
                Cubao → Baler
            </p>

            <a>
                <a href="#journey-details">
                    VIEW SCHEDULES →
                </a>
            </a>

        </div>

        <div class="bus-card blue">

            <h3>LAKBAY EXPRESS</h3>

            <em>
                "Your Journey, Our Pride"
            </em>

            <p>
                5 buses available • Cubao → Dagupan
                <br>
                Cebu City → Moalboal
            </p>

            <a>
                <a href="#journey-details">
                    VIEW SCHEDULES →
                </a>
  
            </a>

        </div>

        <div class="bus-card green">

            <h3>BUNDOK TRANSIT</h3>

            <em>
                "Mountains to Shores"
            </em>

            <p>
                5 buses available • PITX → Batangas
                <br>
                Manila → Banaue
            </p>

            <a>
                <a href="#journey-details">
                    VIEW SCHEDULES →
                </a>

        </div>

        <div class="bus-card blue">

            <h3>DAGAT COACHES</h3>

            <em>
                "Ride the Blue Horizon"
            </em>

            <p>
                4 buses available • Cubao → Cabanatuan
            </p>

           
                <a href="#journey-details">
                    VIEW SCHEDULES →
                </a>
          

        </div>

        <div class="bus-card orange">

            <h3>SINAG LINES</h3>

            <em>
                "Shining the Way Forward"
            </em>

            <p>
                Manila → Vigan
                <br>
                Manila → Cebu
            </p>

            <a href="#journey-details">
                VIEW SCHEDULES →
            </a>
        </div>

        <div class="bus-card purple">

            <h3>HALIGI MOTORS</h3>

            <em>
                "Strong. Steady. Reliable."
            </em>

            <p>
                PITX → Lucena
                <br>
                Cubao → Laoag
            </p>

            <a href="#journey-details">
                VIEW SCHEDULES →
            </a>
        </div>


    </div>

</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
