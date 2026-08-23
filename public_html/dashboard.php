<?php
/**
 * QuestScene — "My QuestScene".
 * The logged-in home. Their week first, recommendations second.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login('dashboard.php');
$me = current_user();

$week = q(
    PLAN_SELECT . "
      JOIN plan_participants pp ON pp.plan_id = p.id AND pp.user_id = ? AND pp.status IN ('going','waitlist')
     WHERE p.status = 'active' AND p.starts_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
     ORDER BY p.starts_at ASC LIMIT 10",
    [$me['id']]
);

$hosting = find_plans(['host_id' => $me['id'], 'limit' => 6]);

$myCommunities = q(
    "SELECT g.*, c.name AS cat_name, c.emoji AS cat_emoji
       FROM communities g
       JOIN community_members m ON m.community_id = g.id AND m.user_id = ?
       LEFT JOIN categories c ON c.id = g.category_id
      WHERE g.status = 'active' ORDER BY g.name LIMIT 8",
    [$me['id']]
);

// Recommendations: their interests, their city, things they haven't joined.
$interestIds = array_column(q("SELECT category_id FROM user_interests WHERE user_id = ?", [$me['id']]), 'category_id');

if ($interestIds) {
    $ph = implode(',', array_fill(0, count($interestIds), '?'));
    $recommended = q(
        PLAN_SELECT . "
         WHERE p.status='active' AND p.visibility='public' AND p.city = ?
           AND p.starts_at >= NOW() AND p.category_id IN ({$ph})
           AND p.host_id <> ?
           AND p.id NOT IN (SELECT plan_id FROM plan_participants WHERE user_id = ?)
         ORDER BY p.starts_at ASC LIMIT 6",
        array_merge([$me['city']], $interestIds, [$me['id'], $me['id']])
    );
} else {
    $recommended = find_plans(['city' => $me['city'], 'sort' => 'popular', 'limit' => 6]);
}

$stats = [
  'joined'  => (int) qv("SELECT COUNT(*) FROM plan_participants WHERE user_id = ? AND status = 'going'", [$me['id']]),
  'hosted'  => (int) qv("SELECT COUNT(*) FROM plans WHERE host_id = ?", [$me['id']]),
  'comms'   => (int) qv("SELECT COUNT(*) FROM community_members WHERE user_id = ?", [$me['id']]),
  'unread'  => (int) qv("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$me['id']]),
];

$nav        = 'dashboard';
$page_title = 'My QuestScene';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">

  <h1 style="font-size:clamp(1.6rem,5vw,2.3rem);margin-bottom:.15em">
    Hey <?= e(explode(' ', $me['name'])[0]) ?> 👋
  </h1>
  <p class="muted" style="margin-bottom:22px">
    <?php if ($week): ?>
      You have <?= count($week) ?> thing<?= count($week) === 1 ? '' : 's' ?> coming up. Next: <strong style="color:var(--text)"><?= e($week[0]['title']) ?></strong>, <?= e(strtolower(fmt_day($week[0]['starts_at']))) ?>.
    <?php else: ?>
      Nothing on your calendar yet. Let's fix that.
    <?php endif; ?>
  </p>

  <!-- Stats -->
  <div class="statgrid" style="margin-bottom:26px">
    <div class="stat"><div class="k">Plans joined</div><div class="v grad"><?= $stats['joined'] ?></div></div>
    <div class="stat"><div class="k">Plans hosted</div><div class="v"><?= $stats['hosted'] ?></div></div>
    <div class="stat"><div class="k">Communities</div><div class="v"><?= $stats['comms'] ?></div></div>
    <div class="stat">
      <div class="k">Notifications</div>
      <div class="v"><?= $stats['unread'] ?></div>
      <?php if ($stats['unread']): ?><a class="d" style="color:var(--brand-3)" href="<?= url('notifications.php') ?>">Read them →</a><?php endif; ?>
    </div>
  </div>

  <!-- Your week -->
  <section>
    <?php render_section_head('Your week', 'What you have said yes to', 'Find more', url('discover.php')); ?>

    <?php if ($week): ?>
      <div class="weekstrip" style="margin-bottom:10px">
        <?php foreach ($week as $p): ?>
          <a class="weekrow" href="<?= e(plan_url($p)) ?>">
            <span class="wday">
              <?= e(date('D', strtotime($p['starts_at']))) ?>
              <b><?= e(date('j', strtotime($p['starts_at']))) ?></b>
            </span>
            <span class="room-ico"><?= e($p['cat_emoji'] ?: '✨') ?></span>
            <span class="wbody">
              <strong><?= e($p['title']) ?></strong>
              <span><?= e(fmt_time($p['starts_at'])) ?> · <?= e($p['venue']) ?> · <?= (int) $p['going_count'] ?> going</span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <?php render_empty('Your week is empty', 'Pick one thing. Showing up once is what makes the second time easy.', 'Explore ' . $me['city'], url('discover.php')); ?>
    <?php endif; ?>
  </section>

  <!-- Recommended -->
  <section class="section">
    <?php render_section_head('Recommended for you', 'Based on your interests in ' . $me['city'], 'See all', url('discover.php')); ?>
    <?php if ($recommended): ?>
      <div class="grid"><?php foreach ($recommended as $p) render_plan_card($p); ?></div>
    <?php else: ?>
      <?php render_empty('Nothing to suggest yet', 'Add a few interests in settings and we will match you better.', 'Update interests', url('settings.php')); ?>
    <?php endif; ?>
  </section>

  <!-- Hosting -->
  <?php if ($hosting): ?>
  <section class="section">
    <?php render_section_head('Plans you are hosting', 'You are responsible for these', '+ New plan', url('create.php')); ?>
    <div class="grid"><?php foreach ($hosting as $p) render_plan_card($p); ?></div>
  </section>
  <?php endif; ?>

  <!-- Communities -->
  <section class="section">
    <?php render_section_head('Your communities', count($myCommunities) . ' joined', 'Browse more', url('communities.php')); ?>
    <?php if ($myCommunities): ?>
      <div class="grid-2"><?php foreach ($myCommunities as $g) render_community_card($g); ?></div>
    <?php else: ?>
      <?php render_empty('Not in any community yet', 'Communities post plans every week — join one and your calendar fills itself.', 'Find communities', url('communities.php')); ?>
    <?php endif; ?>
  </section>

  <!-- Create CTA -->
  <section class="section">
    <div class="cta-band">
      <h2>Nothing you want to join?</h2>
      <p>Then be the one who starts it. Most QuestScene plans get their first three people within an hour of being shared.</p>
      <a class="btn btn-grad btn-lg" href="<?= url('create.php') ?>">+ Create a Plan</a>
    </div>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
