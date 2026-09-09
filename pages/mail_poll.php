<?php
// /mail/poll — JSON unread/unprocessed counts for the current account.
// Used by the /mail page poller, the site-wide navbar badge and sw.js.
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u) {
    echo json_encode(['ok' => false, 'error' => 'not logged in']);
    exit;
}

$aid = (int)$u['accountID'];
$xml = api_get('/char/MailStatus.xml.aspx?accountid=' . $aid, 6);
if ($xml === null || !isset($xml->result)) {
    echo json_encode(['ok' => false, 'error' => 'api unavailable']);
    exit;
}

$unread   = (int)($xml->result->unread ?? 0);
$notif    = (int)($xml->result->notifications ?? 0);
$lastMail = (int)($xml->result->lastmessageid ?? 0);
$lastNotif = (int)($xml->result->lastnotificationid ?? 0);

$out = [
    'ok'        => true,
    'unread'    => $unread,
    'notifications' => $notif,
    'lastmessageid'  => $lastMail,
    'lastnotificationid' => $lastNotif,
];

// optional: newest unread mail (sender/title) for a rich toast
if (!empty($_GET['full']) && $unread > 0) {
    $list = api_get('/char/MailList.xml.aspx?accountid=' . $aid . '&folder=inbox&limit=1', 6);
    if ($list !== null && isset($list->result->mail->row)) {
        $r = $list->result->mail->row[0];
        $out['top'] = [
            'messageid' => (int)($r['messageid'] ?? 0),
            'senderid'  => (int)($r['senderid'] ?? 0),
            'sendername'=> (string)($r['sendername'] ?? ''),
            'title'     => (string)($r['title'] ?? ''),
        ];
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
