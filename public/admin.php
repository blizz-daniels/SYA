<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if ($config['base_url'] === '' && preg_match('~^/(?:[A-Za-z0-9_-]+/)*public/admin\.php$~D', $_SERVER['SCRIPT_NAME'] ?? '')) {
    $config['base_url'] = dirname($_SERVER['SCRIPT_NAME']);
}
require dirname(__DIR__) . '/app/admin-security.php';
require dirname(__DIR__) . '/app/admin-actions.php';
require dirname(__DIR__) . '/app/recruitment.php';
require dirname(__DIR__) . '/app/recruitment-admin.php';
try { require dirname(__DIR__) . '/app/admin-controller.php'; }
catch (Throwable $error) { admin_failure($error); }
