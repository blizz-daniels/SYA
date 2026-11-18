<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if($config['base_url']===''&&preg_match('~^/(?:[A-Za-z0-9_-]+/)*public/attachment\.php$~D',$_SERVER['SCRIPT_NAME']??'')){$config['base_url']=dirname($_SERVER['SCRIPT_NAME']);}
require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';
try{
    if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
    $pdo=admin_start();$user=current_admin($pdo);if(!$user){http_response_code(403);throw new DomainException('Administrator authentication is required.');}
    $id=isset($_GET['id'])&&is_string($_GET['id'])?(int)$_GET['id']:0;
    $stmt=$pdo->prepare('SELECT f.*,r.kind FROM recruitment_attachments f JOIN recruitment_applications a ON a.id=f.application_id JOIN recruitment_roles r ON r.id=a.role_id WHERE f.id=?');$stmt->execute([$id]);$file=$stmt->fetch();
    if(!$file){http_response_code(404);throw new DomainException('Attachment not found.');}r_permission($user,$file['kind']);
    if(!preg_match('/\A[a-f0-9]{64}\.bin\z/D',$file['storage_key'])){throw new RuntimeException('Invalid stored key');}
    $path=r_private_storage().'/'.$file['storage_key'];if(!is_file($path)){http_response_code(404);throw new DomainException('Attachment unavailable.');}
    audit($pdo,(int)$user['id'],'recruitment.attachment_download',(int)$file['application_id']);session_write_close();
    $extension=match($file['mime']){'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf',default=>'bin'};
    header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="attachment-'.$id.'.'.$extension.'"');header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');if($_SERVER['REQUEST_METHOD']!=='HEAD'){readfile($path);}exit;
}catch(Throwable $error){admin_failure($error);}
