<?php
declare(strict_types=1);
$configPath = dirname(__DIR__) . '/config/config.php';
$config = require is_file($configPath) ? $configPath : dirname(__DIR__) . '/config/config.example.php';
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'home'): string {
 global $config;
 return rtrim($config['base_url'], '/') . '/index.php?page=' . rawurlencode($page);
}
function asset(string $path): string { global $config; return rtrim($config['base_url'], '/') . '/assets/' . $path; }
require_once __DIR__ . '/database.php';
