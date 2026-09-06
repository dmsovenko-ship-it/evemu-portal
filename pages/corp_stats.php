<?php
require_once __DIR__ . '/../layout.php';

$corpID = intval($id ?? 0);
if (!$corpID) { redirect('/'); return; }

$corpInfo = null;
$corpXml = api_get('/corp/CorporationSheet.xml.aspx?corporationID=' . $corpID);
if ($corpXml && $corpXml->result) $corpInfo = $corpXml->result;

$rows = [];
$killsXml = api_get('/corp/KillMails.xml.aspx?corporationID=' . $corpID);
if ($killsXml && $killsXml->result && $killsXml->result->kills)
    foreach ($killsXml->result->kills->row as $r) $rows[] = $r;

$corpName = $corpInfo ? (string)($corpInfo->corporationName ?? 'Unknown') : 'Unknown';
$ticker  = $corpInfo ? (string)($corpInfo->ticker ?? '') : '';

// Split into kills (corp delivered final blow) and losses (corp was victim).
$kills = []; $losses = [];
foreach ($rows as $r) {
    if ((int)$r['finalcorporationid'] === $corpID)  $kills[] = $r;
    if ((int)$r['victimcorporationid'] === $corpID) $losses[] = $r;
}

function tally($list, $key) {
    $m = [];
    foreach ($list as $r) {
        $v = (string)$r[$key];
        if ($v === '') $v = 'Unknown';
        $m[$v] = ($m[$v] ?? 0) + 1;
    }
    arsort($m);
    return $m;
}
$kBySystem = tally($kills, 'solarsystemname');
$lBySystem = tally($losses, 'solarsystemname');
$kByShip   = tally($kills, 'victimshipname');
$lByShip   = tally($losses, 'victimshipname');
$kByKiller = tally($kills, 'finalname');
$lByVictim = tally($losses, 'victimname');

$allSystems = [];
foreach ($kBySystem as $s=>$c) $allSystems[$s] = ($allSystems[$s]??0)+$c;
foreach ($lBySystem as $s=>$c) $allSystems[$s] = ($allSystems[$s]??0)+$c;
arsort($allSystems);

function bars($map, $max) {
    $out = '';
    foreach (array_slice($map, 0, 12, true) as $k=>$v) {
        $pct = $max>0 ? round(100*$v/$max) : 0;
        $out .= '<div class="bar-row"><span class="bar-name">' . e($k) . '</span>'
              . '<div class="bar"><div class="bar-fill" style="width:' . $pct . '%"></div></div>'
              . '<span class="bar-val">' . $v . '</span></div>';
    }
    return $out;
}

$nKills = count($kills); $nLosses = count($losses);

ob_start();
?>
<div class="corp-profile">
    <div class="corp-logo"><img src="<?= corp_logo($corpID, 128) ?>" onerror="this.style.display='none'"></div>
    <div class="corp-info">
        <h1><?= e($corpName) ?> <span class="corp-ticker">[<?= e($ticker) ?>]</span></h1>
        <div class="corp-meta">
            <a href="/corporation/<?= $corpID ?>">&laquo; back to kills</a>
        </div>
        <div class="corp-stats">
            <div class="stat"><div class="stat-label">Kills</div><div class="stat-num" style="color:var(--accent)"><?= number_format($nKills) ?></div></div>
            <div class="stat"><div class="stat-label">Losses</div><div class="stat-num" style="color:var(--danger)"><?= number_format($nLosses) ?></div></div>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="panel">
        <h3>Top systems</h3>
        <?php
            $max = $allSystems ? max($allSystems) : 0;
            echo bars($allSystems, $max);
            if (!$allSystems) echo '<p class="empty">No data</p>';
        ?>
    </div>
    <div class="panel">
        <h3>Top ships killed</h3>
        <?php
            $max = $kByShip ? max($kByShip) : 0;
            echo bars($kByShip, $max);
            if (!$kByShip) echo '<p class="empty">No kills</p>';
        ?>
    </div>
    <div class="panel">
        <h3>Top ships lost</h3>
        <?php
            $max = $lByShip ? max($lByShip) : 0;
            echo bars($lByShip, $max);
            if (!$lByShip) echo '<p class="empty">No losses</p>';
        ?>
    </div>
    <div class="panel">
        <h3>Top killers</h3>
        <?php
            $max = $kByKiller ? max($kByKiller) : 0;
            echo bars($kByKiller, $max);
            if (!$kByKiller) echo '<p class="empty">No kills</p>';
        ?>
    </div>
    <div class="panel">
        <h3>Top victims</h3>
        <?php
            $max = $lByVictim ? max($lByVictim) : 0;
            echo bars($lByVictim, $max);
            if (!$lByVictim) echo '<p class="empty">No losses</p>';
        ?>
    </div>
    <div class="panel">
        <h3>Systems of losses</h3>
        <?php
            $max = $lBySystem ? max($lBySystem) : 0;
            echo bars($lBySystem, $max);
            if (!$lBySystem) echo '<p class="empty">No losses</p>';
        ?>
    </div>
</div>

<?php
$content = ob_get_clean();
render_layout($corpName . ' — Statistics', 'kills', $content);
