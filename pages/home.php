<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/char/AllKills.xml.aspx');
$kills = [];
if ($xml && $xml->result && $xml->result->kills)
    foreach ($xml->result->kills->row as $r) $kills[] = $r;

$stats = ['kills' => count($kills)];
$totalDmg = 0;
foreach ($kills as $k) $totalDmg += (int)$k['victimdamagetaken'];
$stats['totalDmg'] = $totalDmg;

ob_start();
?>
<div class="hero">
    <h1><?= SITE_NAME ?></h1>
    <p class="hero-sub">Приватный сервер EVE Online</p>
</div>
<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-num"><?= $stats['kills'] ?></div>
        <div class="stat-label">Киллов</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format($stats['totalDmg']) ?></div>
        <div class="stat-label">Всего урона</div>
    </div>
</div>
<div class="section">
    <h2>Последние киллы</h2>
    <table class="kill-table">
        <thead>
            <tr>
                <th>#</th><th>Дата</th><th>Система</th><th></th><th>Жертва</th><th>Корабль</th><th>Урон</th><th></th><th>Убийца</th><th>Корабль</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($kills, 0, 10) as $k):
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
        <?php if (empty($kills)): ?>
            <tr><td colspan="10" class="empty">Нет данных</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if (count($kills) > 10): ?>
    <a href="/kills" class="btn-link">Все киллы &rarr;</a>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
render_layout('Главная', 'home', $content);
