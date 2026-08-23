<?php
/**
 * QuestScene — Start a community.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';

require_login('community-new.php');

$me     = current_user();
$errors = [];
$in = [
  'name'        => trim((string) ($_POST['name'] ?? '')),
  'description' => trim((string) ($_POST['description'] ?? '')),
  'city'        => (string) ($_POST['city'] ?? current_city()),
  'category_id' => (int) ($_POST['category_id'] ?? 0),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!rate_limit('new_community', 3, 86400)) {
        $errors[] = 'You can start up to 3 communities a day.';
    }
    if (mb_strlen($in['name']) < 3)  $errors[] = 'Give the community a name (at least 3 characters).';
    if (mb_strlen($in['description']) < 20) $errors[] = 'Write a couple of lines about what the group does — at least 20 characters.';
    if (!in_array($in['city'], CITIES, true)) $errors[] = 'Pick a city.';
    if ($in['category_id'] <= 0) $errors[] = 'Pick what the community is about.';
    if (!$errors && qv("SELECT id FROM communities WHERE name = ? AND city = ?", [$in['name'], $in['city']])) {
        $errors[] = 'A community with that name already exists in ' . $in['city'] . '.';
    }

    $cover = null;
    if (!$errors) {
        try { $cover = handle_upload('cover', 'covers'); }
        catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
    }

    if (!$errors) {
        $slug = unique_slug('communities', slugify($in['name']));

        $cid = qi(
            "INSERT INTO communities (slug, name, description, city, category_id, cover, owner_id, member_count)
             VALUES (?,?,?,?,?,?,?,1)",
            [$slug, $in['name'], $in['description'], $in['city'], $in['category_id'], $cover, $me['id']]
        );

        qx("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'owner')", [$cid, $me['id']]);

        // Owning a community makes you an organizer — unlocks the dashboard and Events.
        if ($me['role'] === 'user') {
            qx("UPDATE users SET role = 'organizer' WHERE id = ?", [$me['id']]);
        }

        flash('ok', $in['name'] . ' is live. Post your first plan and share the link.');
        redirect('community.php?s=' . $slug);
    }
}

$nav        = 'communities';
$page_title = 'Start a community';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:700px">
  <h1 style="font-size:clamp(1.6rem,5vw,2.3rem)">Start a <span class="grad-text">community</span></h1>
  <p class="muted" style="margin-bottom:24px">
    Already running a group on WhatsApp or Telegram? Give it a home page, a member list and a plan calendar.
  </p>

  <?php if ($errors): ?>
    <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="panel">
    <?= csrf_field() ?>

    <div class="field">
      <label for="name">Community name</label>
      <input class="input" type="text" name="name" id="name" maxlength="120" required
             value="<?= e($in['name']) ?>" placeholder="Bangalore Sunrise Runners">
    </div>

    <div class="field">
      <span class="field-label">What is it about?</span>
      <div class="optgrid">
        <?php foreach (all_categories() as $c): ?>
          <input type="radio" name="category_id" id="gc<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>"
                 <?= $in['category_id'] === (int) $c['id'] ? 'checked' : '' ?> required>
          <label for="gc<?= (int) $c['id'] ?>"><span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="field">
      <label for="city">City</label>
      <select class="select" name="city" id="city">
        <?php foreach (CITIES as $c): ?>
          <option value="<?= e($c) ?>" <?= $c === $in['city'] ? 'selected' : '' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="description">Describe it</label>
      <textarea class="textarea" name="description" id="description" maxlength="2000" required
                placeholder="We run 5–10K every Sunday morning across Bangalore. All paces welcome — nobody gets left behind."><?= e($in['description']) ?></textarea>
      <span class="hint">Say who it is for, how often you meet, and what a newcomer should expect.</span>
    </div>

    <div class="field">
      <label for="cover">Cover image <span class="muted">(optional)</span></label>
      <input class="input" type="file" name="cover" id="cover" accept="image/*">
    </div>

    <button class="btn btn-grad btn-lg btn-block" type="submit">Create community</button>
    <p class="muted center" style="font-size:.79rem;margin:12px 0 0">
      You become the owner and get an organizer dashboard. Verification is reviewed separately.
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
