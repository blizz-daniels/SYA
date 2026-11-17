<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/recruitment.php';
function ok(bool $condition,string $message):void{if(!$condition){throw new RuntimeException($message);}echo 'PASS '.$message."\n";}
function rejects(callable $fn,string $message):void{try{$fn();}catch(DomainException $error){echo 'PASS '.$message."\n";return;}throw new RuntimeException($message);}
rejects(fn()=>r_schema([['id'=>'qone','label'=>'Pick','type'=>'select','options'=>'Only one']]),'selection requires at least two choices');
rejects(fn()=>r_schema([['id'=>'qone','label'=>'First','type'=>'text'],['id'=>'qone','label'=>'Duplicate','type'=>'text']]),'duplicate question identifiers rejected');
rejects(fn()=>r_schema([['id'=>'qone','label'=>'Unsafe','type'=>'html']]),'unsupported question types rejected');
$schema=r_schema([['id'=>'qtwo','label'=>'Later','type'=>'text','order'=>20],['id'=>'qone','label'=>'Earlier','type'=>'text','order'=>10]]);ok($schema[0]['id']==='qone','question ordering preserved');
rejects(fn()=>r_schema(array_map(fn($n)=>['id'=>'qfile'.$n,'label'=>'Upload','type'=>'file'],range(1,4))),'maximum three file questions');
rejects(fn()=>r_schema(array_map(fn($n)=>['id'=>'qtext'.$n,'label'=>'Text','type'=>'text'],range(1,21))),'maximum twenty nonempty questions');
$_POST=['qurl'=>'javascript:alert(1)'];rejects(fn()=>r_answers([['id'=>'qurl','label'=>'Link','type'=>'url','required'=>true]]),'javascript portfolio URLs rejected');
$_POST=['qurl'=>'https://user:password@example.test/path'];rejects(fn()=>r_answers([['id'=>'qurl','label'=>'Link','type'=>'url','required'=>true]]),'credential-bearing portfolio URLs rejected');
$_POST=['qurl'=>'https://example.test/portfolio'];ok(r_answers([['id'=>'qurl','label'=>'Link','type'=>'url','required'=>true]])['qurl']===$_POST['qurl'],'HTTPS external portfolio accepted without fetching it');
rejects(fn()=>r_text("invalid\xff",100),'invalid UTF-8 rejected');
