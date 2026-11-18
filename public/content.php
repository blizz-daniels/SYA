<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if($config['base_url']===''&&preg_match('~^/(?:[A-Za-z0-9_-]+/)*public/content\.php$~D',$_SERVER['SCRIPT_NAME']??'')){$config['base_url']=dirname($_SERVER['SCRIPT_NAME']);}
require dirname(__DIR__).'/app/admin-security.php';require dirname(__DIR__).'/app/content.php';require dirname(__DIR__).'/app/content-media.php';
try{
 $pdo=admin_start();if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD','POST'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
 if(is_file(__DIR__.'/setup.php')){http_response_code(503);throw new DomainException('Remove the temporary setup file before using administration.');}
 if(!$pdo->query('SELECT setup_completed_at FROM system_state WHERE id=1')->fetchColumn()){http_response_code(503);throw new DomainException('Complete initial setup before using administration.');}
 $user=current_admin($pdo);if(!$user){admin_redirect('login');}c_permission($user);
 $error='';$flash=$_SESSION['flash']??[];unset($_SESSION['flash']);
 if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check();try{$flash=c_action($pdo,$user);$_SESSION['flash']=$flash;header('Location: '.c_url((int)field('item_id')?['id'=>(int)field('item_id')]:[]),true,303);exit;}catch(DomainException $e){$error=$e->getMessage();}catch(PDOException $e){if($e->getCode()==='23000'){$error='That name or slug already exists.';}else{throw $e;}}}
 $id=isset($_GET['id'])&&is_string($_GET['id'])?(int)$_GET['id']:0;$item=$id?c_item($pdo,$id):null;$revision=$item&&!empty($item['draft_revision_id'])?c_revision($pdo,(int)$item['draft_revision_id'],$id):null;
 if($item&&isset($_GET['revision'])&&is_string($_GET['revision'])){$revision=c_revision($pdo,(int)$_GET['revision'],$id);}
 $isPreview=($_GET['preview']??'')==='1';$module=($_GET['tab']??'')==='media'?'media':'content';$contentView=true;$title=$isPreview?'Private content preview':'Content and media';
 $listPage=isset($_GET['list_page'])&&is_string($_GET['list_page'])?max(1,min(100000,(int)$_GET['list_page'])):1;
 $items=$pdo->query("SELECT i.*,r.title,r.state,r.revision FROM cms_items i LEFT JOIN cms_revisions r ON r.id=i.draft_revision_id AND r.item_id=i.id ORDER BY i.id DESC LIMIT 31 OFFSET ".(($listPage-1)*30))->fetchAll();$moreItems=count($items)>30;$items=array_slice($items,0,30);
 $categories=$pdo->query('SELECT * FROM cms_categories ORDER BY name LIMIT 200')->fetchAll();$collections=$pdo->query('SELECT * FROM media_collections ORDER BY name LIMIT 200')->fetchAll();$assetPage=isset($_GET['asset_page'])&&is_string($_GET['asset_page'])?max(1,min(100000,(int)$_GET['asset_page'])):1;$assetRows=$pdo->query('SELECT id,width,height,bytes FROM media_assets ORDER BY id DESC LIMIT 31 OFFSET '.(($assetPage-1)*30))->fetchAll();$moreAssets=count($assetRows)>30;$assets=array_slice($assetRows,0,30);
 $charData=$revision?json_decode($revision['character_json'],true):[];foreach([(int)($revision['cover_asset_id']??0),(int)($charData['portrait_id']??0)] as $selected){if($selected&&!in_array($selected,array_map('intval',array_column($assets,'id')),true)){$stmt=$pdo->prepare('SELECT id,width,height,bytes FROM media_assets WHERE id=?');$stmt->execute([$selected]);if($asset=$stmt->fetch()){$assets[]=$asset;}}}

 $announcements=$pdo->query("SELECT i.id,i.slug,r.title FROM cms_items i LEFT JOIN cms_revisions r ON r.id=i.draft_revision_id AND r.item_id=i.id WHERE kind='announcement' ORDER BY i.id DESC LIMIT 200")->fetchAll();
 $history=[];$events=[];if($item){$stmt=$pdo->prepare('SELECT id,revision,state,author_name,created_at FROM cms_revisions WHERE item_id=? ORDER BY revision DESC LIMIT 100');$stmt->execute([$id]);$history=$stmt->fetchAll();$stmt=$pdo->prepare('SELECT * FROM cms_events WHERE item_id=? ORDER BY id DESC LIMIT 100');$stmt->execute([$id]);$events=$stmt->fetchAll();}
 require dirname(__DIR__).'/resources/views/admin-layout.php';
}catch(Throwable $error){admin_failure($error);}
