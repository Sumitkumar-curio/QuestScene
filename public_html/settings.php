<?php
/**
 * QuestScene — account settings.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';

require_login('settings.php');
$me     = current_user();
$errors = [];

$myInterests = array_map('intval', array_column(
    q("SELECT category_id FROM user_interests WHERE user_id = ?", [$me['id']]), 'category_id'
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'profile');

    if ($action === 'profile') {
        $name     = trim((string) ($_POST['name'] ?? ''));
        $username = strtolower(trim((string) ($_POST['username'] ?? '')));
        $bio      = trim((string) ($_POST['bio'] ?? ''));
        $city     = (string) ($_POST['city'] ?? $me['city']);
        $phone    = trim((string) ($_POST['phone'] ?? ''));
        $picked   = array_map('intval', (array) ($_POST['interests'] ?? []));

        if (mb_strlen($name) < 2)                            $errors[] = 'Name is too short.';
        if (!preg_match('/^[a-z0-9_]{3,40}$/', $username))    $errors[] = 'Handle must be 3–40 characters: lowercase letters, numbers or underscore.';
        if (!in_array($city, CITIES, true))                  $errors[] = 'Pick a city from the list.';
        if ($phone !== '' && !preg_match('/^[0-9+\- ]{8,20}$/', $phone)) $errors[] = 'That phone number does not look right.';

        if (!$errors && qv("SELECT id FROM users WHERE username = ? AND id <> ?", [$username, $me['id']])) {
            $errors[] = 'That handle is taken.';
        }

        $avatar = $me['avatar'];
        if (!$errors) {
            try {
                $new = handle_upload('avatar', 'avatars');
                if ($new) $avatar = $new;
            } catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
        }

        if (!$errors) {
            // Changing the number invalidates any previous verification.
            $phoneVerified = ($phone === $me['phone']) ? (int) $me['phone_verified'] : 0;

            qx("UPDATE users SET name=?, username=?, bio=?, city=?, phone=?, phone_verified=?, avatar=? WHERE id=?",
               [$name, $username, $bio ?: null, $city, $phone ?: null, $phoneVerified, $avatar, $me['id']]);

            qx("DELETE FROM user_interests WHERE user_id = ?", [$me['id']]);
            foreach (array_slice($picked, 0, 12) as $catId) {
                qx("INSERT IGNORE INTO user_interests (user_id, category_id) VALUES (?, ?)", [$me['id'], $catId]);
            }

            $_SESSION['city'] = $city;
            flash('ok', 'Profile saved.');
            redirect('settings.php');
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');

        if (!password_verify($current, $me['password_hash'])) $errors[] = 'Current password is wrong.';
        if (strlen($new) < 8)                                 $errors[] = 'New password needs at least 8 characters.';

        if (!$errors) {
            qx("UPDATE users SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            flash('ok', 'Password changed.');
            redirect('settings.php');
        }
    }
}

$blocked = q("SELECT u.id, u.name, u.username FROM blocks b JOIN users u ON u.id = b.blocked_id WHERE b.user_id = ?", [$me['id']]);

$nav        = 'profile';
$page_title = 'Settings';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:700px">
  <h1 style="font-size:1.8rem">Settings</h1>

  <?php if ($errors): ?>
    <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
  <?php endif; ?>

  <!-- Profile -->
  <form method="post" enctype="multipart/form-data" class="panel" style="margin-bottom:20px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="profile">
    <h2 style="font-size:1.15rem">Profile</h2>

    <div class="field-row">
      <div class="field">
        <label for="name">Name</label>
        <input class="input" type="text" name="name" id="name" maxlength="80" required value="<?= e($me['name']) ?>">
      </div>
      <div class="field">
        <label for="username">Handle</label>
        <input class="input" type="text" name="username" id="username" maxlength="40" required
               pattern="[a-z0-9_]{3,40}" value="<?= e($me['username']) ?>">
        <span class="hint">questscene.com/profile.php?u=<?= e($me['username']) ?></span>
      </div>
    </div>

    <div class="field">
      <label for="bio">Bio</label>
      <textarea class="textarea" name="bio" id="bio" maxlength="400" style="min-height:80px"
                placeholder="Runner, weekend trekker, terrible at badminton."><?= e($me['bio']) ?></textarea>
    </div>

    <div class="field-row">
      <div class="field">
        <label for="city">City</label>
        <select class="select" name="city" id="city">
          <?php foreach (CITIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $c === $me['city'] ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="phone">Phone</label>
        <input class="input" type="tel" name="phone" id="phone" maxlength="20" value="<?= e($me['phone']) ?>" placeholder="+91 …">
        <span class="hint">
          <?= (int) $me['phone_verified'] ? '🟢 Verified' : 'Not verified yet — hosts trust verified people more.' ?>
        </span>
      </div>
    </div>

    <div class="field">
      <label for="avatar">Profile photo</label>
      <input class="input" type="file" name="avatar" id="avatar" accept="image/*">
    </div>

    <div class="field">
      <span class="field-label">Your interests</span>
      <div class="optgrid">
        <?php foreach (all_categories() as $c): ?>
          <input type="checkbox" name="interests[]" id="si<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>"
                 <?= in_array((int) $c['id'], $myInterests, true) ? 'checked' : '' ?>>
          <label for="si<?= (int) $c['id'] ?>"><span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?></label>
        <?php endforeach; ?>
      </div>
      <span class="hint">These drive what shows up on your dashboard.</span>
    </div>

    <button class="btn btn-grad" type="submit">Save profile</button>
  </form>

  <!-- Password -->
  <form method="post" class="panel" style="margin-bottom:20px">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2 style="font-size:1.15rem">Change password</h2>

    <div class="field-row">
      <div class="field">
        <label for="current_password">Current password</label>
        <input class="input" type="password" name="current_password" id="current_password" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input class="input" type="password" name="new_password" id="new_password" required minlength="8" autocomplete="new-password">
      </div>
    </div>

    <button class="btn btn-ghost" type="submit">Update password</button>
  </form>

  <!-- Verification status -->
  <div class="panel" style="margin-bottom:20px">
    <h2 style="font-size:1.15rem">Verification</h2>
    <p class="muted" style="font-size:.87rem">
      QuestScene only shows a badge for checks we have actually done. Nothing here is claimed on your behalf.
    </p>
    <div class="factlist">
      <div class="fact"><span style="font-size:1.05rem">📧</span><div>
        <div class="fact-k">Email</div>
        <div class="fact-v"><?= (int) $me['email_verified'] ? 'Verified' : 'Not verified yet' ?></div>
      </div></div>
      <div class="fact"><span style="font-size:1.05rem">📱</span><div>
        <div class="fact-k">Phone</div>
        <div class="fact-v"><?= (int) $me['phone_verified'] ? 'Verified' : ($me['phone'] ? 'Pending review' : 'Add a number above') ?></div>
      </div></div>
      <div class="fact"><span style="font-size:1.05rem">🪪</span><div>
        <div class="fact-k">Identity</div>
        <div class="fact-v"><?= (int) $me['id_verified'] ? 'Verified' : 'Available to organizers on request' ?></div>
      </div></div>
    </div>
  </div>

  <!-- Blocked -->
  <?php if ($blocked): ?>
  <div class="panel">
    <h2 style="font-size:1.15rem">Blocked people</h2>
    <?php foreach ($blocked as $b): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-soft)">
        <span><?= e($b['name']) ?> <span class="muted">@<?= e($b['username']) ?></span></span>
      </div>
    <?php endforeach; ?>
    <p class="muted" style="font-size:.82rem;margin:12px 0 0">You will not see their chat messages. Contact us to unblock.</p>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
