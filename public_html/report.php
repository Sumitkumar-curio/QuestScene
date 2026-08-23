<?php
/**
 * QuestScene — submit a report. Posted to from plan, profile and community pages.
 */

require_once __DIR__ . '/includes/auth.php';

require_login();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
csrf_check();

$type   = (string) ($_POST['target_type'] ?? '');
$id     = (int) ($_POST['target_id'] ?? 0);
$reason = trim((string) ($_POST['reason'] ?? ''));
$detail = trim((string) ($_POST['details'] ?? ''));

$valid = ['plan', 'user', 'community', 'post', 'message'];

if (!in_array($type, $valid, true) || $id <= 0 || $reason === '') {
    flash('error', 'That report was incomplete. Pick a reason and try again.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}

if (!rate_limit('report', 10, 3600)) {
    flash('error', 'You have filed several reports recently. We are looking at them.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
}

$already = qv(
    "SELECT id FROM reports WHERE reporter_id = ? AND target_type = ? AND target_id = ? AND status IN ('open','reviewing')",
    [$me['id'], $type, $id]
);

if ($already) {
    flash('info', 'You already reported this. We are on it.');
} else {
    qx("INSERT INTO reports (reporter_id, target_type, target_id, reason, details) VALUES (?,?,?,?,?)",
       [$me['id'], $type, $id, mb_substr($reason, 0, 60), mb_substr($detail, 0, 600) ?: null]);
    flash('ok', 'Report received. A QuestScene admin will review it.');
}

redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
