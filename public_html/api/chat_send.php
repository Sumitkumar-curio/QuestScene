<?php
/**
 * POST api/chat_send.php  { type: plan|community, id, body }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queries.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
csrf_check();

$me = current_user();
if (!$me) json_out(['ok' => false, 'error' => 'Log in to chat'], 401);

$type   = ($_POST['type'] ?? '') === 'community' ? 'community' : 'plan';
$roomId = (int) ($_POST['id'] ?? 0);
$body   = trim((string) ($_POST['body'] ?? ''));

if ($body === '')              json_out(['ok' => false, 'error' => 'Message is empty'], 422);
if (mb_strlen($body) > 1000)   json_out(['ok' => false, 'error' => 'Message is too long (1000 characters max)'], 422);
if (!rate_limit('chat', 30, 60)) json_out(['ok' => false, 'error' => 'You are sending messages too quickly'], 429);

if (!can_access_room($type, $roomId, (int) $me['id'])) {
    json_out(['ok' => false, 'error' => 'You are not in this room'], 403);
}

$id = qi("INSERT INTO messages (room_type, room_id, user_id, body) VALUES (?,?,?,?)",
         [$type, $roomId, $me['id'], $body]);

json_out([
    'ok' => true,
    'message' => [
        'id'       => $id,
        'user_id'  => (int) $me['id'],
        'name'     => $me['name'],
        'initials' => initials($me['name']),
        'body'     => $body,
        'time'     => 'just now',
    ],
]);
