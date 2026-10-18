<?php
if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
<?= h($pageTitle ?? 'BUSWAY') ?>
</title>

<link rel="stylesheet" href="/Bus-Reservation-System-Phase1/assets/css/style.css">

<link rel="stylesheet" href="../assets/css/navigation.css">
<script src="../assets/js/navigation.js" defer></script>
</head>

<body>

<header class="main-header">

<div class="header-container">

<a href="index.php" class="brand">
    <img src="/Bus-Reservation-System-Phase1/assets/images/logo.jpg" alt="BUSWAY logo">
    <span class="brand-name">BUSWAY</span>
</a>

<nav>

<a href="cancellation.php">
CANCEL RESERVATION
</a>

<a href="support.php">
SUPPORT
</a>

<button class="menu-btn" id="busway-menu-open" type="button" aria-label="Open menu" aria-controls="busway-menu" aria-expanded="false" aria-haspopup="dialog">☰</button>

</nav>


</div>
</header>
<dialog id="busway-menu" class="bw-drawer" aria-labelledby="busway-menu-title">
  <div class="bw-drawer-heading">
    <h2 id="busway-menu-title">Menu</h2>
    <button id="busway-menu-close" type="button" aria-label="Close menu" autofocus>×</button>
  </div>
  <div class="bw-drawer-scroll">
    <nav aria-label="Main navigation">
      <a href="index.php#journey-details"><span class="bw-icon" aria-hidden="true">→</span><span><strong>Book a trip</strong><small>Choose your route, date and seats</small></span></a>
      <a href="browse.php"><span class="bw-icon" aria-hidden="true">▦</span><span><strong>Browse available buses</strong><small>Explore today's trips and other dates</small></span></a>
      <a href="cancellation.php"><span class="bw-icon" aria-hidden="true">≡</span><span><strong>Manage reservation</strong><small>Find an active booking or request cancellation</small></span></a>
      <a href="support.php"><span class="bw-icon" aria-hidden="true">?</span><span><strong>Help &amp; support</strong><small>Booking guidance and travel policies</small></span></a>
    </nav>
    <div class="bw-staff">
      <a href="../admin/login.php"><span class="bw-icon" aria-hidden="true">↗</span><span><strong>Staff login</strong><small>Authorized staff access</small></span></a>
    </div>
    <p class="bw-drawer-brand">BUSWAY · Plan your next journey</p>
  </div>
</dialog>
<noscript><nav aria-label="Navigation"><a href="index.php">Book a trip</a> · <a href="browse.php">Browse buses</a> · <a href="cancellation.php">Manage reservation</a> · <a href="support.php">Help</a> · <a href="../admin/login.php">Staff login</a></nav></noscript>

<?php if(isset($activeStep)): ?>
<div class="booking-progress">

<?php
$steps = [
    'routes' => 'AVAILABLE BUSES',
    'bus' => 'BUS SELECTION',
    'seat' => 'SEAT SELECTION',
    'passenger' => 'PASSENGER INFO',
    'fare' => 'FARE SUMMARY',
    'payment' => 'PAYMENT',
    'ticket' => 'TICKET',
    'cancel' => 'CANCEL',
    'cancelled' => 'CANCELLED'
];

$count = 1;

foreach($steps as $key => $label):
?>

<div class="progress-step <?= $activeStep == $key ? 'active' : '' ?>">
    <span><?= $count ?></span>
    <?= $label ?>
</div>

<?php
$count++;
endforeach;
?>

</div>
<?php endif; ?>


<main class="page-container">