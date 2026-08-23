<?php
/**
 * QuestScene — shared helpers (escaping, urls, dates, uploads, flashes).
 */

require_once __DIR__ . '/db.php';

/* ----------------------------------------------------------------
 * Output / URLs
 * ---------------------------------------------------------------- */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    $to = preg_match('~^https?://~', $path) ? $path : url($path);
    header('Location: ' . $to);
    exit;
}

function json_out($data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* ----------------------------------------------------------------
 * Flash messages
 * ---------------------------------------------------------------- */

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ----------------------------------------------------------------
 * Strings
 * ---------------------------------------------------------------- */

function slugify(string $text, string $suffix = ''): string
{
    $s = strtolower(trim($text));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') {
        $s = 'item';
    }
    $s = substr($s, 0, 100);
    return $suffix !== '' ? $s . '-' . $suffix : $s;
}

/** Make a slug unique within a table. */
function unique_slug(string $table, string $base): string
{
    $slug = $base;
    $n = 1;
    while (qv("SELECT id FROM {$table} WHERE slug = ?", [$slug])) {
        $slug = $base . '-' . (++$n);
    }
    return $slug;
}

function excerpt(?string $text, int $len = 130): string
{
    $t = trim(preg_replace('/\s+/', ' ', (string) $text));
    return mb_strlen($t) > $len ? mb_substr($t, 0, $len - 1) . '…' : $t;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = mb_strtoupper(mb_substr($parts[0] ?? 'Q', 0, 1));
    if (count($parts) > 1) {
        $out .= mb_strtoupper(mb_substr(end($parts), 0, 1));
    }
    return $out;
}

/* ----------------------------------------------------------------
 * Dates
 * ---------------------------------------------------------------- */

/** "Sun, 30 Aug · 6:30 AM" */
function fmt_when(string $datetime): string
{
    $ts = strtotime($datetime);
    return date('D, j M', $ts) . ' · ' . date('g:i A', $ts);
}

/** "Today", "Tomorrow", "Sun 30 Aug" */
function fmt_day(string $datetime): string
{
    $ts   = strtotime($datetime);
    $day  = date('Y-m-d', $ts);
    if ($day === date('Y-m-d'))                       return 'Today';
    if ($day === date('Y-m-d', strtotime('+1 day')))  return 'Tomorrow';
    return date('D j M', $ts);
}

function fmt_time(string $datetime): string
{
    return date('g:i A', strtotime($datetime));
}

/** "2h ago", "3d ago" */
function ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . 'm ago';
    if ($diff < 86400)   return floor($diff / 3600) . 'h ago';
    if ($diff < 604800)  return floor($diff / 86400) . 'd ago';
    return date('j M', strtotime($datetime));
}

/* ----------------------------------------------------------------
 * Images
 * ---------------------------------------------------------------- */

function avatar_url(?array $user): ?string
{
    if (!empty($user['avatar'])) {
        return UPLOAD_URL . '/avatars/' . rawurlencode($user['avatar']);
    }
    return null;
}

function cover_url(?string $file): ?string
{
    return $file ? UPLOAD_URL . '/covers/' . rawurlencode($file) : null;
}

/**
 * Handle an <input type="file"> upload.
 * Returns the stored filename, or null if nothing was uploaded.
 * Throws RuntimeException on a bad file.
 */
function handle_upload(string $field, string $subdir): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Try a smaller image.');
    }
    if ($f['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image must be under 4 MB.');
    }

    $info = @getimagesize($f['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('That file is not an image.');
    }

    $ext = match ($info[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
        default        => null,
    };
    if ($ext === null) {
        throw new RuntimeException('Use a JPG, PNG, GIF or WEBP image.');
    }

    $dir = UPLOAD_PATH . '/' . $subdir;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('Server cannot write uploads. Check folder permissions.');
    }

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the image.');
    }

    return $name;
}

/* ----------------------------------------------------------------
 * Brand
 * ---------------------------------------------------------------- */

