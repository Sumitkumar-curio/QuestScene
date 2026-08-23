<?php
/**
 * QuestScene — one-time installer.
 *
 * 1. Import database/schema.sql through phpMyAdmin.
 * 2. Set INSTALL_KEY in includes/config.php.
 * 3. Open  https://yourdomain.com/install.php  and fill this form.
 * 4. DELETE THIS FILE.
 *
 * It creates your admin account and (optionally) seeds enough demo content
 * that the homepage does not look abandoned on day one.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';

$done   = [];
$errors = [];

// --- guard rails ----------------------------------------------------
$tablesOk = true;
try {
    qv("SELECT COUNT(*) FROM users");
} catch (Throwable $e) {
    $tablesOk = false;
    $errors[] = 'Database tables are missing. Import database/schema.sql first (phpMyAdmin → Import).';
}

$hasAdmin = $tablesOk && (bool) qv("SELECT id FROM users WHERE role = 'admin' LIMIT 1");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tablesOk) {
    csrf_check();

    $key      = (string) ($_POST['install_key'] ?? '');
    $name     = trim((string) ($_POST['name'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $seed     = !empty($_POST['seed']);

    if (!hash_equals(INSTALL_KEY, $key))            $errors[] = 'Install key does not match INSTALL_KEY in includes/config.php.';
    if (mb_strlen($name) < 2)                        $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errors[] = 'Enter a valid email.';
    if (strlen($password) < 8)                       $errors[] = 'Password needs at least 8 characters.';
    if ($hasAdmin)                                   $errors[] = 'An admin already exists. Delete install.php — you do not need it any more.';
    if (!$errors && qv("SELECT id FROM users WHERE email = ?", [$email])) {
        $errors[] = 'That email is already registered.';
    }

    if (!$errors) {
        // ---- admin account ----
        $adminHandle = 'admin';
        $n = 0;
        while (qv("SELECT id FROM users WHERE username = ?", [$adminHandle])) {
            $adminHandle = 'admin' . (++$n);
        }

        $adminId = qi(
            "INSERT INTO users (name, username, email, password_hash, city, role, email_verified, phone_verified, id_verified)
             VALUES (?, ?, ?, ?, ?, 'admin', 1, 1, 1)",
            [$name, $adminHandle, $email, password_hash($password, PASSWORD_DEFAULT), DEFAULT_CITY]
        );
        $done[] = "Admin account created for {$email}.";

        if ($seed) {
            $catId = function (string $slug): int {
                return (int) qv("SELECT id FROM categories WHERE slug = ?", [$slug]);
            };

            // ---- a few organizer accounts ----
            $hosts = [
                ['Rahul Menon',   'rahulmenon',  'rahul@example.com'],
                ['Priya Nair',    'priyanair',   'priya@example.com'],
                ['Arjun Shetty',  'arjunshetty', 'arjun@example.com'],
                ['Divya Rao',     'divyarao',    'divya@example.com'],
            ];
            $hostIds = [];
            foreach ($hosts as [$hName, $hUser, $hMail]) {
                $existing = qv("SELECT id FROM users WHERE username = ?", [$hUser]);
                $hostIds[] = $existing ? (int) $existing : qi(
                    "INSERT INTO users (name, username, email, password_hash, city, role, phone_verified)
                     VALUES (?, ?, ?, ?, ?, 'organizer', 1)",
                    [$hName, $hUser, $hMail, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), DEFAULT_CITY]
                );
            }
            $done[] = count($hostIds) . ' demo organizer accounts created (random passwords — reset or delete them later).';

            // ---- communities ----
            $communities = [
                ['Bangalore Sunrise Runners', 'running',  'We run 5–10K across Bangalore every Sunday morning. All paces welcome — nobody gets left behind.'],
                ['Weekend Trek Crew',         'trekking', 'One trek every weekend within 150km of Bangalore. Beginners genuinely welcome.'],
                ['HSR Football Collective',   'sports',   'Turf football twice a week in HSR and Koramangala. Mixed skill, no egos.'],
                ['Bangalore Founders Circle', 'founders', 'Early-stage founders meeting monthly to swap notes on what is actually working.'],
                ['Cubbon Cycling Club',       'cycling',  'Early morning rides through Bangalore before the traffic wakes up.'],
                ['Makers & Creators BLR',     'hobbies',  'Photography walks, sketch meets and craft sessions around the city.'],
            ];

            $commIds = [];
            foreach ($communities as $i => [$cName, $cCat, $cDesc]) {
                $slug = unique_slug('communities', slugify($cName));
                $owner = $hostIds[$i % count($hostIds)];
                $cid = qi(
                    "INSERT INTO communities (slug, name, description, city, category_id, owner_id, verified, member_count)
                     VALUES (?,?,?,?,?,?,?,1)",
                    [$slug, $cName, $cDesc, DEFAULT_CITY, $catId($cCat), $owner, $i < 3 ? 1 : 0]
                );
                qx("INSERT INTO community_members (community_id, user_id, role) VALUES (?, ?, 'owner')", [$cid, $owner]);

                // Put every host in every community so the pages have members.
                foreach ($hostIds as $h) {
                    qx("INSERT IGNORE INTO community_members (community_id, user_id) VALUES (?, ?)", [$cid, $h]);
                }
                qx("INSERT IGNORE INTO community_members (community_id, user_id) VALUES (?, ?)", [$cid, $adminId]);
                refresh_community_count($cid);
                $commIds[] = $cid;
            }
            $done[] = count($commIds) . ' communities created.';

            // ---- plans across the next three weeks ----
            $plans = [
                ['Sunday Sunrise 5K',           'running',  'Cubbon Park, Gate 3',        '+3 days 06:30', 25, 0,   0, 'Casual 5K, all levels welcome. We meet at the gate and start at 6:30 sharp. Bring water.'],
                ['Nandi Hills Sunrise Trek',    'trekking', 'Nandi Hills base',           '+5 days 04:30', 20, 350, 1, 'We leave the city at 4:30 AM to catch sunrise from the top. Transport shared between everyone who joins.'],
                ['Turf Football — HSR',         'sports',   'PlayArena, HSR Layout',      '+2 days 19:00', 14, 200, 0, '7-a-side on turf. Bring your own boots. We split the turf cost.'],
                ['Morning Ride to Hesaraghatta','cycling',  'Hebbal flyover',             '+6 days 05:45', 15, 0,   0, '60km round trip at a relaxed pace. Road or hybrid bike, helmet mandatory.'],
                ['Photography Walk: Malleswaram','hobbies', 'Malleswaram 8th Cross',      '+4 days 07:00', 12, 0,   1, 'Slow walk through old Malleswaram shooting street and architecture. Any camera, phones included.'],
                ['Founders Coffee — Indiranagar','founders','Third Wave, Indiranagar',    '+7 days 10:00', 18, 0,   1, 'Informal founders meet. Turn up, say what you are building and what is blocking you.'],
                ['Badminton Doubles',           'sports',   'Padukone Academy, Jayanagar','+1 days 20:00', 8,  150, 0, 'Two courts booked. Intermediate level. Shuttles provided.'],
                ['Beginner Trek: Skandagiri',   'trekking', 'Skandagiri base camp',       '+12 days 03:30', 25, 400, 1, 'Night trek to catch the sunrise above the clouds. First-timers welcome, moderate fitness needed.'],
                ['10K Long Run',                'running',  'Sankey Tank',                '+10 days 06:00', 30, 0,  0, 'Steady 10K around Sankey and Malleswaram. Two pace groups.'],
                ['Board Game Night',            'gaming',   'Koramangala 5th Block',      '+8 days 18:30', 16, 0,   0, 'Catan, Codenames, Wingspan. Bring a game if you have one.'],
                ['Sketch Meet at Lalbagh',      'hobbies',  'Lalbagh West Gate',          '+9 days 08:00', 14, 0,   0, 'Two hours of drawing in the garden. All skill levels, bring your own materials.'],
                ['Sunday Trek: Savandurga',     'trekking', 'Savandurga base',            '+14 days 05:30', 22, 300, 1, 'Steep rock climb with a serious view. Good shoes essential.'],
            ];

            $planCount = 0;
            foreach ($plans as $i => [$pTitle, $pCat, $pVenue, $pWhen, $pCap, $pPrice, $pIsEvent, $pDesc]) {
                $host  = $hostIds[$i % count($hostIds)];
                $comm  = $commIds[$i % count($commIds)];
                $slug  = unique_slug('plans', slugify($pTitle));
                $start = date('Y-m-d H:i:s', strtotime($pWhen));

                $pid = qi(
                    "INSERT INTO plans (slug, title, description, category_id, community_id, host_id, city, venue,
                                        starts_at, capacity, price, is_event, verified, going_count)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)",
                    [$slug, $pTitle, $pDesc, $catId($pCat), $comm, $host, DEFAULT_CITY, $pVenue,
                     $start, $pCap, $pPrice, $pIsEvent, $pIsEvent]
                );

                // Host plus a couple of others, so nothing shows "0 going".
                qx("INSERT IGNORE INTO plan_participants (plan_id, user_id, status) VALUES (?, ?, 'going')", [$pid, $host]);
                foreach (array_slice($hostIds, 0, 1 + ($i % 3)) as $h) {
                    qx("INSERT IGNORE INTO plan_participants (plan_id, user_id, status) VALUES (?, ?, 'going')", [$pid, $h]);
                }
                refresh_plan_count($pid);
                $planCount++;
            }
            $done[] = "{$planCount} plans and events created across the next two weeks.";

            // ---- a few forum posts ----
            $posts = [
                ['Anyone up for a 10K this Sunday morning? Thinking Sankey Tank loop, 6 AM start.', 'running', 'looking'],
                ['Best beginner treks near Bangalore? Have never done one and do not want to start with something brutal.', 'trekking', 'question'],
                ['Looking for 3 more people for badminton doubles tomorrow evening in Jayanagar.', 'sports', 'looking'],
                ['Anyone here cycling to Nandi regularly? Would like to tag along once before attempting it solo.', 'cycling', 'question'],
            ];
            foreach ($posts as $i => [$body, $pcat, $intent]) {
                qx("INSERT INTO posts (user_id, category_id, city, body, intent) VALUES (?,?,?,?,?)",
                   [$hostIds[$i % count($hostIds)], $catId($pcat), DEFAULT_CITY, $body, $intent]);
            }
            $done[] = count($posts) . ' forum posts created.';
        }

        login_user($adminId);
        $done[] = 'You are logged in as admin.';
    }
}

$page_title = 'Install QuestScene';
$nav = '';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section" style="max-width:620px">
  <h1 style="font-size:1.9rem">Install <span class="grad-text">QuestScene</span></h1>

  <?php if ($done): ?>
    <div class="flash flash-ok" style="margin-bottom:18px">
      <?php foreach ($done as $d) echo '<div>✓ ' . e($d) . '</div>'; ?>
    </div>
    <div class="panel">
      <h2 style="font-size:1.15rem">One last thing</h2>
      <p class="text-2">
        <strong style="color:var(--danger)">Delete install.php from your server now.</strong>
        Leaving it there is a standing invitation to anyone who guesses your install key.
      </p>
      <p class="text-2">Then set <code>DEV_MODE</code> to <code>false</code> in <code>includes/config.php</code>.</p>
      <div class="btn-row">
        <a class="btn btn-grad" href="<?= url('index.php') ?>">Open QuestScene</a>
        <a class="btn btn-ghost" href="<?= url('admin/index.php') ?>">Admin panel</a>
      </div>
    </div>

  <?php else: ?>

    <?php if ($errors): ?>
      <div class="form-error"><?php foreach ($errors as $e) echo '<div>• ' . e($e) . '</div>'; ?></div>
    <?php endif; ?>

    <?php if ($hasAdmin): ?>
      <div class="flash flash-info" style="margin-bottom:18px">
        QuestScene is already installed. Delete <code>install.php</code> from the server.
      </div>
    <?php endif; ?>

    <form method="post" class="panel">
      <?= csrf_field() ?>

      <div class="field">
        <label for="install_key">Install key</label>
        <input class="input" type="text" name="install_key" id="install_key" required
               placeholder="The INSTALL_KEY value from includes/config.php">
      </div>

      <div class="field">
        <label for="name">Your name</label>
        <input class="input" type="text" name="name" id="name" required maxlength="80">
      </div>

      <div class="field">
        <label for="email">Admin email</label>
        <input class="input" type="email" name="email" id="email" required maxlength="160">
      </div>

      <div class="field">
        <label for="password">Admin password</label>
        <input class="input" type="password" name="password" id="password" required minlength="8">
        <span class="hint">At least 8 characters. Use something you have not used elsewhere.</span>
      </div>

      <div class="field">
        <label style="display:flex;gap:9px;align-items:flex-start;cursor:pointer">
          <input type="checkbox" name="seed" value="1" checked style="margin-top:4px">
          <span>
            <strong>Seed demo content</strong><br>
            <span class="muted" style="font-size:.83rem">
              6 communities, 12 plans and a few forum posts in <?= e(DEFAULT_CITY) ?>, so the homepage
              is not empty on your first visit. Replace it with real content before you launch.
            </span>
          </span>
        </label>
      </div>

      <button class="btn btn-grad btn-lg btn-block" type="submit">Install QuestScene</button>
    </form>

    <p class="muted" style="font-size:.83rem;margin-top:16px">
      Before this works you must import <code>database/schema.sql</code> and fill in your database
      credentials in <code>includes/config.php</code>.
    </p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
