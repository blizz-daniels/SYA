<?php
declare(strict_types=1);
$pdo = admin_start();
$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) { http_response_code(405); header('Allow: GET, HEAD, POST'); exit; }
if (is_file(dirname(__DIR__) . '/public/setup.php')) {
    http_response_code(503); throw new DomainException('Remove the temporary public/setup.php file before using administrator login.');
}
$state = $pdo->query('SELECT setup_completed_at FROM system_state WHERE id = 1')->fetchColumn();
if (!$state) { http_response_code(503); throw new DomainException('Initial setup has not been completed. Follow the private setup instructions before signing in.'); }
$user = current_admin($pdo);
$module = $_GET['module'] ?? ($user ? 'overview' : 'login');
if (!is_string($module) || !isset(ADMIN_MODULES[$module]) && $module !== 'login') { http_response_code(404); throw new DomainException('Administrator page not found.'); }
$error = ''; $flash = $_SESSION['flash'] ?? []; unset($_SESSION['flash']);
if ($method === 'POST') {
    csrf_check();
    $action = field('action');
    if ($action === 'login' && !$user && $module === 'login') {
        $name = strtolower(trim(field('username'))); $code = field('private_code');
        $accountKey = hash_hmac('sha256', 'login-account:' . substr($name, 0, 48), $config['security']['app_key']);
        if (!rate_limit($pdo, [ip_hash('login') => 30, $accountKey => 6])) {
            http_response_code(429); $error = 'Sign-in could not be completed. Try again in 15 minutes.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM administrators WHERE username = ?'); $stmt->execute([valid_username($name) ? $name : '']); $account = $stmt->fetch();
            // Valid dummy bcrypt hash keeps unknown-user verification on the same expensive path.
            $hash = $account['private_code_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
            $verified = password_verify(strlen($code) <= 72 ? $code : '', $hash);
            if (!$account || !$account['is_active'] || !$verified) {
                audit($pdo, null, 'login.failed'); $error = 'Username or private code was not accepted.';
            } else {
                session_regenerate_id(true); $_SESSION = ['admin_id' => (int)$account['id'], 'auth_version' => (int)$account['auth_version'], 'authenticated_at' => time(), 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32))];
                if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
                    $stmt = $pdo->prepare('UPDATE administrators SET private_code_hash = ? WHERE id = ?'); $stmt->execute([hash_private_code($code), $account['id']]);
                }
                audit($pdo, (int)$account['id'], 'login.succeeded'); admin_redirect();
            }
        }
    } else {
        if (!$user) { http_response_code(403); throw new DomainException('Sign in before performing this action.'); }
        if (!in_array($module, admin_modules($user['role']), true)) { http_response_code(403); throw new DomainException('You do not have permission to access this module.'); }
        if ($action === 'logout') { audit($pdo, (int)$user['id'], 'logout'); end_admin_session(); admin_redirect('login'); }
        try {
            if ($action === 'import' && $module === 'administration') { $flash = import_administrators($pdo, $user); }
            elseif ($action === 'create' && $module === 'administration') {
                require_super($user); confirmation($pdo, $user); $pdo->beginTransaction();
                try { $pdo->query('SELECT id FROM system_state WHERE id = 1 FOR UPDATE')->fetch(); add_administrator($pdo, strtolower(trim(field('username'))), field('new_private_code'), field('role'), (int)$user['id']); $pdo->commit(); }
                catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $exception; }
                $flash = ['Administrator created. Share the code through a private channel.'];
            } elseif ($action === 'update' && $module === 'administration') { update_administrator($pdo, $user); $flash = ['Administrator updated. Existing sessions for that account have been revoked.']; }
            elseif ($action === 'settings' && $module === 'settings') { save_admin_settings($pdo, $user); $flash = ['Administrator settings saved.']; }
            elseif ($action === 'change_code' && $module === 'profile') {
                confirmation($pdo, $user); $new = field('new_private_code');
                if (!strong_private_code($new) || !hash_equals($new, field('repeat_private_code')) || verify_current_code($pdo, (int)$user['id'], $new)) { throw new DomainException('Use a new strong private code and enter it identically in both fields.'); }
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('UPDATE administrators SET private_code_hash = ?, auth_version = auth_version + 1 WHERE id = ?'); $stmt->execute([hash_private_code($new), $user['id']]);
                    audit($pdo, (int)$user['id'], 'private_code.changed', (int)$user['id']); $pdo->commit();
                } catch (Throwable $exception) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $exception; }
                end_admin_session(); admin_redirect('login');
            } else { http_response_code(403); throw new DomainException('This action is not permitted in this module.'); }
            $_SESSION['flash'] = $flash; admin_redirect($module);
        } catch (DomainException $exception) { $error = $exception->getMessage(); }
    }
}
if (!$user && $module !== 'login') { admin_redirect('login'); }
if ($user && $module === 'login') { admin_redirect(); }
if ($user && !in_array($module, admin_modules($user['role']), true)) { http_response_code(403); throw new DomainException('You do not have permission to access this module.'); }
$title = $module === 'login' ? 'Administrator sign in' : ADMIN_MODULES[$module];
require dirname(__DIR__) . '/resources/views/admin-layout.php';
