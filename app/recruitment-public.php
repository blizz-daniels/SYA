<?php
declare(strict_types=1);
$pdo=r_public_start();
$action=isset($_GET['action'])&&is_string($_GET['action'])?$_GET['action']:'list';
if(!in_array($action,['list','apply','received','track','status'],true)){http_response_code(404);throw new DomainException('Page not found.');}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','POST'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
$error='';$message='';$role=null;$schema=[];$preview=false;
if($action==='apply'){
    $role=r_role($pdo,isset($_GET['role'])&&is_string($_GET['role'])?(int)$_GET['role']:0);
    if(!r_open($pdo,$role)){http_response_code(410);throw new DomainException('This opportunity is not accepting applications.');}
    $stmt=$pdo->prepare('SELECT schema_json FROM recruitment_form_versions WHERE role_id=? AND version=?');$stmt->execute([$role['id'],$role['published_version']]);$schema=json_decode($stmt->fetchColumn(),true);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    try{
        if($action==='apply'){
            $reference=r_submit($pdo,(int)$role['id']);$_SESSION['application_receipt']=$reference;header('Location: '.r_url('received'),true,303);exit;
        }elseif($action==='track'&&field('stage')==='request'){
            if(!rate_limit($pdo,[ip_hash('track_request')=>8,r_key('track:'.strtoupper(trim(field('reference'))).':'.strtolower(trim(field('email'))))=>3])){http_response_code(429);throw new DomainException('Too many verification requests. Try again in 15 minutes.');}
            $reference=strtoupper(trim(field('reference')));$email=strtolower(trim(field('email')));
            if(!preg_match('/\ASYA-[A-F0-9]{32}\z/D',$reference)){$reference='invalid';}
            if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)){$email='invalid';}
            $stmt=$pdo->prepare('SELECT id,email FROM recruitment_applications WHERE reference=? AND email_hash=?');$stmt->execute([$reference,r_key($email)]);$application=$stmt->fetch();
            $challenge=bin2hex(random_bytes(32));$_SESSION['tracking_challenge']=$challenge;
            if($application&&r_mail_ready()){
                $code=(string)random_int(100000,999999);$life=max(60,min(600,(int)($config['tracking']['code_lifetime_seconds']??600)));
                $pdo->beginTransaction();try{
                    $stmt=$pdo->prepare('UPDATE recruitment_tracking_challenges SET used_at=? WHERE application_id=? AND used_at IS NULL');$stmt->execute([time(),$application['id']]);
                    $stmt=$pdo->prepare('INSERT INTO recruitment_tracking_challenges (token,application_id,code_hash,expires_at) VALUES (?,?,?,?)');$stmt->execute([$challenge,$application['id'],r_key($challenge.':'.$code),time()+$life]);$pdo->commit();
                }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
                try{r_send($pdo,(int)$application['id'],$application['email'],'tracking','See You Around — verification code','Your application-tracking verification code is '.$code.'. It expires in '.(int)($life/60).' minutes. If you did not request it, ignore this email.');}catch(Throwable $error){error_log('SYA tracking delivery unavailable');}
            }
            $stmt=$pdo->prepare('DELETE FROM recruitment_tracking_challenges WHERE expires_at<? LIMIT 100');$stmt->execute([time()-86400]);
            $_SESSION['tracking_message']='If the reference and email match an application, a verification code will be sent. Check your inbox and spam folder.';
            header('Location: '.r_url('track'),true,303);exit;
        }elseif($action==='track'&&field('stage')==='verify'){
            if(!rate_limit($pdo,[ip_hash('track_verify')=>20])){http_response_code(429);throw new DomainException('Too many verification attempts. Try again in 15 minutes.');}
            $challenge=$_SESSION['tracking_challenge']??'';$code=field('verification_code');$verified=null;
            $pdo->beginTransaction();try{
                $stmt=$pdo->prepare('SELECT * FROM recruitment_tracking_challenges WHERE token=? FOR UPDATE');$stmt->execute([$challenge]);$row=$stmt->fetch();
                if($row&&$row['used_at']===null&&(int)$row['expires_at']>time()&&(int)$row['attempts']<5){
                    $stmt=$pdo->prepare('UPDATE recruitment_tracking_challenges SET attempts=attempts+1 WHERE token=?');$stmt->execute([$challenge]);
                    if(preg_match('/\A[0-9]{6}\z/D',$code)&&hash_equals($row['code_hash'],r_key($challenge.':'.$code))){$verified=(int)$row['application_id'];$stmt=$pdo->prepare('UPDATE recruitment_tracking_challenges SET used_at=? WHERE token=?');$stmt->execute([time(),$challenge]);}
                }$pdo->commit();
            }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
            if(!$verified){throw new DomainException('Verification could not be completed. Request a new code if it has expired.');}
            session_regenerate_id(true);unset($_SESSION['tracking_challenge']);$_SESSION['tracking_access']=['id'=>$verified,'expires'=>time()+600];header('Location: '.r_url('status'),true,303);exit;
        }elseif($action==='status'&&field('stage')==='end'){
            unset($_SESSION['tracking_access']);header('Location: '.r_url('track'),true,303);exit;
        }else{http_response_code(405);throw new DomainException('This action is unavailable.');}
    }catch(DomainException $exception){$error=$exception->getMessage();}
}
if($action==='status'){
    if(empty($_SESSION['tracking_access'])||$_SESSION['tracking_access']['expires']<=time()){unset($_SESSION['tracking_access']);header('Location: '.r_url('track'),true,303);exit;}
    // Deliberately do not select applicant details, private notes, outcomes or attachments.
    $stmt=$pdo->prepare('SELECT a.reference,a.public_status,a.received_at,v.role_snapshot FROM recruitment_applications a JOIN recruitment_form_versions v ON v.role_id=a.role_id AND v.version=a.form_version WHERE a.id=?');$stmt->execute([$_SESSION['tracking_access']['id']]);$tracked=$stmt->fetch();if($tracked){$tracked['title']=json_decode($tracked['role_snapshot'],true)['title'];unset($tracked['role_snapshot']);}
    if(!$tracked){unset($_SESSION['tracking_access']);header('Location: '.r_url('track'),true,303);exit;}
}
$title=match($action){'apply'=>'Apply for '.$role['title'],'track'=>'Track your application','received'=>'Application received','status'=>'Application status',default=>'Recruitment opportunities'};
require dirname(__DIR__).'/resources/views/recruitment-layout.php';
