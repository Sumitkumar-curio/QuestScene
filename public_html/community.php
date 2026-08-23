<?php
/**
 * QuestScene — Community page.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$slug = (string) ($_GET['s'] ?? '');
$g = $slug === '' ? null : q1(
    "SELECT g.*, c.name AS cat_name, c.emoji AS cat_emoji,
            u.name AS owner_name, u.username AS owner_username, u.avatar AS owner_avatar
       FROM communities g
       LEFT JOIN categories c ON c.id = g.category_id
       LEFT JOIN users u      ON u.id = g.owner_id
      WHERE g.slug = ?",
    [$slug]
);

if (!$g || ($g['status'] !== 'active' && !is_admin())) {
    http_response_code(404);
    $page_title = 'Community not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section">';
    render_empty('No such community', 'The link may be old or the community was removed.', 'Browse communities', url('communities.php'));
    echo '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$me      = current_user();
$member  = is_member((int) $g['id'], uid());
$isOwner = $me && (int) $me['id'] === (int) $g['owner_id'];

// Join / leave
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_login();

    if ($isOwner) {
        flash('info', 'You run this community — you cannot leave it.');
    } elseif ($member) {
        qx("DELETE FROM community_members WHERE community_id = ? AND user_id = ?", [$g['id'], $me['id']]);
        flash('info', 'You left ' . $g['name'] . '.');
    } else {
        qx("INSERT IGNORE INTO community_members (community_id, user_id, role) VALUES (?, ?, 'member')", [$g['id'], $me['id']]);
        notify((int) $g['owner_id'], '👥', $me['name'] . ' joined ' . $g['name'], 'community.php?s=' . $g['slug']);
        flash('ok', 'You joined ' . $g['name'] . '. Their plans now show in your week.');
    }
    refresh_community_count((int) $g['id']);
    redirect('community.php?s=' . $g['slug']);
}

if (empty($_SESSION['viewed_c'][$g['id']])) {
    qx("UPDATE communities SET view_count = view_count + 1 WHERE id = ?", [$g['id']]);
    $_SESSION['viewed_c'][$g['id']] = true;
}

$upcoming = find_plans(['community_id' => $g['id'], 'limit' => 9]);
$past     = q(PLAN_SELECT . " WHERE p.community_id = ? AND p.starts_at < NOW() ORDER BY p.starts_at DESC LIMIT 3", [$g['id']]);
$members  = q("SELECT u.id, u.name, u.username, u.avatar, m.role
                 FROM community_members m JOIN users u ON u.id = m.user_id
                WHERE m.community_id = ? ORDER BY FIELD(m.role,'owner','moderator','member'), m.joined_at
                LIMIT 24", [$g['id']]);
$posts = q("SELECT p.*, u.name, u.username, u.avatar
              FROM posts p JOIN users u ON u.id = p.user_id
             WHERE p.community_id = ? AND p.status = 'active'
             ORDER BY p.created_at DESC LIMIT 5", [$g['id']]);

$nav        = 'communities';
$page_title = $g['name'];
$page_desc  = excerpt($g['description'], 155);
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">
  <p class="breadcrumb"><a href="<?= url('communities.php') ?>">Communities</a> › <?= e($g['city']) ?></p>

  <div class="panel" style="margin-bottom:22px">
    <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
      <div class="ccard-media" style="width:76px;height:76px;border-radius:20px">
        <?php if ($cover = cover_url($g['cover'])): ?>
          <img src="<?= e($cover) ?>" alt="">
        <?php else: ?>
          <span class="ccard-emoji" style="font-size:2rem"><?= e($g['cat_emoji'] ?: '👥') ?></span>
        <?php endif; ?>
      </div>

      <div style="flex:1;min-width:220px">
        <h1 style="font-size:clamp(1.4rem,5vw,2rem);margin-bottom:6px">
          <?= e($g['name']) ?>
          <?php if ((int) $g['verified'] === 1): ?><span class="verified-tick" title="QuestScene Verified">✓</span><?php endif; ?>
        </h1>
        <div class="plan-meta" style="margin-bottom:10px">
          <span class="chip"><?= number_format((int) $g['member_count']) ?> members</span>
          <span class="chip">📍 <?= e($g['city']) ?></span>
          <?php if ($g['cat_name']): ?><span class="chip chip-cat"><?= e($g['cat_emoji']) ?> <?= e($g['cat_name']) ?></span><?php endif; ?>
        </div>
        <p class="text-2" style="white-space:pre-wrap;margin-bottom:14px"><?= e($g['description']) ?></p>

        <div class="btn-row">
          <?php if ($isOwner): ?>
            <a class="btn btn-grad" href="<?= url('organizer.php?c=' . (int) $g['id']) ?>">Organizer dashboard</a>
            <a class="btn btn-ghost" href="<?= url('create.php') ?>">+ Create plan</a>
          <?php else: ?>
            <form method="post">
              <?= csrf_field() ?>
              <button class="btn <?= $member ? 'btn-ok' : 'btn-grad' ?>" type="submit">
                <?= $member ? 'Joined ✓' : 'Join community' ?>
              </button>
            </form>
          <?php endif; ?>

          <?php if ($member || $isOwner): ?>
            <a class="btn btn-ghost" href="<?= url('chat.php?type=community&id=' . (int) $g['id']) ?>">Community chat</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Upcoming -->
  <section>
    <?php render_section_head('Upcoming plans', count($upcoming) . ' coming up'); ?>
    <?php if ($upcoming): ?>
      <div class="grid"><?php foreach ($upcoming as $p) render_plan_card($p); ?></div>
    <?php else: ?>
      <?php render_empty('Nothing scheduled yet', 'This community has not posted an upcoming plan.',
        $isOwner ? '+ Create the first plan' : null, $isOwner ? url('create.php') : null); ?>
    <?php endif; ?>
  </section>

  <!-- Board -->
  <?php if ($posts): ?>
  <section class="section">
    <?php render_section_head('Community board', 'What members are asking', 'All posts', url('forum.php?community=' . (int) $g['id'])); ?>
    <div class="grid-2" style="align-items:start">
      <?php foreach ($posts as $p): ?>
        <div class="post">
          <div class="post-head">
            <?php render_avatar($p, 'sm'); ?>
            <div><div class="who"><?= e($p['name']) ?></div><div class="when"><?= e(ago($p['created_at'])) ?></div></div>
          </div>
          <div class="post-body"><?= e(excerpt($p['body'], 220)) ?></div>
          <div class="post-foot">
            <span class="muted" style="font-size:.8rem"><?= (int) $p['interest_count'] ?> interested · <?= (int) $p['reply_count'] ?> replies</span>
            <a class="post-act" href="<?= url('post.php?id=' . (int) $p['id']) ?>">Open</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Members -->
  <section class="section">
    <?php render_section_head('Members', number_format((int) $g['member_count']) . ' people'); ?>
    <div class="people-grid">
      <?php foreach ($members as $m): ?>
        <a class="person" href="<?= url('profile.php?u=' . rawurlencode($m['username'])) ?>">
          <?php render_avatar($m, 'md'); ?>
          <span class="pname"><?= e(explode(' ', $m['name'])[0]) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Past -->
  <?php if ($past): ?>
  <section class="section">
    <?php render_section_head('What they have already done', 'Proof this group actually shows up'); ?>
    <div class="grid"><?php foreach ($past as $p) render_plan_card($p); ?></div>
  </section>
  <?php endif; ?>

  <!-- Share -->
  <section class="section">
    <h2 style="font-size:1.2rem">Share this community</h2>
    <?php render_share(community_url($g), 'Join ' . $g['name'] . ' on QuestScene'); ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
