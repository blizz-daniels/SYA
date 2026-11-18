<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
ob_start();
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/content.php';require dirname(__DIR__).'/app/content-media.php';
function c_expect(bool $ok,string $name):void{if(!$ok){throw new RuntimeException($name);}echo 'PASS '.$name."\n";}
function c_reject(callable $fn,string $name):void{try{$fn();}catch(DomainException $e){echo 'PASS '.$name."\n";return;}throw new RuntimeException($name);}
c_expect(c_https('https://example.test/film'),'valid external HTTPS link accepted without fetching it');
c_expect(!c_https('javascript:alert(1)')&&!c_https('http://example.test')&&!c_https('https://user:pass@example.test'),'script, plain HTTP and credential-bearing video links rejected');
c_expect(c_text('Isioma — Ọrẹ́',100,true)==='Isioma — Ọrẹ́','Nigerian UTF-8 text preserved');
c_reject(fn()=>c_text("bad\xFF",100),'invalid UTF-8 rejected');c_reject(fn()=>c_text("bad\0",100),'null/control characters rejected');c_reject(fn()=>c_text('abc',2),'text byte limit enforced');
c_reject(fn()=>c_super(['role'=>'content_editor']),'editor cannot approve/publish/reveal');c_reject(fn()=>c_permission(['role'=>'casting_manager']),'casting has no CMS authority');
$config['storage']['private_path']=dirname(__DIR__).'/public';$config['storage']['access_verified']=true;c_reject(fn()=>c_media_dir(),'public-directory media storage rejected');
$config['storage']['private_path']=dirname(__DIR__).'/storage/private';$config['storage']['access_verified']=false;c_reject(fn()=>c_media_dir(),'unverified storage fails closed');
if(!extension_loaded('gd')){$pdo=new class extends PDO{public function __construct(){}};c_reject(fn()=>c_upload($pdo,['role'=>'content_editor']),'missing GD fails closed before accepting an image');}
