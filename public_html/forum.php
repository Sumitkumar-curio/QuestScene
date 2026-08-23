<?php
/**
 * QuestScene — Forum.
 * Activity-oriented, not another photo feed. Every post nudges toward a plan.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

$me   = current_user();
$city = current_city();
$cat  = (string) ($_GET['cat'] ?? '');
$communityId = (int) ($_GET['community'] ?? 0);

// --- new post -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    require_login('forum.php');

    $body   = trim((string) ($_POST['body'] ?? ''));
    $intent = in_array($_POST['intent'] ?? '', ['looking', 'question', 'share'], true) ? $_POST['intent'] : 'looking';
    $catId  = (int) ($_POST['category_id'] ?? 0) ?: null;

    if (mb_strlen($body) < 10) {
        flash('error', 'Say a bit more — at least 10 characters.');
    } elseif (!rate_limit('post', 12, 3600)) {
        flash('error', 'You have posted a lot in the last hour. Give it a rest.');
    } else {
        qx("INSERT INTO posts (user_id, category_id, city, body, intent) VALUES (?,?,?,?,?)",
           [$me['id'], $catId, $city, mb_substr($body, 0, 4000), $intent]);
        flash('ok', 'Posted. If a few people say they are in, turn it into a plan.');
    }
    redirect('forum.php' . ($cat ? '?cat=' . urlencode($cat) : ''));
}

// --- list -----------------------------------------------------------
$where  = ["p.status = 'active'"];
$params = [];

if ($communityId > 0) { $where[] = "p.community_id = ?"; $params[] = $communityId; }
else                  { $where[] = "p.city = ?";         $params[] = $city; }

if ($cat) { $where[] = "c.slug = ?"; $params[] = $cat; }

$posts = q(
    "SELECT p.*, u.name, u.username, u.avatar, c.name AS cat_name, c.emoji AS cat_emoji,
            (SELECT COUNT(*) FROM post_interests pi WHERE pi.post_id = p.id AND pi.user_id = ?) AS mine
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY p.created_at DESC LIMIT 40",
    array_merge([(int) ($me['id'] ?? 0)], $params)
);

$nav        = 'forum';
$page_title = 'Forum · ' . $city;
$page_desc  = "Ask, offer and organise. Who's up for a 10K this Sunday in {$city}?";
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:820px">
  <h1 style="font-size:clamp(1.6rem,5vw,2.3rem);margin-bottom:.2em">Forum</h1>
  <p class="muted" style="margin-bottom:20px">
    Looking for two more for badminton? Wondering which trek suits a beginner? Ask here — then turn it into a plan.
  </p>

  <!-- Composer -->
  <?php if ($me): ?>
  <form method="post" class="panel" style="margin-bottom:22px">
    <?= csrf_field() ?>
    <div class="field" style="margin-bottom:12px">
      <textarea class="textarea" name="body" id="fbody" maxlength="4000" required style="min-height:92px"
                placeholder="Anyone up for a 10K this Sunday morning?"></textarea>
      <span class="hint"><span data-count-for="fbody">0</span> characters</span>
    </div>

    <div class="field-row" style="margin-bottom:12px">
      <div class="field" style="margin:0">
        <label for="intent">What is this?</label>
        <select class="select" name="intent" id="intent">
          <option value="looking">Looking for people</option>
          <option value="question">Asking a question</option>
          <option value="share">Sharing something</option>
        </select>
      </div>
      <div class="field" style="margin:0">
        <label for="category_id">About</label>
        <select class="select" name="category_id" id="category_id">
          <option value="0">General</option>
          <?php foreach (all_categories() as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['emoji']) ?> <?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="btn-row">
      <button class="btn btn-grad" type="submit">Post to <?= e($city) ?></button>
      <a class="btn btn-ghost" href="<?= url('create.php') ?>">Skip ahead — create a plan</a>
    </div>
  </form>
  <?php else: ?>
    <div class="panel center" style="margin-bottom:22px">
      <p class="muted" style="margin-bottom:14px">Log in to post and reply.</p>
      <a class="btn btn-grad" href="<?= url('login.php?next=forum.php') ?>">Log in</a>
    </div>
  <?php endif; ?>

  <!-- Category filter -->
  <div class="rail">
    <a class="catpill <?= $cat === '' ? 'active' : '' ?>" href="<?= url('forum.php') ?>">All</a>
    <?php foreach (all_categories() as $c): ?>
      <a class="catpill <?= $cat === $c['slug'] ? 'active' : '' ?>" href="<?= url('forum.php?cat=' . e($c['slug'])) ?>">
        <span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Posts -->
  <?php if (!$posts): ?>
    <?php render_empty('Quiet in here', "Nothing posted in {$city} yet. Ask the first question — someone always answers.", 'Create a plan instead', url('create.php')); ?>
  <?php endif; ?>

  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($posts as $p): ?>
      <article class="post">
        <div class="post-head">
          <a href="<?= url('profile.php?u=' . rawurlencode($p['username'])) ?>"><?php render_avatar($p, 'sm'); ?></a>
          <div style="flex:1;min-width:0">
            <div class="who"><a href="<?= url('profile.php?u=' . rawurlencode($p['username'])) ?>"><?= e($p['name']) ?></a></div>
            <div class="when">
              <?= e(ago($p['created_at'])) ?>
              <?php if ($p['cat_name']): ?> · <?= e($p['cat_emoji']) ?> <?= e($p['cat_name']) ?><?php endif; ?>
            </div>
          </div>
          <?php if ($p['intent'] === 'looking'): ?><span class="pill pill-info">Looking for people</span><?php endif; ?>
        </div>

        <div class="post-body"><?= e($p['body']) ?></div>

        <div class="post-foot">
          <button class="post-act js-interest <?= (int) $p['mine'] ? 'on' : '' ?>" data-post="<?= (int) $p['id'] ?>">
            🙋 I'm in <span class="count"><?= (int) $p['interest_count'] ?></span>
          </button>
          <a class="post-act" href="<?= url('post.php?id=' . (int) $p['id']) ?>">
            💬 <?= (int) $p['reply_count'] ?> repl<?= (int) $p['reply_count'] === 1 ? 'y' : 'ies' ?>
          </a>
          <?php if ($me && (int) $p['user_id'] === (int) $me['id']): ?>
            <a class="post-act" href="<?= url('create.php') ?>">⚡ Turn into a plan</a>
          <?php elseif ((int) $p['interest_count'] >= 2): ?>
            <a class="post-act" href="<?= url('create.php') ?>">⚡ Make it happen</a>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
