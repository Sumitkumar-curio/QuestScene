<?php
/**
 * QuestScene — Create a Plan.
 * The single most important action on the site. Keep it under 60 seconds.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login('create.php');

$me     = current_user();
$errors = [];

// Communities this user can post a plan into.
$myCommunities = q(
    "SELECT g.id, g.name FROM communities g
       JOIN community_members m ON m.community_id = g.id AND m.user_id = ?
      WHERE g.status = 'active' AND m.role IN ('owner','moderator')
      ORDER BY g.name",
    [$me['id']]
);

$in = [
  'title'       => trim((string) ($_POST['title'] ?? '')),
  'category_id' => (int) ($_POST['category_id'] ?? 0),
  'venue'       => trim((string) ($_POST['venue'] ?? '')),
  'city'        => (string) ($_POST['city'] ?? current_city()),
  'map_url'     => trim((string) ($_POST['map_url'] ?? '')),
  'starts_at'   => (string) ($_POST['starts_at'] ?? ''),
  'ends_at'     => (string) ($_POST['ends_at'] ?? ''),
  'capacity'    => (int) ($_POST['capacity'] ?? 15),
  'price'       => (float) ($_POST['price'] ?? 0),
  'description' => trim((string) ($_POST['description'] ?? '')),
  'visibility'  => (string) ($_POST['visibility'] ?? 'public'),
  'community_id'=> (int) ($_POST['community_id'] ?? 0),
  'is_event'    => !empty($_POST['is_event']) && is_organizer() ? 1 : 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!rate_limit('create_plan', 10, 3600)) {
        $errors[] = 'You have created a lot of plans in the last hour. Take a breather and try again later.';
    }
    if (mb_strlen($in['title']) < 4) {
        $errors[] = 'Give the plan a title people will recognise — at least 4 characters.';
    }
    if ($in['category_id'] <= 0) {
        $errors[] = 'Pick what kind of plan this is.';
    }
    if ($in['venue'] === '') {
        $errors[] = 'Add a meeting point — "Cubbon Park, Gate 3" beats "Bangalore".';
    }
    if (!in_array($in['city'], CITIES, true)) {
        $errors[] = 'Choose a city from the list.';
    }

    $startTs = $in['starts_at'] !== '' ? strtotime($in['starts_at']) : false;
    if ($startTs === false) {
        $errors[] = 'Set a start date and time.';
    } elseif ($startTs < time() - 3600) {
        $errors[] = 'Start time is in the past.';
    }

    $endTs = $in['ends_at'] !== '' ? strtotime($in['ends_at']) : null;
    if ($endTs !== null && $endTs !== false && $startTs && $endTs <= $startTs) {
        $errors[] = 'End time has to be after the start time.';
    }

    if ($in['capacity'] < 1 || $in['capacity'] > 5000) {
        $errors[] = 'Capacity should be between 1 and 5000.';
    }
    if ($in['price'] < 0) {
        $errors[] = 'Price cannot be negative.';
    }
    if ($in['map_url'] !== '' && !filter_var($in['map_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'The map link does not look like a valid URL.';
    }
    if ($in['community_id'] > 0 && !in_array($in['community_id'], array_column($myCommunities, 'id'))) {
        $errors[] = 'You can only post plans into communities you run.';
    }

    $cover = null;
    if (!$errors) {
        try {
            $cover = handle_upload('cover', 'covers');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $slug = unique_slug('plans', slugify($in['title']));

        $planId = qi(
            "INSERT INTO plans
              (slug, title, description, category_id, community_id, host_id, city, venue, map_url,
               starts_at, ends_at, capacity, price, cover, visibility, is_event, going_count)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)",
            [
              $slug, $in['title'], $in['description'] ?: null, $in['category_id'],
              $in['community_id'] ?: null, $me['id'], $in['city'], $in['venue'],
              $in['map_url'] ?: null,
              date('Y-m-d H:i:s', $startTs),
              $endTs ? date('Y-m-d H:i:s', $endTs) : null,
              $in['capacity'], $in['price'], $cover, $in['visibility'], $in['is_event'],
            ]
        );

        // The host is automatically the first person going.
        qx("INSERT INTO plan_participants (plan_id, user_id, status) VALUES (?, ?, 'going')", [$planId, $me['id']]);
        refresh_plan_count($planId);

        // Tell the community what just got scheduled.
        if ($in['community_id'] > 0) {
            $members = q("SELECT user_id FROM community_members WHERE community_id = ? AND user_id <> ? LIMIT 500",
                         [$in['community_id'], $me['id']]);
            foreach ($members as $m) {
                notify((int) $m['user_id'], '📅', $me['name'] . ' scheduled "' . $in['title'] . '"', 'plan.php?s=' . $slug);
            }
        }

        redirect('plan-created.php?id=' . $planId);
    }
}

$nav        = 'create';
$page_title = 'Create a plan';
$page_desc  = 'Drop a plan on QuestScene and share one link instead of forty group messages.';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight" style="max-width:760px">

  <h1 style="font-size:clamp(1.7rem,5.5vw,2.5rem)">Drop a <span class="grad-text">plan</span></h1>
  <p class="muted" style="margin-bottom:24px">
    One link instead of forty messages. Everyone sees the time, the place, and who's already in.
  </p>

  <?php if ($errors): ?>
    <div class="form-error">
      <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="panel">
    <?= csrf_field() ?>

    <!-- 1. What -->
    <div class="field">
      <span class="field-label">What are you planning?</span>
      <div class="optgrid">
        <?php foreach (all_categories() as $c): ?>
          <input type="radio" name="category_id" id="cat<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>"
                 <?= $in['category_id'] === (int) $c['id'] ? 'checked' : '' ?> required>
          <label for="cat<?= (int) $c['id'] ?>">
            <span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <hr class="divider">

    <!-- 2. Title -->
    <div class="field">
      <label for="title">Call it something clear</label>
      <input class="input" type="text" name="title" id="title" maxlength="140" required
             value="<?= e($in['title']) ?>" placeholder="Sunday Sunrise 5K at Cubbon Park">
      <span class="hint">People decide from this line alone. "Sunday Sunrise 5K" beats "Run".</span>
    </div>

    <!-- 3. Where -->
    <div class="field-row">
      <div class="field">
        <label for="venue">Meeting point</label>
        <input class="input" type="text" name="venue" id="venue" maxlength="160" required
               value="<?= e($in['venue']) ?>" placeholder="Cubbon Park, Gate 3">
      </div>
      <div class="field">
        <label for="city">City</label>
        <select class="select" name="city" id="city">
          <?php foreach (CITIES as $c): ?>
            <option value="<?= e($c) ?>" <?= $c === $in['city'] ? 'selected' : '' ?>><?= e($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="map_url">Google Maps link <span class="muted">(optional, but people love it)</span></label>
      <input class="input" type="url" name="map_url" id="map_url" maxlength="400"
             value="<?= e($in['map_url']) ?>" placeholder="https://maps.app.goo.gl/…">
    </div>

    <!-- 4. When -->
    <div class="field-row">
      <div class="field">
        <label for="starts_at">Starts</label>
        <input class="input" type="datetime-local" name="starts_at" id="starts_at" required value="<?= e($in['starts_at']) ?>">
      </div>
      <div class="field">
        <label for="ends_at">Ends <span class="muted">(optional)</span></label>
        <input class="input" type="datetime-local" name="ends_at" id="ends_at" value="<?= e($in['ends_at']) ?>">
      </div>
    </div>

    <!-- 5. How many / cost -->
    <div class="field-row">
      <div class="field">
        <label for="capacity">How many people?</label>
        <input class="input" type="number" name="capacity" id="capacity" min="1" max="5000" required value="<?= (int) $in['capacity'] ?>">
        <span class="hint">You're counted as the first one.</span>
      </div>
      <div class="field">
        <label for="price">Cost per person (₹)</label>
        <input class="input" type="number" name="price" id="price" min="0" step="1" value="<?= (int) $in['price'] ?>">
        <span class="hint">Leave 0 for free. Collect money outside QuestScene for now.</span>
      </div>
    </div>

    <!-- 6. Details -->
    <div class="field">
      <label for="description">Anything else people should know?</label>
      <textarea class="textarea" name="description" id="description" maxlength="2000"
                placeholder="Casual 5K, all levels welcome. We meet at the gate and start at 6:30 sharp. Bring water."><?= e($in['description']) ?></textarea>
      <span class="hint"><span data-count-for="description">0</span> characters</span>
    </div>

    <div class="field">
      <label for="cover">Cover photo <span class="muted">(optional)</span></label>
      <input class="input" type="file" name="cover" id="cover" accept="image/*">
      <span class="hint">JPG, PNG or WEBP up to 4 MB. Without one we use your category icon.</span>
    </div>

    <hr class="divider">

    <!-- 7. Who can join -->
    <div class="field-row">
      <div class="field">
        <label for="visibility">Who can join?</label>
        <select class="select" name="visibility" id="visibility">
          <option value="public"    <?= $in['visibility'] === 'public'    ? 'selected' : '' ?>>Anyone on QuestScene</option>
          <option value="community" <?= $in['visibility'] === 'community' ? 'selected' : '' ?>>Community members only</option>
          <option value="invite"    <?= $in['visibility'] === 'invite'    ? 'selected' : '' ?>>Only people with the link</option>
        </select>
      </div>

      <?php if ($myCommunities): ?>
      <div class="field">
        <label for="community_id">Post under a community</label>
        <select class="select" name="community_id" id="community_id">
          <option value="0">Just me</option>
          <?php foreach ($myCommunities as $g): ?>
            <option value="<?= (int) $g['id'] ?>" <?= $in['community_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>

    <?php if (is_organizer()): ?>
      <div class="field">
        <label style="display:flex;gap:9px;align-items:center;cursor:pointer">
          <input type="checkbox" name="is_event" value="1" <?= $in['is_event'] ? 'checked' : '' ?>>
          <span>List this as an <strong>Event</strong> (organizer-hosted, shows in the Events tab)</span>
        </label>
      </div>
    <?php endif; ?>

    <button class="btn btn-grad btn-lg btn-block" type="submit">Post Plan</button>
    <p class="muted center" style="font-size:.79rem;margin:12px 0 0">
      You can edit or cancel it any time. Everyone who joined gets notified.
    </p>
  </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
