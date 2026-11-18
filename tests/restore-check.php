<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Local test config required');}
$config['database']['name']='see_you_around_m6_restore_test';$pdo=database($config);
$stmt=$pdo->query("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION");$groups=[];foreach($stmt as $r){$groups[$r['TABLE_NAME'].'.'.$r['CONSTRAINT_NAME']][]=$r;}
foreach($groups as $cols){$r=$cols[0];$on=[];$notNull=[];foreach($cols as $c){$on[]='t.`'.$c['COLUMN_NAME'].'`=r.`'.$c['REFERENCED_COLUMN_NAME'].'`';$notNull[]='t.`'.$c['COLUMN_NAME'].'` IS NOT NULL';}$query='SELECT COUNT(*) FROM `'.$r['TABLE_NAME'].'` t LEFT JOIN `'.$r['REFERENCED_TABLE_NAME'].'` r ON '.implode(' AND ',$on).' WHERE '.implode(' AND ',$notNull).' AND r.`'.$r['REFERENCED_COLUMN_NAME'].'` IS NULL';if((int)$pdo->query($query)->fetchColumn()){throw new RuntimeException('Restored foreign-key orphan');}}
echo 'PASS restored database: '.count($groups)." foreign-key relationships checked with no orphan records.\n";
if((int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn()!==6){throw new RuntimeException('Missing migration ledger');}
$record=json_decode(file_get_contents(__DIR__.'/artifacts/backup-result.json'),true);$stmt=$pdo->prepare('SELECT storage_key FROM recruitment_attachments WHERE storage_key=?');$stmt->execute([$record['attachment']]);if(!$stmt->fetchColumn()||hash_file('sha256',$record['output'].'/private/'.$record['attachment'])!==$record['hash']){throw new RuntimeException('Restored attachment/DB mismatch');}
echo "PASS restored native PDO, migration ledger and registered attachment bytes agree.\n";
