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

    <title><?= h($pageTitle ?? 'BUSWAY'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>


<header class="navbar">

    <div class="nav-container">

        <a href="index.php" class="logo">

            <div class="logo-box">
                🚌
            </div>

            <span>
                BUSWAY
            </span>

        </a>


        <nav class="nav-links">

            <a href="cancel.php">
                CANCEL RESERVATION
            </a>


            <a href="contact.php">
                SUPPORT
            </a>


            <button class="menu-btn">
                ☰
            </button>

        </nav>


    </div>

</header>


<main class="page-container">