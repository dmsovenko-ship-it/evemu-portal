<?php
require_once __DIR__ . '/../layout.php';

// URL param is "systemID-firstKillID" (the first kill of a battle in the list).
$parts = preg_split('/[-_]/', (string)($id ?? ''));
$sysID = (int)($parts[0] ?? 0);
$firstKill = (int)($parts[1] ?? 0);
if (!$sysID || !$firstKill) { redirect('/battles'); return; }

// Same grouping as the battles list: same system, gaps > 10 min split battles.
$BATTLE_WINDOW = 600;

$xml = api_get('/char/AllKills.xml.aspx');
$rows = [];
if ($xml && $xml->result && $xml->result->kills)
    foreach ($xml->result->kills->row as $r) $rows[] = $r;

$sysRows = [];
foreach ($rows as $r)
    if ((int)$r['solarsystemid'] === $sysID) $sysRows[] = $r;

usort($sysRows, function($a, $b){ return (int)$a['killtime'] <=> (int)$b['killtime']; });

$battle = [];
$cur = []; $prevTs = null;
foreach ($sysRows as $k) {
    $ts = (int)$k['killtime'];
    if ($prevTs !== null && ($ts - $prevTs) > $BATTLE_WINDOW * 10000000) {
        if ($cur && (int)$cur[0]['killid'] === $firstKill) { $battle = $cur; break; }
        $cur = [];
    }
    $cur[] = $k;
    $prevTs = $ts;
}
if (!$battle && $cur && (int)$cur[0]['killid'] === $firstKill) $battle = $cur;

if (!$battle) {
    ob_start();
    ?>
    <div style="text-align:center;padding:60px 0">
        <h2 style="color:var(--text-bright);margin-bottom:8px">Battle not found</h2>
        <a href="/battles" style="color:var(--accent2)">&laquo; Back to Battles</a>
    </div>
    <?php
    render_layout('Battle not found', 'kills', ob_get_clean());
    return;
}

// ---- resolve names for every pilot / corp / alliance in the battle ----
$want = [];
foreach ($battle as $k) {
    foreach (['victimcharacterid','finalcharacterid'] as $a) if ((int)$k[$a]>0) $want[(int)$k[$a]]=1;
    foreach (['victimcorporationid','finalcorporationid'] as $a) if ((int)$k[$a]>0) $want[(int)$k[$a]]=1;
    foreach (['victimallianceid','finalallianceid'] as $a) if ((int)$k[$a]>0) $want[(int)$k[$a]]=1;
}
$names = [];
if ($want) {
    $nx = api_get('/char/Resolve.xml.aspx?ids=' . implode(',', array_keys($want)));
    if ($nx && $nx->result && $nx->result->names)
        foreach ($nx->result->names->row as $nr) $names[(int)$nr['id']] = (string)$nr['name'];
}
$nm = function($id) use ($names) { return $names[(int)$id] ?? ('#' . $id); };

// ---- aggregate ----
// ship-class matrix: rows keyed by class, cols K (kills made by that class) / L (losses of that class)
$classK = [];   // groupName => count (final-blow ship classes = kills)
$classL = [];   // groupName => count (victim ship classes = losses)
$killers = [];  // charID => row (winner side)
$losers  = [];  // charID => row (loser/victim side)
$sec = (float)$battle[0]['finalsecuritystatus'];

foreach ($battle as $k) {
    $gK = (string)($k['finalgroupname'] ?? ''); if ($gK==='') $gK = (string)($k['finalshipname'] ?? 'Unknown');
    $gL = (string)($k['victimgroupname'] ?? ''); if ($gL==='') $gL = (string)($k['victimshipname'] ?? 'Unknown');
    $classK[$gK] = ($classK[$gK] ?? 0) + 1;
    $classL[$gL] = ($classL[$gL] ?? 0) + 1;

    $kid = (int)$k['finalcharacterid'];
    if ($kid > 0) {
        $killers[$kid] = [
            'name' => (string)$k['finalname'], 'type' => (int)$k['finalshiptypeid'], 'ship' => (string)$k['finalshipname'],
            'corp' => (int)$k['finalcorporationid'], 'ally' => (int)$k['finalallianceid'],
            'dmg' => (($killers[$kid]['dmg'] ?? 0) + (int)$k['finaldamagedone']), 'n' => (($killers[$kid]['n'] ?? 0) + 1),
            'link' => (int)$k['killid'],
        ];
    }
    $vid = (int)$k['victimcharacterid'];
    if ($vid > 0) {
        $losers[$vid] = [
            'name' => (string)$k['victimname'], 'type' => (int)$k['victimshiptypeid'], 'ship' => (string)$k['victimshipname'],
            'corp' => (int)$k['victimcorporationid'], 'ally' => (int)$k['victimallianceid'],
            'dmg' => (($losers[$vid]['dmg'] ?? 0) + (int)$k['victimdamagetaken']), 'n' => (($losers[$vid]['n'] ?? 0) + 1),
            'link' => (int)$k['killid'],
        ];
    }
}
// order by damage
uasort($killers, function($a,$b){ return $b['dmg'] <=> $a['dmg']; });
uasort($losers,  function($a,$b){ return $b['dmg'] <=> $a['dmg']; });

