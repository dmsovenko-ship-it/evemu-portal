<?php
require_once __DIR__ . '/../layout.php';
if (is_logged_in()) { redirect('/characters'); return; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pass = $_POST['password'] ?? '';

    $xml = api_post('/auth/Login.xml.aspx', "name=" . urlencode($name) . "&password=" . urlencode($pass));
    if ($xml && $xml->result) {
        $_SESSION['accountID'] = (int)$xml->result->accountid;
        $_SESSION['accountName'] = (string)$xml->result->accountname;
        $_SESSION['role'] = (int)$xml->result->role;
        redirect('/characters');
        return;
    } elseif ($xml && $xml->error) {
        $error = (string)$xml->error;
    } else {
        $error = 'Ошибка сервера.';
    }
}

ob_start();
?>
<div class="form-page">
    <div class="form-card">
        <h2>Вход</h2>
        <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Имя аккаунта</label>
                <input name="name" required autofocus>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input name="password" type="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Войти</button>
        </form>
        <div class="form-footer">Нет аккаунта? <a href="/register">Зарегистрироваться</a></div>
    </div>
</div>
<?php
render_layout('Вход', '', ob_get_clean());
