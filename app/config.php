<?php
/**
 * Central configuration. Adjust MAIL_FROM_ADDRESS before going live;
 * everything else works out of the box on shared PHP hosting.
 */

declare(strict_types=1);

// Explicit, rather than relying on the host's php.ini default (which
// PHP otherwise warns about and which may not be what you expect).
date_default_timezone_set('Europe/Prague');

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('DB_PATH', DATA_DIR . '/inspira.sqlite');

// Shown as the sender of password-reset / invite emails.
define('MAIL_FROM_ADDRESS', 'centruminspira@gmail.com');
define('MAIL_FROM_NAME', 'INSPIRA');

// Base URL used to build links inside emails (no trailing slash).
// Deliberately not derived from the request's Host header, which a
// visitor can forge to point password-reset links at a different site.
// TESTING PHASE: pointed at www.silentiik.cz over plain http (its SSL
// isn't set up yet — https:// currently fails to connect at all).
// Switch to 'https://centruminspira.cz' once SSL is enabled here and
// you're ready to move to the real domain.
define('SITE_BASE_URL', 'http://www.silentiik.cz');

// When true, outgoing emails are written to data/mail.log instead of
// actually being sent — useful while testing before mail is confirmed
// working on the live host. Starts true on purpose: flip to false only
// once you've confirmed real emails arrive, so early testing can't
// accidentally email real people with broken/placeholder content.
define('MAIL_DEV_MODE', true);

// How long a password-reset or invite link stays valid.
define('RESET_TOKEN_TTL_SECONDS', 3600); // 1 hour
define('INVITE_TOKEN_TTL_SECONDS', 60 * 60 * 24 * 7); // 7 days

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

error_reporting(E_ALL);
ini_set('display_errors', '0'); // flip to '1' temporarily while debugging on a test URL
