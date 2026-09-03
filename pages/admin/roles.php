<?php
$msg = '';

$allRoles = [
    ROLE_PLAYER    => ['Player',    'badge-player'],
    ROLE_GML       => ['GML',       'badge-gm'],
    ROLE_GMH       => ['GMH',       'badge-gm'],
    ROLE_QA        => ['QA',        'badge-gm'],
    ROLE_ADMIN     => ['Admin',     'badge-admin'],
    ROLE_WORLDMOD  => ['WorldMod',  'badge-gm'],
    ROLE_CHTADMIN  => ['ChatAdmin', 'badge-gm'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'setrole') {
        $aid = intval($_POST['accountid'] ?? 0);
        $newRole = intval($_POST['role'] ?? 0);
        if ($aid > 0) {
            $xml = api_post('/admin/SetRole.xml.aspx', "accountid=$aid&role=$newRole");
            $msg = $xml && $xml->result ? "Роль обновлена для #$aid" : 'Ошибка';
        }
    }
}

$xml = api_get('/admin/AccountList.xml.aspx');
$accounts = [];
if ($xml && $xml->result && $xml->result->accounts)
    foreach ($xml->result->accounts->row as $r) $accounts[] = $r;
?>

<h2 style="margin-bottom:16px">Управление ролями</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>

<table class="data-table">
    <thead><tr><th>ID</th><th>Имя</th><th>Текущая роль</th><th>Изменить</th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $a):
        $role = (int)$a['role'];
    ?>
    <tr>
        <td><?= $a['accountid'] ?></td>
        <td style="font-weight:600"><?= e($a['accountname']) ?></td>
        <td>
            <?php foreach ($allRoles as $bit => [$name, $cls]):
                if ($role & $bit): ?><span class="badge <?= $cls ?>"><?= $name ?></span> <?php endif;
            endforeach; ?>
        </td>
        <td>
            <form method="POST" style="display:flex;gap:6px;align-items:center">
                <input type="hidden" name="action" value="setrole">
                <input type="hidden" name="accountid" value="<?= $a['accountid'] ?>">
                <select name="role" style="background:#0d1117;border:1px solid var(--border);color:var(--text);padding:4px 8px;border-radius:3px;font-size:12px">
                    <?php foreach ($allRoles as $bit => [$name]): ?>
                    <option value="<?= $bit ?>" <?= $role==$bit?'selected':'' ?>><?= $name ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">OK</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
