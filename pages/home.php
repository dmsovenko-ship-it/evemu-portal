<?php
require_once __DIR__ . '/../layout.php';

$serverStatus = api_get('/server/ServerStatus.xml.aspx');
$valuables = api_get('/server/TopValuables.xml.aspx?period=7d&limit=30');
$activity = api_get('/server/Activity.xml.aspx?period=7d');
$allKills = api_get('/char/AllKills.xml.aspx');

$onlinePlayers = 0; $totalAccounts = 0; $totalCharacters = 0;
if ($serverStatus && $serverStatus->result) {
    $onlinePlayers = (int)($serverStatus->result->onlineplayers ?? 0);
    $totalAccounts = (int)($serverStatus->result->accountcount ?? 0);
    $totalCharacters = (int)($serverStatus->result->charactercount ?? 0);
}

// bucket TopValuables rows by ship / structure / sponsored
$killsAll = [];
if ($valuables && $valuables->result && $valuables->result->kills)
    foreach ($valuables->result->kills->row as $r) $killsAll[] = $r;
$shipCards = []; $structCards = []; $sponsoredCards = [];
$used = [];
foreach ($killsAll as $i => $rk) {
    $cat = (int)$rk['categoryid'];
    if ($cat === 6 && count($shipCards) < 6)      { $shipCards[] = $rk; $used[] = $i; }
    elseif ($cat !== 6 && count($structCards) < 6){ $structCards[] = $rk; $used[] = $i; }
}
foreach ($killsAll as $i => $rk) {
    if (in_array($i, $used, true)) continue;
    if (count($sponsoredCards) < 6) { $sponsoredCards[] = $rk; $used[] = $i; }
}

$recentKills = [];
if ($allKills && $allKills->result && $allKills->result->kills)
    foreach ($allKills->result->kills->row as $r) $recentKills[] = $r;

// activity sidebar
$act = null;
if ($activity && $activity->result) $act = $activity->result;
function act_rows($act, $tag) {
    $out = [];
    if ($act && !empty($act->$tag)) foreach ($act->$tag->row as $r) $out[] = $r;
    return $out;
}

ob_start();
?>
<style>
.home-zk { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }
.zk-main { min-width:0; }
.zk-rail { display:flex; flex-direction:column; gap:14px; }

