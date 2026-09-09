<?php
$xml = api_get('/admin/SecurityFlags.xml.aspx');
$flags = [];
if ($xml && $xml->result && $xml->result->flags)
    foreach ($xml->result->flags->row as $f) $flags[] = $f;
?>

<h2 style="margin-bottom:16px">Security / RMT monitoring</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:12px">
    Крупные переводы человек↔человек (24ч), общие IP (мультиаккаунтинг, 14д), открытые петиции Боты/RMT.
</p>

<table class="data-table">
    <thead><tr><th>Тип</th><th>Детали</th><th>Когда/сумма</th></tr></thead>
    <tbody>
    <?php foreach ($flags as $f): $t = (string)$f['type']; ?>
        <?php if ($t === 'flow'): ?>
        <tr>
            <td><span class="badge badge-banned">RMT-flow</span></td>
            <td>
                <a href="/character/<?= (int)$f['sellerid'] ?>" style="color:var(--accent2)"><?= e($f['sellername']) ?></a>
                → <a href="/character/<?= (int)$f['buyerid'] ?>" style="color:var(--accent2)"><?= e($f['buyername']) ?></a>
                <div style="color:var(--text-dim);font-size:11px">сделок: <?= (int)$f['trades'] ?></div>
            </td>
            <td style="white-space:nowrap"><b style="color:#ff6b6b"><?= number_format((int)$f['isk'], 0, '.', ' ') ?> ISK</b></td>
        </tr>
        <?php elseif ($t === 'multibox'): ?>
        <tr>
            <td><span class="badge badge-gm">Multibox</span></td>
            <td><b style="color:var(--text)"><?= e($f['ip']) ?></b>
                <div style="color:var(--text-dim);font-size:11px">аккаунтов: <?= (int)$f['accounts'] ?> — <?= e($f['names']) ?></div>
            </td>
            <td style="color:var(--text-dim)">14 дней</td>
        </tr>
        <?php elseif ($t === 'petition'): ?>
        <tr>
            <td><span class="badge badge-banned">Petition</span></td>
            <td>
                <a href="/admin/petitions?view=<?= (int)$f['petitionid'] ?>" style="color:var(--accent2)">#<?= (int)$f['petitionid'] ?> <?= e($f['subject']) ?></a>
                <div style="color:var(--text-dim);font-size:11px">автор: <?= (int)$f['accountid'] ? '<a href="/admin/account/'.(int)$f['accountid'].'" style="color:var(--accent2)">'.e($f['authorname']).'</a>' : e($f['authorname']) ?></div>
            </td>
            <td style="color:var(--text-dim);white-space:nowrap"><?= e($f['createdate']) ?></td>
        </tr>
        <?php elseif ($t === 'transfer'): ?>
        <tr>
            <td><span class="badge badge-gm">Transfer</span></td>
            <td>
                <a href="/admin/petitions?view=<?= (int)$f['petitionid'] ?>" style="color:var(--accent2)">#<?= (int)$f['petitionid'] ?> <?= e($f['subject']) ?></a>
                <div style="color:var(--text-dim);font-size:11px">автор: <?= (int)$f['accountid'] ? '<a href="/admin/account/'.(int)$f['accountid'].'" style="color:var(--accent2)">'.e($f['authorname']).'</a>' : e($f['authorname']) ?> — ждёт одобрения</div>
            </td>
            <td style="color:var(--text-dim);white-space:nowrap"><?= e($f['createdate']) ?></td>
        </tr>
        <?php elseif ($t === 'capital'): ?>
        <tr>
            <td><span class="badge badge-gm">Capital</span></td>
            <td>
                <a href="/character/<?= (int)$f['characterid'] ?>" style="color:var(--accent2)"><?= e($f['charactername']) ?></a>
                (акк. <a href="/admin/account/<?= (int)$f['accountid'] ?>" style="color:var(--accent2)"><?= e($f['accountname']) ?></a>)
                <div style="color:var(--text-dim);font-size:11px"><?= e($f['groupname']) ?>: <?= e($f['shipname']) ?></div>
            </td>
            <td style="color:var(--text-dim)"><?= ship_icon((int)$f['shiptypeid'], 32) !== '' ? '<img src="'.ship_icon((int)$f['shiptypeid'],32).'" alt="" width="24" height="24" style="vertical-align:middle" onerror="this.style.display=\'none\'">' : '—' ?></td>
        </tr>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (empty($flags)): ?><tr><td colspan="3" class="empty">Флагов нет — всё чисто.</td></tr><?php endif; ?>
    </tbody>
</table>
