<?php
define('API_BASE', 'http://127.0.0.1:26002');
define('EVE_RENDER', 'https://images.evetech.net/types');
define('EVE_ICON', 'https://images.evetech.net/types');
define('IMAGE_SERVER', 'http://127.0.0.1:26001');
define('SITE_NAME', 'EVEmu');
define('PORTAL_VERSION', '1.0.0');
define('SESSION_LIFETIME', 86400);

// ---- Web Push (mail/notification events) ------------------------------------
// Real browser push needs a secure context (HTTPS) + Push API in the browser
// and php-openssl here. Off by default; flip to true once the portal is behind
// HTTPS and generate VAPID keys with tools/gen_vapid.php.
define('PUSH_ENABLED', false);
define('VAPID_PUBLIC_KEY',  '');   // base64url(applicationServerKey)
define('VAPID_PRIVATE_KEY', '');   // PKCS#8 PEM base64url without headers
define('VAPID_SUBJECT', 'mailto:admin@' . (parse_url(API_BASE, PHP_URL_HOST) ?: 'localhost'));
define('PUSH_DATA_DIR', __DIR__ . '/cache');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) session_start();

function api_get($path, $timeout = 5) {
    $url = API_BASE . $path;
    $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return null;
    return @simplexml_load_string($data);
}

function api_post($path, $body = '', $timeout = 10) {
    $url = API_BASE . $path;
    $opts = ['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $body,
        'timeout' => $timeout,
    ]];
    $ctx = stream_context_create($opts);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false) return null;
    return @simplexml_load_string($data);
}

function current_user() {
    if (isset($_SESSION['accountID']))
        return [
            'accountID' => $_SESSION['accountID'],
            'accountName' => $_SESSION['accountName'] ?? '',
            'role' => $_SESSION['role'] ?? 0,
        ];
    return null;
}

function is_logged_in() { return current_user() !== null; }

function has_role($bit) {
    $u = current_user();
    return $u && ($u['role'] & $bit);
}

// CCP account role bits (see eve-common/EVE_Roles.h Acct::Role)
define('ROLE_PLAYER',       0);
define('ROLE_GML',          18014398509481984);   // 0x40000000000000
define('ROLE_GMH',          9007199254740992);    // 0x20000000000000
define('ROLE_QA',           4503599627370496);    // 0x10000000000000
define('ROLE_WORLDMOD',     4096);
define('ROLE_CENTURION',    2048);
define('ROLE_ADMIN',        72057594037927936);   // 0x0100000000000000
define('ROLE_VIPLOGIN',     144115188075855872);  // 0x0200000000000000
define('ROLE_CONTENT',      36028797018963968);   // 0x0080000000000000
define('ROLE_CHTADMIN',     2097152);             // 0x200000
define('ROLE_LEGIONEER',    262144);              // 0x40000

function role_name($role) {
    $names = [];
    if ($role & ROLE_ADMIN)    $names[] = 'Admin';
    if ($role & ROLE_GMH)      $names[] = 'GMH';
    if ($role & ROLE_GML)      $names[] = 'GML';
    if ($role & ROLE_QA)       $names[] = 'QA';
    if ($role & ROLE_WORLDMOD) $names[] = 'WorldMod';
    if ($role & ROLE_CHTADMIN) $names[] = 'ChatAdmin';
    if (empty($names))         $names[] = 'Player';
    return implode(', ', $names);
}

function evetech_size($size) {
    $size = (int)$size;
    if ($size <= 48)  return 32;
    if ($size <= 96)  return 64;
    if ($size <= 192) return 128;
    if ($size <= 384) return 256;
    return 512;
}

function ship_icon($typeID, $size = 32) {
    if (!$typeID) return '';
    return '/img.php?url=' . urlencode(EVE_RENDER . '/' . $typeID . '/render?size=' . evetech_size($size));
}

function ship_type_icon($typeID, $size = 32) {
    if (!$typeID) return '';
    return '/img.php?url=' . urlencode(EVE_ICON . '/' . $typeID . '/icon?size=' . evetech_size($size));
}

function char_portrait($charID, $size = 64) {
    if (!$charID) return '';
    return IMAGE_SERVER . '/Character/' . $charID . '_' . $size . '.jpg';
}

function corp_logo($corpID, $size = 32) {
    if (!$corpID) return '';
    return IMAGE_SERVER . '/Corporation/' . $corpID . '_' . $size . '.png';
}

function alliance_logo($allianceID, $size = 32) {
    if (!$allianceID) return '';
    return IMAGE_SERVER . '/Alliance/' . $allianceID . '_' . $size . '.png';
}

function filetime_to_unix($filetime) {
    $ft = (int)$filetime;
    if ($ft <= 0) return 0;
    return intval(($ft - 116444736000000000) / 10000000);
}

