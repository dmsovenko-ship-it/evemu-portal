<?php
/**
 * /mb/news-headlines.asp - station billboard news headlines (billboardMgr.DoNewsHeadlines).
 * Client format: plain text, CRLF-separated lines of "DATE;CAPTION" - the caption
 * is rendered as the scrolling text on the station billboard screen.
 */
require_once __DIR__ . '/config.php';   // loads const.php + API_BASE

header('Content-Type: text/plain; charset=utf-8');

$rows = array();
$nxml = api_get('/server/NewsFeed.xml.aspx?limit=10', 8);
if ($nxml && isset($nxml->result) && isset($nxml->result->rows)) {
foreach ($nxml->result->rows->row as $r) {
    // The billboard uses " - " as the caption separator, so a dash inside a
    // title reads as an extra separator - replace it with a comma / space.
    $t = news_client_text((string)$r['title'], 40);
    $t = str_replace(' - ', ', ', $t);
    $t = str_replace('-', ' ', $t);
    $rows[] = array(
        'title' => $t,
        'date'  => (string)$r['date'],
    );
}
}

// The billboard text panel is centred and static (the model does not scroll it);
// a longer line is cut off on both sides, so only the newest headline fits.
$rows = array_slice($rows, 0, 1);

$out = '';
foreach ($rows as $row) {
    $ts = strtotime($row['date']);
    $d = ($ts !== false && $ts > 0) ? date('d.m.Y', $ts) : date('d.m.Y');
    $out .= $d . ';' . $row['title'] . "\r\n";
}
echo $out;
