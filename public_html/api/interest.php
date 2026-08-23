<?php
/** POST api/interest.php { post_id } — toggles "I'm interested" on a forum post. */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/queries.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'POST only'], 405);
csrf_check();

$me = current_user();
if (!$me) json_out(['ok' => false, 'error' => 'Log in first'], 401);

$postId = (int) ($_POST['post_id'] ?? 0);
$post = q1("SELECT id, user_id, body FROM posts WHERE id = ? AND status = 'active'", [$postId]);
if (!$post) json_out(['ok' => false, 'error' => 'Post not found'], 404);

$had = (bool) qv("SELECT 1 FROM post_interests WHERE post_id = ? AND user_id = ?", [$postId, $me['id']]);

if ($had) {
    qx("DELETE FROM post_interests WHERE post_id = ? AND user_id = ?", [$postId, $me['id']]);
} else {
    qx("INSERT IGNORE INTO post_interests (post_id, user_id) VALUES (?, ?)", [$postId, $me['id']]);
    if ((int) $post['user_id'] !== (int) $me['id']) {
        notify((int) $post['user_id'], '🙋', $me['name'] . ' is interested in your post', 'post.php?id=' . $postId);
    }
}

$count = (int) qv("SELECT COUNT(*) FROM post_interests WHERE post_id = ?", [$postId]);
qx("UPDATE posts SET interest_count = ? WHERE id = ?", [$count, $postId]);

json_out(['ok' => true, 'interested' => !$had, 'count' => $count]);
