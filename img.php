<?php
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);

$url = $_GET['url'] ?? '';
if (empty($url) || !preg_match('#^https://(images\.eveonline\.com|images\.zkillboard\.com|images\.evetech\.net)/#', $url)) {
    http_response_code(400);
    exit;
}

$key = md5($url) . '.' . pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION ?: 'png');
$cacheFile = $cacheDir . '/' . $key;

if (!file_exists($cacheFile) || (time() - filemtime($cacheFile)) > 86400 * 30) {
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) > 100) {
        file_put_contents($cacheFile, $data);
    } else {
        http_response_code(404);
        exit;
    }
}

header('Content-Type: ' . mime_content_type($cacheFile));
header('Cache-Control: public, max-age=2592000');
header('X-Image-Cache: HIT');
readfile($cacheFile);
