<?php
/**
 * QuestScene — Contact us.
 * No contact form on purpose: shared hosting mail() lands in spam more often
 * than it lands in an inbox. A real mailto and real social links work better.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

$nav        = '';
$page_title = 'Contact us';
$page_desc  = 'Get in touch with QuestScene — email ' . CONTACT_EMAIL . ', or find us on Instagram and Telegram at ' . SOCIAL_HANDLE . '.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section" style="max-width:720px">

  <h1 style="font-size:clamp(1.8rem,6vw,2.6rem)">Contact <span class="grad-text">us</span></h1>
  <p style="font-size:1.03rem;color:var(--text-2)">
    Running a community and want it on QuestScene? Found a bug? Something felt unsafe?
    Write to us — a real person reads it.
  </p>

  <div class="panel" style="margin:24px 0">
    <h2 style="font-size:1.15rem">Reach us</h2>
    <?php render_social(); ?>
    <p class="muted" style="font-size:.85rem;margin:16px 0 0">
      Same handle everywhere: <strong style="color:var(--brand-2)"><?= e(SOCIAL_HANDLE) ?></strong>.
      Email is the most reliable — we answer within a couple of days.
    </p>
  </div>

  <h2>What to write to us about</h2>
  <div class="factlist" style="margin-bottom:24px">
    <div class="fact"><span style="font-size:1.05rem">👥</span><div>
      <div class="fact-k">Bringing your community over</div>
      <div class="fact-v">Already running a WhatsApp or Telegram group? Tell us roughly how many people and what you do, and we'll get you set up with an organizer account.</div>
    </div></div>
    <div class="fact"><span style="font-size:1.05rem">🎟</span><div>
      <div class="fact-k">Getting your event listed</div>
      <div class="fact-v">Send the date, place, capacity and a photo. We can publish it for you while you get your own account going.</div>
    </div></div>
    <div class="fact"><span style="font-size:1.05rem">✓</span><div>
      <div class="fact-k">Verification</div>
      <div class="fact-v">Want the verified badge for your community? We'll check who runs it and that your meet-ups actually happen.</div>
    </div></div>
    <div class="fact"><span style="font-size:1.05rem">🚩</span><div>
      <div class="fact-k">Safety concerns</div>
      <div class="fact-v">Use the Report button on the plan or profile first — that goes straight into our review queue. Email us too if it is urgent.</div>
    </div></div>
    <div class="fact"><span style="font-size:1.05rem">🐛</span><div>
      <div class="fact-k">Bugs</div>
      <div class="fact-v">Tell us what you tapped, what you expected, and what happened instead. A screenshot helps enormously.</div>
    </div></div>
  </div>

  <div class="panel" style="border-color:rgba(249,115,22,.35)">
    <h2 style="font-size:1.05rem">One thing we will never do</h2>
    <p class="text-2" style="margin:0">
      We will never ask you to pay <em>through</em> QuestScene, and we will never ask for your
      password or an OTP. If a message claims to be from us and does either, it is a scam —
      forward it to <a href="mailto:<?= e(CONTACT_EMAIL) ?>" style="color:var(--brand-2)"><?= e(CONTACT_EMAIL) ?></a>.
    </p>
  </div>

  <div class="cta-band" style="margin-top:26px">
    <h2>Step. Out. Stand Out.</h2>
    <p>While you wait for a reply, there's probably something on this week.</p>
    <a class="btn btn-grad btn-lg" href="<?= url('discover.php') ?>">See what's on</a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
