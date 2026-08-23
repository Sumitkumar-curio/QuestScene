<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

$nav        = '';
$page_title = 'About';
$page_desc  = 'QuestScene helps people discover what is happening around them, make plans, join communities, and actually go do things.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section" style="max-width:740px">

  <h1 style="font-size:clamp(1.8rem,6vw,2.8rem)">QuestScene is an <span class="grad-text">operating layer</span> for real life in a city.</h1>

  <p style="font-size:1.05rem;color:var(--text-2)">
    Not a feed. Not a ticketing site. Not a dating app. You open QuestScene to see what you can do,
    where you can go, and what is happening around you — then you go and do it.
  </p>

  <hr class="divider">

  <h2>The one sentence</h2>
  <p class="text-2">
    QuestScene helps people discover what's happening around them, make plans, join communities,
    and actually go do things.
  </p>

  <h2>The loop we care about</h2>
  <div class="loop" style="justify-content:flex-start;margin-bottom:20px">
    <b>Discover</b><i>→</i><b>Join</b><i>→</i><b>Go</b><i>→</i><b>Share</b><i>→</i><b>Create</b><i>→</i><b>Invite</b><i>↺</i>
  </div>
  <p class="text-2">
    Everything else is secondary. If a feature does not make that loop turn faster, it waits.
  </p>

  <h2>What we deliberately do not do</h2>
  <div class="factlist" style="margin-bottom:22px">
    <div class="fact"><span>🚫</span><div><div class="fact-k">No matching</div>
      <div class="fact-v">You never "match" with a person here. The object is the activity, not the person.</div></div></div>
    <div class="fact"><span>🚫</span><div><div class="fact-k">No open DMs at launch</div>
      <div class="fact-v">Chat exists because you joined a plan or a community. That one rule removes most spam.</div></div></div>
    <div class="fact"><span>🚫</span><div><div class="fact-k">No infinite scroll</div>
      <div class="fact-v">The forum is for organising something, not for filling twenty minutes.</div></div></div>
  </div>

  <h2 id="roadmap">What's next</h2>
  <div class="factlist" style="margin-bottom:22px">
    <div class="fact"><span>✅</span><div><div class="fact-k">Now</div>
      <div class="fact-v">Plans, events, communities, forum, plan &amp; community chat, organizer dashboard, reporting.</div></div></div>
    <div class="fact"><span>🔜</span><div><div class="fact-k">Next</div>
      <div class="fact-v">Map view, phone OTP verification, richer notifications, direct chat gated behind real interaction.</div></div></div>
    <div class="fact"><span>🔭</span><div><div class="fact-k">Later</div>
      <div class="fact-v">Voice rooms for plans, Learn (teach &amp; workshops), Build (founders, projects, collaboration).</div></div></div>
  </div>

  <h2 id="contact">Find us</h2>
  <p class="text-2">
    Same handle everywhere — <strong style="color:var(--brand-2)"><?= e(SOCIAL_HANDLE) ?></strong>.
    We post the week's plans on Instagram and keep the day-to-day chatter on Telegram.
  </p>
  <?php render_social(); ?>
  <p class="text-2" style="margin-top:16px">
    Running a community and want it on QuestScene? Found a bug? Something feels unsafe?
    <a href="<?= url('contact.php') ?>" style="color:var(--brand-2)">Contact us</a> — a real person answers.
  </p>

  <div class="cta-band" style="margin-top:26px">
    <h2>Step. Out. Stand Out.</h2>
    <p>Find something worth doing this week.</p>
    <a class="btn btn-grad btn-lg" href="<?= url('discover.php') ?>">Explore plans</a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
