<?php
/**
 * QuestScene — "Your plan is live" screen.
 * The whole point of this page is to get the link into a group chat
 * within ten seconds of the plan existing.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login();

$plan = find_plan((int) ($_GET['id'] ?? 0));
if (!$plan || (int) $plan['host_id'] !== uid()) {
    redirect('dashboard.php');
}

$shareUrl  = plan_url($plan);
$shareText = $plan['title'] . ' — ' . fmt_when($plan['starts_at']) . ' at ' . $plan['venue'];

$nav        = 'create';
$page_title = 'Plan is live';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section" style="max-width:620px">
  <div class="panel center">
    <span style="font-size:2.6rem;display:block;margin-bottom:8px">🎉</span>
    <h1 style="font-size:1.7rem">Your plan is live</h1>
    <p class="muted" style="margin-bottom:22px">Now share it. A plan nobody sees is just a calendar entry.</p>

    <div class="panel panel-tight" style="text-align:left;background:var(--bg-2);margin-bottom:20px">
      <div class="pcard-meta" style="margin-bottom:8px">
        <span class="chip chip-cat"><?= e($plan['cat_emoji']) ?> <?= e($plan['cat_name']) ?></span>
        <span class="chip"><?= e(fmt_day($plan['starts_at'])) ?></span>
      </div>
      <h2 style="font-size:1.15rem;margin-bottom:6px"><?= e($plan['title']) ?></h2>
      <p class="muted" style="font-size:.86rem;margin:0">
        📍 <?= e($plan['venue']) ?><br>
        🕕 <?= e(fmt_when($plan['starts_at'])) ?><br>
        👥 1 / <?= (int) $plan['capacity'] ?> joined
      </p>
    </div>

    <div class="field">
      <span class="field-label" style="text-align:left">Your shareable link</span>
      <input class="input" type="text" readonly value="<?= e($shareUrl) ?>"
             onclick="this.select()" style="text-align:center;font-size:.86rem">
    </div>

    <?php render_share($shareUrl, $shareText); ?>

    <hr class="divider">

    <div class="btn-row" style="justify-content:center">
      <a class="btn btn-grad" href="<?= e($shareUrl) ?>">View plan</a>
      <a class="btn btn-ghost" href="<?= url('chat.php?type=plan&id=' . (int) $plan['id']) ?>">Open plan chat</a>
      <a class="btn btn-ghost" href="<?= url('plan-edit.php?id=' . (int) $plan['id']) ?>">Edit</a>
    </div>
  </div>

  <p class="muted center" style="font-size:.84rem;margin-top:20px">
    Tip: paste the link into your WhatsApp or Telegram group. People can open it without an account —
    they only sign up when they tap <strong>Join</strong>.
  </p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