.kill-card-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
.zk-section-title { font-size:16px; font-weight:700; color:var(--text-bright); margin:4px 0 10px; border-bottom:1px solid var(--border); padding-bottom:6px; }
.zk-section-title .view-all { float:right; font-size:12px; font-weight:400; color:var(--accent2); text-decoration:none; }
.zk-section-title .view-all:hover { text-decoration:underline; }
.big-kill { display:flex; flex-direction:column; cursor:pointer; transition:opacity .15s; }
.big-kill:hover { opacity:.85; }
.big-kill .bk-img { display:flex; justify-content:center; align-items:center; height:150px; background:#0b1017; border-radius:6px; }
.big-kill .bk-img img { max-width:128px; max-height:128px; object-fit:contain; }
.big-kill .bk-name { padding:6px 4px 0; color:var(--text-bright); font-size:12px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:center; }
.big-kill .bk-sys { padding:0 4px; font-size:10px; color:var(--text-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:center; }
.big-kill .bk-val { padding:4px 4px 0; color:var(--gold); font-size:14px; font-weight:700; text-align:center; }

/* rail */
.zk-card { background:var(--bg-card); border:1px solid var(--border); border-radius:6px; padding:12px; }
.zk-card h3 { font-size:13px; color:var(--text-bright); margin-bottom:10px; border-bottom:1px solid var(--border); padding-bottom:6px; }
.zk-stat { display:flex; justify-content:space-between; padding:2px 0; font-size:12px; }
.zk-stat .l { color:var(--text-dim); } .zk-stat .v { color:var(--text-bright); font-weight:600; }
.zk-top { display:flex; align-items:center; gap:6px; padding:3px 0; font-size:12px; }
.zk-top .rk { color:var(--text-dim); width:16px; text-align:right; }
.zk-top .nm { flex:1; color:var(--accent2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.zk-top .ct { color:var(--text); font-weight:600; }
.zk-top a.nm:hover { text-decoration:underline; }
@media (max-width:1100px){ .kill-card-grid{grid-template-columns:repeat(2,1fr);} }
@media (max-width:800px){ .home-zk{grid-template-columns:1fr;} .kill-card-grid{grid-template-columns:repeat(2,1fr);} }
</style>

<div class="stat-cards">
    <div class="stat-card"><div class="stat-num" style="color:<?= $onlinePlayers>0?'var(--accent)':'var(--danger)' ?>"><?= number_format($onlinePlayers) ?></div><div class="stat-label">Online Now</div></div>
    <div class="stat-card"><div class="stat-num"><?= number_format($totalAccounts) ?></div><div class="stat-label">Accounts</div></div>
    <div class="stat-card"><div class="stat-num"><?= number_format($totalCharacters) ?></div><div class="stat-label">Characters</div></div>
    <div class="stat-card"><div class="stat-num" style="color:var(--warn)"><?= number_format(count($recentKills)) ?></div><div class="stat-label">Total Kills</div></div>
</div>

<div class="home-zk">
  <div class="zk-main">
    <div class="zk-section-title">Most Valuable Ships <a class="view-all" href="/kills?period=7d">View All &rarr;</a></div>
    <div class="kill-card-grid">
    <?php foreach ($shipCards as $rk): ?>
        <div class="big-kill" onclick="location.href='/kill/<?= $rk['killid'] ?>'">
            <div class="bk-img"><img src="<?= ship_icon($rk['victimshiptypeid'], 128) ?>" loading="lazy" onerror="this.style.display='none'"></div>
            <div class="bk-name" title="<?= e($rk['victimname'] ?: '') ?>"><?= e($rk['victimname'] ?: 'Unknown') ?></div>
            <div class="bk-sys"><?= e($rk['victimshipname']) ?> &middot; <?= e($rk['solarsystemname']) ?></div>
            <div class="bk-val"><?= isk_compact((string)$rk['value']) ?> ISK</div>
        </div>
    <?php endforeach; ?>
    <?php if (!$shipCards): ?><div style="color:var(--text-dim);grid-column:1/-1">No ship kills in this period</div><?php endif; ?>
    </div>

    <div class="zk-section-title">Most Valuable Structures <a class="view-all" href="/kills?period=7d">View All &rarr;</a></div>
    <div class="kill-card-grid">
    <?php foreach ($structCards as $rk): ?>
        <div class="big-kill" onclick="location.href='/kill/<?= $rk['killid'] ?>'">
            <div class="bk-img"><img src="<?= ship_icon($rk['victimshiptypeid'], 128) ?>" loading="lazy" onerror="this.style.display='none'"></div>
            <div class="bk-name" title="<?= e($rk['victimname'] ?: '') ?>"><?= e($rk['victimname'] ?: $rk['victimshipname']) ?></div>
            <div class="bk-sys"><?= e($rk['victimshipname']) ?> &middot; <?= e($rk['solarsystemname']) ?></div>
            <div class="bk-val"><?= isk_compact((string)$rk['value']) ?> ISK</div>
        </div>
    <?php endforeach; ?>
    <?php if (!$structCards): ?><div style="color:var(--text-dim);grid-column:1/-1">No structure kills in this period</div><?php endif; ?>
    </div>

    <div class="zk-section-title">Sponsored Killmails <a class="view-all" href="/kills?period=7d">View All &rarr;</a></div>
    <div class="kill-card-grid">
    <?php foreach ($sponsoredCards as $rk): ?>
        <div class="big-kill" onclick="location.href='/kill/<?= $rk['killid'] ?>'">
            <div class="bk-img"><img src="<?= ship_icon($rk['victimshiptypeid'], 128) ?>" loading="lazy" onerror="this.style.display='none'"></div>
            <div class="bk-name" title="<?= e($rk['victimname'] ?: '') ?>"><?= e($rk['victimname'] ?: 'Unknown') ?></div>
            <div class="bk-sys"><?= e($rk['victimshipname']) ?> &middot; <?= e($rk['solarsystemname']) ?></div>
            <div class="bk-val"><?= isk_compact((string)$rk['value']) ?> ISK</div>
        </div>
    <?php endforeach; ?>
    <?php if (!$sponsoredCards): ?><div style="color:var(--text-dim);grid-column:1/-1">No sponsored killmails in this period</div><?php endif; ?>
    </div>

    <div class="zk-section-title">Recent Kills <a class="view-all" href="/kills">View All &rarr;</a></div>
    <table class="kill-table" style="font-size:13px">
        <thead><tr><th></th><th>System</th><th>Victim</th><th>Ship</th><th>Damage</th><th></th><th>Final Blow</th><th>Ship</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($recentKills, 0, 20) as $k):
            $rts = filetime_to_unix((string)$k['killtime']);
            $rsec = (float)$k['finalsecuritystatus']; ?>
        <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
            <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'],32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
            <td class="k-system"><span class="sec" style="color:<?= security_color($rsec) ?>"><?= number_format($rsec,1) ?></span> <?= e($k['solarsystemname']) ?></td>
            <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
            <td class="k-ship"><?= e($k['victimshipname']) ?></td>
            <td class="k-value"><?= number_format((int)$k['victimdamagetaken']) ?></td>
            <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'],32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
            <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
            <td class="k-ship"><?= e($k['finalshipname']) ?></td>
            <td class="k-time" title="<?= date('Y-m-d H:i:s', $rts) ?>"><?= time_ago($rts) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$recentKills): ?><tr><td colspan="9" class="empty">No kills recorded yet</td></tr><?php endif; ?>
        </tbody>
    </table>
  </div>

  <div class="zk-rail">
    <?php $s = $act && !empty($act->summary) ? $act->summary : null; ?>
    <?php if ($s): ?>
    <div class="zk-card">
        <h3>Current Activity <span style="color:var(--text-dim);font-weight:400;font-size:11px">(Last 7 days)</span></h3>
        <div class="zk-stat"><span class="l">Total Kills</span><span class="v"><?= number_format((int)$s['total']) ?></span></div>
        <div class="zk-stat"><span class="l">Characters</span><span class="v"><?= number_format((int)$s['characters']) ?></span></div>
        <div class="zk-stat"><span class="l">Corporations</span><span class="v"><?= number_format((int)$s['corporations']) ?></span></div>
        <div class="zk-stat"><span class="l">Alliances</span><span class="v"><?= number_format((int)$s['alliances']) ?></span></div>
        <div class="zk-stat"><span class="l">Ships</span><span class="v"><?= number_format((int)$s['ships']) ?></span></div>
        <div class="zk-stat"><span class="l">Systems</span><span class="v"><?= number_format((int)$s['systems']) ?></span></div>
        <div class="zk-stat"><span class="l">Regions</span><span class="v"><?= number_format((int)$s['regions']) ?></span></div>
    </div>
    <?php endif; ?>

    <?php $rows = act_rows($act, 'characters'); if ($rows): ?>
    <div class="zk-card"><h3>Top Characters</h3>
    <?php foreach ($rows as $i => $r): ?>
        <div class="zk-top"><span class="rk"><?= $i+1 ?></span>
            <a class="nm" href="/character/<?= $r['id'] ?>"><?= e($r['name'] ?: 'Unknown') ?></a>
            <span class="ct"><?= number_format((int)$r['count']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?>

    <?php $rows = act_rows($act, 'corporations'); if ($rows): ?>
    <div class="zk-card"><h3>Top Corporations</h3>
    <?php foreach ($rows as $i => $r): ?>
        <div class="zk-top"><span class="rk"><?= $i+1 ?></span>
            <a class="nm" href="/corporation/<?= $r['id'] ?>"><?= e($r['name'] ?: 'Unknown') ?></a>
            <span class="ct"><?= number_format((int)$r['count']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?>

    <?php $rows = act_rows($act, 'alliances'); if ($rows): ?>
    <div class="zk-card"><h3>Top Alliances</h3>
    <?php foreach ($rows as $i => $r): ?>
        <div class="zk-top"><span class="rk"><?= $i+1 ?></span>
            <span class="nm"><?= e($r['name'] ?: 'Unknown') ?></span>
            <span class="ct"><?= number_format((int)$r['count']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?>

    <?php $rows = act_rows($act, 'ships'); if ($rows): ?>
    <div class="zk-card"><h3>Top Ships</h3>
    <?php foreach ($rows as $i => $r): ?>
        <div class="zk-top"><span class="rk"><?= $i+1 ?></span>
            <span class="nm"><?= e($r['name'] ?: 'Unknown') ?></span>
            <span class="ct"><?= number_format((int)$r['count']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?>

    <?php $rows = act_rows($act, 'systems'); if ($rows): ?>
    <div class="zk-card"><h3>Top Systems</h3>
    <?php foreach ($rows as $i => $r): ?>
        <div class="zk-top"><span class="rk"><?= $i+1 ?></span>
            <a class="nm" href="/system/<?= $r['id'] ?>"><?= e($r['name'] ?: 'Unknown') ?></a>
            <span class="ct"><?= number_format((int)$r['count']) ?></span></div>
    <?php endforeach; ?></div>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
render_layout('Home', 'home', $content);
