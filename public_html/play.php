<?php
/**
 * QuestScene — Play hub.
 * Small on purpose. Every screen in here has a way back to a real plan.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';
require_once __DIR__ . '/includes/games.php';

$me    = current_user();
$city  = current_city();
$daily = daily_challenge();

$dailyBoard = game_leaderboard($daily['game'], $daily['date'], 5);
$dailyBest  = $me ? game_personal_best($daily['game'], (int) $me['id'], $daily['date']) : null;

$myBests = [];
if ($me) {
    foreach (games() as $slug => $g) {
        $pb = game_personal_best($slug, (int) $me['id']);
        if ($pb) $myBests[$slug] = $pb;
    }
}

$playedToday = (int) qv("SELECT COUNT(*) FROM game_scores WHERE DATE(created_at) = CURDATE()");

// The whole point of Play: hand people back to something real afterwards.
$nextPlan = find_plans(['city' => $city, 'limit' => 1]);

$page_css   = ['games.css'];
$nav        = 'play';
$page_title = 'Play';
$page_desc  = 'Quick games for when you are bored — 2048, Sudoku, Memory Match, Reaction Test and Tic-Tac-Toe, plus a Daily Challenge.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">

  <h1 style="font-size:clamp(1.7rem,5.5vw,2.5rem);margin-bottom:.2em">
    Bored? <span class="grad-text">Play something.</span>
  </h1>
  <p class="muted" style="max-width:56ch;margin-bottom:24px">
    Five quick games and one Daily Challenge. Nothing to install, nothing to buy.
    Then go and do something that isn't on a screen.
  </p>

  <!-- ============ DAILY CHALLENGE ============ -->
  <section class="daily">
    <div class="daily-head">
      <span class="daily-badge">🎯 Daily Challenge</span>
      <span class="muted" style="font-size:.82rem"><?= e(date('D, j M', strtotime($daily['date']))) ?></span>
    </div>

    <h2 style="font-size:1.35rem;margin:8px 0 4px">
      <?= e($daily['meta']['emoji']) ?> <?= e($daily['meta']['name']) ?>
    </h2>
    <p class="muted" style="font-size:.9rem;margin-bottom:14px">
      Same game, same starting position, for everyone on QuestScene today. Resets at midnight.
    </p>

    <div class="btn-row" style="margin-bottom:16px">
      <a class="btn btn-grad" href="<?= url('game.php?g=' . e($daily['game']) . '&daily=1') ?>">
        <?= $dailyBest ? 'Play again' : 'Take today\'s challenge' ?>
      </a>
      <?php if ($dailyBest): ?>
        <span class="btn btn-ok" style="cursor:default">
          Your best: <?= e(game_score_label($daily['game'], (int) $dailyBest['score'])) ?>
        </span>
      <?php endif; ?>
    </div>

    <?php if ($dailyBoard): ?>
      <h3 style="font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-bottom:8px">
        Today's top <?= count($dailyBoard) ?>
      </h3>
      <ol class="board">
        <?php foreach ($dailyBoard as $i => $row): ?>
          <li class="board-row <?= $me && (int) $row['user_id'] === (int) $me['id'] ? 'is-me' : '' ?>">
            <span class="board-rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span>
            <?php render_avatar($row, 'xs'); ?>
            <a class="board-name" href="<?= url('profile.php?u=' . rawurlencode($row['username'])) ?>"><?= e($row['name']) ?></a>
            <span class="board-score"><?= e(game_score_label($daily['game'], (int) $row['score'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <p class="muted" style="font-size:.86rem;margin:0">Nobody has played today's challenge yet. Be first on the board.</p>
    <?php endif; ?>
  </section>

  <!-- ============ THE GAMES ============ -->
  <section class="section">
    <?php render_section_head('All games', $playedToday . ' games played on QuestScene today'); ?>

    <div class="grid">
      <?php foreach (games() as $slug => $g): ?>
        <a class="gcard" href="<?= url('game.php?g=' . e($slug)) ?>">
          <span class="gcard-emoji" aria-hidden="true"><?= e($g['emoji']) ?></span>
          <div class="gcard-body">
            <h3><?= e($g['name']) ?></h3>
            <p class="muted"><?= e($g['tag']) ?></p>
            <div class="gcard-foot">
              <span class="chip"><?= e($g['time']) ?></span>
              <?php if (isset($myBests[$slug])): ?>
                <span class="chip chip-free">Best <?= e(game_score_label($slug, (int) $myBests[$slug]['score'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <span class="gcard-go" aria-hidden="true">→</span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ============ BACK TO THE REAL THING ============ -->
  <section class="section">
    <div class="cta-band">
      <h2>Right — that's enough screen time</h2>
      <?php if ($nextPlan): ?>
        <p>
          <strong style="color:var(--text)"><?= e($nextPlan[0]['title']) ?></strong> is on
          <?= e(strtolower(fmt_day($nextPlan[0]['starts_at']))) ?> at <?= e($nextPlan[0]['venue']) ?>.
          <?= (int) $nextPlan[0]['going_count'] ?> people are going.
        </p>
        <div class="btn-row" style="justify-content:center">
          <a class="btn btn-grad btn-lg" href="<?= e(plan_url($nextPlan[0])) ?>">See that plan</a>
          <a class="btn btn-ghost btn-lg" href="<?= url('discover.php') ?>">Everything else on</a>
        </div>
      <?php else: ?>
        <p>Games are the side dish. QuestScene is for the thing you actually leave the house for.</p>
        <a class="btn btn-grad btn-lg" href="<?= url('discover.php') ?>">See what's on in <?= e($city) ?></a>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!$me): ?>
    <p class="muted center" style="font-size:.86rem">
      Scores are saved and ranked once you have a free account.
      <a href="<?= url('register.php') ?>" style="color:var(--brand-2);font-weight:700">Join QuestScene</a>
    </p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
