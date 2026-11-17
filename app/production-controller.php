<?php
declare(strict_types=1);
$pdo=admin_start();
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','POST'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
if(is_file(dirname(__DIR__).'/public/setup.php')){http_response_code(503);throw new DomainException('Remove the temporary setup file before using administration.');}
if(!$pdo->query('SELECT setup_completed_at FROM system_state WHERE id=1')->fetchColumn()){http_response_code(503);throw new DomainException('Initial administrator setup has not been completed.');}
$user=current_admin($pdo);if(!$user){admin_redirect('login');}
$kind=isset($_GET['kind'])&&is_string($_GET['kind'])?$_GET['kind']:'casting';$section=isset($_GET['section'])&&is_string($_GET['section'])?$_GET['section']:'auditions';
r_permission($user,$kind);if(!in_array($section,['auditions','evaluations'],true)||($section==='auditions'&&$kind!=='casting')){http_response_code(403);throw new DomainException('This production module is not available for your department.');}
$error='';$flash=$_SESSION['flash']??[];unset($_SESSION['flash']);
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();try{$flash=$section==='auditions'?p_schedule_action($pdo,$user,field('action')):v_action($pdo,$user,$kind,field('action'));$_SESSION['flash']=$flash;header('Location: '.p_url($kind,$section),true,303);exit;}catch(DomainException $exception){$error=$exception->getMessage();}
}
$view=p_view_data($pdo,$user,$kind,$section);$title=$section==='auditions'?'Auditions and callbacks':($kind==='casting'?'Acting evaluations':'Crew evaluations');$module=$kind;$productionView=true;
require dirname(__DIR__).'/resources/views/admin-layout.php';
