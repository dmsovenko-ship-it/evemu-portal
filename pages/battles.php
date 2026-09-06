<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/char/AllKills.xml.aspx');
$rows = [];
if ($xml && $xml->result && $xml->result->kills)
    foreach ($xml->result->kills->row as $r) $rows[] = $r;

// Group kills into "battles": same system, each next kill within ~10 minutes of
// the previous one in that system. A solo roam = 1-kill battle.
$systems = [];   // sysID => list of kills
foreach ($rows as $r) {
    $sid = (string)$r['solarsystemid'];
    $systems[$sid][] = $r;
}

$BATTLE_WINDOW = 600;   // seconds

$battles = [];
foreach ($systems as $sid => $list) {
    // Sort ascending by time, then slice into battles.
    usort($list, function($a, $b) {
        return (int)$a['killtime'] <=> (int)$b['killtime'];
    });
    $cur = [];
    $prevTs = null;
    foreach ($list as $k) {
        $ts = (int)$k['killtime'];
        if ($prevTs !== null && ($ts - $prevTs) > $BATTLE_WINDOW * 10000000) {
            $battles[] = $cur;
            $cur = [];
        }
        $cur[] = $k;
        $prevTs = $ts;
    }
    if ($cur) $battles[] = $cur;
}

// Sort battles by most recent first.
usort($battles, function($a, $b) {
    $ta = max(array_map(function($k){ return (int)$k['killtime']; }, $a));
    $tb = max(array_map(function($k){ return (int)$k['killtime']; }, $b));
    return $tb <=> $ta;
});

function battle_summary($battle) {
    $system = (string)$battle[0]['solarsystemname'];
    $sysID = (string)$battle[0]['solarsystemid'];
    $sec = (float)$battle[0]['finalsecuritystatus'];
    $kills = count($battle);
    $start = filetime_to_unix((int)$battle[0]['killtime']);
    $end = filetime_to_unix((int)$battle[count($battle)-1]['killtime']);
    $dmg = 0;
    foreach ($battle as $k) $dmg += (int)$k['victimdamagetaken'];
    $victims = [];
    foreach ($battle as $k) $victims[] = '<a href="/character/' . $k['victimcharacterid'] . '">' . e($k['victimname']) . '</a>';
    $ships = [];
    foreach ($battle as $k) $ships[] = '<img src="' . ship_icon($k['victimshiptypeid'],32) . '" width="22" height="22" style="vertical-align:middle" title="' . e($k['victimshipname']) . '" onerror="this.style.display=\'none\'">';
    $killerNames = [];
    foreach ($battle as $k) $killerNames[] = e($k['finalname']);
    return [
        'sysid' => $sysID, 'system' => $system, 'sec' => $sec,
        'kills' => $kills, 'start' => $start, 'end' => $end, 'dmg' => $dmg,
        'victims' => implode(', ', $victims), 'ships' => implode(' ', $ships),
        'killers' => implode(', ', array_unique($killerNames)),
        'ids' => array_map(function($k){ return (int)$k['killid']; }, $battle),
    ];
}

ob_start();
?>
<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Battles</h2>
    <span class="section-count"><?= number_format(count($battles)) ?> engagements</span>
</div>

<?php if (empty($battles)): ?>
    <p class="empty" style="padding:40px 0;text-align:center">No battles yet.</p>
<?php else: ?>
<table class="kill-table">
    <thead><tr>
        <th class="k-time">When</th>
        <th class="k-system">System</th>
        <th class="k-icon"></th>
        <th>Victims</th>
        <th class="k-value">Ships lost</th>
        <th class="k-value">Damage</th>
    </tr></thead>
    <tbody>
    <?php foreach ($battles as $b): $s = battle_summary($b); ?>
    <tr class="kill-row">
        <td class="k-time" title="<?= date('Y-m-d H:i:s', $s['start']) ?> – <?= date('H:i:s', $s['end']) ?>">
            <b><?= date('Y-m-d', $s['start']) ?></b><br><span style="color:var(--text-dim)"><?= date('H:i', $s['start']) ?></span>
        </td>
        <td class="k-system"><a href="/system/<?= $s['sysid'] ?>"><span class="sec" style="color:<?= security_color($s['sec']) ?>"><?= number_format($s['sec'],1) ?></span> <?= e($s['system']) ?></a></td>
        <td class="k-icon"><?= $s['ships'] ?></td>
        <td style="font-size:12px"><?= $s['victims'] ?></td>
        <td class="k-value"><span class="badge badge-open"><?= $s['kills'] ?> kills</span></td>
        <td class="k-value"><?= number_format($s['dmg']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout('Battles', 'kills', $content);
