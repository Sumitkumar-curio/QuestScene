<?php
/** POST api/save.php { plan_id } — toggles a saved plan. */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queries.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
csrf_check();

$me = current_user();
if (!$me) json_out(['ok' => false, 'error' => 'Log in first'], 401);

$planId = (int) ($_POST['plan_id'] ?? 0);
if (!qv("SELECT id FROM plans WHERE id = ?", [$planId])) {
    json_out(['ok' => false, 'error' => 'Plan not found'], 404);
}

if (is_saved($planId, (int) $me['id'])) {
    qx("DELETE FROM saved_plans WHERE plan_id = ? AND user_id = ?", [$planId, $me['id']]);
    json_out(['ok' => true, 'saved' => false]);
}

qx("INSERT IGNORE INTO saved_plans (user_id, plan_id) VALUES (?, ?)", [$me['id'], $planId]);
json_out(['ok' => true, 'saved' => true]);
