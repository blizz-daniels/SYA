<?php
declare(strict_types=1);
// Short-lived write leases let backup start refuse in-flight requests, then freeze mutations across HTTP parts.
if(PHP_SAPI!=='cli'&&($_SERVER['REQUEST_METHOD']??'')==='POST'&&is_file($configPath)){
 try{
  $guard=database($config);$guard->beginTransaction();$guard->query('SELECT id FROM system_state WHERE id=1 FOR UPDATE')->fetch();
  $s=$guard->query("SELECT setting_value FROM admin_settings WHERE setting_key='backup_until'")->fetchColumn();
  $script=basename($_SERVER['SCRIPT_NAME']??'');$action=is_string($_POST['action']??null)?$_POST['action']:'';
  $allowed=($script==='operations.php'&&str_starts_with($action,'backup_'))||($script==='admin.php'&&in_array($action,['login','logout'],true));
  if((int)$s>time()&&!$allowed){$guard->rollBack();http_response_code(503);header('Retry-After: 60');header('Cache-Control: no-store');exit('A protected backup is in progress. No changes were saved. Please try again shortly.');}
  $request=bin2hex(random_bytes(16));$stmt=$guard->prepare('INSERT INTO maintenance_writes(request_id,expires_at) VALUES (?,?)');$stmt->execute([$request,time()+600]);$guard->commit();$_SERVER['SYA_WRITE_LEASE']=$request;
  register_shutdown_function(static function()use($guard,$request):void{try{if($guard->inTransaction()){$guard->rollBack();}$stmt=$guard->prepare('DELETE FROM maintenance_writes WHERE request_id=?');$stmt->execute([$request]);}catch(Throwable $e){error_log('SYA write lease cleanup deferred');}});
 }catch(Throwable $e){if(isset($guard)&&$guard->inTransaction()){$guard->rollBack();}error_log('SYA write preflight unavailable: '.get_class($e));http_response_code(503);header('Cache-Control: no-store');header('Retry-After: 60');exit('Changes are temporarily unavailable. No changes were saved. Please return later.');}
}
