<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/char/CharacterList.xml.aspx');
$chars = [];
if ($xml && $xml->result && $xml->result->characters)
    foreach ($xml->result->characters->row as $r) $chars[] = $r;

usort($chars, function ($a, $b) {
    return (int)($b['skillpoints'] ?? 0) <=> (int)($a['skillpoints'] ?? 0);
});

ob_start();
?>
<h1>Игроки</h1>
<p style="color:var(--text-dim);margin-bottom:16px">Всего персонажей: <?= count($chars) ?></p>
<table class="data-table">
    <thead><tr>
        <th></th><th>Имя</th><th>Раса</th><th>Sec</th><th>Корп</th><th>Skillpoints</th><th>Система</th><th>Корабль</th>
    </tr></thead>
    <tbody>
    <?php foreach ($chars as $c):
        $sec = (float)($c['securitystatus'] ?? 0);
    ?>
    <tr>
        <td style="width:40px">
            <img src="<?= char_portrait($c['characterid'], 64) ?>"
                 width="32" height="32" style="border-radius:3px;background:#111"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2232%22 height=%2232%22><rect fill=%22%23111%22 width=%2232%22 height=%2232%22/></svg>'">
        </td>
        <td><a href="/character/<?= $c['characterid'] ?>" style="font-weight:600;color:var(--text-bright)"><?= e($c['charactername'] ?? '') ?></a></td>
        <td style="color:var(--text-dim)"><?= e($c['racename'] ?? $c['race'] ?? '') ?></td>
        <td><span style="color:<?= security_color($sec) ?>;font-weight:600;font-size:12px"><?= number_format($sec, 1) ?></span></td>
        <td style="color:var(--accent2)"><?= e($c['corporationticker'] ?? $c['corpticker'] ?? '') ?></td>
        <td style="font-weight:600"><?= number_format((int)($c['skillpoints'] ?? 0)) ?></td>
        <td style="color:var(--gold)"><?= e($c['solarsystemname'] ?? $c['system'] ?? '') ?></td>
        <td style="color:#8899aa"><?= e($c['shipname'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($chars)): ?>
    <tr><td colspan="8" class="empty">Нет персонажей на сервере</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
render_layout('Игроки', 'players', ob_get_clean());
