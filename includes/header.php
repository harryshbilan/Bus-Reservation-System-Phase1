<?php
if (!defined('BUSWAY_APP')) { http_response_code(403); exit('Forbidden'); }

$pageTitle ??= '';
$activeStep ??= null;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ? h($pageTitle) . ' — BUSWAY' : 'BUSWAY — Bus Reservation' ?></title>
<link rel="stylesheet" href="../assets/css/style.css">
<script src="../assets/js/app.js"></script>
</head>
<body>

<header class="site-header">
  <div class="bar container" style="max-width:1152px;">
    <a href="index.php" class="brand">
      <span class="brand-mark">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="#0f1117">
          <path d="M4 16c0 .88.39 1.67 1 2.22V20c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h8v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1.78c.61-.55 1-1.34 1-2.22V6c0-3.5-3.58-4-8-4s-8 .5-8 4v10zm3.5 1c-.83 0-1.5-.67-1.5-1.5S6.67 14 7.5 14s1.5.67 1.5 1.5S8.33 17 7.5 17zm9 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm1.5-6H6V6h12v5z"/>
        </svg>
      </span>
      <span class="brand-name">BUSWAY</span>
    </a>
    <nav class="flex-wrap gap-2">
      <button type="button" class="btn-support" onclick="document.getElementById('support-modal').classList.add('open')">SUPPORT</button>
    </nav>
  </div>
</header>

<?php if ($activeStep !== null): ?>
<div class="progress-wrap">
  <div class="progress-row">
    <?php
    $idx = array_search($activeStep, PROGRESS_STEPS, true);
    foreach (PROGRESS_STEPS as $i => $step):
        $cls = 'progress-step';
        if ($i === $idx) $cls .= ' active';
        if ($i < $idx) $cls .= ' done';
    ?>
      <div class="<?= $cls ?>">
        <span class="dot"><?= $i < $idx ? '&#10003;' : $i + 1 ?></span>
        <span class="plabel"><?= strtoupper(PROGRESS_LABELS[$step]) ?></span>
      </div>
      <?php if ($i < count(PROGRESS_STEPS) - 1): ?><div class="progress-line"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<main class="container">
