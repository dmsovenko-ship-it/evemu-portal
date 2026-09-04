<?php
require_once __DIR__ . '/../../layout.php';
if (!is_logged_in() || !(current_user()['role'] & (ROLE_ADMIN|ROLE_GMH|ROLE_GML))) {
    redirect('/login'); return;
}

$sub = $sub ?? 'dashboard';

ob_start();
?>
<div class="admin-layout">
    <div class="admin-sidebar">
        <a href="/admin" class="<?= $sub==='dashboard'?'active':'' ?>">Dashboard</a>
        <a href="/admin/accounts" class="<?= $sub==='accounts'?'active':'' ?>">Accounts</a>
        <a href="/admin/petitions" class="<?= $sub==='petitions'?'active':'' ?>">Petitions</a>
        <?php if (current_user()['role'] & ROLE_ADMIN): ?>
            <a href="/admin/timecodes" class="<?= $sub==='timecodes'?'active':'' ?>">Timecodes</a>
            <a href="/admin/items" class="<?= $sub==='items'?'active':'' ?>">Items</a>
            <a href="/admin/roles" class="<?= $sub==='roles'?'active':'' ?>">Roles</a>
        <?php endif; ?>
    </div>
    <div class="admin-content">
    <?php
        $pageFile = __DIR__ . "/$sub.php";
        if (file_exists($pageFile)) require $pageFile;
        else echo '<p style="color:var(--text-dim)">Page not found</p>';
    ?>
    </div>
</div>
<?php
render_layout('Admin', 'admin', ob_get_clean());
