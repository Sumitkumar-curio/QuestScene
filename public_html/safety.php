<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/components.php';

$nav        = '';
$page_title = 'Trust & Safety';
$page_desc  = 'How QuestScene handles verification, reporting and blocking — and what our badges actually mean.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section" style="max-width:740px">

  <h1 style="font-size:clamp(1.8rem,6vw,2.6rem)">Trust &amp; <span class="grad-text">Safety</span></h1>
  <p style="font-size:1.05rem;color:var(--text-2)">
    People are meeting strangers in real places. That deserves more care than a feature checklist.
  </p>

  <hr class="divider">

  <h2>What our badges actually mean</h2>
  <p class="text-2">
    We only show a badge for a check we have genuinely performed. A badge you can award yourself is worthless.
  </p>

  <div class="factlist" style="margin-bottom:24px">
    <div class="fact"><span>🟢</span><div><div class="fact-k">Phone Verified</div>
      <div class="fact-v">A working phone number was confirmed for this account.</div></div></div>
    <div class="fact"><span>🔵</span><div><div class="fact-k">Identity Verified</div>
      <div class="fact-v">A QuestScene admin has checked a government-issued ID against this account.</div></div></div>
    <div class="fact"><span>🟣</span><div><div class="fact-k">Community Verified</div>
      <div class="fact-v">We confirmed who runs this community and that it holds real meet-ups.</div></div></div>
    <div class="fact"><span>⭐</span><div><div class="fact-k">QuestScene Verified Organizer</div>
      <div class="fact-v">A repeat organizer with a track record of plans that actually happened.</div></div></div>
  </div>

  <h2>Reporting and blocking</h2>
  <p class="text-2">
    Every plan, profile, community and post can be reported from its own page. Reports go straight
    to a queue an admin works through — they are not auto-dismissed. Blocking someone hides their
    chat messages from you immediately.
  </p>

  <h2>Why chat is restricted</h2>
  <p class="text-2">
    You get a chat room because you joined a plan or a community — not because you looked at someone's
    profile. Unrestricted direct messages are the fastest route to spam, scams and the exact
    "is this a dating app?" feeling QuestScene is built to avoid. Direct chat will only arrive
    once there are real interaction signals to gate it behind.
  </p>

  <h2>Meeting people safely</h2>
  <div class="factlist" style="margin-bottom:24px">
    <div class="fact"><span>📍</span><div><div class="fact-k">Public places</div>
      <div class="fact-v">First-time plans should meet somewhere public and easy to find.</div></div></div>
    <div class="fact"><span>👀</span><div><div class="fact-k">Check the host</div>
      <div class="fact-v">Look at how many plans they have hosted and whether they are verified.</div></div></div>
    <div class="fact"><span>💬</span><div><div class="fact-k">Use the plan chat</div>
      <div class="fact-v">Sort out logistics in the room where everyone can see them.</div></div></div>
    <div class="fact"><span>🙋</span><div><div class="fact-k">Tell someone</div>
      <div class="fact-v">Share the plan link with a friend before you go. That is what the link is for.</div></div></div>
    <div class="fact"><span>🚩</span><div><div class="fact-k">Trust your gut</div>
      <div class="fact-v">If something feels off, leave the plan and report it. No explanation needed.</div></div></div>
  </div>

  <h2>Payments</h2>
  <p class="text-2">
    QuestScene does not process payments yet. If a plan has a cost, it is settled directly between you
    and the host. Anyone asking you to pay <em>through</em> QuestScene is running a scam — report them.
  </p>

  <div class="cta-band" style="margin-top:26px">
    <h2>Something wrong?</h2>
    <p>Report it from the plan, profile or community page. An admin reviews every report.</p>
    <a class="btn btn-grad" href="<?= url('discover.php') ?>">Back to Discover</a>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
