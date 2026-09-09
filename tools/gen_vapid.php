<?php
// tools/gen_vapid.php — generate a VAPID keypair for Web Push.
// Run from the portal host:  php tools/gen_vapid.php
// Paste the two base64url strings into config.php:
//   VAPID_PUBLIC_KEY  = '<public>'
//   VAPID_PRIVATE_KEY = '<private>'
// (public = raw 65-byte uncompressed EC point; private = DER PKCS#8, both base64url.)
error_reporting(E_ALL);

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "php-openssl is not loaded.\n");
    exit(1);
}

$res = openssl_pkey_new([
    'private_key_type' => OPENSSL_KEYTYPE_EC,
    'curve_name'       => 'prime256v1',
]);
if ($res === false) {
    fwrite(STDERR, "key generation failed\n");
    exit(1);
}

if (!openssl_pkey_export($res, $privPem)) {
    fwrite(STDERR, "private export failed\n");
    exit(1);
}
$privDer = base64_decode((string)preg_replace('/-----(BEGIN|END) PRIVATE KEY-----|\s/', '', $privPem));

$details = openssl_pkey_get_details($res);
$pubPem = $details['key'];
$pubDer = base64_decode((string)preg_replace('/-----(BEGIN|END) PUBLIC KEY-----|\s/', '', $pubPem));
// prime256v1 SPKI = prefix || 0x04 || X(32) || Y(32); the point is the last 65 bytes.
$rawPublic = substr($pubDer, -65);

function b64url_encode(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }

if (strlen($rawPublic) !== 65) {
    fwrite(STDERR, "unexpected public key length: " . strlen($rawPublic) . " (expected 65)\n");
    exit(1);
}

echo "VAPID_PUBLIC_KEY  = '" . b64url_encode($rawPublic) . "'\n";
echo "VAPID_PRIVATE_KEY = '" . b64url_encode($privDer) . "'\n";
echo "\nEnable PUSH_ENABLED in config.php once the portal is behind HTTPS.\n";
