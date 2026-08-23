<?php
/**
 * QuestScene — configuration
 * ---------------------------------------------------------------
 * This is the ONLY file you need to edit when moving between your
 * laptop and Hostinger.
 *
 * On Hostinger: hPanel -> Databases -> MySQL Databases
 * copy the database name / user / password it shows you.
 * DB_HOST stays 'localhost' on Hostinger shared hosting.
 *
 * LOCAL DEVELOPMENT
 * If includes/config.local.php exists it is loaded first and wins.
 * Keep your laptop's settings there and this file stays deployable —
 * config.local.php is never uploaded to the server.
 */

if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ---- Database -------------------------------------------------
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_PORT') || define('DB_PORT', 3306);
defined('DB_NAME') || define('DB_NAME', 'questscene');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');

// ---- Site -----------------------------------------------------
defined('SITE_NAME')    || define('SITE_NAME',    'QuestScene');
defined('SITE_TAGLINE') || define('SITE_TAGLINE', 'Step. Out. Stand Out.');

// No trailing slash. Local: http://localhost:8000
// Hostinger: https://questscene.com
defined('SITE_URL') || define('SITE_URL', 'http://localhost:8000');

defined('DEFAULT_CITY') || define('DEFAULT_CITY', 'Bangalore');
defined('CITIES')       || define('CITIES', ['Bangalore', 'Mumbai', 'Delhi', 'Hyderabad', 'Pune', 'Chennai']);

// ---- Contact & social ----------------------------------------
defined('CONTACT_EMAIL')     || define('CONTACT_EMAIL', 'questscene@gmail.com');
defined('SOCIAL_HANDLE')     || define('SOCIAL_HANDLE', '@questscene');
defined('SOCIAL_INSTAGRAM')  || define('SOCIAL_INSTAGRAM', 'https://instagram.com/questscene');
defined('SOCIAL_TELEGRAM')   || define('SOCIAL_TELEGRAM', 'https://t.me/questscene');

// ---- Uploads --------------------------------------------------
defined('UPLOAD_PATH')       || define('UPLOAD_PATH', dirname(__DIR__) . '/uploads');
defined('UPLOAD_URL')        || define('UPLOAD_URL',  SITE_URL . '/uploads');
defined('MAX_UPLOAD_BYTES')  || define('MAX_UPLOAD_BYTES', 4 * 1024 * 1024); // 4 MB

// ---- Security -------------------------------------------------
// Change this before uploading. install.php refuses to run without it.
defined('INSTALL_KEY') || define('INSTALL_KEY', 'change-me-questscene-2026');

// Set to false on the live site — hides PHP errors from visitors.
defined('DEV_MODE') || define('DEV_MODE', true);

// ---------------------------------------------------------------
date_default_timezone_set('Asia/Kolkata');

if (DEV_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
