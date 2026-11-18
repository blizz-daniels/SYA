<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/readiness.php';
$pdo=null;try{$pdo=database($config);}catch(Throwable $e){}
$checks=launch_checks($config,$pdo,in_array('--production',$argv,true));echo json_encode(['checks'=>$checks,'note'=>'Read-only configuration checks; not a claim of deployment or hosted launch approval.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit(array_filter($checks,fn($c)=>$c['status']==='BLOCKED')?1:0);
