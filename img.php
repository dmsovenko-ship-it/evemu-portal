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
    $data = false;
    // try curl first
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code != 200 || strlen($data) < 100) $data = false;
    }
    // fallback to file_get_contents
    if ($data === false && function_exists('stream_context_create')) {
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true], 'ssl' => ['verify_peer' => false]]);
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false && strlen($data) < 100) $data = false;
    }
    if ($data !== false) {
        file_put_contents($cacheFile, $data);
    } else {
        http_response_code(404);
        exit;
    }
}

$mime = @mime_content_type($cacheFile);
header('Content-Type: ' . ($mime ?: 'image/png'));
header('Cache-Control: public, max-age=2592000');
readfile($cacheFile);
