<?php
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'injectplex') {
        if (!(current_user()['role'] & ROLE_ADMIN)) {
            $msg = 'Недостаточно прав: вброс PLEX на рынок — только главный админ.';
        } else {
            $st = intval($_POST['stationid'] ?? 0);
            $pr = (float)($_POST['price'] ?? 0);
            $qt = intval($_POST['quantity'] ?? 0);
            $x = api_post('/admin/InjectPlex.xml.aspx', 'stationid=' . $st . '&price=' . $pr . '&quantity=' . $qt);
            if ($x && isset($x->error)) $msg = 'Ошибка: ' . (string)$x->error;
            elseif ($x && isset($x->result->quantity)) $msg = 'На рынок выставлено PLEX: ' . (int)$x->result->quantity . ' шт. по ' . number_format((float)$x->result->price) . ' ISK (станция ' . $st . ').';
            else $msg = 'Не удалось.';
        }
    } elseif ($act === 'give') {
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

<div class="form-card" style="max-width:400px;margin-top:16px">
    <h3 style="margin-bottom:12px;color:var(--text-bright);font-size:14px">Выдать PLEX (29668)</h3>
    <form method="POST">
        <input type="hidden" name="action" value="give">
        <input type="hidden" name="typeid" value="29668">
        <div class="form-group">
            <label>Character ID</label>
            <input name="characterid" type="number" required>
        </div>
        <div class="form-group">
            <label>Количество PLEX</label>
            <input name="quantity" type="number" min="1" value="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Выдать PLEX</button>
    </form>
    <p style="color:var(--text-dim);font-size:11px;margin-top:8px">1 PLEX = 30 дней Omega (активируется на /account).</p>
</div>

<?php if (current_user()['role'] & ROLE_ADMIN): ?>
<div class="form-card" style="max-width:400px;margin-top:16px">
    <h3 style="margin-bottom:12px;color:var(--text-bright);font-size:14px">Вброс PLEX на рынок</h3>
    <form method="POST">
        <input type="hidden" name="action" value="injectplex">
        <div class="form-group">
            <label>Station ID (например Jita 4-4 = 60003760)</label>
            <input name="stationid" type="number" required>
        </div>
        <div class="form-group">
            <label>Цена за 1 PLEX (ISK)</label>
            <input name="price" type="number" step="0.01" min="1" required>
        </div>
        <div class="form-group">
            <label>Количество (1..100)</label>
            <input name="quantity" type="number" min="1" max="100" value="1" required>
        </div>
        <button type="submit" class="btn btn-primary">Выставить на рынок</button>
    </form>
    <p style="color:var(--text-dim);font-size:11px;margin-top:8px">Создаются sell-ордера NPC-корпы станции; доступно только главному админу.</p>
</div>
<?php endif; ?>
