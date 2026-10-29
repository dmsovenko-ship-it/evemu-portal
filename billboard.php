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
    // Deterministic rotation instead of a random pick: consecutive fetches (the
    // client refreshes every ~90s via the server-side billboard re-emit) must
    // land on a DIFFERENT image, otherwise a 4-image pool repeats 25% of the time
    // and the billboard looks like it never changes.
    sort($files);
    $cf = $root . '/cache/billboard_rot';
    $n = @file_get_contents($cf);
    $n = ($n === false) ? random_int(0, 1000000) : ((int)$n + 1);
    @file_put_contents($cf, (string)$n, LOCK_EX);
    $pick = $files[$n % count($files)];
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
