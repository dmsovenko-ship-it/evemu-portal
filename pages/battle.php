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

// Resolve names for all pilots & corps in the battle.
$charIDs = []; $corpIDs = [];
foreach ($battle as $k) {
    $vc = (int)$k['victimcharacterid']; $fc = (int)$k['finalcharacterid'];
    $vco = (int)$k['victimcorporationid']; $fco = (int)$k['finalcorporationid'];
    if ($vc > 0) $charIDs[$vc] = 1; if ($fc > 0) $charIDs[$fc] = 1;
    if ($vco > 0) $corpIDs[$vco] = 1; if ($fco > 0) $corpIDs[$fco] = 1;
}
$names = [];
$resolveIDs = array_merge(array_keys($charIDs), array_keys($corpIDs));
if ($resolveIDs) {
    $nx = api_get('/char/Resolve.xml.aspx?ids=' . implode(',', $resolveIDs));
    if ($nx && $nx->result && $nx->result->names)
        foreach ($nx->result->names->row as $nr)
            $names[(int)$nr['id']] = (string)$nr['name'];
}

// aggregate by party (corp buckets)
$victimCorps = [];   // corpID => kills
$killerCorps = [];
foreach ($battle as $k) {
    $vc = (int)$k['victimcorporationid'];
    $fc = (int)$k['finalcorporationid'];
    if ($vc > 0) $victimCorps[$vc] = ($victimCorps[$vc] ?? 0) + 1;
    if ($fc > 0) $killerCorps[$fc] = ($killerCorps[$fc] ?? 0) + 1;
}
arsort($victimCorps); arsort($killerCorps);

$start = filetime_to_unix((int)$battle[0]['killtime']);
$end   = filetime_to_unix((int)$battle[count($battle)-1]['killtime']);
$sec   = (float)$battle[0]['finalsecuritystatus'];
$dmg = 0; foreach ($battle as $k) $dmg += (int)$k['victimdamagetaken'];

ob_start();
?>
<style>
.battle-hero { display:flex; gap:20px; align-items:stretch; margin-bottom:18px; flex-wrap:wrap; }
.battle-hero .bx { flex:1; min-width:220px; background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:14px; }
.battle-hero h1 { font-size:20px; color:var(--text-bright); margin-bottom:4px; }
.battle-hero .sub { color:var(--text-dim); font-size:13px; margin-bottom:8px; }
.party-block h3 { font-size:12px; text-transform:uppercase; letter-spacing:.4px; margin-bottom:8px; }
.party-block.victims h3 { color:#ff6b6b; }
.party-block.killers h3 { color:#4ecdc4; }
.party-row { display:flex; justify-content:space-between; font-size:13px; padding:2px 0; border-bottom:1px dashed var(--border); }
.party-row a { color:var(--accent2); }
.party-row .n { color:var(--text-dim); }
.stat-line { font-size:13px; color:var(--text); margin:3px 0; }
.stat-line b { color:var(--text-bright); }
</style>

<a href="/battles" style="font-size:12px;color:var(--text-dim);display:inline-block;margin-bottom:8px">&laquo; Battles</a>

<div class="battle-hero">
    <div class="bx" style="flex:2">
        <h1>Battle &mdash; <?= e($battle[0]['solarsystemname']) ?></h1>
        <div class="sub">
            <span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec,1) ?></span> sec
            &middot; <a href="/system/<?= $sysID ?>"><?= e($battle[0]['solarsystemname']) ?></a>
            &middot; <?= date('Y-m-d H:i', $start) ?> &ndash; <?= date('H:i', $end) ?> (<?= time_ago($end) ?>)
        </div>
        <div class="stat-line"><b><?= count($battle) ?></b> ships destroyed</div>
        <div class="stat-line">Total damage: <b><?= number_format($dmg) ?></b></div>
        <div class="stat-line">Duration: <b><?= max(1, $end - $start) ?>s</b></div>
    </div>
    <div class="bx party-block victims">
        <h3>Losses</h3>
        <?php foreach ($victimCorps as $cid => $cnt): ?>
            <div class="party-row"><span><a href="/corporation/<?= $cid ?>"><?= e($names[$cid] ?? '#'.$cid) ?></a></span><span class="n"><?= $cnt ?> ship<?= $cnt>1?'s':'' ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="bx party-block killers">
        <h3>Killers</h3>
        <?php foreach ($killerCorps as $cid => $cnt): ?>
            <div class="party-row"><span><a href="/corporation/<?= $cid ?>"><?= e($names[$cid] ?? '#'.$cid) ?></a></span><span class="n"><?= $cnt ?> kill<?= $cnt>1?'s':'' ?></span></div>
        <?php endforeach; ?>
    </div>
</div>

<div class="section-title">Kills in this battle</div>
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
        <td class="k-ship" style="color:var(--text-dim)"><?= e($names[(int)$k['victimcorporationid']] ?? '') ?></td>
        <td class="k-value"><?= number_format((int)$k['victimdamagetaken']) ?></td>
        <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname'] ?: 'Unknown') ?></a></td>
        <td class="k-ship"><img src="<?= ship_icon($k['finalshiptypeid'],24) ?>" width="24" height="24" style="vertical-align:middle" onerror="this.style.display='none'"> <?= e($k['finalshipname']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
render_layout('Battle | ' . $battle[0]['solarsystemname'], 'kills', $content);
