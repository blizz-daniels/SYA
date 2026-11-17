<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/admin-security.php';
function expect(bool $condition, string $name): void { if (!$condition) { throw new RuntimeException($name); } }
expect(!strong_private_code('1234'), 'Short code rejected');
expect(!strong_private_code(str_repeat('Ab1!', 8)), 'Low-diversity code rejected');
expect(!strong_private_code(str_repeat('a', 73)), 'Bcrypt truncation boundary rejected');
expect(!strong_private_code("Sya-0123456789abcdefghijkl\0"), 'Null byte rejected');
expect(strong_private_code('Sya-0123456789abcdefghijklXYZ!'), 'Strong code accepted');
expect(!valid_username("root' OR 1=1 --"), 'Invalid username rejected');
expect(valid_username('casting_01'), 'Normal username accepted');
expect(admin_modules('unknown') === [], 'Unknown role has no modules');
foreach (['casting_manager', 'crew_coordinator', 'content_editor'] as $role) { expect(!in_array('administration', admin_modules($role), true), 'Only Super Admin manages accounts'); }
expect(password_get_info(hash_private_code('Sya-0123456789abcdefghijklXYZ!'))['options']['cost'] === 12, 'Fixed bcrypt cost across supported PHP versions');
$config['environment'] = 'production';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1'; $_SERVER['HTTPS'] = ''; $_SERVER['SERVER_PORT'] = '80';
try { admin_start(); throw new RuntimeException('Production HTTP accepted'); }
catch (DomainException $error) { expect(str_contains($error->getMessage(), 'HTTPS'), 'Production HTTP refused'); }
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
try { admin_start(); throw new RuntimeException('Forged forwarded HTTPS accepted'); }
catch (DomainException $error) { expect(str_contains($error->getMessage(), 'HTTPS'), 'Forwarded header cannot bypass HTTPS'); }
$_SERVER['HTTPS'] = 'on'; $_SERVER['SERVER_PORT'] = '443';
$config['base_url'] = 'https://example.invalid/public';
admin_start();
$params = session_get_cookie_params();
expect($params['path'] === '/public/', 'Absolute base URL yields correct cookie path');
expect($params['secure'] && $params['httponly'] && $params['samesite'] === 'Lax', 'HTTPS cookies are Secure, HttpOnly and SameSite');
expect(ini_get('session.use_strict_mode') === '1', 'Strict session mode');
expect(ini_get('session.use_only_cookies') === '1', 'Cookie-only sessions');
end_admin_session();
echo "PASS private-code rules, role defaults, HTTPS enforcement, forged proxy header rejection and secure session configuration.\n";
