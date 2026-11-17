<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
if ($config['environment'] !== 'local' || !str_ends_with($config['database']['name'], '_test')) { exit('Refusing non-test database'); }
$pdo = database($config);
$command = $argv[1] ?? '';
if (in_array($command, ['EXPIRE_SESSION', 'EXPIRE_ABSOLUTE'], true)) {
    $id = (int)$pdo->query("SELECT id FROM administrators WHERE username = 'rootadmin'")->fetchColumn();
    $directory = session_save_path() ?: sys_get_temp_dir();
    foreach (glob($directory . '/sess_*') as $file) {
        $data = file_get_contents($file);
        if (str_contains($data, 'admin_id|i:' . $id . ';')) {
            $key = $command === 'EXPIRE_SESSION' ? 'last_activity' : 'authenticated_at';
            file_put_contents($file, preg_replace('/' . $key . '\\|i:\\d+;/', $key . '|i:1;', $data));
        }
    }
    echo 'expired';
} elseif ($command === 'CLEAR_LIMITS') { $pdo->exec('DELETE FROM auth_rate_limits'); echo 'cleared'; }
elseif ($command === 'DELETE_ALL_TEST_ACCOUNTS') { $pdo->exec('DELETE FROM administrators'); echo 'deleted'; }
elseif ($command === 'CHECK_SECRETS') {
    $fixture = json_decode(file_get_contents(__DIR__ . '/artifacts/admin-fixture.json'), true);
    $all = '';
    foreach (['administrators', 'audit_log', 'admin_settings', 'auth_rate_limits'] as $table) { $all .= json_encode($pdo->query('SELECT * FROM ' . $table)->fetchAll()); }
    foreach ($fixture as $secret) { if (str_contains($all, $secret)) { throw new RuntimeException('Plaintext secret retained'); } }
    foreach ($pdo->query('SELECT private_code_hash FROM administrators') as $row) { if (!str_starts_with($row['private_code_hash'], '$2y$')) { throw new RuntimeException('Expected password_hash output'); } }
    echo 'clean';
} elseif (preg_match('/^SELECT /', $command)) { echo $pdo->query($command)->fetchColumn(); }
else { exit('Unknown test command'); }
