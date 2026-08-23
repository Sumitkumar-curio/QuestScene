<?php
/**
 * QuestScene — public profile.
 * Activity-first. The object is what someone does, not who they are.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$username = (string) ($_GET['u'] ?? '');
$u = $username === '' ? null : q1("SELECT * FROM users WHERE username = ?", [$username]);

if (!$u || ($u['status'] !== 'active' && !is_admin())) {
    http_response_code(404);
    $page_title = 'Profile not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section">';
    render_empty('No such profile', 'That handle does not exist on QuestScene.', 'Discover plans', url('discover.php'));
    echo '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$me     = current_user();
$isMe   = $me && (int) $me['id'] === (int) $u['id'];
$follows = $me && !$isMe
    ? (bool) qv("SELECT 1 FROM follows WHERE follower_id = ? AND following_id = ?", [$me['id'], $u['id']])
    : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_login();

    if (($_POST['action'] ?? '') === 'follow' && !$isMe) {
        if ($follows) {
            qx("DELETE FROM follows WHERE follower_id = ? AND following_id = ?", [$me['id'], $u['id']]);
        } else {
            qx("INSERT IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)", [$me['id'], $u['id']]);
            notify((int) $u['id'], '👤', $me['name'] . ' is following you', 'profile.php?u=' . $me['username']);
        }
    }

    if (($_POST['action'] ?? '') === 'block' && !$isMe) {
        qx("INSERT IGNORE INTO blocks (user_id, blocked_id) VALUES (?, ?)", [$me['id'], $u['id']]);
        qx("DELETE FROM follows WHERE follower_id = ? AND following_id = ?", [$me['id'], $u['id']]);
        flash('info', 'Blocked. You will not see their messages any more.');
        redirect('discover.php');
    }

    redirect('profile.php?u=' . rawurlencode($u['username']));
}

$hosted    = find_plans(['host_id' => $u['id'], 'limit' => 6]);
$interests = q("SELECT c.* FROM user_interests ui JOIN categories c ON c.id = ui.category_id WHERE ui.user_id = ? ORDER BY c.sort", [$u['id']]);
$comms     = q("SELECT g.*, c.emoji AS cat_emoji, c.name AS cat_name
                  FROM community_members m JOIN communities g ON g.id = m.community_id
                  LEFT JOIN categories c ON c.id = g.category_id
                 WHERE m.user_id = ? AND g.status='active' ORDER BY g.name LIMIT 6", [$u['id']]);

$counts = [
  'joined'    => (int) qv("SELECT COUNT(*) FROM plan_participants WHERE user_id = ? AND status='going'", [$u['id']]),
  'hosted'    => (int) qv("SELECT COUNT(*) FROM plans WHERE host_id = ? AND status='active'", [$u['id']]),
  'comms'     => (int) qv("SELECT COUNT(*) FROM community_members WHERE user_id = ?", [$u['id']]),
  'followers' => (int) qv("SELECT COUNT(*) FROM follows WHERE following_id = ?", [$u['id']]),
];

$badge  = verify_badge($u);
$rating = user_rating($u);

$nav        = $isMe ? 'profile' : '';
$page_title = $u['name'];
$page_desc  = $u['bio'] ? excerpt($u['bio'], 150) : $u['name'] . ' on QuestScene — ' . $counts['joined'] . ' plans joined in ' . $u['city'] . '.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:860px">

  <div class="panel">
    <div class="profile-head">
      <?php render_avatar($u, 'lg'); ?>
      <div>
        <h1>
          <?= e($u['name']) ?>
          <?php if ($badge): ?><span class="verified-tick" title="<?= e($badge['label']) ?>">✓</span><?php endif; ?>
        </h1>
        <div class="handle">@<?= e($u['username']) ?> · 📍 <?= e($u['city']) ?></div>
      </div>

      <?php if ($u['bio']): ?>
        <p class="text-2" style="max-width:46ch;margin:0"><?= e($u['bio']) ?></p>
      <?php endif; ?>

      <div class="plan-meta" style="justify-content:center">
        <?php if ($rating): ?><span class="vbadge">⭐ <?= e($rating) ?></span><?php endif; ?>
        <?php if ((int) $u['phone_verified']): ?><span class="vbadge">🟢 Phone verified</span><?php endif; ?>
        <?php if ((int) $u['id_verified']):    ?><span class="vbadge">🔵 Identity verified</span><?php endif; ?>
        <?php if ($u['role'] === 'organizer'): ?><span class="vbadge">🟣 Organizer</span><?php endif; ?>
        <span class="vbadge">Joined <?= e(date('M Y', strtotime($u['created_at']))) ?></span>
      </div>

      <div class="profile-stats">
        <div><strong><?= $counts['joined'] ?></strong><span>Joined</span></div>
        <div><strong><?= $counts['hosted'] ?></strong><span>Hosted</span></div>
        <div><strong><?= $counts['comms'] ?></strong><span>Communities</span></div>
        <div><strong><?= $counts['followers'] ?></strong><span>Followers</span></div>
      </div>

      <div class="btn-row" style="justify-content:center;margin-top:6px">
        <?php if ($isMe): ?>
          <a class="btn btn-grad" href="<?= url('settings.php') ?>">Edit profile</a>
          <a class="btn btn-ghost" href="<?= url('dashboard.php') ?>">My QuestScene</a>
        <?php elseif ($me): ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="follow">
            <button class="btn <?= $follows ? 'btn-ok' : 'btn-grad' ?>" type="submit"><?= $follows ? 'Following ✓' : 'Follow' ?></button>
          </form>
          <button class="btn btn-ghost js-copy" data-copy="<?= e(url('profile.php?u=' . rawurlencode($u['username']))) ?>">Copy link</button>
        <?php else: ?>
          <a class="btn btn-grad" href="<?= url('login.php') ?>">Log in to follow</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($interests): ?>
  <section class="section">
    <h2 style="font-size:1.15rem">Into</h2>
    <div class="rail">
      <?php foreach ($interests as $c): ?>
        <a class="catpill" href="<?= url('discover.php?cat=' . e($c['slug']) . '&city=' . rawurlencode($u['city'])) ?>">
          <span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($hosted): ?>
  <section class="section">
    <?php render_section_head('Plans they are hosting', 'Upcoming and open'); ?>
    <div class="grid"><?php foreach ($hosted as $p) render_plan_card($p); ?></div>
  </section>
  <?php endif; ?>

  <?php if ($comms): ?>
  <section class="section">
    <?php render_section_head('Communities', ''); ?>
    <div class="grid-2"><?php foreach ($comms as $g) render_community_card($g); ?></div>
  </section>
  <?php endif; ?>

  <?php if ($me && !$isMe): ?>
  <section class="section">
    <details>
      <summary class="muted" style="cursor:pointer;font-size:.85rem">Report or block this person</summary>
      <div class="panel panel-tight" style="margin-top:10px">
        <form method="post" action="<?= url('report.php') ?>" style="margin-bottom:14px">
          <?= csrf_field() ?>
          <input type="hidden" name="target_type" value="user">
          <input type="hidden" name="target_id" value="<?= (int) $u['id'] ?>">
          <div class="field">
            <label for="reason">Reason</label>
            <select class="select" name="reason" id="reason" required>
              <option value="">Choose a reason</option>
              <option>Spam or scam</option>
              <option>Harassment</option>
              <option>Fake profile</option>
              <option>Inappropriate behaviour</option>
              <option>Something else</option>
            </select>
          </div>
          <div class="field">
            <label for="details">Details</label>
            <textarea class="textarea" name="details" id="details" maxlength="600" style="min-height:70px"></textarea>
          </div>
          <button class="btn btn-danger btn-sm" type="submit">Report to QuestScene</button>
        </form>

        <form method="post" data-confirm="Block <?= e($u['name']) ?>? You will stop seeing their messages.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="block">
          <button class="btn btn-danger btn-sm" type="submit">Block <?= e(explode(' ', $u['name'])[0]) ?></button>
        </form>
      </div>
    </details>
  </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
