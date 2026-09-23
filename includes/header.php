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

<div class="brand">
    <span class="brand-icon">🚌</span>
    <span>BUSWAY</span>
</div>


<nav>

<a href="#">CANCEL RESERVATION</a>
<a href="#">SUPPORT</a>

<button class="menu-btn">
☰
</button>

</nav>

</div>

</header>

<main class="page-container">