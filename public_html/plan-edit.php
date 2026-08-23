<?php
/**
 * QuestScene — Manage a plan (host or admin).
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login();

$plan = find_plan((int) ($_GET['id'] ?? $_POST['id'] ?? 0));
if (!$plan || ((int) $plan['host_id'] !== uid() && !is_admin())) {
    http_response_code(403);
    exit('You can only manage your own plans.');
}

$me     = current_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'cancel') {
        qx("UPDATE plans SET status = 'cancelled' WHERE id = ?", [$plan['id']]);
        foreach (q("SELECT user_id FROM plan_participants WHERE plan_id = ? AND user_id <> ?", [$plan['id'], $me['id']]) as $p) {
            notify((int) $p['user_id'], '⚠️', 'Cancelled: "' . $plan['title'] . '"', 'plan.php?s=' . $plan['slug']);
        }
        flash('info', 'Plan cancelled. Everyone who joined has been notified.');
        redirect('dashboard.php');
    }

    if ($action === 'remove') {
        $target = (int) ($_POST['user_id'] ?? 0);
        qx("DELETE FROM plan_participants WHERE plan_id = ? AND user_id = ?", [$plan['id'], $target]);
        refresh_plan_count((int) $plan['id']);
        notify($target, '⚠️', 'You were removed from "' . $plan['title'] . '"', 'plan.php?s=' . $plan['slug']);
        flash('ok', 'Person removed from the plan.');
        redirect('plan-edit.php?id=' . $plan['id']);
    }

    // --- save ---
    $title     = trim((string) ($_POST['title'] ?? ''));
    $venue     = trim((string) ($_POST['venue'] ?? ''));
    $desc      = trim((string) ($_POST['description'] ?? ''));
    $capacity  = (int) ($_POST['capacity'] ?? 0);
    $startsAt  = strtotime((string) ($_POST['starts_at'] ?? ''));
    $mapUrl    = trim((string) ($_POST['map_url'] ?? ''));

    if (mb_strlen($title) < 4)              $errors[] = 'Title is too short.';
    if ($venue === '')                       $errors[] = 'Meeting point is required.';
    if ($startsAt === false)                 $errors[] = 'Start time is invalid.';
    if ($capacity < (int) $plan['going_count']) {
        $errors[] = 'Capacity cannot be lower than the ' . (int) $plan['going_count'] . ' people already going.';
    }
    if ($mapUrl !== '' && !filter_var($mapUrl, FILTER_VALIDATE_URL)) $errors[] = 'Map link is not a valid URL.';

    if (!$errors) {
        qx("UPDATE plans SET title=?, venue=?, description=?, capacity=?, starts_at=?, map_url=? WHERE id=?",
           [$title, $venue, $desc ?: null, $capacity, date('Y-m-d H:i:s', $startsAt), $mapUrl ?: null, $plan['id']]);

        // Only bother people if something they'd act on actually moved.
        if ($startsAt !== strtotime($plan['starts_at']) || $venue !== $plan['venue']) {
            foreach (q("SELECT user_id FROM plan_participants WHERE plan_id = ? AND user_id <> ?", [$plan['id'], $me['id']]) as $p) {
                notify((int) $p['user_id'], '📝', 'Updated: "' . $title . '" — check the new time/place', 'plan.php?s=' . $plan['slug']);
            }
        }

        flash('ok', 'Plan updated.');
        redirect('plan-edit.php?id=' . $plan['id']);
    }
}

$people = q(
    "SELECT u.id, u.name, u.username, u.avatar, pp.status, pp.joined_at
       FROM plan_participants pp JOIN users u ON u.id = pp.user_id
      WHERE pp.plan_id = ? ORDER BY pp.joined_at",
    [$plan['id']]
);

$nav        = 'dashboard';
$page_title = 'Manage · ' . $plan['title'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:820px">
  <p class="breadcrumb"><a href="<?= e(plan_url($plan)) ?>">← Back to plan</a></p>
  <h1 style="font-size:1.7rem">Manage plan</h1>

  <?php if ($errors): ?>
    <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
  <?php endif; ?>

  <div class="statgrid" style="margin-bottom:22px">
    <div class="stat"><div class="k">Going</div><div class="v grad"><?= (int) $plan['going_count'] ?></div></div>
    <div class="stat"><div class="k">Capacity</div><div class="v"><?= (int) $plan['capacity'] ?></div></div>
    <div class="stat"><div class="k">Views</div><div class="v"><?= (int) $plan['view_count'] ?></div></div>
    <div class="stat"><div class="k">Status</div><div class="v" style="font-size:1rem;padding-top:9px">
      <span class="pill <?= $plan['status'] === 'active' ? 'pill-ok' : 'pill-danger' ?>"><?= e($plan['status']) ?></span>
    </div></div>
  </div>

  <form method="post" class="panel" style="margin-bottom:22px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
    <input type="hidden" name="action" value="save">

    <div class="field">
      <label for="title">Title</label>
      <input class="input" type="text" name="title" id="title" maxlength="140" required value="<?= e($plan['title']) ?>">
    </div>

    <div class="field-row">
      <div class="field">
        <label for="venue">Meeting point</label>
        <input class="input" type="text" name="venue" id="venue" maxlength="160" required value="<?= e($plan['venue']) ?>">
      </div>
      <div class="field">
        <label for="starts_at">Starts</label>
        <input class="input" type="datetime-local" name="starts_at" id="starts_at" required
               value="<?= e(date('Y-m-d\TH:i', strtotime($plan['starts_at']))) ?>">
      </div>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="capacity">Capacity</label>
        <input class="input" type="number" name="capacity" id="capacity" min="<?= max(1, (int) $plan['going_count']) ?>" max="5000"
               required value="<?= (int) $plan['capacity'] ?>">
      </div>
      <div class="field">
        <label for="map_url">Map link</label>
        <input class="input" type="url" name="map_url" id="map_url" maxlength="400" value="<?= e($plan['map_url']) ?>">
      </div>
    </div>

    <div class="field">
      <label for="description">Description</label>
      <textarea class="textarea" name="description" id="description" maxlength="2000"><?= e($plan['description']) ?></textarea>
    </div>

    <div class="btn-row">
      <button class="btn btn-grad" type="submit">Save changes</button>
      <a class="btn btn-ghost" href="<?= url('chat.php?type=plan&id=' . (int) $plan['id']) ?>">Plan chat</a>
    </div>
  </form>

  <!-- People -->
  <h2 style="font-size:1.2rem">Who's going (<?= count($people) ?>)</h2>
  <div class="table-wrap" style="margin-bottom:22px">
    <table class="data">
      <thead><tr><th>Person</th><th>Status</th><th>Joined</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($people as $p): ?>
          <tr>
            <td>
              <a href="<?= url('profile.php?u=' . rawurlencode($p['username'])) ?>" style="display:flex;gap:9px;align-items:center">
                <?php render_avatar($p, 'xs'); ?><?= e($p['name']) ?>
              </a>
            </td>
            <td><span class="pill <?= $p['status'] === 'going' ? 'pill-ok' : 'pill-warn' ?>"><?= e($p['status']) ?></span></td>
            <td class="nowrap muted"><?= e(ago($p['joined_at'])) ?></td>
            <td>
              <?php if ((int) $p['id'] !== (int) $plan['host_id']): ?>
                <form method="post" data-confirm="Remove <?= e($p['name']) ?> from this plan?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="user_id" value="<?= (int) $p['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                </form>
              <?php else: ?>
                <span class="pill pill-info">Host</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Danger zone -->
  <?php if ($plan['status'] === 'active'): ?>
  <div class="panel" style="border-color:rgba(255,90,90,.3)">
    <h2 style="font-size:1.05rem">Cancel this plan</h2>
    <p class="muted" style="font-size:.87rem">Everyone who joined gets a notification. This cannot be undone.</p>
    <form method="post" data-confirm="Cancel this plan and notify everyone who joined?">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
      <input type="hidden" name="action" value="cancel">
      <button class="btn btn-danger" type="submit">Cancel plan</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
