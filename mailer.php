<?php
// mailer.php — configurable SMTP client for the portal (pure PHP, no deps).
// Used for test mails now; later for registration verification + 2FA codes.
//
// Config lives in config.php (MAIL_*). Encryption: 'tls' (STARTTLS, default),
// 'ssl' (implicit TLS on connect) or 'none'. Auth: LOGIN/PLAIN over EHLO.

if (function_exists('portal_mail_send')) return;

function _smtp_read_response($sock, &$code) {
    $all = '';
    $code = 0;
    while (($line = fgets($sock, 515)) !== false) {
        $all .= $line;
        if (strlen($line) >= 4 && is_numeric(substr($line, 0, 3)) && substr($line, 3, 1) === ' ') {
            $code = (int)substr($line, 0, 3);
            break;
        }
    }
    return $all;
}

function _smtp_cmd($sock, $cmd, &$code) {
    if ($cmd !== null) fwrite($sock, $cmd . "\r\n");
    return _smtp_read_response($sock, $code);
}

function _smtp_ehlo_lines($sock, $host) {
    // 250-.... multiline until the line that ends with a space (250 ...)
    fwrite($sock, "EHLO " . $host . "\r\n");
    $lines = [];
    $code = 0;
    $done = false;
    while (!$done && ($line = fgets($sock, 515)) !== false) {
        if (strlen($line) >= 4 && is_numeric(substr($line, 0, 3))) {
            $code = (int)substr($line, 0, 3);
            $lines[] = trim(substr($line, 4));
            if (substr($line, 3, 1) === ' ') $done = true;
        }
    }
    return [$code, $lines];
}

function _rfc2047($text) {
    if (preg_match('/[^\x20-\x7E]/', $text))
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    return $text;
}

/**
 * Send an email through the configured SMTP server.
 *
 * @param string      $to      recipient address
 * @param string      $subject subject (UTF-8, any chars)
 * @param string      $body    message body
 * @param bool        $html    true → Content-Type text/html, else text/plain
 * @return array{bool,string}  [ok, error-detail]
 */
function portal_mail_send(string $to, string $subject, string $body, bool $html = false): array {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED || MAIL_HOST === '')
        return [false, 'mail is not configured/enabled (MAIL_ENABLED/MAIL_HOST)'];

    $to = trim($to);
    $subject = trim($subject);
    // header-injection guard
    if ($to === '' || preg_match('/[\r\n]/', $to) || preg_match('/[\r\n]/', $subject))
        return [false, 'invalid recipient or subject'];

    $host = MAIL_HOST;
    $port = (int)MAIL_PORT;
    $user = MAIL_USER;
    $pass = MAIL_PASS;
    $from = MAIL_FROM !== '' ? MAIL_FROM : $user;
    $fromName = MAIL_FROM_NAME ?? '';
    $enc   = strtolower(MAIL_ENCRYPTION ?? 'tls');
    $timeout = (int)(MAIL_TIMEOUT ?? 30);

    $transport = 'tcp';
    $connectHost = $host;
    if ($enc === 'ssl') {
        $transport = 'ssl';
    }
    $errno = 0; $errstr = '';
    $sock = @stream_socket_client(
        $transport . '://' . $connectHost . ':' . $port,
        $errno, $errstr, $timeout,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
    );
    if ($sock === false)
        return [false, "connect failed ($errno): $errstr"];
    stream_set_timeout($sock, $timeout);

    $code = 0;
    $greet = _smtp_read_response($sock, $code);
    if ($code !== 220) {
        $meta = stream_get_meta_data($sock);
        $why = $meta['timed_out'] ? ' (timed out — wrong port/encryption? for port 465 use ssl, for 587 use tls)' : ' (connection closed)';
        $partial = trim($greet);
        fclose($sock);
        return [false, 'unexpected greeting: ' . $code . $why . ($partial !== '' ? ' — server said: ' . $partial : '')];
    }

    list($code, $ext) = _smtp_ehlo_lines($sock, 'portal');
    if ($code !== 250) { fclose($sock); return [false, "EHLO rejected: $code"]; }
    $cap = implode("\n", $ext);

    // STARTTLS
    if ($enc === 'tls' && stripos($cap, 'STARTTLS') !== false) {
        _smtp_cmd($sock, 'STARTTLS', $code);
        if ($code !== 220) { fclose($sock); return [false, "STARTTLS failed: $code"]; }
        $ok = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if (!$ok) { fclose($sock); return [false, 'TLS negotiation failed']; }
        list($code, $ext) = _smtp_ehlo_lines($sock, 'portal');
        if ($code !== 250) { fclose($sock); return [false, "EHLO after TLS rejected: $code"]; }
        $cap = implode("\n", $ext);
    }

    // AUTH (LOGIN preferred, PLAIN fallback)
    if ($user !== '') {
        if (stripos($cap, 'AUTH') === false) { fclose($sock); return [false, 'server does not advertise AUTH']; }
        if (stripos($cap, 'LOGIN') !== false) {
            _smtp_cmd($sock, 'AUTH LOGIN', $code);
            if ($code !== 334) { fclose($sock); return [false, "AUTH LOGIN rejected: $code"]; }
            _smtp_cmd($sock, base64_encode($user), $code);
            if ($code !== 334) { fclose($sock); return [false, "AUTH username rejected: $code"]; }
            _smtp_cmd($sock, base64_encode($pass), $code);
            if ($code !== 235) { fclose($sock); return [false, "AUTH password rejected: $code"]; }
        } elseif (stripos($cap, 'PLAIN') !== false) {
            $tok = base64_encode("\0" . $user . "\0" . $pass);
            _smtp_cmd($sock, 'AUTH PLAIN ' . $tok, $code);
            if ($code !== 235) { fclose($sock); return [false, "AUTH PLAIN rejected: $code"]; }
        } else {
            fclose($sock); return [false, 'no supported AUTH mechanism'];
        }
    }

    _smtp_cmd($sock, 'MAIL FROM:<' . $from . '>', $code);
    if ($code !== 250) { fclose($sock); return [false, "MAIL FROM rejected: $code"]; }
    _smtp_cmd($sock, 'RCPT TO:<' . $to . '>', $code);
    if ($code !== 250 && $code !== 251) { fclose($sock); return [false, "RCPT TO rejected: $code"]; }

    _smtp_cmd($sock, 'DATA', $code);
    if ($code !== 354) { fclose($sock); return [false, "DATA rejected: $code"]; }

    $fromHeader = ($fromName !== '' ? _rfc2047($fromName) . ' <' . $from . '>' : $from);
    $headers  = "From: " . $fromHeader . "\r\n";
    $headers .= "To: <" . $to . ">\r\n";
    $headers .= "Subject: " . _rfc2047($subject) . "\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: " . ($html ? 'text/html' : 'text/plain') . "; charset=utf-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";
    $headers .= "\r\n";
    $message = $headers . chunk_split(base64_encode($body), 76, "\r\n");
    // end-of-data marker on its own line; dot-stuff just in case
    $message .= "\r\n.\r\n";
    fwrite($sock, $message);

    _smtp_read_response($sock, $code);
    if ($code !== 250) { fclose($sock); return [false, "message rejected: $code"]; }

    _smtp_cmd($sock, 'QUIT', $code);
    fclose($sock);
    return [true, ''];
}
