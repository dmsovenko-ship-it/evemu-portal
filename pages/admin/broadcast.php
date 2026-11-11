<?php
$msg = '';
$error = '';
$author = current_user()['accountName'] ?? 'GM';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'send') {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $mode = $_POST['mode'] ?? 'now';
        $sendat = 0;
        if ($subject === '' || $body === '') {
            $error = 'Заполните тему и текст рассылки.';
        } else {
            if ($mode === 'scheduled') {
                $when = trim($_POST['when'] ?? '');
                $dt = $when !== '' ? DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $when, new DateTimeZone('UTC')) : false;
                if (!$dt) {
                    $error = 'Укажите корректные дату и время (UTC).';
                } else {
                    $sendat = $dt->getTimestamp();
                    if ($sendat <= time() + 5)
                        $error = 'Запланированное время должно быть в будущем.';
                }
            }
            if ($error === '') {
                $xml = api_post('/admin/Broadcast.xml.aspx',
                    'subject=' . urlencode($subject)
                    . '&body=' . urlencode($body)
                    . '&author=' . urlencode($author)
                    . '&sendat=' . $sendat);
                if ($xml && $xml->result) {
                    $status = (string)($xml->result->status ?? '');
                    if ($status === 'scheduled') {
                        $msg = 'Рассылка #' . (int)$xml->result->broadcastid . ' запланирована на '
                             . gmdate('d.m.Y H:i', $sendat) . ' UTC.';
                    } else {
                        $msg = 'Рассылка отправлена: получателей ' . (int)($xml->result->recipients ?? 0)
                             . ', сообщение #' . (int)($xml->result->messageid ?? 0) . '.';
                    }
                } else {
                    $error = 'Не удалось создать рассылку.';
                }
            }
        }
    } elseif ($act === 'cancel') {
        $bid = (int)($_POST['broadcastid'] ?? 0);
        if ($bid > 0) {
            $xml = api_post('/admin/BroadcastCancel.xml.aspx', 'broadcastid=' . $bid);
            $msg = $xml && $xml->result ? "Рассылка #$bid отменена." : 'Не удалось отменить рассылку.';
        }
    }
}

$rows = [];
$xml = api_get('/admin/BroadcastList.xml.aspx');
if ($xml && $xml->result && $xml->result->broadcasts)
    foreach ($xml->result->broadcasts->row as $r) $rows[] = $r;

$statusLabel = [0 => 'Запланировано', 1 => 'Отправлено', 2 => 'Отменено'];
$statusColor = [0 => '#d9a441', 1 => '#4caf50', 2 => '#888888'];
?>
<h2 style="margin-bottom:16px">Рассылка игрокам</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:12px">
    Системное письмо в игровую почту <b>всем реальным игрокам</b> (челоботы исключены).
    Отправитель — <b>CONCORD Assembly</b>. Время расписания указывается в UTC.
</p>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:720px;margin-bottom:20px">
    <form method="POST">
        <input type="hidden" name="action" value="send">
        <div class="form-group">
            <label>Тема</label>
            <input name="subject" maxlength="200" required placeholder="Например: Плановые работы на сервере">
        </div>
        <div class="form-group">
            <label>Текст</label>
            <textarea name="body" rows="8" required placeholder="Текст системного письма..."></textarea>
        </div>
        <div class="form-group">
            <label>Отправка</label>
            <label style="display:inline-block;margin-right:18px">
                <input type="radio" name="mode" value="now" checked onchange="document.getElementById('whenrow').style.display='none'"> Сейчас
            </label>
            <label style="display:inline-block">
                <input type="radio" name="mode" value="scheduled" onchange="document.getElementById('whenrow').style.display='block'"> По расписанию
            </label>
        </div>
        <div class="form-group" id="whenrow" style="display:none">
            <label>Дата и время (UTC)</label>
            <input type="datetime-local" name="when" value="<?= e(gmdate('Y-m-d\TH:i', time() + 3600)) ?>">
        </div>
        <p style="color:var(--text-dim);font-size:11px;margin-bottom:10px">Автор: <?= e($author) ?></p>
        <button class="btn btn-primary" style="width:auto">Отправить</button>
    </form>
</div>

<h3 style="margin-bottom:10px;font-size:14px">История рассылок</h3>
<?php if (empty($rows)): ?>
    <p style="color:var(--text-dim);padding:20px 0;text-align:center">Рассылок ещё не было.</p>
<?php else: ?>
<table class="data-table">
    <thead><tr><th>#</th><th>Статус</th><th>Тема</th><th>План (UTC)</th><th>Отправлено</th><th>Получатели</th><th style="width:110px"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <?php
            $st = (int)$r['status'];
            $sched = (int)$r['scheduledat'];
            $sent = (int)$r['sentat'];
        ?>
        <tr>
            <td><?= (int)$r['broadcastid'] ?></td>
            <td style="color:<?= $statusColor[$st] ?? '#ccc' ?>;white-space:nowrap"><?= e($statusLabel[$st] ?? ('#' . $st)) ?></td>
            <td><b><?= e($r['subject']) ?></b><br>
                <span style="color:var(--text-dim);font-size:11px"><?= e(shorten_text($r['body'], 120)) ?></span></td>
            <td style="color:var(--text-dim);white-space:nowrap"><?= $sched ? e(gmdate('d.m H:i', $sched)) : '—' ?></td>
            <td style="color:var(--text-dim);white-space:nowrap"><?= $sent ? e(gmdate('d.m H:i', $sent)) : '—' ?></td>
            <td><?= (int)$r['sentcount'] ?></td>
            <td>
                <?php if ($st === 0): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="broadcastid" value="<?= (int)$r['broadcastid'] ?>">
                    <button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px;color:#cc4444;border-color:#5a2a2a"
                        onclick="return confirm('Отменить рассылку #<?= (int)$r['broadcastid'] ?>?')">Отменить</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
