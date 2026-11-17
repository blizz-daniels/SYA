<?php
declare(strict_types=1);
function r_admin_guard(PDO $pdo,array $user,string $kind): void {
    r_permission($user,$kind);
    if(isset($_GET['role'])&&is_string($_GET['role'])&&(int)$_GET['role']>0){$role=r_role($pdo,(int)$_GET['role']);if($role['kind']!==$kind){http_response_code(403);throw new DomainException('This role belongs to another department.');}}
    if(isset($_GET['application'])&&is_string($_GET['application'])&&(int)$_GET['application']>0){$stmt=$pdo->prepare('SELECT r.kind FROM recruitment_applications a JOIN recruitment_roles r ON r.id=a.role_id WHERE a.id=?');$stmt->execute([(int)$_GET['application']]);$department=$stmt->fetchColumn();if($department===false||$department!==$kind){http_response_code(404);throw new DomainException('Application not found in this department.');}}
}
function r_admin_action(PDO $pdo,array $user,string $kind,string $action): array {
    r_permission($user,$kind);
    $id=(int)field('role_id');$role=$id?r_role($pdo,$id):null;
    if($role&&$role['kind']!==$kind){http_response_code(403);throw new DomainException('This role belongs to another department.');}
    if($action==='role_save') {
        $schema=r_schema(is_array($_POST['questions']??null)?$_POST['questions']:[]);
        $min=filter_var(field('age_min'),FILTER_VALIDATE_INT,['options'=>['min_range'=>18,'max_range'=>100]]);
        $max=field('age_max')===''?null:filter_var(field('age_max'),FILTER_VALIDATE_INT,['options'=>['min_range'=>18,'max_range'=>100]]);
        $deadline=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',field('deadline'),new DateTimeZone('UTC'));
        if(!$min||$max===false||($max!==null&&$max<$min)||!$deadline||$deadline->format('Y-m-d\TH:i')!==field('deadline')){throw new DomainException('Use adult age limits and a valid UTC deadline.');}
        $values=[$kind,r_text(field('title'),150),r_text(field('description'),6000),r_text(field('mandatory_requirements'),4000),r_text(field('preferences'),4000,false),r_text(field('location'),200),r_text(field('availability'),300),$min,$max,$deadline->format('Y-m-d H:i:s'),json_encode($schema,JSON_THROW_ON_ERROR)];
        $pdo->beginTransaction();try{
            if($role){$stmt=$pdo->prepare("UPDATE recruitment_roles SET kind=?,title=?,description=?,mandatory_requirements=?,preferences=?,location=?,availability=?,age_min=?,age_max=?,deadline=?,draft_schema=?,state='draft' WHERE id=?");$stmt->execute([...$values,$id]);}
            else{$stmt=$pdo->prepare('INSERT INTO recruitment_roles (kind,title,description,mandatory_requirements,preferences,location,availability,age_min,age_max,deadline,draft_schema,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([...$values,$user['id']]);$id=(int)$pdo->lastInsertId();}
            audit($pdo,(int)$user['id'],'recruitment.role_saved',$id);$pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
        return ['Draft saved as role #'.$id.'. Saving pauses an open role until it is published again.'];
    }
    if(!$role&&$action!=='application_update'){throw new DomainException('Choose a role first.');}
    if($action==='role_publish') {
        $issues=r_readiness($pdo,json_decode($role['draft_schema'],true));if($issues){throw new DomainException(implode(' ',$issues));}
        if(strtotime($role['deadline'].' UTC')<=time()){throw new DomainException('Set a future deadline before publishing.');}
        $pdo->beginTransaction();try{
            $stmt=$pdo->prepare('SELECT * FROM recruitment_roles WHERE id=? FOR UPDATE');$stmt->execute([$id]);$role=$stmt->fetch();
            $issues=r_readiness($pdo,json_decode($role['draft_schema'],true));if($issues||strtotime($role['deadline'].' UTC')<=time()){throw new DomainException('Publication readiness changed. Review the draft and launch checks.');}
            $version=(int)$role['published_version']+1;
            $stmt=$pdo->prepare('INSERT INTO recruitment_form_versions (role_id,version,schema_json,role_snapshot,published_by) VALUES (?,?,?,?,?)');
            $snapshot=array_intersect_key($role,array_flip(['title','description','mandatory_requirements','preferences','location','availability','age_min','age_max','deadline','kind']));
            $stmt->execute([$id,$version,$role['draft_schema'],json_encode($snapshot,JSON_THROW_ON_ERROR),$user['id']]);
            $stmt=$pdo->prepare("UPDATE recruitment_roles SET state='open',published_version=?,closed_at=NULL WHERE id=?");$stmt->execute([$version,$id]);audit($pdo,(int)$user['id'],'recruitment.role_published',$id,'Version '.$version);$pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
        return ['Published a new immutable form version.'];
    }
    if($action==='role_close') {
        $pdo->beginTransaction();try{
            $stmt=$pdo->prepare("UPDATE recruitment_roles SET state='closed',closed_at=COALESCE(closed_at,UTC_TIMESTAMP()) WHERE id=?");$stmt->execute([$id]);
            $due=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+'.r_retention_months().' months')->format('Y-m-d H:i:s');
            $stmt=$pdo->prepare("UPDATE recruitment_applications SET retention_due_at=LEAST(COALESCE(retention_due_at,?),?) WHERE role_id=? AND outcome<>'selected'");$stmt->execute([$due,$due,$id]);
            audit($pdo,(int)$user['id'],'recruitment.role_closed',$id);$pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
        return ['Role closed. Existing applications remain available to authorized reviewers.'];
    }
    if($action==='template_save') {
        $stmt=$pdo->prepare('INSERT INTO recruitment_form_templates (kind,name,schema_json,created_by) VALUES (?,?,?,?)');$stmt->execute([$kind,r_text(field('template_name'),120),$role['draft_schema'],$user['id']]);audit($pdo,(int)$user['id'],'recruitment.template_saved',$id);return ['Reusable department template saved.'];
    }
    if($action==='template_apply') {
        $stmt=$pdo->prepare('SELECT schema_json FROM recruitment_form_templates WHERE id=? AND kind=?');$stmt->execute([(int)field('template_id'),$kind]);$schema=$stmt->fetchColumn();if($schema===false){throw new DomainException('Template not found in this department.');}
        $stmt=$pdo->prepare("UPDATE recruitment_roles SET draft_schema=?,state='draft' WHERE id=?");$stmt->execute([$schema,$id]);audit($pdo,(int)$user['id'],'recruitment.template_applied',$id);return ['Template copied into the draft. Existing submitted form versions are unchanged.'];
    }
    if($action==='application_update') {
        $pdo->beginTransaction();try{
        $stmt=$pdo->prepare('SELECT a.*,r.kind,r.closed_at,r.deadline,v.role_snapshot FROM recruitment_applications a JOIN recruitment_roles r ON r.id=a.role_id JOIN recruitment_form_versions v ON v.role_id=a.role_id AND v.version=a.form_version WHERE a.id=? FOR UPDATE');$stmt->execute([(int)field('application_id')]);$application=$stmt->fetch();
        if(!$application||$application['kind']!==$kind){http_response_code(403);throw new DomainException('Application is not available in your department.');}
        $status=field('public_status');$outcome=field('outcome');$note=r_text(field('private_note'),4000,false);
        if(!in_array($status,R_STATUSES,true)||!in_array($outcome,['pending','selected','not_selected'],true)){throw new DomainException('Choose a valid recruitment status and outcome.');}
        if($outcome!==$application['outcome']&&$user['role']!=='super_admin'){http_response_code(403);throw new DomainException('Only a Super Admin can change the final selection decision.');}
        $retention=null;
        if($outcome!=='selected'){$original=json_decode($application['role_snapshot'],true);$close=$application['closed_at']??$original['deadline'];$retention=(new DateTimeImmutable($close,new DateTimeZone('UTC')))->modify('+'.r_retention_months().' months')->format('Y-m-d H:i:s');if($application['retention_due_at']!==null){$retention=min($retention,$application['retention_due_at']);}}
            $stmt=$pdo->prepare('UPDATE recruitment_applications SET public_status=?,outcome=?,retention_due_at=? WHERE id=?');$stmt->execute([$status,$outcome,$retention,$application['id']]);
            $stmt=$pdo->prepare('INSERT INTO recruitment_events (application_id,actor_id,public_status,outcome,private_note) VALUES (?,?,?,?,?)');$stmt->execute([$application['id'],$user['id'],$status,$outcome,$note]);
            audit($pdo,(int)$user['id'],'recruitment.application_updated',(int)$application['id'],'Public status: '.$status.'; private outcome: '.$outcome);$pdo->commit();
        }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
        return ['Application updated. Notes and decisions remain private; actor identities are never revealed automatically.'];
    }
    throw new DomainException('Unknown recruitment action.');
}
function r_global_action(PDO $pdo,array $user,string $action): array {
    require_super($user);confirmation($pdo,$user);
    if($action==='recruitment_toggle'){
        $enabled=field('recruitment_enabled')==='1'?'1':'0';
        $pdo->beginTransaction();try{$stmt=$pdo->prepare("INSERT INTO admin_settings (setting_key,setting_value,updated_by) VALUES ('recruitment_enabled',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)");$stmt->execute([$enabled,$user['id']]);audit($pdo,(int)$user['id'],'recruitment.global_toggle',null,$enabled==='1'?'Operator enabled recruitment; configuration gates still apply.':'Recruitment disabled.');$pdo->commit();}catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}return ['Recruitment switch saved. Configuration and role deadlines still apply.'];
    }
    if($action==='recruitment_mail_test'){
        $email=trim(field('test_email'));if(!filter_var($email,FILTER_VALIDATE_EMAIL)){throw new DomainException('Enter a valid test recipient address.');}
        $ok=r_send($pdo,null,$email,'test','See You Around — email delivery test','This is the requested production-office email test. Confirm receipt before setting email.delivery_verified to true in private configuration.');
        audit($pdo,(int)$user['id'],'recruitment.email_test',null,$ok?'Provider accepted the test; inbox delivery must be confirmed.':'Delivery failed.');return [$ok?'Provider accepted the test email. Confirm it reaches the inbox before enabling delivery_verified.':'Email delivery failed. Check the private Resend settings and hosting outbound HTTPS support.'];
    }
    throw new DomainException('Unknown recruitment setting.');
}
