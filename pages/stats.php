<?php
require_once __DIR__ . '/../layout.php';

$server = api_get('/server/ServerStatus.xml.aspx');
$kills = api_get('/server/KillStats.xml.aspx');

$online = $version = $players = $accounts = $characters = $bots = '—';
if ($server && $server->result) {
    $r = $server->result;
    $online    = $r->online ?? $r->serveronline ?? '—';
    $version   = $r->version ?? $r->serverversion ?? '—';
    $players   = $r->players ?? '—';
    $accounts  = $r->accounts ?? '—';
    $characters = $r->characters ?? '—';
    $bots      = $r->bots ?? '—';
}

$totalKills = 0;
$topKillers = $topVictims = $killsBySystem = $topShips = [];
if ($kills && $kills->result) {
    $r = $kills->result;
    $totalKills = (int)($r->totalkills ?? 0);

    if (!empty($r->topkillers))
        foreach ($r->topkillers->row as $row) $topKillers[] = $row;
    if (!empty($r->topvictims))
        foreach ($r->topvictims->row as $row) $topVictims[] = $row;
    if (!empty($r->killsbysystem))
        foreach ($r->killsbysystem->row as $row) $killsBySystem[] = $row;
    if (!empty($r->topships))
        foreach ($r->topships->row as $row) $topShips[] = $row;
}

ob_start();
?>
<h1>Статистика сервера</h1>

<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-num" style="color:<?= $online ? '#66cc88' : '#ee4444' ?>"><?= e($online) ?></div>
        <div class="stat-label">Онлайн</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($players) ?></div>
        <div class="stat-label">Игроков</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($accounts) ?></div>
        <div class="stat-label">Аккаунтов</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($characters) ?></div>
        <div class="stat-label">Персонажей</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#ee8844"><?= e($bots) ?></div>
        <div class="stat-label">Ботов</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#ee4444"><?= number_format($totalKills) ?></div>
        <div class="stat-label">Всего киллов</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($version) ?></div>
        <div class="stat-label">Версия</div>
    </div>
</div>

<?php if (!empty($topKillers)): ?>
<div class="section">
    <h2>Топ убийц</h2>
    <table class="data-table">
        <thead><tr><th>#</th><th>Пилот</th><th>Корабль</th><th>Киллы</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($topKillers, 0, 10) as $i => $row): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="/character/<?= $row['characterid'] ?? '' ?>"><?= e($row['charactername'] ?? $row['name'] ?? '') ?></a></td>
            <td><?= e($row['shipname'] ?? $row['ship'] ?? '') ?></td>
            <td style="font-weight:600;color:var(--accent2)"><?= e($row['kills'] ?? $row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($topKillers)): ?>
        <tr><td colspan="4" class="empty">Нет данных</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($topVictims)): ?>
<div class="section">
    <h2>Топ жертв</h2>
    <table class="data-table">
        <thead><tr><th>#</th><th>Пилот</th><th>Корабль</th><th>Смерти</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($topVictims, 0, 10) as $i => $row): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="/character/<?= $row['characterid'] ?? '' ?>"><?= e($row['charactername'] ?? $row['name'] ?? '') ?></a></td>
            <td><?= e($row['shipname'] ?? $row['ship'] ?? '') ?></td>
            <td style="font-weight:600;color:var(--danger)"><?= e($row['deaths'] ?? $row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($topVictims)): ?>
        <tr><td colspan="4" class="empty">Нет данных</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($killsBySystem)): ?>
<div class="section">
    <h2>Киллы по системам</h2>
    <table class="data-table">
        <thead><tr><th>Система</th><th>Киллы</th></tr></thead>
        <tbody>
        <?php foreach ($killsBySystem as $row): ?>
        <tr>
            <td style="color:var(--gold)"><?= e($row['solarsystemname'] ?? $row['system'] ?? '') ?></td>
            <td style="font-weight:600"><?= e($row['kills'] ?? $row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($topShips)): ?>
<div class="section">
    <h2>Популярные корабли</h2>
    <table class="data-table">
        <thead><tr><th></th><th>Корабль</th><th>Использований</th></tr></thead>
        <tbody>
        <?php foreach ($topShips as $row): ?>
        <tr>
            <td style="width:40px"><img src="<?= ship_icon($row['typeid'] ?? 0, 32) ?>" width="32" height="32" style="border-radius:3px;background:#0d1117" onerror="this.style.display='none'"></td>
            <td><?= e($row['shipname'] ?? $row['name'] ?? '') ?></td>
            <td style="font-weight:600"><?= e($row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($topShips)): ?>
        <tr><td colspan="3" class="empty">Нет данных</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($totalKills === 0 && empty($topKillers) && empty($killsBySystem)): ?>
<div class="section">
    <p style="color:var(--text-dim);text-align:center;padding:40px 0">Статистика пока пуста. Начните стрелять!</p>
</div>
<?php endif; ?>

<?php
render_layout('Статистика', 'stats', ob_get_clean());
