<?php
require_once __DIR__ . '/../layout.php';

$page_num = max(1, intval($_GET['p'] ?? 1));

$xml = api_get('/char/CharacterList.xml.aspx?page=' . $page_num);
$chars = [];
$total = 0;
$perPage = 50;
if ($xml && $xml->result) {
    if (!empty($xml->result->characters))
        foreach ($xml->result->characters->row as $r) $chars[] = $r;
    $total = (int)($xml->result->total ?? count($chars));
    $perPage = (int)($xml->result->perpage ?? 50);
}

usort($chars, function ($a, $b) {
    return (int)($b['skillpoints'] ?? 0) <=> (int)($a['skillpoints'] ?? 0);
});

$total_pages = max(1, ceil($total / $perPage));
$showFrom = ($page_num - 1) * $perPage + 1;
$showTo = min($page_num * $perPage, $total);
if ($total === 0) { $showFrom = 0; $showTo = 0; }

ob_start();
?>

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Players</h2>
    <span class="section-count"><?= number_format($total) ?> characters</span>
</div>

<div class="filter-bar" id="filterBar">
    <div class="form-group filter-search">
        <label for="searchName">Name</label>
        <input type="text" id="searchName" placeholder="Filter by name...">
    </div>
    <div class="form-group">
        <label for="filterRace">Race</label>
        <select id="filterRace">
            <option value="">All</option>
            <option value="Amarr">Amarr</option>
            <option value="Caldari">Caldari</option>
            <option value="Gallente">Gallente</option>
            <option value="Minmatar">Minmatar</option>
        </select>
    </div>
    <div class="form-group filter-search">
        <label for="searchCorp">Corporation</label>
        <input type="text" id="searchCorp" placeholder="Filter by corp/ticker...">
    </div>
</div>

<div class="filter-info" id="filterInfo">
    Showing <?= $showFrom ?>&ndash;<?= $showTo ?> of <?= number_format($total) ?> characters
</div>

<table class="data-table" id="playersTable">
    <thead><tr>
        <th></th><th>Name</th><th>Sec</th><th>Corp</th><th>Skillpoints</th><th>System</th><th>Ship</th>
    </tr></thead>
    <tbody>
    <?php foreach ($chars as $c):
        $sec = (float)($c['securitystatus'] ?? 0);
        $race = $c['racename'] ?? $c['race'] ?? '';
        $corpTicker = $c['corporationticker'] ?? $c['corpticker'] ?? '';
        $corpName = $c['corporationname'] ?? $c['corpname'] ?? '';
    ?>
    <tr data-race="<?= e($race) ?>" data-corp="<?= e(strtolower($corpTicker . ' ' . $corpName)) ?>" data-name="<?= e(strtolower($c['charactername'] ?? '')) ?>">
        <td style="width:36px">
            <img src="<?= char_portrait($c['characterid'], 64) ?>"
                 width="32" height="32" style="border-radius:3px;background:#111820"
                 onerror="this.style.display='none'">
        </td>
        <td><a href="/character/<?= $c['characterid'] ?>" style="font-weight:600;color:var(--text-bright)"><?= e($c['charactername'] ?? '') ?></a></td>
        <td><span style="color:<?= security_color($sec) ?>;font-weight:600;font-size:11px"><?= number_format($sec, 1) ?></span></td>
        <td style="color:var(--accent2)"><?= e($corpTicker) ?></td>
        <td style="font-weight:600;font-variant-numeric:tabular-nums"><?= number_format((int)($c['skillpoints'] ?? 0)) ?></td>
        <td style="color:var(--gold)"><?= e($c['solarsystemname'] ?? $c['system'] ?? '') ?></td>
        <td style="color:var(--text-dim)"><?= e($c['shipname'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($chars)): ?>
    <tr><td colspan="7" class="empty">No characters found</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page_num > 1): ?><a href="/players?p=<?= $page_num-1 ?>">&laquo;</a><?php endif; ?>
    <span><?= $page_num ?> / <?= $total_pages ?></span>
    <?php if ($page_num < $total_pages): ?><a href="/players?p=<?= $page_num+1 ?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<script>
(function() {
    var table = document.getElementById('playersTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-name]');
    var info = document.getElementById('filterInfo');
    var searchName = document.getElementById('searchName');
    var filterRace = document.getElementById('filterRace');
    var searchCorp = document.getElementById('searchCorp');

    function applyFilters() {
        var nameQ = searchName.value.toLowerCase();
        var raceQ = filterRace.value.toLowerCase();
        var corpQ = searchCorp.value.toLowerCase();
        var shown = 0;
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var matchName = !nameQ || r.getAttribute('data-name').indexOf(nameQ) !== -1;
            var matchRace = !raceQ || r.getAttribute('data-race').toLowerCase() === raceQ;
            var matchCorp = !corpQ || r.getAttribute('data-corp').indexOf(corpQ) !== -1;
            if (matchName && matchRace && matchCorp) {
                r.style.display = '';
                shown++;
            } else {
                r.style.display = 'none';
            }
        }
        info.textContent = shown === rows.length
            ? 'Showing <?= $showFrom ?>\u2013<?= $showTo ?> of <?= number_format($total) ?> characters'
            : 'Filter: ' + shown + ' of ' + rows.length + ' on page';
    }

    searchName.addEventListener('input', applyFilters);
    filterRace.addEventListener('change', applyFilters);
    searchCorp.addEventListener('input', applyFilters);
})();
</script>

<?php
render_layout('Players', 'players', ob_get_clean());
