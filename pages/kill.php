<?php
require_once __DIR__ . '/../layout.php';

$killID = intval($id ?? 0);
if (!$killID) { redirect('/kills'); return; }

$xml = api_get('/char/AllKills.xml.aspx');
$k = null;
if ($xml && $xml->result && $xml->result->kills)
    foreach ($xml->result->kills->row as $row)
        if ((int)$row['killid'] === $killID) { $k = $row; break; }

if (!$k) {
    ob_start();
    echo '<h2>Килл #' . $killID . ' не найден</h2><p><a href="/kills">Назад</a></p>';
    render_layout('Kill not found', '', ob_get_clean());
    return;
}

$ts = filetime_to_unix((string)$k['killtime']);
$sec = (float)$k['finalsecuritystatus'];
$blob = (string)$k['killblob'];
$drops = [];
if ($blob && strlen($blob) > 10) {
    $doc = new DOMDocument();
    @$doc->loadXML($blob);
    foreach ($doc->getElementsByTagName('i') as $el)
        $drops[] = ['t' => (int)$el->getAttribute('t'), 'q' => (int)$el->getAttribute('q'), 'x' => (int)$el->getAttribute('x')];
}

ob_start();
?>
<a href="/kills" style="font-size:13px">&laquo; Killboard</a>
<div class="kill-detail">
    <div class="kill-header">
        <h2>Kill #<?= $k['killid'] ?></h2>
        <span class="kill-meta"><?= date('d.m.Y H:i:s', $ts) ?> &mdash; <?= e($k['solarsystemname']) ?> &mdash; <span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec,2) ?></span></span>
    </div>
    <div class="kill-parties">
        <div class="party victim">
            <h3>Жертва</h3>
            <div class="party-visual"><img class="ship-img" src="<?= ship_icon($k['victimshiptypeid'], 128) ?>" width="128" height="128" onerror="this.style.display='none'"></div>
            <div class="pilot-name"><?= e($k['victimname']) ?></div>
            <div class="ship-name"><?= e($k['victimshipname']) ?></div>
            <div class="dmg">HP: <?= number_format((int)$k['victimdamagetaken']) ?></div>
        </div>
        <div class="party killer">
            <h3>Финальный удар</h3>
            <div class="party-visual"><img class="ship-img" src="<?= ship_icon($k['finalshiptypeid'], 128) ?>" width="128" height="128" onerror="this.style.display='none'"></div>
            <div class="pilot-name"><?= e($k['finalname']) ?></div>
            <div class="ship-name"><?= e($k['finalshipname']) ?></div>
            <div class="weapon-name"><img src="<?= ship_icon($k['finalweapontypeid'], 32) ?>" width="16" height="16" onerror="this.style.display='none'" style="vertical-align:middle"> <?= e($k['finalweaponname']) ?></div>
            <div class="dmg">Дамаг: <?= number_format((int)$k['finaldamagedone']) ?></div>
        </div>
    </div>
    <?php if ($drops): ?>
    <div class="drops-section">
        <h3>Выпад</h3>
        <div class="drops-grid">
        <?php foreach ($drops as $d): ?>
            <div class="drop-item">
                <img src="<?= ship_icon($d['t'], 32) ?>" width="32" height="32" onerror="this.style.display='none'">
                <span>#<?= $d['t'] ?></span>
                <span class="qty">x<?= max(1, $d['q'] * max(1,$d['x'])) ?></span>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php
render_layout('Kill #' . $k['killid'], 'kills', ob_get_clean());
