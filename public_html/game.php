<?php
/**
 * QuestScene — the shell every game mounts into.
 * The PHP here only supplies chrome, the seed and the leaderboard; all the
 * gameplay lives in assets/js/games/<slug>.js.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';
require_once __DIR__ . '/includes/games.php';

$slug = (string) ($_GET['g'] ?? '');

if (!game_exists($slug)) {
    http_response_code(404);
    $nav = 'play';
    $page_title = 'Game not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section">';
    render_empty('No such game', 'That game does not exist on QuestScene.', 'Back to Play', url('play.php'));
    echo '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$meta    = game_meta($slug);
$me      = current_user();
$daily   = daily_challenge();
$isDaily = !empty($_GET['daily']) && $daily['game'] === $slug;

// A daily run uses a fixed seed so everyone gets an identical puzzle.
$seed = $isDaily ? (int) $daily['seed'] : random_int(1, 999999);

$board = game_leaderboard($slug, $isDaily ? $daily['date'] : null, 10);
$pb    = $me ? game_personal_best($slug, (int) $me['id'], $isDaily ? $daily['date'] : null) : null;

$page_css   = ['games.css'];
$nav        = 'play';
$page_title = $meta['name'];
$page_desc  = $meta['blurb'];
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">
  <p class="breadcrumb"><a href="<?= url('play.php') ?>">← Play</a></p>

  <div class="game-layout">

    <!-- ============ THE GAME ============ -->
    <div>
      <div class="game-head">
        <span class="game-emoji" aria-hidden="true"><?= e($meta['emoji']) ?></span>
        <div style="flex:1;min-width:0">
          <h1 style="font-size:1.5rem;margin:0"><?= e($meta['name']) ?></h1>
          <p class="muted" style="margin:2px 0 0;font-size:.85rem"><?= e($meta['tag']) ?></p>
        </div>
        <?php if ($isDaily): ?>
          <span class="daily-badge">🎯 Daily</span>
        <?php endif; ?>
      </div>

      <?php if ($isDaily): ?>
        <div class="flash flash-info" style="margin:0 0 14px">
          Everyone on QuestScene gets this exact starting position today. Resets at midnight.
        </div>
      <?php endif; ?>

      <!-- Each game script mounts itself into #game and reads these attributes. -->
      <div id="game"
           class="game-stage"
           data-game="<?= e($slug) ?>"
           data-seed="<?= (int) $seed ?>"
           data-daily="<?= $isDaily ? $daily['date'] : '' ?>"
           data-signed-in="<?= $me ? '1' : '0' ?>">
        <div class="game-loading">Loading <?= e($meta['name']) ?>…</div>
      </div>

      <noscript>
        <div class="flash flash-error" style="margin-top:14px">
          The games need JavaScript. Everything else on QuestScene works without it.
        </div>
      </noscript>

      <p class="muted" style="font-size:.85rem;margin-top:16px"><?= e($meta['blurb']) ?></p>
    </div>

    <!-- ============ SIDEBAR ============ -->
    <aside>
      <div class="panel panel-tight" style="margin-bottom:14px">
        <h2 style="font-size:1rem;margin-bottom:10px">
          <?= $isDaily ? "Today's board" : 'Top players' ?>
        </h2>

        <?php if ($board): ?>
          <ol class="board">
            <?php foreach ($board as $i => $row): ?>
              <li class="board-row <?= $me && (int) $row['user_id'] === (int) $me['id'] ? 'is-me' : '' ?>">
                <span class="board-rank <?= $i < 3 ? 'top' : '' ?>"><?= $i + 1 ?></span>
                <?php render_avatar($row, 'xs'); ?>
                <a class="board-name" href="<?= url('profile.php?u=' . rawurlencode($row['username'])) ?>"><?= e($row['name']) ?></a>
                <span class="board-score"><?= e(game_score_label($slug, (int) $row['score'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <p class="muted" style="font-size:.86rem;margin:0">No scores yet. Yours would be first.</p>
        <?php endif; ?>

        <?php if ($pb): ?>
          <hr class="divider" style="margin:14px 0">
          <p style="margin:0;font-size:.88rem">
            <span class="muted">Your best:</span>
            <strong><?= e(game_score_label($slug, (int) $pb['score'])) ?></strong>
          </p>
        <?php elseif (!$me): ?>
          <hr class="divider" style="margin:14px 0">
          <p class="muted" style="font-size:.84rem;margin:0 0 10px">Log in and your scores get saved and ranked.</p>
          <a class="btn btn-grad btn-sm btn-block" href="<?= url('login.php?next=game.php%3Fg=' . e($slug)) ?>">Log in</a>
        <?php endif; ?>
      </div>

      <?php if (!$isDaily && $daily['game'] !== $slug): ?>
        <a class="panel panel-tight" href="<?= url('play.php') ?>" style="display:block;margin-bottom:14px">
          <strong style="font-size:.92rem">🎯 Today's challenge</strong><br>
          <span class="muted" style="font-size:.84rem">
            <?= e($daily['meta']['emoji']) ?> <?= e($daily['meta']['name']) ?> — same board for everyone
          </span>
        </a>
      <?php endif; ?>

      <div class="panel panel-tight">
        <h2 style="font-size:.95rem;margin-bottom:8px">Other games</h2>
        <?php foreach (games() as $s => $g): ?>
          <?php if ((string) $s === $slug) continue;   // keys can be ints — see daily_challenge() ?>
          <a href="<?= url('game.php?g=' . e($s)) ?>" class="mini-game">
            <span><?= e($g['emoji']) ?></span> <?= e($g['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</div>

<script src="<?= asset('js/games/_shared.js') ?>?v=1" defer></script>
<script src="<?= asset('js/games/' . $slug . '.js') ?>?v=1" defer></script>

<?php require __DIR__ . '/includes/footer.php'; ?>
