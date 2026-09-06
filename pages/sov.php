<?php
require_once __DIR__ . '/../layout.php';

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min(500, max(1, (int)$_GET['limit'])) : 100;
$systemFilter = isset($_GET['systemid']) && is_numeric($_GET['systemid']) ? (int)$_GET['systemid'] : null;

$url = '/server/SovChanges.xml.aspx?limit=' . $limit;
if ($systemFilter) $url .= '&systemid=' . $systemFilter;

$xml = api_get($url, 15);
$changes = [];
if ($xml && $xml->result && $xml->result->changes)
    foreach ($xml->result->changes->row as $r) $changes[] = $r;

// faction icon/tag colors are cosmetic; owners shown as text only.
function owner_html($id, $name, $type) {
    $label = $name !== '' ? $name : ($id > 0 ? '#' . $id : '—');
    if ($id == 0) return '<span class="sov-owner sov-none">' . e($label) . '</span>';
    $cls = ($type === 'faction') ? 'sov-faction' : 'sov-alliance';
    return '<span class="sov-owner ' . $cls . '">' . e($label) . '</span>';
}

ob_start();
?>
<style>
.sov-wrap { display:flex; flex-direction:column; gap:14px; }
.sov-filters { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
.sov-filters input[type=number] { width:160px; }
.sov-note { color:var(--text-dim); font-size:12px; }
.sov-owner { display:inline-block; padding:1px 8px; border-radius:10px; font-size:12px; font-weight:600; }
.sov-owner.sov-faction  { background:rgba(240,150,60,.15); color:#f0963c; }
.sov-owner.sov-alliance { background:rgba(110,140,255,.15); color:#6e8cff; }
.sov-owner.sov-none     { background:rgba(140,140,140,.12); color:var(--text-dim); }
.sov-arrow { color:var(--text-dim); padding:0 4px; }
.sov-time { color:var(--text-dim); white-space:nowrap; font-variant-numeric:tabular-nums; }
.sov-region { color:var(--text-dim); font-size:11px; }
</style>

<div class="section-header" style="margin-bottom:8px">
    <h2 style="font-size:16px">Sovereignty &amp; Influence</h2>
    <span class="section-count"><?= number_format(count($changes)) ?> recorded changes</span>
</div>
<p class="sov-note" style="margin:0 0 10px">
    System ownership changes: faction-war flips and alliance sov claims/releases.
    <a href="/sov" style="color:var(--accent2)">Latest</a>
    <?php if ($limit < 500): ?> &middot; <a href="/sov?limit=500" style="color:var(--accent2)">Show more (500)</a><?php endif; ?>
</p>

<form class="sov-filters" method="get" action="/sov" style="margin-bottom:6px">
    <div class="form-group">
        <label for="systemid">System ID</label>
        <input type="number" id="systemid" name="systemid" value="<?= $systemFilter ?>" min="1" placeholder="e.g. 30000197">
    </div>
    <button type="submit" class="nav-search-btn">Filter</button>
    <?php if ($systemFilter): ?><a href="/sov" style="color:var(--accent2);font-size:12px">clear</a><?php endif; ?>
</form>

<?php if (empty($changes)): ?>
    <p class="empty" style="padding:40px 0;text-align:center">No sovereignty changes recorded yet.</p>
<?php else: ?>
<table class="kill-table">
    <thead><tr>
        <th class="k-time">When</th>
        <th class="k-system">System</th>
        <th class="k-region">Region</th>
        <th>Previous owner</th>
        <th></th>
        <th>New owner</th>
        <th>Type</th>
    </tr></thead>
    <tbody>
    <?php foreach ($changes as $c):
        $ts = filetime_to_unix((int)$c['time']);
        $type = (string)$c['ownertype'];
        $sysID = (int)$c['systemid'];
    ?>
    <tr class="kill-row">
        <td class="sov-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= date('Y-m-d H:i', $ts) ?></td>
        <td class="k-system"><a href="/system/<?= $sysID ?>"><?= e($c['systemname'] ?: $sysID) ?></a></td>
        <td class="sov-region"><?= e($c['regionname'] ?: '') ?></td>
        <td><?= owner_html((int)$c['oldownerid'], (string)$c['oldownername'], $type) ?></td>
        <td class="sov-arrow">&rarr;</td>
        <td><?= owner_html((int)$c['newownerid'], (string)$c['newownername'], $type) ?></td>
        <td><span class="badge <?= $type === 'faction' ? 'badge-open' : 'badge-closed' ?>"><?= e($type) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout('Sovereignty & Influence', 'sov', $content);
