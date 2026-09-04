<?php
require_once __DIR__ . '/../layout.php';

$period = $_GET['period'] ?? 'all';
$validPeriods = ['24h', '7d', '30d', 'all'];
if (!in_array($period, $validPeriods)) $period = 'all';

$page_num = max(1, intval($_GET['p'] ?? 1));

$kills = [];

if (in_array($period, ['24h', '7d', '30d'])) {
    $xml = api_get('/server/TopKills.xml.aspx?period=' . $period . '&page=' . $page_num);
    if ($xml && $xml->result && $xml->result->kills)
        foreach ($xml->result->kills->row as $r) $kills[] = $r;
    $total = $xml && $xml->result ? (int)($xml->result->total ?? count($kills)) : count($kills);
    $total_pages = max(1, ceil($total / 20));
} else {
    $xml = api_get('/char/AllKills.xml.aspx');
    if ($xml && $xml->result && $xml->result->kills)
        foreach ($xml->result->kills->row as $r) $kills[] = $r;
    $total = count($kills);
    $total_pages = max(1, ceil($total / 20));
    $page_num = min($page_num, $total_pages);
    $offset = ($page_num - 1) * 20;
    $kills = array_slice($kills, $offset, 20);
}

ob_start();
?>

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Killboard</h2>
    <span class="section-count"><?= number_format($total) ?> kills</span>
</div>

<div class="period-tabs">
    <a href="?period=24h" class="period-tab <?= $period === '24h' ? 'active' : '' ?>">24 Hours</a>
    <a href="?period=7d" class="period-tab <?= $period === '7d' ? 'active' : '' ?>">7 Days</a>
    <a href="?period=30d" class="period-tab <?= $period === '30d' ? 'active' : '' ?>">30 Days</a>
    <a href="?period=all" class="period-tab <?= $period === 'all' ? 'active' : '' ?>">All Time</a>
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
    <?php foreach ($kills as $k):
        $ts = filetime_to_unix((string)$k['killtime']);
        $sec = (float)$k['finalsecuritystatus'];
        $dmg = (int)$k['victimdamagetaken'];
    ?>
    <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
        <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'],32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
        <td class="k-system"><a href="/system/<?= $k['solarsystemid'] ?? '' ?>" onclick="event.stopPropagation()"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span> <?= e($k['solarsystemname']) ?></a></td>
        <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
        <td class="k-ship"><?= e($k['victimshipname']) ?></td>
        <td class="k-value"><?= number_format($dmg) ?></td>
        <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'],32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
        <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
        <td class="k-ship"><?= e($k['finalshipname']) ?></td>
        <td class="k-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= time_ago($ts) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($kills)): ?>
        <tr><td colspan="9" class="empty">No kills found for this period</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page_num > 1): ?><a href="?period=<?= $period ?>&p=<?= $page_num - 1 ?>">&laquo; Prev</a><?php endif; ?>
    <span>Page <?= $page_num ?> of <?= $total_pages ?></span>
    <?php if ($page_num < $total_pages): ?><a href="?period=<?= $period ?>&p=<?= $page_num + 1 ?>">Next &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout('Killboard', 'kills', $content);
