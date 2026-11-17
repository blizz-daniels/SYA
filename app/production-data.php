<?php
declare(strict_types=1);
function p_view_data(PDO $pdo,array $user,string $kind,string $section):array{
    $data=[];
    $id=isset($_GET['session'])&&is_string($_GET['session'])?max(0,(int)$_GET['session']):0;
    $appId=isset($_GET['application'])&&is_string($_GET['application'])?max(0,(int)$_GET['application']):0;
    $roleId=isset($_GET['role'])&&is_string($_GET['role'])?max(0,(int)$_GET['role']):0;
    $data['application']=$appId?p_app($pdo,$user,$kind,$appId):null;
    $stmt=$pdo->prepare('SELECT id,title FROM recruitment_roles WHERE kind=? ORDER BY id DESC LIMIT 100');$stmt->execute([$kind]);$data['roles']=$stmt->fetchAll();
    $stmt=$pdo->prepare('SELECT a.id,a.reference,a.full_name,r.title FROM recruitment_applications a JOIN recruitment_roles r ON r.id=a.role_id WHERE r.kind=? ORDER BY a.id DESC LIMIT 200');$stmt->execute([$kind]);$data['applications']=$stmt->fetchAll();
    if($section==='auditions'){
        $data['sessions']=$pdo->query('SELECT * FROM audition_sessions ORDER BY starts_at DESC,id DESC LIMIT 100')->fetchAll();$data['session']=$id?p_session($pdo,$id):null;
        $stmt=$pdo->query("SELECT s.*,x.title,x.display_zone FROM audition_slots s JOIN audition_sessions x ON x.id=s.session_id LEFT JOIN audition_appointments b ON b.active_slot_id=s.id WHERE x.state='active' AND s.starts_at>UTC_TIMESTAMP() AND b.id IS NULL ORDER BY s.starts_at LIMIT 300");$data['free_slots']=$stmt->fetchAll();
        $data['slots']=[];if($id){$stmt=$pdo->prepare('SELECT s.*,b.id AS booking_id,b.state,a.full_name,a.reference FROM audition_slots s LEFT JOIN audition_appointments b ON b.active_slot_id=s.id LEFT JOIN recruitment_applications a ON a.id=b.application_id WHERE s.session_id=? ORDER BY s.starts_at');$stmt->execute([$id]);$data['slots']=$stmt->fetchAll();}
        $data['booking_page']=isset($_GET['booking_page'])&&is_string($_GET['booking_page'])?max(1,min(100000,(int)$_GET['booking_page'])):1;
        $data['managed_booking']=isset($_GET['booking'])&&is_string($_GET['booking'])?max(0,(int)$_GET['booking']):0;
        $base='SELECT b.*,a.full_name,a.reference,s.starts_at,s.ends_at,x.display_zone,x.title FROM audition_appointments b JOIN recruitment_applications a ON a.id=b.application_id JOIN recruitment_roles r ON r.id=a.role_id JOIN audition_slots s ON s.id=b.slot_id JOIN audition_sessions x ON x.id=s.session_id';
        $stmt=$pdo->prepare($base." WHERE r.kind='casting'".($appId?' AND a.id=?':'').' ORDER BY b.id DESC LIMIT 21 OFFSET '.(($data['booking_page']-1)*20));$stmt->execute($appId?[$appId]:[]);$rows=$stmt->fetchAll();$data['more_bookings']=count($rows)>20;$data['bookings']=array_slice($rows,0,20);
        if($data['managed_booking']){$stmt=$pdo->prepare($base." WHERE r.kind='casting' AND b.id=?");$stmt->execute([$data['managed_booking']]);$managed=$stmt->fetch();if(!$managed){http_response_code(404);throw new DomainException('Booking not found in casting.');}if(!in_array($data['managed_booking'],array_map('intval',array_column($data['bookings'],'id')),true)){$data['bookings'][]=$managed;}}

        $stmt=$pdo->query("SELECT n.*,a.reference,a.full_name FROM audition_notifications n JOIN audition_appointments b ON b.id=n.appointment_id JOIN recruitment_applications a ON a.id=b.application_id WHERE n.state IN ('pending','failed','sending') ORDER BY n.id LIMIT 100");$data['notices']=$stmt->fetchAll();
        $data['callbacks']=$pdo->query('SELECT c.*,a.full_name,a.reference FROM audition_callbacks c JOIN recruitment_applications a ON a.id=c.application_id ORDER BY c.id DESC LIMIT 100')->fetchAll();
        $data['events']=[];if($appId){$stmt=$pdo->prepare('SELECT * FROM audition_events WHERE application_id=? ORDER BY id DESC LIMIT 100');$stmt->execute([$appId]);$data['events']=$stmt->fetchAll();}
    }else{
        if($data['application']){if($roleId&&$roleId!==(int)$data['application']['role_id']){http_response_code(404);throw new DomainException('Application and role do not match.');}$roleId=(int)$data['application']['role_id'];}
        $role=$roleId?r_role($pdo,$roleId):null;if($role&&$role['kind']!==$kind){http_response_code(404);throw new DomainException('Role not found in this department.');}$data['role']=$role;$data['rubric']=null;$data['criteria']=[];$data['versions']=[];
        if($role){$stmt=$pdo->prepare('SELECT * FROM evaluation_rubrics WHERE role_id=?');$stmt->execute([$roleId]);$data['rubric']=$stmt->fetch()?:null;$data['criteria']=$data['rubric']?json_decode($data['rubric']['draft_schema'],true):v_defaults($kind);$stmt=$pdo->prepare('SELECT version,published_at FROM evaluation_rubric_versions WHERE role_id=? ORDER BY version DESC LIMIT 50');$stmt->execute([$roleId]);$data['versions']=$stmt->fetchAll();}
        $data['score_schema']=[];$data['score_version']=0;$data['own_previous']=null;$data['reviews']=[];
        if($appId){$roleId=(int)$data['application']['role_id'];$stmt=$pdo->prepare('SELECT published_version FROM evaluation_rubrics WHERE role_id=?');$stmt->execute([$roleId]);$data['score_version']=(int)$stmt->fetchColumn();
            if($data['score_version']){$stmt=$pdo->prepare('SELECT schema_json FROM evaluation_rubric_versions WHERE role_id=? AND version=?');$stmt->execute([$roleId,$data['score_version']]);$data['score_schema']=json_decode($stmt->fetchColumn(),true);}
            $stmt=$pdo->prepare('SELECT * FROM evaluations WHERE application_id=? AND reviewer_id=? ORDER BY id DESC LIMIT 1');$stmt->execute([$appId,$user['id']]);$data['own_previous']=$stmt->fetch()?:null;
            $stmt=$pdo->prepare('SELECT e.*,v.schema_json FROM evaluations e JOIN evaluation_rubric_versions v ON v.role_id=e.role_id AND v.version=e.rubric_version WHERE e.application_id=? ORDER BY e.id DESC LIMIT 100');$stmt->execute([$appId]);$data['reviews']=$stmt->fetchAll();
        }
    }return $data;
}
