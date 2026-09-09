<?php
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../portal_rules.php';
if (is_logged_in()) { redirect('/characters'); return; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $agree = isset($_POST['agree']);

    if (strlen($name) < 3 || strlen($name) > 40)
        $error = 'Account name: 3-40 characters';
    elseif (strlen($pass) < 6)
        $error = 'Password: minimum 6 characters';
    elseif ($pass !== $pass2)
        $error = 'Passwords do not match';
    elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $error = 'Email is required and must be valid (used for login codes).';
    elseif (!$agree)
        $error = 'You must accept the server rules to register.';
    else {
        $clientIP = client_ip();
        $xml = api_post('/auth/Register.xml.aspx', "name=" . urlencode($name) . "&password=" . urlencode($pass) . "&email=" . urlencode($email) . "&ip=" . urlencode($clientIP));
        if ($xml && $xml->result && $xml->result->accountid) {
            $success = 'Account created! You can now log in.';
        } elseif ($xml && $xml->error) {
            $error = (string)$xml->error;
        } else {
            $error = 'Server error. Please try again later.';
        }
    }
}

ob_start();
?>
<div class="form-page">
    <div class="form-card">
        <h2>Register</h2>
        <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="form-success"><?= e($success) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Account Name</label>
                <input name="name" required maxlength="40" autofocus>
            </div>
            <div class="form-group">
                <label>Email (required)</label>
                <input name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input name="password" type="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input name="password2" type="password" required>
            </div>
            <div class="form-group">
                <details style="border:1px solid var(--border);border-radius:8px;padding:8px 12px;background:var(--bg-input)">
                    <summary style="cursor:pointer;font-weight:700">Правила сервера (обязательно к прочтению)</summary>
                    <div style="max-height:260px;overflow:auto;margin-top:8px;padding-right:8px;white-space:pre-wrap;line-height:1.5;font-size:12.5px"><?= e(server_rules_text()) ?></div>
                </details>
                <label style="display:flex;gap:8px;align-items:flex-start;margin-top:10px;font-weight:400">
                    <input type="checkbox" name="agree" value="1" <?= !empty($_POST['agree']) ? 'checked' : '' ?> required>
                    <span>Я прочитал(а) и принимаю <a href="/rules" target="_blank" rel="noopener">правила игрового сервера</a>.</span>
                </label>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
        <div class="form-footer">Already have an account? <a href="/login">Login</a></div>
    </div>
</div>
<?php
render_layout('Register', '', ob_get_clean());
