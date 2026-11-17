<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$token = bin2hex(random_bytes(32));
echo "Application key (security.app_key): " . bin2hex(random_bytes(32)) . "\n";
echo "Setup token hash (security.setup_token_hash): " . hash('sha256', $token) . "\n";
echo "One-time token (setup form only): " . $token . "\n";
echo "Suggested private code: Sya-" . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') . "\n";
echo "Keep these values private. Nothing was saved.\n";