function security_color($sec) {
    $sec = (float)$sec;
    if ($sec >= 0.5) return '#00ff00';
    if ($sec >= 0.0) return '#ffff00';
    if ($sec >= -0.5) return '#ff8800';
    if ($sec >= -0.8) return '#ff4400';
    return '#ff0000';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function time_ago($ts) {
    if ($ts <= 0) return '';
    $diff = time() - $ts;
    if ($diff < 0) return 'just now';
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}

function format_isk($val) {    $val = (float)$val;
    if ($val >= 1e12) return number_format($val / 1e12, 2) . 'T ISK';
    if ($val >= 1e9) return number_format($val / 1e9, 2) . 'B ISK';
    if ($val >= 1e6) return number_format($val / 1e6, 2) . 'M ISK';
    if ($val >= 1e3) return number_format($val / 1e3, 2) . 'K ISK';
    return number_format($val, 2) . ' ISK';
}

// Compact EVE-ish money like "57.86b" / "1.05t" (dot decimal, no spaces).
function isk_compact($val) {
    $val = (float)$val;
    if ($val >= 1e12) return rtrim(rtrim(number_format($val / 1e12, 2, '.', ''), '0'), '.') . 't';
    if ($val >= 1e9)  return rtrim(rtrim(number_format($val / 1e9, 2, '.', ''), '0'), '.') . 'b';
    if ($val >= 1e6)  return rtrim(rtrim(number_format($val / 1e6, 2, '.', ''), '0'), '.') . 'm';
    if ($val >= 1e3)  return rtrim(rtrim(number_format($val / 1e3, 2, '.', ''), '0'), '.') . 'k';
    return number_format($val, 0, '.', '');
}

function format_isk_full($val) {
    return number_format((float)$val, 2) . ' ISK';
}

function get_slot_name($flag) {
    $flag = (int)$flag;
    // EVE item flags (EVE_Flags.h): Low 11-18, Mid 19-26, High 27-34, Rig 92-94/155,
    // SubSystem 125-133, Cargo 5, Drone Bay 87/89.
    if ($flag >= 11 && $flag <= 18) return 'Low';
    if ($flag >= 19 && $flag <= 26) return 'Mid';
    if ($flag >= 27 && $flag <= 34) return 'High';
    if ($flag >= 92 && $flag <= 94) return 'Rig';
    if ($flag === 155) return 'Rig';
    if ($flag >= 125 && $flag <= 133) return 'Subsystem';
    if ($flag === 87 || $flag === 89) return 'Drone Bay';
    if ($flag === 5 || $flag === 0 || $flag === 8) return 'Cargo';
    return 'Other';
}

function slot_sort_order($name) {
    $order = ['High' => 0, 'Mid' => 1, 'Low' => 2, 'Rig' => 3, 'Subsystem' => 4, 'Ship' => 5, 'Cargo' => 6, 'Drone Bay' => 7, 'Other' => 8];
    return $order[$name] ?? 9;
}

// Generic client-side pager for admin lists. Preserves the current GET params
// (except page) so filters/view stay active. $items is a full array; returns
// nothing when it fits on one page. $out/$page/$pages are by-reference outputs
// (passed possibly-uninitialised by callers).
function paginate_admin($items, $perPage, &$out, &$page, &$pages): void {
    $perPage = max(1, (int)$perPage);
    $items   = is_array($items) ? array_values($items) : [];
    $pages   = max(1, (int)ceil(count($items) / $perPage));
    $page    = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $page    = max(1, min($pages, $page));
    $out     = array_slice($items, ($page - 1) * $perPage, $perPage);
}

function pager_html(int $page, int $pages): string {
    if ($pages <= 1) return '';
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $q = $_GET;
    $html = '<div class="pagination" style="margin-top:10px">';
    if ($page > 1) { $q['page'] = $page - 1; $html .= '<a href="' . htmlspecialchars($path . '?' . http_build_query($q), ENT_QUOTES, 'UTF-8') . '">&larr; Prev</a>'; }
    $html .= '<span>' . $page . ' / ' . $pages . '</span>';
    if ($page < $pages) { $q['page'] = $page + 1; $html .= '<a href="' . htmlspecialchars($path . '?' . http_build_query($q), ENT_QUOTES, 'UTF-8') . '">Next &rarr;</a>'; }
    $html .= '</div>';
    return $html;
}

// ---- push subscription store (JSON file in PUSH_DATA_DIR) --------------------
function push_subs_file(): string {
    if (!is_dir(PUSH_DATA_DIR)) @mkdir(PUSH_DATA_DIR, 0775, true);
    return rtrim(PUSH_DATA_DIR, '/') . '/push_subs.json';
}

function push_subs(): array {
    $f = push_subs_file();
    if (!is_file($f)) return [];
    $raw = @file_get_contents($f);
    $arr = json_decode((string)$raw, true);
    return is_array($arr) ? $arr : [];
}

function push_save_subs(array $subs): void {
    // key by endpoint
    $byKey = [];
    foreach ($subs as $s) if (isset($s['endpoint'])) $byKey[$s['endpoint']] = $s;
    file_put_contents(push_subs_file(), json_encode(array_values($byKey), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// base64url helpers (browser push keys are base64url, VAPID keys too)
function b64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function b64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}
