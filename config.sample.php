<?php
/**
 * config.sample.php — user configuration TEMPLATE.
 *
 * Copy to `config.php` on the server and edit only what you need.
 * `config.php` is git-ignored, so `git pull` / updates never overwrite it.
 * All defaults + the engine (helpers, session) live in const.php.
 *
 * Only define values you want to override; anything omitted keeps its default
 * from const.php.
 */

// ---- core -------------------------------------------------------------------
define('API_BASE',     'http://127.0.0.1:26002');  // EVEmu API server
define('IMAGE_SERVER', 'http://127.0.0.1:26001');  // EVEmu image server
define('SITE_NAME',    'EVEmu');

// ---- outbound e-mail (SMTP) -------------------------------------------------
// Used for registration e-mail + 2FA codes and by /admin/emailtest.
// Encryption: 'tls' = STARTTLS (port 587), 'ssl' = implicit TLS (port 465),
// 'none' = plaintext (port 25). AUTH is used only when MAIL_USER is non-empty.
// define('MAIL_ENABLED', true);
// define('MAIL_HOST', 'smtp.example.com');
// define('MAIL_PORT', 465);
// define('MAIL_USER', 'no-reply@example.com');
// define('MAIL_PASS', 'secret');
// define('MAIL_FROM', 'no-reply@example.com');
// define('MAIL_FROM_NAME', 'EVEmu');
// define('MAIL_ENCRYPTION', 'ssl');

// ---- two-factor e-mail codes (require a working SMTP) -----------------------
// define('TFA_ENABLED', true);            // false disables 2FA entirely
// define('TFA_REQUIRE_NEW_DEVICE', true); // false → only admins get codes
// define('TFA_ADMIN_ALWAYS', true);

// ---- Web Push (needs HTTPS + VAPID keys from tools/gen_vapid.php) -----------
// define('PUSH_ENABLED', true);
// define('VAPID_PUBLIC_KEY', '...');
// define('VAPID_PRIVATE_KEY', '...');

// ---- session ----------------------------------------------------------------
// define('SESSION_LIFETIME', 8 * 3600);

// ---- engine (do not edit) ---------------------------------------------------
require_once __DIR__ . '/const.php';
