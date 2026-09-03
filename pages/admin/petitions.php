<?php
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'close') {
        $pid = intval($_POST['petitionid'] ?? 0);
        $xml = api_post('/admin/PetitionClose.xml.aspx', "petitionid=$pid");
        $msg = $xml && $xml->result ? 'Петиция закрыта' : 'Ошибка';
    }
    if ($act === 'reply') {
        $pid = intval($_POST['petitionid'] ?? 0);
        $reply = $_POST['reply'] ?? '';
        $xml = api_post('/admin/PetitionReply.xml.aspx', "petitionid=$pid&reply=" . urlencode($reply));
        $msg = $xml && $xml->result ? 'Ответ отправлен' : 'Ошибка';
    }
}

$xml = api_get('/admin/PetitionList.xml.aspx');
$petitions = [];
if ($xml && $xml->result && $xml->result->petitions)
    foreach ($xml->result->petitions->row as $r) $petitions[] = $r;
?>

<h2 style="margin-bottom:16px">Петиции</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>

<table class="data-table">
    <thead><tr><th>#</th><th>Дата</th><th>Автор</th><th>Тема</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($petitions as $p): ?>
    <tr>
        <td><?= $p['petitionid'] ?></td>
        <td style="color:var(--text-dim)"><?= e($p['createdate']) ?></td>
        <td><?= e($p['authorname']) ?></td>
        <td><?= e($p['subject']) ?></td>
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
