<?php
require_once __DIR__ . '/../layout.php';

$killID = intval($id ?? 0);
if (!$killID) { redirect('/kills'); return; }

$xml = api_get('/char/KillDetail.xml.aspx?killid=' . $killID);
if (!$xml || !$xml->result || !$xml->result->row) {
    ob_start();
    ?>
    <div style="text-align:center;padding:60px 0">
        <h2 style="color:var(--text-bright);margin-bottom:8px">Kill #<?= $killID ?> not found</h2>
        <p style="color:var(--text-dim);margin-bottom:16px">This killmail may have been deleted or does not exist.</p>
        <a href="/kills" style="color:var(--accent2)">&laquo; Back to Killboard</a>
    </div>
    <?php
    render_layout('Kill not found', 'kills', ob_get_clean());
    return;
}

$k = $xml->result->row;
$ts = filetime_to_unix((string)$k['killtime']);
$sec = (float)$k['security'];
$victimDmg = (int)$k['victimdamagetaken'];
$finalDmg = (int)$k['finaldamagedone'];
$dmgPct = $victimDmg > 0 ? round($finalDmg / $victimDmg * 100, 1) : 0;

// ---- parse killBlob into slot buckets ----
$blob = (string)$k['killblob'];
$items = [];
if ($blob && strlen($blob) > 10) {
    $doc = new DOMDocument();
    @$doc->loadXML($blob);
    foreach ($doc->getElementsByTagName('i') as $el) {
        $items[] = [
            't' => (int)$el->getAttribute('t'),
            'f' => (int)$el->getAttribute('f'),
            'q' => max(1, (int)$el->getAttribute('q')),
            's' => (int)$el->getAttribute('s'),
            'd' => (int)$el->getAttribute('d'),
            'x' => (int)$el->getAttribute('x'),
        ];
    }
}

$itemNames = [];
$itemPrice = [];
$ids = [];
foreach ($items as $it) $ids[] = $it['t'];
// hull itself is also priced (added below as a "Ship" entry)
$ids[] = (int)$k['victimshiptypeid'];
$ids = array_unique(array_filter($ids));
if ($ids) {
    $nxml = api_get('/char/Resolve.xml.aspx?ids=' . implode(',', $ids));
    if ($nxml && $nxml->result && $nxml->result->names)
        foreach ($nxml->result->names->row as $nr) {
            $itemNames[(int)$nr['id']] = (string)$nr['name'];
            if (isset($nr['price']) && $nr['price'] !== '')
                $itemPrice[(int)$nr['id']] = (float)$nr['price'];
        }
}
// price fallback helper (also accepts basePrice — but Resolve already falls back server-side)
$priceOf = function($typeID) use (&$itemPrice) { return $itemPrice[$typeID] ?? 0.0; };

// Group blob items into slot buckets, keeping per-slot ascending flag order
// (High 27→34, Mid 19→26, Low 11→18, Rig 92+, ...). A stack may be split between
// dropped (d) and destroyed (x); keep one row with both counts for correct ISK math.
$slots = [];
foreach ($items as $it) {
    if ($it['t'] == (int)$k['victimshiptypeid'] && $it['f'] == 0) continue; // hull added separately
    $slot = get_slot_name($it['f']);
    $slots[$slot][] = $it;
}
uksort($slots, function($a, $b) { return slot_sort_order($a) - slot_sort_order($b); });
foreach ($slots as &$row) {
    usort($row, function($a, $b) { return $a['f'] - $b['f']; });
}
unset($row);

// Grand ISK totals (zkillboard-style): ship hull is always destroyed.
$totDropped = 0.0; $totDestroyed = 0.0;
$hullValue = $priceOf((int)$k['victimshiptypeid']);
$totDestroyed += $hullValue;
foreach ($slots as $slotItems)
    foreach ($slotItems as $it) {
        $p = $priceOf($it['t']);
        $totDropped   += $p * $it['d'];
        $totDestroyed += $p * $it['x'];
    }
$totAll = $totDropped + $totDestroyed;

