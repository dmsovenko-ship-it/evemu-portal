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

// Resolve corporation names for both sides (the battle table shows corps, not
// every pilot name — long player lists overflow the screen).
$corpIDs = [];
foreach ($battles as $battle) {
    foreach ($battle as $k) {
        $vc = (int)$k['victimcorporationid'];
        $fc = (int)$k['finalcorporationid'];
        if ($vc > 0) $corpIDs[$vc] = true;
        if ($fc > 0) $corpIDs[$fc] = true;
    }
}
$corpNames = [];
if (!empty($corpIDs)) {
    $rxml = api_get('/char/Resolve.xml.aspx?ids=' . implode(',', array_keys($corpIDs)));
    if ($rxml && $rxml->result && $rxml->result->names)
        foreach ($rxml->result->names->row as $r)
            $corpNames[(string)$r['id']] = (string)$r['name'];
}

// corp labels for a battle: unique, resolved, capped so a 27-kill blob doesn't
// push the table off-screen ("+N" for the rest). Returns [id, name] pairs.
function corp_list($battle, $attr, $corpNames, $max = 3) {
    $ids = [];
    foreach ($battle as $k) {
        $id = (int)$k[$attr];
        if ($id > 0) $ids[$id] = true;
    }
    $pairs = [];
    foreach (array_keys($ids) as $id)
        $pairs[] = [$id, $corpNames[(string)$id] ?? ('#' . $id)];
    return [
        'pairs' => array_slice($pairs, 0, $max),
        'extra' => count($pairs) > $max ? ' <span style="color:var(--text-dim)">+' . (count($pairs) - $max) . ' more</span>' : '',
    ];
}

ob_start();
?>

<style>
/* keep the battle table on-screen: parties capped by corp_list() and clipped */
.battle-parties { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
.battle-parties .vs { color: var(--text-dim); padding: 0 6px; }
.battle-parties a { color: var(--text); }
.battle-parties a:hover { color: var(--accent2); }
</style>

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
        <th>Corporations</th>
        <th class="k-value">Ships lost</th>
        <th class="k-value">Damage</th>
    </tr></thead>
    <tbody>
    <?php foreach ($battles as $b):
        $start = filetime_to_unix((int)$b[0]['killtime']);
        $end   = filetime_to_unix((int)$b[count($b)-1]['killtime']);
        $sec   = (float)$b[0]['finalsecuritystatus'];
        $dmg   = 0;
        foreach ($b as $k) $dmg += (int)$k['victimdamagetaken'];
        $vc = corp_list($b, 'victimcorporationid', $corpNames);
        $kc = corp_list($b, 'finalcorporationid', $corpNames);
        $battleId = $b[0]['solarsystemid'] . '-' . $b[0]['killid'];
    ?>
    <tr class="kill-row" onclick="location.href='/battle/<?= $battleId ?>'">
        <td class="k-time" title="<?= date('Y-m-d H:i:s', $start) ?> – <?= date('H:i:s', $end) ?>">
            <b><?= date('Y-m-d', $start) ?></b><br><span style="color:var(--text-dim)"><?= date('H:i', $start) ?></span>
        </td>
        <td class="k-system"><a href="/system/<?= $b[0]['solarsystemid'] ?>"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec,1) ?></span> <?= e($b[0]['solarsystemname']) ?></a></td>
        <td class="battle-parties">
            <?php if ($vc['pairs']): foreach ($vc['pairs'] as $p): ?><a href="/corporation/<?= $p[0] ?>"><?= e($p[1]) ?></a> <?php endforeach; ?><?= $vc['extra'] ?><?php endif; ?>
            <?php if ($kc['pairs']): ?><span class="vs">vs</span>
            <?php foreach ($kc['pairs'] as $p): ?><a href="/corporation/<?= $p[0] ?>"><?= e($p[1]) ?></a> <?php endforeach; ?><?= $kc['extra'] ?>
            <?php endif; ?>
        </td>
        <td class="k-value"><span class="badge badge-open"><?= count($b) ?> kills</span></td>
        <td class="k-value"><?= number_format($dmg) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout('Battles', 'kills', $content);
