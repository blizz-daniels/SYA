<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit;}
require dirname(__DIR__).'/app/bootstrap.php';
if($config['environment']!=='local'||!str_ends_with($config['database']['name'],'_test')){exit('Disposable local database required');}
$pdo=database($config);$command=$argv[1]??'';$id=(int)($argv[2]??0);
if(str_starts_with($command,'SELECT ')){echo json_encode($pdo->query($command)->fetchAll());}
elseif($command==='due'){$stmt=$pdo->prepare('UPDATE cms_items SET scheduled_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?');$stmt->execute([$id]);}
elseif($command==='bulk'){
 $pdo->beginTransaction();
 for($n=1;$n<=25;$n++){
  foreach(['article','media_post'] as $kind){$slug='test-pagination-'.$kind.'-'.$n;$stmt=$pdo->prepare('INSERT INTO cms_items(kind,slug) VALUES (?,?)');$stmt->execute([$kind,$slug]);$item=(int)$pdo->lastInsertId();$stmt=$pdo->prepare("INSERT INTO cms_revisions(item_id,revision,title,excerpt,body,cover_asset_id,alt_text,character_json,state,author_name) VALUES (?,1,?,'TEST pagination excerpt','TEST pagination body',?,'TEST optimized image','[]','approved','test-fixture')");$stmt->execute([$item,'TEST pagination '.$kind.' '.$n,$kind==='media_post'?$id:null]);$rev=(int)$pdo->lastInsertId();$stmt=$pdo->prepare('UPDATE cms_items SET draft_revision_id=?,published_revision_id=? WHERE id=?');$stmt->execute([$rev,$rev,$item]);}
 }$pdo->commit();
}
elseif($command==='limits'){$pdo->exec('DELETE FROM auth_rate_limits');}
else{exit('Unknown command');}
