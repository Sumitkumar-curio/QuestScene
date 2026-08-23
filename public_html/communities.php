<?php
/**
 * QuestScene — Communities directory.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$city = current_city();
$q    = trim((string) ($_GET['q'] ?? ''));
$cat  = (string) ($_GET['cat'] ?? '');

$communities = find_communities(['city' => $city, 'q' => $q, 'cat' => $cat, 'limit' => 48]);

$nav        = 'communities';
$page_title = 'Communities in ' . $city;
$page_desc  = "Running clubs, trek crews, football groups and founder circles in {$city}.";
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">
  <h1 style="font-size:clamp(1.6rem,5vw,2.3rem);margin-bottom:.2em">
    Communities in <span class="grad-text"><?= e($city) ?></span>
  </h1>
  <p class="muted" style="margin-bottom:20px">Groups that actually meet up. Join one and their plans show up in your week.</p>

  <form class="filters" method="get" action="<?= url('communities.php') ?>">
    <div class="filter-search">
      <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search communities…" aria-label="Search communities">
    </div>
    <select class="select" name="city" onchange="this.form.submit()" style="max-width:160px" aria-label="City">
      <?php foreach (CITIES as $c): ?>
        <option value="<?= e($c) ?>" <?= $c === $city ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
  </form>

  <div class="rail">
    <a class="catpill <?= $cat === '' ? 'active' : '' ?>" href="<?= url('communities.php?city=' . rawurlencode($city)) ?>">All</a>
    <?php foreach (all_categories() as $c): ?>
      <a class="catpill <?= $cat === $c['slug'] ? 'active' : '' ?>"
         href="<?= url('communities.php?city=' . rawurlencode($city) . '&cat=' . e($c['slug'])) ?>">
        <span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="result-line">
    <span><?= count($communities) ?> communit<?= count($communities) === 1 ? 'y' : 'ies' ?></span>
    <a class="section-link" href="<?= url('community-new.php') ?>">+ Start a community</a>
  </div>

  <?php if ($communities): ?>
    <div class="grid-2">
      <?php foreach ($communities as $g) render_community_card($g); ?>
    </div>
  <?php else: ?>
    <?php render_empty(
      'No communities here yet',
      "Nothing matching that in {$city}. If you already run a group on WhatsApp or Telegram, bring it here — your plans get a page and a link.",
      'Start a community',
      url('community-new.php')
    ); ?>
  <?php endif; ?>

  <div class="section">
    <div class="cta-band">
      <h2>Already running a group?</h2>
      <p>Bring your WhatsApp or Telegram community onto QuestScene. Your members get one place to see every plan, and you get an organizer dashboard.</p>
      <a class="btn btn-grad btn-lg" href="<?= url('community-new.php') ?>">Start your community</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
