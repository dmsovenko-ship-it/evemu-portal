<?php
$msg = '';
$error = '';
$view = isset($_GET['view']) ? intval($_GET['view']) : 0;
// monitoring filter: 'bot' (601 Multiboxing/Botting) or 'rmt' (602 RMT)
$catFilter = $_GET['cat'] ?? '';
if ($catFilter !== 'bot' && $catFilter !== 'rmt') $catFilter = '';

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
    if ($act === 'approve_transfer') {
        $pid = intval($_POST['petitionid'] ?? 0);
        $seller = intval($_POST['selleraccountid'] ?? 0);
        $buyer  = intval($_POST['buyeraccountid'] ?? 0);
        if ($pid && $seller && $buyer) {
            $xml = api_post('/admin/ApproveTransfer.xml.aspx',
                'selleraccountid=' . $seller . '&buyeraccountid=' . $buyer . '&petitionid=' . $pid
                . '&note=' . urlencode('передача аккаунта, петиция #' . $pid));
            $msg = $xml && $xml->result ? 'Передача одобрена — потоки пары больше не флагаются как RMT' : 'Ошибка';
        }
        if ($pid) $view = $pid;
    }
}

$xml = api_get('/admin/PetitionList.xml.aspx');
$petitions = [];
if ($xml && $xml->result && $xml->result->petitions)
    foreach ($xml->result->petitions->row as $r) $petitions[] = $r;

// Apply the bot/RMT filter first, then paginate the visible list.
$listPetitions = [];
foreach ($petitions as $p) {
    $cid = (int)$p['categoryid'];
    if ($catFilter === 'bot' && $cid !== 601) continue;
    if ($catFilter === 'rmt' && $cid !== 602) continue;
    $listPetitions[] = $p;
}
paginate_admin($listPetitions, 25, $pagedPetitions, $pgNo, $pgTotal);

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

<div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <a href="/admin/petitions" class="btn <?= $catFilter===''?'btn-primary':'btn-outline' ?>" style="width:auto;padding:5px 12px;font-size:12px">Все</a>
    <a href="/admin/petitions?cat=bot" class="btn <?= $catFilter==='bot'?'btn-primary':'btn-outline' ?>" style="width:auto;padding:5px 12px;font-size:12px">🤖 Боты / мультиаккаунты</a>
    <a href="/admin/petitions?cat=rmt" class="btn <?= $catFilter==='rmt'?'btn-primary':'btn-outline' ?>" style="width:auto;padding:5px 12px;font-size:12px">💸 RMT</a>
</div>

<table class="data-table">
    <thead><tr><th>#</th><th>Создана</th><th>Изменена</th><th>Автор</th><th>Тип</th><th>Категория</th><th>Тема</th><th>Статус</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pagedPetitions as $p):
        $catId = (int)$p['categoryid'];
        $specialCat = ($catId === 601 || $catId === 602) ? ' <span class="badge badge-banned" style="background:#5a1d1d">' . ($catId === 601 ? 'Боты' : 'RMT') . '</span>' : '';
    ?>
    <tr style="<?= $view==(int)$p['petitionid'] ? 'background:rgba(255,255,255,0.04)' : ''; ?>">
        <td><a href="/admin/petitions?view=<?= (int)$p['petitionid'] ?>" style="color:var(--accent2);font-weight:600">#<?= (int)$p['petitionid'] ?></a></td>
        <td style="color:var(--text-dim);white-space:nowrap"><?= e($p['createdate']) ?></td>
        <td style="color:var(--text-dim);white-space:nowrap"><?= e($p['touchdate'] ?: $p['createdate']) ?></td>
        <td>
            <?php if ((int)$p['accountid']): ?><a href="/admin/account/<?= (int)$p['accountid'] ?>" style="color:var(--accent2)"><?= e($p['authorname']) ?></a>
            <?php else: ?><?= e($p['authorname']) ?><?php endif; ?>
        </td>
        <td><?= (int)$p['characterid'] > 0 ? 'игра' : 'портал' ?></td>
        <td style="color:var(--text-dim)"><?= e($p['categoryname'] ?: '—') ?><?= $specialCat ?></td>
        <td><a href="/admin/petitions?view=<?= (int)$p['petitionid'] ?>" style="color:var(--accent2)"><?= e($p['subject']) ?></a></td>
        <td><?= (int)$p['status']===1 ? '<span class="badge badge-open">Открыта</span>' : '<span class="badge badge-closed">Закрыта</span>' ?></td>
        <td>
            <?php if ((int)$p['status']===1): ?>
                <form method="POST" style="display:inline"><input type="hidden" name="action" value="close"><input type="hidden" name="petitionid" value="<?= (int)$p['petitionid'] ?>"><button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">Закрыть</button></form>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($pagedPetitions)): ?><tr><td colspan="9" class="empty">Нет петиций</td></tr><?php endif; ?>
    </tbody>
</table>
<?= pager_html($pgNo, $pgTotal) ?>

<?php if ($viewPet): ?>
<div class="form-card" style="margin-top:16px">
    <h3 style="margin-bottom:4px;font-size:14px">#<?= (int)$viewPet['petitionid'] ?> — <?= e($viewPet['subject']) ?></h3>
    <div style="color:var(--text-dim);font-size:12px;margin-bottom:10px">
        <?php if ((int)$viewPet['accountid']): ?>
            <a href="/admin/account/<?= (int)$viewPet['accountid'] ?>" style="color:var(--accent2)"><?= e($viewPet['authorname']) ?></a> (акк. #<?= (int)$viewPet['accountid'] ?> — персонажи/ISK/SP)
        <?php else: ?><?= e($viewPet['authorname']) ?><?php endif; ?>
        &middot; <?= e($viewPet['categoryname'] ?: '—') ?>
        &middot; создана <?= e($viewPet['createdate']) ?>
        &middot; изменена <?= e($viewPet['touchdate'] ?: $viewPet['createdate']) ?>
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
    <?php if ((int)$viewPet['categoryid']===603 && (int)$viewPet['status']===1): ?>
    <div style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px">
        <h4 style="font-size:12px;color:var(--text-dim);margin-bottom:8px">Легальная передача аккаунта</h4>
        <form method="POST">
            <input type="hidden" name="action" value="approve_transfer">
            <input type="hidden" name="petitionid" value="<?= (int)$viewPet['petitionid'] ?>">
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <div class="form-group" style="flex:1;min-width:180px">
                    <label>Продавец (accountID)</label>
                    <input name="selleraccountid" value="<?= (int)$viewPet['accountid'] ?>" required>
                </div>
                <div class="form-group" style="flex:1;min-width:180px">
                    <label>Покупатель (accountID)</label>
                    <input name="buyeraccountid" required placeholder="аккаунт получателя">
                </div>
                <div style="align-self:flex-end">
                    <button class="btn btn-primary" style="width:auto">Одобрить передачу</button>
                </div>
            </div>
            <p style="color:var(--text-dim);font-size:11px;margin-top:6px">
                После одобрения крупные переводы между этими аккаунтами не будут помечаться как RMT.
            </p>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
