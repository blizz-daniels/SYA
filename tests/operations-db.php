<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Disposable local test database required.');}$pdo=database($config);$command=$argv[1]??'';$id=(int)($argv[2]??0);
if(str_starts_with($command,'SELECT ')){echo json_encode($pdo->query($command)->fetchAll());}
elseif($command==='seed_campaign'){
 $s=$pdo->prepare("INSERT INTO newsletter_subscribers(email,email_hash,state,topics_json,notice_version,notice_text,confirmed_at) VALUES ('race@example.test',?,'active','[\"production\"]','test','TEST synthetic consent',UTC_TIMESTAMP())");$s->execute([hash_hmac('sha256','race@example.test',$config['security']['app_key'])]);$sub=(int)$pdo->lastInsertId();$pdo->exec("INSERT INTO newsletter_campaigns(subject,body,topic,state) VALUES ('TEST simultaneous campaign','TEST race body','production','ready')");$campaign=(int)$pdo->lastInsertId();$s=$pdo->prepare('INSERT INTO newsletter_deliveries(campaign_id,subscriber_id) VALUES (?,?)');$s->execute([$campaign,$sub]);echo (int)$pdo->lastInsertId();
}
elseif($command==='seed_backup'){
 $config=require $configPath;$key=bin2hex(random_bytes(32)).'.bin';$bytes=random_bytes(2097209);file_put_contents($config['storage']['private_path'].'/'.$key,$bytes);$app=(int)$pdo->query("SELECT id FROM recruitment_applications WHERE email='other@example.test' LIMIT 1")->fetchColumn();$s=$pdo->prepare('INSERT INTO recruitment_attachments(application_id,question_id,storage_key,mime,size_bytes) VALUES (?,?,?,?,?)');$s->execute([$app,'cbackup',$key,'application/pdf',strlen($bytes)]);
 $dir=$config['storage']['private_path'].'/cms';if(!is_dir($dir)){mkdir($dir,0700,true);}$asset=bin2hex(random_bytes(32));$im=imagecreatetruecolor(64,64);foreach([480,960,1600] as $size){imagejpeg($im,$dir.'/'.$asset.'-'.$size.'.jpg');}imagedestroy($im);$s=$pdo->prepare('INSERT INTO media_assets(storage_key,width,height,bytes) VALUES (?,64,64,1000)');$s->execute([$asset]);echo json_encode(['attachment'=>$key,'hash'=>hash('sha256',$bytes),'asset'=>$asset]);
}
elseif($command==='limits'){$pdo->exec('DELETE FROM auth_rate_limits');}
elseif($command==='lease'){$pdo->exec("INSERT INTO maintenance_writes(request_id,expires_at) VALUES ('ffffffffffffffffffffffffffffffff',UNIX_TIMESTAMP()+600)");}
elseif($command==='clear_lease'){$pdo->exec("DELETE FROM maintenance_writes WHERE request_id='ffffffffffffffffffffffffffffffff'");}
elseif($command==='expire_tokens'){$pdo->exec('UPDATE communication_tokens SET expires_at=1');}
elseif($command==='selected'){$stmt=$pdo->prepare("UPDATE recruitment_applications SET outcome='selected',retention_due_at=NULL WHERE id=?");$stmt->execute([$id]);}
elseif($command==='expire_role'){$stmt=$pdo->prepare('UPDATE recruitment_roles SET deadline=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 13 MONTH) WHERE id=?');$stmt->execute([$id]);}
elseif($command==='expire_app'){$stmt=$pdo->prepare("UPDATE recruitment_applications SET outcome='not_selected',retention_due_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?");$stmt->execute([$id]);}
elseif($command==='expire_backup'){$stmt=$pdo->prepare('UPDATE backup_runs SET expires_at=1 WHERE id=?');$stmt->execute([$argv[2]]);$pdo->exec("UPDATE admin_settings SET setting_value='0' WHERE setting_key='backup_until'");}
else{exit('Unknown test command.');}