// ---- SVG map helpers ----
function svg_axes(&$nodes, $pad = 12) {
    $xs = array_column($nodes, 'x'); $zs = array_column($nodes, 'z');
    $minX = min($xs); $maxX = max($xs); $minZ = min($zs); $maxZ = max($zs);
    if (($maxX - $minX) < 1e-3) { $maxX += 1; $minX -= 1; }
    if (($maxZ - $minZ) < 1e-3) { $maxZ += 1; $minZ -= 1; }
    $w = 150.0; $h = 90.0;
    $sx = ($w - 2 * $pad) / ($maxX - $minX);
    $sy = ($h - 2 * $pad) / ($maxZ - $minZ);
    foreach ($nodes as &$n) {
        $n['px'] = $pad + ($n['x'] - $minX) * $sx;
        $n['py'] = $pad + ($n['z'] - $minZ) * $sy;
    }
    return $nodes;
}
function sec_fill($s) { $s = (float)$s; if ($s>=0.5) return '#4caf50'; if ($s>=0) return '#ffeb3b'; if ($s>=-0.5) return '#ff9800'; if ($s>=-0.8) return '#f44336'; return '#b71c1c'; }
function render_minimap($nodes, $links, $focus, $w = 172, $h = 100) {
    if (!$nodes) return '';
    $nodes = svg_axes($nodes, 10);
    $out = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" style="width:100%;height:auto;background:#0b0f16;border-radius:4px">';
    $set = array_flip(array_column($nodes, 'id'));
    foreach ($links as $l) {
        if (!isset($set[$l['from']]) || !isset($set[$l['to']])) continue;
        $a = null; $b = null;
        foreach ($nodes as $n) { if ($n['id']==$l['from']) $a=$n; if ($n['id']==$l['to']) $b=$n; }
        if ($a && $b) $out .= '<line x1="'.$a['px'].'" y1="'.$a['py'].'" x2="'.$b['px'].'" y2="'.$b['py'].'" stroke="#2a3344" stroke-width="0.5"/>';
    }
    foreach ($nodes as $n) {
        $isFocus = ($focus && $n['id'] == $focus);
        $fill = $isFocus ? '#ff5252' : sec_fill($n['sec']);
        $r = $isFocus ? 3 : 1.8;
        $out .= '<circle cx="'.$n['px'].'" cy="'.$n['py'].'" r="'.$r.'" fill="'.$fill.'">';
        $out .= '<title>'.e($n['name']).' ('.number_format((float)$n['sec'],1).')</title></circle>';
        if ($isFocus && !empty($n['name']))
            $out .= '<text x="'.$n['px'].'" y="'.($n['py']-4).'" font-size="6" fill="#fff" text-anchor="middle">'.e($n['name']).'</text>';
    }
    return $out . '</svg>';
}

// ---- load map geometry ----
$mapSys = $mapCon = $mapReg = [];
if ((int)$k['systemid']) {
    $mxml = api_get('/server/MapData.xml.aspx?systemid=' . $k['systemid']);
    if ($mxml && $mxml->result) {
        $cur = $mxml->result->system;
        foreach ($mxml->result->systems->row ?? [] as $r)
            $mapSys[] = ['id'=>(int)$r['id'],'name'=>(string)$r['name'],'sec'=>(string)$r['security'],'x'=>(float)$r['x'],'z'=>(float)$r['z']];
        foreach ($mxml->result->constellations->row ?? [] as $r)
            $mapCon[] = ['id'=>(int)$r['id'],'name'=>(string)$r['name'],'sec'=>0.5,'x'=>(float)$r['x'],'z'=>(float)$r['z']];
        $jumps = [];
        foreach ($mxml->result->jumps->row ?? [] as $r)
            $jumps[] = ['from'=>(int)$r['from'],'to'=>(int)$r['to']];
        $sysFocus = (int)$k['systemid'];
        $conFocus = $cur ? (int)$cur['constellationid'] : 0;
        $mapSysSys = $mapSys;
        $mapSysCon = $mapCon;
        // region map: scatter current region's systems is potentially huge; reuse constellation systems + focus is fine here
        $mapSysReg = array_slice($mapSys, 0, 900);
    }
}

ob_start();
?>
<style>
.kill-layout { display:grid; grid-template-columns:1fr 260px; gap:16px; align-items:start; }
.kill-main { min-width:0; }
.kill-rail { display:flex; flex-direction:column; gap:12px; }
.map-card { background:var(--bg-card); border:1px solid var(--border); border-radius:6px; padding:8px; }
.map-card .map-title { font-size:11px; color:var(--text-dim); text-transform:uppercase; letter-spacing:.4px; margin-bottom:4px; }
.map-card .map-cap { font-size:11px; color:var(--text); margin-bottom:4px; }
.rail-link { font-size:11px; color:var(--accent2); }
</style>

