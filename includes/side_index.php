<?php

if (!defined('BUSWAY_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/*
 * Side index. Set $indexSections (and optionally $indexTitle) before requiring this file:
 *   [['id' => 'explore', 'title' => 'Explore', 'expanded' => true,
 *     'items' => [['label' => 'Popular', 'href' => '#where-title'], ['label' => 'Book', 'href' => 'book.php']]]]
 * Items that start with # are tracked while scrolling; other links are normal page links.
 * Add 'locked' => true to show an item as unavailable.
 */
$indexSections = is_array($indexSections ?? null) ? $indexSections : [];
$indexTitle = (string) ($indexTitle ?? 'On this page');

if (!$indexSections) {
    return;
}
?>
<script>
// Set open/closed before the first paint so the index doesn't flash.
(function () {
    var collapsed = window.innerWidth < 992;
    if (!collapsed) { try { collapsed = localStorage.getItem('busway_index_collapsed') === 'true'; } catch (e) {} }
    if (collapsed) document.documentElement.classList.add('index-collapsed');
})();
</script>

<aside id="sideIndex" class="side-index" aria-label="<?= h($indexTitle) ?>">
  <div class="side-index-head">
    <span class="bw-kicker"><?= h($indexTitle) ?></span>
    <button type="button" class="side-index-collapse" id="sideIndexClose" aria-label="Hide index" aria-controls="sideIndex">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
    </button>
  </div>
  <div class="side-index-progress" aria-hidden="true"><i id="sideIndexBar"></i></div>

  <nav class="side-index-body" aria-label="<?= h($indexTitle) ?>">
    <?php foreach ($indexSections as $section):
        $open = !empty($section['expanded']);
        $listId = 'si-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($section['id'] ?? uniqid()));
        ?>
      <section class="si-group<?= $open ? ' is-open' : '' ?>">
        <button type="button" class="si-group-toggle" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= h($listId) ?>">
          <span><?= h($section['title'] ?? '') ?></span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </button>
        <ul class="si-list" id="<?= h($listId) ?>">
          <?php foreach (($section['items'] ?? []) as $item):
              $href = (string) ($item['href'] ?? '#');
              $isAnchor = str_starts_with($href, '#') && strlen($href) > 1;
              $locked = !empty($item['locked']);
              ?>
            <li class="si-item">
              <?php if ($locked): ?>
                <span class="si-link is-locked" aria-disabled="true" title="Not available yet">
                  <span class="si-label"><?= h($item['label'] ?? '') ?></span>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 1 1 4 0v2h-4V7z"/></svg>
                </span>
              <?php else: ?>
                <a class="si-link<?= $isAnchor ? '' : ' is-page' ?>" href="<?= h($href) ?>"<?= $isAnchor ? ' data-spy="' . h(substr($href, 1)) . '"' : '' ?>>
                  <span class="si-label"><?= h($item['label'] ?? '') ?></span>
                </a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </nav>
</aside>

<button type="button" class="side-index-open" id="sideIndexOpen" aria-controls="sideIndex" aria-expanded="false">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>
  Index
</button>
<div class="side-index-backdrop" id="sideIndexBackdrop"></div>
<script src="../assets/js/side-index.js" defer></script>