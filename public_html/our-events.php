<?php
/**
 * QuestScene — Our events: what we have actually conducted.
 * Proof, not promises. This is the page you send someone who asks
 * "is this thing real?"
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$city = (string) ($_GET['city'] ?? '');
$cat  = (string) ($_GET['cat']  ?? '');

$where  = ["p.status = 'active'", "p.starts_at < NOW()"];
$params = [];
if ($city !== '' && in_array($city, CITIES, true)) { $where[] = "p.city = ?"; $params[] = $city; }
if ($cat !== '')                                    { $where[] = "c.slug = ?"; $params[] = $cat; }

$past = q(
    PLAN_SELECT . " WHERE " . implode(' AND ', $where) . " ORDER BY p.starts_at DESC LIMIT 60",
    $params
);

$totals = q1(
    "SELECT COUNT(*) AS runs,
            COALESCE(SUM(going_count),0) AS people,
            COUNT(DISTINCT host_id)      AS hosts,
            COUNT(DISTINCT city)         AS cities
       FROM plans WHERE starts_at < NOW() AND status = 'active'"
);
$topCats = q(
    "SELECT c.name, c.emoji, COUNT(*) AS n
       FROM plans p JOIN categories c ON c.id = p.category_id
      WHERE p.starts_at < NOW() AND p.status = 'active'
      GROUP BY c.id ORDER BY n DESC LIMIT 6"
);

// Group by month so the page reads like a track record.
$byMonth = [];
foreach ($past as $p) {
    $byMonth[date('F Y', strtotime($p['starts_at']))][] = $p;
}

$nav        = 'events';
$page_title = 'Our events';
$page_desc  = 'Every plan and event QuestScene has run so far — what happened, where, and how many people turned up.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">

  <h1 style="font-size:clamp(1.7rem,5.5vw,2.6rem);margin-bottom:.2em">
    What we've <span class="grad-text">actually run</span>
  </h1>
  <p class="muted" style="max-width:56ch;margin-bottom:22px">
    Not a roadmap — a record. Every plan and event below already happened, with the number of
    people who turned up. New here? This is the honest version of what QuestScene is.
  </p>

  <div class="statgrid" style="margin-bottom:26px">
    <div class="stat"><div class="k">Plans run</div><div class="v grad"><?= number_format((int) $totals['runs']) ?></div></div>
    <div class="stat"><div class="k">People who showed up</div><div class="v"><?= number_format((int) $totals['people']) ?></div></div>
    <div class="stat"><div class="k">Hosts</div><div class="v"><?= number_format((int) $totals['hosts']) ?></div></div>
    <div class="stat"><div class="k">Cities</div><div class="v"><?= number_format((int) $totals['cities']) ?></div></div>
  </div>

  <?php if ($topCats): ?>
    <h2 style="font-size:1.1rem">What we run most</h2>
    <div class="rail">
      <?php foreach ($topCats as $tc): ?>
        <span class="catpill"><span class="emo"><?= e($tc['emoji']) ?></span><?= e($tc['name']) ?> · <?= (int) $tc['n'] ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="filter-pills" style="margin:8px 0 20px">
    <a class="fpill <?= $city === '' ? 'active' : '' ?>" href="<?= url('our-events.php') ?>">All cities</a>
    <?php foreach (CITIES as $c): ?>
      <a class="fpill <?= $city === $c ? 'active' : '' ?>" href="<?= url('our-events.php?city=' . rawurlencode($c)) ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$past): ?>
    <?php render_empty(
      'Nothing has happened yet',
      'Once plans start taking place they show up here automatically — with real attendance numbers, not marketing copy.',
      'See what is coming up',
      url('discover.php')
    ); ?>
  <?php endif; ?>

  <?php foreach ($byMonth as $month => $plans): ?>
    <h2 style="font-size:1.15rem;margin-top:26px"><?= e($month) ?> <span class="muted" style="font-weight:600;font-size:.85rem">· <?= count($plans) ?></span></h2>
    <div class="grid-2">
      <?php foreach ($plans as $p): ?>
        <a class="pastcard" href="<?= e(plan_url($p)) ?>">
          <span class="pc-ico"><?= e($p['cat_emoji'] ?: '✨') ?></span>
          <span class="pc-body">
            <strong><?= e($p['title']) ?></strong>
            <span><?= e(date('D j M', strtotime($p['starts_at']))) ?> · <?= e($p['venue']) ?>, <?= e($p['city']) ?></span>
          </span>
          <span class="pc-n"><?= (int) $p['going_count'] ?> went</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <div class="section">
    <div class="cta-band">
      <h2>Want the next one on your calendar?</h2>
      <p>We post every week's plans on Instagram and Telegram — <?= e(SOCIAL_HANDLE) ?> on both.</p>
      <div style="display:flex;justify-content:center;margin-bottom:16px"><?php render_social(false); ?></div>
      <a class="btn btn-grad btn-lg" href="<?= url('discover.php') ?>">See what's coming up</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
