<?php
/**
 * QuestScene admin — manage communities.
 */

$admin_page = 'communities';
require __DIR__ . '/_nav.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cid    = (int) ($_POST['community_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    $g = q1("SELECT id, name, owner_id, slug FROM communities WHERE id = ?", [$cid]);
    if ($g) {
        switch ($action) {
            case 'verify':
                qx("UPDATE communities SET verified = 1 - verified WHERE id = ?", [$cid]);
                $isNow = (int) qv("SELECT verified FROM communities WHERE id = ?", [$cid]);
                if ($isNow) {
                    notify((int) $g['owner_id'], '🟣', $g['name'] . ' is now QuestScene Verified.', 'community.php?s=' . $g['slug']);
                }
                flash('ok', 'Verification toggled for ' . $g['name'] . '.');
                break;
            case 'hide':
                qx("UPDATE communities SET status = 'hidden' WHERE id = ?", [$cid]);
                flash('ok', $g['name'] . ' hidden.');
                break;
            case 'restore':
                qx("UPDATE communities SET status = 'active' WHERE id = ?", [$cid]);
                flash('ok', $g['name'] . ' restored.');
                break;
            case 'recount':
                refresh_community_count($cid);
                flash('ok', 'Member count recalculated.');
                break;
        }
    }
    redirect('admin/communities.php');
}

$communities = q(
    "SELECT g.*, c.emoji AS cat_emoji, u.name AS owner_name, u.username AS owner_username,
            (SELECT COUNT(*) FROM plans p WHERE p.community_id = g.id AND p.status='active') AS plan_count
       FROM communities g
       LEFT JOIN categories c ON c.id = g.category_id
       LEFT JOIN users u      ON u.id = g.owner_id
      ORDER BY g.verified DESC, g.member_count DESC LIMIT 100"
);
?>

<h1 style="font-size:1.8rem">Communities</h1>
<p class="muted" style="margin-bottom:16px">
  Verify a community only after confirming who runs it. The badge is worth nothing if it is handed out freely.
</p>

<div class="table-wrap">
  <table class="data" style="min-width:860px">
    <thead><tr><th>Community</th><th>Owner</th><th>City</th><th>Members</th><th>Plans</th><th>Views</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$communities): ?><tr><td colspan="8" class="muted">No communities yet.</td></tr><?php endif; ?>
      <?php foreach ($communities as $g): ?>
        <tr>
          <td>
            <a href="<?= e(community_url($g)) ?>"><?= e($g['cat_emoji'] ?: '👥') ?> <?= e($g['name']) ?></a>
            <?php if ((int) $g['verified']): ?> <span class="verified-tick">✓</span><?php endif; ?>
          </td>
          <td class="nowrap"><a href="<?= url('profile.php?u=' . rawurlencode((string) $g['owner_username'])) ?>"><?= e($g['owner_name']) ?></a></td>
          <td class="nowrap"><?= e($g['city']) ?></td>
          <td><?= number_format((int) $g['member_count']) ?></td>
          <td><?= (int) $g['plan_count'] ?></td>
          <td><?= number_format((int) $g['view_count']) ?></td>
          <td><span class="pill <?= $g['status'] === 'active' ? 'pill-ok' : 'pill-warn' ?>"><?= e($g['status']) ?></span></td>
          <td class="nowrap">
            <form method="post" style="display:flex;gap:5px;flex-wrap:wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="community_id" value="<?= (int) $g['id'] ?>">
              <button class="btn btn-ghost btn-sm" name="action" value="verify" type="submit"><?= (int) $g['verified'] ? 'Unverify' : 'Verify' ?></button>
              <button class="btn btn-ghost btn-sm" name="action" value="recount" type="submit">Recount</button>
              <?php if ($g['status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm" name="action" value="hide" type="submit">Hide</button>
              <?php else: ?>
                <button class="btn btn-ok btn-sm" name="action" value="restore" type="submit">Restore</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