$classRows = array_keys($classK + $classL);
natcasesort($classRows);
$totalK = array_sum($classK); $totalL = array_sum($classL);
$start = filetime_to_unix((int)$battle[0]['killtime']);
$end   = filetime_to_unix((int)$battle[count($battle)-1]['killtime']);
$dmg = 0; foreach ($battle as $k) $dmg += (int)$k['victimdamagetaken'];

ob_start();
?>
<style>
.bx-scroll { overflow-x:auto; }
.battle-title { font-size:20px; color:var(--text-bright); margin:0 0 2px; }
.battle-title small { font-size:12px; color:var(--text-dim); font-weight:400; }
.class-matrix { border-collapse:collapse; min-width:360px; }
.class-matrix th, .class-matrix td { border:1px solid var(--border); padding:3px 10px; font-size:12px; text-align:center; }
.class-matrix th { background:var(--bg-card); color:var(--text-dim); text-transform:uppercase; font-size:10px; }
.class-matrix td.cl { text-align:left; color:var(--text); }
.class-matrix .tot td { font-weight:700; background:var(--bg-card); color:var(--text-bright); }
.side-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin:14px 0; }
@media (max-width:900px){ .side-grid{grid-template-columns:1fr;} }
.side { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:12px; min-width:0; }
.side h3 { font-size:13px; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid var(--border); }
.side.losers h3 { color:#ff6b6b; } .side.killers h3 { color:#4ecdc4; }
.side .cnt { font-weight:400; color:var(--text-dim); font-size:11px; }
.pilot { display:flex; align-items:center; gap:8px; padding:4px 0; border-bottom:1px dashed var(--border); }
.pilot img.ps { width:32px; height:32px; border-radius:3px; flex:0 0 32px; }
.pilot .pi { min-width:0; }
.pilot .pn a { color:var(--text-bright); font-weight:600; font-size:13px; }
.pilot .pn a:hover { color:var(--accent2); }
.pilot .pship { font-size:11px; color:var(--text-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pilot .pc { font-size:10px; color:#667788; }
.pilot .pd { margin-left:auto; text-align:right; font-size:11px; color:var(--text); white-space:nowrap; }
.pilot .pd b { color:var(--warn); }
/* side colouring: killed ships red (click → its killmail), survivors green */
.pilot.dead img.ps, .pilot.dead .pship img { box-shadow:0 0 0 1px #ff5252; border-radius:3px; }
.pilot.alive img.ps, .pilot.alive .pship img { box-shadow:0 0 0 1px #2ecc71; border-radius:3px; }
.pilot.dead .pn a { color:#ff7b7b; }
.pilot.alive .pn a { color:#5ee08a; }
.pilot .killchip { font-size:10px; color:var(--text-dim); }
</style>

<a href="/battles" style="font-size:12px;color:var(--text-dim);display:inline-block;margin-bottom:8px">&laquo; Battles</a>

<div class="battle-title">Battle in <a href="/system/<?= $sysID ?>"><?= e($battle[0]['solarsystemname']) ?></a><small> &middot; <?= date('Y-m-d H:i', $start) ?> &ndash; <?= date('H:i', $end) ?> &middot; sec <?= number_format($sec,1) ?></small></div>

<div class="bx-scroll">
<table class="class-matrix">
  <thead><tr><th class="cl">Ship class</th><th>K</th><th>L</th></tr></thead>
  <tbody>
  <?php foreach ($classRows as $cls): ?>
    <tr><td class="cl"><?= e($cls) ?></td><td><?= (int)($classK[$cls]??0) ?></td><td><?= (int)($classL[$cls]??0) ?></td></tr>
  <?php endforeach; ?>
  <tr class="tot"><td>Totals</td><td><?= $totalK ?></td><td><?= $totalL ?></td></tr>
  </tbody>
</table>
</div>

<div class="side-grid">
  <div class="side killers">
    <h3>Killers (winners) <span class="cnt">Pilots: <?= count($killers) ?>, Ships: <?= $totalK ?></span></h3>
    <?php foreach ($killers as $pID => $p): ?>
      <div class="pilot alive" title="survived the battle">
        <a href="/kill/<?= $p['link'] ?>"><img class="ps" src="<?= char_portrait($pID, 64) ?>" onerror="this.src='<?= ship_icon($p['type'],32) ?>'"></a>
        <div class="pi">
          <div class="pn"><a href="/character/<?= $pID ?>"><?= e($p['name'] ?: 'Unknown') ?></a></div>
          <div class="pship"><a href="/kill/<?= $p['link'] ?>"><img src="<?= ship_icon($p['type'],24) ?>" width="16" height="16" style="vertical-align:middle" onerror="this.style.display='none'"></a> <?= e($p['ship']) ?></div>
          <div class="pc"><?= e($nm($p['corp'])) ?><?php if ($p['ally']>0 && $names[(int)$p['ally']]) echo ' / '.e($names[(int)$p['ally']]); ?></div>
        </div>
        <div class="pd"><b><?= number_format($p['dmg']) ?></b><br><a href="/kill/<?= $p['link'] ?>" class="killchip">kill &rarr;</a></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="side losers">
    <h3>Losses (destroyed) <span class="cnt">Pilots: <?= count($losers) ?>, Ships: <?= $totalL ?></span></h3>
    <?php foreach ($losers as $pID => $p): ?>
      <div class="pilot dead" title="ship destroyed">
        <a href="/kill/<?= $p['link'] ?>"><img class="ps" src="<?= ship_icon($p['type'],32) ?>" onerror="this.style.display='none'"></a>
        <div class="pi">
          <div class="pn"><a href="/character/<?= $pID ?>"><?= e($p['name'] ?: 'Unknown') ?></a></div>
          <div class="pship"><a href="/kill/<?= $p['link'] ?>"><?= e($p['ship']) ?></a></div>
          <div class="pc"><?= e($nm($p['corp'])) ?><?php if ($p['ally']>0 && $names[(int)$p['ally']]) echo ' / '.e($names[(int)$p['ally']]); ?></div>
        </div>
        <div class="pd"><b><?= number_format($p['dmg']) ?></b><br><a href="/kill/<?= $p['link'] ?>" class="killchip">killmail &rarr;</a></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="section-title">Battle Statistics</div>
<div class="stat-cards" style="justify-content:flex-start">
  <div class="stat-card"><div class="stat-num"><?= $totalL ?></div><div class="stat-label">Ships lost</div></div>
  <div class="stat-card"><div class="stat-num" style="color:var(--accent)"><?= $totalK ?></div><div class="stat-label">Ships killed</div></div>
  <div class="stat-card"><div class="stat-num" style="color:var(--warn)"><?= number_format($dmg) ?></div><div class="stat-label">Damage (HP)</div></div>
  <div class="stat-card"><div class="stat-num"><?= max(1, $end - $start) ?>s</div><div class="stat-label">Duration</div></div>
</div>

<div class="section-title">Timeline</div>
<div class="bx-scroll">
<table class="kill-table">
    <thead><tr>
        <th>Time</th><th>Victim</th><th>Ship</th><th>Corp</th><th>Damage</th><th>Final Blow</th><th>Ship</th>
    </tr></thead>
    <tbody>
    <?php foreach ($battle as $k):
        $kts = filetime_to_unix((int)$k['killtime']); ?>
    <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
        <td class="k-time" title="<?= date('Y-m-d H:i:s', $kts) ?>"><?= date('H:i:s', $kts) ?></td>
        <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname'] ?: 'Unknown') ?></a></td>
        <td class="k-ship"><img src="<?= ship_icon($k['victimshiptypeid'],24) ?>" width="24" height="24" style="vertical-align:middle" onerror="this.style.display='none'"> <?= e($k['victimshipname']) ?></td>
        <td class="k-ship" style="color:var(--text-dim)"><?= e($nm($k['victimcorporationid'])) ?></td>
        <td class="k-value"><?= number_format((int)$k['victimdamagetaken']) ?></td>
        <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname'] ?: 'Unknown') ?></a></td>
        <td class="k-ship"><img src="<?= ship_icon($k['finalshiptypeid'],24) ?>" width="24" height="24" style="vertical-align:middle" onerror="this.style.display='none'"> <?= e($k['finalshipname']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php
$content = ob_get_clean();
render_layout('Battle | ' . $battle[0]['solarsystemname'], 'kills', $content);
