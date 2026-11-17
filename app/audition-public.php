<?php
declare(strict_types=1);
$pdo=r_public_start();header('X-Robots-Tag: noindex, nofollow');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','POST'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
$clean=rtrim($config['base_url'],'/').'/audition.php';$error='';$receipt=false;$booking=null;
if(isset($_GET['token'])&&$_SERVER['REQUEST_METHOD']!=='POST'){
    if(!rate_limit($pdo,[ip_hash('audition_link')=>30])){http_response_code(429);throw new DomainException('Too many invitation attempts. Try again later.');}
    $token=is_string($_GET['token'])?$_GET['token']:'';$row=null;
    if(preg_match('/\A[a-f0-9]{64}\z/D',$token)){$stmt=$pdo->prepare('SELECT id,version,token_expires,token_hash FROM audition_appointments WHERE token_hash=? AND token_used_at IS NULL');$stmt->execute([r_key('audition:'.$token)]);$row=$stmt->fetch();}
    unset($_SESSION['audition_grant'],$_SESSION['audition_receipt']);
    if($row){$grant=['id'=>(int)$row['id'],'version'=>(int)$row['version'],'hash'=>$row['token_hash'],'expires'=>min((int)$row['token_expires'],time()+600)];if(p_public_booking($pdo,$grant)){session_regenerate_id(true);$_SESSION['audition_grant']=$grant;}}
    if(!isset($_SESSION['audition_grant'])){$_SESSION['audition_error']='This invitation is unavailable, expired, replaced or already used. Request help from the production team or use email-verified application tracking.';}
    header('Location: '.$clean,true,303);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if(!rate_limit($pdo,[ip_hash('audition_reply')=>15])){http_response_code(429);throw new DomainException('Too many invitation responses. Try again later.');}
    $choice=field('response');if(!in_array($choice,['confirm','decline'],true)){http_response_code(403);throw new DomainException('Choose confirm or decline.');}
    p_lock($pdo);try{
        $booking=p_public_booking($pdo,$_SESSION['audition_grant']??[]);if(!$booking){http_response_code(410);throw new DomainException('This invitation is unavailable, expired, replaced or already used.');}
        $state=$choice==='confirm'?'confirmed':'declined';
        $stmt=$pdo->prepare('UPDATE audition_appointments SET state=?,active_slot_id=IF(?=\'declined\',NULL,active_slot_id),token_used_at=? WHERE id=?');$stmt->execute([$state,$state,time(),$booking['id']]);
        p_event($pdo,null,$state,(int)$booking['application_id'],(int)$booking['id'],null,'', ['response'=>$state]);$pdo->commit();
        $_SESSION['audition_receipt']=['id'=>(int)$booking['id'],'version'=>(int)$booking['version'],'expires'=>time()+600];unset($_SESSION['audition_grant']);header('Location: '.$clean,true,303);exit;
    }catch(Throwable $exception){if($pdo->inTransaction()){$pdo->rollBack();}throw $exception;}
}
if(isset($_SESSION['audition_receipt'])){$booking=p_public_booking($pdo,$_SESSION['audition_receipt'],true);$receipt=(bool)$booking;}
if(!$booking&&isset($_SESSION['audition_grant'])){$booking=p_public_booking($pdo,$_SESSION['audition_grant']);}
if(!$booking){unset($_SESSION['audition_grant'],$_SESSION['audition_receipt']);$error=$_SESSION['audition_error']??'Open your current private invitation link, or use email-verified application tracking to view your schedule.';unset($_SESSION['audition_error']);}
$details=$booking?json_decode($booking['public_snapshot'],true):null;
require dirname(__DIR__).'/resources/views/audition-public.php';
