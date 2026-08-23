<?php
/**
 * QuestScene admin — trust & safety queue.
 */

$admin_page = 'reports';
require __DIR__ . '/_nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rid    = (int) ($_POST['report_id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');

    if (in_array($status, ['open', 'reviewing', 'resolved', 'dismissed'], true)) {
        qx("UPDATE reports SET status = ? WHERE id = ?", [$status, $rid]);
        flash('ok', 'Report marked ' . $status . '.');
    }
    redirect('admin/reports.php');
}

$filter = (string) ($_GET['status'] ?? 'open');
$where  = $filter !== 'all' ? "WHERE r.status = ?" : '';
$params = $filter !== 'all' ? [$filter] : [];

$reports = q(
    "SELECT r.*, u.name AS reporter_name, u.username AS reporter_username
       FROM reports r JOIN users u ON u.id = r.reporter_id
       {$where}
      ORDER BY FIELD(r.status,'open','reviewing','resolved','dismissed'), r.created_at DESC
      LIMIT 100",
    $params
);

/** Resolve a report target into a label + link. */
function report_target(string $type, int $id): array
{
    switch ($type) {
        case 'plan':
            $p = q1("SELECT title, slug FROM plans WHERE id = ?", [$id]);
            return $p ? [$p['title'], url('plan.php?s=' . rawurlencode($p['slug']))] : ['Deleted plan #' . $id, null];
        case 'user':
            $u = q1("SELECT name, username FROM users WHERE id = ?", [$id]);
            return $u ? [$u['name'], url('profile.php?u=' . rawurlencode($u['username']))] : ['Deleted user #' . $id, null];
        case 'community':
            $g = q1("SELECT name, slug FROM communities WHERE id = ?", [$id]);
            return $g ? [$g['name'], url('community.php?s=' . rawurlencode($g['slug']))] : ['Deleted community #' . $id, null];
        case 'post':
            $p = q1("SELECT body FROM posts WHERE id = ?", [$id]);
            return $p ? [excerpt($p['body'], 50), url('post.php?id=' . $id)] : ['Deleted post #' . $id, null];
        default:
            return [ucfirst($type) . ' #' . $id, null];
    }
}
?>

<h1 style="font-size:1.8rem">Reports</h1>
<p class="muted" style="margin-bottom:16px">Every report a member files lands here. Work the open queue first.</p>

<div class="filter-pills" style="margin-bottom:16px">
  <?php foreach (['open', 'reviewing', 'resolved', 'dismissed', 'all'] as $f): ?>
    <a class="fpill <?= $filter === $f ? 'active' : '' ?>" href="<?= url('admin/reports.php?status=' . $f) ?>"><?= ucfirst($f) ?></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table class="data" style="min-width:860px">
    <thead><tr><th>What</th><th>Reason</th><th>Details</th><th>Reported by</th><th>When</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php if (!$reports): ?>
        <tr><td colspan="7" class="muted">Nothing in this queue. Good sign.</td></tr>
      <?php endif; ?>

      <?php foreach ($reports as $r): ?>
        <?php [$label, $link] = report_target($r['target_type'], (int) $r['target_id']); ?>
        <tr>
          <td>
            <span class="pill pill-info"><?= e($r['target_type']) ?></span><br>
            <?php if ($link): ?><a href="<?= e($link) ?>"><?= e($label) ?></a><?php else: ?><span class="muted"><?= e($label) ?></span><?php endif; ?>
          </td>
          <td class="nowrap"><?= e($r['reason']) ?></td>
          <td style="max-width:280px"><span class="muted" style="font-size:.82rem"><?= e(excerpt($r['details'], 140)) ?></span></td>
          <td class="nowrap"><a href="<?= url('profile.php?u=' . rawurlencode($r['reporter_username'])) ?>"><?= e($r['reporter_name']) ?></a></td>
          <td class="nowrap muted"><?= e(ago($r['created_at'])) ?></td>
          <td>
            <span class="pill <?= match ($r['status']) {
                'open' => 'pill-danger', 'reviewing' => 'pill-warn', 'resolved' => 'pill-ok', default => ''
            } ?>"><?= e($r['status']) ?></span>
          </td>
          <td class="nowrap">
            <form method="post" style="display:flex;gap:5px;flex-wrap:wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
              <?php if ($r['status'] === 'open'): ?>
                <button class="btn btn-ghost btn-sm" name="status" value="reviewing" type="submit">Reviewing</button>
              <?php endif; ?>
              <button class="btn btn-ok btn-sm"    name="status" value="resolved"  type="submit">Resolve</button>
              <button class="btn btn-ghost btn-sm" name="status" value="dismissed" type="submit">Dismiss</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<p class="muted" style="font-size:.83rem;margin-top:16px">
  Acting on a report usually means going to
  <a href="<?= url('admin/plans.php') ?>" style="color:var(--brand-3)">Plans</a> or
  <a href="<?= url('admin/users.php') ?>" style="color:var(--brand-3)">Users</a> and hiding or suspending the target.
</p>

<?php require __DIR__ . '/_foot.php'; ?>
