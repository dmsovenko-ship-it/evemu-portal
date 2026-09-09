<?php
// tools/push_worker.php — background Web Push delivery for portal events.
//
// Every subscription in PUSH_DATA_DIR/push_subs.json belongs to an accountID.
// For each one we poll the emulator's /char/MailStatus.xml.aspx; when unread
// mail or unprocessed notifications increased since the last run we send a
// payloadless push to that subscription (sw.js then fetches /mail/poll and
// shows the notification). Requires PUSH_ENABLED + VAPID keys in config.php
// and a secure-context (HTTPS) portal origin.
//
// Cron every minute on the portal host:
//   * * * * *  cd /var/www/html && php tools/push_worker.php >> /tmp/push_worker.log 2>&1
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_once __DIR__ . '/../config.php';

function b64url_encode(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64url_decode(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }

if (!PUSH_ENABLED) {
    echo date('c') . " PUSH_ENABLED is off\n";
    exit(0);
}
if (!extension_loaded('openssl') || VAPID_PRIVATE_KEY === '' || VAPID_PUBLIC_KEY === '') {
    echo date('c') . " openssl / VAPID keys missing\n";
    exit(0);
}

$subs = push_subs();
if (!$subs) {
    echo date('c') . " no subscriptions\n";
    exit(0);
}

function vapid_auth_header(string $endpoint): string {
    $aud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $h = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $p = b64url_encode(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => VAPID_SUBJECT,
    ]));
    $der = b64url_decode(VAPID_PRIVATE_KEY);
    $pem = "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PRIVATE KEY-----\n";
    $key = openssl_pkey_get_private($pem);
    $sig = '';
    openssl_sign($h . '.' . $p, $sig, $key, 'sha256');
    $k = VAPID_PUBLIC_KEY;
    return 'Authorization: vapid t=' . $h . '.' . $p . ', k=' . $k;
}

function webpush_send(array $sub): bool {
    $endpoint = $sub['endpoint'] ?? '';
    if ($endpoint === '') return false;
    $opts = ['http' => [
        'method'  => 'POST',
        'header'  => implode("\r\n", [
            vapid_auth_header($endpoint),
            'Content-Type: application/octet-stream',
            'TTL: 120',
            'Content-Length: 0',
            'Connection: close',
        ]) . "\r\n",
        'content' => '',
        'timeout' => 10,
        'ignore_errors' => true,
    ]];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($endpoint, false, $ctx);
    // 201 created / 202 accepted = ok; 404/410 = subscription gone (prune)
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    if ($code >= 400) {
        echo date('c') . " push $code for $endpoint\n";
        return false;
    }
    return true;
}

$changed = false;
foreach ($subs as $i => $sub) {
    $aid = (int)($sub['accountid'] ?? 0);
    if ($aid <= 0) continue;

    $xml = @simplexml_load_string(@file_get_contents(API_BASE . '/char/MailStatus.xml.aspx?accountid=' . $aid, false,
        stream_context_create(['http' => ['timeout' => 10]])));
    if ($xml === null || !isset($xml->result)) {
        echo date('c') . " api unavailable for account $aid\n";
        continue;
    }
    $unread = (int)($xml->result->unread ?? 0);
    $notif  = (int)($xml->result->notifications ?? 0);
    $last   = $sub['last'] ?? null;
    $lastMail  = is_array($last) ? (int)($last['mail'] ?? 0) : -1;
    $lastNotif = is_array($last) ? (int)($last['notif'] ?? 0) : -1;

    if ($unread > $lastMail || $notif > $lastNotif) {
        $ok = webpush_send($sub);
        echo date('c') . " push to account $aid (mail $unread>$lastMail, notif $notif>$lastNotif) " . ($ok ? 'sent' : 'failed') . "\n";
    }
    $subs[$i]['last'] = ['mail' => $unread, 'notif' => $notif, 'ts' => time()];
    $changed = true;
}
if ($changed) push_save_subs($subs);
echo date('c') . " done\n";
