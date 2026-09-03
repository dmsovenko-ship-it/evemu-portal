<?php
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    $aid = intval($_POST['accountid'] ?? 0);

    if ($act === 'ban' && $aid) {
        $xml = api_post('/admin/BanAccount.xml.aspx', "accountid=$aid");
        $msg = $xml && $xml->result ? 'OK' : (($xml && $xml->error) ? (string)$xml->error : 'Ошибка');
    }
    if ($act === 'unban' && $aid) {
        $xml = api_post('/admin/UnbanAccount.xml.aspx', "accountid=$aid");
        $msg = $xml && $xml->result ? 'OK' : (($xml && $xml->error) ? (string)$xml->error : 'Ошибка');
    }
}

$xml = api_get('/admin/AccountList.xml.aspx');
$accounts = [];
if ($xml && $xml->result && $xml->result->accounts)
    foreach ($xml->result->accounts->row as $r) $accounts[] = $r;
?>

<h2 style="margin-bottom:16px">Аккаунты</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="form-error"><?= e($err) ?></div><?php endif; ?>

<table class="data-table">
    <thead><tr><th>ID</th><th>Имя</th><th>Email</th><th>Роль</th><th>Онлайн</th><th>Бан</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a):
        $role = (int)$a['role'];
        $banned = (int)$a['banned'];
    ?>
    <tr>
        <td><?= $a['accountid'] ?></td>
        <td style="font-weight:600"><?= e($a['accountname']) ?></td>
        <td style="color:var(--text-dim)"><?= e($a['email']) ?></td>
        <td>
            <?php if ($role & ROLE_ADMIN): ?><span class="badge badge-admin">Admin</span>
            <?php elseif ($role & (ROLE_GMH|ROLE_GML)): ?><span class="badge badge-gm">GM</span>
            <?php else: ?><span class="badge badge-player">Player</span><?php endif; ?>
        </td>
        <td><?= $a['online'] ? '🟢' : '⚫' ?></td>
        <td><?= $banned ? '<span class="badge badge-banned">BANNED</span>' : '—' ?></td>
        <td>
            <?php if ($banned): ?>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="unban"><input type="hidden" name="accountid" value="<?= $a['accountid'] ?>"><button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">Разбанить</button></form>
            <?php else: ?>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="ban"><input type="hidden" name="accountid" value="<?= $a['accountid'] ?>"><button class="btn btn-danger" style="width:auto;padding:4px 10px;font-size:11px">Забанить</button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($accounts)): ?><tr><td colspan="7" class="empty">Нет аккаунтов</td></tr><?php endif; ?>
    </tbody>
</table>
