<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SYA_TEST_RESET')!=='YES'){exit('Explicit CLI test reset required.');}
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/admin-security.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Refusing non-test installation.');}
$pdo=database($config);
foreach(['backup_parts','backup_runs','maintenance_writes','privacy_file_deletions','privacy_controls','communication_tokens','privacy_events','privacy_requests','contact_events','contact_inquiries','newsletter_deliveries','newsletter_campaigns','newsletter_events','newsletter_subscribers','cms_events','cms_revisions','cms_items','media_assets','media_collections','cms_categories','evaluations','evaluation_rubric_versions','evaluation_rubrics','audition_notifications','audition_events','audition_appointments','audition_callbacks','audition_slots','audition_sessions','recruitment_tracking_challenges','recruitment_delivery_log','recruitment_events','recruitment_attachments','recruitment_applications','recruitment_form_templates','recruitment_form_versions','recruitment_roles','audit_log','admin_settings','auth_rate_limits','administrators'] as $table){$pdo->exec('DELETE FROM '.$table.($table==='evaluations'?' ORDER BY id DESC':''));}
$pdo->exec('UPDATE system_state SET setup_completed_at=UTC_TIMESTAMP() WHERE id=1');
$config['features']['recruitment']=true;$config['email']=['provider'=>'test','api_key'=>'','from_email'=>'noreply@example.test','delivery_verified'=>true];
$config['recruitment']=['privacy_approved'=>true,'uploads_enabled'=>true,'privacy_notice'=>'TEST ONLY: recruitment privacy notice, approved for local fixtures only. No real applicant data.','privacy_contact'=>'privacy@example.test','notice_version'=>'test-v1'];
$config['storage']=['private_path'=>'/tmp/sya-m3-private','access_verified'=>true];
if(!is_dir($config['storage']['private_path'])){mkdir($config['storage']['private_path'],0700,true);}file_put_contents($config['storage']['private_path'].'/.htaccess',"Require all denied\n");
foreach(glob($config['storage']['private_path'].'/*.bin') as $file){unlink($file);}
$config['security']['app_key']=bin2hex(random_bytes(32));$config['security']['setup_token_hash']='';
file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n");
$fixture=[];
foreach(['super'=>'super_admin','cast'=>'casting_manager','crew'=>'crew_coordinator','editor'=>'content_editor'] as $name=>$role){$code='Sya-'.bin2hex(random_bytes(20));$stmt=$pdo->prepare('INSERT INTO administrators (username,private_code_hash,role) VALUES (?,?,?)');$stmt->execute([$name.'admin',hash_private_code($code),$role]);$fixture[$name]=$code;}
$pdo->exec("INSERT INTO admin_settings (setting_key,setting_value) VALUES ('recruitment_enabled','1')");
@mkdir(__DIR__.'/artifacts',0700,true);file_put_contents(__DIR__.'/artifacts/recruitment-fixture.json',json_encode($fixture));
echo "Disposable Milestone 3 fixture prepared. No real data or provider credentials used.\n";
