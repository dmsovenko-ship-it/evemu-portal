<?php
// /mail/push — web-push subscription management (JSON).
//   GET  -> { ok, pushEnabled, subscribed:bool, subs:[...] } for this session
//   POST action=subscribe   + { endpoint, keys:{p256dh,auth} }
//   POST action=unsubscribe + { endpoint }
// Subscriptions are stored keyed by endpoint in PUSH_DATA_DIR/push_subs.json,
// each with the accountID that created it so push_worker can scope the poll.
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u) {
    echo json_encode(['ok' => false, 'error' => 'not logged in']);
    exit;
}

function respond(array $a): void { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

if (!PUSH_ENABLED) {
    respond(['ok' => true, 'pushenabled' => false, 'subscribed' => false]);
}

$aid = (int)$u['accountID'];
$subs = push_subs();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mine = array_values(array_filter($subs, function ($s) use ($aid) {
        return (int)($s['accountid'] ?? 0) === $aid;
    }));
    respond(['ok' => true, 'pushenabled' => true, 'subscribed' => count($mine) > 0, 'subs' => $mine]);
}

$action = $_POST['action'] ?? '';
$endpoint = $_POST['endpoint'] ?? '';

if ($endpoint === '' || !preg_match('#^https://#', $endpoint)) {
    respond(['ok' => false, 'error' => 'bad endpoint']);
}

if ($action === 'unsubscribe') {
    $subs = array_values(array_filter($subs, function ($s) use ($endpoint) {
        return ($s['endpoint'] ?? '') !== $endpoint;
    }));
    push_save_subs($subs);
    respond(['ok' => true, 'subscribed' => false]);
}

if ($action === 'subscribe') {
    $p256dh = $_POST['p256dh'] ?? '';
    $auth   = $_POST['auth'] ?? '';
    if ($p256dh === '' || $auth === '') {
        respond(['ok' => false, 'error' => 'missing keys']);
    }
    $subs = array_values(array_filter($subs, function ($s) use ($endpoint) {
        return ($s['endpoint'] ?? '') !== $endpoint;
    }));
    $subs[] = [
        'endpoint'   => $endpoint,
        'p256dh'     => $p256dh,
        'auth'       => $auth,
        'accountid'  => $aid,
        'created'    => time(),
        'last'       => null, // {mail:int, notif:int, ts:int} set by push_worker
    ];
    push_save_subs($subs);
    respond(['ok' => true, 'subscribed' => true]);
}

respond(['ok' => false, 'error' => 'bad action']);
