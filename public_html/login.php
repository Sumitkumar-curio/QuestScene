<?php
/**
 * QuestScene — Log in.
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect('dashboard.php');

$errors = [];
$email  = trim((string) ($_POST['email'] ?? ''));
$next   = (string) ($_POST['next'] ?? $_GET['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $password = (string) ($_POST['password'] ?? '');

    if (!rate_limit('login', 8, 900)) {
        $errors[] = 'Too many attempts. Wait a few minutes and try again.';
    } else {
        $user = q1("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Same message either way — never reveal which emails exist.
            $errors[] = 'Email or password is wrong.';
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'This account is suspended. Contact us if you think that is a mistake.';
        } else {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                qx("UPDATE users SET password_hash = ? WHERE id = ?",
                   [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            }
            login_user((int) $user['id']);
            $_SESSION['city'] = $user['city'];

            // Only follow relative in-site paths, never an attacker's absolute URL.
            $safeNext = ($next !== '' && !preg_match('~^(https?:)?//~', $next)) ? $next : 'dashboard.php';
            redirect($safeNext);
        }
    }
}

$nav        = '';
$page_title = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="lede">Something's happening near you right now.</p>

    <?php if ($errors): ?>
      <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" name="email" id="email" required autocomplete="email"
               value="<?= e($email) ?>" placeholder="you@example.com">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" name="password" id="password" required
               autocomplete="current-password" placeholder="Your password">
      </div>

      <button class="btn btn-grad btn-lg btn-block" type="submit">Log in</button>
    </form>

    <p class="auth-alt">New here? <a href="<?= url('register.php') ?>">Create a free account</a></p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
