<?php
/**
 * QuestScene admin — manage plans.
 */

$admin_page = 'plans';
require __DIR__ . '/_nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid    = (int) ($_POST['plan_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    $plan = q1("SELECT id, title, slug, host_id FROM plans WHERE id = ?", [$pid]);
    if ($plan) {
        switch ($action) {
            case 'hide':
                qx("UPDATE plans SET status = 'hidden' WHERE id = ?", [$pid]);
                notify((int) $plan['host_id'], '⚠️', 'Your plan "' . $plan['title'] . '" was hidden by QuestScene.', null);
                flash('ok', 'Plan hidden.');
                break;
            case 'restore':
                qx("UPDATE plans SET status = 'active' WHERE id = ?", [$pid]);
                flash('ok', 'Plan restored.');
                break;
            case 'verify':
                qx("UPDATE plans SET verified = 1 - verified WHERE id = ?", [$pid]);
                flash('ok', 'Verification flag toggled.');
                break;
            case 'delete':
                qx("DELETE FROM plans WHERE id = ?", [$pid]);
                flash('ok', 'Plan deleted permanently.');
                break;
        }
    }
    redirect('admin/plans.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'status' => $_POST['status'] ?? ''])));
}

$q      = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');

$where  = ['1=1'];
$params = [];
if ($q !== '')      { $where[] = "(p.title LIKE ? OR p.venue LIKE ? OR u.name LIKE ?)"; $like = "%{$q}%"; array_push($params, $like, $like, $like); }
if ($status !== '') { $where[] = "p.status = ?"; $params[] = $status; }

$plans = q(PLAN_SELECT . " WHERE " . implode(' AND ', $where) . " ORDER BY p.created_at DESC LIMIT 100", $params);
?>

<h1 style="font-size:1.8rem">Plans</h1>

<form class="filters" method="get" style="margin-bottom:16px">
  <div class="filter-search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search title, venue or host…">
  </div>
  <select class="select" name="status" onchange="this.form.submit()" style="max-width:170px">
    <option value="">All statuses</option>
    <option value="active"    <?= $status === 'active'    ? 'selected' : '' ?>>Active</option>
    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    <option value="hidden"    <?= $status === 'hidden'    ? 'selected' : '' ?>>Hidden</option>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Plan</th><th>Host</th><th>City</th><th>When</th><th>Going</th><th>Views</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$plans): ?><tr><td colspan="8" class="muted">Nothing matches.</td></tr><?php endif; ?>
      <?php foreach ($plans as $p): ?>
        <tr>
          <td>
            <a href="<?= e(plan_url($p)) ?>"><?= e($p['cat_emoji']) ?> <?= e(excerpt($p['title'], 42)) ?></a>
            <?php if ((int) $p['verified']): ?> <span class="verified-tick">✓</span><?php endif; ?>
            <?php if ((int) $p['is_event']): ?> <span class="pill pill-info">Event</span><?php endif; ?>
          </td>
          <td class="nowrap"><a href="<?= url('profile.php?u=' . rawurlencode($p['host_username'])) ?>"><?= e($p['host_name']) ?></a></td>
          <td class="nowrap"><?= e($p['city']) ?></td>
          <td class="nowrap muted"><?= e(fmt_when($p['starts_at'])) ?></td>
          <td class="nowrap"><?= (int) $p['going_count'] ?>/<?= (int) $p['capacity'] ?></td>
          <td><?= (int) $p['view_count'] ?></td>
          <td><span class="pill <?= $p['status'] === 'active' ? 'pill-ok' : ($p['status'] === 'hidden' ? 'pill-warn' : 'pill-danger') ?>"><?= e($p['status']) ?></span></td>
          <td class="nowrap">
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <input type="hidden" name="status" value="<?= e($status) ?>">
              <button class="btn btn-ghost btn-sm" name="action" value="verify" type="submit">
                <?= (int) $p['verified'] ? 'Unverify' : 'Verify' ?>
              </button>
              <?php if ($p['status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm" name="action" value="hide" type="submit">Hide</button>
              <?php else: ?>
                <button class="btn btn-ok btn-sm" name="action" value="restore" type="submit">Restore</button>
              <?php endif; ?>
            </form>
            <form method="post" style="display:inline" data-confirm="Delete “<?= e($p['title']) ?>” permanently? This cannot be undone.">
              <?= csrf_field() ?>
              <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn btn-danger btn-sm" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
