<?php
require_once __DIR__ . '/../layout.php';

$xml = api_get('/server/MarketTops.xml.aspx', 15);
$bought = $sold = $buyers = $sellers = [];
$totalOrders = 0;
if ($xml && $xml->result) {
    foreach ($xml->result->topboughtitems->row ?? [] as $r) $bought[] = $r;
    foreach ($xml->result->topsolditems->row ?? [] as $r) $sold[] = $r;
    foreach ($xml->result->topbuyers->row ?? [] as $r) $buyers[] = $r;
    foreach ($xml->result->topsellers->row ?? [] as $r) $sellers[] = $r;
}

function isk_fmt($v) { $v=(float)$v; if ($v>=1e9) return number_format($v/1e9,2).'b'; if ($v>=1e6) return number_format($v/1e6,2).'m'; if ($v>=1e3) return number_format($v/1e3,1).'k'; return number_format($v); }

ob_start();
?>
<style>
.mkt-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
.mkt-block { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; padding:14px; }
.mkt-block h3 { font-size:14px; color:var(--text-bright); margin-bottom:10px; border-bottom:1px solid var(--border); padding-bottom:6px; }
.mkt-row { display:flex; align-items:center; gap:8px; padding:3px 0; font-size:13px; }
.mkt-row .rk { color:var(--text-dim); width:16px; text-align:right; font-weight:600; }
.mkt-row img { border-radius:3px; background:#0d1117; }
.mkt-row .nm { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--text-bright); font-weight:500; }
.mkt-row .qty { color:var(--text-dim); font-size:11px; white-space:nowrap; }
.mkt-row .val { color:var(--accent); font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
@media (max-width:900px){ .mkt-grid{grid-template-columns:1fr;} }
</style>

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Market</h2>
    <span class="section-count">Trades by value</span>
</div>

<div class="mkt-grid">
    <div class="mkt-block">
        <h3>Most Bought Items</h3>
        <?php foreach ($bought as $i => $r): ?>
        <div class="mkt-row">
            <span class="rk"><?= $i+1 ?></span>
            <img src="<?= ship_type_icon($r['typeid'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
            <span class="nm" title="<?= e($r['name']) ?>"><?= e($r['name']) ?></span>
            <span class="qty">x<?= number_format((int)$r['qty']) ?></span>
            <span class="val"><?= isk_fmt($r['isk']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$bought): ?><div style="color:var(--text-dim);padding:12px 0;text-align:center">No buy trades yet</div><?php endif; ?>
    </div>

    <div class="mkt-block">
        <h3>Most Sold Items</h3>
        <?php foreach ($sold as $i => $r): ?>
        <div class="mkt-row">
            <span class="rk"><?= $i+1 ?></span>
            <img src="<?= ship_type_icon($r['typeid'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
            <span class="nm" title="<?= e($r['name']) ?>"><?= e($r['name']) ?></span>
            <span class="qty">x<?= number_format((int)$r['qty']) ?></span>
            <span class="val"><?= isk_fmt($r['isk']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$sold): ?><div style="color:var(--text-dim);padding:12px 0;text-align:center">No sell trades yet</div><?php endif; ?>
    </div>

    <div class="mkt-block">
        <h3>Top Buyers</h3>
        <?php foreach ($buyers as $i => $r): ?>
        <div class="mkt-row">
            <span class="rk"><?= $i+1 ?></span>
            <?php $isChar = (int)$r['id'] >= 90000000 && (int)$r['id'] < 99000000; ?>
            <?php if ($isChar): ?>
                <img src="<?= char_portrait($r['id'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                <a class="nm" href="/character/<?= $r['id'] ?>" style="color:var(--accent2)"><?= e($r['name']) ?></a>
            <?php else: ?>
                <img src="<?= corp_logo($r['id'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                <a class="nm" href="/corporation/<?= $r['id'] ?>" style="color:var(--accent2)"><?= e($r['name']) ?></a>
            <?php endif; ?>
            <span class="qty"><?= $r['trades'] ?> trades</span>
            <span class="val"><?= isk_fmt($r['isk']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$buyers): ?><div style="color:var(--text-dim);padding:12px 0;text-align:center">No buyers yet</div><?php endif; ?>
    </div>

    <div class="mkt-block">
        <h3>Top Sellers</h3>
        <?php foreach ($sellers as $i => $r): ?>
        <div class="mkt-row">
            <span class="rk"><?= $i+1 ?></span>
            <?php $isChar = (int)$r['id'] >= 90000000 && (int)$r['id'] < 99000000; ?>
            <?php if ($isChar): ?>
                <img src="<?= char_portrait($r['id'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                <a class="nm" href="/character/<?= $r['id'] ?>" style="color:var(--accent2)"><?= e($r['name']) ?></a>
            <?php else: ?>
                <img src="<?= corp_logo($r['id'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                <a class="nm" href="/corporation/<?= $r['id'] ?>" style="color:var(--accent2)"><?= e($r['name']) ?></a>
            <?php endif; ?>
            <span class="qty"><?= $r['trades'] ?> trades</span>
            <span class="val"><?= isk_fmt($r['isk']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (!$sellers): ?><div style="color:var(--text-dim);padding:12px 0;text-align:center">No sellers yet</div><?php endif; ?>
    </div>
</div>

<?php
render_layout('Market', 'market', ob_get_clean());
