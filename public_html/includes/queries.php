<?php
/**
 * QuestScene — reusable read queries.
 * Keeps the "select a plan with its host + category" join in one place.
 */

require_once __DIR__ . '/db.php';

/**
 * The standard plan projection.
 *
 * goer_names / goer_avatars carry the first three people going, pipe-separated,
 * so a card can show a face pile without an extra query per card. GROUP_CONCAT
 * is capped by group_concat_max_len (1024 by default) which is far more than
 * three names need.
 */
const PLAN_SELECT = "
    SELECT p.*,
           c.name  AS cat_name,  c.emoji AS cat_emoji, c.slug AS cat_slug,
           u.name  AS host_name, u.username AS host_username, u.avatar AS host_avatar,
           u.rating_sum AS host_rating_sum, u.rating_count AS host_rating_count,
           u.id_verified AS host_id_verified, u.phone_verified AS host_phone_verified,
           u.role AS host_role,
           g.name  AS comm_name, g.slug AS comm_slug,
           (SELECT SUBSTRING_INDEX(GROUP_CONCAT(u2.name ORDER BY pp2.joined_at SEPARATOR '||'), '||', 3)
              FROM plan_participants pp2 JOIN users u2 ON u2.id = pp2.user_id
             WHERE pp2.plan_id = p.id AND pp2.status = 'going') AS goer_names,
           (SELECT SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(u2.avatar,'') ORDER BY pp2.joined_at SEPARATOR '||'), '||', 3)
              FROM plan_participants pp2 JOIN users u2 ON u2.id = pp2.user_id
             WHERE pp2.plan_id = p.id AND pp2.status = 'going') AS goer_avatars
      FROM plans p
      LEFT JOIN categories  c ON c.id = p.category_id
      LEFT JOIN users       u ON u.id = p.host_id
      LEFT JOIN communities g ON g.id = p.community_id
";

function find_plan_by_slug(string $slug): ?array
{
    return q1(PLAN_SELECT . " WHERE p.slug = ? LIMIT 1", [$slug]);
}

function find_plan(int $id): ?array
{
    return q1(PLAN_SELECT . " WHERE p.id = ? LIMIT 1", [$id]);
}

/**
 * The main Discover query. Every filter is optional.
 *
 * $f = [city, cat, when(today|tomorrow|weekend|week), q, free, tab(plans|events|all),
 *       community_id, host_id, sort(soon|popular|new), limit, offset]
 */
function find_plans(array $f = []): array
{
    $where  = ["p.status = 'active'", "p.visibility = 'public'", "p.starts_at >= DATE_SUB(NOW(), INTERVAL 3 HOUR)"];
    $params = [];

    if (!empty($f['city'])) {
        $where[] = "p.city = ?";
        $params[] = $f['city'];
    }
    if (!empty($f['cat'])) {
        $where[] = "c.slug = ?";
        $params[] = $f['cat'];
    }
    if (!empty($f['q'])) {
        $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.venue LIKE ? OR c.name LIKE ?)";
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like, $like);
    }
    if (!empty($f['free'])) {
        $where[] = "p.price = 0";
    }
    if (!empty($f['community_id'])) {
        $where[] = "p.community_id = ?";
        $params[] = $f['community_id'];
    }
    if (!empty($f['host_id'])) {
        $where[] = "p.host_id = ?";
        $params[] = $f['host_id'];
    }

    switch ($f['tab'] ?? 'all') {
        case 'events': $where[] = "p.is_event = 1"; break;
        case 'plans':  $where[] = "p.is_event = 0"; break;
    }

    switch ($f['when'] ?? '') {
        case 'today':
            $where[] = "DATE(p.starts_at) = CURDATE()";
            break;
        case 'tomorrow':
            $where[] = "DATE(p.starts_at) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'weekend':
            // Saturday (7) or Sunday (1) within the next 8 days
            $where[] = "DAYOFWEEK(p.starts_at) IN (1, 7) AND p.starts_at <= DATE_ADD(NOW(), INTERVAL 8 DAY)";
            break;
        case 'week':
            $where[] = "p.starts_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)";
            break;
    }

    $order = match ($f['sort'] ?? 'soon') {
        'popular' => 'p.going_count DESC, p.starts_at ASC',
        'new'     => 'p.created_at DESC',
        default   => 'p.starts_at ASC',
    };

    $limit  = max(1, min(60, (int) ($f['limit'] ?? 12)));
    $offset = max(0, (int) ($f['offset'] ?? 0));

    $sql = PLAN_SELECT . ' WHERE ' . implode(' AND ', $where)
         . " ORDER BY {$order} LIMIT {$limit} OFFSET {$offset}";

    return q($sql, $params);
}

