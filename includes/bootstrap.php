<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

session_start();

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';


if (!isset($_SESSION['booking'])) {
    $_SESSION['booking'] = [];
}

?>
