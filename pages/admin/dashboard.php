<?php
$xml = api_get('/server/ServerStatus.xml.aspx');
$online = $xml && $xml->result ? (int)$xml->result->serveronline : 0;
$players = $xml && $xml->result ? (int)$xml->result->onlineplayers : 0;

$xml2 = api_get('/char/AllKills.xml.aspx');
$kills = 0;
if ($xml2 && $xml2->result && $xml2->result->kills)
    $kills = count($xml2->result->kills->row);
?>
<h2 style="margin-bottom:16px">Обзор</h2>
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-num" style="color:<?= $online ? 'var(--accent)' : 'var(--danger)' ?>"><?= $online ? 'ON' : 'OFF' ?></div>
        <div class="stat-label">Сервер</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= $players ?></div>
        <div class="stat-label">Онлайн</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= $kills ?></div>
        <div class="stat-label">Киллов</div>
    </div>
</div>
