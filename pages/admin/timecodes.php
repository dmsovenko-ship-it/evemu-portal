<?php
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'grant') {
        $aid = intval($_POST['accountid'] ?? 0);
        $days = intval($_POST['days'] ?? 0);
        if ($aid > 0 && $days > 0) {
            $xml = api_post('/admin/GrantTimecode.xml.aspx', "accountid=$aid&days=$days");
            $msg = $xml && $xml->result ? "Выдано $days дней аккаунту #$aid" : 'Ошибка';
        }
    }
}

$xml = api_get('/admin/TimecodeList.xml.aspx');
$codes = [];
if ($xml && $xml->result && $xml->result->timecodes)
    foreach ($xml->result->timecodes->row as $r) $codes[] = $r;
?>

<h2 style="margin-bottom:16px">Таймкоды</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>

<div class="form-card" style="max-width:400px;margin-bottom:24px">
    <h3 style="margin-bottom:12px;color:var(--text-bright);font-size:14px">Выдать таймкод</h3>
    <form method="POST">
        <input type="hidden" name="action" value="grant">
        <div class="form-group">
            <label>Account ID</label>
            <input name="accountid" type="number" required>
        </div>
        <div class="form-group">
            <label>Дней</label>
            <input name="days" type="number" required min="1" max="3650" value="30">
        </div>
        <button type="submit" class="btn btn-primary">Выдать</button>
    </form>
</div>

<h3 style="margin-bottom:8px">История</h3>
<table class="data-table">
    <thead><tr><th>#</th><th>Аккаунт</th><th>Дней</th><th>Дата</th></tr></thead>
    <tbody>
    <?php foreach ($codes as $c): ?>
    <tr>
        <td><?= $c['id'] ?></td>
        <td><?= $c['accountid'] ?></td>
        <td><?= $c['days'] ?></td>
        <td style="color:var(--text-dim)"><?= e($c['grantdate']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($codes)): ?><tr><td colspan="4" class="empty">Нет таймкодов</td></tr><?php endif; ?>
    </tbody>
</table>
