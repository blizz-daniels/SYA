<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}ob_start();
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';require dirname(__DIR__).'/app/content.php';require dirname(__DIR__).'/app/privacy.php';require dirname(__DIR__).'/app/backups.php';require dirname(__DIR__).'/app/readiness.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Disposable local test config required');}
function final_ok(bool $condition,string $name):void{if(!$condition){throw new RuntimeException($name);}echo 'PASS '.$name.PHP_EOL;}
$config['recruitment']['uploads_enabled']=false;
final_ok(is_dir(b_dir()),'verified backup storage works with new recruitment uploads disabled');
$blocked=false;try{r_storage();}catch(DomainException $e){$blocked=true;}final_ok($blocked,'disabled new recruitment uploads remain blocked');
$blocked=false;try{b_key("Strong-Printable-Backup-0123456789\n",random_bytes(16));}catch(DomainException $e){$blocked=true;}final_ok($blocked,'nonprintable backup passphrase rejected before an unrecoverable export');
final_ok(str_contains(asset('css/site.css'),'?v='),'static asset URLs carry a change version');
$pdo=database($config);$checks=launch_checks($config,$pdo,true,false);$serialized=json_encode($checks);foreach([$config['security']['app_key'],$config['database']['password']] as $secret){final_ok($secret===''||!str_contains($serialized,$secret),'readiness output excludes configured secrets');}
final_ok((bool)array_filter($checks,fn($c)=>$c['name']==='Production mode'&&$c['status']==='BLOCKED'),'production checker rejects local mode');
