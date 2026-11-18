<?php
declare(strict_types=1);
$configPath = dirname(__DIR__) . '/config/config.php';
// Never let a missing PHP opening tag, debug echo or parse failure print configuration secrets.
$configBuffer=ob_get_level();ob_start();
try {
 $config=require is_file($configPath)?$configPath:dirname(__DIR__).'/config/config.example.php';
 $configOutput=ob_get_clean();
 if(!is_array($config)||$configOutput!==''){throw new RuntimeException('Invalid private configuration');}
} catch(Throwable $configError) {
 while(ob_get_level()>$configBuffer){ob_end_clean();}
 error_log('SYA configuration unavailable: '.get_class($configError));
 if(PHP_SAPI==='cli'){fwrite(STDERR,"Configuration is unavailable. Check the protected PHP configuration file.".PHP_EOL);exit(1);}
 http_response_code(503);header('Cache-Control: no-store');header('Content-Type: text/plain; charset=utf-8');
 exit('The website is temporarily unavailable. Please return later.');
}
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'home'): string {
 global $config;
 return rtrim($config['base_url'], '/') . '/index.php?page=' . rawurlencode($page);
}
function asset(string $path): string { global $config; return rtrim($config['base_url'], '/') . '/assets/' . $path; }
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/maintenance.php';
