<?php
/**
 * QuestScene — Organizer dashboard.
 * For community owners: their numbers, their plans, their members,
 * and a one-click way to post an event or an announcement.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login('organizer.php');
$me = current_user();

$owned = q(
    "SELECT g.*, c.emoji AS cat_emoji, c.name AS cat_name
       FROM communities g
       JOIN community_members m ON m.community_id = g.id AND m.user_id = ? AND m.role IN ('owner','moderator')
       LEFT JOIN categories c ON c.id = g.category_id
      WHERE g.status = 'active' ORDER BY g.name",
    [$me['id']]
);

if (!$owned) {
    $nav = 'dashboard';
    $page_title = 'Organizer';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section">';
    render_empty(
        'You do not run a community yet',
        'Start one and you get this dashboard: member counts, plan performance, announcements and events.',
        'Start a community',
        url('community-new.php')
    );
    echo '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$cid = (int) ($_GET['c'] ?? $_POST['community_id'] ?? $owned[0]['id']);
$g   = null;
foreach ($owned as $o) { if ((int) $o['id'] === $cid) $g = $o; }
if (!$g) { $g = $owned[0]; $cid = (int) $g['id']; }

// --- announcement --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'announce') {
        $body = trim((string) ($_POST['body'] ?? ''));
        if (mb_strlen($body) < 5) {
            flash('error', 'Write the announcement first.');
        } else {
            qx("INSERT INTO posts (user_id, community_id, city, body, intent) VALUES (?,?,?,?,'share')",
               [$me['id'], $cid, $g['city'], mb_substr($body, 0, 4000)]);

            $members = q("SELECT user_id FROM community_members WHERE community_id = ? AND user_id <> ? LIMIT 1000", [$cid, $me['id']]);
            foreach ($members as $m) {
                notify((int) $m['user_id'], '📢', $g['name'] . ': ' . excerpt($body, 90), 'community.php?s=' . $g['slug']);
            }
            flash('ok', 'Announcement posted and ' . count($members) . ' members notified.');
        }
        redirect('organizer.php?c=' . $cid);
    }

    if (($_POST['action'] ?? '') === 'verify_request') {
        notify((int) $me['id'], '📨', 'Verification request received for ' . $g['name'] . '. We will be in touch.', 'organizer.php?c=' . $cid);
        flash('info', 'Verification request noted. A QuestScene admin will review your community.');
        redirect('organizer.php?c=' . $cid);
    }
}

// --- numbers -------------------------------------------------------
$stats = [
  'members'   => (int) $g['member_count'],
  'newWeek'   => (int) qv("SELECT COUNT(*) FROM community_members WHERE community_id = ? AND joined_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$cid]),
  'upcoming'  => (int) qv("SELECT COUNT(*) FROM plans WHERE community_id = ? AND status='active' AND starts_at >= NOW()", [$cid]),
  'parts'     => (int) qv("SELECT COUNT(*) FROM plan_participants pp JOIN plans p ON p.id = pp.plan_id
                            WHERE p.community_id = ? AND pp.status='going'", [$cid]),
  'views'     => (int) qv("SELECT COALESCE(SUM(view_count),0) FROM plans WHERE community_id = ?", [$cid]) + (int) $g['view_count'],
  'messages'  => (int) qv("SELECT COUNT(*) FROM messages WHERE room_type='community' AND room_id = ?", [$cid]),
];

$plans = q(PLAN_SELECT . " WHERE p.community_id = ? ORDER BY p.starts_at DESC LIMIT 20", [$cid]);
$recentMembers = q(
    "SELECT u.id, u.name, u.username, u.avatar, m.joined_at, m.role
       FROM community_members m JOIN users u ON u.id = m.user_id
      WHERE m.community_id = ? ORDER BY m.joined_at DESC LIMIT 12",
    [$cid]
);

$nav        = 'dashboard';
$page_title = 'Organizer · ' . $g['name'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">

  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin-bottom:18px">
    <div>
      <p class="breadcrumb" style="margin:0"><a href="<?= e(community_url($g)) ?>">← <?= e($g['name']) ?></a></p>
      <h1 style="font-size:1.8rem;margin:4px 0 0">Organizer dashboard</h1>
    </div>

    <?php if (count($owned) > 1): ?>
      <form method="get">
        <select class="select" name="c" onchange="this.form.submit()" aria-label="Choose community">
          <?php foreach ($owned as $o): ?>
            <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === $cid ? 'selected' : '' ?>><?= e($o['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>

  <!-- Numbers -->
  <div class="statgrid" style="margin-bottom:12px">
    <div class="stat"><div class="k">Members</div><div class="v grad"><?= number_format($stats['members']) ?></div><div class="d">+<?= $stats['newWeek'] ?> this week</div></div>
    <div class="stat"><div class="k">Upcoming plans</div><div class="v"><?= $stats['upcoming'] ?></div></div>
    <div class="stat"><div class="k">Participants</div><div class="v"><?= number_format($stats['parts']) ?></div><div class="d">across all plans</div></div>
    <div class="stat"><div class="k">Views</div><div class="v"><?= number_format($stats['views']) ?></div></div>
  </div>

  <!-- Actions -->
  <div class="btn-row" style="margin-bottom:24px">
    <a class="btn btn-grad" href="<?= url('create.php') ?>">+ Create plan or event</a>
    <a class="btn btn-ghost" href="<?= url('chat.php?type=community&id=' . $cid) ?>">Community chat (<?= $stats['messages'] ?>)</a>
    <a class="btn btn-ghost" href="<?= e(community_url($g)) ?>">View public page</a>
    <button class="btn btn-ghost js-copy" data-copy="<?= e(community_url($g)) ?>">Copy invite link</button>
  </div>

  <div class="grid-2" style="align-items:start;margin-bottom:24px">

    <!-- Announcement -->
    <form method="post" class="panel">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="announce">
      <input type="hidden" name="community_id" value="<?= $cid ?>">
      <h2 style="font-size:1.1rem">Post an announcement</h2>
      <p class="muted" style="font-size:.85rem">Goes on the community board and notifies every member.</p>
      <div class="field">
        <textarea class="textarea" name="body" maxlength="4000" required style="min-height:96px"
                  placeholder="Sunday run moves to 6:15 AM — meet at Gate 3 instead of the main entrance."></textarea>
      </div>
      <button class="btn btn-grad btn-sm" type="submit">Post &amp; notify <?= number_format($stats['members']) ?> members</button>
    </form>

    <!-- Verification -->
    <div class="panel">
      <h2 style="font-size:1.1rem">Verification</h2>
      <?php if ((int) $g['verified'] === 1): ?>
        <p class="text-2" style="font-size:.9rem">
          <span class="vbadge">🟣 Community Verified</span><br><br>
          Verified communities rank higher in Discover and show a tick on every plan.
        </p>
      <?php else: ?>
        <p class="muted" style="font-size:.87rem">
          Not verified yet. We only badge communities after we have actually checked who runs them —
          that is the whole point of the badge.
        </p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="verify_request">
          <input type="hidden" name="community_id" value="<?= $cid ?>">
          <button class="btn btn-ghost btn-sm" type="submit">Request verification</button>
        </form>
      <?php endif; ?>

      <hr class="divider">
      <h3 style="font-size:.95rem">Coming later</h3>
      <p class="muted" style="font-size:.83rem;margin:0">
        Paid events, memberships and promotions land once enough communities are running weekly plans here.
      </p>
    </div>
  </div>

  <!-- Plans -->
  <h2 style="font-size:1.2rem">Your plans</h2>
  <div class="table-wrap" style="margin-bottom:24px">
    <table class="data">
      <thead><tr><th>Plan</th><th>When</th><th>Going</th><th>Views</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php if (!$plans): ?>
          <tr><td colspan="6" class="muted">No plans yet. Create the first one — communities without plans go quiet fast.</td></tr>
        <?php endif; ?>
        <?php foreach ($plans as $p): ?>
          <tr>
            <td><a href="<?= e(plan_url($p)) ?>"><?= e($p['cat_emoji']) ?> <?= e($p['title']) ?></a></td>
            <td class="nowrap muted"><?= e(fmt_when($p['starts_at'])) ?></td>
            <td class="nowrap"><strong><?= (int) $p['going_count'] ?></strong> / <?= (int) $p['capacity'] ?></td>
            <td><?= (int) $p['view_count'] ?></td>
            <td><span class="pill <?= $p['status'] === 'active' ? 'pill-ok' : 'pill-danger' ?>"><?= e($p['status']) ?></span></td>
            <td><a class="btn btn-ghost btn-sm" href="<?= url('plan-edit.php?id=' . (int) $p['id']) ?>">Manage</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Members -->
  <h2 style="font-size:1.2rem">Newest members</h2>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Person</th><th>Role</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($recentMembers as $m): ?>
          <tr>
            <td><a href="<?= url('profile.php?u=' . rawurlencode($m['username'])) ?>" style="display:flex;gap:9px;align-items:center">
              <?php render_avatar($m, 'xs'); ?><?= e($m['name']) ?></a></td>
            <td><span class="pill <?= $m['role'] === 'owner' ? 'pill-info' : '' ?>"><?= e($m['role']) ?></span></td>
            <td class="nowrap muted"><?= e(ago($m['joined_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
