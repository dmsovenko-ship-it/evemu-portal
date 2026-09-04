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

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Market</h2>
    <span class="section-count"><?= number_format($totalOrders) ?> orders</span>
</div>

<div class="stat-cards" style="justify-content:flex-start">
    <div class="stat-card">
        <div class="stat-num"><?= number_format($totalOrders) ?></div>
        <div class="stat-label">Orders</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= count($orders) ?></div>
        <div class="stat-label">Items Traded</div>
    </div>
</div>

<?php if (!empty($orders)): ?>
<div class="filter-bar" id="filterBar">
    <div class="form-group filter-search">
        <label for="searchItem">Item</label>
        <input type="text" id="searchItem" placeholder="Filter by name...">
    </div>
</div>

<div class="section">
    <div class="section-header">
        <h2>Top Items by Volume</h2>
    </div>
    <table class="data-table" id="marketTable">
        <thead><tr>
            <th></th><th>Item</th><th>Volume</th><th>Avg Price</th>
        </tr></thead>
        <tbody>
        <?php foreach ($orders as $row):
            $name  = $row['typename'] ?? $row['name'] ?? '';
            $vol   = (int)($row['volume'] ?? $row['quantity'] ?? 0);
            $price = (float)($row['avgprice'] ?? $row['price'] ?? 0);
        ?>
        <tr data-name="<?= e(strtolower($name)) ?>">
            <td style="width:36px"><img src="<?= ship_icon($row['typeid'] ?? 0, 32) ?>" width="32" height="32" style="border-radius:3px;background:#0d1117" onerror="this.style.display='none'"></td>
            <td style="font-weight:600;color:var(--text-bright)"><?= e($name) ?></td>
            <td style="font-variant-numeric:tabular-nums"><?= number_format($vol) ?></td>
            <td style="color:var(--accent);font-variant-numeric:tabular-nums"><?= $price > 0 ? number_format($price, 2, '.', ' ') . ' ISK' : '—' ?></td>
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
    <p style="color:var(--text-dim);text-align:center;padding:32px 0">Market data is not yet available.</p>
</div>
<?php endif; ?>

<?php
render_layout('Market', 'market', ob_get_clean());
