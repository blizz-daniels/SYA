<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if($config['base_url']===''&&preg_match('~^/(?:[A-Za-z0-9_-]+/)*public/production\.php$~D',$_SERVER['SCRIPT_NAME']??'')){$config['base_url']=dirname($_SERVER['SCRIPT_NAME']);}
require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';require dirname(__DIR__).'/app/production.php';require dirname(__DIR__).'/app/evaluations.php';require dirname(__DIR__).'/app/production-data.php';
try{require dirname(__DIR__).'/app/production-controller.php';}catch(Throwable $error){admin_failure($error);}
