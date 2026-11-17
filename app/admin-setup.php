<?php
declare(strict_types=1);
$pdo = admin_start();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'POST'], true)) { http_response_code(405); header('Allow: GET, HEAD, POST'); exit; }
$state = $pdo->query('SELECT setup_completed_at FROM system_state WHERE id = 1')->fetchColumn();
if ($state || (int)$pdo->query("SELECT COUNT(*) FROM administrators WHERE role = 'super_admin'")->fetchColumn() > 0) {
    http_response_code(410); throw new DomainException('Initial setup is permanently closed. Delete this temporary setup file.');
}
$expectedHash = $config['security']['setup_token_hash'] ?? '';
if (!preg_match('/\A[a-f0-9]{64}\z/D', $expectedHash)) { http_response_code(503); throw new DomainException('Configure a strong setup token hash before activating setup.'); }
$error = ''; $success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!rate_limit($pdo, [ip_hash('setup') => 6])) { http_response_code(429); throw new DomainException('Too many setup attempts. Try again in 15 minutes.'); }
    $token = field('setup_token'); $name = strtolower(trim(field('username'))); $code = field('private_code');
    if (!preg_match('/\A[A-Za-z0-9_-]{43,128}\z/D', $token) || count(array_unique(str_split($token))) < 12 || !hash_equals($expectedHash, hash('sha256', $token))) {
        audit($pdo, null, 'setup.token_rejected'); $error = 'Setup token was not accepted.';
    } elseif (!valid_username($name) || !strong_private_code($code) || !hash_equals($code, field('repeat_private_code'))) {
        $error = 'Use a valid username and a matching random private code that meets the requirements below.';
    } else {
        $pdo->beginTransaction();
        try {
            $row = $pdo->query('SELECT setup_completed_at FROM system_state WHERE id = 1 FOR UPDATE')->fetch();
            if (!$row || $row['setup_completed_at'] || (int)$pdo->query("SELECT COUNT(*) FROM administrators WHERE role = 'super_admin'")->fetchColumn() > 0) {
                http_response_code(410); throw new DomainException('Initial setup is permanently closed.');
            }
            $stmt = $pdo->prepare("INSERT INTO administrators (username, private_code_hash, role) VALUES (?, ?, 'super_admin')"); $stmt->execute([$name, hash_private_code($code)]);
            $id = (int)$pdo->lastInsertId();
            $pdo->exec('UPDATE system_state SET setup_completed_at = UTC_TIMESTAMP() WHERE id = 1');
            audit($pdo, $id, 'setup.completed', $id); $pdo->commit();
            session_regenerate_id(true); $_SESSION = []; $success = true;
        } catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $exception; }
    }
}
$title = 'First Super Admin setup';
require dirname(__DIR__) . '/resources/views/admin-setup.php';
