<?php
$msg = '';
$error = '';
$author = current_user()['accountName'] ?? 'GM';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body'] ?? '');
        if ($title === '' || $body === '') {
            $error = 'Заполните заголовок и текст.';
        } else {
            $xml = api_post('/admin/PostNews.xml.aspx',
                'title=' . urlencode($title)
                . '&body=' . urlencode($body)
                . '&author=' . urlencode($author));
            if ($xml && $xml->result) {
                $msg = 'Новость опубликована и отправлена в игровой Telegram.';
            } else {
                $error = 'Не удалось опубликовать новость.';
            }
        }
    } elseif ($act === 'resend') {
        $nid = intval($_POST['newsid'] ?? 0);
        if ($nid) {
            $xml = api_post('/admin/NewsResend.xml.aspx', 'newsid=' . $nid);
            $msg = $xml && $xml->result ? "Новость #$nid повторно отправлена в Telegram." : 'Не удалось отправить.';
        }
    } elseif ($act === 'delete') {
        $nid = intval($_POST['newsid'] ?? 0);
        if ($nid) {
            $xml = api_post('/admin/NewsDelete.xml.aspx', 'newsid=' . $nid);
            $msg = $xml && $xml->result ? "Новость #$nid удалена из архива." : 'Не удалось удалить.';
        }
    }
}

// Archive list (all published news).
$news = [];
$xml = api_get('/admin/NewsList.xml.aspx');
if ($xml && $xml->result && $xml->result->news)
    foreach ($xml->result->news->row as $r) $news[] = $r;

function news_preview($s, $max = 140) {
    $s = (string)$s;
    if (strlen($s) <= $max) return $s;
    $s = substr($s, 0, $max);
    // don't split a UTF-8 char
    while (strlen($s) > 0 && (ord($s[strlen($s)-1]) & 0xC0) === 0x80) $s = substr($s, 0, -1);
    return $s . '…';
}
?>

<h2 style="margin-bottom:16px">Новости (публичное событие)</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:12px">
    Публикуется в игровой Telegram-группе и сохраняется в архиве. Из архива можно
    отправить заново или удалить навсегда.
</p>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:720px;margin-bottom:20px">
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label>Заголовок</label>
            <input name="title" maxlength="200" required placeholder="Например: Событие выходных / Обновление">
        </div>
        <div class="form-group">
            <label>Текст</label>
            <textarea name="body" rows="8" required placeholder="Текст новости..."></textarea>
        </div>
        <p style="color:var(--text-dim);font-size:11px;margin-bottom:10px">Автор: <?= e($author) ?></p>
        <button class="btn btn-primary" style="width:auto">Опубликовать</button>
    </form>
</div>

<h3 style="margin-bottom:10px;font-size:14px">Архив новостей</h3>
<?php if (empty($news)): ?>
    <p style="color:var(--text-dim);padding:20px 0;text-align:center">Пока нет опубликованных новостей.</p>
<?php else: ?>
<table class="data-table">
    <thead><tr><th>#</th><th>Дата</th><th>Автор</th><th>Заголовок</th><th>Текст</th><th style="width:180px"></th></tr></thead>
    <tbody>
    <?php foreach ($news as $n): ?>
    <tr>
        <td><?= (int)$n['newsid'] ?></td>
        <td style="color:var(--text-dim);white-space:nowrap"><?= e($n['createdat']) ?></td>
        <td><?= e($n['authorname']) ?></td>
        <td><b><?= e($n['title']) ?></b></td>
        <td style="color:var(--text-dim);max-width:420px;white-space:normal"
            title="<?= e($n['body']) ?>"><?= e(news_preview($n['body'])) ?></td>
        <td style="white-space:nowrap">
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="resend">
                <input type="hidden" name="newsid" value="<?= (int)$n['newsid'] ?>">
                <button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px">В ТГ</button>
            </form>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="newsid" value="<?= (int)$n['newsid'] ?>">
                <button class="btn btn-outline" style="width:auto;padding:4px 10px;font-size:11px;color:#cc4444;border-color:#5a2a2a"
                    onclick="return confirm('Удалить новость #<?= (int)$n['newsid'] ?> навсегда?')">Удалить</button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
