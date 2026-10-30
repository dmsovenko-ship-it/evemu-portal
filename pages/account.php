<?php
// /account - Alpha/Omega subscription status + PLEX activation (portal side).
require_once __DIR__ . '/../layout.php';
if (!is_logged_in()) { redirect('/login'); return; }

$u = current_user();
$accountID = (int)($u['accountid'] ?? 0);
$msg = null;
$error = null;

function sub_premium_status(int $accountID) {
    $sx = api_get('/char/PremiumStatus.xml.aspx?accountid=' . $accountID, 5);
    if ($sx && isset($sx->result) && isset($sx->result->omega))
        return array(
            'omega' => ((string)$sx->result->omega === '1'),
            'until' => (int)$sx->result->premiumuntil,
            'days'  => (int)$sx->result->daysleft,
        );
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'activate') {
    $cid = (int)($_POST['characterid'] ?? 0);
    if ($cid <= 0) {
        $error = 'Выберите персонажа, у которого в ангаре лежит PLEX.';
    } else {
        $x = api_post('/char/ActivatePlex.xml.aspx', "accountid=$accountID&characterid=$cid");
        if ($x && isset($x->error)) {
            $error = 'Не удалось активировать PLEX: ' . (string)$x->error;
        } elseif ($x && isset($x->result->premiumuntil)) {
            $msg = 'Омега активирована на ' . (int)$x->result->days . ' дней.';
        } else {
            $error = 'Сервер не ответил.';
        }
    }
}

$status = sub_premium_status($accountID);

$chars = array();
$cl = api_get('/char/CharacterList.xml.aspx?accountid=' . $accountID, 5);
if ($cl && isset($cl->result) && isset($cl->result->rowset))
    foreach ($cl->result->rowset->row as $r)
        $chars[] = array('id' => (int)$r['characterid'], 'name' => (string)$r['name']);

ob_start();
?>
<div class="section">
    <h1 class="section-title">Подписка</h1>

    <?php if ($msg): ?><div class="form-success" style="margin-bottom:14px"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="form-error" style="margin-bottom:14px"><?= e($error) ?></div><?php endif; ?>

    <div class="panel" style="padding:18px;margin-bottom:18px">
        <?php if ($status === null): ?>
            <p style="color:var(--text-dim);font-size:13px">Не удалось получить статус подписки.</p>
        <?php elseif ($status['omega']): ?>
            <p style="font-size:15px"><b style="color:#8fe3a2">OMEGA</b> — полный доступ.</p>
            <p style="font-size:13px;color:var(--text-dim)">
                Действует до <?= e(date('d.m.Y H:i', filetime_to_unix($status['until']))) ?>
                (осталось <?= (int)$status['days'] ?> дн.)
            </p>
        <?php else: ?>
            <p style="font-size:15px"><b style="color:#f0c674">ALPHA</b> — бесплатный доступ.</p>
            <p style="font-size:13px;color:var(--text-dim)">
                Доступны корабли до линкора и базовые скиллы. Капиталы, POS, клоаки и T2/T3 — только для Omega.
            </p>
        <?php endif; ?>
    </div>

    <h2 class="section-title" style="font-size:16px;margin-bottom:10px">Активировать PLEX</h2>
    <p style="color:var(--text-dim);font-size:12px;margin-bottom:12px">
        Нужен 1 PLEX (предмет) в ангаре персонажа на станции. Он будет погашен, аккаунт станет Omega на 30 дней.
    </p>
    <?php if (!$chars): ?>
        <p style="color:var(--text-dim);font-size:12px">Персонажи не найдены.</p>
    <?php else: ?>
    <form method="POST" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="hidden" name="action" value="activate">
        <select name="characterid" style="background:#0d1117;border:1px solid var(--border);color:var(--text);padding:6px 10px;border-radius:3px;font-size:13px">
            <?php foreach ($chars as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?> (<?= (int)$c['id'] ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn" style="width:auto;padding:7px 18px">Активировать PLEX</button>
    </form>
    <?php endif; ?>
</div>
<?php
render_layout('Подписка', 'account', ob_get_clean());
