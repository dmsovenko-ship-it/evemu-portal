<?php
$msg = '';
$err = '';

$accountID = isset($id) && is_numeric($id) ? intval($id) : (isset($_GET['accountid']) ? intval($_GET['accountid']) : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accountID) {
    $act = $_POST['action'] ?? '';
    if ($act === 'ban') {
        $xml = api_post('/admin/BanAccount.xml.aspx', "accountid=$accountID");
        $msg = $xml && $xml->result ? 'Аккаунт забанен' : 'Ошибка';
    }
    if ($act === 'unban') {
        $xml = api_post('/admin/UnbanAccount.xml.aspx', "accountid=$accountID");
        $msg = $xml && $xml->result ? 'Аккаунт разбанен' : 'Ошибка';
    }
    if ($act === 'back') { redirect('/admin/accounts'); return; }
}

$acc = null; $chars = [];
if ($accountID) {
    $xml = api_get('/admin/AccountInfo.xml.aspx?accountid=' . $accountID);
    if ($xml && $xml->result && $xml->result->account) {
        $acc = $xml->result->account;
        if ($xml->result->characters && isset($xml->result->characters->row))
            foreach ($xml->result->characters->row as $c) $chars[] = $c;
    } else {
        $err = 'Аккаунт не найден';
    }
}
?>

<h2 style="margin-bottom:16px">
    <?= $acc ? 'Аккаунт: ' . e($acc['accountname']) : 'Аккаунт' ?>
    <?php if ($acc): $role=(int)$acc['role']; ?>
        <?php if ($role & ROLE_ADMIN): ?><span class="badge badge-admin">Admin</span>
        <?php elseif ($role & (ROLE_GMH|ROLE_GML)): ?><span class="badge badge-gm">GM</span>
        <?php else: ?><span class="badge badge-player">Player</span><?php endif; ?>
        <?= ((int)$acc['online']) ? '<span class="badge badge-open">Online</span>' : '<span class="badge badge-closed">Offline</span>' ?>
        <?= ((int)$acc['banned']) ? '<span class="badge badge-banned">BANNED</span>' : '' ?>
    <?php endif; ?>
</h2>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="form-error"><?= e($err) ?></div><?php endif; ?>

<?php if ($acc): ?>
<div class="form-card" style="margin-bottom:16px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;font-size:13px">
        <div><span style="color:var(--text-dim)">ID</span><div style="font-weight:600">#<?= (int)$acc['accountid'] ?></div></div>
        <div><span style="color:var(--text-dim)">Email</span><div style="font-weight:600"><?= e($acc['email']) ?></div></div>
        <div><span style="color:var(--text-dim)">Входов</span><div style="font-weight:600"><?= (int)$acc['logoncount'] ?></div></div>
        <div><span style="color:var(--text-dim)">Последний вход</span><div style="font-weight:600"><?= e($acc['lastlogin']) ?: '—' ?></div></div>
    </div>
    <div style="margin-top:14px">
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="back"><button class="btn btn-outline" style="width:auto">← К списку аккаунтов</button>
        </form>
        <?php if ((int)$acc['banned']): ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="unban"><button class="btn btn-outline" style="width:auto">Разбанить</button></form>
        <?php else: ?>
            <form method="POST" style="display:inline"><input type="hidden" name="action" value="ban"><button class="btn btn-danger" style="width:auto">Забанить</button></form>
        <?php endif; ?>
    </div>
</div>

<h3 style="margin-bottom:10px;font-size:14px">Персонажи аккаунта (<?= count($chars) ?>)</h3>
<table class="data-table">
    <thead><tr><th></th><th>Персонаж</th><th>Корпорация</th><th>Баланс</th><th>SP</th><th>Sec</th><th>Корабль</th><th>Онлайн</th></tr></thead>
    <tbody>
    <?php foreach ($chars as $c): ?>
        <tr>
            <td><img src="<?= char_portrait((int)$c['characterid'], 32) ?>" alt="" width="32" height="32" style="border-radius:4px;object-fit:cover"></td>
            <td><a href="/character/<?= (int)$c['characterid'] ?>" style="color:var(--accent2);font-weight:600"><?= e($c['charactername']) ?></a></td>
            <td><a href="/corporation/<?= (int)$c['corporationid'] ?>" style="color:var(--text-dim)"><?= e($c['corporationname']) ?: '—' ?></a></td>
            <td style="white-space:nowrap"><?= number_format((float)$c['balance'], 0, '.', ' ') ?> ISK</td>
            <td style="white-space:nowrap"><?= number_format((int)$c['skillpoints'], 0, '.', ' ') ?></td>
            <td><?php $sv=(float)$c['securityrating']; ?><span style="color:#000;background:<?= security_color($sv) ?>;border-radius:4px;padding:1px 6px;font-weight:600;display:inline-block"><?= number_format($sv,2,'.','') ?></span></td>
            <td>
                <?php if ((int)$c['shiptypeid']): ?>
                    <a href="/ship/<?= (int)$c['shiptypeid'] ?>" title="Type <?= (int)$c['shiptypeid'] ?>"><img src="<?= ship_icon((int)$c['shiptypeid'], 24) ?>" alt="" width="24" height="24" style="vertical-align:middle"></a>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= ((int)$c['online']) ? '🟢' : '⚫' ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($chars)): ?><tr><td colspan="8" class="empty">Нет персонажей</td></tr><?php endif; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:var(--text-dim)">Аккаунт не найден или недоступен.</p>
<?php endif; ?>
