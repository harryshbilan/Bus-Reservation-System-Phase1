<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Help & Support';
require __DIR__ . '/../includes/header.php';
?>
<style>
.bw-help{max-width:900px;margin:32px auto 56px;padding:0 20px;color:#21180e}
.bw-help h1{font-size:36px;margin-bottom:12px}.bw-help p{line-height:1.7}
.bw-help article{background:white;border:1px solid #e0d6c7;border-radius:14px;padding:24px;margin:18px 0}
.bw-help h2{font-size:21px;margin-top:0}.bw-help a{color:#805000;text-underline-offset:3px}
.bw-help-links{display:flex;gap:16px;flex-wrap:wrap}
</style>
<section class="bw-help">
  <div class="eyebrow">BUSWAY HELP</div>
  <h1>How can we help?</h1>
  <p>Find guidance for booking, counter payments and cancellations.</p>
  <article><h2>Find a trip</h2><p>Choose your origin, destination, date and passenger count. If there are no matching buses, check alternative trips or browse other dates.</p><div class="bw-help-links"><a href="index.php#journey-details">Book a trip →</a><a href="browse.php">Browse schedules →</a></div></article>
  <article><h2>Pay at the counter</h2><p>Keep your booking reference and bring it to the boarding terminal's counter. A pending reservation is unpaid. Staff confirmation records the payment; refresh your ticket page afterward to see its updated status.</p></article>
  <article><h2>Find or cancel a reservation</h2><p>Use your booking reference and booking email to find an active reservation. Review the cancellation policy before confirming a cancellation.</p><a href="cancellation.php">Manage reservation →</a></article>
  <article><h2>Travel policies</h2><div class="bw-help-links"><a href="terms.php">Terms and conditions</a><a href="refund.php">Refund policy</a><a href="discounts.php">Discounts and policies</a></div></article>
  <article><h2>Need help with a specific booking?</h2><p>Ask staff at your boarding terminal and have your booking reference ready.</p></article>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>