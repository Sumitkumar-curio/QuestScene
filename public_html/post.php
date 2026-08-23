<?php
/**
 * QuestScene — a single forum post and its replies.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$id = (int) ($_GET['id'] ?? 0);
$post = q1(
    "SELECT p.*, u.name, u.username, u.avatar, c.name AS cat_name, c.emoji AS cat_emoji
       FROM posts p JOIN users u ON u.id = p.user_id
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.id = ?",
    [$id]
);

if (!$post || ($post['status'] !== 'active' && !is_admin())) {
    http_response_code(404);
    $page_title = 'Post not found';
    require __DIR__ . '/includes/header.php';
    echo '<div class="wrap section">';
    render_empty('Post not found', 'It may have been removed.', 'Back to forum', url('forum.php'));
    echo '</div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_login('post.php?id=' . $id);

    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body === '') {
        flash('error', 'Write something first.');
    } elseif (!rate_limit('reply', 30, 3600)) {
        flash('error', 'Too many replies just now. Try again later.');
    } else {
        qx("INSERT INTO post_replies (post_id, user_id, body) VALUES (?,?,?)",
           [$id, $me['id'], mb_substr($body, 0, 1000)]);
        qx("UPDATE posts SET reply_count = (SELECT COUNT(*) FROM post_replies WHERE post_id = ?) WHERE id = ?", [$id, $id]);

        if ((int) $post['user_id'] !== (int) $me['id']) {
            notify((int) $post['user_id'], '💬', $me['name'] . ' replied to your post', 'post.php?id=' . $id);
        }
    }
    redirect('post.php?id=' . $id);
}

$replies = q(
    "SELECT r.*, u.name, u.username, u.avatar
       FROM post_replies r JOIN users u ON u.id = r.user_id
      WHERE r.post_id = ? ORDER BY r.created_at ASC",
    [$id]
);
$mine = $me ? (bool) qv("SELECT 1 FROM post_interests WHERE post_id = ? AND user_id = ?", [$id, $me['id']]) : false;

$nav        = 'forum';
$page_title = excerpt($post['body'], 60);
$page_desc  = excerpt($post['body'], 155);
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:760px">
  <p class="breadcrumb"><a href="<?= url('forum.php') ?>">← Forum</a></p>

  <article class="post" style="margin-bottom:20px">
    <div class="post-head">
      <a href="<?= url('profile.php?u=' . rawurlencode($post['username'])) ?>"><?php render_avatar($post, 'md'); ?></a>
      <div style="flex:1">
        <div class="who"><?= e($post['name']) ?></div>
        <div class="when">
          <?= e(ago($post['created_at'])) ?> · <?= e($post['city']) ?>
          <?php if ($post['cat_name']): ?> · <?= e($post['cat_emoji']) ?> <?= e($post['cat_name']) ?><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="post-body" style="font-size:1.03rem"><?= e($post['body']) ?></div>

    <div class="post-foot">
      <button class="post-act js-interest <?= $mine ? 'on' : '' ?>" data-post="<?= (int) $post['id'] ?>">
        🙋 I'm in <span class="count"><?= (int) $post['interest_count'] ?></span>
      </button>
      <a class="post-act" href="<?= url('create.php') ?>">⚡ Turn this into a plan</a>
      <button class="post-act js-copy" data-copy="<?= e(url('post.php?id=' . (int) $post['id'])) ?>">🔗 Copy link</button>
    </div>
  </article>

  <h2 style="font-size:1.15rem"><?= count($replies) ?> repl<?= count($replies) === 1 ? 'y' : 'ies' ?></h2>

  <div class="panel" style="margin-bottom:18px">
    <?php if (!$replies): ?>
      <p class="muted" style="margin:0">No replies yet.</p>
    <?php endif; ?>

    <?php foreach ($replies as $r): ?>
      <div class="reply">
        <?php render_avatar($r, 'sm'); ?>
        <div>
          <div class="reply-who">
            <a href="<?= url('profile.php?u=' . rawurlencode($r['username'])) ?>"><?= e($r['name']) ?></a>
            <span class="reply-when"><?= e(ago($r['created_at'])) ?></span>
          </div>
          <div class="reply-body"><?= e($r['body']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($me): ?>
    <form method="post" class="panel">
      <?= csrf_field() ?>
      <div class="field">
        <label for="body">Your reply</label>
        <textarea class="textarea" name="body" id="body" maxlength="1000" required style="min-height:88px"
                  placeholder="I'm in — what time?"></textarea>
      </div>
      <button class="btn btn-grad" type="submit">Reply</button>
    </form>
  <?php else: ?>
    <div class="panel center">
      <p class="muted" style="margin-bottom:12px">Log in to reply.</p>
      <a class="btn btn-grad" href="<?= url('login.php?next=post.php%3Fid=' . (int) $id) ?>">Log in</a>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