<a href="/kills" style="font-size:12px;color:var(--text-dim);display:inline-block;margin-bottom:8px">&laquo; Killboard</a>

<div class="kill-detail-hero">
    <div class="kill-victim">
        <div class="victim-portrait">
            <img src="<?= char_portrait($k['victimid'], 128) ?>"
                 onerror="this.src='<?= ship_icon($k['victimshiptypeid'], 128) ?>'">
        </div>
        <div class="victim-info">
            <h2><?= e($k['victimname'] ?: 'Unknown') ?></h2>
            <div class="victim-corp">
                <?php if ((int)$k['victimcorpid']): ?>
                <img src="<?= corp_logo($k['victimcorpid'], 32) ?>" onerror="this.style.display='none'">
                <a href="/corporation/<?= $k['victimcorpid'] ?>"><?= e($k['victimcorpname'] ?: 'Unknown') ?></a>
                <span style="color:#778899">[<?= e($k['victimticker']) ?>]</span>
                <?php endif; ?>
                <?php if (!empty($k['victimalliancename'])): ?>
                <span style="color:#556677">/</span>
                <span style="color:#556677"><?= e($k['victimalliancename']) ?></span>
                <?php endif; ?>
            </div>
            <div class="victim-ship">
                <img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" onerror="this.style.display='none'">
                <?= e($k['victimshipname']) ?>
            </div>
            <div class="victim-meta">
                <strong>System:</strong>
                <a href="/system/<?= $k['systemid'] ?>"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span> <?= e($k['systemname']) ?></a><br>
                <strong>Region:</strong> <?= e($k['regionname']) ?: e($k['constellationname']) ?><br>
                <strong>Time:</strong> <?= date('Y-m-d H:i:s', $ts) ?><br>
                <strong>Damage Taken:</strong> <?= number_format($victimDmg) ?>
            </div>
        </div>
    </div>

    <div class="kill-stats">
        <div class="stat-row"><span class="stat-label">Final Blow</span>
            <span class="stat-value"><a href="/character/<?= $k['finalid'] ?>"><?= e($k['finalname'] ?: 'Unknown') ?></a></span></div>
        <div class="stat-row"><span class="stat-label">Final Corp</span>
            <span class="stat-value" style="font-size:11px"><?= e($k['finalcorpname']) ?></span></div>
        <div class="stat-row"><span class="stat-label">Final Ship</span>
            <span class="stat-value" style="font-size:12px"><?= e($k['finalshipname']) ?></span></div>
        <div class="stat-row"><span class="stat-label">Weapon</span>
            <span class="stat-value" style="font-size:11px"><img src="<?= ship_type_icon($k['finalweapontypeid'],16) ?>" width="16" height="16" style="vertical-align:middle" onerror="this.style.display='none'"> <?= e($k['finalweaponname']) ?></span></div>
        <div class="stat-row"><span class="stat-label">Final Damage</span>
            <span class="stat-value" style="color:var(--warn)"><?= number_format($finalDmg) ?></span></div>
        <div class="stat-row"><span class="stat-label">Total Damage</span>
            <span class="stat-value" style="color:var(--danger)"><?= number_format($victimDmg) ?></span></div>
        <div class="stat-row"><span class="stat-label">Security</span>
            <span class="stat-value" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 2) ?></span></div>
    </div>
</div>

