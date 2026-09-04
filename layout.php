<?php
function render_layout($title, $active, $content) {
    $user = current_user();
?>
<!DOCTYPE html>
<html lang="ru">
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
        <div class="nav-links">
            <a href="/kills" class="<?= $active==='kills'?'active':'' ?>">Killboard</a>
            <a href="/stats" class="<?= $active==='stats'?'active':'' ?>">Статистика</a>
            <a href="/players" class="<?= $active==='players'?'active':'' ?>">Игроки</a>
            <a href="/systems" class="<?= $active==='systems'?'active':'' ?>">Системы</a>
            <a href="/market" class="<?= $active==='market'?'active':'' ?>">Рынок</a>
            <?php if ($user): ?>
                <a href="/characters" class="<?= $active==='chars'?'active':'' ?>">Персонажи</a>
                <?php if ($user['role'] & (ROLE_ADMIN|ROLE_GMH|ROLE_GML)): ?>
                    <a href="/admin" class="<?= $active==='admin'?'active':'' ?>">Админка</a>
                <?php endif; ?>
                <span class="nav-user"><?= e($user['accountName']) ?></span>
                <a href="/logout" class="nav-btn-outline">Выход</a>
            <?php else: ?>
                <a href="/login" class="nav-btn">Вход</a>
                <a href="/register" class="nav-btn-outline">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="container">
<?= $content ?>
</main>
<footer class="footer">
    <p><?= SITE_NAME ?> &copy; <?= date('Y') ?></p>
</footer>
</body>
</html>
<?php
}
