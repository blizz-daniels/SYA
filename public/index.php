<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
$pages = ['home'=>'Home','about'=>'About the Film','characters'=>'Characters','casting'=>'Casting Calls','crew'=>'Join the Crew','updates'=>'Production Updates','media'=>'Media','contact'=>'Contact'];
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$basePath = rtrim(parse_url($config['base_url'], PHP_URL_PATH) ?: '', '/');
$page = in_array($requestPath, [$basePath . '/', $basePath . '/index.php'], true) ? ($_GET['page'] ?? 'home') : '404';
if (!is_string($page) || !isset($pages[$page])) { $page = '404'; http_response_code(404); }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) { http_response_code(405); header('Allow: GET, HEAD'); exit('Method not allowed'); }
$title = $pages[$page] ?? 'Page not found';
require dirname(__DIR__) . '/resources/views/layout.php';