function is_going(int $planId, ?int $userId): bool
{
    if (!$userId) return false;
    return (bool) qv(
        "SELECT 1 FROM plan_participants WHERE plan_id = ? AND user_id = ? AND status IN ('going','waitlist')",
        [$planId, $userId]
    );
}

function is_saved(int $planId, ?int $userId): bool
{
    if (!$userId) return false;
    return (bool) qv("SELECT 1 FROM saved_plans WHERE plan_id = ? AND user_id = ?", [$planId, $userId]);
}

function plan_participants(int $planId, int $limit = 24): array
{
    return q(
        "SELECT u.id, u.name, u.username, u.avatar, pp.status
           FROM plan_participants pp
           JOIN users u ON u.id = pp.user_id
          WHERE pp.plan_id = ? AND pp.status IN ('going','waitlist')
          ORDER BY pp.joined_at ASC
          LIMIT {$limit}",
        [$planId]
    );
}

/** Can this user read/write the given chat room? */
function can_access_room(string $type, int $roomId, ?int $userId): bool
{
    if (!$userId) return false;

    if ($type === 'plan') {
        return (bool) qv(
            "SELECT 1 FROM plans p
              LEFT JOIN plan_participants pp ON pp.plan_id = p.id AND pp.user_id = ? AND pp.status IN ('going','waitlist')
             WHERE p.id = ? AND (p.host_id = ? OR pp.user_id IS NOT NULL)",
            [$userId, $roomId, $userId]
        );
    }

    if ($type === 'community') {
        return (bool) qv(
            "SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?",
            [$roomId, $userId]
        );
    }

    return false;
}

function find_communities(array $f = []): array
{
    $where  = ["g.status = 'active'"];
    $params = [];

    if (!empty($f['city'])) { $where[] = "g.city = ?";   $params[] = $f['city']; }
    if (!empty($f['cat']))  { $where[] = "c.slug = ?";   $params[] = $f['cat']; }
    if (!empty($f['q'])) {
        $where[] = "(g.name LIKE ? OR g.description LIKE ?)";
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like);
    }

    $limit  = max(1, min(60, (int) ($f['limit'] ?? 12)));
    $offset = max(0, (int) ($f['offset'] ?? 0));

    return q(
        "SELECT g.*, c.name AS cat_name, c.emoji AS cat_emoji
           FROM communities g
           LEFT JOIN categories c ON c.id = g.category_id
          WHERE " . implode(' AND ', $where) . "
          ORDER BY g.verified DESC, g.member_count DESC
          LIMIT {$limit} OFFSET {$offset}",
        $params
    );
}

function is_member(int $communityId, ?int $userId): bool
{
    if (!$userId) return false;
    return (bool) qv("SELECT 1 FROM community_members WHERE community_id = ? AND user_id = ?", [$communityId, $userId]);
}

/** Recompute denormalised counters after a join/leave. */
function refresh_plan_count(int $planId): void
{
    qx("UPDATE plans SET going_count =
          (SELECT COUNT(*) FROM plan_participants WHERE plan_id = ? AND status = 'going')
        WHERE id = ?", [$planId, $planId]);
}

function refresh_community_count(int $communityId): void
{
    qx("UPDATE communities SET member_count =
          (SELECT COUNT(*) FROM community_members WHERE community_id = ?)
        WHERE id = ?", [$communityId, $communityId]);
}
