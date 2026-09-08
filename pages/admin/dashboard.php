<?php
$ss = api_get('/server/ServerStatus.xml.aspx');
$online   = $ss && $ss->result ? (int)$ss->result->serveronline : 0;
$players  = $ss && $ss->result ? (int)($ss->result->onlineplayers ?? 0) : 0;
$real     = $ss && $ss->result ? (int)($ss->result->onlineplayersreal ?? 0) : 0;
$accountsAll = $ss && $ss->result ? (int)($ss->result->accountcount ?? 0) : 0;
$charsAll    = $ss && $ss->result ? (int)($ss->result->charactercount ?? 0) : 0;
$botsAll     = $ss && $ss->result ? (int)($ss->result->botcount ?? 0) : 0;

$acct = api_get('/admin/AccountList.xml.aspx');
$acctOnline = 0; $acctBanned = 0;
if ($acct && $acct->result && $acct->result->accounts)
    foreach ($acct->result->accounts->row as $a) {
        if ((int)$a['online']) $acctOnline++;
        if ((int)$a['banned']) $acctBanned++;
    }

$pets = api_get('/admin/PetitionList.xml.aspx');
$petOpen = 0; $petBotRmt = 0; $petRows = [];
if ($pets && $pets->result && $pets->result->petitions)
    foreach ($pets->result->petitions->row as $r) {
        $cid = (int)$r['categoryid'];
        if ((int)$r['status'] === 1) { $petOpen++; $petRows[] = $r; if ($cid === 601 || $cid === 602) $petBotRmt++; }
    }
usort($petRows, function($x,$y){ return (int)$y['petitionid'] - (int)$x['petitionid']; });

$sec = api_get('/admin/SecurityFlags.xml.aspx');
$secCount = $sec && $sec->result && $sec->result->flags ? count($sec->result->flags->row) : 0;

$kk = api_get('/char/AllKills.xml.aspx');
$kills = $kk && $kk->result && $kk->result->kills ? count($kk->result->kills->row) : 0;
?>
<h2 style="margin-bottom:16px">Обзор</h2>
<div class="stat-cards">
    <div class="stat-card"><div class="stat-num" style="color:<?= $online ? 'var(--accent)' : 'var(--danger)' ?>"><?= $online ? 'ON' : 'OFF' ?></div><div class="stat-label">Сервер</div></div>
    <div class="stat-card"><div class="stat-num"><?= $players ?><span style="font-size:12px;color:var(--text-dim)"> / <?= $real ?></span></div><div class="stat-label">Онлайн (всего / реальных)</div></div>
    <div class="stat-card"><div class="stat-num"><?= $acctOnline ?><span style="font-size:12px;color:var(--text-dim)"> / <?= $accountsAll ?></span></div><div class="stat-label">Аккаунтов (онлайн / всего)</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $acctBanned ? 'var(--danger)' : 'var(--text)' ?>"><?= $acctBanned ?></div><div class="stat-label">Забанено</div></div>
    <div class="stat-card"><div class="stat-num"><?= $charsAll ?><span style="font-size:12px;color:var(--text-dim)"> / <?= $botsAll ?></span></div><div class="stat-label">Персонажей / симул.</div></div>
    <div class="stat-card"><div class="stat-num"><?= $kills ?></div><div class="stat-label">Киллов (выборка)</div></div>
    <div class="stat-card"><div class="stat-num"><?= $petOpen ?></div><div class="stat-label">Открытых петиций</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $petBotRmt ? 'var(--danger)' : 'var(--text)' ?>"><?= $petBotRmt ?></div><div class="stat-label">Боты/RMT петиции</div></div>
    <div class="stat-card"><div class="stat-num" style="color:<?= $secCount ? 'var(--danger)' : 'var(--text)' ?>"><?= $secCount ?></div><div class="stat-label">Security-флаги</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px;align-items:start">
    <div class="form-card">
        <h3 style="font-size:14px;margin-bottom:8px">Открытые петиции (свежие)</h3>
        <?php if (!$petRows): ?><p style="color:var(--text-dim)">Нет открытых петиций.</p><?php endif; ?>
        <?php foreach (array_slice($petRows,0,6) as $p): ?>
            <div style="padding:6px 0;border-bottom:1px solid var(--border);display:flex;gap:8px;align-items:center">
                <a href="/admin/petitions?view=<?= (int)$p['petitionid'] ?>" style="color:var(--accent2);white-space:nowrap">#<?= (int)$p['petitionid'] ?></a>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($p['subject']) ?></span>
                <?php $cc=(int)$p['categoryid']; if ($cc===601||$cc===602): ?><span class="badge badge-banned" style="background:#5a1d1d"><?= $cc===601?'Боты':'RMT' ?></span><?php endif; ?>
                <span style="color:var(--text-dim);font-size:11px"><?= e($p['authorname']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="form-card">
        <h3 style="font-size:14px;margin-bottom:8px">Мониторинг</h3>
        <div style="font-size:13px;line-height:1.8">
            <div><b>Security-флаги:</b> <a href="/admin/security" style="color:var(--accent2)"><?= $secCount ?></a></div>
            <div><b>Боты/RMT петиции:</b> <a href="/admin/petitions?cat=bot" style="color:var(--accent2)"><?= $petBotRmt ?></a> (🤖 + 💸)</div>
            <div><b>Общие IP-группы:</b> <a href="/admin/network" style="color:var(--accent2)">открыть</a></div>
            <div><b>Аккаунты:</b> <a href="/admin/accounts" style="color:var(--accent2)"><?= $accountsAll ?></a> · <a href="/admin/network" style="color:var(--accent2)">сеть</a></div>
            <div><b>Забанить по IP:</b> в разделе Network (кнопка на группе IP)</div>
        </div>
    </div>
</div>
