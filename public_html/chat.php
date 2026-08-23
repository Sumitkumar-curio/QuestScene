<?php
/**
 * QuestScene — Chat.
 * Deliberately NOT open DMs. You get a room because you joined a plan or a
 * community. That single rule removes most of the spam and all of the
 * "is this a dating app?" feeling.
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/queries.php';
require_once __DIR__ . '/includes/components.php';

require_login('chat.php');
$me = current_user();

// --- rooms this user is allowed into -------------------------------
$planRooms = q(
    "SELECT p.id, p.title, p.slug, p.starts_at, c.emoji,
            (SELECT COUNT(*) FROM messages m WHERE m.room_type='plan' AND m.room_id=p.id) AS msg_count
       FROM plans p
       LEFT JOIN categories c ON c.id = p.category_id
       LEFT JOIN plan_participants pp ON pp.plan_id = p.id AND pp.user_id = ? AND pp.status IN ('going','waitlist')
      WHERE p.status = 'active'
        AND (p.host_id = ? OR pp.user_id IS NOT NULL)
        AND p.starts_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      ORDER BY p.starts_at ASC",
    [$me['id'], $me['id']]
);

$commRooms = q(
    "SELECT g.id, g.name, g.slug, g.cover, c.emoji,
            (SELECT COUNT(*) FROM messages m WHERE m.room_type='community' AND m.room_id=g.id) AS msg_count
       FROM communities g
       JOIN community_members cm ON cm.community_id = g.id AND cm.user_id = ?
       LEFT JOIN categories c ON c.id = g.category_id
      WHERE g.status = 'active'
      ORDER BY g.name",
    [$me['id']]
);

// --- which room are we in? -----------------------------------------
$type   = ($_GET['type'] ?? '') === 'community' ? 'community' : 'plan';
$roomId = (int) ($_GET['id'] ?? 0);

if ($roomId === 0) {
    if ($planRooms)      { $type = 'plan';      $roomId = (int) $planRooms[0]['id']; }
    elseif ($commRooms)  { $type = 'community'; $roomId = (int) $commRooms[0]['id']; }
}

$allowed  = $roomId > 0 && can_access_room($type, $roomId, (int) $me['id']);
$roomName = '';
$roomSub  = '';
$roomIcon = '💬';
$roomLink = null;

if ($allowed) {
    if ($type === 'plan') {
        $p = find_plan($roomId);
        $roomName = $p['title'];
        $roomSub  = fmt_when($p['starts_at']) . ' · ' . $p['going_count'] . ' going';
        $roomIcon = $p['cat_emoji'] ?: '💬';
        $roomLink = plan_url($p);
    } else {
        $g = q1("SELECT g.*, c.emoji FROM communities g LEFT JOIN categories c ON c.id = g.category_id WHERE g.id = ?", [$roomId]);
        $roomName = $g['name'];
        $roomSub  = number_format((int) $g['member_count']) . ' members';
        $roomIcon = $g['emoji'] ?: '👥';
        $roomLink = community_url($g);
    }
}

$messages = [];
$lastId   = 0;
if ($allowed) {
    $messages = q(
        "SELECT m.id, m.body, m.created_at, m.user_id, u.name, u.avatar
           FROM messages m JOIN users u ON u.id = m.user_id
          WHERE m.room_type = ? AND m.room_id = ?
            AND m.user_id NOT IN (SELECT blocked_id FROM blocks WHERE user_id = ?)
          ORDER BY m.id DESC LIMIT 80",
        [$type, $roomId, $me['id']]
    );
    $messages = array_reverse($messages);
    if ($messages) $lastId = (int) end($messages)['id'];
}

$nav        = 'chat';
$page_title = $allowed ? $roomName . ' · Chat' : 'Chat';
require __DIR__ . '/includes/header.php';
?>

<div class="wrap section-tight">
  <h1 style="font-size:1.6rem;margin-bottom:.15em">Chat</h1>
  <p class="muted" style="margin-bottom:18px;font-size:.88rem">
    You get a room when you join a plan or a community. No open DMs — that keeps QuestScene about what people are doing.
  </p>

  <div class="chat-layout">

    <!-- ---------- Room list ---------- -->
    <aside>
      <?php if ($planRooms): ?>
        <h2 style="font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 8px">Plan chats</h2>
        <div class="room-list" style="margin-bottom:20px">
          <?php foreach ($planRooms as $r): ?>
            <a class="room <?= ($type === 'plan' && (int) $r['id'] === $roomId) ? 'active' : '' ?>"
               href="<?= url('chat.php?type=plan&id=' . (int) $r['id']) ?>">
              <span class="room-ico"><?= e($r['emoji'] ?: '💬') ?></span>
              <span class="room-body">
                <strong><?= e($r['title']) ?></strong>
                <span><?= e(fmt_day($r['starts_at'])) ?> · <?= (int) $r['msg_count'] ?> messages</span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($commRooms): ?>
        <h2 style="font-size:.78rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin:0 0 8px">Community chats</h2>
        <div class="room-list">
          <?php foreach ($commRooms as $r): ?>
            <a class="room <?= ($type === 'community' && (int) $r['id'] === $roomId) ? 'active' : '' ?>"
               href="<?= url('chat.php?type=community&id=' . (int) $r['id']) ?>">
              <span class="room-ico"><?= e($r['emoji'] ?: '👥') ?></span>
              <span class="room-body">
                <strong><?= e($r['name']) ?></strong>
                <span><?= (int) $r['msg_count'] ?> messages</span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$planRooms && !$commRooms): ?>
        <div class="panel panel-tight">
          <p class="muted" style="font-size:.87rem;margin-bottom:12px">No rooms yet. Join a plan and its chat opens automatically.</p>
          <a class="btn btn-grad btn-sm btn-block" href="<?= url('discover.php') ?>">Find a plan</a>
        </div>
      <?php endif; ?>
    </aside>

    <!-- ---------- Message panel ---------- -->
    <div>
      <?php if (!$allowed): ?>
        <div class="chat-panel">
          <div class="chat-locked" style="margin:auto">
            <span style="font-size:2rem;display:block;margin-bottom:10px">🔒</span>
            <strong style="display:block;margin-bottom:6px;color:var(--text)">Pick a room</strong>
            <?= $roomId > 0 ? 'You are not in this plan or community yet.' : 'Choose a chat on the left to start talking.' ?>
          </div>
        </div>
      <?php else: ?>
        <div class="chat-panel" id="chat"
             data-room-type="<?= e($type) ?>" data-room-id="<?= (int) $roomId ?>" data-last-id="<?= (int) $lastId ?>">

          <div class="chat-head">
            <span class="room-ico" style="width:36px;height:36px;font-size:1rem"><?= e($roomIcon) ?></span>
            <div style="flex:1;min-width:0">
              <strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($roomName) ?></strong>
              <span class="sub"><?= e($roomSub) ?></span>
            </div>
            <a class="btn btn-ghost btn-sm hide-sm" href="<?= e($roomLink) ?>">Open</a>
          </div>

          <div class="chat-scroll">
            <?php if (!$messages): ?>
              <p class="chat-locked" style="margin:auto">No messages yet. Say hi — logistics are easier here than in a group chat.</p>
            <?php endif; ?>

            <?php foreach ($messages as $m): ?>
              <div class="msg <?= (int) $m['user_id'] === (int) $me['id'] ? 'mine' : '' ?>">
                <?php render_avatar($m, 'xs'); ?>
                <div>
                  <div class="msg-body">
                    <div class="msg-who"><?= e($m['name']) ?></div>
                    <div class="msg-text"><?= e($m['body']) ?></div>
                  </div>
                  <div class="msg-time"><?= e(ago($m['created_at'])) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <form class="chat-form">
            <input type="text" name="body" maxlength="1000" autocomplete="off"
                   placeholder="Message <?= e($roomName) ?>…" aria-label="Message">
            <button class="chat-send" type="submit" aria-label="Send">→</button>
          </form>
        </div>

        <p class="muted" style="font-size:.78rem;margin-top:10px">
          Be decent. Messages are visible to everyone in this room and can be reported.
          Voice rooms are on the roadmap — see <a href="<?= url('about.php#roadmap') ?>" style="color:var(--brand-3)">what's next</a>.
        </p>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
