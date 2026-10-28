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

$out = '';
$n = count($rows);
if ($n > 0) {
    // The billboard marquee is a very wide strip. The client concatenates the
    // captions ("a - b - c ...") until ~2048px (about 250 characters). With only
    // a handful of short news items the texture comes out narrow, so the screen
    // stretches it and the text looks giant / gets cut off. Repeat the headlines
    // (a ticker loop) until the line is well past that budget.
    $i = 0;
    while (strlen($out) < 450 && $i < 80) {
        $row  = $rows[$i % $n];
        $ts   = strtotime($row['date']);
        $d    = ($ts !== false && $ts > 0) ? date('d.m.Y', $ts) : date('d.m.Y');
        $out .= $d . ';' . $row['title'] . "\r\n";
        ++$i;
    }
}
echo $out;
