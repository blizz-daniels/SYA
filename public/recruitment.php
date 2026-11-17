<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if($config['base_url']===''&&preg_match('~^/(?:[A-Za-z0-9_-]+/)*public/recruitment\.php$~D',$_SERVER['SCRIPT_NAME']??'')){$config['base_url']=dirname($_SERVER['SCRIPT_NAME']);}
require dirname(__DIR__).'/app/admin-security.php';
require dirname(__DIR__).'/app/recruitment.php';
try{require dirname(__DIR__).'/app/recruitment-public.php';}
catch(Throwable $error){
    if(!($error instanceof DomainException)){http_response_code(503);error_log('SYA recruitment unavailable: '.get_class($error));}
    $message=$error instanceof DomainException?$error->getMessage():'Recruitment is temporarily unavailable. Please return later.';
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recruitment — See You Around</title><h1>Recruitment</h1><p>'.e($message).'</p></html>';
}
