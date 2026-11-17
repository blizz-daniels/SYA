<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Test database required');}
$pdo=database($config);$command=$argv[1]??'';
if(str_starts_with($command,'SELECT ')){echo json_encode($pdo->query($command)->fetchAll());}
elseif($command==='limits'){$pdo->exec('DELETE FROM auth_rate_limits');}
elseif($command==='expire_grant'){$directory=session_save_path()?:sys_get_temp_dir();foreach(glob($directory.'/sess_*') as $file){$data=file_get_contents($file);if(str_contains($data,'tracking_access|')){file_put_contents($file,preg_replace('/(s:7:"expires";i:)\d+;/','${1}1;',$data));}}}
elseif($command==='expire'){$pdo->exec('UPDATE recruitment_tracking_challenges SET expires_at=1');}
elseif($command==='disable'){$pdo->exec("UPDATE admin_settings SET setting_value='0' WHERE setting_key='recruitment_enabled'");}
elseif($command==='enable'){$pdo->exec("UPDATE admin_settings SET setting_value='1' WHERE setting_key='recruitment_enabled'");}
elseif($command==='privacy_off'||$command==='privacy_on'){$config['recruitment']['privacy_approved']=$command==='privacy_on';file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n");}
elseif($command==='mail_off'||$command==='mail_on'){$config['email']['provider']=$command==='mail_on'?'test':'disabled';file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n");}
elseif($command==='storage_off'||$command==='storage_on'){$config['storage']['access_verified']=$command==='storage_on';file_put_contents($configPath,"<?php\nreturn ".var_export($config,true).";\n");}
elseif($command==='deadline_past'){$pdo->exec("UPDATE recruitment_roles SET deadline='2020-01-01 00:00:00'");}
else{exit('Unknown command');}
