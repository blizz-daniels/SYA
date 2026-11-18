<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if($config['base_url']===''&&preg_match('~^/(?:[A-Za-z0-9_-]+/)*public/connect\.php$~D',$_SERVER['SCRIPT_NAME']??'')){$config['base_url']=dirname($_SERVER['SCRIPT_NAME']);}
require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';require dirname(__DIR__).'/app/production.php';require dirname(__DIR__).'/app/content.php';require dirname(__DIR__).'/app/communications.php';
try{
 $pdo=r_public_start();header('X-Robots-Tag: noindex, nofollow');if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','POST'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
 $view=isset($_GET['view'])&&is_string($_GET['view'])?$_GET['view']:'newsletter';if(!in_array($view,['newsletter','preferences','contact','rights','token'],true)){http_response_code(404);throw new DomainException('Page not found.');}
 $error='';$message=$_SESSION['comm_message']??'';unset($_SESSION['comm_message']);$tokenRow=null;$topics=[];
 if($view==='token'&&isset($_GET['token'])&&$_SERVER['REQUEST_METHOD']!=='POST'){
  if(!rate_limit($pdo,[ip_hash('comm_link')=>30])){http_response_code(429);throw new DomainException('Too many link attempts. Try later.');}
  unset($_SESSION['comm_grant']);$raw=is_string($_GET['token'])?$_GET['token']:'';
  if(preg_match('/\A[a-f0-9]{64}\z/D',$raw)){$stmt=$pdo->prepare('SELECT token_hash,expires_at FROM communication_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>?');$stmt->execute([r_key('comm:'.$raw),time()]);if($row=$stmt->fetch()){session_regenerate_id(true);$_SESSION['comm_grant']=['hash'=>$row['token_hash'],'expires'=>min((int)$row['expires_at'],time()+600)];}}
  header('Location: '.m_url('token'),true,303);exit;
 }
 if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();try{$message=$view==='token'?m_token_post($pdo):m_public_action($pdo,$view);$_SESSION['comm_message']=$message;header('Location: '.m_url($view),true,303);exit;}catch(DomainException $e){$error=$e->getMessage();}
 }
 if($view==='token'&&(int)($_SESSION['comm_grant']['expires']??0)>time()){$stmt=$pdo->prepare('SELECT * FROM communication_tokens WHERE token_hash=? AND expires_at>? AND used_at IS NULL');$stmt->execute([$_SESSION['comm_grant']['hash'],time()]);$tokenRow=$stmt->fetch()?:null;if($tokenRow&&$tokenRow['kind']!=='rights'){$payload=json_decode($tokenRow['payload_json'],true);if($tokenRow['kind']==='confirm'){$topics=$payload['topics'];}else{$stmt=$pdo->prepare('SELECT topics_json FROM newsletter_subscribers WHERE id=?');$stmt->execute([$tokenRow['subject_id']]);$topics=json_decode((string)$stmt->fetchColumn(),true)?:[];}}}
 require dirname(__DIR__).'/resources/views/connect.php';
}catch(Throwable $e){admin_failure($e);}
