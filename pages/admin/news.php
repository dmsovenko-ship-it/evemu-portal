<?php
$msg = '';
$error = '';
$author = current_user()['accountName'] ?? 'GM';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
}
?>

<h2 style="margin-bottom:16px">Новости (публичное событие)</h2>
<p style="color:var(--text-dim);font-size:12px;margin-bottom:12px">
    Публикуется в игровой Telegram-группе и сохраняется в истории.
</p>

<?php if ($msg): ?><div class="form-success"><?= e($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:720px">
    <form method="POST">
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
