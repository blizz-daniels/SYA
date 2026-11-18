<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}ob_start();
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';require dirname(__DIR__).'/app/content.php';require dirname(__DIR__).'/app/communications.php';require dirname(__DIR__).'/app/backup-crypto.php';
function m_ok(bool $ok,string $name):void{if(!$ok){throw new RuntimeException($name);}echo 'PASS '.$name."\n";}
function m_reject(callable $fn,string $name):void{try{$fn();}catch(DomainException $e){echo 'PASS '.$name."\n";return;}throw new RuntimeException($name);}
m_reject(fn()=>m_topics([['production']]),'nested topic payload rejected without warnings');m_reject(fn()=>m_topics(['unknown']),'unknown marketing category rejected');m_reject(fn()=>m_topics([]),'empty marketing preference rejected');m_ok(m_topics(['production','production'])===['production'],'duplicate topic normalized');m_reject(fn()=>m_email("test@example.test\0"),'null-bearing email rejected');
$config['features']['newsletter']=true;$config['communications']=['privacy_approved'=>false];m_ok(!m_ready('newsletter'),'unapproved communications notice fails closed');
$config['base_url']='http://attacker.example';m_reject(fn()=>m_external(m_url('token')),'remote HTTP secure-link base rejected');
$pass='TEST-Unique-Backup-Passphrase-0123456789';$salt=random_bytes(16);$key=b_key($pass,$salt);$a=b_encrypt('TEST private data',$key,$salt);$b=b_encrypt('TEST private data',$key,$salt);m_ok($a!==$b,'fresh GCM nonce changes ciphertext per part');m_ok(b_decrypt($a,$pass)==='TEST private data','authenticated encryption round trip');$a[strlen($a)-1]=chr(ord($a[strlen($a)-1])^1);try{b_decrypt($a,$pass);throw new DomainException('tampering accepted');}catch(RuntimeException $e){m_ok(true,'tampered GCM ciphertext rejected');}m_reject(fn()=>b_key('1234',$salt),'weak backup passphrase rejected');
