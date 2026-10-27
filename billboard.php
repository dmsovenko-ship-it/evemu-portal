<?php
/**
 * /billboard.asp - station billboard ad texture.
 *
 * Rotates through the images uploaded via /admin/news (media/billboard/), so
 * every client session gets a different ad - the way the original EVE billboard
 * rotated its ads. Falls back to media/billboard.png when the pool is empty.
 * Served with no-store so nothing caches a stale ad.
 */
$root = __DIR__;
$dir  = $root . '/media/billboard';

$types = [
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
];

$files = [];
if (is_dir($dir) && ($dh = @opendir($dir)) !== false) {
    while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (isset($types[$ext]) && is_file($dir . '/' . $f)) $files[] = $f;
    }
    closedir($dh);
}

$path = null;
$ext  = 'png';
if ($files) {
    $pick = $files[random_int(0, count($files) - 1)];
    $path = $dir . '/' . $pick;
    $ext  = strtolower(pathinfo($pick, PATHINFO_EXTENSION));
} else {
    $path = $root . '/media/billboard.png';
    $ext  = 'png';
}

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Length: ' . filesize($path));
readfile($path);
