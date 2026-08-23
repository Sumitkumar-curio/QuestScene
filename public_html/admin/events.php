<?php
/**
 * QuestScene admin — post an event.
 * This is how the team seeds the city before launch: post events on behalf of
 * any organizer or community so the homepage never says "no events found".
 */

$admin_page = 'events';
require __DIR__ . '/_nav.php';

$me     = current_user();
$errors = [];

$communities = q("SELECT id, name, city, owner_id FROM communities WHERE status='active' ORDER BY name");
$organizers  = q("SELECT id, name, username FROM users WHERE role IN ('organizer','admin') AND status='active' ORDER BY name");

$in = [
  'title'        => trim((string) ($_POST['title'] ?? '')),
  'category_id'  => (int) ($_POST['category_id'] ?? 0),
  'venue'        => trim((string) ($_POST['venue'] ?? '')),
  'city'         => (string) ($_POST['city'] ?? DEFAULT_CITY),
  'map_url'      => trim((string) ($_POST['map_url'] ?? '')),
  'starts_at'    => (string) ($_POST['starts_at'] ?? ''),
  'ends_at'      => (string) ($_POST['ends_at'] ?? ''),
  'capacity'     => (int) ($_POST['capacity'] ?? 50),
  'price'        => (float) ($_POST['price'] ?? 0),
  'description'  => trim((string) ($_POST['description'] ?? '')),
  'host_id'      => (int) ($_POST['host_id'] ?? $me['id']),
  'community_id' => (int) ($_POST['community_id'] ?? 0),
  'verified'     => !empty($_POST['verified']) ? 1 : 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    csrf_check();

    if (mb_strlen($in['title']) < 4)          $errors[] = 'Title is too short.';
    if ($in['category_id'] <= 0)              $errors[] = 'Pick a category.';
    if ($in['venue'] === '')                  $errors[] = 'Venue is required.';
    if (!in_array($in['city'], CITIES, true)) $errors[] = 'Pick a city.';

    $startTs = $in['starts_at'] !== '' ? strtotime($in['starts_at']) : false;
    if ($startTs === false) $errors[] = 'Start date/time is invalid.';

    $endTs = $in['ends_at'] !== '' ? strtotime($in['ends_at']) : null;
    if ($endTs && $startTs && $endTs <= $startTs) $errors[] = 'End time must be after the start.';

    if ($in['capacity'] < 1) $errors[] = 'Capacity must be at least 1.';
    if (!qv("SELECT id FROM users WHERE id = ?", [$in['host_id']])) $errors[] = 'Host account not found.';
    if ($in['map_url'] !== '' && !filter_var($in['map_url'], FILTER_VALIDATE_URL)) $errors[] = 'Map link is not a valid URL.';

    $cover = null;
    if (!$errors) {
        try { $cover = handle_upload('cover', 'covers'); }
        catch (RuntimeException $ex) { $errors[] = $ex->getMessage(); }
    }

    if (!$errors) {
        $slug = unique_slug('plans', slugify($in['title']));

        $planId = qi(
            "INSERT INTO plans
              (slug, title, description, category_id, community_id, host_id, city, venue, map_url,
               starts_at, ends_at, capacity, price, cover, visibility, is_event, verified, going_count)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'public', 1, ?, 1)",
            [
              $slug, $in['title'], $in['description'] ?: null, $in['category_id'],
              $in['community_id'] ?: null, $in['host_id'], $in['city'], $in['venue'],
              $in['map_url'] ?: null,
              date('Y-m-d H:i:s', $startTs),
              $endTs ? date('Y-m-d H:i:s', $endTs) : null,
              $in['capacity'], $in['price'], $cover, $in['verified'],
            ]
        );

        qx("INSERT INTO plan_participants (plan_id, user_id, status) VALUES (?, ?, 'going')", [$planId, $in['host_id']]);
        refresh_plan_count($planId);

        if ((int) $in['host_id'] !== (int) $me['id']) {
            notify($in['host_id'], '🎟', 'QuestScene published an event under your name: "' . $in['title'] . '"', 'plan.php?s=' . $slug);
        }

        flash('ok', 'Event published: ' . $in['title']);
        redirect('admin/events.php');
    }
}

// Quick toggles on existing events.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_verified') {
    csrf_check();
    $pid = (int) ($_POST['plan_id'] ?? 0);
    qx("UPDATE plans SET verified = 1 - verified WHERE id = ?", [$pid]);
    flash('ok', 'Verification flag updated.');
    redirect('admin/events.php');
}

$events = q(PLAN_SELECT . " WHERE p.is_event = 1 ORDER BY p.starts_at DESC LIMIT 40");
?>

<h1 style="font-size:1.8rem">Post an event</h1>
<p class="muted" style="margin-bottom:20px">
  Publish on behalf of an organizer or community. Use this to seed a city before launch —
  an empty homepage is the fastest way to lose a first-time visitor.
</p>

