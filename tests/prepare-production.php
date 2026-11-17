<?php
declare(strict_types=1);
require __DIR__.'/prepare-recruitment.php';
require dirname(__DIR__).'/app/recruitment.php';require dirname(__DIR__).'/app/production.php';require dirname(__DIR__).'/app/evaluations.php';
$fixture=json_decode(file_get_contents(__DIR__.'/artifacts/recruitment-fixture.json'),true);
$code='Sya-'.bin2hex(random_bytes(20));$stmt=$pdo->prepare("INSERT INTO administrators (username,private_code_hash,role) VALUES ('casttwo',?,'casting_manager')");$stmt->execute([hash_private_code($code)]);$fixture['casttwo']=$code;
$super=(int)$pdo->query("SELECT id FROM administrators WHERE username='superadmin'")->fetchColumn();
$roles=[];
foreach(['acting'=>'casting','acting_second'=>'casting','crew_role'=>'crew'] as $key=>$kind){
    $title='TEST ONLY '.$key;$snapshot=['title'=>$title,'kind'=>$kind,'deadline'=>'2030-12-31 12:00:00'];
    $stmt=$pdo->prepare("INSERT INTO recruitment_roles (kind,title,description,mandatory_requirements,preferences,location,availability,deadline,state,published_version,draft_schema,created_by) VALUES (?,?,'Synthetic fixture','Adults only','','Test city','Test availability','2030-12-31 12:00:00','closed',1,'[]',?)");$stmt->execute([$kind,$title,$super]);$id=(int)$pdo->lastInsertId();$roles[$key]=$id;
    $stmt=$pdo->prepare("INSERT INTO recruitment_form_versions (role_id,version,schema_json,role_snapshot,published_by) VALUES (?,1,'[]',?,?)");$stmt->execute([$id,json_encode($snapshot),$super]);
}
$apps=[];
foreach(['actor'=>['acting','actor@example.test'],'other'=>['acting','other@example.test'],'same_person'=>['acting_second','actor@example.test'],'crew'=>['crew_role','crew@example.test'],'third'=>['acting','third@example.test']] as $name=>[$key,$email]){
    $stmt=$pdo->prepare("INSERT INTO recruitment_applications (reference,role_id,form_version,full_name,email,email_hash,age,location,availability,answers_json,notice_version,privacy_notice,acknowledged_at) VALUES (?,?,1,?,?,?,25,'Test city','Available','{}','test-v1','Synthetic local fixture only',UTC_TIMESTAMP())");$stmt->execute(['SYA-'.strtoupper(bin2hex(random_bytes(16))),$roles[$key],'TEST '.$name,$email,r_key($email)]);$apps[$name]=(int)$pdo->lastInsertId();
}
$fixture['roles']=$roles;$fixture['apps']=$apps;file_put_contents(__DIR__.'/artifacts/production-fixture.json',json_encode($fixture));
$pdo->exec("UPDATE admin_settings SET setting_value='0' WHERE setting_key='recruitment_enabled'");
echo "Milestone 4 synthetic roles/applications prepared; public recruitment remains closed.\n";
