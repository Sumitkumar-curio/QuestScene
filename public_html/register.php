<?php
/**
 * QuestScene — Sign up.
 * Onboarding target: under 60 seconds. Name, email, password, city, interests.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';

if (is_logged_in()) redirect('dashboard.php');

$errors = [];
$in = [
  'name'  => trim((string) ($_POST['name']  ?? '')),
  'email' => trim((string) ($_POST['email'] ?? '')),
  'city'  => (string) ($_POST['city'] ?? DEFAULT_CITY),
];
$picked = array_map('intval', (array) ($_POST['interests'] ?? []));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $password = (string) ($_POST['password'] ?? '');

    if (!rate_limit('register', 5, 900)) {
        $errors[] = 'Too many sign-up attempts. Wait a few minutes.';
    }
    if (mb_strlen($in['name']) < 2 || mb_strlen($in['name']) > 80) {
        $errors[] = 'Tell us your name (2–80 characters).';
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look right.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password needs at least 8 characters.';
    }
    if (!in_array($in['city'], CITIES, true)) {
        $errors[] = 'Pick your city from the list.';
    }
    if (!$errors && qv("SELECT id FROM users WHERE email = ?", [$in['email']])) {
        $errors[] = 'That email is already registered. Log in instead?';
    }

    if (!$errors) {
        // Derive a handle from the name, then make it unique.
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($in['name'])) ?: 'quester';
        $base = substr($base, 0, 26);
        $username = $base;
        $n = 0;
        while (qv("SELECT id FROM users WHERE username = ?", [$username])) {
            $username = $base . (++$n);
        }

        $userId = qi(
            "INSERT INTO users (name, username, email, password_hash, city, role)
             VALUES (?, ?, ?, ?, ?, 'user')",
            [$in['name'], $username, $in['email'], password_hash($password, PASSWORD_DEFAULT), $in['city']]
        );

        foreach (array_slice($picked, 0, 12) as $catId) {
            qx("INSERT IGNORE INTO user_interests (user_id, category_id) VALUES (?, ?)", [$userId, $catId]);
        }

        notify($userId, '👋', 'Welcome to QuestScene. Find something worth doing this week.', 'discover.php');

        login_user($userId);
        $_SESSION['city'] = $in['city'];
        flash('ok', 'Welcome to QuestScene, ' . explode(' ', $in['name'])[0] . '.');

        // Only follow relative in-site paths, never an attacker-supplied absolute URL.
        $next = (string) ($_POST['next'] ?? '');
        redirect($next !== '' && !preg_match('~^(https?:)?//~', $next) ? $next : 'dashboard.php');
    }
}

$nav        = '';
$page_title = 'Join QuestScene';
$page_desc  = 'Create a free QuestScene account and start joining plans in your city.';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Join <span class="grad-text">QuestScene</span></h1>
    <p class="lede">Free, and under a minute. Then go do something.</p>

    <?php if ($errors): ?>
      <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e((string) ($_GET['next'] ?? '')) ?>">

      <div class="field">
        <label for="name">Your name</label>
        <input class="input" type="text" name="name" id="name" required maxlength="80"
               autocomplete="name" value="<?= e($in['name']) ?>" placeholder="Sumit Kumar">
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" name="email" id="email" required maxlength="160"
               autocomplete="email" value="<?= e($in['email']) ?>" placeholder="you@example.com">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" name="password" id="password" required minlength="8"
               autocomplete="new-password" placeholder="At least 8 characters">
      </div>

      <div class="field">
        <label for="city">Where are you?</label>
        <select class="select" name="city" id="city">
          <?php foreach (CITIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $c === $in['city'] ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <span class="field-label">What do you enjoy? <span class="muted">(pick a few)</span></span>
        <div class="optgrid">
          <?php foreach (all_categories() as $c): ?>
            <input type="checkbox" name="interests[]" id="int<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>"
                   <?= in_array((int) $c['id'], $picked, true) ? 'checked' : '' ?>>
            <label for="int<?= (int) $c['id'] ?>"><span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="btn btn-grad btn-lg btn-block" type="submit">Create account</button>
    </form>

    <p class="auth-alt">Already here? <a href="<?= url('login.php') ?>">Log in</a></p>
    <p class="muted center" style="font-size:.74rem;margin-top:14px">
      By joining you agree to keep QuestScene a place people feel safe showing up to.
      See <a href="<?= url('safety.php') ?>" style="color:var(--brand-3)">Trust &amp; Safety</a>.
    </p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
