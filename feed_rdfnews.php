<?php
/**
 * /feed/rdfnews.asp - RDF news feed for the EVE client's station holoscreen
 * news ticker (holoscreenSvc.RSS_FEEDS -> corebrowserutil.GetStringFromURL).
 *
 * The client machine resolves www.eveonline.com to this host via the hosts file.
 * The client parses the response LINE BY LINE with crude lstrip/rstrip, so the
 * emitted format is exact:
 *   - "<item rdf:about=...>" / "<title>...</title>" / "<dc:date>...</dc:date>"
 *     MUST start at the beginning of a line (the parser strips, not parses).
 *   - dc:date needs TWO colons (client splits hour/min/sec) and a '+' tail:
 *     YYYY-MM-DDTHH:MM:SS+0000
 *   - titles are padded with spaces: the client's lstrip('</title>') set is
 *     {<,t,i,l,e,>} and would eat leading/trailing letters of real titles.
 *     Spaces are NOT in that set, so they survive both strips.
 */
require_once __DIR__ . '/config.php';   // loads const.php + API_BASE

header('Content-Type: text/xml; charset=utf-8');

function rdf_escape($s) {
    $s = preg_replace('/[\r\n\t]+/', ' ', (string)$s);
    $s = strip_tags($s);
    return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8', false);
}

function rdf_date($s) {
    $ts = strtotime((string)$s);
    if ($ts === false || $ts <= 0) $ts = time();
    // The Crucible client parses dc:date by splitting:
    //   split('-')=3, split('T')=2, split(':')=3, split('+')=2 on the *minutes*
    // part -> it requires the timezone BETWEEN minutes and seconds:
    //   YYYY-MM-DDTHH:MM+ZZZZ:SS
    return gmdate('Y-m-d\TH:i', $ts) . '+0000:' . gmdate('s', $ts);
}

echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
echo "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\""
   . " xmlns:dc=\"http://purl.org/dc/elements/1.1/\""
   . " xmlns=\"http://purl.org/rss/1.0/\">\n";

$rows = array();
$nxml = api_get('/server/NewsFeed.xml.aspx?limit=10', 8);
if ($nxml && isset($nxml->result) && isset($nxml->result->rows)) {
    foreach ($nxml->result->rows->row as $r) {
        $rows[] = array(
            'id'    => (int)$r['id'],
            'title' => (string)$r['title'],
            'date'  => (string)$r['date'],
        );
    }
}

// The client requests BOTH tid=7 and tid=4. Our news store is unified, so
// serve the items only on the primary feed; otherwise every headline is
// rendered twice in the CQ news ticker.
$tid = isset($_GET['tid']) ? trim((string)$_GET['tid']) : '';
if ($tid !== '' && $tid !== '7')
    $rows = array();

foreach ($rows as $row) {
    $link = 'http://www.eveonline.com/news/' . (int)$row['id'];
    // client-safe: no emoji / non-BMP glyphs, clamped length
    $title = ' ' . rdf_escape(news_client_text($row['title'], 120)) . ' ';
    echo '<item rdf:about="' . htmlspecialchars($link, ENT_XML1) . "\">\n";
    echo '<title>' . $title . "</title>\n";
    echo '<dc:date>' . rdf_date($row['date']) . "</dc:date>\n";
    echo "</item>\n";
}

echo "</rdf:RDF>\n";
