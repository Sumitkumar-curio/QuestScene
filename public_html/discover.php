<?php
/**
 * QuestScene — Discover.
 * Search + filter across every public plan and event.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$city   = current_city();
$q      = trim((string) ($_GET['q'] ?? ''));
$cat    = (string) ($_GET['cat']  ?? '');
$when   = (string) ($_GET['when'] ?? '');
$tab    = (string) ($_GET['tab']  ?? 'all');
$sort   = (string) ($_GET['sort'] ?? 'soon');
$free   = !empty($_GET['free']);
$page   = max(1, (int) ($_GET['p'] ?? 1));
$per    = 24;

$filters = [
  'city' => $city, 'q' => $q, 'cat' => $cat, 'when' => $when,
  'tab'  => $tab,  'sort' => $sort, 'free' => $free,
  'limit' => $per, 'offset' => ($page - 1) * $per,
];

$plans = find_plans($filters);

/** Rebuild the current URL with one parameter changed. */
function qs_link(array $overrides): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    unset($params['p']);
    return url('discover.php?' . http_build_query($params));
}

$activeCat  = $cat ? category_by_slug($cat) : null;
$nav        = $tab === 'events' ? 'events' : 'discover';
$page_title = $activeCat ? $activeCat['name'] . ' in ' . $city : 'Discover ' . $city;
$page_desc  = "Find " . ($activeCat['name'] ?? 'plans, events and communities') . " happening in {$city}.";
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">

  <h1 style="font-size:clamp(1.6rem,5vw,2.3rem);margin-bottom:.2em">
    <?php if ($activeCat): ?>
      <?= e($activeCat['emoji']) ?> <?= e($activeCat['name']) ?> in <?= e($city) ?>
    <?php elseif ($tab === 'events'): ?>
      Events in <?= e($city) ?>
    <?php else: ?>
      What's happening in <span class="grad-text"><?= e($city) ?></span>
    <?php endif; ?>
  </h1>
  <p class="muted" style="margin-bottom:20px">Pick something. Show up. That's the whole idea.</p>

  <!-- ---------- Filters ---------- -->
  <form class="filters" method="get" action="<?= url('discover.php') ?>">
    <div class="filter-search">
      <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search plans, venues, activities…" aria-label="Search">
    </div>

    <select class="select" name="city" onchange="this.form.submit()" aria-label="City" style="max-width:160px">
      <?php foreach (CITIES as $c): ?>
        <option value="<?= e($c) ?>" <?= $c === $city ? 'selected' : '' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="select" name="sort" onchange="this.form.submit()" aria-label="Sort" style="max-width:170px">
      <option value="soon"    <?= $sort === 'soon'    ? 'selected' : '' ?>>Starting soonest</option>
      <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most popular</option>
      <option value="new"     <?= $sort === 'new'     ? 'selected' : '' ?>>Newest</option>
    </select>

    <?php foreach (['cat' => $cat, 'when' => $when, 'tab' => $tab, 'free' => $free ? 1 : ''] as $k => $v): ?>
      <?php if ($v !== '' && $v !== false): ?><input type="hidden" name="<?= $k ?>" value="<?= e((string) $v) ?>"><?php endif; ?>
    <?php endforeach; ?>

    <noscript><button class="btn btn-grad btn-sm" type="submit">Apply</button></noscript>
  </form>

  <!-- ---------- When / type pills ---------- -->
  <div class="filter-pills" style="margin-bottom:12px">
    <a class="fpill <?= $when === ''         ? 'active' : '' ?>" href="<?= e(qs_link(['when' => ''])) ?>">Anytime</a>
    <a class="fpill <?= $when === 'today'    ? 'active' : '' ?>" href="<?= e(qs_link(['when' => 'today'])) ?>">Today</a>
    <a class="fpill <?= $when === 'tomorrow' ? 'active' : '' ?>" href="<?= e(qs_link(['when' => 'tomorrow'])) ?>">Tomorrow</a>
    <a class="fpill <?= $when === 'weekend'  ? 'active' : '' ?>" href="<?= e(qs_link(['when' => 'weekend'])) ?>">This weekend</a>
    <a class="fpill <?= $when === 'week'     ? 'active' : '' ?>" href="<?= e(qs_link(['when' => 'week'])) ?>">Next 7 days</a>
    <a class="fpill <?= $free               ? 'active' : '' ?>" href="<?= e(qs_link(['free' => $free ? '' : '1'])) ?>">Free</a>
    <a class="fpill <?= $tab === 'events'    ? 'active' : '' ?>" href="<?= e(qs_link(['tab' => $tab === 'events' ? '' : 'events'])) ?>">Events only</a>
  </div>

  <!-- ---------- Categories ---------- -->
  <div class="rail">
    <a class="catpill <?= $cat === '' ? 'active' : '' ?>" href="<?= e(qs_link(['cat' => ''])) ?>">All</a>
    <?php foreach (all_categories() as $c): ?>
      <a class="catpill <?= $cat === $c['slug'] ? 'active' : '' ?>" href="<?= e(qs_link(['cat' => $c['slug']])) ?>">
        <span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ---------- Results ---------- -->
  <div class="result-line">
    <span><?= count($plans) ?><?= count($plans) === $per ? '+' : '' ?> result<?= count($plans) === 1 ? '' : 's' ?></span>
    <a class="section-link" href="<?= url('create.php') ?>">+ Create a plan</a>
  </div>

  <?php if ($plans): ?>
    <div class="grid">
      <?php foreach ($plans as $p) render_plan_card($p); ?>
    </div>

    <div class="pager">
      <?php if ($page > 1): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('discover.php?' . http_build_query(array_merge($_GET, ['p' => $page - 1])))) ?>">← Previous</a>
      <?php endif; ?>
      <?php if (count($plans) === $per): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('discover.php?' . http_build_query(array_merge($_GET, ['p' => $page + 1])))) ?>">Next →</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php render_empty(
      'Nothing matches that yet',
      $q !== ''
        ? "No plans for “{$q}” in {$city}. Try a different word, widen the dates, or create it yourself."
        : "Nothing scheduled with these filters in {$city}. Clear a filter — or be the one who starts something.",
      '+ Create this plan',
      url('create.php')
    ); ?>
  <?php endif; ?>

  <!-- ---------- Communities cross-link ---------- -->
  <div class="section">
    <?php render_section_head('Or find a community', 'Groups that run plans every week', 'Browse', url('communities.php')); ?>
    <div class="grid-2">
      <?php foreach (find_communities(['city' => $city, 'cat' => $cat, 'limit' => 4]) as $g) render_community_card($g); ?>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
