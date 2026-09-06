<?php
require_once __DIR__ . '/../layout.php';

$corpID = intval($id ?? 0);
if (!$corpID) { redirect('/'); return; }

$tab = $_GET['tab'] ?? 'kills';   // kills | losses
if (!in_array($tab, ['kills','losses'], true)) $tab = 'kills';

$corpInfo = null;
$corpXml = api_get('/corp/CorporationSheet.xml.aspx?corporationID=' . $corpID);
if ($corpXml && $corpXml->result) $corpInfo = $corpXml->result;

// Fetch ALL kill rows involving this corp, then split kills/losses client-side.
$rows = [];
$killsXml = api_get('/corp/KillMails.xml.aspx?corporationID=' . $corpID);
if ($killsXml && $killsXml->result && $killsXml->result->kills)
    foreach ($killsXml->result->kills->row as $r) $rows[] = $r;

$kills = []; $losses = [];
foreach ($rows as $r) {
    if ((int)$r['finalcorporationid'] === $corpID) $kills[] = $r;
    if ((int)$r['victimcorporationid'] === $corpID) $losses[] = $r;
}

$corpName = $corpInfo ? (string)($corpInfo->corporationName ?? 'Unknown') : 'Unknown';
$ticker = $corpInfo ? (string)($corpInfo->ticker ?? '') : '';
$memberCount = $corpInfo ? (int)($corpInfo->memberCount ?? 0) : 0;
$ceoID = $corpInfo ? (int)($corpInfo->ceoID ?? 0) : 0;
$ceoName = $corpInfo ? (string)($corpInfo->ceoName ?? '') : '';

$nKills = count($kills);
$nLosses = count($losses);
$ratio = $nLosses > 0 ? round($nKills / $nLosses, 1) : $nKills;

// Damage-based "ISK" proxy: victimDamageTaken as a rough value metric.
$dmgDealt = 0; $dmgTaken = 0;
foreach ($kills  as $k) $dmgDealt += (int)$k['victimdamagetaken'];
foreach ($losses as $k) $dmgTaken += (int)$k['victimdamagetaken'];
$eff = ($dmgDealt + $dmgTaken) > 0 ? round(100 * $dmgDealt / ($dmgDealt + $dmgTaken), 1) : 0;

// Kill history table shared by both tabs.
function render_kill_rows($list) {
    $out = '';
    foreach ($list as $k):
        $ts = filetime_to_unix((string)$k['killtime']);
        $sec = (float)$k['finalsecuritystatus'];
        $dmg = (int)$k['victimdamagetaken'];
        $out .= '<tr class="kill-row" onclick="location.href=\'/kill/' . $k['killid'] . '\'">'
            . '<td class="k-icon"><img src="' . ship_icon($k['victimshiptypeid'],32) . '" width="32" height="32" loading="lazy" onerror="this.style.display=\'none\'"></td>'
            . '<td class="k-system"><a href="/system/' . ($k['solarsystemid'] ?? '') . '" onclick="event.stopPropagation()"><span class="sec" style="color:' . security_color($sec) . '">' . number_format($sec,1) . '</span> ' . e($k['solarsystemname']) . '</a></td>'
            . '<td class="k-victim"><a href="/character/' . $k['victimcharacterid'] . '" onclick="event.stopPropagation()">' . e($k['victimname']) . '</a></td>'
            . '<td class="k-ship">' . e($k['victimshipname']) . '</td>'
            . '<td class="k-value">' . number_format($dmg) . '</td>'
            . '<td class="k-icon"><img src="' . ship_icon($k['finalshiptypeid'],32) . '" width="32" height="32" loading="lazy" onerror="this.style.display=\'none\'"></td>'
            . '<td class="k-killer"><a href="/character/' . $k['finalcharacterid'] . '" onclick="event.stopPropagation()">' . e($k['finalname']) . '</a></td>'
            . '<td class="k-ship">' . e($k['finalshipname']) . '</td>'
            . '<td class="k-time" title="' . date('Y-m-d H:i:s', $ts) . '">' . time_ago($ts) . '</td></tr>';
    endforeach;
    return $out;
}

$showList = ($tab === 'kills') ? $kills : $losses;

ob_start();
?>
<div class="corp-profile">
    <div class="corp-logo"><img src="<?= corp_logo($corpID, 128) ?>" onerror="this.style.display='none'"></div>
    <div class="corp-info">
        <h1><?= e($corpName) ?> <span class="corp-ticker">[<?= e($ticker) ?>]</span></h1>
        <div class="corp-meta">
            <span class="corp-members"><?= number_format($memberCount) ?> members</span>
            <?php if ($ceoID): ?><span style="color:var(--text-dim)">CEO: <a href="/character/<?= $ceoID ?>"><?= e($ceoName) ?></a></span><?php endif; ?>
            <span style="color:var(--text-dim)"><a href="/corporation/<?= $corpID ?>/stats">Statistics &raquo;</a></span>
        </div>
        <div class="corp-stats">
            <div class="stat"><div class="stat-label">Kills</div><div class="stat-num" style="color:var(--accent)"><?= number_format($nKills) ?></div></div>
            <div class="stat"><div class="stat-label">Losses</div><div class="stat-num" style="color:var(--danger)"><?= number_format($nLosses) ?></div></div>
            <div class="stat"><div class="stat-label">Kill ratio</div><div class="stat-num"><?= $ratio ?> : 1</div></div>
            <div class="stat"><div class="stat-label">Efficiency</div><div class="stat-num"><?= $eff ?>%</div></div>
        </div>
    </div>
</div>

<div class="tabs">
    <a href="/corporation/<?= $corpID ?>?tab=kills" class="tab <?= $tab==='kills'?'active':'' ?>">Kills <span class="tab-count"><?= number_format($nKills) ?></span></a>
    <a href="/corporation/<?= $corpID ?>?tab=losses" class="tab <?= $tab==='losses'?'active':'' ?>">Losses <span class="tab-count"><?= number_format($nLosses) ?></span></a>
</div>

<table class="kill-table">
    <thead><tr>
        <th class="k-icon"></th><th class="k-system">System</th>
        <th class="k-victim">Victim</th><th class="k-ship">Ship</th>
        <th class="k-value">Damage</th><th class="k-icon"></th>
        <th class="k-killer">Final Blow</th><th class="k-ship">Ship</th>
        <th class="k-time">When</th>
    </tr></thead>
    <tbody>
    <?= render_kill_rows($showList) ?>
    <?php if (empty($showList)): ?>
        <tr><td colspan="9" class="empty">No <?= $tab ?> recorded for this corporation</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
render_layout($corpName, 'kills', $content);