<?php if ($errors): ?>
  <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="panel" style="margin-bottom:26px">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">

  <div class="field">
    <label for="title">Event title</label>
    <input class="input" type="text" name="title" id="title" maxlength="140" required
           value="<?= e($in['title']) ?>" placeholder="Nandi Hills Sunrise Trek">
  </div>

  <div class="field">
    <span class="field-label">Category</span>
    <div class="optgrid">
      <?php foreach (all_categories() as $c): ?>
        <input type="radio" name="category_id" id="ac<?= (int) $c['id'] ?>" value="<?= (int) $c['id'] ?>"
               <?= $in['category_id'] === (int) $c['id'] ? 'checked' : '' ?> required>
        <label for="ac<?= (int) $c['id'] ?>"><span class="emo"><?= e($c['emoji']) ?></span><?= e($c['name']) ?></label>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="field-row">
    <div class="field">
      <label for="host_id">Publish as</label>
      <select class="select" name="host_id" id="host_id">
        <?php foreach ($organizers as $o): ?>
          <option value="<?= (int) $o['id'] ?>" <?= $in['host_id'] === (int) $o['id'] ? 'selected' : '' ?>>
            <?= e($o['name']) ?> (@<?= e($o['username']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
      <span class="hint">Only organizers and admins can be listed as event hosts.</span>
    </div>
    <div class="field">
      <label for="community_id">Under community</label>
      <select class="select" name="community_id" id="community_id">
        <option value="0">None</option>
        <?php foreach ($communities as $g): ?>
          <option value="<?= (int) $g['id'] ?>" <?= $in['community_id'] === (int) $g['id'] ? 'selected' : '' ?>>
            <?= e($g['name']) ?> — <?= e($g['city']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="field-row">
    <div class="field">
      <label for="venue">Venue</label>
      <input class="input" type="text" name="venue" id="venue" maxlength="160" required
             value="<?= e($in['venue']) ?>" placeholder="Nandi Hills base, Chikkaballapur">
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

  <div class="field-row">
    <div class="field">
      <label for="starts_at">Starts</label>
      <input class="input" type="datetime-local" name="starts_at" id="starts_at" required value="<?= e($in['starts_at']) ?>">
    </div>
    <div class="field">
      <label for="ends_at">Ends (optional)</label>
      <input class="input" type="datetime-local" name="ends_at" id="ends_at" value="<?= e($in['ends_at']) ?>">
    </div>
  </div>

  <div class="field-row">
    <div class="field">
      <label for="capacity">Capacity</label>
      <input class="input" type="number" name="capacity" id="capacity" min="1" max="5000" required value="<?= (int) $in['capacity'] ?>">
    </div>
    <div class="field">
      <label for="price">Price (₹)</label>
      <input class="input" type="number" name="price" id="price" min="0" step="1" value="<?= (int) $in['price'] ?>">
    </div>
  </div>

  <div class="field">
    <label for="map_url">Google Maps link</label>
    <input class="input" type="url" name="map_url" id="map_url" maxlength="400" value="<?= e($in['map_url']) ?>">
  </div>

  <div class="field">
    <label for="description">Description</label>
    <textarea class="textarea" name="description" id="description" maxlength="2000"><?= e($in['description']) ?></textarea>
  </div>

  <div class="field">
    <label for="cover">Cover image</label>
    <input class="input" type="file" name="cover" id="cover" accept="image/*">
  </div>

  <div class="field">
    <label style="display:flex;gap:9px;align-items:center;cursor:pointer">
      <input type="checkbox" name="verified" value="1" <?= $in['verified'] ? 'checked' : '' ?>>
      <span>Mark <strong>QuestScene Verified</strong> — only if the organizer has actually been checked</span>
    </label>
  </div>

  <button class="btn btn-grad btn-lg" type="submit">Publish event</button>
</form>

<h2 style="font-size:1.2rem">Published events (<?= count($events) ?>)</h2>
<div class="table-wrap">
  <table class="data">
    <thead><tr><th>Event</th><th>Host</th><th>When</th><th>Going</th><th>Verified</th><th></th></tr></thead>
    <tbody>
      <?php if (!$events): ?>
        <tr><td colspan="6" class="muted">No events yet. Publish the first one above.</td></tr>
      <?php endif; ?>
      <?php foreach ($events as $p): ?>
        <tr>
          <td><a href="<?= e(plan_url($p)) ?>"><?= e($p['cat_emoji']) ?> <?= e($p['title']) ?></a><br>
              <span class="muted" style="font-size:.75rem"><?= e($p['venue']) ?>, <?= e($p['city']) ?></span></td>
          <td class="nowrap"><?= e($p['host_name']) ?></td>
          <td class="nowrap muted"><?= e(fmt_when($p['starts_at'])) ?></td>
          <td class="nowrap"><?= (int) $p['going_count'] ?>/<?= (int) $p['capacity'] ?></td>
          <td><span class="pill <?= (int) $p['verified'] ? 'pill-ok' : '' ?>"><?= (int) $p['verified'] ? 'Yes' : 'No' ?></span></td>
          <td class="nowrap">
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_verified">
              <input type="hidden" name="plan_id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-ghost btn-sm" type="submit">Toggle</button>
            </form>
            <a class="btn btn-ghost btn-sm" href="<?= url('plan-edit.php?id=' . (int) $p['id']) ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
