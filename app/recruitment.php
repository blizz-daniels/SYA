<?php
declare(strict_types=1);
const R_STATUSES = ['Received','Under Review','Shortlisted','Audition Requested','Closed'];
function r_url(string $action = 'list', array $params = []): string { global $config; return rtrim($config['base_url'], '/') . '/recruitment.php?' . http_build_query(['action'=>$action] + $params); }
function r_key(string $value): string { global $config; return hash_hmac('sha256', $value, $config['security']['app_key']); }
function r_text(string $value, int $maximum, bool $required = true): string {
    $value = trim($value);
    if (($required && $value === '') || strlen($value) > $maximum || !preg_match('//u', $value) || str_contains($value, "\0")) { throw new DomainException('A text field is missing or exceeds its allowed length.'); }
    return $value;
}
function r_permission(array $user, string $kind): void {
    if (!in_array($kind, ['casting','crew'], true) || !($user['role'] === 'super_admin' || $user['role'] === ($kind === 'casting' ? 'casting_manager' : 'crew_coordinator'))) {
        http_response_code(403); throw new DomainException('You do not have permission for this recruitment department.');
    }
}
function r_role(PDO $pdo, int $id): array {
    $stmt=$pdo->prepare('SELECT * FROM recruitment_roles WHERE id = ?'); $stmt->execute([$id]); $role=$stmt->fetch();
    if (!$role) { http_response_code(404); throw new DomainException('Opportunity not found.'); } return $role;
}
function r_schema(array $rows): array {
    if (count($rows)>50) { throw new DomainException('Use at most 20 custom questions.'); }
    $result=[]; $files=0; $ids=[];
    foreach ($rows as $row) {
        if (!is_array($row) || !empty($row['remove'])) { continue; }
        $label=is_string($row['label']??null)?trim($row['label']):'';
        if ($label==='') { continue; }
        $type=$row['type']??''; $id=$row['id']??'';
        if (!is_string($id) || !preg_match('/\Aq[a-z0-9_]{1,28}\z/D',$id) || isset($ids[$id])) { throw new DomainException('Question identifiers must be unique.'); }
        if (!in_array($type,['text','textarea','select','checkbox','url','file'],true)) { throw new DomainException('Unsupported question type.'); }
        $options=[];
        if ($type==='select') {
            $raw=$row['options']??''; if (!is_string($raw)) { throw new DomainException('Invalid selection options.'); }
            foreach(explode("\n",$raw) as $option) { if (trim($option)!=='') { $options[]=r_text($option,120); } }
            $options=array_values(array_unique($options));
            if (count($options)<2 || count($options)>20) { throw new DomainException('Selection questions need 2–20 choices, one per line.'); }
        }
        if ($type==='file' && ++$files>3) { throw new DomainException('At most three small attachment questions are allowed.'); }
        $ids[$id]=true;
        $result[]=['id'=>$id,'label'=>r_text($label,120),'type'=>$type,'required'=>!empty($row['required']),'options'=>$options,'order'=>(int)($row['order']??0)];
    }
    if(count($result)>20){throw new DomainException('Use at most 20 custom questions.');}
    usort($result,fn($a,$b)=>$a['order']<=>$b['order']); return $result;
}
function r_private_storage(): string {
    global $config;
    if (($config['storage']['access_verified']??false)!==true) { throw new DomainException('Private attachment storage has not been verified.'); }
    $path=$config['storage']['private_path'];
    if (!is_dir($path) && !mkdir($path,0700,true)) { throw new RuntimeException('Private storage unavailable'); }
    $real=realpath($path); $public=realpath(dirname(__DIR__).'/public');
    if (!$real || !$public || str_starts_with($real . DIRECTORY_SEPARATOR, $public . DIRECTORY_SEPARATOR) || !is_writable($real)) { throw new RuntimeException('Unsafe private storage path'); }
    $docroot=realpath($_SERVER['DOCUMENT_ROOT']??dirname(__DIR__));
    if ($docroot && str_starts_with($real.DIRECTORY_SEPARATOR,$docroot.DIRECTORY_SEPARATOR) && (!is_file($real.'/.htaccess') || !str_contains(file_get_contents($real.'/.htaccess'),'Require all denied'))) { throw new RuntimeException('Private directory protection missing'); }
    return $real;
}
function r_storage(): string {global $config;if(($config['recruitment']['uploads_enabled']??false)!==true){throw new DomainException('New application attachments are disabled.');}return r_private_storage();}
function r_readiness(PDO $pdo, array $schema = []): array {
    global $config; $issues=[];
    if (($config['features']['recruitment']??false)!==true || setting($pdo,'recruitment_enabled','0')!=='1') { $issues[]='Recruitment is globally disabled.'; }
    if (($config['recruitment']['privacy_approved']??false)!==true || empty($config['recruitment']['privacy_notice']) || empty($config['recruitment']['privacy_contact'])) { $issues[]='Approved recruitment privacy notice and contact are required.'; }
    if (!r_mail_ready()) { $issues[]='Resend sender, API key and successful delivery verification are required.'; }
    if (array_filter($schema,fn($q)=>$q['type']==='file')) { try { r_storage(); if (!class_exists('finfo')) { $issues[]='PHP Fileinfo is required for attachments.'; } } catch (Throwable $error) { $issues[]='Private attachment storage must be configured and tested.'; } }
    return $issues;
}
function r_open(PDO $pdo, array $role): bool {
    return $role['state']==='open' && (int)$role['published_version']>0 && strtotime($role['deadline'].' UTC')>time() && !r_readiness($pdo,json_decode($role['draft_schema'],true));
}
function r_mail_ready(): bool {
    global $config;
    if (($config['email']['provider']??'')==='test') { return $config['environment']==='local' && in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true) && (bool)getenv('SYA_TEST_MAIL_DIR'); }
    return ($config['email']['provider']??'')==='resend' && !empty($config['email']['api_key']) && filter_var($config['email']['from_email']??'',FILTER_VALIDATE_EMAIL) && ($config['email']['delivery_verified']??false)===true;
}
function r_send(PDO $pdo, ?int $application, string $email, string $purpose, string $subject, string $text, ?string $deliveryKey = null): bool {
    global $config; $ok=false; $result='not_configured';
    if (($config['email']['provider']??'')==='test' && r_mail_ready()) {
        $dir=getenv('SYA_TEST_MAIL_DIR'); if (!is_dir($dir)) { mkdir($dir,0700,true); }
        $ok=file_put_contents($dir.'/'.bin2hex(random_bytes(12)).'.json',json_encode(['email'=>$email,'purpose'=>$purpose,'subject'=>$subject,'text'=>$text]))!==false; $result='local_test_transport';
    } elseif (($config['email']['provider']??'')==='resend' && !empty($config['email']['api_key']) && filter_var($config['email']['from_email']??'',FILTER_VALIDATE_EMAIL)) {
        $name=str_replace(["\r","\n",'<','>','"'],'', $config['email']['from_name']??'See You Around');
        $body=json_encode(['from'=>$name.' <'.$config['email']['from_email'].'>','to'=>[$email],'subject'=>$subject,'text'=>$text],JSON_THROW_ON_ERROR);
        $headers=['Authorization: Bearer '.$config['email']['api_key'],'Content-Type: application/json','Idempotency-Key: sya-'.$purpose.'-'.($deliveryKey??bin2hex(random_bytes(16)))];
        $http=0; $response='';
        if (function_exists('curl_init')) {
            $curl=curl_init('https://api.resend.com/emails'); curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false]);
            $response=curl_exec($curl); $http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE); curl_close($curl);
        } elseif (ini_get('allow_url_fopen')) {
            $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>8,'ignore_errors'=>true,'follow_location'=>0], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
            $response=@file_get_contents('https://api.resend.com/emails',false,$context);
            foreach($http_response_header??[] as $line) { if(preg_match('~^HTTP/\S+ (\d{3})~',$line,$m)) { $http=(int)$m[1]; } }
        }
        $decoded=is_string($response)?json_decode($response,true):null;
        $ok=$http>=200&&$http<300&&is_array($decoded)&&is_string($decoded['id']??null); $result='http_'.$http;
    }
    $stmt=$pdo->prepare('INSERT INTO recruitment_delivery_log (application_id,purpose,recipient_hash,delivery_status,result_code) VALUES (?,?,?,?,?)');
    $stmt->execute([$application,$purpose,r_key(strtolower($email)),$ok?'accepted':'failed',$result]); return $ok;
}
function r_retention_months(): int { global $config; return max(1,min(12,(int)($config['retention']['unsuccessful_applicant_months']??12))); }
function r_public_start(): PDO {
    global $config; $name=$config['session']['name']; $config['session']['name']=$name.'_applicant';
    try { return admin_start(); } catch(DomainException $error) { if(str_contains($error->getMessage(),'HTTPS')){throw new DomainException('Recruitment access requires HTTPS. Open the secure website address.');}throw $error; } finally { $config['session']['name']=$name; }
}
function r_answers(array $schema): array {
    $answers=[];
    foreach($schema as $q) {
        if($q['type']==='file') { continue; }
        $value=field($q['id']);
        if($q['required']&&trim($value)==='') { throw new DomainException('Answer the required question: '.$q['label']); }
        if($value!==''&&$q['type']==='select'&&!in_array($value,$q['options'],true)) { throw new DomainException('Choose an available option for '.$q['label']); }
        if($q['type']==='checkbox'&&!in_array($value,['','1'],true)) { throw new DomainException('Invalid checkbox answer.'); }
        if($value!==''&&$q['type']==='url') { $parts=parse_url($value); if(!filter_var($value,FILTER_VALIDATE_URL)||!in_array($parts['scheme']??'', ['https','http'],true)||isset($parts['user'])||isset($parts['pass'])) { throw new DomainException('Use a valid public HTTP or HTTPS portfolio link.'); } }
        $answers[$q['id']]=r_text($value,$q['type']==='textarea'?4000:1000,false);
    } return $answers;
}
function r_uploads(array $schema): array {
    global $config; $files=[];
    try {
        foreach($schema as $q) {
            if($q['type']!=='file') { continue; }
            $file=$_FILES[$q['id']]??['error'=>UPLOAD_ERR_NO_FILE];
            if(($file['error']??null)===UPLOAD_ERR_NO_FILE && !$q['required']) { continue; }
            if(!is_array($file)||($file['error']??null)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||$file['size']<1||$file['size']>min(2097152,(int)$config['uploads']['max_bytes'])) { throw new DomainException('Upload a valid attachment no larger than 2 MiB: '.$q['label']); }
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true)||!in_array($mime,$config['uploads']['allowed_types']??[],true)) { throw new DomainException('Attachments must contain JPG, PNG, WebP or PDF data.'); }
            if($mime==='application/pdf') { $handle=fopen($file['tmp_name'],'rb');$head=fread($handle,5);fseek($handle,max(0,(int)$file['size']-2048));$tail=stream_get_contents($handle);fclose($handle);if($head!=='%PDF-'||!str_contains($tail,'%%EOF')) { throw new DomainException('The PDF attachment is invalid.'); } }
            else { $image=@getimagesize($file['tmp_name']);if(!$image||$image['mime']!==$mime||$image[0]*$image[1]>20000000) { throw new DomainException('The image attachment is invalid or too large in dimensions.'); } }
            $directory=r_storage(); $key=bin2hex(random_bytes(32)).'.bin';$path=$directory.'/'.$key;
            if(!move_uploaded_file($file['tmp_name'],$path)) { throw new RuntimeException('Attachment could not be stored'); }
            chmod($path,0600); $files[]=['question'=>$q['id'],'key'=>$key,'mime'=>$mime,'bytes'=>(int)$file['size'],'path'=>$path];
        } return $files;
    } catch(Throwable $error) { foreach($files as $file) { @unlink($file['path']); } throw $error; }
}
function r_submit(PDO $pdo, int $roleId): string {
    global $config;
    if(!rate_limit($pdo,[ip_hash('application')=>8])) { http_response_code(429);throw new DomainException('Too many application attempts. Try again in 15 minutes.'); }
    if(field('website')!=='') { throw new DomainException('Application could not be accepted.'); }
    $name=r_text(field('full_name'),150); $email=strtolower(trim(field('email')));
    if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)) { throw new DomainException('Enter a valid email address.'); }
    $age=filter_var(field('age'),FILTER_VALIDATE_INT,['options'=>['min_range'=>18,'max_range'=>100]]);
    if(!$age||field('eligibility')!=='1'||field('privacy_ack')!=='1') { throw new DomainException('Applications are for adults aged 18 or older. Confirm the eligibility requirements and privacy notice.'); }
    $location=r_text(field('location'),200);$availability=r_text(field('availability'),1000);
    $files=[]; $pdo->beginTransaction();
    try {
        $stmt=$pdo->prepare('SELECT * FROM recruitment_roles WHERE id=? FOR UPDATE');$stmt->execute([$roleId]);$role=$stmt->fetch();
        if(!$role||!r_open($pdo,$role)) { throw new DomainException('This opportunity is not accepting applications.'); }
        if((string)$role['published_version']!==field('form_version')) { http_response_code(409);throw new DomainException('This form changed. Refresh the page before applying.'); }
        if($age<(int)$role['age_min']||($role['age_max']!==null&&$age>(int)$role['age_max'])) { throw new DomainException('Your age does not meet this role’s mandatory age requirements.'); }
        $stmt=$pdo->prepare('SELECT schema_json FROM recruitment_form_versions WHERE role_id=? AND version=?');$stmt->execute([$roleId,$role['published_version']]);$schema=json_decode($stmt->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
        $answers=r_answers($schema);$files=r_uploads($schema);$hash=r_key($email);
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM recruitment_applications WHERE role_id=? AND email_hash=?');$stmt->execute([$roleId,$hash]);$duplicate=(int)$stmt->fetchColumn()>0;
        $reference='SYA-'.strtoupper(bin2hex(random_bytes(16)));
        $stmt=$pdo->prepare('INSERT INTO recruitment_applications (reference,role_id,form_version,full_name,email,email_hash,age,location,availability,answers_json,duplicate_flag,notice_version,privacy_notice,acknowledged_at,retention_due_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?)');
        $stmt->execute([$reference,$roleId,$role['published_version'],$name,$email,$hash,$age,$location,$availability,json_encode($answers,JSON_THROW_ON_ERROR),(int)$duplicate,$config['recruitment']['notice_version']??'1',$config['recruitment']['privacy_notice'],(new DateTimeImmutable($role['deadline'],new DateTimeZone('UTC')))->modify('+'.r_retention_months().' months')->format('Y-m-d H:i:s')]);$id=(int)$pdo->lastInsertId();
        if($duplicate) { $stmt=$pdo->prepare('UPDATE recruitment_applications SET duplicate_flag=1 WHERE role_id=? AND email_hash=?');$stmt->execute([$roleId,$hash]); }
        foreach($files as $file) { $stmt=$pdo->prepare('INSERT INTO recruitment_attachments (application_id,question_id,storage_key,mime,size_bytes) VALUES (?,?,?,?,?)');$stmt->execute([$id,$file['question'],$file['key'],$file['mime'],$file['bytes']]); }
        audit($pdo,null,'recruitment.received',$id,'Application received.');$pdo->commit();
    } catch(Throwable $error) { if($pdo->inTransaction()){$pdo->rollBack();}foreach($files as $file){@unlink($file['path']);}throw $error; }
    // An email outage must never roll back an already received application.
    try { r_send($pdo,$id,$email,'receipt','See You Around — application received',"Your application was received.\nReference: ".$reference."\nRole: ".$role['title']."\nTrack your application: ".r_url('track')."\nKeep this reference. Applying does not subscribe you to marketing."); } catch(Throwable $error) { error_log('SYA recruitment receipt delivery unavailable'); }
    return $reference;
}

function r_not_removed(PDO $pdo,int $id):void{$stmt=$pdo->prepare("SELECT anonymized_at FROM privacy_controls WHERE scope='application' AND subject_id=?");$stmt->execute([$id]);if($stmt->fetchColumn()){http_response_code(410);throw new DomainException('This application was anonymized and cannot be processed further.');}}
