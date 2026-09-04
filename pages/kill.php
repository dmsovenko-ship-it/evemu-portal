<?php
require_once __DIR__ . '/../layout.php';

$killID = intval($id ?? 0);
if (!$killID) { redirect('/kills'); return; }

$xml = api_get('/char/AllKills.xml.aspx');
$k = null;
if ($xml && $xml->result && $xml->result->kills)
    foreach ($xml->result->kills->row as $row)
        if ((int)$row['killid'] === $killID) { $k = $row; break; }

if (!$k) {
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

$ts = filetime_to_unix((string)$k['killtime']);
$sec = (float)$k['finalsecuritystatus'];
$blob = (string)$k['killblob'];
$drops = [];
$destroyed = [];
if ($blob && strlen($blob) > 10) {
    $doc = new DOMDocument();
    @$doc->loadXML($blob);
    foreach ($doc->getElementsByTagName('i') as $el) {
        $item = [
            't' => (int)$el->getAttribute('t'),
            'f' => (int)$el->getAttribute('f'),
            'q' => (int)$el->getAttribute('q'),
            's' => (int)$el->getAttribute('s'),
            'd' => (int)$el->getAttribute('d'),
            'x' => (int)$el->getAttribute('x'),
        ];
        if ($item['d'] > 0) $drops[] = $item;
        if ($item['x'] > 0) $destroyed[] = $item;
    }
}

$allTypeIDs = array_unique(array_merge(
    array_column($drops, 't'),
    array_column($destroyed, 't')
));
$itemNames = [];
if (!empty($allTypeIDs)) {
    $ids = implode(',', $allTypeIDs);
    $nxml = api_get('/char/Resolve.xml.aspx?ids=' . $ids);
    if ($nxml && $nxml->result && $nxml->result->names)
        foreach ($nxml->result->names->row as $nr)
            $itemNames[(int)$nr['id']] = (string)$nr['name'];
}

$victimDmg = (int)$k['victimdamagetaken'];
$finalDmg = (int)$k['finaldamagedone'];
$dmgPct = $victimDmg > 0 ? round($finalDmg / $victimDmg * 100, 1) : 0;

$slots = [];
foreach ($destroyed as $item) {
    $slot = get_slot_name($item['f']);
    $slots[$slot][] = ['item' => $item, 'status' => 'destroyed'];
}
foreach ($drops as $item) {
    $slot = get_slot_name($item['f']);
    $slots[$slot][] = ['item' => $item, 'status' => 'dropped'];
}
uksort($slots, function($a, $b) { return slot_sort_order($a) - slot_sort_order($b); });

$totalDestroyed = 0;
$totalDropped = 0;
foreach ($destroyed as $item) $totalDestroyed += ($item['x'] > 0 ? $item['x'] : 1) * ($item['q'] > 0 ? $item['q'] : 1);
foreach ($drops as $item) $totalDropped += ($item['x'] > 0 ? $item['x'] : 1) * ($item['q'] > 0 ? $item['q'] : 1);

$attackerColors = ['#ee4444','#ee8844','#eecc44','#88cc44','#44cc88','#44aacc','#4488ee','#8844ee','#cc44ee','#ee44aa','#ee6644','#ccaa44'];

ob_start();
?>

<a href="/kills" style="font-size:12px;color:var(--text-dim);display:inline-block;margin-bottom:8px">&laquo; Killboard</a>

<div class="kill-detail-hero">
    <div class="kill-victim">
        <div class="victim-portrait">
            <img src="<?= char_portrait($k['victimcharacterid'], 128) ?>"
                 onerror="this.src='<?= ship_icon($k['victimshiptypeid'], 128) ?>'">
        </div>
        <div class="victim-info">
            <h2><?= e($k['victimname'] ?: 'Unknown') ?></h2>
            <div class="victim-corp">
                <?php if ($k['victimcorporationid']): ?>
                <img src="<?= corp_logo($k['victimcorporationid'], 32) ?>" onerror="this.style.display='none'">
                <a href="/corporation/<?= $k['victimcorporationid'] ?>"><?= e($k['victimcorpname'] ?? '') ?></a>
                <?php endif; ?>
                <?php if (!empty($k['victimalliancename'])): ?>
                <span style="color:#556677">/</span>
                <a href="/corporation/<?= $k['victimallianceid'] ?? '' ?>" style="color:#556677"><?= e($k['victimalliancename']) ?></a>
                <?php endif; ?>
            </div>
            <div class="victim-ship">
                <img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" onerror="this.style.display='none'">
                <?= e($k['victimshipname']) ?>
            </div>
            <div class="victim-meta">
                <strong>System:</strong>
                <a href="/system/<?= $k['solarsystemid'] ?? '' ?>">
                    <span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span>
                    <?= e($k['solarsystemname']) ?>
                </a><br>
                <strong>Time:</strong> <?= date('Y-m-d H:i:s', $ts) ?><br>
                <strong>Damage Taken:</strong> <?= number_format($victimDmg) ?>
            </div>
        </div>
    </div>

    <div class="kill-stats">
        <div class="stat-row">
            <span class="stat-label">Final Blow</span>
            <span class="stat-value"><a href="/character/<?= $k['finalcharacterid'] ?>"><?= e($k['finalname'] ?: 'Unknown') ?></a></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Final Ship</span>
            <span class="stat-value"><?= e($k['finalshipname']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Weapon</span>
            <span class="stat-value" style="font-size:11px"><?= e($k['finalweaponname']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Final Damage</span>
            <span class="stat-value" style="color:var(--warn)"><?= number_format($finalDmg) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Total Damage</span>
            <span class="stat-value" style="color:var(--danger)"><?= number_format($victimDmg) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Security</span>
            <span class="stat-value" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 2) ?></span>
        </div>
    </div>
</div>

<div class="ship-render-wrap">
    <img src="<?= ship_icon($k['victimshiptypeid'], 256) ?>" alt="<?= e($k['victimshipname']) ?>" onerror="this.style.display='none'">
</div>

<?php if (!empty($slots)): ?>
<div class="fit-visual">
    <?php foreach (['High', 'Mid', 'Low', 'Rig', 'Cargo', 'Drone Bay', 'Other'] as $slotType): ?>
        <?php if (!empty($slots[$slotType])): ?>
        <div class="fit-slot-row">
            <div class="fit-slot-label"><?= $slotType ?></div>
            <div class="fit-slot-icons">
            <?php foreach ($slots[$slotType] as $si):
                $item = $si['item'];
                $name = $itemNames[$item['t']] ?? 'Unknown';
            ?>
                <div class="fit-slot <?= $si['status'] ?>" title="<?= e($name) ?>">
                    <img src="<?= ship_type_icon($item['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="section-title">Items</div>
<table class="items-table">
    <thead><tr><th></th><th>Item</th><th>Qty</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($slots as $slotName => $slotItems): ?>
    <tr><td colspan="4" class="slot-header"><?= e($slotName) ?> Slots</td></tr>
    <?php foreach ($slotItems as $si):
        $item = $si['item'];
        $name = $itemNames[$item['t']] ?? 'Unknown #' . $item['t'];
        $qty = max(1, $item['q'] * max(1, $item['x']));
    ?>
    <tr>
        <td style="width:32px"><img src="<?= ship_type_icon($item['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
        <td><?= e($name) ?></td>
        <td><?= $qty > 1 ? $qty : '' ?></td>
        <td class="<?= $si['status'] ?>"><?= $si['status'] === 'destroyed' ? 'Destroyed' : 'Dropped' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($victimDmg > 0): ?>
<div class="damage-bar-wrap">
    <div class="damage-bar-label">Damage Distribution</div>
    <div class="damage-bar">
        <div class="damage-bar-seg" style="width:<?= $dmgPct ?>%;background:var(--accent)" title="<?= e($k['finalname']) ?>: <?= number_format($finalDmg) ?> (<?= $dmgPct ?>%)"></div>
        <div class="damage-bar-seg" style="width:<?= max(0, 100 - $dmgPct) ?>%;background:var(--danger)" title="Others"></div>
    </div>
</div>
<?php endif; ?>

<div class="attacker-section">
    <div class="section-title">Attackers</div>

    <div class="attacker-row final-blow">
        <img class="atk-portrait" src="<?= char_portrait($k['finalcharacterid'], 64) ?>" width="48" height="48"
             onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22><rect fill=%22%23111820%22 width=%2248%22 height=%2248%22/><text x=%2224%22 y=%2228%22 text-anchor=%22middle%22 fill=%22%23556%22 font-size=%2216%22>?</text></svg>'">
        <div class="atk-info">
            <div class="atk-name"><?= e($k['finalname'] ?: 'Unknown') ?></div>
            <div class="atk-ship">
                <img src="<?= ship_icon($k['finalshiptypeid'], 16) ?>" width="16" height="16" style="vertical-align:middle;margin-right:2px" onerror="this.style.display='none'">
                <?= e($k['finalshipname']) ?>
            </div>
            <?php if (!empty($k['finalcorpname'])): ?>
            <div class="atk-corp"><?= e($k['finalcorpname']) ?></div>
            <?php endif; ?>
        </div>
        <div class="atk-dmg">
            <div class="atk-dmg-val"><?= number_format($finalDmg) ?></div>
            <div class="atk-dmg-pct"><?= $dmgPct ?>%</div>
        </div>
    </div>

    <div style="font-size:10px;color:var(--text-dim);padding:4px 10px;text-transform:uppercase;letter-spacing:.3px;font-weight:600">
        Final Blow &amp; Top Damage
    </div>
</div>

<?php
// EFT Fitting export
$eftLines = [];
foreach ($slots as $slotName => $slotItems) {
    foreach ($slotItems as $si) {
        $item = $si['item'];
        $name = $itemNames[$item['t']] ?? null;
        if ($name && $item['t'] != $k['victimshiptypeid']) {
            $qty = max(1, $item['q']);
            $eftLines[] = $name . ($qty > 1 ? ' x' . $qty : '');
        }
    }
}
$eftFitting = '[' . e($k['victimshipname']) . ', ' . e($k['victimname']) . "'s " . e($k['victimshipname']) . ']' . "\n" . implode("\n", $eftLines);
?>
<div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap">
    <details style="background:var(--bg-card);border:1px solid var(--border);border-radius:6px;padding:12px;flex:1;min-width:300px">
        <summary style="cursor:pointer;color:var(--text-bright);font-size:13px;font-weight:600">EFT Fitting</summary>
        <pre style="margin-top:8px;padding:8px;background:#0d1117;border-radius:4px;font-size:11px;color:var(--text);overflow-x:auto;white-space:pre-wrap"><?= e($eftFitting) ?></pre>
    </details>
</div>

<?php
// Related kills
$relatedKills = [];
$relXml = api_get('/char/RelatedKills.xml.aspx?killid=' . $killID);
if ($relXml && $relXml->result && $relXml->result->related)
    foreach ($relXml->result->related->row as $r) $relatedKills[] = $r;
?>
<?php if (!empty($relatedKills)): ?>
<div class="section-title">Related Kills (same system, ±24h)</div>
<table class="kill-table" style="font-size:12px">
    <thead><tr><th>#</th><th>Victim</th><th>Ship</th><th>Damage</th><th>Final Blow</th><th>Ship</th></tr></thead>
    <tbody>
    <?php foreach ($relatedKills as $rk): ?>
    <tr class="kill-row" onclick="location.href='/kill/<?= $rk['killid'] ?>'">
        <td><?= $rk['killid'] ?></td>
        <td><a href="/character/<?= $rk['victimid'] ?>"><?= e($rk['victimname'] ?: 'Unknown') ?></a></td>
        <td><?= e($rk['victimshipname']) ?></td>
        <td><?= number_format((int)$rk['victimdamagetaken']) ?></td>
        <td><a href="/character/<?= $rk['finalid'] ?>"><?= e($rk['finalname'] ?: 'Unknown') ?></a></td>
        <td><?= e($rk['finalshipname']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout($k['victimshipname'] . ' | ' . $k['victimname'] . ' | Killmail', 'kills', $content);
