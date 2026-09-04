<?php
require_once __DIR__ . '/../layout.php';

$server = api_get('/server/ServerStatus.xml.aspx');
$kills = api_get('/server/KillStats.xml.aspx');

$online = $version = $players = $accounts = $characters = $bots = '—';
if ($server && $server->result) {
    $r = $server->result;
    $online    = $r->online ?? $r->serveronline ?? $r->playersOnline ?? '—';
    $version   = $r->version ?? $r->serverversion ?? '—';
    $players   = $r->players ?? $r->playersOnline ?? '—';
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

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Server Statistics</h2>
</div>

<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-num" style="color:<?= $online != '—' && $online ? 'var(--accent)' : 'var(--danger)' ?>"><?= e($online) ?></div>
        <div class="stat-label">Online</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($players) ?></div>
        <div class="stat-label">Players</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($accounts) ?></div>
        <div class="stat-label">Accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= e($characters) ?></div>
        <div class="stat-label">Characters</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--warn)"><?= e($bots) ?></div>
        <div class="stat-label">Bots</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--danger)"><?= number_format($totalKills) ?></div>
        <div class="stat-label">Total Kills</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="font-size:16px"><?= e($version) ?></div>
        <div class="stat-label">Version</div>
    </div>
</div>

<?php if (!empty($topKillers)): ?>
<div class="section">
    <div class="section-header">
        <h2>Top Killers</h2>
    </div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Pilot</th><th>Ship</th><th>Kills</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($topKillers, 0, 10) as $i => $row): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="/character/<?= $row['characterid'] ?? '' ?>"><?= e($row['charactername'] ?? $row['name'] ?? '') ?></a></td>
            <td style="color:var(--text-dim)"><?= e($row['shipname'] ?? $row['ship'] ?? '') ?></td>
            <td style="font-weight:600;color:var(--accent2)"><?= e($row['kills'] ?? $row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($topVictims)): ?>
<div class="section">
    <div class="section-header">
        <h2>Top Victims</h2>
    </div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Pilot</th><th>Ship</th><th>Deaths</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($topVictims, 0, 10) as $i => $row): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><a href="/character/<?= $row['characterid'] ?? '' ?>"><?= e($row['charactername'] ?? $row['name'] ?? '') ?></a></td>
            <td style="color:var(--text-dim)"><?= e($row['shipname'] ?? $row['ship'] ?? '') ?></td>
            <td style="font-weight:600;color:var(--danger)"><?= e($row['deaths'] ?? $row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($killsBySystem)): ?>
<div class="section">
    <div class="section-header">
        <h2>Kills by System</h2>
    </div>
    <table class="data-table">
        <thead><tr><th>System</th><th>Kills</th></tr></thead>
        <tbody>
        <?php foreach ($killsBySystem as $row): ?>
        <tr>
            <td style="color:var(--gold);font-weight:600"><?= e($row['solarsystemname'] ?? $row['system'] ?? '') ?></td>
            <td style="font-weight:600;font-variant-numeric:tabular-nums"><?= e($row['kills'] ?? $row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($topShips)): ?>
<div class="section">
    <div class="section-header">
        <h2>Popular Ships</h2>
    </div>
    <table class="data-table">
        <thead><tr><th></th><th>Ship</th><th>Uses</th></tr></thead>
        <tbody>
        <?php foreach ($topShips as $row): ?>
        <tr>
            <td style="width:36px"><img src="<?= ship_icon($row['typeid'] ?? 0, 32) ?>" width="32" height="32" style="border-radius:3px;background:#0d1117" onerror="this.style.display='none'"></td>
            <td><?= e($row['shipname'] ?? $row['name'] ?? '') ?></td>
            <td style="font-weight:600;font-variant-numeric:tabular-nums"><?= e($row['count'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($totalKills === 0 && empty($topKillers) && empty($killsBySystem)): ?>
<div class="section">
    <p style="color:var(--text-dim);text-align:center;padding:32px 0">Statistics are empty. Go get some kills!</p>
</div>
<?php endif; ?>

<?php
render_layout('Statistics', 'stats', ob_get_clean());
