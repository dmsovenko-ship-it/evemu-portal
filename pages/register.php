<?php
require_once __DIR__ . '/../layout.php';
if (is_logged_in()) { redirect('/characters'); return; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $email = trim($_POST['email'] ?? '');

    if (strlen($name) < 3 || strlen($name) > 40)
        $error = 'Имя аккаунта: 3-40 символов';
    elseif (strlen($pass) < 6)
        $error = 'Пароль: минимум 6 символов';
    elseif ($pass !== $pass2)
        $error = 'Пароли не совпадают';
    else {
        $xml = api_post('/auth/Register.xml.aspx', "name=" . urlencode($name) . "&password=" . urlencode($pass) . "&email=" . urlencode($email));
        if ($xml && $xml->result && $xml->result->accountid) {
            $success = 'Аккаунт создан! Теперь войдите.';
        } elseif ($xml && $xml->error) {
            $error = (string)$xml->error;
        } else {
            $error = 'Ошибка сервера. Попробуйте позже.';
        }
    }
}

ob_start();
?>
<div class="form-page">
    <div class="form-card">
        <h2>Регистрация</h2>
        <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="form-success"><?= e($success) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Имя аккаунта</label>
                <input name="name" required maxlength="40" autofocus>
            </div>
            <div class="form-group">
                <label>Email (необязательно)</label>
                <input name="email" type="email">
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input name="password" type="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Повторите пароль</label>
                <input name="password2" type="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
        </form>
        <div class="form-footer">Уже есть аккаунт? <a href="/login">Войти</a></div>
    </div>
</div>
<?php
render_layout('Регистрация', '', ob_get_clean());
