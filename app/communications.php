<?php
declare(strict_types=1);
const M_TOPICS=['recruitment'=>'Casting and recruitment','production'=>'Production news','reveals'=>'Cast reveals','release'=>'Trailers and release announcements'];
const M_CATEGORIES=['General','Press','Partnerships','Sponsorships','Production','Other'];
const M_CONTACT_STATES=['New','In Progress','Awaiting Reply','Resolved','Closed'];
function m_url(string $view='newsletter'):string{global $config;return rtrim($config['base_url'],'/').'/connect.php?view='.rawurlencode($view);}
function m_admin_url(string $view='inbox',array $params=[]):string{global $config;return rtrim($config['base_url'],'/').'/operations.php?'.http_build_query(['view'=>$view]+$params);}
function m_email(string $value):string{$email=strtolower(c_text($value,254,true));if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)){throw new DomainException('Enter a valid email address.');}return $email;}
function m_topics(mixed $values):array{if(!is_array($values)||count($values)>4||count(array_filter($values,'is_string'))!==count($values)||array_diff($values,array_keys(M_TOPICS))||!$values){throw new DomainException('Choose at least one valid update category.');}return array_values(array_unique($values));}
function m_ready(string $view):bool{global $config;$s=$config['communications']??[];$notice=trim($s['notice']??'');$privacy=($s['privacy_approved']??false)===true&&$notice!==''&&strlen($notice)<=12000&&filter_var($s['privacy_contact']??'',FILTER_VALIDATE_EMAIL)&&trim($s['notice_version']??'')!=='';
 return $privacy&&match($view){'newsletter','preferences'=>!empty($config['features']['newsletter'])&&r_mail_ready(),'contact'=>!empty($config['features']['contact']),'rights'=>!empty($s['rights_enabled'])&&r_mail_ready(),default=>false};}
