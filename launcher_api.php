<?php
/**
 * /launcher/api - JSON auth endpoints for the EVEmu Launcher.
 *
 * The launcher authenticates HERE (with the same credentials as the portal,
 * including the e-mail 2FA) so that the /autologin credentials it stores are
 * always verified. Session-based: the launcher keeps the PHPSESSID cookie.
 *
 *   POST action=login   name, password      -> {status: ok|code|error, name, chars, email}
 *   POST action=code    code                -> {status: ok|error, name, chars}
 *   POST action=resend                      -> {status: code|error, email}
 *   POST action=logout                      -> {status: ok}
 *   GET  action=whoami                      -> {status: ok|guest, name, chars}
 *   GET  action=clientinfo                  -> {status: ok, urls...} (static links)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/const.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function lj($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

function launcher_char_count(int $aid): int {
    $xml = api_get('/char/CharacterList.xml.aspx?accountid=' . $aid);
    if ($xml && isset($xml->result) && isset($xml->result->characters) && isset($xml->result->characters->row))
        return count($xml->result->characters->row);
    return 0;
}

function launcher_finalize(int $aid, string $name, int $role): void {
    $_SESSION['accountID']   = $aid;
    $_SESSION['accountName'] = $name;
    $_SESSION['role']        = $role;
    $_SESSION['login_time']  = time();
    unset($_SESSION['2fa_pending']);
    tfa_trust_device($aid, client_ip());
}

$action  = $_GET['action'] ?? '';
$pending = $_SESSION['2fa_pending'] ?? null;

if ($action === 'whoami') {
    $aid = (int)($_SESSION['accountID'] ?? 0);
    if (!$aid) lj(['status' => 'guest']);
    lj(['status' => 'ok', 'name' => (string)($_SESSION['accountName'] ?? ''), 'chars' => launcher_char_count($aid)]);
}

if ($action === 'clientinfo') {
    lj(['status' => 'ok',
        'base' => 'https://files.evesharp.dev/by%20build%20version/360229%20-%20EVE-EVE-TRANQUILITY%20-%20Crucible/',
        'installer' => 'EVE_Online_Installer_360229.exe',
        'parts' => ['eveonline_360229_1of2.7z', 'eveonline_360229_2of2.7z']]);
}

if ($action === 'logout') {
    unset($_SESSION['accountID'], $_SESSION['accountName'], $_SESSION['role'], $_SESSION['login_time'], $_SESSION['2fa_pending']);
    lj(['status' => 'ok']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') lj(['status' => 'error', 'error' => 'POST required']);

if ($action === 'login') {
    $name = trim((string)($_POST['name'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if ($name === '' || $pass === '') lj(['status' => 'error', 'error' => 'Enter login and password']);

    $xml = api_post('/auth/Login.xml.aspx', 'name=' . urlencode($name) . '&password=' . urlencode($pass));
    if (!($xml && isset($xml->result))) {
        $err = ($xml && isset($xml->error)) ? (string)$xml->error : 'Server unreachable';
        lj(['status' => 'error', 'error' => $err]);
    }
    $aid   = (int)$xml->result->accountid;
    $rname = (string)$xml->result->accountname;
    $role  = (int)$xml->result->role;
    $email = trim((string)($xml->result->email ?? ''));

    if ($email === '') {
        // legacy account without e-mail: 2FA impossible, log in directly
        launcher_finalize($aid, $rname, $role);
        lj(['status' => 'ok', 'name' => $rname, 'chars' => launcher_char_count($aid), 'noemail' => 1]);
    }

    if (!tfa_requires_code($aid, $role, client_ip())) {
        launcher_finalize($aid, $rname, $role);
        lj(['status' => 'ok', 'name' => $rname, 'chars' => launcher_char_count($aid)]);
    }

    $code = tfa_new_code();
    $body = "Your EVEmu login confirmation code:\n\n" . $code . "\n\nAccount: " . $rname
          . "\nValid " . (int)(TFA_CODE_TTL / 60) . " minutes.\n";
    list($okMail, $mailErr) = portal_mail_send($email, 'EVEmu login confirmation', $body);
    if (!$okMail) lj(['status' => 'error', 'error' => 'Cannot send e-mail: ' . $mailErr]);

    $_SESSION['2fa_pending'] = [
        'stage' => 'code', 'aid' => $aid, 'name' => $rname, 'role' => $role,
        'email' => $email, 'hash' => hash('sha256', $code),
        'exp' => time() + TFA_CODE_TTL, 'tries' => 0, 'ip' => client_ip(),
    ];
    $masked = preg_replace('/^(.{2}).*(@.*)$/u', '$1***$2', $email);
    lj(['status' => 'code', 'email' => $masked]);
}

if ($action === 'code') {
    if (!$pending || ($pending['stage'] ?? '') !== 'code') lj(['status' => 'error', 'error' => 'No pending confirmation - log in again']);
    if (client_ip() !== ($pending['ip'] ?? '')) { unset($_SESSION['2fa_pending']); lj(['status' => 'error', 'error' => 'IP changed - log in again']); }
    if (time() > (int)($pending['exp'] ?? 0)) { unset($_SESSION['2fa_pending']); lj(['status' => 'error', 'error' => 'Code expired - log in again']); }

    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    if ($code !== '' && hash_equals((string)($pending['hash'] ?? ''), hash('sha256', $code))) {
        launcher_finalize((int)$pending['aid'], (string)$pending['name'], (int)$pending['role']);
        lj(['status' => 'ok', 'name' => (string)$pending['name'], 'chars' => launcher_char_count((int)$pending['aid'])]);
    }
    $tries = (int)($pending['tries'] ?? 0) + 1;
    $pending['tries'] = $tries;
    if ($tries >= TFA_MAX_ATTEMPTS) { unset($_SESSION['2fa_pending']); lj(['status' => 'error', 'error' => 'Too many attempts - log in again']); }
    $_SESSION['2fa_pending'] = $pending;
    lj(['status' => 'error', 'error' => 'Wrong code (' . (TFA_MAX_ATTEMPTS - $tries) . ' left)', 'code' => 1]);
}

if ($action === 'resend') {
    if (!$pending || ($pending['stage'] ?? '') !== 'code' || ($pending['email'] ?? '') === '') lj(['status' => 'error', 'error' => 'Nothing to resend']);
    $code = tfa_new_code();
    $body = "Your EVEmu login confirmation code:\n\n" . $code . "\n\nAccount: " . (string)$pending['name']
          . "\nValid " . (int)(TFA_CODE_TTL / 60) . " minutes.\n";
    list($okMail, $mailErr) = portal_mail_send((string)$pending['email'], 'EVEmu login confirmation', $body);
    if (!$okMail) lj(['status' => 'error', 'error' => $err = 'Cannot send e-mail: ' . $mailErr]);
    $pending['hash'] = hash('sha256', $code);
    $pending['exp']  = time() + TFA_CODE_TTL;
    $pending['tries'] = 0;
    $_SESSION['2fa_pending'] = $pending;
    $masked = preg_replace('/^(.{2}).*(@.*)$/u', '$1***$2', (string)$pending['email']);
    lj(['status' => 'code', 'email' => $masked, 'resent' => 1]);
}

lj(['status' => 'error', 'error' => 'Unknown action']);
