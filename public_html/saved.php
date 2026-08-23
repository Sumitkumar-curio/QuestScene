<?php
/**
 * QuestScene — saved plans.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login('saved.php');
$me = current_user();

$plans = q(
    PLAN_SELECT . "
      JOIN saved_plans sp ON sp.plan_id = p.id AND sp.user_id = ?
     WHERE p.status = 'active'
     ORDER BY p.starts_at ASC",
    [$me['id']]
);

$nav        = 'dashboard';
$page_title = 'Saved plans';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">
  <h1 style="font-size:1.8rem">Saved plans</h1>
  <p class="muted" style="margin-bottom:20px">Things you were thinking about. Nothing is reserved until you join.</p>

  <?php if ($plans): ?>
    <div class="grid"><?php foreach ($plans as $p) render_plan_card($p); ?></div>
  <?php else: ?>
    <?php render_empty('Nothing saved', 'Tap Save on any plan to park it here.', 'Discover plans', url('discover.php')); ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
