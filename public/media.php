<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/content.php';require dirname(__DIR__).'/app/content-media.php';
header('X-Content-Type-Options: nosniff');header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
try{
 if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
 $id=isset($_GET['id'])&&is_string($_GET['id'])&&ctype_digit($_GET['id'])?(int)$_GET['id']:0;$size=isset($_GET['size'])&&is_string($_GET['size'])?(int)$_GET['size']:960;if(!in_array($size,[480,960,1600],true)||!$id){http_response_code(404);exit;}
 $pdo=database($config);$preview=($_GET['preview']??'')==='1';
 if($preview){require dirname(__DIR__).'/app/admin-security.php';$pdo=admin_start();$user=current_admin($pdo);if(!$user){http_response_code(404);exit;}c_permission($user);if(is_file(__DIR__.'/setup.php')||!$pdo->query('SELECT setup_completed_at FROM system_state WHERE id=1')->fetchColumn()){http_response_code(404);exit;}}
 elseif(!c_media_allowed($pdo,$id)){http_response_code(404);exit;}
 $stmt=$pdo->prepare('SELECT storage_key FROM media_assets WHERE id=?');$stmt->execute([$id]);$key=$stmt->fetchColumn();if(!is_string($key)||!preg_match('/\A[a-f0-9]{64}\z/D',$key)){http_response_code(404);exit;}$path=c_media_dir().'/'.$key.'-'.$size.'.jpg';if(!is_file($path)){http_response_code(404);exit;}header('Content-Type: image/jpeg');header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="film-image.jpg"');if($_SERVER['REQUEST_METHOD']!=='HEAD'){readfile($path);}
}catch(Throwable $error){http_response_code(404);if(!($error instanceof DomainException)){error_log('SYA media unavailable: '.get_class($error));}}
