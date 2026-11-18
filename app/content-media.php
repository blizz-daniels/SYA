<?php
declare(strict_types=1);
function c_media_dir(bool $write=false):string{
 global $config;$base=$config['storage']['private_path']??dirname(__DIR__).'/storage/private';
 if(!is_string($base)||$base===''||!is_dir($base)){throw new DomainException('Configure an existing protected private storage directory before uploading media.');}
 $real=realpath($base);$public=realpath(dirname(__DIR__).'/public');if(!$real||($public&&($real===$public||str_starts_with($real,$public.DIRECTORY_SEPARATOR)))){throw new DomainException('Media storage must be outside the public directory.');}
 if(($config['storage']['access_verified']??false)!==true){throw new DomainException('Verify private storage protection before uploading or serving media.');}
 $dir=$real.'/cms';if(is_link($dir)){throw new DomainException('The protected media directory must not be a symbolic link.');}if($write&&!is_dir($dir)&&!mkdir($dir,0700)){throw new DomainException('Cannot create protected media storage.');}
 if($write){file_put_contents($dir.'/.htaccess',"Require all denied\n");}
 return $dir;
}
function c_upload(PDO $pdo,array $user):array{
 global $config;c_permission($user);
 if(!function_exists('imagecreatetruecolor')||!class_exists('finfo')){throw new DomainException('Image uploads require PHP GD and Fileinfo. Keep uploads disabled until the host provides them. External video links still work.');}
 $f=$_FILES['image']??null;if(!is_array($f)||!is_int($f['error']??null)||$f['error']!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name'])){throw new DomainException('Choose one JPG, PNG or WebP image within the upload limit.');}
 $max=max(1024,min(5242880,(int)($config['uploads']['max_bytes']??2097152)));if((int)$f['size']>$max||(int)$f['size']<1){throw new DomainException('Image exceeds the configured upload limit.');}
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$info=@getimagesize($f['tmp_name']);if(!$info||!in_array($mime,['image/jpeg','image/png','image/webp'],true)||($info['mime']??'')!==$mime||$info[0]<1||$info[1]<1||$info[0]*$info[1]>12000000||max($info[0],$info[1])>12000){throw new DomainException('Invalid image format or dimensions. Use JPG, PNG or WebP up to 12 megapixels.');}
 // Reject a decode likely to exceed shared-host memory before GD allocates it.
 $memory=ini_get('memory_limit');$limit=-1;if(preg_match('/\A(\d+)([KMG]?)\z/i',$memory,$m)){$limit=(int)$m[1]*(1024**(array_search(strtoupper($m[2]),['','K','M','G'],true)));}
 if($limit>0&&memory_get_usage(true)+$info[0]*$info[1]*8+20000000>$limit){throw new DomainException('Image is too large for this host memory limit. Resize it before uploading.');}
 $dir=c_media_dir(true);$key=bin2hex(random_bytes(32));$source=@imagecreatefromstring(file_get_contents($f['tmp_name']));if(!$source){throw new DomainException('Image could not be decoded.');}$files=[];$bytes=0;
 try{
 foreach([480,960,1600] as $size){$scale=min(1,$size/$info[0]);$w=max(1,(int)round($info[0]*$scale));$h=max(1,(int)round($info[1]*$scale));$image=imagecreatetruecolor($w,$h);$ivory=imagecolorallocate($image,245,241,236);imagefill($image,0,0,$ivory);imagecopyresampled($image,$source,0,0,0,0,$w,$h,$info[0],$info[1]);$path=$dir.'/'.$key.'-'.$size.'.jpg';$files[]=$path;if(!imagejpeg($image,$path,82)){throw new DomainException('Optimized image could not be stored.');}imagedestroy($image);chmod($path,0600);$bytes+=filesize($path);}
 $pdo->beginTransaction();$stmt=$pdo->prepare('INSERT INTO media_assets(storage_key,width,height,bytes,uploaded_by) VALUES (?,?,?,?,?)');$stmt->execute([$key,$info[0],$info[1],$bytes,$user['id']]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$user['id'],'content.image_uploaded',$id);$pdo->commit();return ['Image #'.$id.' optimized and stored privately. Attach it to a draft and publish through review.'];
 }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}foreach($files as $path){if(is_file($path)){unlink($path);}}throw $error;}finally{imagedestroy($source);}
}
function c_media_allowed(PDO $pdo,int $id):bool{
 // A portrait attached to an unrevealed draft cannot become public through another cover association.
 $stmt=$pdo->prepare("SELECT i.slug FROM cms_items i JOIN cms_revisions r ON r.id=i.draft_revision_id AND r.item_id=i.id WHERE i.kind='character' AND JSON_UNQUOTE(JSON_EXTRACT(r.character_json,'$.portrait_id'))=?");$stmt->execute([(string)$id]);
 foreach($stmt->fetchAll() as $char){$r=c_public_one($pdo,$char['slug']);$public=$r?c_character($pdo,$r):[];if(empty($public['revealed'])||($public['portrait']??0)!==$id){return false;}}
 $stmt=$pdo->prepare("SELECT i.slug,i.kind FROM cms_items i JOIN cms_revisions r ON r.id=(".c_effective().") AND r.item_id=i.id AND r.state='approved' WHERE i.archived=0 AND (r.cover_asset_id=? OR (i.kind='character' AND JSON_UNQUOTE(JSON_EXTRACT(r.character_json,'$.portrait_id'))=?))");$stmt->execute([$id,(string)$id]);
 foreach($stmt->fetchAll() as $candidate){if($candidate['kind']!=='character'){return true;}$r=c_public_one($pdo,$candidate['slug']);if($r){$p=c_character($pdo,$r);if(!empty($p['revealed'])&&$p['portrait']===$id){return true;}}}return false;
}
