<?php
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

$url = $_GET['url'] ?? '';
if (empty($url) || !preg_match('#^https://(images\.eveonline\.com|images\.zkillboard\.com|images\.evetech\.net)/#', $url)) {
    http_response_code(400);
    exit;
}

$key = md5($url) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION ?: 'png');
$cacheFile = $cacheDir . '/' . $key;

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

// try curl
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($code != 200 || strlen($data) < 100) {
        $data = false;
    }
}

// try file_get_contents
if ($data === false && @ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
        'http' => ['timeout' => 10, 'ignore_errors' => true, 'user_agent' => 'Mozilla/5.0'],
        'ssl' => ['verify_peer' => false]
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) < 100) $data = false;
}

if ($data !== false && strlen($data) > 100) {
    @file_put_contents($cacheFile, $data);
    $mime = @mime_content_type($cacheFile) ?: 'image/png';
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=2592000');
    header('X-Image-Cache: MISS');
    readfile($cacheFile);
    exit;
}

// debug: show error
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain');
    echo "URL: $url\n";
    echo "CURL: " . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
    echo "allow_url_fopen: " . (@ini_get('allow_url_fopen') ?: 'disabled') . "\n";
    echo "Cache dir: $cacheDir\n";
    echo "Cache file: $cacheFile\n";
    echo "Exists: " . (file_exists($cacheFile) ? 'yes' : 'no') . "\n";
    exit;
}

http_response_code(404);
