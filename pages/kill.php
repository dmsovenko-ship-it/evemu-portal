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
    echo '<h2>Килл #' . $killID . ' не найден</h2><p><a href="/kills">Назад</a></p>';
    render_layout('Kill not found', '', ob_get_clean());
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

// resolve item names
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
            if ((string)$nr['type'] === 'ship')
                $itemNames[(int)$nr['id']] = (string)$nr['name'];
}

ob_start();
?>
<style>
.kill-detail-hero { display: flex; gap: 24px; margin-bottom: 24px; }
.kill-victim { flex: 1; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 20px; display: flex; gap: 20px; }
.victim-portrait { flex-shrink: 0; }
.victim-portrait img { width: 128px; height: 128px; border-radius: 6px; background: #0d1117; }
.victim-info { flex: 1; }
.victim-info h2 { font-size: 20px; color: var(--text-bright); margin-bottom: 4px; }
.victim-corp { color: var(--text-dim); font-size: 13px; margin-bottom: 12px; }
.victim-corp img { width: 24px; height: 24px; border-radius: 3px; vertical-align: middle; margin-right: 4px; }
.victim-ship { font-size: 16px; color: var(--accent2); margin-bottom: 8px; }
.victim-ship img { width: 32px; height: 32px; vertical-align: middle; margin-right: 6px; }
.victim-meta { font-size: 12px; color: var(--text-dim); line-height: 1.8; }
.victim-meta strong { color: var(--text); }

.kill-stats { flex: 0 0 280px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
.stat-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 13px; }
.stat-row:last-child { border-bottom: none; }
.stat-label { color: var(--text-dim); }
.stat-value { color: var(--text-bright); font-weight: 600; }
.stat-value.isk { color: var(--gold); }

.section-title { font-size: 14px; color: var(--text-dim); text-transform: uppercase; letter-spacing: .5px; margin: 20px 0 10px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }

.fit-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(36px, 1fr)); gap: 6px; margin-bottom: 16px; }
.fit-slot { width: 36px; height: 36px; border-radius: 4px; background: #0d1117; border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; }
.fit-slot img { width: 32px; height: 32px; border-radius: 2px; }
.fit-slot:hover { border-color: var(--accent2); }

.items-table { width: 100%; border-collapse: collapse; }
.items-table th { background: var(--bg-card); color: var(--text-dim); font-size: 11px; text-transform: uppercase; padding: 6px 8px; text-align: left; border-bottom: 1px solid var(--border); }
.items-table td { padding: 5px 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
.items-table .destroyed { color: var(--danger); }
.items-table .dropped { color: var(--accent); }

.attacker-row { display: flex; align-items: center; gap: 12px; padding: 8px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; margin-bottom: 6px; }
.attacker-row img { border-radius: 4px; background: #0d1117; }
.attacker-name { font-weight: 600; color: var(--text-bright); }
.attacker-ship { color: var(--accent2); font-size: 12px; }
.attacker-dmg { margin-left: auto; text-align: right; }
.attacker-dmg .dmg-val { font-weight: 600; color: #ee6644; }
.attacker-dmg .dmg-pct { font-size: 11px; color: var(--text-dim); }
</style>

<a href="/kills" style="font-size:13px">&laquo; Killboard</a>

<div class="kill-detail-hero">
    <div class="kill-victim">
        <div class="victim-portrait">
            <img src="<?= char_portrait($k['victimcharacterid'], 128) ?>"
                 onerror="this.src='https://images.zkillboard.com/types/<?= $k['victimshiptypeid'] ?>/render/128'" alt="">
        </div>
        <div class="victim-info">
            <h2><?= e($k['victimname'] ?: 'Unknown') ?></h2>
            <div class="victim-corp">
                <img src="<?= corp_logo($k['victimcorporationid'], 32) ?>"
                     onerror="this.style.display='none'" alt="">
                <?= e($k['finalname'] ? '' : '') ?>
            </div>
            <div class="victim-ship">
                <img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" onerror="this.style.display='none'" alt="">
                <?= e($k['victimshipname']) ?>
            </div>
            <div class="victim-meta">
                <strong>Система:</strong> <?= e($k['solarsystemname']) ?>
                (<span style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span>)<br>
                <strong>Время:</strong> <?= date('Y-m-d H:i:s', $ts) ?><br>
                <strong>Урон:</strong> <?= number_format((int)$k['victimdamagetaken']) ?>
            </div>
        </div>
    </div>

    <div class="kill-stats">
        <div class="stat-row">
            <span class="stat-label">Убийца</span>
            <span class="stat-value"><?= e($k['finalname'] ?: 'Unknown') ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Корабль убийцы</span>
            <span class="stat-value"><?= e($k['finalshipname']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Оружие</span>
            <span class="stat-value"><?= e($k['finalweaponname']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Урон убийцы</span>
            <span class="stat-value" style="color:#ee6644"><?= number_format((int)$k['finaldamagedone']) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Sec Status</span>
            <span class="stat-value" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 2) ?></span>
        </div>
    </div>
</div>

<?php if (!empty($destroyed) || !empty($drops)): ?>
<div class="section-title">Предметы</div>
<table class="items-table">
    <thead><tr><th></th><th>Предмет</th><th>Кол-во</th><th>Статус</th></tr></thead>
    <tbody>
    <?php foreach ($destroyed as $item): ?>
        <tr>
            <td><img src="<?= ship_icon($item['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
            <td><?= e($itemNames[$item['t']] ?? '#' . $item['t']) ?></td>
            <td><?= max(1, $item['q'] * max(1, $item['x'])) ?></td>
            <td class="destroyed">Уничтожено</td>
        </tr>
    <?php endforeach; ?>
    <?php foreach ($drops as $item): ?>
        <tr>
            <td><img src="<?= ship_icon($item['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
            <td><?= e($itemNames[$item['t']] ?? '#' . $item['t']) ?></td>
            <td><?= max(1, $item['q'] * max(1, $item['x'])) ?></td>
            <td class="dropped">Выпало</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div class="section-title">Атакующий</div>
<div class="attacker-row">
    <img src="<?= char_portrait($k['finalcharacterid'], 64) ?>"
         width="64" height="64" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2264%22 height=%2264%22><rect fill=%22%23111%22 width=%2264%22 height=%2264%22/><text x=%2232%22 y=%2236%22 text-anchor=%22middle%22 fill=%22%23556%22 font-size=%2220%22>?</text></svg>'" alt="">
    <div>
        <div class="attacker-name"><?= e($k['finalname'] ?: 'Unknown') ?></div>
        <div class="attacker-ship">
            <img src="<?= ship_icon($k['finalshiptypeid'], 16) ?>" width="16" height="16" onerror="this.style.display='none'" style="vertical-align:middle">
            <?= e($k['finalshipname']) ?>
        </div>
    </div>
    <div class="attacker-dmg">
        <div class="dmg-val"><?= number_format((int)$k['finaldamagedone']) ?></div>
        <div class="dmg-pct">100%</div>
    </div>
</div>

<?php
render_layout('Kill #' . $k['killid'], 'kills', ob_get_clean());
