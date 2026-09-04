<?php
require_once __DIR__ . '/../layout.php';

$from = isset($_GET['from']) && is_numeric($_GET['from']) ? (int)$_GET['from'] : null;
$to   = isset($_GET['to'])   && is_numeric($_GET['to'])   ? (int)$_GET['to']   : null;

$url = '/server/CourierContracts.xml.aspx?limit=100';
if ($from) $url .= '&fromsystem=' . $from;
if ($to)   $url .= '&tosystem=' . $to;

$xml = api_get($url, 15);
$contracts = [];
if ($xml && $xml->result) {
    foreach ($xml->result->contract ?? [] as $r) $contracts[] = $r;
}

ob_start();
?>
<style>
.haul-list { background:var(--bg-card); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
.haul-row { display:grid; grid-template-columns:1.2fr 1.2fr 1fr 90px 90px 70px; gap:10px; align-items:center;
            padding:9px 14px; font-size:13px; border-top:1px solid var(--border); }
.haul-row:first-child { border-top:none; background:var(--bg-elev); font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--text-dim); }
.haul-route { min-width:0; }
.haul-route .sys { color:var(--text-dim); font-size:11px; }
.haul-route .stn { color:var(--text-bright); font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.haul-arrow { color:var(--text-dim); text-align:center; }
.haul-isk { color:var(--accent); font-weight:700; font-variant-numeric:tabular-nums; text-align:right; }
.haul-issuer { color:var(--accent2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.haul-cargo { text-align:center; font-size:11px; }
.haul-cargo .tag { display:inline-block; padding:1px 7px; border-radius:9px; font-weight:600; }
.haul-cargo .tag.real { background:rgba(63,185,80,.15); color:#3fb950; }
.haul-cargo .tag.none { background:rgba(140,140,140,.12); color:var(--text-dim); }
.haul-empty { color:var(--text-dim); padding:28px; text-align:center; }
@media (max-width:760px){ .haul-row{grid-template-columns:1fr 1fr; } .haul-arrow,.haul-cargo{grid-column:auto;} }
</style>

<div class="section-header" style="margin-bottom:12px">
    <h2 style="font-size:16px">Haul Contracts</h2>
    <span class="section-count">Public courier jobs on the open market</span>
</div>

<div class="haul-list">
    <div class="haul-row">
        <div>From</div><div>To</div><div>Issuer</div><div style="text-align:right">Volume</div><div style="text-align:right">Reward</div><div>Cargo</div>
    </div>
    <?php if (!$contracts): ?>
        <div class="haul-empty">No public courier contracts right now</div>
    <?php endif; ?>
    <?php foreach ($contracts as $c): ?>
    <div class="haul-row">
        <div class="haul-route">
            <div class="stn" title="<?= e($c['fromstation']) ?>"><?= e($c['fromstation']) ?></div>
            <div class="sys"><?= e($c['fromsystem']) ?></div>
        </div>
        <div class="haul-route">
            <div class="stn" title="<?= e($c['tostation']) ?>"><?= e($c['tostation']) ?></div>
            <div class="sys"><?= e($c['tosystem']) ?></div>
        </div>
        <div class="haul-issuer" title="<?= e($c['issuer']) ?>"><?= e($c['issuer']) ?></div>
        <div style="text-align:right;color:var(--text-bright);font-variant-numeric:tabular-nums"><?= number_format((float)$c['volume']) ?> m&sup3;</div>
        <div class="haul-isk"><?= isk_compact((float)$c['reward']) ?></div>
        <div class="haul-cargo">
            <?php if ((int)$c['itemcount'] > 0): ?>
                <span class="tag real" title="<?= number_format((int)$c['units']) ?> units locked in">real goods</span>
            <?php else: ?>
                <span class="tag none">generic</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
render_layout('Haul Contracts', 'haul', ob_get_clean());
