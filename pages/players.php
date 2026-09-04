<?php
require_once __DIR__ . '/../layout.php';

$page_num = max(1, intval($_GET['p'] ?? 1));

$xml = api_get('/char/CharacterList.xml.aspx?page=' . $page_num);
$chars = [];
$total = 0;
$perPage = 50;
$pageFromApi = $page_num;
if ($xml && $xml->result) {
    if (!empty($xml->result->characters))
        foreach ($xml->result->characters->row as $r) $chars[] = $r;
    $total = (int)($xml->result->total ?? count($chars));
    $perPage = (int)($xml->result->perPage ?? 50);
    $pageFromApi = (int)($xml->result->page ?? $page_num);
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
<h1>Игроки</h1>

<div class="filter-bar" id="filterBar">
    <div class="form-group filter-search">
        <label for="searchName">Имя</label>
        <input type="text" id="searchName" placeholder="Фильтр по имени...">
    </div>
    <div class="form-group">
        <label for="filterRace">Раса</label>
        <select id="filterRace">
            <option value="">Все</option>
            <option value="Amarr">Amarr</option>
            <option value="Caldari">Caldari</option>
            <option value="Gallente">Gallente</option>
            <option value="Minmatar">Minmatar</option>
        </select>
    </div>
    <div class="form-group filter-search">
        <label for="searchCorp">Корпорация</label>
        <input type="text" id="searchCorp" placeholder="Фильтр по корпу/тикеру...">
    </div>
</div>

<div class="filter-info" id="filterInfo">
    Показано <?= $showFrom ?>–<?= $showTo ?> из <?= number_format($total) ?> персонажей
</div>

<table class="data-table" id="playersTable">
    <thead><tr>
        <th></th><th>Имя</th><th>Раса</th><th>Sec</th><th>Корп</th><th>Skillpoints</th><th>Система</th><th>Корабль</th>
    </tr></thead>
    <tbody>
    <?php foreach ($chars as $c):
        $sec = (float)($c['securitystatus'] ?? 0);
        $race = $c['racename'] ?? $c['race'] ?? '';
        $corpTicker = $c['corporationticker'] ?? $c['corpticker'] ?? '';
        $corpName = $c['corporationname'] ?? $c['corpname'] ?? '';
    ?>
    <tr data-race="<?= e($race) ?>" data-corp="<?= e(strtolower($corpTicker . ' ' . $corpName)) ?>" data-name="<?= e(strtolower($c['charactername'] ?? '')) ?>">
        <td style="width:40px">
            <img src="<?= char_portrait($c['characterid'], 64) ?>"
                 width="32" height="32" style="border-radius:3px;background:#111"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2232%22 height=%2232%22><rect fill=%22%23111%22 width=%2232%22 height=%2232%22/></svg>'">
        </td>
        <td><a href="/character/<?= $c['characterid'] ?>" style="font-weight:600;color:var(--text-bright)"><?= e($c['charactername'] ?? '') ?></a></td>
        <td style="color:var(--text-dim)"><?= e($race) ?></td>
        <td><span style="color:<?= security_color($sec) ?>;font-weight:600;font-size:12px"><?= number_format($sec, 1) ?></span></td>
        <td style="color:var(--accent2)"><?= e($corpTicker) ?></td>
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
            ? 'Показано <?= $showFrom ?>\u2013<?= $showTo ?> из <?= number_format($total) ?> персонажей'
            : 'Фильтр: ' + shown + ' из ' + rows.length + ' на странице';
    }

    searchName.addEventListener('input', applyFilters);
    filterRace.addEventListener('change', applyFilters);
    searchCorp.addEventListener('input', applyFilters);
})();
</script>

<?php
render_layout('Игроки', 'players', ob_get_clean());
