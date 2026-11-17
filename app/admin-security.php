<?php
declare(strict_types=1);

const ADMIN_ROLES = [
    'super_admin' => 'Super Admin', 'casting_manager' => 'Casting Manager',
    'crew_coordinator' => 'Crew Coordinator', 'content_editor' => 'Content Editor',
];
const ADMIN_MODULES = [
    'overview' => 'Overview', 'casting' => 'Casting', 'crew' => 'Crew', 'content' => 'Content',
    'media' => 'Media', 'communications' => 'Communications',
    'administration' => 'Administration', 'audit' => 'Audit log', 'settings' => 'Settings', 'profile' => 'My private code',
];
function admin_modules(string $role): array {
    return match ($role) {
        'super_admin' => array_keys(ADMIN_MODULES),
        'casting_manager' => ['overview', 'casting', 'communications', 'profile'],
        'crew_coordinator' => ['overview', 'crew', 'communications', 'profile'],
        'content_editor' => ['overview', 'content', 'media', 'profile'],
        default => [],
    };
}
function admin_url(string $module = 'overview'): string {
    global $config;
    return rtrim($config['base_url'], '/') . '/admin.php?module=' . rawurlencode($module);
}
function admin_redirect(string $module = 'overview'): never {
    header('Location: ' . admin_url($module), true, 303); exit;
}
function field(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : ''; }
function valid_username(string $name): bool { return preg_match('/\A[a-z0-9][a-z0-9_.-]{2,47}\z/D', $name) === 1; }
function strong_private_code(string $code): bool {
    if (strlen($code) < 24 || strlen($code) > 72 || preg_match('/[^\x21-\x7e]/', $code)) { return false; }
    $groups = (int)(bool)preg_match('/[a-z]/', $code) + (int)(bool)preg_match('/[A-Z]/', $code)
        + (int)(bool)preg_match('/[0-9]/', $code) + (int)(bool)preg_match('/[^a-zA-Z0-9]/', $code);
    return $groups >= 3 && count(array_unique(str_split($code))) >= 12;
}
function hash_private_code(string $code): string {
    return password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]);
}
function csrf_token(): string {
    if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}