<div class="kill-layout">
  <div class="kill-main">
    <div class="ship-render-wrap">
        <img src="<?= ship_icon($k['victimshiptypeid'], 256) ?>" alt="<?= e($k['victimshipname']) ?>" onerror="this.style.display='none'">
    </div>

    <?php if (!empty($slots) || $hullValue > 0): ?>
    <div class="fit-visual">
        <?php foreach (['High','Mid','Low','Rig','Subsystem','Drone Bay','Cargo','Other'] as $slotType):
            if (empty($slots[$slotType])) continue; ?>
        <div class="fit-slot-row">
            <div class="fit-slot-label"><?= $slotType ?></div>
            <div class="fit-slot-icons">
            <?php foreach ($slots[$slotType] as $it):
                $nm = $itemNames[$it['t']] ?? 'Unknown';
                $cls = ($it['d'] > 0 && $it['x'] == 0) ? 'dropped' : (($it['d'] > 0) ? 'partial' : 'destroyed');
                $qtyNote = ($it['d'] > 0 && $it['x'] > 0) ? ' (D'.$it['d'].'/X'.$it['x'].')' : ($it['q'] > 1 ? ' x'.$it['q'] : '');
                ?>
                <div class="fit-slot <?= $cls ?>" title="<?= e($nm . $qtyNote) ?>">
                    <img src="<?= ship_type_icon($it['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section-title">Items</div>
    <table class="items-table">
        <thead><tr><th></th><th>Item</th><th>Qty</th><th>Dropped</th><th>Destroyed</th><th style="text-align:right">Value</th></tr></thead>
        <tbody>
        <tr>
            <td style="width:32px"><img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
            <td><?= e($k['victimshipname']) ?: e($k['victimshiptypeid']) ?></td>
            <td>1</td>
            <td class="dropped">0</td>
            <td class="destroyed">1</td>
            <td class="destroyed" style="text-align:right"><?= isk_compact($hullValue) ?></td>
        </tr>
        <?php foreach ($slots as $slotName => $slotItems): ?>
        <tr><td colspan="6" class="slot-header"><?= e($slotName) ?> Slots</td></tr>
        <?php foreach ($slotItems as $it):
            $nm = $itemNames[$it['t']] ?? 'Unknown #'.$it['t'];
            $qty = max(1, $it['q']);
            $val = $priceOf($it['t']);
            $isSplit = ($it['d'] > 0 && $it['x'] > 0);
            $rowCls = ($it['d'] > 0 && $it['x'] == 0) ? 'dropped' : (($it['x'] > 0) ? 'destroyed' : 'destroyed');
            ?>
        <tr>
            <td style="width:32px"><img src="<?= ship_type_icon($it['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
            <td><?= e($nm) ?></td>
            <td><?= $qty ?></td>
            <td class="dropped"><?= $it['d'] ?></td>
            <td class="destroyed"><?= $it['x'] ?></td>
            <td style="text-align:right" class="<?= $rowCls ?>">
                <?php if ($isSplit): ?>
                    <span class="dropped"><?= isk_compact($val * $it['d']) ?></span> /
                    <span class="destroyed"><?= isk_compact($val * $it['x']) ?></span>
                <?php else: ?>
                    <?= isk_compact($val * max($it['d'], $it['x'])) ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endforeach; ?>
        <tr class="totals-row">
            <td colspan="3" style="text-align:right"><strong>Total</strong></td>
            <td class="dropped"><strong><?= isk_compact($totDropped) ?></strong></td>
            <td class="destroyed"><strong><?= isk_compact($totDestroyed) ?></strong></td>
            <td style="text-align:right"><strong><?= isk_compact($totAll) ?></strong></td>
        </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($victimDmg > 0): ?>
    <div class="damage-bar-wrap">
        <div class="damage-bar-label">Damage Distribution</div>
        <div class="damage-bar">
            <div class="damage-bar-seg" style="width:<?= $dmgPct ?>%;background:var(--accent)" title="<?= e($k['finalname']) ?>: <?= number_format($finalDmg) ?> (<?= $dmgPct ?>%)"></div>
            <div class="damage-bar-seg" style="width:<?= max(0,100-$dmgPct) ?>%;background:var(--danger)" title="Others"></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="attacker-section">
        <div class="section-title">Attackers</div>
        <div class="attacker-row final-blow">
            <img class="atk-portrait" src="<?= char_portrait($k['finalid'], 64) ?>" width="48" height="48"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22><rect fill=%22%23111820%22 width=%2248%22 height=%2248%22/><text x=%2224%22 y=%2228%22 text-anchor=%22middle%22 fill=%22%23556%22 font-size=%2216%22>?</text></svg>'">
            <div class="atk-info">
                <div class="atk-name"><a href="/character/<?= $k['finalid'] ?>"><?= e($k['finalname'] ?: 'Unknown') ?></a></div>
                <div class="atk-ship">
                    <img src="<?= ship_icon($k['finalshiptypeid'], 16) ?>" width="16" height="16" style="vertical-align:middle;margin-right:2px" onerror="this.style.display='none'">
                    <?= e($k['finalshipname']) ?>
                </div>
                <div class="atk-corp"><?= e($k['finalcorpname']) ?></div>
            </div>
            <div class="atk-dmg">
                <div class="atk-dmg-val"><?= number_format($finalDmg) ?></div>
                <div class="atk-dmg-pct"><?= $dmgPct ?>%</div>
            </div>
        </div>
        <div style="font-size:10px;color:var(--text-dim);padding:4px 10px;text-transform:uppercase;letter-spacing:.3px;font-weight:600">Final Blow</div>
    </div>

    <?php
    // EFT fitting
    $eftLines = [];
    foreach ($slots as $slotName => $slotItems)
        foreach ($slotItems as $it) {
            $nm = $itemNames[$it['t']] ?? null;
            if ($nm) $eftLines[] = $nm . ($it['q'] > 1 ? ' x' . $it['q'] : '');
        }
    $eft = '[' . e($k['victimshipname']) . ', ' . e($k['victimname']) . "'s " . e($k['victimshipname']) . ']' . "\n" . implode("\n", $eftLines);
    $kmXml = api_get('/char/KillMail.xml.aspx?killid=' . $killID);
    $killmailText = ($kmXml && $kmXml->result) ? (string)($kmXml->result->killmail ?? '') : '';
    ?>
    <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap">
        <details style="background:var(--bg-card);border:1px solid var(--border);border-radius:6px;padding:12px;flex:1;min-width:280px">
            <summary style="cursor:pointer;color:var(--text-bright);font-size:13px;font-weight:600">EFT Fitting</summary>
            <pre style="margin-top:8px;padding:8px;background:#0d1117;border-radius:4px;font-size:11px;color:var(--text);overflow-x:auto;white-space:pre-wrap"><?= e($eft) ?></pre>
        </details>
        <details style="background:var(--bg-card);border:1px solid var(--border);border-radius:6px;padding:12px;flex:1;min-width:280px">
            <summary style="cursor:pointer;color:var(--text-bright);font-size:13px;font-weight:600">Original Killmail</summary>
            <pre style="margin-top:8px;padding:8px;background:#0d1117;border-radius:4px;font-size:11px;color:var(--text);overflow-x:auto;white-space:pre-wrap"><?= e($killmailText) ?></pre>
        </details>
    </div>

    <?php
    // Related kills
    $related = [];
    $relXml = api_get('/char/RelatedKills.xml.aspx?killid=' . $killID);
    if ($relXml && $relXml->result && $relXml->result->related)
        foreach ($relXml->result->related->row as $r) $related[] = $r;
    ?>
    <?php if ($related): ?>
    <div class="section-title">Related Kills (same system, ±24h)</div>
    <table class="kill-table" style="font-size:12px">
        <thead><tr><th>#</th><th>Victim</th><th>Ship</th><th>Damage</th><th>Final Blow</th><th>Ship</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($related as $rk):
            $rts = filetime_to_unix((string)$rk['killtime']); ?>
        <tr class="kill-row" onclick="location.href='/kill/<?= $rk['killid'] ?>'">
            <td><a href="/kill/<?= $rk['killid'] ?>"><?= $rk['killid'] ?></a></td>
            <td><a href="/character/<?= $rk['victimid'] ?>"><?= e($rk['victimname'] ?: 'Unknown') ?></a></td>
            <td><?= e($rk['victimshipname']) ?></td>
            <td><?= number_format((int)$rk['victimdamagetaken']) ?></td>
            <td><a href="/character/<?= $rk['finalid'] ?>"><?= e($rk['finalname'] ?: 'Unknown') ?></a></td>
            <td><?= e($rk['finalshipname']) ?></td>
            <td title="<?= date('Y-m-d H:i:s', $rts) ?>"><?= time_ago($rts) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="kill-rail">
    <?php if ($mapSys): ?>
    <div class="map-card">
        <div class="map-title">System</div>
        <div class="map-cap"><a class="rail-link" href="/system/<?= (int)$k['systemid'] ?>"><?= e($k['systemname']) ?></a> — <?= e($k['constellationname']) ?></div>
        <?= render_minimap($mapSysSys ?? $mapSys, $jumps ?? [], (int)$k['systemid']) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($mapSysCon)): ?>
    <div class="map-card">
        <div class="map-title">Constellation</div>
        <div class="map-cap"><?= e($k['constellationname']) ?> in <?= e($k['regionname']) ?></div>
        <?= render_minimap($mapSysCon, [], $conFocus) ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($mapSysCon)): ?>
    <div class="map-card">
        <div class="map-title">Region</div>
        <div class="map-cap"><?= e($k['regionname']) ?> (<?= count($mapSysCon) ?> constellations)</div>
        <?= render_minimap($mapSysCon, [], $conFocus) ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
render_layout($k['victimshipname'] . ' | ' . $k['victimname'] . ' | Killmail', 'kills', $content);
