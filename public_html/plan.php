<?php
/**
 * QuestScene — Plan page.
 * This is the page that gets pasted into WhatsApp and Telegram, so it has
 * to load fast, read clearly, and put JOIN above everything else.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$slug = (string) ($_GET['s'] ?? '');
$plan = $slug !== '' ? find_plan_by_slug($slug) : null;

if (!$plan || ($plan['status'] !== 'active' && !is_admin() && (int) ($plan['host_id']) !== uid())) {
    http_response_code(404);
    $page_title = 'Plan not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section">';
    render_empty('This plan is gone', 'It may have been cancelled or removed. Plenty of others going on.', 'Discover plans', url('discover.php'));
    echo '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$me       = current_user();
$isHost   = $me && (int) $me['id'] === (int) $plan['host_id'];
$going    = is_going((int) $plan['id'], uid());
$saved    = is_saved((int) $plan['id'], uid());
$people   = plan_participants((int) $plan['id']);
$left     = spots_left($plan);
$isFree   = (float) $plan['price'] == 0.0;
$past     = strtotime($plan['starts_at']) < time();
$shareUrl = plan_url($plan);

// Count a view once per session per plan.
if (empty($_SESSION['viewed'][$plan['id']])) {
    qx("UPDATE plans SET view_count = view_count + 1 WHERE id = ?", [$plan['id']]);
    $_SESSION['viewed'][$plan['id']] = true;
}

$host = [
  'id' => $plan['host_id'], 'name' => $plan['host_name'], 'username' => $plan['host_username'],
  'avatar' => $plan['host_avatar'], 'rating_sum' => $plan['host_rating_sum'],
  'rating_count' => $plan['host_rating_count'], 'id_verified' => $plan['host_id_verified'],
  'phone_verified' => $plan['host_phone_verified'], 'role' => $plan['host_role'],
];
$hostBadge  = verify_badge($host);
$hostRating = user_rating($host);
$hostPlans  = (int) qv("SELECT COUNT(*) FROM plans WHERE host_id = ? AND status = 'active'", [$plan['host_id']]);

$nav        = 'discover';
$page_title = $plan['title'];
$page_desc  = excerpt($plan['description'] ?: ($plan['title'] . ' — ' . fmt_when($plan['starts_at']) . ' at ' . $plan['venue']), 155);
$og_image   = cover_url($plan['cover']) ?: asset('img/og-default.png');
require __DIR__ . '/includes/header.php';
?>

<div class="wrap">
  <p class="breadcrumb">
    <a href="<?= url('discover.php') ?>">Discover</a> ›
    <a href="<?= url('discover.php?cat=' . e($plan['cat_slug'])) ?>"><?= e($plan['cat_name'] ?: 'Plans') ?></a>
  </p>

  <?php if ($plan['status'] === 'cancelled'): ?>
    <div class="flash flash-error" style="margin-bottom:16px">This plan was cancelled by the host.</div>
  <?php elseif ($past): ?>
    <div class="flash flash-info" style="margin-bottom:16px">This plan has already happened.</div>
  <?php endif; ?>

  <div class="plan-layout">

    <!-- ============ LEFT: the plan ============ -->
    <div>
      <div class="plan-hero">
        <?php if ($cover = cover_url($plan['cover'])): ?>
          <img src="<?= e($cover) ?>" alt="">
        <?php else: ?>
          <span class="big-emoji" aria-hidden="true"><?= e($plan['cat_emoji'] ?: '✨') ?></span>
        <?php endif; ?>
      </div>

      <div class="plan-meta">
        <span class="chip chip-cat"><?= e($plan['cat_emoji']) ?> <?= e($plan['cat_name'] ?: 'Other') ?></span>
        <span class="chip <?= $isFree ? 'chip-free' : '' ?>"><?= $isFree ? 'Free' : '₹' . number_format((float) $plan['price']) ?></span>
        <?php if ((int) $plan['is_event'] === 1): ?><span class="chip">Organizer event</span><?php endif; ?>
        <?php if ((int) $plan['verified'] === 1): ?><span class="chip">✓ QuestScene Verified</span><?php endif; ?>
      </div>

      <h1 class="plan-title"><?= e($plan['title']) ?></h1>

      <!-- Facts -->
      <div class="factlist" style="margin-bottom:22px">
        <div class="fact">
          <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
          <div>
            <div class="fact-k">When</div>
            <div class="fact-v"><?= e(date('l, j F Y', strtotime($plan['starts_at']))) ?> · <?= e(fmt_time($plan['starts_at'])) ?><?php
              if ($plan['ends_at']) echo ' – ' . e(fmt_time($plan['ends_at'])); ?></div>
          </div>
        </div>
        <div class="fact">
          <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><path d="M12 21s7-6.3 7-11a7 7 0 10-14 0c0 4.7 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
          <div>
            <div class="fact-k">Where</div>
            <div class="fact-v">
              <?= e($plan['venue'] ?: $plan['city']) ?>, <?= e($plan['city']) ?>
              <?php if ($plan['map_url']): ?>
                <br><a href="<?= e($plan['map_url']) ?>" target="_blank" rel="noopener noreferrer">Open in Maps →</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="fact">
          <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0114 0"/><path d="M16 5.5a3.5 3.5 0 010 7M17 20a7 7 0 00-1.5-4.3"/></svg>
          <div>
            <div class="fact-k">Who</div>
            <div class="fact-v"><?= (int) $plan['going_count'] ?> of <?= (int) $plan['capacity'] ?> spots taken<?= $left ? " · {$left} left" : ' · full' ?></div>
          </div>
        </div>
      </div>

      <!-- About -->
      <?php if (trim((string) $plan['description']) !== ''): ?>
        <h2 style="font-size:1.2rem">About this plan</h2>
        <p style="white-space:pre-wrap;color:var(--text-2)"><?= e($plan['description']) ?></p>
      <?php endif; ?>

      <!-- Host -->
      <hr class="divider">
      <h2 style="font-size:1.2rem">Hosted by</h2>
      <a class="panel panel-tight host-row" href="<?= url('profile.php?u=' . rawurlencode($plan['host_username'])) ?>" style="display:flex">
        <?php render_avatar($host, 'md'); ?>
        <div>
          <div class="name">
            <?= e($plan['host_name']) ?>
            <?php if ($hostBadge): ?><span class="verified-tick" title="<?= e($hostBadge['label']) ?>">✓</span><?php endif; ?>
          </div>
          <div class="sub">
            <?php if ($hostRating): ?>⭐ <?= e($hostRating) ?> · <?php endif; ?>
            <?= $hostPlans ?> plan<?= $hostPlans === 1 ? '' : 's' ?> hosted
            <?php if ($hostBadge): ?> · <?= e($hostBadge['label']) ?><?php endif; ?>
          </div>
        </div>
      </a>

      <!-- Community -->
      <?php if ($plan['comm_slug']): ?>
        <hr class="divider">
        <h2 style="font-size:1.2rem">Part of</h2>
        <a class="panel panel-tight host-row" href="<?= url('community.php?s=' . rawurlencode($plan['comm_slug'])) ?>" style="display:flex">
          <span class="room-ico">👥</span>
          <div>
            <div class="name"><?= e($plan['comm_name']) ?></div>
            <div class="sub">View community →</div>
          </div>
        </a>
      <?php endif; ?>

      <!-- People going -->
      <hr class="divider">
      <h2 style="font-size:1.2rem"><?= (int) $plan['going_count'] ?> going</h2>
      <?php if ($people): ?>
        <div class="people-grid">
          <?php foreach ($people as $p): ?>
            <a class="person" href="<?= url('profile.php?u=' . rawurlencode($p['username'])) ?>">
              <?php render_avatar($p, 'md'); ?>
              <span class="pname"><?= e(explode(' ', $p['name'])[0]) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted">Nobody yet — be the first in and the rest will follow.</p>
      <?php endif; ?>

      <!-- Share -->
      <hr class="divider">
      <h2 style="font-size:1.2rem">Share this plan</h2>
      <p class="muted" style="font-size:.88rem">Drop the link in your group. People can see it without an account.</p>
      <?php render_share($shareUrl, $plan['title'] . ' — ' . fmt_when($plan['starts_at']) . ' on QuestScene'); ?>

      <!-- Report -->
      <?php if ($me && !$isHost): ?>
        <hr class="divider">
        <details>
          <summary class="muted" style="cursor:pointer;font-size:.85rem">Report this plan</summary>
          <form method="post" action="<?= url('report.php') ?>" class="panel panel-tight" style="margin-top:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="target_type" value="plan">
            <input type="hidden" name="target_id" value="<?= (int) $plan['id'] ?>">
            <div class="field">
              <label for="reason">What's wrong?</label>
              <select class="select" name="reason" id="reason" required>
                <option value="">Choose a reason</option>
                <option>Spam or scam</option>
                <option>Misleading details</option>
                <option>Unsafe or inappropriate</option>
                <option>Harassment</option>
                <option>Something else</option>
              </select>
            </div>
            <div class="field">
              <label for="details">Details (optional)</label>
              <textarea class="textarea" name="details" id="details" maxlength="600" style="min-height:80px"></textarea>
            </div>
            <button class="btn btn-danger btn-sm" type="submit">Submit report</button>
          </form>
        </details>
      <?php endif; ?>
    </div>

    <!-- ============ RIGHT: join box ============ -->
    <aside>
      <div class="join-box">
        <div class="join-count">
          <strong data-going-count><?= (int) $plan['going_count'] ?></strong>
          <span>of <?= (int) $plan['capacity'] ?> going</span>
        </div>
        <div class="pcard-bar" style="margin-bottom:14px">
          <span data-going-bar style="width:<?= (int) min(100, round(($plan['going_count'] / max(1, $plan['capacity'])) * 100)) ?>%"></span>
        </div>

        <p class="muted" style="font-size:.85rem;margin-bottom:14px">
          <?= e(fmt_day($plan['starts_at'])) ?> · <?= e(fmt_time($plan['starts_at'])) ?><br>
          <?= e($plan['venue'] ?: $plan['city']) ?>
        </p>

        <?php if ($isHost): ?>
          <a class="btn btn-grad btn-block" href="<?= url('plan-edit.php?id=' . (int) $plan['id']) ?>">Manage plan</a>
          <a class="btn btn-ghost btn-block" style="margin-top:8px" href="<?= url('chat.php?type=plan&id=' . (int) $plan['id']) ?>">Open plan chat</a>

        <?php elseif ($past || $plan['status'] !== 'active'): ?>
          <button class="btn btn-block" disabled>Joining closed</button>

        <?php else: ?>
          <button class="btn <?= $going ? 'btn-ok' : 'btn-grad' ?> btn-lg btn-block js-join"
                  data-plan="<?= (int) $plan['id'] ?>"
                  data-state="<?= $going ? 'going' : 'left' ?>">
            <?= $going ? 'You’re going ✓' : ($left === 0 ? 'Join waitlist' : 'Join Plan') ?>
          </button>

          <a class="btn btn-ghost btn-block <?= $going ? '' : 'hidden' ?>" data-plan-chat
             style="margin-top:8px" href="<?= url('chat.php?type=plan&id=' . (int) $plan['id']) ?>">
            Open plan chat
          </a>

          <button class="btn btn-ghost btn-block js-save <?= $saved ? 'btn-ok' : '' ?>"
                  style="margin-top:8px" data-plan="<?= (int) $plan['id'] ?>">
            <?= $saved ? 'Saved ✓' : 'Save' ?>
          </button>
        <?php endif; ?>

        <?php if (!$me): ?>
          <p class="muted center" style="font-size:.79rem;margin:12px 0 0">
            You'll need a free account to join.
          </p>
        <?php endif; ?>

        <hr class="divider" style="margin:16px 0">

        <div class="host-row">
          <?php render_avatar($host, 'sm'); ?>
          <div>
            <div class="name" style="font-size:.85rem"><?= e($plan['host_name']) ?></div>
            <div class="sub"><?= $hostBadge ? e($hostBadge['label']) : 'Host' ?></div>
          </div>
        </div>
      </div>
    </aside>

  </div>
</div>

<?php if (!$isHost && !$past && $plan['status'] === 'active'): ?>
<!-- Phone-only: keeps Join reachable once the sidebar has scrolled past. -->
<div class="plan-sticky" id="planSticky">
  <span class="ps-info">
    <strong><?= e($plan['title']) ?></strong>
    <span><?= e(fmt_day($plan['starts_at'])) ?> · <span data-going-count><?= (int) $plan['going_count'] ?></span> going</span>
  </span>
  <button class="btn <?= $going ? 'btn-ok' : 'btn-grad' ?> js-join"
          data-plan="<?= (int) $plan['id'] ?>"
          data-state="<?= $going ? 'going' : 'left' ?>">
    <?= $going ? 'Going ✓' : ($left === 0 ? 'Waitlist' : 'Join') ?>
  </button>
</div>

<script>
/* Reveal the sticky bar only after the main join button is out of view. */
(function () {
  var bar = document.getElementById('planSticky');
  var box = document.querySelector('.join-box');
  if (!bar || !box || !('IntersectionObserver' in window)) return;
  new IntersectionObserver(function (e) {
    bar.classList.toggle('show', !e[0].isIntersecting);
  }, { threshold: 0.15 }).observe(box);
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
