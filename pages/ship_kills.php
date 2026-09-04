<?php
require_once __DIR__ . '/../layout.php';

$typeID = intval($id ?? 0);
if (!$typeID) { redirect('/'); return; }

$page_num = max(1, intval($_GET['p'] ?? 1));

$allKills = api_get('/char/AllKills.xml.aspx');
$kills = [];
$shipName = '';

if ($allKills && $allKills->result && $allKills->result->kills) {
    foreach ($allKills->result->kills->row as $r) {
        if ((int)($r['victimshiptypeid']) === $typeID) {
            $kills[] = $r;
            if (empty($shipName)) $shipName = (string)$r['victimshipname'];
        }
    }
}

$total = count($kills);
$perPage = 20;
$total_pages = max(1, ceil($total / $perPage));
$page_num = min($page_num, $total_pages);
$offset = ($page_num - 1) * $perPage;
$paginated = array_slice($kills, $offset, $perPage);

ob_start();
?>

<div class="system-profile">
    <div class="system-info">
        <h1>
            <img src="<?= ship_icon($typeID, 64) ?>" width="64" height="64" style="vertical-align:middle;margin-right:10px" onerror="this.style.display='none'">
            <?= e($shipName ?: 'Ship type #' . $typeID) ?>
        </h1>
    </div>
</div>

<div class="section-header">
    <h2>Kills involving this ship</h2>
    <span class="section-count"><?= number_format($total) ?> kills</span>
</div>

<table class="kill-table">
    <thead>
        <tr>
            <th class="k-icon"></th>
            <th class="k-system">System</th>
            <th class="k-victim">Victim</th>
            <th class="k-ship">Ship</th>
            <th class="k-value">Damage</th>
            <th class="k-icon"></th>
            <th class="k-killer">Final Blow</th>
            <th class="k-ship">Ship</th>
            <th class="k-time">When</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($paginated as $k):
        $ts = filetime_to_unix((string)$k['killtime']);
        $sec = (float)$k['finalsecuritystatus'];
        $dmg = (int)$k['victimdamagetaken'];
    ?>
    <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
        <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'],32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
        <td class="k-system"><a href="/system/<?= $k['solarsystemid'] ?? '' ?>" onclick="event.stopPropagation()"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span> <?= e($k['solarsystemname']) ?></a></td>
        <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
        <td class="k-ship"><a href="/ship/<?= $k['victimshiptypeid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimshipname']) ?></a></td>
        <td class="k-value"><?= number_format($dmg) ?></td>
        <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'],32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
        <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
        <td class="k-ship"><?= e($k['finalshipname']) ?></td>
        <td class="k-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= time_ago($ts) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($paginated)): ?>
        <tr><td colspan="9" class="empty">No kills recorded for this ship type</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page_num > 1): ?><a href="/ship/<?= $typeID ?>?p=<?= $page_num - 1 ?>">&laquo; Prev</a><?php endif; ?>
    <span>Page <?= $page_num ?> of <?= $total_pages ?></span>
    <?php if ($page_num < $total_pages): ?><a href="/ship/<?= $typeID ?>?p=<?= $page_num + 1 ?>">Next &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout($shipName ?: 'Ship', 'search', $content);
