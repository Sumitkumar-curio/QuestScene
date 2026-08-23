<?php
/**
 * QuestScene — site chrome (top bar + mobile bottom nav).
 * Set $page_title, $page_desc, $nav (active tab) before including.
 */

require_once __DIR__ . '/auth.php';

$me       = current_user();
$city     = current_city();
$nav      = $nav      ?? '';
$title    = isset($page_title) ? $page_title . ' · ' . SITE_NAME : SITE_NAME . ' — ' . SITE_TAGLINE;
$desc     = $page_desc ?? 'Discover what is happening around you, make plans, join communities, and actually go do things.';
$unread   = $me ? (int) qv("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0", [$me['id']]) : 0;
// Link previews must be raster — WhatsApp, Telegram and Facebook do not render SVG.
$ogImage  = $og_image ?? asset('img/og-default.png');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<script>
/* Apply the saved theme before first paint — otherwise a light-mode user
   gets a dark flash on every page load. Must stay inline and stay here. */
(function () {
  try {
    var t = localStorage.getItem('qs-theme');
    if (!t) t = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', t);
  } catch (e) {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#071018" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#FBF9F7" media="(prefers-color-scheme: light)">

<meta property="og:type"        content="website">
<meta property="og:site_name"   content="<?= e(SITE_NAME) ?>">
<meta property="og:title"       content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:image"       content="<?= e($ogImage) ?>">
<meta name="twitter:card"       content="summary_large_image">

<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="manifest" href="<?= url('manifest.json') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>?v=2">
<?php foreach (($page_css ?? []) as $sheet): ?>
  <link rel="stylesheet" href="<?= asset('css/' . $sheet) ?>?v=1">
<?php endforeach; ?>
<script>window.QS = { url: <?= json_encode(SITE_URL) ?>, csrf: <?= json_encode(csrf_token()) ?>, uid: <?= (int) ($me['id'] ?? 0) ?> };</script>
</head>
<body class="<?= $nav === 'home' ? 'is-home' : '' ?>">

<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">
  <div class="topbar-inner wrap">

    <a class="logo" href="<?= url('index.php') ?>" aria-label="QuestScene home">
      <?= logo_lockup() ?>
    </a>

    <nav class="topnav" aria-label="Primary">
      <a href="<?= url('discover.php') ?>"    class="<?= $nav === 'discover'  ? 'active' : '' ?>">Discover</a>
      <a href="<?= url('our-events.php') ?>" class="<?= $nav === 'events' ? 'active' : '' ?>">Our Events</a>
      <a href="<?= url('communities.php') ?>" class="<?= $nav === 'communities' ? 'active' : '' ?>">Communities</a>
      <a href="<?= url('forum.php') ?>"       class="<?= $nav === 'forum'     ? 'active' : '' ?>">Forum</a>
      <a href="<?= url('play.php') ?>"        class="<?= $nav === 'play'      ? 'active' : '' ?>">Play</a>
      <?php if ($me): ?>
        <a href="<?= url('chat.php') ?>"      class="<?= $nav === 'chat'      ? 'active' : '' ?>">Chat</a>
      <?php endif; ?>
    </nav>

    <div class="topbar-right">

      <form class="city-picker" method="get" action="<?= url('discover.php') ?>">
        <span aria-hidden="true">📍</span>
        <select name="city" onchange="this.form.submit()" aria-label="Change city">
          <?php foreach (CITIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $c === $city ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <a class="icon-btn" href="<?= url('discover.php') ?>" aria-label="Search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      </a>

      <button class="icon-btn js-theme" type="button" aria-label="Switch between dark and light mode">
        <svg class="ico-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 13A9 9 0 1111 3a7 7 0 0010 10z"/></svg>
        <svg class="ico-sun"  viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></svg>
      </button>

      <?php if ($me): ?>
        <a class="icon-btn" href="<?= url('notifications.php') ?>" aria-label="Notifications">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 10-12 0c0 7-2 8-2 8h16s-2-1-2-8"/><path d="M13.7 20a2 2 0 01-3.4 0"/></svg>
          <?php if ($unread): ?><span class="dot-badge"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </a>

        <a class="btn btn-grad btn-sm hide-sm" href="<?= url('create.php') ?>">+ Create Plan</a>

        <details class="menu">
          <summary aria-label="Your account">
            <?php if ($av = avatar_url($me)): ?>
              <img class="avatar avatar-sm" src="<?= e($av) ?>" alt="">
            <?php else: ?>
              <span class="avatar avatar-sm avatar-fallback"><?= e(initials($me['name'])) ?></span>
            <?php endif; ?>
          </summary>
          <div class="menu-panel">
            <div class="menu-head">
              <strong><?= e($me['name']) ?></strong>
              <span>@<?= e($me['username']) ?></span>
            </div>
            <a href="<?= url('dashboard.php') ?>">My QuestScene</a>
            <a href="<?= url('profile.php?u=' . rawurlencode($me['username'])) ?>">Profile</a>
            <a href="<?= url('saved.php') ?>">Saved plans</a>
            <a href="<?= url('play.php') ?>">Play</a>
            <a href="<?= url('settings.php') ?>">Settings</a>
            <?php if (is_organizer()): ?>
              <a href="<?= url('organizer.php') ?>">Organizer dashboard</a>
            <?php endif; ?>
            <?php if (is_admin()): ?>
              <a href="<?= url('admin/index.php') ?>">Admin panel</a>
            <?php endif; ?>
            <hr>
            <a href="<?= url('logout.php') ?>">Log out</a>
          </div>
        </details>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?= url('login.php') ?>">Log in</a>
        <a class="btn btn-grad btn-sm" href="<?= url('register.php') ?>">Join free</a>
      <?php endif; ?>

    </div>
  </div>
</header>

<?php foreach (take_flashes() as $f): ?>
  <div class="wrap"><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div></div>
<?php endforeach; ?>

<main id="main">
