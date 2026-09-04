<?php
function render_layout($title, $active, $content) {
    $user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= SITE_NAME ?></title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<nav class="navbar">
    <div class="nav-inner">
        <a href="/" class="nav-brand"><?= SITE_NAME ?></a>
        <form action="/search" method="get" class="nav-search">
            <input type="text" name="q" placeholder="Search characters, corporations, systems..." class="nav-search-input" autocomplete="off">
            <button type="submit" class="nav-search-btn">Search</button>
        </form>
        <div class="nav-links">
            <a href="/kills" class="<?= $active==='kills'?'active':'' ?>">Killboard</a>
            <a href="/players" class="<?= $active==='players'?'active':'' ?>">Players</a>
            <a href="/systems" class="<?= $active==='systems'?'active':'' ?>">Systems</a>
            <a href="/market" class="<?= $active==='market'?'active':'' ?>">Market</a>
            <a href="/haul" class="<?= $active==='haul'?'active':'' ?>">Haul</a>
            <?php if ($user): ?>
                <a href="/characters" class="<?= $active==='chars'?'active':'' ?>">My Characters</a>
                <?php if ($user['role'] & (ROLE_ADMIN|ROLE_GMH|ROLE_GML)): ?>
                    <a href="/admin" class="<?= $active==='admin'?'active':'' ?>">Admin</a>
                <?php endif; ?>
                <span class="nav-user"><?= e($user['accountName']) ?></span>
                <a href="/logout" class="nav-btn-outline">Logout</a>
            <?php else: ?>
                <a href="/login" class="nav-btn">Login</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container">
<?= $content ?>
</main>
<footer class="footer">
    <?= SITE_NAME ?> Killboard &copy; <?= date('Y') ?> &mdash; Portal v<?= PORTAL_VERSION ?> &mdash; Powered by EVEmu
</footer>
</body>
</html>
<?php
}
