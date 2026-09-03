<?php
require_once __DIR__ . '/../layout.php';

$page_num = max(1, intval($_GET['p'] ?? 1));
$offset = ($page_num - 1) * 20;

$xml = api_get('/char/AllKills.xml.aspx');
$kills = [];
if ($xml && $xml->result && $xml->result->kills)
    foreach ($xml->result->kills->row as $r) $kills[] = $r;

$total = count($kills);
$paged = array_slice($kills, $offset, 20);
$total_pages = max(1, ceil($total / 20));

ob_start();
?>
<h1>Killboard</h1>
<table class="kill-table">
    <thead><tr>
        <th>#</th><th>Дата</th><th>Система</th><th></th><th>Жертва</th><th>Корабль</th><th>Урон</th><th></th><th>Убийца</th><th>Корабль</th>
    </tr></thead>
    <tbody>
    <?php foreach ($paged as $k):
        $ts = filetime_to_unix((string)$k['killtime']);
        $sec = (float)$k['finalsecuritystatus'];
    ?>
    <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
        <td class="id"><?= $k['killid'] ?></td>
        <td class="date"><?= date('d.m.y H:i', $ts) ?></td>
        <td class="system"><?= e($k['solarsystemname']) ?></td>
        <td class="icon"><img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
        <td class="pilot"><?= e($k['victimname']) ?></td>
        <td class="ship"><?= e($k['victimshipname']) ?></td>
        <td class="damage"><?= number_format((int)$k['victimdamagetaken']) ?></td>
        <td class="icon"><img src="<?= ship_icon($k['finalshiptypeid'], 32) ?>" width="32" height="32" onerror="this.style.display='none'"></td>
        <td class="pilot"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec,1) ?></span> <?= e($k['finalname']) ?></td>
        <td class="ship"><?= e($k['finalshipname']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($paged)): ?><tr><td colspan="10" class="empty">Нет данных</td></tr><?php endif; ?>
    </tbody>
</table>
<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page_num > 1): ?><a href="/kills?p=<?= $page_num-1 ?>">&laquo;</a><?php endif; ?>
    <span><?= $page_num ?> / <?= $total_pages ?></span>
    <?php if ($page_num < $total_pages): ?><a href="/kills?p=<?= $page_num+1 ?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php
render_layout('Killboard', 'kills', ob_get_clean());
