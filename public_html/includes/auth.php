<?php
/**
 * QuestScene — sessions, login state, CSRF.
 * Include this at the top of every page (it pulls in db + helpers).
 */

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_name('questscene');
    session_start();
}

require_once __DIR__ . '/helpers.php';

/* ----------------------------------------------------------------
 * Current user
 * ---------------------------------------------------------------- */

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) return $user;
    $loaded = true;

    if (empty($_SESSION['uid'])) return null;

    $user = q1("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['uid']]);

    if ($user === null) {           // deleted or suspended mid-session
        unset($_SESSION['uid']);
        return null;
    }

    // Cheap presence tracking — at most one write per 5 minutes.
    if (empty($_SESSION['seen_at']) || time() - $_SESSION['seen_at'] > 300) {
        qx("UPDATE users SET last_seen_at = NOW() WHERE id = ?", [$user['id']]);
        $_SESSION['seen_at'] = time();
    }

    return $user;
}

function uid(): ?int
{
    $u = current_user();
    return $u ? (int) $u['id'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $u = current_user();
    return $u !== null && $u['role'] === 'admin';
}

function is_organizer(): bool
{
    $u = current_user();
    return $u !== null && ($u['role'] === 'organizer' || $u['role'] === 'admin');
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    unset($_SESSION['seen_at']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ----------------------------------------------------------------
 * Guards
 * ---------------------------------------------------------------- */

function require_login(?string $returnTo = null): void
{
    if (is_logged_in()) return;
    $to = $returnTo ?? ($_SERVER['REQUEST_URI'] ?? '/');
    redirect('login.php?next=' . rawurlencode($to));
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('403 — Admins only.');
    }
}

/* ----------------------------------------------------------------
 * CSRF
 * ---------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page and try again.');
    }
}

/* ----------------------------------------------------------------
 * Simple rate limiting (per session) — keeps chat/report spam down.
 * ---------------------------------------------------------------- */

function rate_limit(string $key, int $maxHits, int $windowSecs): bool
{
    $now = time();
    $bucket = $_SESSION['rl'][$key] ?? ['start' => $now, 'hits' => 0];

    if ($now - $bucket['start'] > $windowSecs) {
        $bucket = ['start' => $now, 'hits' => 0];
    }
    $bucket['hits']++;
    $_SESSION['rl'][$key] = $bucket;

    return $bucket['hits'] <= $maxHits;
}