/**
 * The QuestScene monogram, inline so the "S" trail can inherit currentColor.
 * The brand logo is navy-on-white; on the dark UI the navy half has to become
 * light or it vanishes — an <img> could never do that.
 */
function logo_mark(int $size = 28): string
{
    static $seq = 0;
    $id = 'qsGrad' . (++$seq);

    return <<<SVG
<svg class="logo-svg" width="{$size}" height="{$size}" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="{$id}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"   stop-color="#FFA23D"/>
      <stop offset="55%"  stop-color="#F97316"/>
      <stop offset="100%" stop-color="#EA5A0B"/>
    </linearGradient>
  </defs>
  <circle cx="24" cy="28" r="15" fill="none" stroke="url(#{$id})" stroke-width="7"
          stroke-linecap="round" stroke-dasharray="74 21" transform="rotate(28 24 28)"/>
  <path d="M25 41 L41 41 L31 55 Z" fill="url(#{$id})"/>
  <path d="M59 20 C59 12 44 12 44 20 C44 28 59 28 59 36 C59 44 44 44 44 36"
        fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round"/>
</svg>
SVG;
}

/**
 * Full lockup: monogram + wordmark (+ optional tagline).
 * If you drop your original artwork at assets/img/logo.png it is used instead.
 */
function logo_lockup(bool $tagline = false, int $size = 30): string
{
    $out = '<span class="logo-lockup">';

    if (is_file(dirname(__DIR__) . '/assets/img/logo.png')) {
        $out .= '<img class="logo-img" src="' . asset('img/logo.png') . '" alt="' . e(SITE_NAME) . '">';
    } else {
        $out .= logo_mark($size)
              . '<span class="logo-text">Quest<em>Scene</em></span>';
    }

    $out .= '</span>';

    if ($tagline) {
        $out .= '<span class="logo-tagline">Step. <em>Out.</em> Stand Out.</span>';
    }

    return $out;
}

/* ----------------------------------------------------------------
 * Domain helpers
 * ---------------------------------------------------------------- */

function all_categories(): array
{
    static $cats = null;
    if ($cats === null) {
        $cats = q("SELECT * FROM categories ORDER BY sort");
    }
    return $cats;
}

function category_by_slug(string $slug): ?array
{
    foreach (all_categories() as $c) {
        if ($c['slug'] === $slug) return $c;
    }
    return null;
}

function current_city(): string
{
    if (!empty($_GET['city']) && in_array($_GET['city'], CITIES, true)) {
        $_SESSION['city'] = $_GET['city'];
    }
    return $_SESSION['city'] ?? (current_user()['city'] ?? DEFAULT_CITY);
}

function notify(int $userId, string $icon, string $body, ?string $link = null): void
{
    qx(
        "INSERT INTO notifications (user_id, icon, body, link) VALUES (?, ?, ?, ?)",
        [$userId, $icon, $body, $link]
    );
}

function spots_left(array $plan): int
{
    return max(0, (int) $plan['capacity'] - (int) $plan['going_count']);
}

function plan_url(array $plan): string
{
    return url('plan.php?s=' . rawurlencode($plan['slug']));
}

function community_url(array $c): string
{
    return url('community.php?s=' . rawurlencode($c['slug']));
}

function user_rating(array $u): ?string
{
    if ((int) $u['rating_count'] === 0) return null;
    return number_format($u['rating_sum'] / $u['rating_count'], 1);
}

/** Highest verification badge a user has earned. */
function verify_badge(array $u): ?array
{
    if ($u['role'] === 'admin' || !empty($u['id_verified'])) {
        return ['label' => 'Identity Verified', 'class' => 'badge-id', 'dot' => '🔵'];
    }
    if (!empty($u['phone_verified'])) {
        return ['label' => 'Phone Verified', 'class' => 'badge-phone', 'dot' => '🟢'];
    }
    return null;
}
