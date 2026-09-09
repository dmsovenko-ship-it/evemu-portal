<?php
// /login — account login with optional e-mail 2FA.
// Stage flow:
//   login   → password check via /auth/Login (server also returns account email)
//   setemail→ (legacy accounts w/o email) attach one via /auth/SetEmail
//   code    → 6-digit e-mail code (admins always; new device/IP otherwise)
// Session (accountID/accountName/role) is only created after a passed code.
require_once __DIR__ . '/../layout.php';
require_once __DIR__ . '/../mailer.php';

if (is_logged_in()) { redirect('/characters'); return; }

function _finalize_login(int $aid, string $name, int $role): void {
    session_regenerate_id(true);
    $_SESSION['accountID']   = $aid;
    $_SESSION['accountName'] = $name;
    $_SESSION['role']        = $role;
    $_SESSION['login_time']  = time();
    unset($_SESSION['2fa_pending']);
    tfa_trust_device($aid, client_ip());
}

$error = '';
$view  = 'login';
$emailMasked = '';

$pending = $_SESSION['2fa_pending'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phase = $_POST['phase'] ?? '';

    if ($phase === 'login') {
        $name = trim((string)($_POST['name'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        if ($name === '' || $pass === '') {
            $error = 'Введите имя и пароль.';
        } else {
            $xml = api_post('/auth/Login.xml.aspx', "name=" . urlencode($name) . "&password=" . urlencode($pass));
            if ($xml && isset($xml->result)) {
                $aid    = (int)$xml->result->accountid;
                $rname  = (string)$xml->result->accountname;
                $role   = (int)$xml->result->role;
                $email  = trim((string)($xml->result->email ?? ''));

                if ($email === '') {
                    // legacy account without e-mail → attach one first
                    $_SESSION['2fa_pending'] = ['stage' => 'setemail', 'aid' => $aid, 'name' => $rname, 'role' => $role, 'email' => ''];
                    $view = 'setemail';
                    $error = 'Для этого аккаунта нужен e-mail (используется для подтверждения входа).';
                } elseif (tfa_requires_code($aid, $role, client_ip())) {
                    $code = tfa_new_code();
                    $body = "Код подтверждения для входа на портал " . SITE_NAME . ":\n\n"
                          . $code . "\n\n"
                          . "Аккаунт: " . $rname . "\n"
                          . "Код действителен " . (int)(TFA_CODE_TTL / 60) . " минут.\n"
                          . "Если вы не пытались войти — проигнорируйте это письмо.";
                    list($okMail, $mailErr) = portal_mail_send($email, 'Код подтверждения ' . SITE_NAME, $body);
                    if (!$okMail) {
                        $error = 'Не удалось отправить код на почту: ' . $mailErr . ' (повторите вход позже).';
                        $view = 'login';
                    } else {
                        $_SESSION['2fa_pending'] = [
                            'stage' => 'code', 'aid' => $aid, 'name' => $rname, 'role' => $role,
                            'email' => $email, 'hash' => hash('sha256', $code),
                            'exp' => time() + TFA_CODE_TTL, 'tries' => 0, 'ip' => client_ip(),
                        ];
                        $view = 'code';
                        $emailMasked = preg_replace('/^(.{2}).*(@.*)$/u', '$1…$2', $email);
                    }
                } else {
                    _finalize_login($aid, $rname, $role);
                    redirect('/characters'); return;
                }
            } elseif ($xml && isset($xml->error)) {
                $error = (string)$xml->error;
            } else {
                $error = 'Сервер недоступен.';
            }
        }
    }

    if ($phase === 'setemail') {
        $name = trim((string)($_POST['name'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '' || $pass === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Введите имя, пароль и корректный e-mail.';
        } else {
            $xml = api_post('/auth/SetEmail.xml.aspx',
                http_build_query(['name' => $name, 'password' => $pass, 'email' => $email, 'ip' => client_ip()]));
            if ($xml && isset($xml->result)) {
                $aid  = (int)($_SESSION['2fa_pending']['aid'] ?? 0);
                $rname = (string)($_SESSION['2fa_pending']['name'] ?? $name);
                $role = (int)($_SESSION['2fa_pending']['role'] ?? 0);
                $code = tfa_new_code();
                $body = "Код подтверждения для входа на портал " . SITE_NAME . ":\n\n"
                      . $code . "\n\nАккаунт: " . $rname . "\n"
                      . "Код действителен " . (int)(TFA_CODE_TTL / 60) . " минут.";
                list($okMail, $mailErr) = portal_mail_send($email, 'Код подтверждения ' . SITE_NAME, $body);
                if (!$okMail) {
                    $error = 'Email сохранён, но не удалось отправить код: ' . $mailErr;
                    $view = 'setemail';
                } else {
                    $_SESSION['2fa_pending'] = [
                        'stage' => 'code', 'aid' => $aid, 'name' => $rname, 'role' => $role, 'email' => $email,
                        'hash' => hash('sha256', $code), 'exp' => time() + TFA_CODE_TTL,
                        'tries' => 0, 'ip' => client_ip(),
                    ];
                    $view = 'code';
                    $emailMasked = preg_replace('/^(.{2}).*(@.*)$/u', '$1…$2', $email);
                }
            } elseif ($xml && isset($xml->error)) {
                $error = (string)$xml->error;
            } else {
                $error = 'Сервер недоступен.';
            }
        }
    }

    if ($phase === 'code') {
        if (!$pending || ($pending['stage'] ?? '') !== 'code') {
            $error = 'Сессия подтверждения истекла — войдите заново.';
            $view = 'login';
        } else {
            if (client_ip() !== ($pending['ip'] ?? '')) {
                $error = 'IP изменился — начните вход заново.';
                unset($_SESSION['2fa_pending']);
                $view = 'login';
            } elseif (time() > (int)($pending['exp'] ?? 0)) {
                $error = 'Код истёк — запросите новый.';
                unset($_SESSION['2fa_pending']);
                $view = 'login';
            } else {
                $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
                if ($code !== '' && hash_equals((string)($pending['hash'] ?? ''), hash('sha256', $code))) {
                    _finalize_login((int)$pending['aid'], (string)$pending['name'], (int)$pending['role']);
                    redirect('/characters'); return;
                }
                $tries = (int)($pending['tries'] ?? 0) + 1;
                $pending['tries'] = $tries;
                if ($tries >= TFA_MAX_ATTEMPTS) {
                    $error = 'Слишком много неверных попыток — начните вход заново.';
                    unset($_SESSION['2fa_pending']);
                    $view = 'login';
                } else {
                    $error = 'Неверный код. Осталось попыток: ' . (TFA_MAX_ATTEMPTS - $tries);
                    $_SESSION['2fa_pending'] = $pending;
                    $view = 'code';
                }
            }
        }
    }

    if ($phase === 'resend') {
        if (!$pending || ($pending['stage'] ?? '') !== 'code' || ($pending['email'] ?? '') === '') {
            $view = 'login';
        } else {
            $code = tfa_new_code();
            $body = "Новый код подтверждения для входа на портал " . SITE_NAME . ":\n\n"
                  . $code . "\n\nАккаунт: " . (string)$pending['name'] . "\n"
                  . "Код действителен " . (int)(TFA_CODE_TTL / 60) . " минут.";
            list($okMail, $mailErr) = portal_mail_send((string)$pending['email'], 'Код подтверждения ' . SITE_NAME, $body);
            if ($okMail) {
                $pending['hash'] = hash('sha256', $code);
                $pending['exp']  = time() + TFA_CODE_TTL;
                $pending['tries'] = 0;
                $_SESSION['2fa_pending'] = $pending;
                $view = 'code';
                $error = 'Новый код отправлен.';
            } else {
                $error = 'Не удалось отправить код: ' . $mailErr;
                $view = 'code';
            }
        }
    }
}

// honour a previously-started pending stage on refresh
if ($view === 'login' && $pending && ($pending['stage'] ?? '') === 'code') $view = 'code';
if ($view === 'login' && $pending && ($pending['stage'] ?? '') === 'setemail') $view = 'setemail';

$pendingNow = $_SESSION['2fa_pending'] ?? null;
if ($view === 'code' && $emailMasked === '' && !empty($pendingNow['email']))
    $emailMasked = preg_replace('/^(.{2}).*(@.*)$/u', '$1…$2', (string)$pendingNow['email']);

ob_start();
?>
<div class="form-page">
    <div class="form-card">
        <?php if ($view === 'code'): ?>
            <h2>Подтверждение входа</h2>
            <p style="color:var(--text-dim);font-size:13px">
                Код отправлен на <?= e($emailMasked ?: ($pending['email'] ?? '')) ?>.<br>
                Введите его ниже.
            </p>
            <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="phase" value="code">
                <div class="form-group">
                    <label>Код из письма</label>
                    <input name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required autofocus
                           style="letter-spacing:4px;font-size:20px;text-align:center">
                </div>
                <button type="submit" class="btn btn-primary">Войти</button>
            </form>
            <form method="POST" style="margin-top:8px">
                <input type="hidden" name="phase" value="resend">
                <button type="submit" class="btn btn-outline" style="font-size:13px">Отправить код ещё раз</button>
            </form>
        <?php elseif ($view === 'setemail'): ?>
            <h2>Требуется e-mail</h2>
            <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
            <p style="color:var(--text-dim);font-size:13px">
                Аккаунт создан до введения e-mail. Привяжите его — на него будут приходить коды подтверждения входа.
            </p>
            <form method="POST">
                <input type="hidden" name="phase" value="setemail">
                <div class="form-group"><label>Account Name</label><input name="name" required value="<?= e($_POST['name'] ?? ($pending['name'] ?? '')) ?>"></div>
                <div class="form-group"><label>Password</label><input name="password" type="password" required></div>
                <div class="form-group"><label>E-mail</label><input name="email" type="email" required value="<?= e($_POST['email'] ?? '') ?>"></div>
                <button type="submit" class="btn btn-primary">Сохранить e-mail</button>
            </form>
        <?php else: ?>
            <h2>Login</h2>
            <?php if ($error): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="phase" value="login">
                <div class="form-group"><label>Account Name</label><input name="name" required autofocus value="<?= e($_POST['name'] ?? '') ?>"></div>
                <div class="form-group"><label>Password</label><input name="password" type="password" required></div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
        <?php endif; ?>
        <div class="form-footer">
            <?php if ($view !== 'setemail'): ?>
                Don't have an account? <a href="/register">Register</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
render_layout($view === 'code' ? 'Confirm login' : 'Login', '', ob_get_clean());
