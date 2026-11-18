<?php
declare(strict_types=1);
const C_KINDS=['article'=>'Article','diary'=>'Production diary','announcement'=>'Announcement','media_post'=>'Media post','character'=>'Character biography','film_info'=>'Film information'];
const C_SECTIONS=['hero'=>'Hero','story'=>'Story','characters'=>'Characters','join'=>'Join production','updates'=>'Updates','connected'=>'Stay connected'];
function c_url(array $params=[]):string{global $config;return rtrim($config['base_url'],'/').'/content.php'.($params?'?'.http_build_query($params):'');}
function c_public_url(array $item):string{return url('updates').'&story='.rawurlencode($item['slug']);}
function c_media_url(int $id,int $size=960,bool $preview=false):string{global $config;return rtrim($config['base_url'],'/').'/media.php?'.http_build_query(['id'=>$id,'size'=>$size]+($preview?['preview'=>'1']:[]));}
function c_text(string $s,int $max,bool $required=false):string{$invalid=preg_match('//u',$s)!==1||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$s);$s=trim($s);if($invalid||strlen($s)>$max||($required&&$s==='')||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$s)){throw new DomainException('A text field is missing, too long or contains invalid characters.');}return $s;}
function c_id(string $s):?int{if($s===''){return null;}if(!ctype_digit($s)||(int)$s<1){throw new DomainException('Choose a valid record.');}return (int)$s;}
function c_permission(array $user):void{if(!in_array($user['role'],['super_admin','content_editor'],true)){http_response_code(403);throw new DomainException('Content access is not authorized.');}}
function c_super(array $user):void{if($user['role']!=='super_admin'){http_response_code(403);throw new DomainException('Only the Super Admin can approve, publish, archive or reveal.');}}
function c_event(PDO $pdo,array $user,int $id,?int $rev,string $action,string $note=''):void{$stmt=$pdo->prepare('INSERT INTO cms_events(item_id,revision_id,actor_id,actor_name,action,note) VALUES (?,?,?,?,?,?)');$stmt->execute([$id,$rev,$user['id'],$user['username'],$action,$note]);audit($pdo,(int)$user['id'],'content.'.$action,$id);}
function c_item(PDO $pdo,int $id,bool $lock=false):array{$stmt=$pdo->prepare('SELECT * FROM cms_items WHERE id=?'.($lock?' FOR UPDATE':''));$stmt->execute([$id]);$i=$stmt->fetch();if(!$i){http_response_code(404);throw new DomainException('Content not found.');}return $i;}
function c_revision(PDO $pdo,int $id,int $item):array{$stmt=$pdo->prepare('SELECT * FROM cms_revisions WHERE id=? AND item_id=?');$stmt->execute([$id,$item]);$r=$stmt->fetch();if(!$r){http_response_code(404);throw new DomainException('Revision not found.');}return $r;}
function c_effective():string{return 'CASE WHEN i.scheduled_revision_id IS NOT NULL AND i.scheduled_at<=UTC_TIMESTAMP() THEN i.scheduled_revision_id ELSE i.published_revision_id END';}
function c_public_rows(PDO $pdo,?string $kind=null,int $limit=100,int $offset=0,?int $category=null,?int $collection=null):array{
 $sql='SELECT i.id,i.kind,i.slug,r.*,cat.name AS category,col.name AS collection FROM cms_items i JOIN cms_revisions r ON r.id=('.c_effective().') AND r.item_id=i.id AND r.state=\'approved\' LEFT JOIN cms_categories cat ON cat.id=r.category_id LEFT JOIN media_collections col ON col.id=r.collection_id WHERE i.archived=0';$params=[];if($kind==='updates'){$sql.=" AND i.kind IN ('article','diary','announcement','media_post')";}elseif($kind){$sql.=' AND i.kind=?';$params[]=$kind;}if($category!==null){$sql.=' AND r.category_id=?';$params[]=$category;}if($collection!==null){$sql.=' AND r.collection_id=?';$params[]=$collection;}$sql.=' ORDER BY r.created_at DESC,r.id DESC LIMIT '.max(1,min(1000,$limit)).' OFFSET '.max(0,$offset);$stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
}
function c_public_one(PDO $pdo,string $slug):?array{$stmt=$pdo->prepare('SELECT i.kind,i.slug,r.*,cat.name AS category,col.name AS collection FROM cms_items i JOIN cms_revisions r ON r.id=('.c_effective().') AND r.item_id=i.id AND r.state=\'approved\' LEFT JOIN cms_categories cat ON cat.id=r.category_id LEFT JOIN media_collections col ON col.id=r.collection_id WHERE i.archived=0 AND i.slug=?');$stmt->execute([$slug]);return $stmt->fetch()?:null;}
function c_character(PDO $pdo,array $r):array{
 $data=json_decode($r['character_json'],true)?:[];$public=['title'=>$r['title'],'description'=>$r['excerpt'],'body'=>$r['body'],'gender'=>in_array($r['slug'],['isioma','ayo'],true)?'Male character':'Character','revealed'=>false];
 if(!empty($data['reveal'])&&!empty($data['announcement_id'])){$stmt=$pdo->prepare('SELECT slug FROM cms_items WHERE id=? AND kind=\'announcement\'');$stmt->execute([$data['announcement_id']]);$slug=$stmt->fetchColumn();$announcement=$slug?c_public_one($pdo,$slug):null;if($announcement){$public+=['actor'=>$data['actor_name'],'biography'=>$data['actor_bio'],'portrait'=>(int)$data['portrait_id'],'alt'=>$data['portrait_alt'],'announcement'=>c_public_url($announcement)];$public['revealed']=true;}}
 return $public;
}
function c_validate(PDO $pdo,string $kind,string $slug):array{
 $d=[];foreach(['title'=>180,'excerpt'=>500,'body'=>60000,'alt_text'=>250,'caption'=>500,'credit'=>200,'seo_title'=>180,'seo_description'=>300] as $key=>$max){$d[$key]=c_text(field($key),$max,$key==='title');}
 foreach(['category_id'=>'cms_categories','collection_id'=>'media_collections','cover_asset_id'=>'media_assets'] as $key=>$table){$d[$key]=c_id(field($key));if($d[$key]){$stmt=$pdo->prepare('SELECT id FROM '.$table.' WHERE id=?');$stmt->execute([$d[$key]]);if(!$stmt->fetchColumn()){throw new DomainException('The selected category, collection or image does not exist.');}}}
 $d['video_url']=c_text(field('video_url'),1000);if($d['video_url']!==''&&!c_https($d['video_url'])){throw new DomainException('Video links must be complete HTTPS URLs without credentials.');}
 if($kind==='character'){$d['cover_asset_id']=null;$d['video_url']='';}
 if($d['cover_asset_id']&&$d['alt_text']===''){throw new DomainException('Provide meaningful alt text for the cover image.');}
 if($kind==='media_post'&&!$d['cover_asset_id']&&$d['video_url']===''){throw new DomainException('A media post needs an image or external video link.');}
 $character=['reveal'=>field('reveal')==='1','actor_name'=>c_text(field('actor_name'),180),'actor_bio'=>c_text(field('actor_bio'),6000),'portrait_id'=>c_id(field('portrait_id')),'portrait_alt'=>c_text(field('portrait_alt'),250),'announcement_id'=>c_id(field('announcement_id'))];
 if($kind==='character'){
  if(in_array($slug,['isioma','ayo'],true)&&$d['title']!==ucfirst($slug)){throw new DomainException('Isioma and Ayo retain their approved character names.');}
  if($character['portrait_id']){$stmt=$pdo->prepare('SELECT id FROM media_assets WHERE id=?');$stmt->execute([$character['portrait_id']]);if(!$stmt->fetchColumn()){throw new DomainException('Portrait image not found.');}}
  if($character['announcement_id']){$stmt=$pdo->prepare("SELECT id FROM cms_items WHERE id=? AND kind='announcement'");$stmt->execute([$character['announcement_id']]);if(!$stmt->fetchColumn()){throw new DomainException('Choose an announcement record.');}}
  if($character['reveal']&&(!$character['actor_name']||!$character['actor_bio']||!$character['portrait_id']||!$character['portrait_alt']||!$character['announcement_id'])){throw new DomainException('A reveal needs actor name, approved biography, portrait/alt text and an announcement.');}
 }
 $d['character_json']=json_encode($kind==='character'?$character:[],JSON_THROW_ON_ERROR);return $d;
}
function c_https(string $url):bool{$p=parse_url($url);return filter_var($url,FILTER_VALIDATE_URL)!==false&&($p['scheme']??'')==='https'&&!empty($p['host'])&&!isset($p['user'])&&!isset($p['pass']);}
function c_publish_check(PDO $pdo,array $item,array $r,array $user,string $action):void{
 c_super($user);if($r['state']!=='approved'){throw new DomainException('Approve this revision before publishing.');}
 $data=json_decode($r['character_json'],true)?:[];
 if($item['kind']==='character'&&!empty($data['reveal'])){
  if($action!=='reveal_publish'||field('reveal_confirm')!=='1'){throw new DomainException('Use the explicit Super Admin character reveal action and approval checkbox.');}
  $stmt=$pdo->prepare('SELECT slug FROM cms_items WHERE id=?');$stmt->execute([$data['announcement_id']]);$slug=$stmt->fetchColumn();$ann=$slug?c_public_one($pdo,$slug):null;if(!$ann||$ann['kind']!=='announcement'){throw new DomainException('Publish the linked official announcement before revealing this actor.');}
 }
}
function c_action(PDO $pdo,array $user):array{
 c_permission($user);$action=field('action');
 if($action==='upload'){return c_upload($pdo,$user);}
 if(in_array($action,['category_add','collection_add'],true)){$name=c_text(field('name'),$action==='category_add'?80:100,true);$table=$action==='category_add'?'cms_categories':'media_collections';if($action==='category_add'){$slug=strtolower(c_text(field('slug'),90,true));if(!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',$slug)){throw new DomainException('Use a lowercase category slug with hyphens.');}$stmt=$pdo->prepare('INSERT INTO cms_categories(name,slug) VALUES (?,?)');$stmt->execute([$name,$slug]);}else{$stmt=$pdo->prepare('INSERT INTO media_collections(name) VALUES (?)');$stmt->execute([$name]);}audit($pdo,(int)$user['id'],'content.'.$action);return ['Category or collection created.'];}
 if($action==='homepage'){c_super($user);$positions=[];$enabled=[];foreach(C_SECTIONS as $key=>$label){$position=field('order_'.$key);if(!ctype_digit($position)||(int)$position>99){throw new DomainException('Section positions must be 0–99.');}$positions[$key]=(int)$position;$enabled[$key]=field('enabled_'.$key)==='1';}asort($positions,SORT_NUMERIC);$json=json_encode(['order'=>array_keys($positions),'enabled'=>$enabled],JSON_THROW_ON_ERROR);$stmt=$pdo->prepare("INSERT INTO admin_settings(setting_key,setting_value) VALUES ('homepage',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");$stmt->execute([$json]);audit($pdo,(int)$user['id'],'content.homepage');return ['Homepage sections updated.'];}
 $pdo->beginTransaction();try{
 $id=(int)field('item_id');$item=$id?c_item($pdo,$id,true):null;
 if($action==='save'){
  if(!$item){$kind=field('kind');$slug=strtolower(c_text(field('slug'),100,true));if(!isset(C_KINDS[$kind])||!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D',$slug)){throw new DomainException('Choose a content type and a lowercase unique slug.');}$stmt=$pdo->prepare('INSERT INTO cms_items(kind,slug,created_by) VALUES (?,?,?)');$stmt->execute([$kind,$slug,$user['id']]);$id=(int)$pdo->lastInsertId();$item=c_item($pdo,$id,true);}
  if((int)field('expected_revision')!==(int)$item['draft_revision_id']){http_response_code(409);throw new DomainException('This draft changed. Reload before editing.');}
  $d=c_validate($pdo,$item['kind'],$item['slug']);$stmt=$pdo->prepare('SELECT COALESCE(MAX(revision),0)+1 FROM cms_revisions WHERE item_id=?');$stmt->execute([$id]);$version=(int)$stmt->fetchColumn();
  $keys=array_keys($d);$stmt=$pdo->prepare('INSERT INTO cms_revisions(item_id,revision,'.implode(',',$keys).',author_id,author_name) VALUES ('.implode(',',array_fill(0,count($keys)+4,'?')).')');$stmt->execute(array_merge([$id,$version],array_values($d),[$user['id'],$user['username']]));$rev=(int)$pdo->lastInsertId();$stmt=$pdo->prepare('UPDATE cms_items SET draft_revision_id=? WHERE id=?');$stmt->execute([$rev,$id]);c_event($pdo,$user,$id,$rev,'saved');$message='Draft revision saved. Previously published content remains unchanged.';
 }else{
  if(!$item){throw new DomainException('Choose a content record.');}$rev=(int)$item['draft_revision_id'];if(!$rev){throw new DomainException('Save a draft first.');}
  if((int)field('expected_revision')!==$rev){http_response_code(409);throw new DomainException('This draft changed. Reload before reviewing.');}$r=c_revision($pdo,$rev,$id);
  if($action==='submit'){if(!in_array($r['state'],['draft','changes_requested'],true)){throw new DomainException('Only a draft or requested revision can be submitted.');}$stmt=$pdo->prepare("UPDATE cms_revisions SET state='submitted' WHERE id=?");$stmt->execute([$rev]);$message='Submitted for Super Admin review.';}
  elseif(in_array($action,['approve','request_changes'],true)){c_super($user);if($action==='request_changes'&&$r['state']!=='submitted'){throw new DomainException('Only submitted content can be returned for changes.');}if($action==='approve'&&!in_array($r['state'],['draft','submitted','changes_requested'],true)){throw new DomainException('This revision is already approved.');}$note=c_text(field('note'),2000,$action==='request_changes');$stmt=$pdo->prepare('UPDATE cms_revisions SET state=? WHERE id=?');$stmt->execute([$action==='approve'?'approved':'changes_requested',$rev]);$message=$action==='approve'?'Revision approved. Publish separately when ready.':'Changes requested; publication is unchanged.';}
  elseif(in_array($action,['publish','reveal_publish'],true)){c_publish_check($pdo,$item,$r,$user,$action);$date=field('publish_at');$scheduled=null;if($date!==''){$time=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new DateTimeZone('UTC'));if(!$time||$time->format('Y-m-d\TH:i')!==$date||$time->getTimestamp()<=time()){throw new DomainException('Scheduled publication must be a valid future UTC time, or leave it blank to publish now.');}$scheduled=$time->format('Y-m-d H:i:s');}
   if($scheduled){$note='Scheduled publication: '.$scheduled.' UTC';$stmt=$pdo->prepare('UPDATE cms_items SET scheduled_revision_id=?,scheduled_at=?,archived=0 WHERE id=?');$stmt->execute([$rev,$scheduled,$id]);$message='Approved revision scheduled. It becomes public on the first request at or after the UTC time, without cron.';}
   else{$note='Published immediately';$stmt=$pdo->prepare('UPDATE cms_items SET published_revision_id=?,scheduled_revision_id=NULL,scheduled_at=NULL,archived=0 WHERE id=?');$stmt->execute([$rev,$id]);$message='Approved revision published.';}
  }elseif($action==='archive'){c_super($user);$stmt=$pdo->prepare('UPDATE cms_items SET archived=1,scheduled_revision_id=NULL,scheduled_at=NULL WHERE id=?');$stmt->execute([$id]);$message='Archived: content and exclusively referenced images are no longer public.';}
  elseif($action==='cancel_schedule'){c_super($user);$stmt=$pdo->prepare('UPDATE cms_items SET scheduled_revision_id=NULL,scheduled_at=NULL WHERE id=?');$stmt->execute([$id]);$message='Scheduled publication cancelled.';}
  else{http_response_code(403);throw new DomainException('Unknown content action.');}
  c_event($pdo,$user,$id,$rev,$action,$note??'');
 }
 $pdo->commit();return [$message,'Open record #'.$id.' to preview or continue editing.'];
 }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}if($error instanceof PDOException&&$error->getCode()==='23000'){throw new DomainException('That slug or name already exists, or a selected record is unavailable.');}throw $error;}
}
function c_home_settings(?PDO $pdo):array{$default=['order'=>array_keys(C_SECTIONS),'enabled'=>array_fill_keys(array_keys(C_SECTIONS),true)];if(!$pdo){return $default;}$stmt=$pdo->query("SELECT setting_value FROM admin_settings WHERE setting_key='homepage'");$s=json_decode((string)$stmt->fetchColumn(),true);if(!is_array($s)){return $default;}$order=array_values(array_intersect($s['order']??[],array_keys(C_SECTIONS)));$order=array_values(array_unique(array_merge($order,array_keys(C_SECTIONS))));return ['order'=>$order,'enabled'=>array_replace($default['enabled'],array_intersect_key($s['enabled']??[],$default['enabled']))];}

function c_public_filters(PDO $pdo,string $type):array{
 $isCollection=$type==='collection';$table=$isCollection?'media_collections':'cms_categories';$column=$isCollection?'collection_id':'category_id';
 $sql='SELECT DISTINCT t.id,t.name FROM cms_items i JOIN cms_revisions r ON r.id=('.c_effective().') AND r.item_id=i.id AND r.state=\'approved\' JOIN '.$table.' t ON t.id=r.'.$column.' WHERE i.archived=0 AND '.($isCollection?"i.kind='media_post'":"i.kind IN ('article','diary','announcement','media_post')").' ORDER BY t.name LIMIT 200';return $pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);
}
