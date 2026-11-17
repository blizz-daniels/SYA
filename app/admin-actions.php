<?php
declare(strict_types=1);

function require_super(array $user): void {
    if ($user['role'] !== 'super_admin') { http_response_code(403); throw new DomainException('You do not have permission to perform this action.'); }
}
function confirmation(PDO $pdo, array $user): void {
    if (!rate_limit($pdo, [ip_hash('confirmation') => 30, hash('sha256', 'confirmation:' . $user['id']) => 10])) {
        http_response_code(429); throw new DomainException('Too many confirmation attempts. Try again in 15 minutes.');
    }
    if (!verify_current_code($pdo, (int)$user['id'], field('confirm_code'))) {
        audit($pdo, (int)$user['id'], 'confirmation.failed');
        throw new DomainException('Your current private code could not be verified.');
    }
}
function add_administrator(PDO $pdo, string $name, string $code, string $role, int $actor): int {
    if (!valid_username($name)) { throw new DomainException('Username must be 3–48 lowercase letters, digits, dots, underscores or hyphens, starting with a letter or digit.'); }
    if (!isset(ADMIN_ROLES[$role])) { throw new DomainException('Select a valid administrator role.'); }
    if (!strong_private_code($code)) { throw new DomainException('Use a random private code of 24–72 printable characters, with at least three character types and 12 distinct characters.'); }
    $stmt = $pdo->prepare('SELECT id FROM administrators WHERE username = ?'); $stmt->execute([$name]);
    if ($stmt->fetchColumn()) { throw new DomainException('That username already exists.'); }
    $stmt = $pdo->prepare('INSERT INTO administrators (username, private_code_hash, role) VALUES (?, ?, ?)');
    $stmt->execute([$name, hash_private_code($code), $role]);
    $id = (int)$pdo->lastInsertId(); audit($pdo, $actor, 'administrator.created', $id, 'Role: ' . $role); return $id;
}
function import_administrators(PDO $pdo, array $user): array {
    require_super($user); confirmation($pdo, $user);
    $file = $_FILES['administrators_csv'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || $file['size'] > 65536) {
        throw new DomainException('Upload a CSV file no larger than 64 KiB. No file was imported.');
    }
    $stream = fopen($file['tmp_name'], 'rb');
    if (!$stream) { throw new RuntimeException('CSV could not be read'); }
    $rows = [];
    try {
        $header = fgetcsv($stream, 65537, ',', '"', '');
        if ($header && is_string($header[0])) { $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); }
        if ($header !== ['username', 'private_code', 'role']) { throw new DomainException('CSV header must be exactly: username,private_code,role'); }
        while (($row = fgetcsv($stream, 65537, ',', '"', '')) !== false) {
            if (count($rows) >= 100) { throw new DomainException('Maximum 100 rows per import. No accounts were imported.'); }
            $rows[] = $row;
        }
    } finally { fclose($stream); unlink($file['tmp_name']); }
    $results = []; $created = 0;
    foreach ($rows as $offset => &$row) {
        $line = $offset + 2;
        if (count($row) !== 3 || !is_string($row[0]) || !is_string($row[1]) || !is_string($row[2])) {
            $results[] = 'Row ' . $line . ': rejected — expected three fields.'; continue;
        }
        $pdo->beginTransaction();
        try {
            // Serialize account administration and duplicate checks across concurrent imports.
            $pdo->query('SELECT id FROM system_state WHERE id = 1 FOR UPDATE')->fetch();
            add_administrator($pdo, strtolower(trim($row[0])), $row[1], trim($row[2]), (int)$user['id']);
            $pdo->commit(); $created++; $results[] = 'Row ' . $line . ': account created.';
        } catch (DomainException $error) {
            $pdo->rollBack(); $results[] = 'Row ' . $line . ': rejected — ' . $error->getMessage();
        } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
        $row = []; // No private codes go into result messages, audit details or retained files.
    }
    unset($row, $rows);
    audit($pdo, (int)$user['id'], 'administrator.csv_import', null, 'Created ' . $created . ' accounts.');
    return $results ?: ['No account rows were found.'];
}
function update_administrator(PDO $pdo, array $user): void {
    require_super($user); confirmation($pdo, $user);
    $id = filter_var(field('administrator_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $role = field('role'); $active = field('is_active') === '1' ? 1 : 0; $code = field('new_private_code');
    if (!$id || !isset(ADMIN_ROLES[$role]) || !in_array(field('is_active'), ['0', '1'], true)) { throw new DomainException('Invalid account details.'); }
    if ($code !== '' && !strong_private_code($code)) { throw new DomainException('New private code does not meet the 24–72 character strength requirements.'); }
    if ($id === (int)$user['id']) { throw new DomainException('Use My private code to change your own code. You cannot change your own role or active status.'); }
    $pdo->beginTransaction();
    try {
        $pdo->query('SELECT id FROM system_state WHERE id = 1 FOR UPDATE')->fetch();
        $stmt = $pdo->prepare('SELECT id, role, is_active FROM administrators WHERE id = ? FOR UPDATE'); $stmt->execute([$id]); $target = $stmt->fetch();
        if (!$target) { throw new DomainException('Administrator not found.'); }
        if ($target['role'] === 'super_admin' && $target['is_active'] && ($role !== 'super_admin' || !$active)) {
            if ((int)$pdo->query("SELECT COUNT(*) FROM administrators WHERE role = 'super_admin' AND is_active = 1")->fetchColumn() <= 1) {
                throw new DomainException('At least one active Super Admin must remain.');
            }
        }
        $stmt = $pdo->prepare('UPDATE administrators SET role = ?, is_active = ?, auth_version = auth_version + 1' . ($code !== '' ? ', private_code_hash = ?' : '') . ' WHERE id = ?');
        $values = [$role, $active]; if ($code !== '') { $values[] = hash_private_code($code); } $values[] = $id; $stmt->execute($values);
        audit($pdo, (int)$user['id'], 'administrator.updated', $id, 'Role: ' . $role . '; active: ' . $active . '; code reset: ' . ($code !== '' ? 'yes' : 'no'));
        $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
}
function save_admin_settings(PDO $pdo, array $user): void {
    require_super($user); confirmation($pdo, $user);
    $idle = filter_var(field('idle_seconds'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 300, 'max_range' => 3600]]);
    $notice = trim(field('admin_notice'));
    if (!$idle || strlen($notice) > 300 || !preg_match('//u', $notice)) { throw new DomainException('Use an inactivity timeout of 300–3600 seconds and a notice of at most 300 UTF-8 bytes.'); }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO admin_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
        $stmt->execute(['idle_seconds', (string)$idle, $user['id']]); $stmt->execute(['admin_notice', $notice, $user['id']]);
        audit($pdo, (int)$user['id'], 'settings.updated', null, 'Administrator notice and inactivity timeout.'); $pdo->commit();
    } catch (Throwable $error) { if ($pdo->inTransaction()) { $pdo->rollBack(); } throw $error; }
}
