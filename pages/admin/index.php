<?php
require_once __DIR__ . '/../../layout.php';
if (!is_logged_in() || !(current_user()['role'] & (ROLE_ADMIN|ROLE_GMH|ROLE_GML))) {
    redirect('/login'); return;
}

$sub = $sub ?? 'dashboard';
$adminPages = [
    'dashboard'  => 'Обзор',
    'accounts'   => 'Аккаунты',
    'petitions'  => 'Петиции',
    'timecodes'  => 'Таймкоды',
    'items'      => 'Выдача предметов',
    'roles'      => 'Роли',
];

ob_start();
?>
<div class="admin-layout">
    <div class="admin-sidebar">
        <a href="/admin" class="<?= $sub==='dashboard'?'active':'' ?>">Обзор</a>
        <a href="/admin/accounts" class="<?= $sub==='accounts'?'active':'' ?>">Аккаунты</a>
        <a href="/admin/petitions" class="<?= $sub==='petitions'?'active':'' ?>">Петиции</a>
        <?php if (current_user()['role'] & ROLE_ADMIN): ?>
            <a href="/admin/timecodes" class="<?= $sub==='timecodes'?'active':'' ?>">Таймкоды</a>
            <a href="/admin/items" class="<?= $sub==='items'?'active':'' ?>">Предметы</a>
            <a href="/admin/roles" class="<?= $sub==='roles'?'active':'' ?>">Роли</a>
        <?php endif; ?>
    </div>
    <div class="admin-content">
    <?php
        $pageFile = __DIR__ . "/$sub.php";
        if (file_exists($pageFile)) require $pageFile;
        else echo '<p>Страница не найдена</p>';
    ?>
    </div>
</div>
<?php
render_layout('Админка', 'admin', ob_get_clean());
