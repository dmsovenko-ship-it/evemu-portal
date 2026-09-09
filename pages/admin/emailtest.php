<?php
// /admin/emailtest — send a test e-mail through the configured SMTP server.
require_once __DIR__ . '/../../mailer.php';

$sentOk = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = trim((string)($_POST['to'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? '')) !== '' ? (string)$_POST['subject'] : 'Тест SMTP ' . SITE_NAME;
    $body    = (string)($_POST['body'] ?? '');
    if ($body === '') $body = 'Это тестовое письмо с портала ' . SITE_NAME . ".\n\nЕсли вы его видите — SMTP работает.";
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Укажите корректный e-mail получателя.';
    } else {
        $bodyText = $body . "\n\n—\nОтправлено " . date('d.m.Y H:i:s') . " с " . $_SERVER['HTTP_HOST'] . ' (' . SITE_NAME . ')';
        list($ok, $err) = portal_mail_send($to, $subject, nl2br($bodyText), true);
        if ($ok) $sentOk = true;
        else $error = 'Ошибка отправки: ' . $err;
    }
}
?>
<h3>Тест SMTP</h3>
<?php if ($sentOk): ?><div class="form-success">Письмо отправлено на <?= e($to ?? '') ?> — проверьте ящик (и спам).</div><?php endif; ?>
<?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>

<div class="form-card" style="max-width:520px">
    <?php if (!MAIL_ENABLED || MAIL_HOST === ''): ?>
        <p style="color:var(--text-dim)">SMTP выключен. Заполните MAIL_ENABLED/MAIL_HOST/MAIL_PORT/MAIL_USER/MAIL_PASS/MAIL_FROM в config.php.</p>
    <?php endif; ?>
    <form method="post" action="/admin/emailtest">
        <label style="display:block;font-size:12px;color:var(--text-dim);margin:8px 0 3px">Кому</label>
        <input type="email" name="to" required value="<?= e($_POST['to'] ?? '') ?>" style="width:100%;box-sizing:border-box;padding:7px 9px;border-radius:7px;border:1px solid var(--border);background:var(--bg-input);color:var(--text)">
        <label style="display:block;font-size:12px;color:var(--text-dim);margin:8px 0 3px">Тема</label>
        <input type="text" name="subject" value="<?= e($_POST['subject'] ?? '') ?>" style="width:100%;box-sizing:border-box;padding:7px 9px;border-radius:7px;border:1px solid var(--border);background:var(--bg-input);color:var(--text)">
        <label style="display:block;font-size:12px;color:var(--text-dim);margin:8px 0 3px">Текст</label>
        <textarea name="body" style="width:100%;box-sizing:border-box;min-height:120px;padding:7px 9px;border-radius:7px;border:1px solid var(--border);background:var(--bg-input);color:var(--text)"><?= e($_POST['body'] ?? '') ?></textarea>
        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary" <?= (!MAIL_ENABLED || MAIL_HOST === '') ? 'disabled' : '' ?>>Отправить тест</button>
        </div>
    </form>
    <p style="color:var(--text-dim);font-size:12px;margin-top:12px">
        Текущая конфигурация: <code><?= e(MAIL_HOST) ?></code>:<code><?= (int)MAIL_PORT ?></code>
        (<?= e(MAIL_ENCRYPTION) ?>), from <code><?= e(MAIL_FROM !== '' ? MAIL_FROM : MAIL_USER) ?></code>,
        auth <?= MAIL_USER !== '' ? 'on' : 'off' ?>.
    </p>
</div>
