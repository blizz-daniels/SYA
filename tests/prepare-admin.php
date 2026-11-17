<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SYA_TEST_RESET') !== 'YES') { exit("CLI only; SYA_TEST_RESET=YES required.\n"); }
require dirname(__DIR__) . '/app/bootstrap.php';
if (!str_ends_with($config['database']['name'], '_test') || $config['environment'] !== 'local') { exit("Refusing to reset a database without _test suffix and local environment.\n"); }
$pdo = database($config);
foreach (['audit_log', 'admin_settings', 'auth_rate_limits', 'administrators'] as $table) { $pdo->exec('DELETE FROM ' . $table); }
$pdo->exec('UPDATE system_state SET setup_completed_at = NULL WHERE id = 1');
$token = bin2hex(random_bytes(32));
$config['security']['setup_token_hash'] = hash('sha256', $token);
file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n");
$fixtures = ['token' => $token, 'super' => 'Root-' . bin2hex(random_bytes(20)), 'casting' => 'Cast-' . bin2hex(random_bytes(20)), 'crew' => 'Crew-' . bin2hex(random_bytes(20)), 'editor' => 'Edit-' . bin2hex(random_bytes(20)), 'replacement' => 'Reset-' . bin2hex(random_bytes(20))];
@mkdir(dirname(__DIR__) . '/tests/artifacts', 0700, true);
file_put_contents(dirname(__DIR__) . '/tests/artifacts/admin-fixture.json', json_encode($fixtures));
copy(dirname(__DIR__) . '/public/setup.php.dist', dirname(__DIR__) . '/public/setup.php');
echo "Disposable test database reset and setup fixture activated. Never use this script on a real installation.\n";
