<?php
define('BUSWAY_APP', true);
require __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/trips.php';

// Searches used to be posted here. 307 resends any old form to the booking page as-is.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Location: book.php', true, 307);
    exit;
}

$zone = new DateTimeZone('Asia/Manila');
$now = new DateTimeImmutable('now', $zone);
$routes = upcoming_routes($pdo, $now);
$cities = route_cities($pdo);

// The busiest destinations double as the "Popular" shortcuts under the search box.
$popular = [];

foreach ($routes as $route) {
    $popular[$route['destination']] = true;

    if (count($popular) === 5) {
        break;
    }
}

$browseLink = static fn (array $params): string => 'browse.php?' . http_build_query($params + ['period' => 'month']);
// Drop hero.mp4 (or hero.webm) into assets/videos to replace the photo with a looping video.
$heroVideo = null;

foreach (['hero.webm', 'hero.mp4'] as $file) {
    if (is_file(__DIR__ . '/../assets/videos/' . $file)) {
        $heroVideo = $file;
    }
}
$lineColors = ['#c0392b', '#1a6fa8', '#2e7d32', '#0077b6', '#e8940a', '#6a0572'];

$pageTitle = 'BUSWAY | Bus tickets across the Philippines';
$pageStyles = ['home.css'];
require __DIR__ . '/../includes/header.php';
?>
<div class="hm">

  <section class="hm-hero">
    <?php if ($heroVideo): ?>
      <video class="hm-hero-photo" id="hm-video" autoplay muted loop playsinline preload="metadata" poster="<?= is_file(__DIR__ . '/../assets/videos/hero-poster.jpg') ? '../assets/videos/hero-poster.jpg' : h(image_url('el nido palawan.jpg')) ?>" aria-hidden="true">
        <source src="../assets/videos/<?= h($heroVideo) ?>" type="video/<?= pathinfo($heroVideo, PATHINFO_EXTENSION) === 'webm' ? 'webm' : 'mp4' ?>">
      </video>
      <button type="button" class="hm-video-toggle" id="hm-video-toggle" aria-pressed="false">Pause video</button>
    <?php else: ?>
      <img class="hm-hero-photo" src="<?= h(image_url('el nido palawan.jpg')) ?>" alt="" fetchpriority="high">
    <?php endif; ?>
    <div class="hm-hero-copy">
      <span class="bw-kicker">The Philippines awaits</span>
      <h1 class="bw-display">Discover the Philippines,<br><em>one route at a time</em></h1>
      <p>Comfortable, safe and affordable bus travel to some of the most beautiful places in the archipelago.</p>
      <form class="hm-search" action="browse.php" method="get" role="search">
        <label class="hm-visually-hidden" for="hm-destination">Where do you want to go?</label>
        <span class="hm-search-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        </span>
        <input id="hm-destination" name="destination" list="hm-cities" placeholder="Search destination (e.g. Baguio, Vigan…)" autocomplete="off" maxlength="100">
        <input type="hidden" name="period" value="month">
        <button type="submit">Search →</button>
      </form>
      <datalist id="hm-cities"><?php foreach ($cities as $city): ?><option value="<?= h($city) ?>"><?php endforeach; ?></datalist>
      <?php if ($popular): ?>
        <p class="hm-popular">Popular:
          <?php foreach (array_keys($popular) as $i => $city): ?><?= $i ? ', ' : '' ?><a href="<?= h($browseLink(['destination' => $city])) ?>"><?= h($city) ?></a><?php endforeach; ?>
        </p>
      <?php endif; ?>
      <a class="hm-hero-alt" href="book.php">Know your route and date? Book a trip directly →</a>
    </div>
  </section>

  <section class="hm-section" aria-labelledby="where-title">
    <header class="hm-head">
      <span class="bw-kicker">Popular destinations</span>
      <h2 id="where-title" class="bw-display">Where will you go?</h2>
      <p>From island paradise to mountain haven, the Philippines has a destination for every traveller.</p>
    </header>
    <div class="hm-places">
      <?php foreach (destination_profiles() as $keyword => [$name, $photo, $tag, $tagline]):
          $route = route_to($routes, $keyword);
          ?>
        <a class="hm-place" href="<?= h($route ? $browseLink(['destination' => $route['destination']]) : 'browse.php') ?>">
          <img src="<?= h(image_url($photo)) ?>" alt="" loading="lazy">
          <div class="hm-place-copy">
            <span class="hm-place-tag"><?= h($tag) ?></span>
            <h3 class="bw-display"><?= h($name) ?></h3>
            <p><?= h($tagline) ?></p>
            <small><?= $route
                    ? 'Next bus ' . h((new DateTimeImmutable($route['next_departure'], $zone))->format('D, M j')) . ' · from ₱' . number_format((float) $route['lowest_fare'], 0)
                    : 'No direct bus yet. Browse nearby routes' ?></small>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="hm-deals" aria-labelledby="deals-title">
    <header class="hm-head">
      <span class="bw-kicker">Good to know before you book</span>
      <h2 id="deals-title" class="bw-display">Fares &amp; savings</h2>
    </header>
    <div class="hm-deals-grid">
      <article class="hm-deal-main">
        <span class="bw-kicker">Pay your way</span>
        <h3 class="bw-display">GCash, card<br>or cash</h3>
        <p>Pay online and your ticket is confirmed on the spot, or reserve now and pay at the counter. Online bookings hold your seats for <strong>15 minutes</strong> while you pay.</p>
        <a class="bw-btn" href="book.php">Book now →</a>
      </article>
      <div class="hm-deal-side">
        <a class="hm-deal" href="discounts.php">
          <span class="hm-deal-icon" style="--tone:#2e7d32">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="7" r="3"/><path d="M6 21v-2a6 6 0 0 1 12 0v2"/></svg>
          </span>
          <span><small style="color:#2e7d32">Senior / PWD</small><strong class="bw-display">20% discount</strong>For senior citizens and PWDs. Show your ID to the staff before you pay.</span>
        </a>
        <a class="hm-deal" href="discounts.php">
          <span class="hm-deal-icon" style="--tone:#1a6fa8">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/></svg>
          </span>
          <span><small style="color:#1a6fa8">Students</small><strong class="bw-display">20% discount</strong>On eligible fares with a valid school ID and enrolment proof.</span>
        </a>
      </div>
    </div>
  </section>

  <?php if ($routes): ?>
  <section class="hm-section" aria-labelledby="lines-title">
    <header class="hm-head">
      <span class="bw-kicker">Where our buses go</span>
      <h2 id="lines-title" class="bw-display">Bus transit lines</h2>
      <p><?= count($routes) ?> routes with departures coming up. Pick one to see every schedule.</p>
    </header>
    <div class="hm-lines">
      <?php foreach (array_slice($routes, 0, 6) as $i => $route):
          $color = $lineColors[$i % count($lineColors)];
          ?>
        <a class="hm-line" style="--line:<?= $color ?>" href="<?= h($browseLink(['origin' => $route['origin'], 'destination' => $route['destination']])) ?>">
          <span class="hm-line-stripe"></span>
          <?php if ($route['operators']): ?><span class="hm-line-operator"><?= h($route['operators']) ?></span><?php endif; ?>
          <h3 class="bw-display"><?= h($route['origin']) ?> → <?= h($route['destination']) ?></h3>
          <p class="hm-line-meta"><?= (int) $route['departures'] ?> departure<?= (int) $route['departures'] === 1 ? '' : 's' ?> ahead · fares from ₱<?= number_format((float) $route['lowest_fare'], 0) ?></p>
          <p class="hm-line-types"><?= h($route['bus_types']) ?></p>
          <span class="hm-line-cta">View schedules →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="hm-why" aria-labelledby="why-title">
    <header class="hm-head">
      <h2 id="why-title" class="bw-display">Why ride with <em>BUSWAY</em>?</h2>
      <p>Booking a bus seat shouldn't take more than a few minutes.</p>
    </header>
    <div class="hm-why-grid">
      <div>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="12" rx="2"/><path d="M5 11h14M8 19v2M16 19v2M7 15v4h10v-4"/></svg>
        <h3 class="bw-display">Pick your seat</h3>
        <p>Choose exactly where you sit from the live seat map.</p>
      </div>
      <div>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16l-3-2-3 2-2-2-2 2-3-2-3 2z"/><path d="M8 9h8M8 13h5"/></svg>
        <h3 class="bw-display">No surprise fees</h3>
        <p>Service fee and tax are on the summary before you pay.</p>
      </div>
      <div>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
        <h3 class="bw-display">Pay your way</h3>
        <p>GCash, credit or debit card, or cash at the counter.</p>
      </div>
      <div>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="m9 12 2 2 4-4"/></svg>
        <h3 class="bw-display">Ticket right away</h3>
        <p>Your booking reference is ready to print the moment you book.</p>
      </div>
    </div>
  </section>

  <section class="hm-cta">
    <h2 class="bw-display">Ready to hit the road?</h2>
    <p>Book your next bus trip in minutes.</p>
    <a class="bw-btn dark" href="book.php">Book now →</a>
  </section>

</div>
<?php if ($heroVideo): ?>
<script>
// Pause button for the hero video; respects the "reduce motion" setting.
(() => {
  const video = document.getElementById('hm-video');
  const toggle = document.getElementById('hm-video-toggle');
  const set = playing => {
    toggle.textContent = playing ? 'Pause video' : 'Play video';
    toggle.setAttribute('aria-pressed', String(!playing));
  };
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { video.pause(); set(false); }
  toggle.addEventListener('click', () => {
    if (video.paused) { video.play(); set(true); } else { video.pause(); set(false); }
  });
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
