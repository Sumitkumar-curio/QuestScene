<?php
/**
 * POST api/score.php { game, score, duration_ms, detail, daily }
 *
 * HONEST LIMITATION: the score comes from the browser, so a determined person
 * can forge it. There is no way around that without running the whole game on
 * the server, which is not worth it for a side feature. What we do instead:
 *   - cap each game at a plausible ceiling,
 *   - require a believable play duration,
 *   - rate-limit submissions,
 *   - keep one row per run so a cheated board can be cleaned up in admin.
 * Treat the leaderboards as fun, not as a competitive record.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/games.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
csrf_check();

$me = current_user();
if (!$me) json_out(['ok' => false, 'error' => 'Log in to save scores'], 401);

$game     = (string) ($_POST['game'] ?? '');
$score    = (int) ($_POST['score'] ?? 0);
$duration = (int) ($_POST['duration_ms'] ?? 0);
$detail   = trim((string) ($_POST['detail'] ?? ''));
$daily    = (string) ($_POST['daily'] ?? '');

$meta = game_meta($game);
if (!$meta) json_out(['ok' => false, 'error' => 'Unknown game'], 404);

if (!rate_limit('score', 60, 3600)) {
    json_out(['ok' => false, 'error' => 'That is a lot of games in one hour. Take a break.'], 429);
}

if ($score < 0 || $score > $meta['max']) {
    json_out(['ok' => false, 'error' => 'That score is out of range.'], 422);
}

// Nothing real finishes in under a second, and nothing sensible runs past 3 hours.
$duration = max(0, min($duration, 3 * 60 * 60 * 1000));
if ($duration > 0 && $duration < 900 && $meta['unit'] !== 'ms') {
    json_out(['ok' => false, 'error' => 'That game finished suspiciously fast.'], 422);
}

// Only accept a daily submission for the game that is actually today's challenge.
$dailyDate = null;
if ($daily !== '') {
    $today = daily_challenge();
    if ($daily === $today['date'] && $today['game'] === $game) {
        $dailyDate = $today['date'];
    }
}

$better = false;
$prev   = game_personal_best($game, (int) $me['id'], $dailyDate);
if ($prev === null) {
    $better = true;
} else {
    $better = $meta['dir'] === 'asc'
        ? $score < (int) $prev['score']
        : $score > (int) $prev['score'];
}

qx(
    "INSERT INTO game_scores (game, user_id, score, duration_ms, detail, daily_date, city)
     VALUES (?, ?, ?, ?, ?, ?, ?)",
    [$game, $me['id'], $score, $duration, mb_substr($detail, 0, 120) ?: null, $dailyDate, $me['city']]
);

// Where this run places: within today's challenge if it was one, otherwise
// on the all-time board (which counts every run, matching game_leaderboard).
$op   = $meta['dir'] === 'asc' ? '<' : '>';
$rank = 1 + (int) qv(
    "SELECT COUNT(DISTINCT user_id) FROM game_scores
      WHERE game = ? AND score {$op} ?" . ($dailyDate ? " AND daily_date = ?" : ''),
    $dailyDate ? [$game, $score, $dailyDate] : [$game, $score]
);

json_out([
    'ok'            => true,
    'personal_best' => $better,
    'best'          => $better ? $score : (int) $prev['score'],
    'rank'          => $rank,
    'label'         => game_score_label($game, $score),
]);
