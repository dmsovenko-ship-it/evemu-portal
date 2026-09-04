<?php
require_once __DIR__ . '/../layout.php';

$top7d = api_get('/server/TopKills.xml.aspx?period=7d');
$top24h = api_get('/server/TopKills.xml.aspx?period=24h');
$allKills = api_get('/char/AllKills.xml.aspx');
$serverStatus = api_get('/server/ServerStatus.xml.aspx');

$top7dKills = [];
if ($top7d && $top7d->result && $top7d->result->kills)
    foreach ($top7d->result->kills->row as $r) $top7dKills[] = $r;

$top24hKills = [];
if ($top24h && $top24h->result && $top24h->result->kills)
    foreach ($top24h->result->kills->row as $r) $top24hKills[] = $r;

$recentKills = [];
if ($allKills && $allKills->result && $allKills->result->kills)
    foreach ($allKills->result->kills->row as $r) $recentKills[] = $r;

$onlinePlayers = 0;
$totalAccounts = 0;
$totalCharacters = 0;
if ($serverStatus && $serverStatus->result) {
    $onlinePlayers = (int)($serverStatus->result->onlineplayers ?? 0);
    $totalAccounts = (int)($serverStatus->result->accountcount ?? 0);
    $totalCharacters = (int)($serverStatus->result->charactercount ?? 0);
}

ob_start();
?>

<div class="stat-cards">
    <div class="stat-card">
        <div class="stat-num" style="color:<?= $onlinePlayers > 0 ? 'var(--accent)' : 'var(--danger)' ?>"><?= number_format($onlinePlayers) ?></div>
        <div class="stat-label">Online Now</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format($totalAccounts) ?></div>
        <div class="stat-label">Accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format($totalCharacters) ?></div>
        <div class="stat-label">Characters</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--warn)"><?= number_format(count($recentKills)) ?></div>
        <div class="stat-label">Total Kills</div>
    </div>
</div>

<div class="home-layout">
    <div class="home-main">

        <?php if (!empty($top7dKills)): ?>
        <div class="top-kills-card">
            <div class="top-kills-header">
                <h3>Most Valuable Kills &mdash; Last 7 Days</h3>
                <a href="/kills?period=7d" class="section-link">View All &rarr;</a>
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
                <?php foreach (array_slice($top7dKills, 0, 10) as $k):
                    $ts = filetime_to_unix((string)$k['killtime']);
                    $sec = (float)$k['finalsecuritystatus'];
                    $dmg = (int)$k['victimdamagetaken'];
                ?>
                <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
                    <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
                    <td class="k-system"><a href="/system/<?= $k['solarsystemid'] ?? '' ?>" onclick="event.stopPropagation()"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span> <?= e($k['solarsystemname']) ?></a></td>
                    <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
                    <td class="k-ship"><?= e($k['victimshipname']) ?></td>
                    <td class="k-value"><?= number_format($dmg) ?></td>
                    <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
                    <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
                    <td class="k-ship"><?= e($k['finalshipname']) ?></td>
                    <td class="k-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= time_ago($ts) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($top24hKills)): ?>
        <div class="top-kills-card">
            <div class="top-kills-header">
                <h3>Most Valuable Kills &mdash; 24 Hours</h3>
                <a href="/kills?period=24h" class="section-link">View All &rarr;</a>
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
                <?php foreach (array_slice($top24hKills, 0, 10) as $k):
                    $ts = filetime_to_unix((string)$k['killtime']);
                    $sec = (float)$k['finalsecuritystatus'];
                    $dmg = (int)$k['victimdamagetaken'];
                ?>
                <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
                    <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
                    <td class="k-system"><a href="/system/<?= $k['solarsystemid'] ?? '' ?>" onclick="event.stopPropagation()"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span> <?= e($k['solarsystemname']) ?></a></td>
                    <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
                    <td class="k-ship"><?= e($k['victimshipname']) ?></td>
                    <td class="k-value"><?= number_format($dmg) ?></td>
                    <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
                    <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
                    <td class="k-ship"><?= e($k['finalshipname']) ?></td>
                    <td class="k-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= time_ago($ts) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="top-kills-card">
            <div class="top-kills-header">
                <h3>Recent Kills</h3>
                <a href="/kills" class="section-link">View All &rarr;</a>
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
                <?php foreach (array_slice($recentKills, 0, 20) as $k):
                    $ts = filetime_to_unix((string)$k['killtime']);
                    $sec = (float)$k['finalsecuritystatus'];
                    $dmg = (int)$k['victimdamagetaken'];
                ?>
                <tr class="kill-row" onclick="location.href='/kill/<?= $k['killid'] ?>'">
                    <td class="k-icon"><img src="<?= ship_icon($k['victimshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
                    <td class="k-system"><a href="/system/<?= $k['solarsystemid'] ?? '' ?>" onclick="event.stopPropagation()"><span class="sec" style="color:<?= security_color($sec) ?>"><?= number_format($sec, 1) ?></span> <?= e($k['solarsystemname']) ?></a></td>
                    <td class="k-victim"><a href="/character/<?= $k['victimcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['victimname']) ?></a></td>
                    <td class="k-ship"><?= e($k['victimshipname']) ?></td>
                    <td class="k-value"><?= number_format($dmg) ?></td>
                    <td class="k-icon"><img src="<?= ship_icon($k['finalshiptypeid'], 32) ?>" width="32" height="32" loading="lazy" onerror="this.style.display='none'"></td>
                    <td class="k-killer"><a href="/character/<?= $k['finalcharacterid'] ?>" onclick="event.stopPropagation()"><?= e($k['finalname']) ?></a></td>
                    <td class="k-ship"><?= e($k['finalshipname']) ?></td>
                    <td class="k-time" title="<?= date('Y-m-d H:i:s', $ts) ?>"><?= time_ago($ts) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentKills)): ?>
                    <tr><td colspan="9" class="empty">No kills recorded yet</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="home-sidebar">
        <div class="sidebar-card">
            <h3>Quick Links</h3>
            <div class="sidebar-links">
                <a href="/kills">All Kills</a>
                <a href="/kills?period=24h">Kills (24h)</a>
                <a href="/kills?period=7d">Kills (7 Days)</a>
                <a href="/kills?period=30d">Kills (30 Days)</a>
                <a href="/players">Players</a>
                <a href="/systems">Active Systems</a>
            </div>
        </div>
        <div class="sidebar-card">
            <h3>Server Stats</h3>
            <div class="sidebar-stats">
                <div class="sidebar-stat">
                    <span class="sidebar-stat-label">Online</span>
                    <span class="sidebar-stat-value" style="color:<?= $onlinePlayers > 0 ? 'var(--accent)' : 'var(--danger)' ?>"><?= number_format($onlinePlayers) ?></span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-label">Accounts</span>
                    <span class="sidebar-stat-value"><?= number_format($totalAccounts) ?></span>
                </div>
                <div class="sidebar-stat">
                    <span class="sidebar-stat-label">Characters</span>
                    <span class="sidebar-stat-value"><?= number_format($totalCharacters) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
render_layout('Home', 'home', $content);
