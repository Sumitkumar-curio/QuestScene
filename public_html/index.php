<?php
/**
 * QuestScene — Home.
 * Answers one question above the fold: "what can I do today?"
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$city = current_city();
$me   = current_user();

$trending   = find_plans(['city' => $city, 'sort' => 'popular', 'when' => 'week', 'limit' => 6]);
$pastEvents = q(PLAN_SELECT . " WHERE p.status = 'active' AND p.starts_at < NOW()
                                ORDER BY p.starts_at DESC LIMIT 6");
$pastStats  = q1("SELECT COUNT(*) AS runs, COALESCE(SUM(going_count),0) AS people
                    FROM plans WHERE starts_at < NOW() AND status = 'active'");
$upcoming   = find_plans(['city' => $city, 'sort' => 'soon',    'limit' => 6]);
$events     = find_plans(['city' => $city, 'tab'  => 'events',  'limit' => 3]);
$communities = find_communities(['city' => $city, 'limit' => 6]);

// Live counters — a homepage that looks empty kills the product, so we
// only show the strip once there is something real behind it.
$statPlans   = (int) qv("SELECT COUNT(*) FROM plans WHERE city = ? AND status = 'active' AND starts_at >= NOW()", [$city]);
$statMembers = (int) qv("SELECT COUNT(*) FROM users WHERE city = ? AND status = 'active'", [$city]);
$statComms   = (int) qv("SELECT COUNT(*) FROM communities WHERE city = ? AND status = 'active'", [$city]);
$statGoing   = (int) qv("SELECT COUNT(*) FROM plan_participants pp JOIN plans p ON p.id = pp.plan_id
                          WHERE p.city = ? AND pp.status = 'going' AND p.starts_at >= NOW()", [$city]);

$nav        = 'home';
$page_desc  = "What's happening in {$city} this week — runs, treks, football, workshops and communities on QuestScene.";
require __DIR__ . '/includes/header.php';
?>

<!-- ================= HERO ================= -->
<section class="hero">
  <!-- Progressive enhancement: the CSS glow below is what everyone gets.
       hero3d.js only paints here if the device can afford it. -->
  <div id="hero3d" class="hero-3d" aria-hidden="true"
       data-lib="<?= asset('js/vendor/three.module.min.js') ?>"></div>

  <div class="wrap">
    <span class="hero-kicker"><span class="pulse"></span> <?= $statPlans ?> plans live in <?= e($city) ?></span>

    <h1><span class="grad-text">Step. Out.</span><br>Stand Out.</h1>
    <p class="hero-sub">
      QuestScene shows you what's happening around you — runs, treks, football, workshops,
      meet-ups — lets you join in one tap, and helps you start your own.
      <strong style="color:var(--text)">Free, and built for actually turning up.</strong>
    </p>

    <form class="hero-search" method="get" action="<?= url('discover.php') ?>" role="search">
      <svg viewBox="0 0 24 24" class="ico" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" placeholder="running, trekking, football, workshops…" aria-label="Search plans">
      <input type="hidden" name="city" value="<?= e($city) ?>">
      <button class="btn btn-grad btn-sm" type="submit">Search</button>
    </form>

    <div class="btn-row" style="margin-top:16px">
      <a class="btn btn-grad btn-lg" href="<?= url('create.php') ?>">+ Create a Plan</a>
      <a class="btn btn-ghost btn-lg" href="<?= url('discover.php') ?>">Explore <?= e($city) ?></a>
    </div>

    <?php if ($statMembers > 0): ?>
    <div class="hero-stats">
      <div class="hero-stat"><strong><?= number_format($statPlans) ?></strong><span>Upcoming plans</span></div>
      <div class="hero-stat"><strong><?= number_format($statGoing) ?></strong><span>People going</span></div>
      <div class="hero-stat"><strong><?= number_format($statComms) ?></strong><span>Communities</span></div>
      <div class="hero-stat"><strong><?= number_format($statMembers) ?></strong><span>Members</span></div>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ================= CATEGORIES ================= -->
<section class="section-tight">
  <div class="wrap">
    <div class="rail">
      <?php foreach (all_categories() as $c): ?>
        <a class="catpill" href="<?= url('discover.php?cat=' . e($c['slug']) . '&city=' . rawurlencode($city)) ?>">
          <span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ================= WHAT YOU GET ================= -->
<section class="section">
  <div class="wrap">
    <?php render_section_head(
      'What you get',
      'Everything below is free. No ticket fees, no subscription, no catch.'
    ); ?>

    <div class="offers">
      <div class="offer">
        <span class="offer-ico" aria-hidden="true">🗺️</span>
        <div class="offer-body">
          <h3>Everything on, in one place</h3>
          <p>Runs, treks, football, cycling, workshops, hangouts and founder meets in <?= e($city) ?> — with the time, the meeting point and who's already going.</p>
        </div>
      </div>

      <div class="offer">
        <span class="offer-ico" aria-hidden="true">✋</span>
        <div class="offer-body">
          <h3>Join in one tap</h3>
          <p>No forms, no tickets, no waiting for approval. See the spots left, tap Join, you're on the list.</p>
        </div>
      </div>

      <div class="offer">
        <span class="offer-ico" aria-hidden="true">💬</span>
        <div class="offer-body">
          <h3>A chat for every plan</h3>
          <p>Joining opens the group chat for that plan, so "who's bringing the ball" gets sorted with the people actually coming.</p>
        </div>
      </div>

      <div class="offer">
        <span class="offer-ico" aria-hidden="true">👥</span>
        <div class="offer-body">
          <h3>Communities that meet weekly</h3>
          <p>Running clubs, trek crews, football groups, founder circles. Join one and their plans land in your week automatically.</p>
        </div>
      </div>

      <div class="offer">
        <span class="offer-ico" aria-hidden="true">⚡</span>
        <div class="offer-body">
          <h3>Host your own with one link</h3>
          <p>Create a plan in about a minute and paste the link in your group. Friends can open it and see who's in <em>without</em> an account.</p>
        </div>
      </div>

      <div class="offer">
        <span class="offer-ico" aria-hidden="true">🛡️</span>
        <div class="offer-body">
          <h3>Safety built in, not bolted on</h3>
          <p>Verified hosts, no open DMs, and a Report button on every plan, profile and community that a real admin reviews.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ================= WHY JOIN ================= -->
<section class="section">
  <div class="wrap">
    <?php render_section_head(
      'Why join',
      'If any of these sound like you, this is what QuestScene is for.'
    ); ?>

    <div class="reasons">
      <div class="reason">
        <p class="quote">I'm new in this city and don't know anyone.</p>
        <p class="answer">Turn up to something you already enjoy. <strong>Meeting people over an activity is far easier than meeting them cold</strong> — you've got the whole thing to talk about.</p>
      </div>

      <div class="reason">
        <p class="quote">Our group chat never actually decides anything.</p>
        <p class="answer">Forty messages, no game. Drop a plan instead: <strong>one link, a time, a place, and a live list of who's in.</strong></p>
      </div>

      <div class="reason">
        <p class="quote">I want to start trekking, but not on my own.</p>
        <p class="answer">Most plans are beginner-friendly and say so. You can see <strong>how many people the host has taken out before</strong> you commit.</p>
      </div>

      <div class="reason">
        <p class="quote">My weekends keep disappearing.</p>
        <p class="answer">Open QuestScene on a Wednesday, pick one thing, and the weekend has a shape. <strong>That's the whole product.</strong></p>
      </div>
    </div>

    <div class="trust-strip">
      <span><b>✓</b> Always free</span>
      <span><b>✓</b> Not a dating app</span>
      <span><b>✓</b> No open DMs</span>
      <span><b>✓</b> Verified hosts</span>
      <span><b>✓</b> Report &amp; block on every page</span>
    </div>

    <h3 style="font-size:1.05rem;margin:28px 0 10px">And to be clear about what it isn't</h3>
    <div class="notlist">
      <div class="notrow">
        <span class="mark" aria-hidden="true">🚫</span>
        <span class="txt"><b>Not a dating app</b><span>You never "match" with a person. The activity is the thing, not the profile.</span></span>
      </div>
      <div class="notrow">
        <span class="mark" aria-hidden="true">🚫</span>
        <span class="txt"><b>Not another feed</b><span>Nothing here is built to keep you scrolling. It's built to get you out.</span></span>
      </div>
      <div class="notrow">
        <span class="mark" aria-hidden="true">🚫</span>
        <span class="txt"><b>Not a ticket site</b><span>Most plans are free and casual. We don't take a cut of anything.</span></span>
      </div>
    </div>

    <div class="btn-row" style="margin-top:22px;justify-content:center">
      <a class="btn btn-grad btn-lg" href="<?= url($me ? 'discover.php' : 'register.php') ?>">
        <?= $me ? 'See what\'s on this week' : 'Join free — takes a minute' ?>
      </a>
      <a class="btn btn-ghost btn-lg" href="<?= url('our-events.php') ?>">See what we've run</a>
    </div>
  </div>
</section>

<!-- ================= HOW IT WORKS ================= -->
<section class="section">
  <div class="wrap">
    <?php render_section_head(
      'How it works',
      'Four steps, and you never touch a spreadsheet.'
    ); ?>

    <div class="steps">
      <div class="step">
        <span class="emo" aria-hidden="true">📍</span>
        <h3>Tell us your city</h3>
        <p>Pick <?= e($city) ?> and the few things you're into. Takes under a minute and you're done setting up.</p>
      </div>
      <div class="step">
        <span class="emo" aria-hidden="true">🔎</span>
        <h3>Find something on</h3>
        <p>Filter by today, this weekend, free, or by activity. Every plan shows the spots left before you tap.</p>
      </div>
      <div class="step">
        <span class="emo" aria-hidden="true">✋</span>
        <h3>Join — the chat opens</h3>
        <p>You're on the list and in the plan's group chat. The host gets a notification that you're coming.</p>
      </div>
      <div class="step">
        <span class="emo" aria-hidden="true">🥾</span>
        <h3>Turn up</h3>
        <p>That's the whole loop. Do it once and hosting your own is the obvious next step.</p>
      </div>
    </div>

    <div class="btn-row" style="margin-top:18px;justify-content:center">
      <a class="btn btn-ghost" href="<?= url('about.php') ?>">More about us</a>
      <a class="btn btn-ghost" href="<?= url('safety.php') ?>">How we keep it safe</a>
    </div>
  </div>
</section>

<!-- ================= TRENDING ================= -->
<section class="section">
  <div class="wrap">
    <?php render_section_head(
      '🔥 Trending near you',
      "Filling up fast in {$city}",
      'Explore all',
      url('discover.php?sort=popular&city=' . rawurlencode($city))
    ); ?>

    <?php if ($trending): ?>
      <div class="grid snap">
        <?php foreach ($trending as $p) render_plan_card($p); ?>
      </div>
    <?php else: ?>
      <?php render_empty(
        'Nothing trending yet in ' . $city,
        'Be the first — create a plan and share the link. It takes about 40 seconds.',
        '+ Create a Plan',
        url('create.php')
      ); ?>
    <?php endif; ?>
  </div>
</section>

<!-- ================= THIS WEEK ================= -->
<?php if ($upcoming): ?>
<section class="section">
  <div class="wrap">
    <?php render_section_head('Happening soon', 'The next things on the calendar', 'See all', url('discover.php?city=' . rawurlencode($city))); ?>
    <div class="grid snap">
      <?php foreach ($upcoming as $p) render_plan_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= EVENTS ================= -->
<?php if ($events): ?>
<section class="section">
  <div class="wrap">
    <?php render_section_head('🎟 Events by organizers', 'Hosted by verified communities and organizers', 'All events', url('discover.php?tab=events')); ?>
    <div class="grid snap">
      <?php foreach ($events as $p) render_plan_card($p); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= COMMUNITIES ================= -->
<section class="section">
  <div class="wrap">
    <?php render_section_head('👥 Communities worth joining', "Find your people in {$city}", 'Browse all', url('communities.php')); ?>

    <?php if ($communities): ?>
      <div class="grid-2">
        <?php foreach ($communities as $g) render_community_card($g); ?>
      </div>
    <?php else: ?>
      <?php render_empty('No communities here yet', 'Run a running group, trek crew or founders circle? Bring it onto QuestScene.', 'Start a community', url('community-new.php')); ?>
    <?php endif; ?>
  </div>
</section>

<!-- ================= WHAT WE'VE RUN ================= -->
<?php if ($pastEvents): ?>
<section class="section">
  <div class="wrap">
    <?php render_section_head(
      '✅ What we\'ve already run',
      (int) $pastStats['runs'] . ' plans done · ' . (int) $pastStats['people'] . ' people showed up',
      'See them all',
      url('our-events.php')
    ); ?>

    <div class="grid-2">
      <?php foreach ($pastEvents as $p): ?>
        <a class="pastcard" href="<?= e(plan_url($p)) ?>">
          <span class="pc-ico"><?= e($p['cat_emoji'] ?: '✨') ?></span>
          <span class="pc-body">
            <strong><?= e($p['title']) ?></strong>
            <span><?= e(date('j M Y', strtotime($p['starts_at']))) ?> · <?= e($p['venue']) ?></span>
          </span>
          <span class="pc-n"><?= (int) $p['going_count'] ?> went</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= CREATE CTA ================= -->
<section class="section">
  <div class="wrap">
    <div class="cta-band">
      <h2>Stop planning in group chats</h2>
      <p>Forty messages to organise one football game. Drop a plan instead — one link, everyone sees who's in.</p>

      <div class="cta-examples">
        <span>"Football Saturday?"</span>
        <span>"Morning run?"</span>
        <span>"Weekend trek?"</span>
        <span>"Anyone for badminton?"</span>
      </div>

      <a class="btn btn-grad btn-lg" href="<?= url('create.php') ?>">+ Drop a Plan</a>

      <div class="loop" style="margin-top:26px">
        <b>Discover</b><i>→</i><b>Join</b><i>→</i><b>Go</b><i>→</i><b>Share</b><i>→</i><b>Create</b><i>↺</i>
      </div>
    </div>
  </div>
</section>

<!-- ================= PLAY ================= -->
<section class="section">
  <div class="wrap">
    <div class="panel" style="display:flex;flex-wrap:wrap;gap:18px;align-items:center;justify-content:space-between">
      <div style="flex:1;min-width:260px">
        <h2 style="font-size:1.35rem;margin-bottom:.25em">Nothing on tonight? 🎮</h2>
        <p class="muted" style="margin:0;max-width:46ch">
          2048, Sudoku, Memory Match, Reaction Test and Tic-Tac-Toe — plus a Daily Challenge
          everyone on QuestScene gets the same board for.
        </p>
      </div>
      <a class="btn btn-grad btn-lg" href="<?= url('play.php') ?>">Open Play</a>
    </div>
  </div>
</section>

<!-- ================= COMMUNITY / SOCIAL ================= -->
<section class="section">
  <div class="wrap panel" style="text-align:center">
    <h2>Follow the scene</h2>
    <p class="muted" style="max-width:48ch;margin:0 auto 18px">
      We post every week's plans on Instagram and run the day-to-day chatter on Telegram.
      Come say hello before you sign up — <?= e(SOCIAL_HANDLE) ?> on both.
    </p>
    <div style="display:flex;justify-content:center">
      <?php render_social(); ?>
    </div>
  </div>
</section>

<?php if (!$me): ?>
<!-- ================= SIGNUP ================= -->
<section class="section">
  <div class="wrap panel" style="text-align:center">
    <h2>Free to join. Under a minute.</h2>
    <p class="muted" style="max-width:44ch;margin:0 auto 18px">
      Tell us your city and what you're into. We'll show you what's happening — no feed to scroll, just things to do.
    </p>
    <a class="btn btn-grad btn-lg" href="<?= url('register.php') ?>">Join QuestScene</a>
  </div>
</section>
<?php endif; ?>

<script type="module" src="<?= asset('js/hero3d.js') ?>?v=1"></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
