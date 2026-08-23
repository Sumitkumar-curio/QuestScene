<?php
/**
 * POST api/join.php  { plan_id, action: join|leave }
 * Joins or leaves a plan. Overflow goes to the waitlist rather than being refused.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queries.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
csrf_check();

$me = current_user();
if (!$me) json_out(['ok' => false, 'error' => 'Log in to join plans'], 401);

$planId = (int) ($_POST['plan_id'] ?? 0);
$action = ($_POST['action'] ?? 'join') === 'leave' ? 'leave' : 'join';

$plan = q1("SELECT * FROM plans WHERE id = ?", [$planId]);
if (!$plan)                            json_out(['ok' => false, 'error' => 'Plan not found'], 404);
if ($plan['status'] !== 'active')       json_out(['ok' => false, 'error' => 'This plan is no longer open'], 409);
if ((int) $plan['host_id'] === (int) $me['id']) json_out(['ok' => false, 'error' => 'You are the host of this plan'], 409);

if ($action === 'leave') {
    qx("DELETE FROM plan_participants WHERE plan_id = ? AND user_id = ?", [$planId, $me['id']]);

    // A spot opened — promote the person who has been waiting longest.
    $next = q1("SELECT user_id FROM plan_participants WHERE plan_id = ? AND status = 'waitlist' ORDER BY joined_at LIMIT 1", [$planId]);
    if ($next) {
        qx("UPDATE plan_participants SET status = 'going' WHERE plan_id = ? AND user_id = ?", [$planId, $next['user_id']]);
        notify((int) $next['user_id'], '🎉', 'A spot opened up — you are in for "' . $plan['title'] . '"', 'plan.php?s=' . $plan['slug']);
    }

    $state = 'left';
} else {
    if (strtotime($plan['starts_at']) < time()) json_out(['ok' => false, 'error' => 'This plan has already started'], 409);
    if (!rate_limit('join', 40, 3600))          json_out(['ok' => false, 'error' => 'Slow down a moment'], 429);

    if ($plan['visibility'] === 'community' && $plan['community_id']) {
        if (!is_member((int) $plan['community_id'], (int) $me['id'])) {
            json_out(['ok' => false, 'error' => 'Join the community first to take part in this plan'], 403);
        }
    }

    $going = (int) qv("SELECT COUNT(*) FROM plan_participants WHERE plan_id = ? AND status = 'going'", [$planId]);
    $state = $going >= (int) $plan['capacity'] ? 'waitlist' : 'going';

    qx("INSERT INTO plan_participants (plan_id, user_id, status) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status)", [$planId, $me['id'], $state]);

    notify(
        (int) $plan['host_id'],
        $state === 'going' ? '✅' : '⏳',
        $me['name'] . ($state === 'going' ? ' joined ' : ' is on the waitlist for ') . '"' . $plan['title'] . '"',
        'plan.php?s=' . $plan['slug']
    );
}

refresh_plan_count($planId);
$count = (int) qv("SELECT going_count FROM plans WHERE id = ?", [$planId]);

json_out([
    'ok'          => true,
    'state'       => $state,
    'going_count' => $count,
    'percent'     => (int) min(100, round(($count / max(1, (int) $plan['capacity'])) * 100)),
]);
