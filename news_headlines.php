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
    // ';' is the field separator the client splits on, so it must not appear
    // inside a caption. Dashes are fine: the client joins captions with ' - '.
    $t = news_client_text((string)$r['title'], 40);
    $t = str_replace(';', ',', $t);
    $rows[] = array(
        'title' => $t,
        'date'  => (string)$r['date'],
    );
}
}

// The billboard text panel is a 1/5-wide window (model TextureScaling U=0.2)
// that scrolls its texture via TextScrollingCurve. A single short headline fits
// inside the window and looks static, so return several headlines - the client
// joins them with ' - ' and caps the result at ~2048 px (~256 chars), which is
// wide enough to actually scroll.
$rows = array_slice($rows, 0, 6);

$out = '';
foreach ($rows as $row) {
    $ts = strtotime($row['date']);
    $d = ($ts !== false && $ts > 0) ? date('d.m.Y', $ts) : date('d.m.Y');
    $out .= $d . ';' . $row['title'] . "\r\n";
}
echo $out;
