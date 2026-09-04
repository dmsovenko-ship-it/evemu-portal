<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/server/ActiveSystems.xml.aspx');
$systems = [];
if ($xml && $xml->result && $xml->result->systems)
    foreach ($xml->result->systems->row as $r) $systems[] = $r;

usort($systems, function ($a, $b) {
    return (int)($b['playercount'] ?? $b['players'] ?? 0) <=> (int)($a['playercount'] ?? $a['players'] ?? 0);
});

$totalSystems = count($systems);

ob_start();
?>
<h1>Активные системы</h1>

<div class="filter-bar" id="filterBar">
    <div class="form-group filter-search">
        <label for="searchSystem">Система</label>
        <input type="text" id="searchSystem" placeholder="Фильтр по названию...">
    </div>
    <div class="form-group">
        <label for="filterSec">Безопасность</label>
        <select id="filterSec">
            <option value="">Все</option>
            <option value="high">High (>0.5)</option>
            <option value="low">Low (-0.5…0.5)</option>
            <option value="null">Null (<-0.5)</option>
        </select>
    </div>
</div>

<div class="filter-info" id="filterInfo">
    Систем: <?= $totalSystems ?>
</div>

<table class="data-table" id="systemsTable">
    <thead><tr>
        <th>Система</th><th>Sec</th><th>Игроков</th><th>Кораблей</th>
    </tr></thead>
    <tbody>
    <?php foreach ($systems as $s):
        $sec  = (float)($s['security'] ?? $s['securitystatus'] ?? 0);
        $name = $s['solarsystemname'] ?? $s['systemname'] ?? $s['name'] ?? '';
        $players = $s['playercount'] ?? $s['players'] ?? 0;
        $ships   = $s['shipcount'] ?? $s['ships'] ?? 0;
        $secClass = $sec > 0.5 ? 'high' : ($sec >= -0.5 ? 'low' : 'null');
    ?>
    <tr data-name="<?= e(strtolower($name)) ?>" data-sec="<?= $secClass ?>">
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

<script>
(function() {
    var table = document.getElementById('systemsTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-name]');
    var info = document.getElementById('filterInfo');
    var searchSystem = document.getElementById('searchSystem');
    var filterSec = document.getElementById('filterSec');

    function applyFilters() {
        var nameQ = searchSystem.value.toLowerCase();
        var secQ = filterSec.value;
        var shown = 0;
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var matchName = !nameQ || r.getAttribute('data-name').indexOf(nameQ) !== -1;
            var matchSec = !secQ || r.getAttribute('data-sec') === secQ;
            if (matchName && matchSec) {
                r.style.display = '';
                shown++;
            } else {
                r.style.display = 'none';
            }
        }
        info.textContent = shown === rows.length
            ? 'Систем: <?= $totalSystems ?>'
            : 'Фильтр: ' + shown + ' из <?= $totalSystems ?> систем';
    }

    searchSystem.addEventListener('input', applyFilters);
    filterSec.addEventListener('change', applyFilters);
})();
</script>

<?php
render_layout('Системы', 'systems', ob_get_clean());
