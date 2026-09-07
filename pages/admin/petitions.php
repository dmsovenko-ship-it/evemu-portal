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
        $reply = trim($_POST['reply'] ?? '');
        if ($pid && $reply !== '') {
            // GM identity: use the admin account's first character if present.
            $gmName = current_user()['accountName'] ?? 'GM';
            $gmID = 1;
            $xc = api_get('/char/CharacterList.xml.aspx?accountid=' . (current_user()['accountID'] ?? 0));
            if ($xc && $xc->result && $xc->result->characters && isset($xc->result->characters->row[0])) {
                $gmName = (string)$xc->result->characters->row[0]['charactername'];
                $gmID = (int)$xc->result->characters->row[0]['characterid'];
            }
            $xml = api_post('/admin/PetitionReply.xml.aspx',
                "petitionid=$pid"
                . '&adminname=' . urlencode($gmName)
                . '&senderid=' . $gmID
                . '&reply=' . urlencode($reply));
            $msg = $xml && $xml->result ? 'Ответ отправлен' : 'Ошибка';
        }
        if ($pid) $view = $pid;
    }
}

$xml = api_get('/admin/PetitionList.xml.aspx');
$petitions = [];
if ($xml && $xml->result && $xml->result->petitions)
    foreach ($xml->result->petitions->row as $r) $petitions[] = $r;

$thread = [];
$viewPet = null;
if ($view) {
    foreach ($petitions as $p) if ((int)$p['petitionid'] === $view) { $viewPet = $p; break; }
    if ($viewPet) {
        $mx = api_get('/admin/PetitionMessages.xml.aspx?petitionid=' . $view);
        if ($mx && $mx->result && $mx->result->messages)
            foreach ($mx->result->messages->row as $m) $thread[] = $m;
    }
}
?>

<h2 style="margin-bottom:16px">Петиции</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<table class="data-table">
    <thead><tr><th>#</th><th>Дата</th><th>Автор</th><th>Тип</th><th>Категория</th><th>Тема</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($petitions as $p): ?>
    <tr style="<?= $view==(int)$p['petitionid'] ? 'background:rgba(255,255,255,0.04)' : ''; ?>">
        <td><?= (int)$p['petitionid'] ?></td>
        <td style="color:var(--text-dim);white-space:nowrap"><?= e($p['createdate']) ?></td>
        <td><?= e($p['authorname']) ?></td>
        <td><?= (int)$p['characterid'] > 0 ? 'игра' : 'портал' ?></td>
        <td style="color:var(--text-dim)"><?= e($p['categoryname'] ?: '—') ?></td>
        <td><a href="/admin/petitions?view=<?= (int)$p['petitionid'] ?>" style="color:var(--accent2)"><?= e($p['subject']) ?></a></td>
        <td><?= (int)$p['status']===1 ? '<span class="badge badge-open">Открыта</span>' : '<span class="badge badge-closed">Закрыта</span>' ?></td>
        <td>
            <?php if ((int)$p['status']===1): ?>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="close"><input type="hidden" name="petitionid" value="<?= (int)$p['petitionid'] ?>"><button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">Закрыть</button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($petitions)): ?><tr><td colspan="8" class="empty">Нет петиций</td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($viewPet): ?>
<div class="form-card" style="margin-top:16px">
    <h3 style="margin-bottom:4px;font-size:14px">#<?= (int)$viewPet['petitionid'] ?> — <?= e($viewPet['subject']) ?></h3>
    <div style="color:var(--text-dim);font-size:12px;margin-bottom:10px">
        <?= e($viewPet['authorname']) ?> (акк. <?= (int)$viewPet['accountid'] ?>)
        &middot; <?= e($viewPet['categoryname'] ?: '—') ?>
        &middot; создана <?= e($viewPet['createdate']) ?>
        &middot; <?= (int)$viewPet['status']===1 ? 'открыта' : 'закрыта' ?>
    </div>
    <div class="pet-thread">
        <?php foreach ($thread as $m): ?>
        <div class="pet-msg <?= (int)$m['isgm']===1 ? 'pet-msg-gm' : '' ?>">
            <div class="pet-msg-head">
                <?php if ((int)$m['isgm']===1): ?><span class="badge badge-admin">GM</span><?php endif; ?>
                <b><?= e($m['sendername'] ?: ($m['isgm']==1 ? 'GM' : 'игрок')) ?></b>
                <span style="color:var(--text-dim);font-size:11px"><?= e($m['sentdate']) ?></span>
            </div>
            <div class="pet-msg-body"><?= nl2br(e($m['text'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (!$thread): ?><p style="color:var(--text-dim)">—</p><?php endif; ?>
    </div>
    <?php if ((int)$viewPet['status']===1): ?>
    <form method="POST" style="margin-top:12px">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="petitionid" value="<?= (int)$viewPet['petitionid'] ?>">
        <div class="form-group">
            <label>Ответ игроку</label>
            <textarea name="reply" rows="4" required placeholder="Текст ответа..."></textarea>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-primary" style="width:auto">Отправить ответ</button>
            <button class="btn btn-outline" style="width:auto" formnovalidate
                onclick="this.form.action.value='close';return confirm('Закрыть петицию?')">Закрыть</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>