function csrf_check(): void {
    if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], field('csrf'))) {
        http_response_code(403); throw new DomainException('This form expired. Refresh the page and try again.');
    }
}
function ip_hash(string $purpose): string {
    global $config;
    return hash_hmac('sha256', $purpose . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $config['security']['app_key']);
}
function audit(PDO $pdo, ?int $actor, string $action, ?int $target = null, string $details = ''): void {
    $stmt = $pdo->prepare('INSERT INTO audit_log (actor_id, action, target_id, details, ip_hash) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$actor, $action, $target, $details, ip_hash('audit')]);
}
/** Persistent fixed-window limits count attempts before credentials are checked. */
function rate_limit(PDO $pdo, array $buckets): bool {
    ksort($buckets); $now = time(); $allowed = true;
    $pdo->beginTransaction();
    try {
        foreach ($buckets as $key => $maximum) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO auth_rate_limits (bucket_key, attempts, expires_at) VALUES (?, 0, ?)');
            $stmt->execute([$key, $now + 900]);
            $stmt = $pdo->prepare('SELECT attempts, expires_at FROM auth_rate_limits WHERE bucket_key = ? FOR UPDATE');
            $stmt->execute([$key]); $row = $stmt->fetch();
            $count = (int)$row['expires_at'] <= $now ? 1 : min((int)$row['attempts'] + 1, 1000000);
            $expiry = (int)$row['expires_at'] <= $now ? $now + 900 : (int)$row['expires_at'];
            $stmt = $pdo->prepare('UPDATE auth_rate_limits SET attempts = ?, expires_at = ? WHERE bucket_key = ?');
            $stmt->execute([$count, $expiry, $key]);
            if ($count > $maximum) { $allowed = false; }
        }
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
    // Bounded opportunistic cleanup; no cron dependency, no raw IP addresses retained.
    $stmt = $pdo->prepare('DELETE FROM auth_rate_limits WHERE expires_at < ? LIMIT 100');
    $stmt->execute([$now - 86400]);
    return $allowed;
}
function setting(PDO $pdo, string $key, string $default): string {
    $stmt = $pdo->prepare('SELECT setting_value FROM admin_settings WHERE setting_key = ?'); $stmt->execute([$key]);
    $value = $stmt->fetchColumn(); return $value === false ? $default : (string)$value;
}
function session_idle_seconds(PDO $pdo): int {
    global $config;
    return max(300, min(3600, (int)setting($pdo, 'idle_seconds', (string)($config['session']['idle_seconds'] ?? 1800))));
}
function end_admin_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => $params['samesite']]);
    }
    session_destroy();
}
function admin_start(): PDO {
    global $config, $configPath;
    header('Cache-Control: no-store, private'); header('Pragma: no-cache');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!is_file($configPath) || !preg_match('/\A[a-f0-9]{64}\z/D', $config['security']['app_key'] ?? '')) {
        throw new RuntimeException('Private configuration is required');
    }
    $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $local = $config['environment'] === 'local' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$https && (!$local || ($config['security']['https_required'] ?? true) || ($config['session']['secure'] ?? true))) {
        http_response_code(403); throw new DomainException('Administrator access requires HTTPS. Open the HTTPS address for this website.');
    }
    if ($https) { header('Strict-Transport-Security: max-age=31536000'); }
    ini_set('display_errors', '0');
    ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1'); ini_set('session.use_trans_sid', '0');
    session_cache_limiter('');
    session_name($config['session']['name']);
    session_set_cookie_params(['lifetime' => 0, 'path' => rtrim(parse_url($config['base_url'], PHP_URL_PATH) ?: '', '/') . '/', 'secure' => $https, 'httponly' => true, 'samesite' => in_array($config['session']['same_site'] ?? '', ['Lax', 'Strict'], true) ? $config['session']['same_site'] : 'Lax']);
    if (!session_start()) { throw new RuntimeException('Session storage unavailable'); }
    $pdo = database($config);
    // Early schema check provides a safe service error instead of exposing SQL details.
    $pdo->query('SELECT setup_completed_at FROM system_state WHERE id = 1')->fetch();
    return $pdo;
}
function current_admin(PDO $pdo): ?array {
    if (!isset($_SESSION['admin_id'])) { return null; }
    $stmt = $pdo->prepare('SELECT id, username, role, is_active, auth_version FROM administrators WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]); $user = $stmt->fetch();
    $idle = session_idle_seconds($pdo);
    $expired = time() - (int)($_SESSION['last_activity'] ?? 0) >= $idle || time() - (int)($_SESSION['authenticated_at'] ?? 0) >= 28800;
    if (!$user || !$user['is_active'] || (int)$user['auth_version'] !== (int)($_SESSION['auth_version'] ?? 0) || $expired) {
        if ($user) { audit($pdo, (int)$user['id'], $expired ? 'session.expired' : 'session.revoked'); }
        end_admin_session(); admin_redirect('login');
    }
    $_SESSION['last_activity'] = time(); return $user;
}
function verify_current_code(PDO $pdo, int $id, string $code): bool {
    $stmt = $pdo->prepare('SELECT private_code_hash FROM administrators WHERE id = ?'); $stmt->execute([$id]);
    return strlen($code) <= 72 && password_verify($code, (string)$stmt->fetchColumn());
}
function admin_failure(Throwable $error): never {
    if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    $safe = $error instanceof DomainException;
    if (!$safe) { http_response_code(503); error_log('SYA administration unavailable: ' . get_class($error)); }
    $message = $safe ? $error->getMessage() : 'Administration is temporarily unavailable. Check the private configuration and database migrations.';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Administration — See You Around</title><h1>Administrator access</h1><p>' . e($message) . '</p></html>';
    exit;
}
