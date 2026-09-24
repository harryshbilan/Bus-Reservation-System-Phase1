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

<link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

<header class="main-header">

<div class="header-container">

<a href="index.php" class="brand">
    <img src="../assets/images/logo.jpg" alt="BUSWAY logo">
    <span class="brand-name">BUSWAY</span>
</a>

<nav>

<a href="index.php" onclick="sessionStorage.clear();">
CANCEL RESERVATION
</a>

<a href="#support">
SUPPORT
</a>

<button class="menu-btn" onclick="toggleMenu()">
☰
</button>

</nav>

<div class="mobile-menu" id="mobileMenu">

<a href="index.php">
HOME
</a>

<a href="#journey-details">
BOOK NOW
</a>

<a href="#destinations">
DESTINATIONS
</a>
<a href="#support">
SUPPORT
</a>
</div>
</div>
</header>

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