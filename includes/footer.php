</main>
<?php
$footerColumns = [
    'Services' => [
        'Bus Booking' => 'book.php',
        'Route Schedules' => 'browse.php',
    ],
    'About' => [
        'Our Story' => 'about.php#our-story',
        'Terminal Directory' => 'about.php#terminal-directory',
        'Route Map' => 'about.php#route-map',
        'Careers' => 'about.php#careers',
    ],
    'Guidelines' => [
        'Discounts / Policies' => 'discounts.php',
        'Terms and Conditions' => 'terms.php',
        'Refund Policy' => 'refund.php',
        'Baggage & Boarding Policy' => 'discounts.php#baggage',
    ],
    'Support' => [
        'Contact Us' => 'support.php#contact',
        'FAQs' => 'support.php#faqs',
        'Lost & Found' => 'support.php#lost-found',
        'Feedback' => 'support.php#contact',
    ],
];

// Paste the real page addresses here. An icon only shows once it has a link.
$socialLinks = [
    'Facebook' => '',
    'X (Twitter)' => '',
    'Instagram' => '',
];
$socialIcons = [
    'Facebook' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>',
    'X (Twitter)' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="#fff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>',
    'Instagram' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><path d="M17.5 6.5h.01"/></svg>',
];
$socialLinks = array_filter($socialLinks);
?>
<footer class="bw-footer">
  <div class="bw-footer-cols">
    <?php foreach ($footerColumns as $heading => $links): ?>
      <div>
        <h2><?= h($heading) ?></h2>
        <?php foreach ($links as $label => $href): ?>
          <a href="<?= h($href) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($socialLinks): ?>
    <div class="bw-socials">
      <?php foreach ($socialLinks as $label => $url): ?>
        <a href="<?= h($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="BUSWAY on <?= h($label) ?>"><?= $socialIcons[$label] ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="bw-footer-address">9 Central Ave, New Era, Quezon City, Metro Manila, Philippines</p>

  <div class="bw-footer-bottom">
    <span>&copy; <?= date('Y') ?> BUSWAY Philippines Inc.</span>
  </div>
</footer>

</body>
</html>
