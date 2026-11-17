<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';require dirname(__DIR__).'/app/production.php';require dirname(__DIR__).'/app/evaluations.php';
function ok(bool $condition,string $name):void{if(!$condition){throw new RuntimeException($name);}echo "PASS $name\n";}
function reject(callable $fn,string $name):void{try{$fn();}catch(DomainException $error){echo "PASS $name\n";return;}throw new RuntimeException($name);}
ok(p_date('2030-01-01T09:00','Africa/Lagos')==='2030-01-01 08:00:00','WAT input converts correctly to UTC');
reject(fn()=>p_date('2030-02-31T10:00','UTC'),'invalid calendar date rejected');reject(fn()=>p_date('2030-01-01T10:00','unknown'),'unknown time zone rejected');
$schema=v_schema([['id'=>'cfirst','label'=>'Authenticity','weight'=>'3','order'=>20],['id'=>'csecond','label'=>'Delivery','weight'=>'1','order'=>10]]);
ok($schema[0]['id']==='csecond','criterion order preserved');$score=v_score($schema,['cfirst'=>'8','csecond'=>'4']);ok($score['average']===7.0,'weighted average matches 3:1 example');
reject(fn()=>v_score($schema,['cfirst'=>'11','csecond'=>'4']),'score above 10 rejected');reject(fn()=>v_score($schema,['cfirst'=>'NaN','csecond'=>'4']),'non-finite score rejected');reject(fn()=>v_score($schema,['cfirst'=>'8']),'missing criterion rejected');
reject(fn()=>v_schema([['id'=>'cone','label'=>'One','weight'=>'0']]),'zero weight rejected');reject(fn()=>v_schema([['id'=>'cone','label'=>'One','weight'=>'1'],['id'=>'cone','label'=>'Two','weight'=>'1']]),'duplicate criteria IDs rejected');
ok(v_schema([['id'=>'cone','label'=>'One','weight'=>'']])[0]['weight']===1.0,'blank optional weight defaults to one');
$config['base_url']='https://user:pass@example.test/public';reject(fn()=>p_invite_url(str_repeat('a',64)),'credential-bearing invitation base URL rejected');$config['base_url']='http://evil.example/public';reject(fn()=>p_invite_url(str_repeat('a',64)),'remote HTTP invitation base URL rejected');
