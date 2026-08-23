<?php
/**
 * QuestScene admin — manage users.
 * This is where a community organizer gets promoted so they can post events.
 */

$admin_page = 'users';
require __DIR__ . '/_nav.php';

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uidTarget = (int) ($_POST['user_id'] ?? 0);
    $action    = (string) ($_POST['action'] ?? '');

    if ($uidTarget === (int) $me['id'] && in_array($action, ['suspend', 'demote'], true)) {
        flash('error', 'You cannot suspend or demote your own account.');
        redirect('admin/users.php');
    }

    $target = q1("SELECT id, name, role FROM users WHERE id = ?", [$uidTarget]);
    if ($target) {
        switch ($action) {
            case 'promote_organizer':
                qx("UPDATE users SET role = 'organizer' WHERE id = ?", [$uidTarget]);
                notify($uidTarget, '🎉', 'You are now a QuestScene organizer — you can publish events.', 'organizer.php');
                flash('ok', $target['name'] . ' is now an organizer.');
                break;
            case 'promote_admin':
                qx("UPDATE users SET role = 'admin' WHERE id = ?", [$uidTarget]);
                flash('ok', $target['name'] . ' is now an admin.');
                break;
            case 'demote':
                qx("UPDATE users SET role = 'user' WHERE id = ?", [$uidTarget]);
                flash('ok', $target['name'] . ' is back to a normal account.');
                break;
            case 'verify_phone':
                qx("UPDATE users SET phone_verified = 1 - phone_verified WHERE id = ?", [$uidTarget]);
                flash('ok', 'Phone verification toggled for ' . $target['name'] . '.');
                break;
            case 'verify_id':
                qx("UPDATE users SET id_verified = 1 - id_verified WHERE id = ?", [$uidTarget]);
                flash('ok', 'Identity verification toggled for ' . $target['name'] . '.');
                break;
            case 'suspend':
                qx("UPDATE users SET status = 'suspended' WHERE id = ?", [$uidTarget]);
                flash('ok', $target['name'] . ' suspended.');
                break;
            case 'reinstate':
                qx("UPDATE users SET status = 'active' WHERE id = ?", [$uidTarget]);
                flash('ok', $target['name'] . ' reinstated.');
                break;
        }
    }
    redirect('admin/users.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'role' => $_POST['role'] ?? ''])));
}

$q    = trim((string) ($_GET['q'] ?? ''));
$role = (string) ($_GET['role'] ?? '');

$where  = ['1=1'];
$params = [];
if ($q !== '')    { $where[] = "(name LIKE ? OR email LIKE ? OR username LIKE ?)"; $like = "%{$q}%"; array_push($params, $like, $like, $like); }
if ($role !== '') { $where[] = "role = ?"; $params[] = $role; }

$users = q(
    "SELECT u.*,
            (SELECT COUNT(*) FROM plans WHERE host_id = u.id) AS hosted,
            (SELECT COUNT(*) FROM plan_participants WHERE user_id = u.id AND status='going') AS joined
       FROM users u WHERE " . implode(' AND ', $where) . "
      ORDER BY u.id DESC LIMIT 100",
    $params
);
?>

<h1 style="font-size:1.8rem">Users</h1>
<p class="muted" style="margin-bottom:16px">
  Promote a community owner to <strong>organizer</strong> so they can publish events.
  Only tick a verification badge after you have actually checked it.
</p>

<form class="filters" method="get" style="margin-bottom:16px">
  <div class="filter-search">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, handle or email…">
  </div>
  <select class="select" name="role" onchange="this.form.submit()" style="max-width:160px">
    <option value="">All roles</option>
    <option value="user"      <?= $role === 'user'      ? 'selected' : '' ?>>Users</option>
    <option value="organizer" <?= $role === 'organizer' ? 'selected' : '' ?>>Organizers</option>
    <option value="admin"     <?= $role === 'admin'     ? 'selected' : '' ?>>Admins</option>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="table-wrap">
  <table class="data" style="min-width:900px">
    <thead><tr><th>Person</th><th>Email</th><th>City</th><th>Role</th><th>Verified</th><th>Activity</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (!$users): ?><tr><td colspan="7" class="muted">Nothing matches.</td></tr><?php endif; ?>
      <?php foreach ($users as $u): ?>
        <tr>
          <td>
            <a href="<?= url('profile.php?u=' . rawurlencode($u['username'])) ?>" style="display:flex;gap:9px;align-items:center">
              <?php render_avatar($u, 'xs'); ?>
              <span><?= e($u['name']) ?><br><span class="muted" style="font-size:.74rem">@<?= e($u['username']) ?></span></span>
            </a>
          </td>
          <td class="muted" style="font-size:.8rem"><?= e($u['email']) ?></td>
          <td class="nowrap"><?= e($u['city']) ?></td>
          <td>
            <span class="pill <?= $u['role'] === 'admin' ? 'pill-danger' : ($u['role'] === 'organizer' ? 'pill-info' : '') ?>"><?= e($u['role']) ?></span>
            <?php if ($u['status'] !== 'active'): ?><br><span class="pill pill-warn">suspended</span><?php endif; ?>
          </td>
          <td class="nowrap">
            <?= (int) $u['phone_verified'] ? '🟢' : '⚪' ?>
            <?= (int) $u['id_verified']    ? '🔵' : '⚪' ?>
          </td>
          <td class="nowrap muted" style="font-size:.8rem">
            <?= (int) $u['hosted'] ?> hosted<br><?= (int) $u['joined'] ?> joined
          </td>
          <td class="nowrap">
            <form method="post" style="display:flex;gap:5px;flex-wrap:wrap">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <input type="hidden" name="role" value="<?= e($role) ?>">

              <?php if ($u['role'] === 'user'): ?>
                <button class="btn btn-ghost btn-sm" name="action" value="promote_organizer" type="submit">→ Organizer</button>
              <?php else: ?>
                <button class="btn btn-ghost btn-sm" name="action" value="demote" type="submit">→ User</button>
              <?php endif; ?>

              <button class="btn btn-ghost btn-sm" name="action" value="verify_phone" type="submit">📱</button>
              <button class="btn btn-ghost btn-sm" name="action" value="verify_id" type="submit">🪪</button>

              <?php if ($u['status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm" name="action" value="suspend" type="submit">Suspend</button>
              <?php else: ?>
                <button class="btn btn-ok btn-sm" name="action" value="reinstate" type="submit">Reinstate</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
