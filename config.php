<?php
define('API_BASE', 'http://127.0.0.1:26002');
define('ZKILLBOARD_SHIPS', 'https://images.zkillboard.com/renders');
define('ZKILLBOARD_TYPES', 'https://images.zkillboard.com/types');
define('IMAGE_SERVER', 'http://127.0.0.1:26001');
define('SITE_NAME', 'EVEmu');
define('PORTAL_VERSION', '1.0.0');
define('SESSION_LIFETIME', 86400);

session_start();

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

define('ROLE_PLAYER',       0);
define('ROLE_GML',          1 << 0);
define('ROLE_GMH',          1 << 1);
define('ROLE_CHTADMIN',     1 << 2);
define('ROLE_ADMIN',        1 << 3);
define('ROLE_QA',           1 << 4);
define('ROLE_WORLDMOD',     1 << 5);
define('ROLE_CENTURION',    1 << 6);
define('ROLE_LEGIONEER',    1 << 7);

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

function ship_icon($typeID, $size = 32) {
    if (!$typeID) return '';
    return '/img.php?url=' . urlencode(ZKILLBOARD_SHIPS . '/' . $typeID . '_' . $size . '.png');
}

function ship_type_icon($typeID, $size = 32) {
    if (!$typeID) return '';
    return '/img.php?url=' . urlencode(ZKILLBOARD_TYPES . '/' . $typeID . '/icon/' . $size);
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

function format_isk($val) {
    $val = (float)$val;
    if ($val >= 1e12) return number_format($val / 1e12, 2) . 'T ISK';
    if ($val >= 1e9) return number_format($val / 1e9, 2) . 'B ISK';
    if ($val >= 1e6) return number_format($val / 1e6, 2) . 'M ISK';
    if ($val >= 1e3) return number_format($val / 1e3, 2) . 'K ISK';
    return number_format($val, 2) . ' ISK';
}

function format_isk_full($val) {
    return number_format((float)$val, 2) . ' ISK';
}

function get_slot_name($flag) {
    $flag = (int)$flag;
    if ($flag >= 27 && $flag <= 34) return 'High';
    if ($flag >= 19 && $flag <= 26) return 'Mid';
    if ($flag >= 11 && $flag <= 18) return 'Low';
    if ($flag >= 92 && $flag <= 94) return 'Rig';
    if ($flag >= 133 && $flag <= 135) return 'Subsystem';
    if ($flag === 87) return 'Cargo';
    if ($flag === 89) return 'Drone Bay';
    if ($flag === 5) return 'Ship';
    if ($flag === 8) return 'Cargo';
    if ($flag === 155) return 'Rig';
    return 'Other';
}

function slot_sort_order($name) {
    $order = ['High' => 0, 'Mid' => 1, 'Low' => 2, 'Rig' => 3, 'Subsystem' => 4, 'Ship' => 5, 'Cargo' => 6, 'Drone Bay' => 7, 'Other' => 8];
    return $order[$name] ?? 9;
}
