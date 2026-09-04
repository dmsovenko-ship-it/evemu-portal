<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/server/ActiveSystems.xml.aspx', 15);
$systems = [];
if ($xml && $xml->result && $xml->result->systems)
    foreach ($xml->result->systems->row as $r) $systems[] = $r;

usort($systems, function ($a, $b) {
    return (int)($b['playercount'] ?? $b['players'] ?? 0) <=> (int)($a['playercount'] ?? $a['players'] ?? 0);
});

$totalSystems = count($systems);

ob_start();
?>

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Active Systems</h2>
    <span class="section-count"><?= number_format($totalSystems) ?> systems</span>
</div>

<div class="filter-bar" id="filterBar">
    <div class="form-group filter-search">
        <label for="searchSystem">System</label>
        <input type="text" id="searchSystem" placeholder="Filter by name...">
    </div>
    <div class="form-group">
        <label for="filterSec">Security</label>
        <select id="filterSec">
            <option value="">All</option>
            <option value="high">High (>0.5)</option>
            <option value="low">Low (-0.5..0.5)</option>
            <option value="null">Null (<-0.5)</option>
        </select>
    </div>
</div>

<div class="filter-info" id="filterInfo">
    Systems: <?= $totalSystems ?>
</div>

<table class="data-table" id="systemsTable">
    <thead><tr>
        <th>System</th><th>Sec</th><th>Players</th><th>Ships</th>
    </tr></thead>
    <tbody>
    <?php foreach ($systems as $s):
        $sec  = (float)($s['security'] ?? 0);
        $name = $s['name'] ?? $s['systemname'] ?? '';
        $players = (int)($s['players'] ?? 0);
        $ships   = (int)($s['ships'] ?? 0);
        $sysID = (int)($s['systemid'] ?? $s['solarsystemid'] ?? 0);
        $secClass = $sec > 0.5 ? 'high' : ($sec >= -0.5 ? 'low' : 'null');
    ?>
    <tr data-name="<?= e(strtolower($name)) ?>" data-sec="<?= $secClass ?>">
        <td style="font-weight:600"><a href="/system/<?= $sysID ?>"><?= e($name) ?></a></td>
        <td><span style="color:<?= security_color($sec) ?>;font-weight:600"><?= number_format($sec, 1) ?></span></td>
        <td style="font-weight:600;font-variant-numeric:tabular-nums"><?= (int)$players ?></td>
        <td style="font-variant-numeric:tabular-nums"><?= (int)$ships ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($systems)): ?>
    <tr><td colspan="4" class="empty">No active systems</td></tr>
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
            ? 'Systems: <?= $totalSystems ?>'
            : 'Filter: ' + shown + ' of <?= $totalSystems ?> systems';
    }

    searchSystem.addEventListener('input', applyFilters);
    filterSec.addEventListener('change', applyFilters);
})();
</script>

<?php
render_layout('Systems', 'systems', ob_get_clean());
