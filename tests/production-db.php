<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Disposable local database required');}
$pdo=database($config);$command=$argv[1]??'';$id=(int)($argv[2]??0);
if(str_starts_with($command,'SELECT ')){echo json_encode($pdo->query($command)->fetchAll());}
elseif($command==='limits'){$pdo->exec('DELETE FROM auth_rate_limits');}
elseif($command==='expire'){$stmt=$pdo->prepare('UPDATE audition_appointments SET token_expires=1 WHERE id=?');$stmt->execute([$id]);}
elseif($command==='past'){$stmt=$pdo->prepare('UPDATE audition_slots s JOIN audition_appointments b ON b.slot_id=s.id SET s.starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE),s.ends_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE) WHERE b.id=?');$stmt->execute([$id]);}
elseif($command==='privacy_off'||$command==='privacy_on'){$config['recruitment']['privacy_approved']=$command==='privacy_on';file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n");}
else{exit('Unknown command');}
