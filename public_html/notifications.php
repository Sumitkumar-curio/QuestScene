<?php
/**
 * QuestScene — notifications.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

require_login('notifications.php');
$me = current_user();

$rows = q("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 60", [$me['id']]);

// Reading the page is the acknowledgement.
qx("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$me['id']]);

$nav        = 'dashboard';
$page_title = 'Notifications';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:680px">
  <h1 style="font-size:1.8rem">Notifications</h1>

  <?php if (!$rows): ?>
    <?php render_empty('Nothing yet', 'Join a plan or a community and this is where the updates land.', 'Discover plans', url('discover.php')); ?>
  <?php endif; ?>

  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($rows as $n): ?>
      <?php $href = $n['link'] ? url($n['link']) : null; ?>
      <<?= $href ? 'a href="' . e($href) . '"' : 'div' ?> class="weekrow" style="<?= (int) $n['is_read'] ? 'opacity:.68' : '' ?>">
        <span class="room-ico" style="width:36px;height:36px;font-size:1rem"><?= e($n['icon'] ?: '🔔') ?></span>
        <span class="wbody">
          <strong style="white-space:normal"><?= e($n['body']) ?></strong>
          <span><?= e(ago($n['created_at'])) ?></span>
        </span>
      </<?= $href ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
