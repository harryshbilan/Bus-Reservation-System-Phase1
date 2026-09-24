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

</div>

</header>

<main class="page-container">