<?php
declare(strict_types=1);
require __DIR__.'/prepare-content.php';
$config['features']['newsletter']=true;$config['features']['contact']=true;
$config['communications']=['privacy_approved'=>true,'notice'=>'TEST ONLY communications/privacy notice. No real users.','notice_version'=>'test-comm-v1','privacy_contact'=>'privacy@example.test','rights_enabled'=>true,'response_days'=>30,'contact_retention_days'=>180];
file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n");
$app=(int)$pdo->query("SELECT id FROM recruitment_applications WHERE email='actor@example.test' ORDER BY id LIMIT 1")->fetchColumn();$key=bin2hex(random_bytes(32)).'.bin';$data='TEST PRIVATE ATTACHMENT';file_put_contents($config['storage']['private_path'].'/'.$key,$data);$stmt=$pdo->prepare('INSERT INTO recruitment_attachments(application_id,question_id,storage_key,mime,size_bytes) VALUES (?,?,?,?,?)');$stmt->execute([$app,'ctestfile',$key,'application/pdf',strlen($data)]);
echo "Disposable Milestone 6 fixture prepared. No real messages or personal data.\n";
