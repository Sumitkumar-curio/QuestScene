<?php
/**
 * GET api/chat_fetch.php?type=plan&id=12&after=340
 * Returns messages newer than `after`. Polled by app.js every 4s.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queries.php';

$me = current_user();
if (!$me) json_out(['ok' => false, 'error' => 'Not logged in'], 401);

$type   = ($_GET['type'] ?? '') === 'community' ? 'community' : 'plan';
$roomId = (int) ($_GET['id'] ?? 0);
$after  = (int) ($_GET['after'] ?? 0);

if (!can_access_room($type, $roomId, (int) $me['id'])) {
    json_out(['ok' => false, 'error' => 'No access'], 403);
}

$rows = q(
    "SELECT m.id, m.body, m.created_at, m.user_id, u.name
       FROM messages m JOIN users u ON u.id = m.user_id
      WHERE m.room_type = ? AND m.room_id = ? AND m.id > ?
        AND m.user_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = ?)
      ORDER BY m.id ASC LIMIT 100",
    [$type, $roomId, $after, $me['id']]
);

$out = array_map(fn($m) => [
    'id'       => (int) $m['id'],
    'user_id'  => (int) $m['user_id'],
    'name'     => $m['name'],
    'initials' => initials($m['name']),
    'body'     => $m['body'],
    'time'     => ago($m['created_at']),
], $rows);

json_out(['ok' => true, 'messages' => $out]);
