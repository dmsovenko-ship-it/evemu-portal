<?php
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
    if (!is_dir($cacheDir)) {
        // cache dir not writable, serve without caching
        $cacheDir = sys_get_temp_dir() . '/evemu_img_cache';
        @mkdir($cacheDir, 0777, true);
    }
}

$url = $_GET['url'] ?? '';
// Two hosts only: the public image CDNs AND our own LAN image server
// (http://172.20.1.47:26001 - the portal's char_portrait()/corp_logo() route
// through this cache proxy because a browser on the internet cannot reach a
// 172.x address directly).
if (empty($url) || !preg_match('#^(https://(images\.eveonline\.com|images\.zkillboard\.com|images\.evetech\.net)|http://172\.20\.1\.47:26001)/#', $url)) {
    http_response_code(400);
    exit;
}

// 1x1 transparent GIF - what we hand back for an image we could not get.
function evemu_blank_image()
{
    return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

$key = md5($url) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION ?: 'png');
$cacheFile = $cacheDir . '/' . $key;
$failFile  = $cacheFile . '.fail';

// NEGATIVE CACHE. A URL that could not be fetched used to be retried on EVERY page view:
// no marker was written, so the miss was paid over and over, and a CDN that hangs costs
// the timeout twice (curl + file_get_contents) while php-fpm runs a handful of workers.
// One poisoned icon was enough to stall every other request on the page. Remember the
// failure for a day and answer it instantly with a placeholder.
if (file_exists($failFile) && (time() - filemtime($failFile)) < 86400) {
    header('Content-Type: image/gif');
    header('Cache-Control: public, max-age=600');
    header('X-Image-Cache: NEGATIVE');
    echo evemu_blank_image();
    exit;
}

// serve cached file
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400 * 30) {
    $mime = @mime_content_type($cacheFile) ?: 'image/png';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=2592000');
    header('X-Image-Cache: HIT');
    readfile($cacheFile);
    exit;
}

// fetch and cache
$data = false;
$transportError = true;   // true = worth retrying over another transport

// try curl (4s connect / 4s total: these are icons, there is no reason to hold a php-fpm
// worker for ten seconds)
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code != 200 || strlen($data) < 100) {
        // A definitive HTTP answer (403 from the zkillboard CDN, 404 for an unknown type)
        // will not change over file_get_contents - retrying it just doubles the wait.
        $transportError = ($code == 0);
        $data = false;
    }
}

// try file_get_contents only when the failure was at the transport level
if ($data === false && $transportError && @ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => ['timeout' => 4, 'ignore_errors' => true, 'user_agent' => 'Mozilla/5.0'],
        'ssl' => ['verify_peer' => false]
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) < 100) $data = false;
}

if ($data !== false && strlen($data) > 100) {
    @file_put_contents($cacheFile, $data);
    @unlink($failFile);   // it works now - drop any stale negative marker
    $mime = @mime_content_type($cacheFile) ?: 'image/png';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=2592000');
    header('X-Image-Cache: MISS');
    readfile($cacheFile);
    exit;
}

// Remember the failure (negative cache) and answer with the placeholder instead of a
// 404, so the page shows a blank slot rather than a broken-image icon.
@file_put_contents($failFile, (string)time());

// debug: show error
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "URL: $url\n";
    echo "CURL: " . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
    echo "allow_url_fopen: " . (@ini_get('allow_url_fopen') ?: 'disabled') . "\n";
    echo "Cache dir: $cacheDir\n";
    echo "Cache file: $cacheFile\n";
    echo "Exists: " . (file_exists($cacheFile) ? 'yes' : 'no') . "\n";
    echo "Negative marker: " . (file_exists($failFile) ? 'yes' : 'no') . "\n";
    exit;
}

header('Content-Type: image/gif');
header('Cache-Control: public, max-age=600');
header('X-Image-Cache: NEGATIVE');
echo evemu_blank_image();