function m_external(string $link):string{global $config;$base=rtrim($config['base_url'],'/');if(!c_https($base)&&!($config['environment']==='local'&&preg_match('~\Ahttp://(?:127\.0\.0\.1|localhost)(?::\d+)?(?:/[^?#]*)?\z~D',$base))){throw new DomainException('Configure the complete HTTPS public base_url before sending secure links.');}return $link;}
function m_token(PDO $pdo,string $kind,int $subject,array $payload=[],int $seconds=86400):string{$token=bin2hex(random_bytes(32));$stmt=$pdo->prepare('INSERT INTO communication_tokens(token_hash,kind,subject_id,payload_json,expires_at) VALUES (?,?,?,?,?)');$stmt->execute([r_key('comm:'.$token),$kind,$subject,json_encode($payload,JSON_THROW_ON_ERROR),time()+$seconds]);return m_external(m_url('token').'&token='.$token);}
function m_sub_event(PDO $pdo,int $id,string $action,array $topics,string $version):void{$s=$pdo->prepare('INSERT INTO newsletter_events(subscriber_id,action,topics_json,notice_version) VALUES (?,?,?,?)');$s->execute([$id,$action,json_encode($topics),$version]);}
function m_contact(PDO $pdo,array $user,int $id,bool $lock=false):array{$stmt=$pdo->prepare('SELECT * FROM contact_inquiries WHERE id=?'.($lock?' FOR UPDATE':''));$stmt->execute([$id]);$r=$stmt->fetch();if(!$r||($user['role']!=='super_admin'&&(int)$r['assigned_to']!==(int)$user['id'])){http_response_code(404);throw new DomainException('Inquiry not found in your assigned inbox.');}return $r;}
function m_contact_event(PDO $pdo,array $user,int $id,string $kind,string $body,string $state='none'):int{$stmt=$pdo->prepare('INSERT INTO contact_events(inquiry_id,actor_id,actor_name,kind,body,delivery_state) VALUES (?,?,?,?,?,?)');$stmt->execute([$id,$user['id'],$user['username'],$kind,$body,$state]);return (int)$pdo->lastInsertId();}
function m_staff(array $user):void{if(!isset(ADMIN_ROLES[$user['role']])){http_response_code(403);throw new DomainException('Staff access required.');}}
function m_campaign_permission(array $user):void{c_permission($user);}
function m_public_action(PDO $pdo,string $view):string{
 global $config;
 if(!m_ready($view)){throw new DomainException('This service is not currently accepting requests.');}
 $cleanup=$pdo->prepare('DELETE FROM communication_tokens WHERE expires_at<? LIMIT 100');$cleanup->execute([time()-86400]);
 $email=m_email(field('email'));if(!rate_limit($pdo,[ip_hash('comm_'.$view)=>8,r_key('comm:'.$view.':'.$email)=>3])){http_response_code(429);throw new DomainException('Too many requests. Try again in 15 minutes.');}
 if(field('website')!==''){return 'Your request has been received.';}
 $notice=$config['communications'];if(field('acknowledge')!=='1'){throw new DomainException('Read and acknowledge the privacy notice.');}
 if($view==='preferences'){$stmt=$pdo->prepare("SELECT id FROM newsletter_subscribers WHERE email_hash=? AND state='active'");$stmt->execute([r_key($email)]);$id=$stmt->fetchColumn();if($id){$link=m_token($pdo,'manage',(int)$id,[],600);r_send($pdo,null,$email,'confirmation','See You Around — update preferences',"Review your preferences or unsubscribe using this private link within 10 minutes:\n".$link);}return 'If an active subscription matches, a private preferences link will arrive. Check spam too.';}
 if($view==='newsletter'){
  $topics=m_topics($_POST['topics']??[]);$pdo->beginTransaction();try{
  $stmt=$pdo->prepare('SELECT * FROM newsletter_subscribers WHERE email_hash=? FOR UPDATE');$stmt->execute([r_key($email)]);$sub=$stmt->fetch();
  if(!$sub){$stmt=$pdo->prepare('INSERT INTO newsletter_subscribers(email,email_hash,topics_json,notice_version,notice_text) VALUES (?,?,?,?,?)');$stmt->execute([$email,r_key($email),json_encode($topics),$notice['notice_version'],$notice['notice']]);$id=(int)$pdo->lastInsertId();}else{$id=(int)$sub['id'];}
  $stmt=$pdo->prepare("UPDATE communication_tokens SET used_at=? WHERE kind='confirm' AND subject_id=? AND used_at IS NULL");$stmt->execute([time(),$id]);
  $link=m_token($pdo,'confirm',$id,['topics'=>$topics,'version'=>$notice['notice_version'],'notice'=>$notice['notice']]);$pdo->commit();
  }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
  r_send($pdo,null,$email,'confirmation','See You Around — confirm your updates',"You requested optional film updates. Open this private link to review and confirm your preferences within 24 hours:\n".$link."\nOpening it alone does not subscribe you. If this was not you, ignore it. Recruitment applications never subscribe you automatically.");
  return 'If the request can be processed, a confirmation email will arrive. Check spam too. No subscription changes take effect until you confirm.';
 }
 if($view==='contact'){
  if(!in_array(field('category'),M_CATEGORIES,true)){throw new DomainException('Choose an inquiry category.');}
  $reference='CONTACT-'.strtoupper(bin2hex(random_bytes(16)));$stmt=$pdo->prepare('INSERT INTO contact_inquiries(reference,full_name,email,email_hash,category,subject,message,notice_version,notice_text) VALUES (?,?,?,?,?,?,?,?,?)');$stmt->execute([$reference,c_text(field('full_name'),150,true),$email,r_key($email),field('category'),c_text(field('subject'),180,true),c_text(field('message'),10000,true),$notice['notice_version'],$notice['notice']]);return 'Inquiry received. Your reference is '.$reference.'. This does not submit a casting application or subscribe you to updates.';
 }
 $kind=field('request_kind');if(!in_array($kind,['access','delete'],true)){throw new DomainException('Choose access or deletion.');}
 $reference='PRIVACY-'.strtoupper(bin2hex(random_bytes(16)));$days=max(1,min(90,(int)($notice['response_days']??30)));$stmt=$pdo->prepare("INSERT INTO privacy_requests(reference,email,email_hash,kind,due_at) VALUES (?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? DAY))");$stmt->execute([$reference,$email,r_key($email),$kind,$days]);$id=(int)$pdo->lastInsertId();$ack=$pdo->prepare('INSERT INTO privacy_events(request_id,actor_name,note) VALUES (?,?,?)');$ack->execute([$id,'Requester','Privacy information acknowledged: '.$notice['notice_version']."\n".$notice['notice']]);$link=m_token($pdo,'rights',$id,[],3600);r_send($pdo,null,$email,'rights','See You Around — verify your privacy request',"Verify your email for privacy request ".$reference." within one hour:\n".$link."\nThis verifies email access only; staff may need additional proportionate verification before releasing or deleting records. If this was not you, ignore it.");return 'If the request can be processed, an email verification link will arrive. Check spam too. No records are released or deleted automatically.';
}
function m_token_post(PDO $pdo):string{
 $grant=$_SESSION['comm_grant']??[];if((int)($grant['expires']??0)<=time()){throw new DomainException('This link is unavailable or expired. Request a new link.');}
 p_lock($pdo);try{
 $stmt=$pdo->prepare('SELECT * FROM communication_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>? FOR UPDATE');$stmt->execute([$grant['hash']??'',time()]);$token=$stmt->fetch();if(!$token){throw new DomainException('This link is unavailable, replaced or already used.');}$payload=json_decode($token['payload_json'],true);
 if($token['kind']==='rights'){
  $stmt=$pdo->prepare("UPDATE privacy_requests SET state='Verified',verified_at=UTC_TIMESTAMP() WHERE id=? AND state='Awaiting Verification'");$stmt->execute([$token['subject_id']]);$message='Email verified. The production team will review your request. No records have been released or deleted.';
 }else{
  $stmt=$pdo->prepare('SELECT * FROM newsletter_subscribers WHERE id=? FOR UPDATE');$stmt->execute([$token['subject_id']]);$sub=$stmt->fetch();if(!$sub||$sub['state']==='removed'){throw new DomainException('This subscription link is unavailable.');}
  if(field('choice')==='unsubscribe'){$stmt=$pdo->prepare("UPDATE newsletter_subscribers SET state='unsubscribed',topics_json='[]',unsubscribed_at=UTC_TIMESTAMP() WHERE id=?");$stmt->execute([$sub['id']]);$invalidate=$pdo->prepare("UPDATE communication_tokens SET used_at=? WHERE subject_id=? AND kind='confirm' AND used_at IS NULL");$invalidate->execute([time(),$sub['id']]);m_sub_event($pdo,(int)$sub['id'],'unsubscribed',[],$sub['notice_version']);$message='Unsubscribed. No new campaign sends will be started; an email already processing may still arrive.';}
  else{
   if(field('choice')!=='save'){throw new DomainException('Choose save preferences or unsubscribe.');}$topics=m_topics($_POST['topics']??[]);
   if($token['kind']==='manage'&&$sub['state']!=='active'){throw new DomainException('This subscription is inactive. Submit a fresh opt-in to subscribe again.');}
   $stmt=$pdo->prepare("UPDATE newsletter_subscribers SET state='active',topics_json=?,notice_version=?,notice_text=?,confirmed_at=COALESCE(confirmed_at,UTC_TIMESTAMP()),unsubscribed_at=NULL WHERE id=?");$stmt->execute([json_encode($topics),$payload['version']??$sub['notice_version'],$payload['notice']??$sub['notice_text'],$sub['id']]);m_sub_event($pdo,(int)$sub['id'],$token['kind']==='confirm'?'confirmed':'preferences',$topics,$payload['version']??$sub['notice_version']);$message='Your confirmed update preferences have been saved.';
  }
 }
 $stmt=$pdo->prepare('UPDATE communication_tokens SET used_at=? WHERE token_hash=?');$stmt->execute([time(),$token['token_hash']]);$pdo->commit();unset($_SESSION['comm_grant']);return $message;
 }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
}
function m_campaign_digest(array $c):string{return hash('sha256',$c['subject']."\0".$c['body']."\0".$c['topic']);}
function m_campaign_send(PDO $pdo,array $user,int $id):array{
 c_super($user);if(!m_ready('newsletter')){throw new DomainException('Approved newsletter notice, feature flag and verified email delivery are required.');}
 p_lock($pdo);try{
 $stmt=$pdo->prepare('SELECT d.*,c.subject,c.body,c.topic,c.state AS campaign_state,s.email,s.state AS subscriber_state,s.topics_json FROM newsletter_deliveries d JOIN newsletter_campaigns c ON c.id=d.campaign_id JOIN newsletter_subscribers s ON s.id=d.subscriber_id WHERE d.id=? FOR UPDATE');$stmt->execute([$id]);$d=$stmt->fetch();if(!$d||$d['campaign_state']!=='ready'||in_array($d['state'],['accepted','skipped'],true)){throw new DomainException('This delivery is not available for sending.');}
 if($d['state']==='sending'&&(int)$d['lease_until']>time()){throw new DomainException('Already sending. Wait a minute before retrying.');}
 if($d['subscriber_state']!=='active'||!in_array($d['topic'],json_decode($d['topics_json'],true),true)){$stmt=$pdo->prepare("UPDATE newsletter_deliveries SET state='skipped',lease_until=NULL WHERE id=?");$stmt->execute([$id]);$pdo->commit();return ['Inactive or changed subscription skipped.'];}
 $link=m_token($pdo,'manage',(int)$d['subscriber_id'],[],90*86400);$stmt=$pdo->prepare("UPDATE newsletter_deliveries SET state='sending',lease_until=?,attempts=attempts+1 WHERE id=?");$stmt->execute([time()+60,$id]);$pdo->commit();
 }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
 $ok=false;$log=null;try{$ok=r_send($pdo,null,$d['email'],'newsletter',$d['subject'],$d['body']."\n\nYou confirmed optional See You Around updates. Manage preferences or unsubscribe (review the page, then confirm):\n".$link."\nIf this link has expired, request a fresh preferences link at ".m_external(m_url('preferences')), 'campaign-'.$id.'-attempt-'.$d['attempts']);$log=(int)$pdo->lastInsertId();}catch(Throwable $e){error_log('SYA campaign delivery unavailable');}
 $stmt=$pdo->prepare('UPDATE newsletter_deliveries SET state=?,lease_until=NULL,delivery_log_id=? WHERE id=?');$stmt->execute([$ok?'accepted':'failed',$log,$id]);audit($pdo,(int)$user['id'],'newsletter.delivery_'.$id);return [$ok?'Provider accepted the campaign email; inbox placement is not guaranteed.':'Campaign delivery failed; check the log and retry manually.'];
}
function m_admin_action(PDO $pdo,array $user):array{
 global $config;$action=field('action');m_staff($user);
 if(str_starts_with($action,'campaign_')){
  m_campaign_permission($user);if($action!=='campaign_save'){c_super($user);}if($action==='campaign_send'){return m_campaign_send($pdo,$user,(int)field('delivery_id'));}
  p_lock($pdo);try{
  $id=(int)field('campaign_id');$stmt=$pdo->prepare('SELECT * FROM newsletter_campaigns WHERE id=? FOR UPDATE');$stmt->execute([$id]);$c=$stmt->fetch();if($c&&in_array($action,['campaign_save','campaign_approve'],true)&&!hash_equals(m_campaign_digest($c),field('expected_digest'))){http_response_code(409);throw new DomainException('This campaign draft changed. Reload and review it before saving or approving.');}
  if($action==='campaign_save'){
   $subject=c_text(field('subject'),180,true);$body=c_text(field('body'),20000,true);$topic=field('topic');if(!isset(M_TOPICS[$topic])){throw new DomainException('Choose a campaign category.');}if($c&&$c['state']!=='draft'){throw new DomainException('Prepared campaigns are immutable; create a new draft.');}
   if($c){$stmt=$pdo->prepare('UPDATE newsletter_campaigns SET subject=?,body=?,topic=? WHERE id=?');$stmt->execute([$subject,$body,$topic,$id]);}else{$stmt=$pdo->prepare('INSERT INTO newsletter_campaigns(subject,body,topic,created_by) VALUES (?,?,?,?)');$stmt->execute([$subject,$body,$topic,$user['id']]);$id=(int)$pdo->lastInsertId();}
  }elseif($action==='campaign_approve'){c_super($user);if(!$c||$c['state']!=='draft'){throw new DomainException('Choose a draft campaign.');}$stmt=$pdo->prepare("UPDATE newsletter_campaigns SET state='preparing',approved_by=? WHERE id=?");$stmt->execute([$user['id'],$id]);}
  elseif($action==='campaign_queue'){c_super($user);if(!$c||$c['state']!=='preparing'){throw new DomainException('Choose an approved campaign being prepared.');}$stmt=$pdo->prepare("SELECT id,topics_json FROM newsletter_subscribers WHERE id>? AND state='active' ORDER BY id LIMIT 100");$stmt->execute([$c['cursor_id']]);$rows=$stmt->fetchAll();$cursor=(int)$c['cursor_id'];foreach($rows as $sub){$cursor=(int)$sub['id'];if(in_array($c['topic'],json_decode($sub['topics_json'],true),true)){$stmt=$pdo->prepare('INSERT IGNORE INTO newsletter_deliveries(campaign_id,subscriber_id) VALUES (?,?)');$stmt->execute([$id,$sub['id']]);}}$stmt=$pdo->prepare('UPDATE newsletter_campaigns SET cursor_id=?,state=? WHERE id=?');$stmt->execute([$cursor,count($rows)<100?'ready':'preparing',$id]);}
  elseif($action==='campaign_cancel'){c_super($user);$stmt=$pdo->prepare("UPDATE newsletter_campaigns SET state='cancelled' WHERE id=?");$stmt->execute([$id]);}else{throw new DomainException('Unknown campaign action.');}
  audit($pdo,(int)$user['id'],$action,$id?:null);$pdo->commit();return ['Campaign action recorded. Prepare recipients in batches, then send each queued delivery explicitly.'];
  }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
 }
 if($action==='contact_reply_send'){return m_reply_send($pdo,$user,(int)field('event_id'));}
 p_lock($pdo);try{
 $id=(int)field('inquiry_id');$inquiry=m_contact($pdo,$user,$id,true);
 if($action==='contact_assign'){c_super($user);$assigned=c_id(field('assigned_to'));if($assigned){$stmt=$pdo->prepare('SELECT id FROM administrators WHERE id=? AND is_active=1');$stmt->execute([$assigned]);if(!$stmt->fetchColumn()){throw new DomainException('Choose an active staff member.');}}$stmt=$pdo->prepare('UPDATE contact_inquiries SET assigned_to=? WHERE id=?');$stmt->execute([$assigned,$id]);m_contact_event($pdo,$user,$id,'assignment','Assigned staff ID: '.($assigned??'none'));}
 elseif($action==='contact_status'){
  $state=field('state');if(!in_array($state,M_CONTACT_STATES,true)){throw new DomainException('Choose an inquiry status.');}$date=field('follow_up_at');$follow=null;if($date!==''){$follow=p_date($date,'UTC');}
  $days=max(1,min(365,(int)($config['communications']['contact_retention_days']??180)));$closed=in_array($state,['Resolved','Closed'],true);$stmt=$pdo->prepare('UPDATE contact_inquiries SET state=?,follow_up_at=?,closed_at=IF(?,COALESCE(closed_at,UTC_TIMESTAMP()),closed_at),retention_due_at=IF(?,COALESCE(retention_due_at,DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? DAY)),retention_due_at) WHERE id=?');$stmt->execute([$state,$follow,(int)$closed,(int)$closed,$days,$id]);m_contact_event($pdo,$user,$id,'status',$state.($follow?' · follow up '.$follow.' UTC':''));
 }elseif(in_array($action,['contact_note','contact_reply'],true)){m_contact_event($pdo,$user,$id,$action==='contact_note'?'note':'reply',c_text(field('body'),10000,true),$action==='contact_reply'?'pending':'none');}
 else{throw new DomainException('Unknown inquiry action.');}audit($pdo,(int)$user['id'],$action,$id);$pdo->commit();return ['Inquiry updated. Reply drafts require a separate Send reply action.'];
 }catch(Throwable $e){if($pdo->inTransaction()){$pdo->rollBack();}throw $e;}
}
function m_reply_send(PDO $pdo,array $user,int $event):array{
 if(!m_ready('contact')||!r_mail_ready()){throw new DomainException('Approved contact settings and verified email delivery are required.');}
 p_lock($pdo);try{$stmt=$pdo->prepare("SELECT * FROM contact_events WHERE id=? AND kind='reply' FOR UPDATE");$stmt->execute([$event]);$e=$stmt->fetch();if(!$e){throw new DomainException('Reply not found.');}$inquiry=m_contact($pdo,$user,(int)$e['inquiry_id'],true);$stmt=$pdo->prepare("SELECT anonymized_at FROM privacy_controls WHERE scope='contact' AND subject_id=?");$stmt->execute([$inquiry['id']]);if($stmt->fetchColumn()){throw new DomainException('This inquiry was anonymized.');}if($e['delivery_state']==='accepted'||($e['delivery_state']==='sending'&&(int)$e['lease_until']>time())){throw new DomainException('Reply was accepted or is currently sending.');}$stmt=$pdo->prepare("UPDATE contact_events SET delivery_state='sending',lease_until=? WHERE id=?");$stmt->execute([time()+60,$event]);$pdo->commit();}catch(Throwable $x){if($pdo->inTransaction()){$pdo->rollBack();}throw $x;}
 $ok=false;$log=null;try{$ok=r_send($pdo,null,$inquiry['email'],'contact','See You Around — '.$inquiry['reference'],$e['body'],'contact-reply-'.$event);$log=(int)$pdo->lastInsertId();}catch(Throwable $x){error_log('SYA contact reply unavailable');}$stmt=$pdo->prepare('UPDATE contact_events SET delivery_state=?,lease_until=NULL,delivery_log_id=? WHERE id=?');$stmt->execute([$ok?'accepted':'failed',$log,$event]);audit($pdo,(int)$user['id'],'contact.reply_sent',(int)$inquiry['id']);return [$ok?'Provider accepted the reply. Confirm actual delivery separately.':'Reply delivery failed; a manual retry is available.'];
}
