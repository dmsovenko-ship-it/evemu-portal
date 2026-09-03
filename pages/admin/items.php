<?php
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'give') {
        $cid = intval($_POST['characterid'] ?? 0);
        $tid = intval($_POST['typeid'] ?? 0);
        $qty = max(1, intval($_POST['quantity'] ?? 1));
        if ($cid > 0 && $tid > 0) {
            $xml = api_post('/admin/GiveItem.xml.aspx', "characterid=$cid&typeid=$tid&quantity=$qty");
            $msg = $xml && $xml->result ? "Предмет #$tid x$qty выдан персонажу #$cid" : 'Ошибка';
        }
    }
}
?>

<h2 style="margin-bottom:16px">Выдача предметов</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>

<div class="form-card" style="max-width:400px">
    <h3 style="margin-bottom:12px;color:var(--text-bright);font-size:14px">Выдать предмет</h3>
    <form method="POST">
        <input type="hidden" name="action" value="give">
        <div class="form-group">
            <label>Character ID</label>
            <input name="characterid" type="number" required>
        </div>
        <div class="form-group">
            <label>Type ID предмета</label>
            <input name="typeid" type="number" required>
        </div>
        <div class="form-group">
            <label>Количество</label>
            <input name="quantity" type="number" required min="1" value="1">
        </div>
        <button type="submit" class="btn btn-primary">Выдать</button>
    </form>
</div>
