<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/server/ActiveSystems.xml.aspx');
$systems = [];
if ($xml && $xml->result && $xml->result->systems)
    foreach ($xml->result->systems->row as $r) $systems[] = $r;

usort($systems, function ($a, $b) {
    return (int)($b['playercount'] ?? $b['players'] ?? 0) <=> (int)($a['playercount'] ?? $a['players'] ?? 0);
});

ob_start();
?>
<h1>Активные системы</h1>
<p style="color:var(--text-dim);margin-bottom:16px">Систем с активными игроками: <?= count($systems) ?></p>
<table class="data-table">
    <thead><tr>
        <th>Система</th><th>Sec</th><th>Игроков</th><th>Кораблей</th>
    </tr></thead>
    <tbody>
    <?php foreach ($systems as $s):
        $sec  = (float)($s['security'] ?? $s['securitystatus'] ?? 0);
        $name = $s['solarsystemname'] ?? $s['systemname'] ?? $s['name'] ?? '';
        $players = $s['playercount'] ?? $s['players'] ?? 0;
        $ships   = $s['shipcount'] ?? $s['ships'] ?? 0;
    ?>
    <tr>
        <td style="font-weight:600;color:var(--text-bright)"><?= e($name) ?></td>
        <td><span style="color:<?= security_color($sec) ?>;font-weight:600"><?= number_format($sec, 1) ?></span></td>
        <td style="font-weight:600"><?= (int)$players ?></td>
        <td><?= (int)$ships ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($systems)): ?>
    <tr><td colspan="4" class="empty">Нет активных систем</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php
render_layout('Системы', 'systems', ob_get_clean());
