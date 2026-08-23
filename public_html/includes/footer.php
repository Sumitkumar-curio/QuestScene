</main>

<?php require_once __DIR__ . '/components.php'; ?>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div class="footer-brand">
      <a class="logo" href="<?= url('index.php') ?>">
        <?= logo_lockup(false, 34) ?>
      </a>
      <p class="tagline">Step. <em>Out.</em> Stand Out.</p>
      <p class="muted">Discover what's happening around you, make plans, join communities, and actually go do things.</p>
      <?php render_social(); ?>
    </div>

    <div>
      <h4>Discover</h4>
      <a href="<?= url('discover.php') ?>">All plans</a>
      <a href="<?= url('discover.php?tab=events') ?>">Events</a>
      <a href="<?= url('our-events.php') ?>">What we've run</a>
      <a href="<?= url('communities.php') ?>">Communities</a>
      <a href="<?= url('forum.php') ?>">Forum</a>
      <a href="<?= url('play.php') ?>">Play</a>
    </div>

    <div>
      <h4>Create</h4>
      <a href="<?= url('create.php') ?>">Create a plan</a>
      <a href="<?= url('community-new.php') ?>">Start a community</a>
      <a href="<?= url('organizer.php') ?>">For organizers</a>
    </div>

    <div>
      <h4>QuestScene</h4>
      <a href="<?= url('about.php') ?>">About us</a>
      <a href="<?= url('contact.php') ?>">Contact us</a>
      <a href="<?= url('safety.php') ?>">Trust &amp; Safety</a>
      <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a>
    </div>
  </div>

  <div class="wrap footer-bottom">
    <span>© <?= date('Y') ?> QuestScene · Bangalore, India</span>
    <span class="muted">Built for people who'd rather be outside.</span>
  </div>
</footer>

<?php $me = current_user(); ?>
<nav class="bottomnav" aria-label="Mobile">
  <a href="<?= url('index.php') ?>" class="<?= ($nav ?? '') === 'home' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/></svg>
    <span>Home</span>
  </a>
  <a href="<?= url('discover.php') ?>" class="<?= in_array(($nav ?? ''), ['discover','events'], true) ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    <span>Discover</span>
  </a>
  <a href="<?= url('create.php') ?>" class="bn-create" aria-label="Create a plan">
    <span class="bn-fab">+</span>
  </a>
  <a href="<?= url('chat.php') ?>" class="<?= ($nav ?? '') === 'chat' ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a8 8 0 01-8 8H4l2.2-2.6A8 8 0 1121 12z"/></svg>
    <span>Chat</span>
  </a>
  <a href="<?= $me ? url('dashboard.php') : url('login.php') ?>" class="<?= in_array(($nav ?? ''), ['dashboard','profile'], true) ? 'active' : '' ?>">
    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0115 0"/></svg>
    <span><?= $me ? 'You' : 'Log in' ?></span>
  </a>
</nav>

<script src="<?= asset('js/app.js') ?>?v=1" defer></script>
</body>
</html>
