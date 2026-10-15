<?php
/**
 * /mb/news-headlines.asp - station billboard news headlines (billboardMgr.DoNewsHeadlines).
 * Client format: plain text, CRLF-separated lines of "DATE;CAPTION" - the caption
 * is rendered as the scrolling text on the station billboard screen.
 */
require_once __DIR__ . '/const.php';

header('Content-Type: text/plain; charset=utf-8');

$rows = array();
$nxml = api_get('/server/NewsFeed.xml.aspx?limit=10', 8);
if ($nxml && isset($nxml->result) && isset($nxml->result->rows)) {
    foreach ($nxml->result->rows->row as $r) {
        $rows[] = array(
            'title' => preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)$r['title'])),
            'date'  => (string)$r['date'],
        );
    }
}

$out = '';
foreach ($rows as $row) {
    $ts = strtotime($row['date']);
    $d = ($ts !== false && $ts > 0) ? date('d.m.Y', $ts) : date('d.m.Y');
    $out .= $d . ';' . $row['title'] . "\r\n";
}
echo $out;
