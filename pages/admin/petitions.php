<?php
$msg = '';
$error = '';
$view = isset($_GET['view']) ? intval($_GET['view']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'close') {
        $pid = intval($_POST['petitionid'] ?? 0);
        $xml = api_post('/admin/PetitionClose.xml.aspx', "petitionid=$pid");
        $msg = $xml && $xml->result ? 'Петиция закрыта' : 'Ошибка';
        if ($pid) $view = $pid;
    }
    if ($act === 'reply') {
        $pid = intval($_POST['petitionid'] ?? 0);
        $reply = $_POST['reply'] ?? '';
        $xml = api_post('/admin/PetitionReply.xml.aspx', "petitionid=$pid&reply=" . urlencode($reply));
        $msg = $xml && $xml->result ? 'Ответ отправлен' : 'Ошибка';
        if ($pid) $view = $pid;
    }
}

$xml = api_get('/admin/PetitionList.xml.aspx');
$petitions = [];
if ($xml && $xml->result && $xml->result->petitions)
    foreach ($xml->result->petitions->row as $r) $petitions[] = $r;
?>

<h2 style="margin-bottom:16px">Петиции</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<table class="data-table">
    <thead><tr><th>#</th><th>Дата</th><th>Автор</th><th>Тема</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($petitions as $p): ?>
    <tr style="<?= $view==$p['petitionid'] ? 'background:rgba(255,255,255,0.04)' : '' ?>">
        <td><?= $p['petitionid'] ?></td>
        <td style="color:var(--text-dim);white-space:nowrap"><?= e($p['createdate']) ?></td>
        <td><?= e($p['authorname']) ?></td>
        <td><a href="/admin/petitions?view=<?= $p['petitionid'] ?>" style="color:var(--accent2)"><?= e($p['subject']) ?></a></td>
        <td><?= $p['status']==1 ? '<span class="badge badge-open">Открыта</span>' : '<span class="badge badge-closed">Закрыта</span>' ?></td>
        <td>
            <?php if ($p['status']==1): ?>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="close"><input type="hidden" name="petitionid" value="<?= $p['petitionid'] ?>"><button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">Закрыть</button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($petitions)): ?><tr><td colspan="6" class="empty">Нет петиций</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($view): foreach ($petitions as $p): if ($p['petitionid']==$view): ?>
<div class="form-card" style="margin-top:16px">
    <h3 style="margin-bottom:4px;font-size:14px">#<?= $p['petitionid'] ?> — <?= e($p['subject']) ?></h3>
    <div style="color:var(--text-dim);font-size:12px;margin-bottom:10px">
        <?= e($p['authorname']) ?> &middot; <?= e($p['createdate']) ?> &middot;
        <?= $p['status']==1 ? 'открыта' : 'закрыта' ?>
    </div>
    <div style="white-space:pre-wrap;font-size:13px;line-height:1.5;color:var(--text);border-top:1px solid var(--border);padding-top:10px;margin-bottom:12px">
        <?= e($p['body']) ?>
    </div>
    <?php if ($p['status']==1): ?>
    <form method="POST">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="petitionid" value="<?= $p['petitionid'] ?>">
        <div class="form-group">
            <label>Ответ игроку</label>
            <textarea name="reply" rows="4" required placeholder="Текст ответа..."></textarea>
        </div>
        <button class="btn btn-primary" style="width:auto">Отправить ответ</button>
    </form>
    <?php endif; ?>
</div>
<?php break; endif; endforeach; endif; ?>
