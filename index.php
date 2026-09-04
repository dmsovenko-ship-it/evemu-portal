<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
require_once __DIR__ . '/config.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';
$parts = array_values(array_filter(explode('/', $uri)));

$page = $parts[0] ?? 'home';
$sub  = $parts[1] ?? null;
$id   = $parts[2] ?? null;

if (is_numeric($page)) {
    $sub = 'kill';
    $id = $page;
    $page = 'kill';
}

switch ($page) {
    case 'kill':
        require __DIR__ . '/pages/kill.php';
        break;
    case 'kills':
        require __DIR__ . '/pages/kills.php';
        break;
    case 'search':
        require __DIR__ . '/pages/search.php';
        break;
    case 'character':
        require __DIR__ . '/pages/char_kills.php';
        break;
    case 'corporation':
        require __DIR__ . '/pages/corp_kills.php';
        break;
    case 'system':
        require __DIR__ . '/pages/system_kills.php';
        break;
    case 'stats':
        require __DIR__ . '/pages/stats.php';
        break;
    case 'players':
        require __DIR__ . '/pages/players.php';
        break;
    case 'systems':
        require __DIR__ . '/pages/systems.php';
        break;
    case 'market':
        require __DIR__ . '/pages/market.php';
        break;
    case 'register':
        require __DIR__ . '/pages/register.php';
        break;
    case 'login':
        require __DIR__ . '/pages/login.php';
        break;
    case 'logout':
        session_destroy();
        redirect('/');
        break;
    case 'characters':
        require __DIR__ . '/pages/characters.php';
        break;
    case 'admin':
        require __DIR__ . '/pages/admin/index.php';
        break;
    default:
        require __DIR__ . '/pages/home.php';
        break;
}
