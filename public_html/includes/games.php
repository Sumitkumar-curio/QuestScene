<?php
/**
 * QuestScene — the Play section.
 *
 * Play is deliberately small. It exists to give someone a reason to open
 * QuestScene when they are bored on a Tuesday, and to hand them back to the
 * real product afterwards. It is not meant to grow into a gaming site.
 */

require_once __DIR__ . '/db.php';

/**
 * Every game, in one place. 'dir' says which way is better:
 *   'desc' — higher score wins (2048, memory)
 *   'asc'  — lower score wins (reaction time, sudoku seconds)
 */
function games(): array
{
    return [
        '2048' => [
            'name'  => '2048',
            'emoji' => '🧩',
            'tag'   => 'Slide, merge, chase 2048',
            'blurb' => 'Swipe the tiles, merge matching numbers, and see how far past 2048 you can push before the grid jams up.',
            'unit'  => 'points',
            'dir'   => 'desc',
            'time'  => '5–15 min',
            'max'   => 4000000,
        ],
        'sudoku' => [
            'name'  => 'Sudoku',
            'emoji' => '🧠',
            'tag'   => 'Four difficulties, one solution',
            'blurb' => 'Every puzzle is generated fresh and has exactly one solution. Fill the grid without repeating a digit in any row, column or box.',
            'unit'  => 'points',
            'dir'   => 'desc',
            'time'  => '5–30 min',
            'max'   => 20000,
        ],
        'memory' => [
            'name'  => 'Memory Match',
            'emoji' => '🃏',
            'tag'   => 'Find all eight pairs',
            'blurb' => 'Flip two cards at a time and remember what you saw. Fewer moves and less time means a better score.',
            'unit'  => 'points',
            'dir'   => 'desc',
            'time'  => '2–4 min',
            'max'   => 20000,
        ],
        'reaction' => [
            'name'  => 'Reaction Test',
            'emoji' => '🔢',
            'tag'   => 'How fast are you, really?',
            'blurb' => 'Wait for green, then tap. Five rounds, averaged. Anything under 250ms is genuinely quick.',
            'unit'  => 'ms',
            'dir'   => 'asc',
            'time'  => '1 min',
            'max'   => 5000,
        ],
        'tictactoe' => [
            'name'  => 'Tic-Tac-Toe',
            'emoji' => '❌',
            'tag'   => 'Solo vs computer, or two players',
            'blurb' => 'Play a friend on one phone, or take on the computer. On Unbeatable it plays perfectly — a draw is the best you can do.',
            'unit'  => 'points',
            'dir'   => 'desc',
            'time'  => '1–2 min',
            'max'   => 10000,
        ],
    ];
}

function game_exists(string $slug): bool
{
    return array_key_exists($slug, games());
}

function game_meta(string $slug): ?array
{
    return games()[$slug] ?? null;
}

/**
 * The Daily Challenge: one game, one seed, the same for everybody, changing at
 * midnight. Derived from the date so it needs no cron and no stored state.
 */
function daily_challenge(?string $date = null): array
{
    $date  = $date ?? date('Y-m-d');
    $slugs = array_keys(games());

    // crc32 of the date gives a stable, evenly-spread number per day.
    $n    = crc32('questscene-daily-' . $date);

    // Cast to string on purpose: PHP turns numeric-looking array keys into
    // integers, so games()['2048'] has the INT key 2048. Without this cast
    // every `=== $slug` check against a $_GET value silently fails.
    $slug = (string) $slugs[$n % count($slugs)];

    return [
        'date' => $date,
        'game' => $slug,
        'seed' => $n % 100000,
        'meta' => games()[$slug],
    ];
}

/**
 * Top scores for a game — one row per player, their single best run.
 * Pass $date to scope it to that day's challenge.
 *
 * Deliberately written WITHOUT GROUP BY: MySQL 8 ships with
 * only_full_group_by enabled, which rejects selecting columns that are
 * neither grouped nor aggregated. Two correlated subqueries do the job and
 * work on every MySQL and MariaDB version we care about.
 */
function game_leaderboard(string $slug, ?string $date = null, int $limit = 10, ?string $city = null): array
{
    $meta = game_meta($slug);
    if (!$meta) return [];

    $order = $meta['dir'] === 'asc' ? 'ASC' : 'DESC';
    $bestFn = $meta['dir'] === 'asc' ? 'MIN' : 'MAX';
    $limit  = max(1, min(50, $limit));

    // Filter shared by the outer query and both subqueries.
    $filter = 'game = ?';
    $fp     = [$slug];

    // With a date we want that day's challenge only; without one, the
    // all-time board counts every run including daily ones.
    if ($date !== null) { $filter .= ' AND daily_date = ?'; $fp[] = $date; }
    if ($city !== null) { $filter .= ' AND city = ?';       $fp[] = $city; }

    $sql = "
        SELECT gs.user_id, gs.score, gs.duration_ms, gs.detail, gs.created_at,
               u.name, u.username, u.avatar
          FROM game_scores gs
          JOIN users u ON u.id = gs.user_id
         WHERE gs.{$filter}
           AND gs.score = (SELECT {$bestFn}(b.score) FROM game_scores b
                            WHERE b.user_id = gs.user_id AND b.{$filter})
           AND gs.id    = (SELECT MIN(c.id) FROM game_scores c
                            WHERE c.user_id = gs.user_id AND c.score = gs.score AND c.{$filter})
         ORDER BY gs.score {$order}, gs.created_at ASC
         LIMIT {$limit}";

    return q($sql, array_merge($fp, $fp, $fp));
}

/** A single player's best at a game. */
function game_personal_best(string $slug, int $userId, ?string $date = null): ?array
{
    $meta = game_meta($slug);
    if (!$meta) return null;

    $order = $meta['dir'] === 'asc' ? 'ASC' : 'DESC';
    $sql   = "SELECT score, duration_ms, detail, created_at FROM game_scores
               WHERE game = ? AND user_id = ?";
    $params = [$slug, $userId];

    if ($date !== null) { $sql .= " AND daily_date = ?"; $params[] = $date; }

    return q1($sql . " ORDER BY score {$order} LIMIT 1", $params);
}

/** Format a score for display ("2,048 points" / "241 ms"). */
function game_score_label(string $slug, int $score): string
{
    $meta = game_meta($slug);
    if (!$meta) return (string) $score;

    return $meta['unit'] === 'ms'
        ? number_format($score) . ' ms'
        : number_format($score) . ' pts';
}
