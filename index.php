<?php
/** Shared-hosting entry point; application pages remain in public/. */
declare(strict_types=1);
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit('Method not allowed');
}
$target = 'public/index.php';
if (isset($_GET['page']) && is_string($_GET['page'])) {
    $target .= '?page=' . rawurlencode($_GET['page']);
}
header('Cache-Control: no-store');
header('Location: ' . $target, true, 302);
exit;
