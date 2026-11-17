<?php
declare(strict_types=1);
const P_RECOMMENDATIONS=['advance'=>'Advance','callback'=>'Request callback / interview','hold'=>'Hold','do_not_advance'=>'Do not advance'];
function p_url(string $kind='casting',string $section='auditions',array $params=[]):string{global $config;return rtrim($config['base_url'],'/').'/production.php?'.http_build_query(['kind'=>$kind,'section'=>$section]+$params);}
function p_id(string $key):int{return max(0,(int)field($key));}
function p_date(string $value,string $zone):string{
    if(!in_array($zone,['UTC','Africa/Lagos'],true)){throw new DomainException('Choose UTC or Africa/Lagos time.');}
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone($zone));
    if(!$date||$date->format('Y-m-d\TH:i')!==$value){throw new DomainException('Enter a valid date and time.');}
    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function p_time(string $utc,string $zone='Africa/Lagos'):string{return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($zone))->format('d M Y, H:i').' '.$zone;}
function p_lock(PDO $pdo):void{$pdo->beginTransaction();$pdo->query('SELECT id FROM system_state WHERE id=1 FOR UPDATE')->fetch();}
function p_app(PDO $pdo,array $user,string $kind,int $id,bool $lock=false):array{
    r_permission($user,$kind);$stmt=$pdo->prepare('SELECT a.*,r.kind,v.role_snapshot FROM recruitment_applications a JOIN recruitment_roles r ON r.id=a.role_id JOIN recruitment_form_versions v ON v.role_id=a.role_id AND v.version=a.form_version WHERE a.id=?'.($lock?' FOR UPDATE':''));$stmt->execute([$id]);$app=$stmt->fetch();
    if(!$app||$app['kind']!==$kind){http_response_code(404);throw new DomainException('Application not found in this department.');}
    $app['role_title']=json_decode($app['role_snapshot'],true,512,JSON_THROW_ON_ERROR)['title'];return $app;
}
function p_session(PDO $pdo,int $id):array{$stmt=$pdo->prepare('SELECT * FROM audition_sessions WHERE id=?');$stmt->execute([$id]);$session=$stmt->fetch();if(!$session){http_response_code(404);throw new DomainException('Audition session not found.');}return $session;}
function p_booking(PDO $pdo,int $id):array{
    $stmt=$pdo->prepare('SELECT b.*,s.starts_at,s.ends_at,s.session_id,x.state AS session_state,x.display_zone FROM audition_appointments b JOIN audition_slots s ON s.id=b.slot_id JOIN audition_sessions x ON x.id=s.session_id WHERE b.id=?');$stmt->execute([$id]);$row=$stmt->fetch();
    if(!$row){http_response_code(404);throw new DomainException('Audition booking not found.');}return $row;
}
function p_event(PDO $pdo,?array $user,string $action,?int $app,?int $booking=null,?int $callback=null,string $note='',array $snapshot=[]):void{
    $stmt=$pdo->prepare('INSERT INTO audition_events (application_id,appointment_id,callback_id,actor_id,actor_name,action,private_note,snapshot_json) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$app,$booking,$callback,$user['id']??null,$user['username']??'Applicant',$action,$note,json_encode($snapshot,JSON_THROW_ON_ERROR)]);
    audit($pdo,isset($user['id'])?(int)$user['id']:null,'audition.'.$action,$app);
}
function p_notice(PDO $pdo,int $id,int $version,string $kind):void{
    $stmt=$pdo->prepare("UPDATE audition_notifications SET state='superseded' WHERE appointment_id=? AND state IN ('pending','failed','sending')");$stmt->execute([$id]);
    $stmt=$pdo->prepare('INSERT INTO audition_notifications (appointment_id,appointment_version,kind) VALUES (?,?,?)');$stmt->execute([$id,$version,$kind]);
}
function p_snapshot(array $session,array $slot,array $app):array{
    return ['session_title'=>$session['title'],'mode'=>$session['mode'],'location'=>$session['mode']==='physical'?$session['location']:'','meeting_url'=>$session['mode']==='virtual'?$session['meeting_url']:'','instructions'=>$session['public_instructions'],'starts_at'=>$slot['starts_at'],'ends_at'=>$slot['ends_at'],'zone'=>$session['display_zone'],'role_title'=>$app['role_title'],'reference'=>$app['reference']];
}
function p_slot_check(PDO $pdo,array $session,string $start,string $end):void{
    if($session['state']!=='active'||$start<$session['starts_at']||$end>$session['ends_at']||$start>=$end||strtotime($start.' UTC')<=time()){throw new DomainException('Use a future slot inside an active session.');}
    $stmt=$pdo->prepare('SELECT id FROM audition_slots WHERE session_id=? AND starts_at<? AND ends_at>? LIMIT 1');$stmt->execute([$session['id'],$end,$start]);if($stmt->fetchColumn()){throw new DomainException('That slot overlaps an existing slot.');}
}
function p_assign(PDO $pdo,array $user):array{
    $id=p_id('appointment_id');$app=p_app($pdo,$user,'casting',p_id('application_id'),true);$old=$id?p_booking($pdo,$id):null;
    if($old&&((int)$old['application_id']!==(int)$app['id']||(int)$old['version']!==p_id('version'))){http_response_code(409);throw new DomainException('This booking changed. Reload before rescheduling.');}
    $stmt=$pdo->prepare('SELECT * FROM audition_slots WHERE id=?');$stmt->execute([p_id('slot_id')]);$slot=$stmt->fetch();if(!$slot){throw new DomainException('Choose an existing slot.');}$session=p_session($pdo,(int)$slot['session_id']);
    if($session['state']!=='active'||strtotime($slot['starts_at'].' UTC')<=time()){throw new DomainException('Choose a future slot in an active session.');}
    $stmt=$pdo->prepare("SELECT b.id FROM audition_appointments b JOIN audition_slots s ON s.id=b.slot_id JOIN recruitment_applications a ON a.id=b.application_id WHERE b.state IN ('invited','confirmed') AND b.id<>? AND (b.active_slot_id=? OR (a.email_hash=? AND s.starts_at<? AND s.ends_at>?)) LIMIT 1");
    $stmt->execute([$id,$slot['id'],$app['email_hash'],$slot['ends_at'],$slot['starts_at']]);if($stmt->fetchColumn()){throw new DomainException('The slot is occupied or this applicant already has an overlapping audition.');}
    $round=field('round');$callback=p_id('callback_id')?:null;
    if(!in_array($round,['initial','callback'],true)){throw new DomainException('Choose initial audition or callback.');}
    if($round==='callback'){$stmt=$pdo->prepare("SELECT id FROM audition_callbacks WHERE id=? AND application_id=? AND state IN ('requested','arranged')");$stmt->execute([$callback,$app['id']]);if(!$stmt->fetchColumn()){throw new DomainException('Choose an open callback request for this application.');}}
    else{$callback=null;}
    $note=r_text(field('private_note'),4000,false);$snapshot=p_snapshot($session,$slot,$app);$snapshot['round']=$round;$version=$old?(int)$old['version']+1:1;
    if($old){$stmt=$pdo->prepare("UPDATE audition_appointments SET slot_id=?,active_slot_id=?,callback_id=?,round=?,version=?,state='invited',attendance='pending',public_snapshot=?,private_note=?,token_hash=NULL,token_expires=NULL,token_used_at=NULL WHERE id=?");$stmt->execute([$slot['id'],$slot['id'],$callback,$round,$version,json_encode($snapshot,JSON_THROW_ON_ERROR),$note,$id]);}
    else{$stmt=$pdo->prepare('INSERT INTO audition_appointments (application_id,slot_id,active_slot_id,callback_id,round,public_snapshot,private_note) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$app['id'],$slot['id'],$slot['id'],$callback,$round,json_encode($snapshot,JSON_THROW_ON_ERROR),$note]);$id=(int)$pdo->lastInsertId();}
    if($callback){$stmt=$pdo->prepare("UPDATE audition_callbacks SET state='arranged' WHERE id=?");$stmt->execute([$callback]);}
    p_notice($pdo,$id,$version,$old?'change':'invite');p_event($pdo,$user,$old?'rescheduled':'assigned',(int)$app['id'],$id,$callback,$note,$snapshot);
    return ['Audition saved. Invitation queued; use Send notice to email it. Earlier links are invalid after a reschedule.'];
}
function p_cancel(PDO $pdo,?array $user,array $booking,string $note):void{
    if($booking['state']==='cancelled'){return;}$version=(int)$booking['version']+1;
    $stmt=$pdo->prepare("UPDATE audition_appointments SET state='cancelled',active_slot_id=NULL,version=?,token_hash=NULL,token_expires=NULL,token_used_at=NULL WHERE id=?");$stmt->execute([$version,$booking['id']]);
    p_notice($pdo,(int)$booking['id'],$version,'cancel');p_event($pdo,$user,'cancelled',(int)$booking['application_id'],(int)$booking['id'],$booking['callback_id']?(int)$booking['callback_id']:null,$note,json_decode($booking['public_snapshot'],true));
}
function p_schedule_action(PDO $pdo,array $user,string $action):array{
    r_permission($user,'casting');
    if($action==='notice_send'){return p_send_notice($pdo,$user,p_id('notice_id'));}
    p_lock($pdo);
    try{
        $message=[];
        if($action==='session_save'){
            $id=p_id('session_id');$zone=field('display_zone');$start=p_date(field('starts_at'),$zone);$end=p_date(field('ends_at'),$zone);
            $key=strtolower(trim(field('resource_key')));$mode=field('mode');$location=r_text(field('location'),500,false);$url=trim(field('meeting_url'));
            if(!preg_match('/\A[a-z0-9][a-z0-9_-]{0,59}\z/D',$key)||!in_array($mode,['physical','virtual'],true)||$start>=$end||strtotime($end.' UTC')-strtotime($start.' UTC')>86400||strtotime($start.' UTC')<=time()){throw new DomainException('Use a future session of at most 24 hours, a panel/room key, and a valid mode.');}
            if($mode==='physical'&&$location===''){throw new DomainException('A physical audition requires its location.');}
            $parts=parse_url($url);if($mode==='virtual'&&(!filter_var($url,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||strlen($url)>1000)){throw new DomainException('A virtual audition requires a valid HTTPS meeting link without embedded credentials.');}
            $stmt=$pdo->prepare("SELECT id FROM audition_sessions WHERE resource_key=? AND state='active' AND id<>? AND starts_at<? AND ends_at>? LIMIT 1");$stmt->execute([$key,$id,$end,$start]);if($stmt->fetchColumn()){throw new DomainException('That panel/room already has an overlapping session.');}
            if($id){$session=p_session($pdo,$id);if($session['state']!=='active'){throw new DomainException('Cancelled sessions cannot be reopened. Create a new session.');}
                $stmt=$pdo->prepare("SELECT b.id FROM audition_appointments b JOIN audition_slots s ON s.id=b.slot_id WHERE s.session_id=? AND b.state IN ('invited','confirmed') LIMIT 1");$stmt->execute([$id]);if($stmt->fetchColumn()){throw new DomainException('This session has active bookings. Create a corrected session and reschedule affected applicants so they receive fresh invitations.');}
                $stmt=$pdo->prepare('SELECT id FROM audition_slots WHERE session_id=? AND (starts_at<? OR ends_at>?) LIMIT 1');$stmt->execute([$id,$start,$end]);if($stmt->fetchColumn()){throw new DomainException('Existing slots must remain inside the session bounds.');}
            }
            $values=[r_text(field('title'),150),$mode,$mode==='physical'?$location:'',$mode==='virtual'?$url:'',r_text(field('public_instructions'),3000,false),r_text(field('private_note'),4000,false),$key,$zone,$start,$end];
            if($id){$stmt=$pdo->prepare('UPDATE audition_sessions SET title=?,mode=?,location=?,meeting_url=?,public_instructions=?,private_note=?,resource_key=?,display_zone=?,starts_at=?,ends_at=? WHERE id=?');$stmt->execute([...$values,$id]);}
            else{$stmt=$pdo->prepare('INSERT INTO audition_sessions (title,mode,location,meeting_url,public_instructions,private_note,resource_key,display_zone,starts_at,ends_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([...$values,$user['id']]);$id=(int)$pdo->lastInsertId();}
            audit($pdo,(int)$user['id'],'audition.session_saved',$id);$message=['Session saved.'];
        }elseif(in_array($action,['slot_add','slots_generate'],true)){
            $session=p_session($pdo,p_id('session_id'));$slots=[];
            if($action==='slot_add'){$slots[]=[p_date(field('starts_at'),$session['display_zone']),p_date(field('ends_at'),$session['display_zone'])];}
            else{
                $minutes=filter_var(field('duration'),FILTER_VALIDATE_INT,['options'=>['min_range'=>5,'max_range'=>120]]);$break=filter_var(field('break_minutes'),FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>60]]);
                if(!$minutes||$break===false){throw new DomainException('Use a duration of 5–120 minutes and a break of 0–60 minutes.');}
                $stmt=$pdo->prepare('SELECT COUNT(*) FROM audition_slots WHERE session_id=?');$stmt->execute([$session['id']]);if((int)$stmt->fetchColumn()>0){throw new DomainException('Automatic generation is available only for an empty session. Add individual slots to an existing schedule.');}
                for($time=strtotime($session['starts_at'].' UTC');$time+$minutes*60<=strtotime($session['ends_at'].' UTC');$time+=($minutes+$break)*60){$slots[]=[gmdate('Y-m-d H:i:s',$time),gmdate('Y-m-d H:i:s',$time+$minutes*60)];if(count($slots)>100){throw new DomainException('Generate at most 100 slots in one session.');}}
                if(!$slots){throw new DomainException('No complete slot fits inside this session.');}
            }
            $stmt=$pdo->prepare('SELECT COUNT(*) FROM audition_slots WHERE session_id=?');$stmt->execute([$session['id']]);if((int)$stmt->fetchColumn()+count($slots)>100){throw new DomainException('A session can contain at most 100 slots. Create another session for additional auditions.');}
            foreach($slots as [$start,$end]){p_slot_check($pdo,$session,$start,$end);$stmt=$pdo->prepare('INSERT INTO audition_slots (session_id,starts_at,ends_at) VALUES (?,?,?)');$stmt->execute([$session['id'],$start,$end]);}
            audit($pdo,(int)$user['id'],'audition.slots_created',(int)$session['id'],'Slots: '.count($slots));$message=['Created '.count($slots).' non-overlapping slots.'];
        }elseif($action==='appointment_save'){$message=p_assign($pdo,$user);}
        elseif($action==='appointment_cancel'){$booking=p_booking($pdo,p_id('appointment_id'));if((int)$booking['version']!==p_id('version')){http_response_code(409);throw new DomainException('This booking changed. Reload it.');}p_app($pdo,$user,'casting',(int)$booking['application_id']);p_cancel($pdo,$user,$booking,r_text(field('private_note'),4000,false));$message=['Booking cancelled; its slot is available and previous links no longer work. Cancellation notice queued.'];}
        elseif($action==='session_cancel'){
            $session=p_session($pdo,p_id('session_id'));$stmt=$pdo->prepare("UPDATE audition_sessions SET state='cancelled' WHERE id=?");$stmt->execute([$session['id']]);
            $stmt=$pdo->prepare("SELECT b.id FROM audition_appointments b JOIN audition_slots s ON s.id=b.slot_id WHERE s.session_id=? AND b.state IN ('invited','confirmed')");$stmt->execute([$session['id']]);$ids=$stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach($ids as $id){p_cancel($pdo,$user,p_booking($pdo,(int)$id),'Session cancelled.');}
            audit($pdo,(int)$user['id'],'audition.session_cancelled',(int)$session['id']);$message=['Session cancelled. Affected bookings are cancelled and their links revoked. Send queued notices individually.'];
        }elseif($action==='attendance'){
            $booking=p_booking($pdo,p_id('appointment_id'));p_app($pdo,$user,'casting',(int)$booking['application_id']);$attendance=field('attendance');
            if(!in_array($attendance,['pending','present','absent'],true)||!in_array($booking['state'],['invited','confirmed'],true)){throw new DomainException('Attendance can be recorded only for an active booking.');}
            if($attendance!=='pending'&&strtotime($booking['starts_at'].' UTC')>time()){throw new DomainException('Record attendance after the slot starts.');}
            $stmt=$pdo->prepare('UPDATE audition_appointments SET attendance=? WHERE id=?');$stmt->execute([$attendance,$booking['id']]);p_event($pdo,$user,'attendance',(int)$booking['application_id'],(int)$booking['id'],null,r_text(field('private_note'),4000,false),['attendance'=>$attendance]);$message=['Private attendance record saved.'];
        }elseif($action==='callback_save'){
            $id=p_id('callback_id');$app=p_app($pdo,$user,'casting',p_id('application_id'));$note=r_text(field('private_note'),4000,false);$state=field('callback_state');
            if(!in_array($state,['requested','arranged','completed','closed'],true)){throw new DomainException('Choose a valid callback state.');}
            if($id){$stmt=$pdo->prepare('SELECT id FROM audition_callbacks WHERE id=? AND application_id=?');$stmt->execute([$id,$app['id']]);if(!$stmt->fetchColumn()){throw new DomainException('Callback not found for this application.');}$stmt=$pdo->prepare('UPDATE audition_callbacks SET state=?,private_note=? WHERE id=?');$stmt->execute([$state,$note,$id]);}
            else{$stmt=$pdo->prepare('INSERT INTO audition_callbacks (application_id,state,private_note,requested_by) VALUES (?,?,?,?)');$stmt->execute([$app['id'],$state,$note,$user['id']]);$id=(int)$pdo->lastInsertId();}
            p_event($pdo,$user,'callback_recorded',(int)$app['id'],null,$id,$note,['state'=>$state]);$message=['Private callback record saved. Arrange a slot separately to invite the applicant.'];
        }else{http_response_code(403);throw new DomainException('Unknown audition action.');}
        $pdo->commit();return $message;
    }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
}
function p_invite_url(string $token):string{
    global $config;$base=rtrim($config['base_url'],'/');$parts=parse_url($base);$scheme=$parts['scheme']??'';$host=$parts['host']??'';
    $local=$config['environment']==='local'&&in_array($host,['localhost','127.0.0.1','::1'],true);
    if(!$host||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||($scheme!=='https'&&!($local&&$scheme==='http'))){throw new DomainException('Configure base_url as the complete HTTPS public website address before sending invitations.');}
    return $base.'/audition.php?token='.rawurlencode($token);
}
function p_send_notice(PDO $pdo,array $user,int $id):array{
    global $config;
    if(($config['recruitment']['privacy_approved']??false)!==true||!r_mail_ready()){throw new DomainException('Verified email delivery and the privacy approval flag are required before sending invitations.');}
    p_lock($pdo);
    try{
        $stmt=$pdo->prepare('SELECT * FROM audition_notifications WHERE id=? FOR UPDATE');$stmt->execute([$id]);$notice=$stmt->fetch();if(!$notice){throw new DomainException('Notice not found.');}
        $booking=p_booking($pdo,(int)$notice['appointment_id']);$app=p_app($pdo,$user,'casting',(int)$booking['application_id']);
        if((int)$booking['version']!==(int)$notice['appointment_version']||$notice['state']==='superseded'){throw new DomainException('This notice was replaced by a newer booking update.');}
        if($notice['state']==='accepted'){throw new DomainException('This notice was already accepted by the email provider.');}
        if($notice['state']==='sending'&&(int)$notice['lease_until']>time()){throw new DomainException('This notice is already being sent. Refresh after a minute.');}
        $snapshot=json_decode($booking['public_snapshot'],true,512,JSON_THROW_ON_ERROR);$token=null;
        if($notice['kind']!=='cancel'){
            if($booking['state']!=='invited'||$booking['session_state']!=='active'||strtotime($booking['starts_at'].' UTC')<=time()){throw new DomainException('This booking no longer needs an invitation. Reschedule or create a new invitation if required.');}
            $token=bin2hex(random_bytes(32));$link=p_invite_url($token);$expiry=min(strtotime($booking['starts_at'].' UTC'),time()+max(1,min(168,(int)($config['auditions']['invitation_hours']??48)))*3600);
            $stmt=$pdo->prepare('UPDATE audition_appointments SET token_hash=?,token_expires=?,token_used_at=NULL WHERE id=?');$stmt->execute([r_key('audition:'.$token),$expiry,$booking['id']]);
            $subject=$notice['kind']==='change'?'See You Around — updated audition invitation':($booking['round']==='callback'?'See You Around — callback invitation':'See You Around — audition invitation');
            $text="Application: ".$app['reference']."\nRole: ".$snapshot['role_title']."\nAudition: ".p_time($booking['starts_at'],$snapshot['zone'])."\nOpen this private link to view details and confirm or decline:\n".$link."\nThe link expires ".gmdate('d M Y H:i',$expiry)." UTC and is replaced if the booking changes.\nDo not forward it. After responding, use email-verified application tracking to view your current schedule: ".r_url('track');
        }else{$subject='See You Around — audition cancelled';$text="Your audition for application ".$app['reference']." scheduled ".p_time($booking['starts_at'],$snapshot['zone'])." has been cancelled. No response is needed. Use email-verified application tracking for any current schedule. This does not communicate a final recruitment decision.";}
        $stmt=$pdo->prepare("UPDATE audition_notifications SET state='sending',lease_until=?,attempts=attempts+1 WHERE id=?");$stmt->execute([time()+60,$id]);$pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
    $ok=false;$logId=null;
    try{$ok=r_send($pdo,(int)$app['id'],$app['email'],'audition',$subject,$text);$logId=(int)$pdo->lastInsertId();}catch(Throwable $error){error_log('SYA audition delivery unavailable');}
    p_lock($pdo);try{
        $current=p_booking($pdo,(int)$booking['id']);
        $state=(int)$current['version']!==(int)$booking['version']?'superseded':($ok?'accepted':'failed');
        $stmt=$pdo->prepare('UPDATE audition_notifications SET state=?,lease_until=NULL,delivery_log_id=? WHERE id=?');$stmt->execute([$state,$logId,$id]);
        if(!$ok&&(int)$current['version']===(int)$booking['version']){$stmt=$pdo->prepare('UPDATE audition_appointments SET token_hash=NULL,token_expires=NULL WHERE id=?');$stmt->execute([$booking['id']]);}
        audit($pdo,(int)$user['id'],'audition.notice_'.$state,(int)$app['id']);$pdo->commit();
    }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
    if($state==='superseded'){return ['The booking changed while this notice was processing. Any older invitation link is invalid. Send the current queued update or cancellation.'];}
    return [$ok?'Email provider accepted the notice. Confirm delivery in your mailbox/provider dashboard.':'Delivery failed. The booking remains recorded; retry this notice after checking email settings. No raw invitation token is shown or logged.'];
}
function p_public_booking(PDO $pdo,array $grant,bool $receipt=false):?array{
    if((int)($grant['expires']??0)<=time()){return null;}
    $stmt=$pdo->prepare('SELECT b.id,b.application_id,b.version,b.state,b.token_hash,b.token_expires,b.token_used_at,b.public_snapshot,s.starts_at,s.ends_at,x.state AS session_state FROM audition_appointments b JOIN audition_slots s ON s.id=b.slot_id JOIN audition_sessions x ON x.id=s.session_id WHERE b.id=?');$stmt->execute([$grant['id']??0]);$row=$stmt->fetch();
    if(!$row||(int)$row['version']!==(int)($grant['version']??0)||$row['session_state']!=='active'||in_array($row['state'],['cancelled'],true)){return null;}
    if($receipt){return in_array($row['state'],['confirmed','declined'],true)?$row:null;}
    if($row['state']!=='invited'||$row['token_used_at']!==null||(int)$row['token_expires']<=time()||!hash_equals($row['token_hash']??'', $grant['hash']??'')){return null;}
    return $row;
}
function p_verified_schedule(PDO $pdo,int $app):array{
    $stmt=$pdo->prepare("SELECT b.state,b.public_snapshot FROM audition_appointments b JOIN audition_slots s ON s.id=b.slot_id JOIN audition_sessions x ON x.id=s.session_id WHERE b.application_id=? AND b.state IN ('invited','confirmed') AND x.state='active' AND s.ends_at>UTC_TIMESTAMP() ORDER BY s.starts_at LIMIT 20");$stmt->execute([$app]);$result=[];
    foreach($stmt as $row){$snapshot=json_decode($row['public_snapshot'],true);$result[]=['state'=>$row['state'],'details'=>array_intersect_key($snapshot,array_flip(['session_title','mode','location','meeting_url','instructions','starts_at','ends_at','zone','role_title','reference','round']))];}return $result;
}
