<?php
require_once __DIR__ . '/../layout.php';

$charID = intval($id ?? 0);
if (!$charID) { redirect('/'); return; }

$page_num = max(1, intval($_GET['p'] ?? 1));
$tab = $_GET['tab'] ?? 'attacker';

$charInfo = null;
$charXml = api_get('/char/CharacterInfo.xml.aspx?characterID=' . $charID);
if ($charXml && $charXml->result) {
    $charInfo = $charXml->result;
}

$killsXml = api_get('/char/KillMails.xml.aspx?characterID=' . $charID);
$allKills = [];
if ($killsXml && $killsXml->result && $killsXml->result->kills)
    foreach ($killsXml->result->kills->row as $r) $allKills[] = $r;

$attackerKills = [];
$victimKills = [];
foreach ($allKills as $k) {
    if ((int)($k['finalcharacterid']) === $charID)
        $attackerKills[] = $k;
    if ((int)($k['victimcharacterid']) === $charID)
        $victimKills[] = $k;
}

$activeKills = $tab === 'victim' ? $victimKills : $attackerKills;

$total = count($activeKills);
$perPage = 20;
$total_pages = max(1, ceil($total / $perPage));
$page_num = min($page_num, $total_pages);
$offset = ($page_num - 1) * $perPage;
$paginated = array_slice($activeKills, $offset, $perPage);

$charName = $charInfo ? (string)($charInfo->characterName ?? 'Unknown') : 'Unknown';
$corpName = $charInfo ? (string)($charInfo->corporationName ?? '') : '';
$corpID = $charInfo ? (int)($charInfo->corporationID ?? 0) : 0;
$sp = $charInfo ? (int)($charInfo->skillPoints ?? 0) : 0;
$sec = $charInfo ? (float)($charInfo->securityStatus ?? 0) : 0;
$race = $charInfo ? (string)($charInfo->raceName ?? '') : '';

ob_start();
?>

<div class="char-profile">
    <div class="char-portrait-wrap">
        <img src="<?= char_portrait($charID, 256) ?>" width="192" height="192" class="char-portrait" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22192%22 height=%22192%22><rect fill=%22%23111%22 width=%22192%22 height=%22192%22/><text x=%2296%22 y=%22100%22 text-anchor=%22middle%22 fill=%22%23556%22 font-size=%2248%22>?</text></svg>'">
    </div>
    <div class="char-info">
        <h1><?= e($charName) ?></h1>
        <div class="char-meta">
            <?php if ($corpID): ?>
            <span class="char-corp">
                <img src="<?= corp_logo($corpID, 24) ?>" width="24" height="24" onerror="this.style.display='none'">
                <a href="/corporation/<?= $corpID ?>"><?= e($corpName) ?></a>
            </span>
            <?php endif; ?>
            <span class="char-sec">
                <span class="sec" style="color:<?= security_color($sec) ?>;font-size:14px;font-weight:700"><?= number_format($sec, 1) ?></span>
            </span>
            <?php if ($sp > 0): ?>
            <span class="char-sp"><?= number_format($sp) ?> SP</span>
            <?php endif; ?>
            <?php if ($race): ?>
            <span class="char-race"><?= e($race) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="kill-tabs">
    <a href="?id=<?= $charID ?>&tab=attacker" class="kill-tab <?= $tab === 'attacker' ? 'active' : '' ?>">
        Kills as Attacker <span class="tab-count"><?= count($attackerKills) ?></span>
    </a>
    <a href="?id=<?= $charID ?>&tab=victim" class="kill-tab <?= $tab === 'victim' ? 'active' : '' ?>">
        Kills as Victim <span class="tab-count"><?= count($victimKills) ?></span>
    </a>
</div>

<table class="kill-table">
    <thead>
        <tr>
            <th class="k-icon"></th>
            <th class="k-system">System</th>
            <th class="k-victim">Victim</th>
            <th class="k-ship">Ship</th>
            <th class="k-value">Damage</th>
            <th class="k-icon"></th>
            <th class="k-killer">Final Blow</th>
            <th class="k-ship">Ship</th>
            <th class="k-time">When</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($paginated as $k):
        $ts = filetime_to_unix((string)$k['killtime']);
        $ksec = (float)$k['finalsecuritystatus'];
        $dmg = (int)$k['victimdamagetaken'];
    ?>
    <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
        <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
        <td class="k-system"><a href="/system/<?= $k['solarsystemid'] ?? '' ?>" onclick="event.stopPropagation()"><span class="sec" style="color:<?= security_color($ksec) ?>"><?= number_format($ksec, 1) ?></span> <?= e($k['solarsystemname']) ?></a></td>
        <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
        <td class="k-ship"><?= e($k['victimshipname']) ?></td>
        <td class="k-value"><?= number_format($dmg) ?></td>
        <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
        <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
        <td class="k-ship"><?= e($k['finalshipname']) ?></td>
        <td class="k-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= time_ago($ts) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($paginated)): ?>
        <tr><td colspan="9" class="empty">No <?= $tab === 'victim' ? 'deaths' : 'kills' ?> recorded</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<?php if ($total_pages > 1): ?>
<div class="pagination">
    <?php if ($page_num > 1): ?><a href="?id=<?= $charID ?>&tab=<?= $tab ?>&p=<?= $page_num - 1 ?>">&laquo; Prev</a><?php endif; ?>
    <span>Page <?= $page_num ?> of <?= $total_pages ?></span>
    <?php if ($page_num < $total_pages): ?><a href="?id=<?= $charID ?>&tab=<?= $tab ?>&p=<?= $page_num + 1 ?>">Next &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
render_layout($charName, 'search', $content);
