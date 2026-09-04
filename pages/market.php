<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/server/MarketStats.xml.aspx');
$orders = [];
$totalOrders = 0;
if ($xml && $xml->result) {
    $r = $xml->result;
    $totalOrders = (int)($r->totalorders ?? 0);
    if (!empty($r->topitems))
        foreach ($r->topitems->row as $row) $orders[] = $row;
}

ob_start();
?>
<h1>Рынок</h1>

<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-num"><?= number_format($totalOrders) ?></div>
        <div class="stat-label">Ордеров</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= count($orders) ?></div>
        <div class="stat-label">Торгуемых предметов</div>
    </div>
</div>

<?php if (!empty($orders)): ?>
<div class="filter-bar" id="filterBar">
    <div class="form-group filter-search">
        <label for="searchItem">Предмет</label>
        <input type="text" id="searchItem" placeholder="Фильтр по названию...">
    </div>
</div>

<div class="section">
    <h2>Топ товаров по объёму</h2>
    <table class="data-table" id="marketTable">
        <thead><tr>
            <th></th><th>Предмет</th><th>Объём</th><th>Ср. цена</th>
        </tr></thead>
        <tbody>
        <?php foreach ($orders as $row):
            $name  = $row['typename'] ?? $row['name'] ?? '';
            $vol   = (int)($row['volume'] ?? $row['quantity'] ?? 0);
            $price = (float)($row['avgprice'] ?? $row['price'] ?? 0);
        ?>
        <tr data-name="<?= e(strtolower($name)) ?>">
            <td style="width:40px"><img src="<?= ship_icon($row['typeid'] ?? 0, 32) ?>" width="32" height="32" style="border-radius:3px;background:#0d1117" onerror="this.style.display='none'"></td>
            <td style="font-weight:600;color:var(--text-bright)"><?= e($name) ?></td>
            <td><?= number_format($vol) ?></td>
            <td style="color:var(--accent)"><?= $price > 0 ? number_format($price, 2, '.', ' ') . ' ISK' : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function() {
    var table = document.getElementById('marketTable');
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr[data-name]');
    var searchItem = document.getElementById('searchItem');

    function applyFilter() {
        var q = searchItem.value.toLowerCase();
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            if (!q || r.getAttribute('data-name').indexOf(q) !== -1) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        }
    }

    searchItem.addEventListener('input', applyFilter);
})();
</script>

<?php else: ?>
<div class="section">
    <p style="color:var(--text-dim);text-align:center;padding:40px 0">Данные рынка пока пусты.</p>
</div>
<?php endif; ?>

<?php
render_layout('Рынок', 'market', ob_get_clean());
