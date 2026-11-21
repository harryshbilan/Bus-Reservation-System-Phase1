<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/** Coordinates of a departure's boarding point, or null if not recorded yet. */
function boarding_coordinates(PDO $pdo, int $scheduleId): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT r.boarding_lat, r.boarding_lng FROM schedules s JOIN routes r ON r.route_id = s.route_id WHERE s.schedule_id = ?');
        $stmt->execute([$scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null; // locations.sql not imported yet: the map is simply skipped
    }

    if (!$row || $row['boarding_lat'] === null || $row['boarding_lng'] === null) {
        return null;
    }

    return ['lat' => (float) $row['boarding_lat'], 'lng' => (float) $row['boarding_lng']];
}

/** Boarding-point map (Leaflet + OpenStreetMap), directions link and a distance check. */
function render_boarding_map(?array $coords, string $label): void
{
    if ($coords === null) {
        return;
    }
    $directions = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($coords['lat'] . ',' . $coords['lng']);
    $safe = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<section class="st-card bw-map-card" aria-labelledby="map-title">
  <h2 class="st-card-title" id="map-title">Where to board</h2>
  <p class="bw-map-label"><?= h($label) ?></p>
  <div id="bw-map" class="bw-map" role="region" aria-label="Map of the boarding point"></div>
  <div class="bw-map-actions">
    <a class="bw-btn" href="<?= h($directions) ?>" target="_blank" rel="noopener">Get directions →</a>
    <button type="button" class="bw-btn-ghost" id="bw-distance-btn">How far am I?</button>
  </div>
  <p class="st-note" id="bw-distance" role="status" aria-live="polite"></p>
</section>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
  const target = <?= json_encode($coords) ?>;
  const label = <?= json_encode($label, $safe) ?>;
  const box = document.getElementById('bw-map');
  const out = document.getElementById('bw-distance');
  let map = null;

  if (window.L) {
    // One-finger drag scrolls the page on phones; pinch still zooms the map.
    map = L.map(box, { scrollWheelZoom: false, dragging: !L.Browser.mobile }).setView([target.lat, target.lng], 16);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    const name = document.createElement('span');
    name.textContent = label;
    L.marker([target.lat, target.lng]).addTo(map).bindPopup(name);
  } else {
    box.textContent = 'The map could not load. Use the directions link instead.';
  }

  const km = (a, b, c, d) => {
    const r = x => x * Math.PI / 180;
    const s = Math.sin(r(c - a) / 2) ** 2 + Math.cos(r(a)) * Math.cos(r(c)) * Math.sin(r(d - b) / 2) ** 2;
    return 12742 * Math.asin(Math.sqrt(s));
  };

  document.getElementById('bw-distance-btn').addEventListener('click', () => {
    if (!('geolocation' in navigator)) { out.textContent = 'This device can’t share its location. Use the directions link instead.'; return; }
    out.textContent = 'Finding your location…';
    navigator.geolocation.getCurrentPosition(
      pos => {
        const { latitude, longitude } = pos.coords;
        const d = km(latitude, longitude, target.lat, target.lng);
        out.textContent = d < 1 ? 'You’re less than 1 km from the terminal.' : `You’re about ${d.toFixed(1)} km from the terminal (in a straight line).`;
        if (map) {
          L.circleMarker([latitude, longitude], { radius: 8 }).addTo(map).bindTooltip('You are here');
          map.fitBounds([[latitude, longitude], [target.lat, target.lng]], { padding: [40, 40] });
        }
      },
      err => {
        out.textContent = err.code === err.PERMISSION_DENIED
          ? 'Location permission is off. Use the directions link instead.'
          : 'We couldn’t get your location. Use the directions link instead.';
      },
      { timeout: 10000, maximumAge: 300000 }
    );
  });
})();
</script>
    <?php
}