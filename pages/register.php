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
        $error = 'Account name: 3-40 characters';
    elseif (strlen($pass) < 6)
        $error = 'Password: minimum 6 characters';
    elseif ($pass !== $pass2)
        $error = 'Passwords do not match';
    else {
        $xml = api_post('/auth/Register.xml.aspx', "name=" . urlencode($name) . "&password=" . urlencode($pass) . "&email=" . urlencode($email));
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
                <label>Email (optional)</label>
                <input name="email" type="email">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input name="password" type="password" required minlength="6">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input name="password2" type="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Register</button>
        </form>
        <div class="form-footer">Already have an account? <a href="/login">Login</a></div>
    </div>
</div>
<?php
render_layout('Register', '', ob_get_clean());
