<?php
if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); }

session_start();

require __DIR__ . '/data.php';
require __DIR__ . '/functions.php';

if (!isset($_SESSION['booking'])) {
    $_SESSION['booking'] = [];
}
