<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Disposable local test config required');}$pdo=database($config);$command=$argv[1]??'';
if(str_starts_with($command,'SELECT ')){echo json_encode($pdo->query($command)->fetchAll());}
elseif($command==='uploads_off'||$command==='uploads_on'){$config['recruitment']['uploads_enabled']=$command==='uploads_on';file_put_contents($configPath,'<?php'.PHP_EOL.'return '.var_export($config,true).';'.PHP_EOL);}
elseif($command==='preflight_off'){$pdo->exec('RENAME TABLE maintenance_writes TO maintenance_writes_fault_test');}
elseif($command==='preflight_on'){$pdo->exec('RENAME TABLE maintenance_writes_fault_test TO maintenance_writes');}
else{exit('Unknown command');}
