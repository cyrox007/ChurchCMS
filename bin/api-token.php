#!/usr/bin/env php
<?php

declare(strict_types=1);

$partnerId = trim((string) ($argv[1] ?? 'partner'));
if (preg_match('/^[a-zA-Z0-9_.-]{1,64}$/D', $partnerId) !== 1) {
    fwrite(STDERR, "Usage: php bin/api-token.php [partner-id]\n");
    exit(2);
}

$token = 'ccms_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$hash = hash('sha256', $token);

echo "Partner: {$partnerId}\n";
echo "Token (show once): {$token}\n";
echo "SHA-256 hash for config/local.php: {$hash}\n";
echo "Do not store the plaintext token in the repository.\n";
