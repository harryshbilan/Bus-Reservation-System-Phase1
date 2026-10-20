<?php
if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

$bookingSteps = [
    'routes' => 'Available buses',
    'bus' => 'Bus selection',
    'seat' => 'Seat selection',
    'passenger' => 'Passenger info',
    'fare' => 'Fare summary',
    'payment' => 'Payment',
    'ticket' => 'Ticket',
];

// Small line icons for the menu, drawn with the current text colour.
$menuIcon = static function (string $name): string {
    $paths = [
        'ticket' => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bus' => '<rect x="4" y="3" width="16" height="14" rx="2"/><path d="M4 11h16M8 21v-4M16 21v-4"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.9.8c0 1.7-2.4 2.2-2.4 3.7M12 17h.01"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    ];

    return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
};

$menuLinks = [
    ['book.php', 'ticket', 'Book a trip', 'Choose your route, date and seats'],
    ['browse.php', 'list', 'Browse buses', "See every departure with seats left"],
    ['cancellation.php', 'search', 'Manage booking', 'Find a reservation or cancel it'],
    ['about.php#terminal-directory', 'pin', 'Terminals', 'Where to board and what to bring'],
    ['support.php', 'help', 'Help & support', 'Contact us, FAQs and lost & found'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'BUSWAY') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap">
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/navigation.css">
<link rel="stylesheet" href="../assets/css/theme.css">
<?php foreach ((array) ($pageStyles ?? []) as $sheet): ?>
<link rel="stylesheet" href="../assets/css/<?= h($sheet) ?>">
<?php endforeach; ?>
<script src="../assets/js/navigation.js" defer></script>
</head>
<body>

<header class="bw-header">
  <div class="bw-header-inner">
    <a href="index.php" class="bw-logo" aria-label="BUSWAY home">
      <span class="bw-logo-mark"><img src="../assets/images/logo.jpg" alt="" width="52" height="52"></span>
      <span class="bw-logo-text">BUSWAY</span>
    </a>
    <nav class="bw-header-nav" aria-label="Quick links">
      <a class="bw-nav-btn cancel" href="cancellation.php">Cancel reservation</a>
      <a class="bw-nav-btn" href="support.php">Support</a>
      <button class="bw-burger" id="busway-menu-open" type="button" aria-label="Open menu" aria-controls="busway-menu" aria-expanded="false" aria-haspopup="dialog">
        <span></span><span></span><span></span>
      </button>
    </nav>
  </div>
</header>

<dialog id="busway-menu" class="bw-drawer" aria-labelledby="busway-menu-title">
  <div class="bw-drawer-heading">
    <h2 id="busway-menu-title">Menu</h2>
    <button id="busway-menu-close" type="button" aria-label="Close menu" autofocus>✕</button>
  </div>
  <div class="bw-drawer-scroll">
    <nav aria-label="Main navigation">
      <?php foreach ($menuLinks as [$bwHref, $bwIcon, $bwTitle, $bwHint]): ?>
        <a href="<?= h($bwHref) ?>">
          <span class="bw-icon"><?= $menuIcon($bwIcon) ?></span>
          <span><strong><?= h($bwTitle) ?></strong><small><?= h($bwHint) ?></small></span>
          <span class="bw-chevron" aria-hidden="true">›</span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="bw-staff">
      <a href="../admin/login.php">
        <span class="bw-icon"><?= $menuIcon('lock') ?></span>
        <span><strong>Admin / employee login</strong><small>Staff access only</small></span>
      </a>
    </div>
    <p class="bw-drawer-brand"><strong>BUSWAY Philippines</strong>9 Central Ave, New Era, Quezon City</p>
  </div>
</dialog>
<noscript><nav class="bw-noscript" aria-label="Navigation"><a href="book.php">Book a trip</a> · <a href="browse.php">Browse buses</a> · <a href="cancellation.php">Manage booking</a> · <a href="support.php">Help</a> · <a href="../admin/login.php">Staff login</a></nav></noscript>

<?php if (!empty($activeStep) && isset($bookingSteps[$activeStep])):
    $activeIndex = array_search($activeStep, array_keys($bookingSteps), true);
    ?>
<nav class="bw-progress" aria-label="Booking progress">
  <ol>
    <?php $bwStep = 0;

    foreach ($bookingSteps as $bwKey => $bwLabel):
        $bwState = $bwStep < $activeIndex ? 'done' : ($bwStep === $activeIndex ? 'active' : '');
        ?>
      <li class="<?= $bwState ?>"<?= $bwState === 'active' ? ' aria-current="step"' : '' ?>>
        <span class="num"><?= $bwState === 'done' ? '✓' : $bwStep + 1 ?></span><?= h($bwLabel) ?>
      </li>
    <?php $bwStep++; endforeach; ?>
  </ol>
</nav>
<?php endif; ?>

<main class="page-container">
