<?php
/**
 * QuestScene admin — overview.
 * Shows what is actually happening rather than what you assume is happening.
 */

$admin_page = 'index';
require __DIR__ . '/_nav.php';

$s = [
  'users'        => (int) qv("SELECT COUNT(*) FROM users WHERE status = 'active'"),
  'usersWeek'    => (int) qv("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
  'active7'      => (int) qv("SELECT COUNT(*) FROM users WHERE last_seen_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
  'plans'        => (int) qv("SELECT COUNT(*) FROM plans WHERE status = 'active'"),
  'plansUpcoming'=> (int) qv("SELECT COUNT(*) FROM plans WHERE status = 'active' AND starts_at >= NOW()"),
  'plansWeek'    => (int) qv("SELECT COUNT(*) FROM plans WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
  'events'       => (int) qv("SELECT COUNT(*) FROM plans WHERE is_event = 1 AND status = 'active'"),
  'communities'  => (int) qv("SELECT COUNT(*) FROM communities WHERE status = 'active'"),
  'joins'        => (int) qv("SELECT COUNT(*) FROM plan_participants WHERE status = 'going'"),
  'joinsToday'   => (int) qv("SELECT COUNT(*) FROM plan_participants WHERE DATE(joined_at) = CURDATE()"),
  'messages'     => (int) qv("SELECT COUNT(*) FROM messages"),
  'posts'        => (int) qv("SELECT COUNT(*) FROM posts WHERE status = 'active'"),
  'reports'      => (int) qv("SELECT COUNT(*) FROM reports WHERE status = 'open'"),
  'hosts'        => (int) qv("SELECT COUNT(DISTINCT host_id) FROM plans"),
];

$byCity = q(
    "SELECT city,
            COUNT(*) AS plans,
            SUM(going_count) AS going
       FROM plans WHERE status = 'active' AND starts_at >= NOW()
      GROUP BY city ORDER BY plans DESC"
);

$recentPlans = q(PLAN_SELECT . " ORDER BY p.created_at DESC LIMIT 8");
$recentUsers = q("SELECT id, name, username, avatar, city, created_at FROM users ORDER BY id DESC LIMIT 8");

// Last 14 days of activity, as a simple bar strip.
$daily = q(
    "SELECT DATE(created_at) AS d, COUNT(*) AS n
       FROM plans WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
      GROUP BY DATE(created_at) ORDER BY d"
);
$dailyMap = array_column($daily, 'n', 'd');
$maxDaily = max(1, ...array_map('intval', array_values($dailyMap) ?: [0]));
?>

<h1 style="font-size:1.8rem">Overview</h1>
<p class="muted" style="margin-bottom:20px">Live numbers. If something here looks wrong, it probably is — check before assuming.</p>

<?php if ($s['reports']): ?>
  <div class="flash flash-error" style="margin:0 0 18px">
    <?= $s['reports'] ?> open report<?= $s['reports'] === 1 ? '' : 's' ?> waiting.
    <a href="<?= url('admin/reports.php') ?>" style="color:inherit;text-decoration:underline">Review now</a>
  </div>
<?php endif; ?>

<div class="statgrid" style="margin-bottom:12px">
  <div class="stat"><div class="k">Users</div><div class="v grad"><?= number_format($s['users']) ?></div><div class="d">+<?= $s['usersWeek'] ?> this week</div></div>
  <div class="stat"><div class="k">Active (7d)</div><div class="v"><?= number_format($s['active7']) ?></div></div>
  <div class="stat"><div class="k">Upcoming plans</div><div class="v"><?= number_format($s['plansUpcoming']) ?></div><div class="d">+<?= $s['plansWeek'] ?> created this week</div></div>
  <div class="stat"><div class="k">Communities</div><div class="v"><?= number_format($s['communities']) ?></div></div>
</div>

<div class="statgrid" style="margin-bottom:24px">
  <div class="stat"><div class="k">Total joins</div><div class="v"><?= number_format($s['joins']) ?></div><div class="d"><?= $s['joinsToday'] ?> today</div></div>
  <div class="stat"><div class="k">Hosts</div><div class="v"><?= number_format($s['hosts']) ?></div></div>
  <div class="stat"><div class="k">Chat messages</div><div class="v"><?= number_format($s['messages']) ?></div></div>
  <div class="stat"><div class="k">Forum posts</div><div class="v"><?= number_format($s['posts']) ?></div></div>
</div>

<!-- Plans created, last 14 days -->
<div class="panel" style="margin-bottom:24px">
  <h2 style="font-size:1.1rem">Plans created — last 14 days</h2>
  <div style="display:flex;gap:5px;align-items:flex-end;height:110px;margin-top:14px">
    <?php for ($i = 13; $i >= 0; $i--): ?>
      <?php
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $n   = (int) ($dailyMap[$day] ?? 0);
        $h   = max(3, (int) round(($n / $maxDaily) * 100));
      ?>
      <div style="flex:1;text-align:center" title="<?= e($day) ?>: <?= $n ?> plans">
        <div style="height:<?= $h ?>%;background:var(--grad);border-radius:4px 4px 0 0;min-height:3px"></div>
        <span style="font-size:.6rem;color:var(--muted);display:block;margin-top:5px"><?= e(date('j', strtotime($day))) ?></span>
      </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Cities -->
<h2 style="font-size:1.2rem">By city</h2>
<div class="table-wrap" style="margin-bottom:24px">
  <table class="data">
    <thead><tr><th>City</th><th>Upcoming plans</th><th>People going</th></tr></thead>
    <tbody>
      <?php if (!$byCity): ?><tr><td colspan="3" class="muted">No upcoming plans anywhere yet.</td></tr><?php endif; ?>
      <?php foreach ($byCity as $c): ?>
        <tr><td><?= e($c['city']) ?></td><td><?= (int) $c['plans'] ?></td><td><?= (int) $c['going'] ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="grid-2" style="align-items:start">
  <div>
    <h2 style="font-size:1.2rem">Newest plans</h2>
    <div class="table-wrap">
      <table class="data" style="min-width:0">
        <tbody>
          <?php foreach ($recentPlans as $p): ?>
            <tr>
              <td><a href="<?= e(plan_url($p)) ?>"><?= e($p['cat_emoji']) ?> <?= e(excerpt($p['title'], 34)) ?></a><br>
                  <span class="muted" style="font-size:.75rem"><?= e($p['host_name']) ?> · <?= e($p['city']) ?> · <?= e(ago($p['created_at'])) ?></span></td>
              <td class="nowrap"><?= (int) $p['going_count'] ?>/<?= (int) $p['capacity'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <h2 style="font-size:1.2rem">Newest users</h2>
    <div class="table-wrap">
      <table class="data" style="min-width:0">
        <tbody>
          <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td><a href="<?= url('profile.php?u=' . rawurlencode($u['username'])) ?>" style="display:flex;gap:8px;align-items:center">
                    <?php render_avatar($u, 'xs'); ?><?= e($u['name']) ?></a></td>
              <td class="nowrap muted"><?= e($u['city']) ?> · <?= e(ago($u['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
