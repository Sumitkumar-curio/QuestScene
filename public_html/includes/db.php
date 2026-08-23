<?php
/**
 * QuestScene — PDO connection.
 * Every query in the app goes through db() with bound parameters.
 */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        if (DEV_MODE) {
            die('<pre>Database connection failed: ' . htmlspecialchars($e->getMessage()) . "\n\n"
              . "Check DB_NAME / DB_USER / DB_PASS in includes/config.php</pre>");
        }
        die('QuestScene is temporarily unavailable. Please try again shortly.');
    }

    return $pdo;
}

/** Run a query and return all rows. */
function q(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Run a query and return the first row, or null. */
function q1(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Run a query and return a single scalar value. */
function qv(string $sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

/** Execute a write. Returns affected row count. */
function qx(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

/** Execute an INSERT and return the new id. */
function qi(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId();
}
