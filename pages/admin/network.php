<?php
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ban_ip') {
    $ip = trim($_POST['ip'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if ($ip !== '') {
        $xml = api_post('/admin/BanByIP.xml.aspx', 'ip=' . urlencode($ip) . '&reason=' . urlencode($reason));
        $msg = $xml && $xml->result ? 'Все аккаунты по IP ' . e($ip) . ' заблокированы' : 'Ошибка';
    }
}

$group = ($_GET['group'] ?? 'ip') === 'email' ? 'email' : 'ip';

// Offline MaxMind GeoLite2 lookup (if DB present at /geo/GeoLite2-City.mmdb).
require_once __DIR__ . '/../../lib/MaxMind/Db/Reader.php';
$GLOBALS['__geoReader'] = null;
$GLOBALS['__asnReader'] = null;
function geo_txt($ip) {
    if ($ip === '' || $ip === '(нет)' || filter_var($ip, FILTER_VALIDATE_IP) === false) return '';
    try {
        if ($GLOBALS['__geoReader'] === null) {
            $db = __DIR__ . '/../../geo/GeoLite2-City.mmdb';
            if (is_file($db)) $GLOBALS['__geoReader'] = new \MaxMind\Db\Reader($db);
        }
        $parts = [];
        if ($GLOBALS['__geoReader'] !== null) {
            $r = $GLOBALS['__geoReader']->get($ip);
            if (is_array($r)) {
                if (!empty($r['country']['names']['ru'])) $parts[] = $r['country']['names']['ru'];
                elseif (!empty($r['country']['names']['en'])) $parts[] = $r['country']['names']['en'];
                if (!empty($r['subdivisions'][0]['names']['ru'])) $parts[] = $r['subdivisions'][0]['names']['ru'];
                elseif (!empty($r['subdivisions'][0]['names']['en'])) $parts[] = $r['subdivisions'][0]['names']['en'];
                if (!empty($r['city']['names']['ru'])) $parts[] = $r['city']['names']['ru'];
                elseif (!empty($r['city']['names']['en'])) $parts[] = $r['city']['names']['en'];
            }
        }
        // ASN / provider (optional GeoLite2-ASN.mmdb)
        if ($GLOBALS['__asnReader'] === null) {
            $db = __DIR__ . '/../../geo/GeoLite2-ASN.mmdb';
            if (is_file($db)) $GLOBALS['__asnReader'] = new \MaxMind\Db\Reader($db);
        }
        if ($GLOBALS['__asnReader'] !== null) {
            $a = $GLOBALS['__asnReader']->get($ip);
            if (is_array($a)) {
                if (!empty($a['autonomous_system_organization']))
                    $parts[] = $a['autonomous_system_organization'];
                elseif (!empty($a['autonomous_system_number']))
                    $parts[] = 'AS' . $a['autonomous_system_number'];
            }
        }
        return implode(', ', $parts);
    } catch (\Throwable $e) {
        return '';
    }
}

$accounts = [];
$xml = api_get('/admin/AccountsNetwork.xml.aspx');
if ($xml && $xml->result && $xml->result->accounts)
    foreach ($xml->result->accounts->row as $r) $accounts[] = $r;

$groups = [];
foreach ($accounts as $a) {
    $key = $group === 'email' ? (string)$a['email'] : (string)$a['ip'];
    if ($key === '') $key = '(нет)';
    $groups[$key][] = $a;
}
// sort by group size desc
uasort($groups, function($x, $y) { return count($y) - count($x); });
?>

<h2 style="margin-bottom:16px">Сеть / группировка аккаунтов</h2>
<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div style="margin-bottom:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <a href="/admin/network" class="btn <?= $group==='ip'?'btn-primary':'btn-outline' ?>" style="width:auto;padding:5px 12px;font-size:12px">По IP</a>
    <a href="/admin/network?group=email" class="btn <?= $group==='email'?'btn-primary':'btn-outline' ?>" style="width:auto;padding:5px 12px;font-size:12px">По e-mail</a>
    <span style="color:var(--text-dim);font-size:12px">групп: <?= count($groups) ?>, аккаунтов: <?= count($accounts) ?></span>
</div>

<?php foreach ($groups as $key => $list): if (count($list) < 1) continue; ?>
<div class="form-card" style="margin-bottom:12px">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
        <div>
            <b style="font-size:14px"><?= $group==='ip' ? '🌐' : '✉️' ?> <?= e($key) ?></b>
            <span style="color:var(--text-dim);font-size:12px;margin-left:8px">аккаунтов: <?= count($list) ?></span>
            <?php if ($group==='ip' && $key !== '(нет)'): $geo = geo_txt($key); if ($geo !== ''): ?>
                <div style="color:var(--text-dim);font-size:12px">📍 <?= e($geo) ?></div>
            <?php endif; endif; ?>
        </div>
        <?php if ($group === 'ip' && $key !== '(нет)'): ?>
            <form method="POST" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="action" value="ban_ip">
                <input type="hidden" name="ip" value="<?= e($key) ?>">
                <input type="text" name="reason" placeholder="причина (уйдёт в Telegram)" style="width:180px;padding:4px 8px;font-size:11px;background:#1a1a1a;border:1px solid var(--border);border-radius:4px;color:var(--text)">
                <button class="btn btn-danger" style="width:auto;padding:4px 10px;font-size:11px" onclick="return confirm('Заблокировать все аккаунты с IP <?= e($key) ?>?')">Бан IP</button>
            </form>
        <?php endif; ?>
    </div>
    <table class="data-table" style="margin-top:8px">
        <thead><tr><th>ID</th><th>Аккаунт</th><th>e-mail</th><th>Роль</th><th>Статус</th></tr></thead>
        <tbody>
        <?php foreach ($list as $a): $role=(int)$a['role']; $banned=(int)$a['banned']; ?>
            <tr>
                <td><a href="/admin/account/<?= (int)$a['accountid'] ?>" style="color:var(--accent2)"><?= (int)$a['accountid'] ?></a></td>
                <td><a href="/admin/account/<?= (int)$a['accountid'] ?>" style="color:var(--text);font-weight:600;text-decoration:none"><?= e($a['accountname']) ?></a></td>
                <td style="color:var(--text-dim)"><?= e($a['email']) ?: '—' ?></td>
                <td>
                    <?php if ($role & ROLE_ADMIN): ?><span class="badge badge-admin">Admin</span>
                    <?php elseif ($role & (ROLE_GMH|ROLE_GML)): ?><span class="badge badge-gm">GM</span>
                    <?php else: ?><span class="badge badge-player">Player</span><?php endif; ?>
                </td>
                <td>
                    <?= (int)$a['online'] ? '<span class="badge badge-open">Online</span>' : '<span class="badge badge-closed">Offline</span>' ?>
                    <?= $banned ? '<span class="badge badge-banned">BANNED</span>' : '' ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
