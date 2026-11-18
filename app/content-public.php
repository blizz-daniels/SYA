<?php
declare(strict_types=1);
$publicListPage=isset($_GET['list_page'])&&is_string($_GET['list_page'])?max(1,min(100000,(int)$_GET['list_page'])):1;
$categoryFilter=isset($_GET['category'])&&is_string($_GET['category'])&&ctype_digit($_GET['category'])?(int)$_GET['category']:null;
$collectionFilter=isset($_GET['collection'])&&is_string($_GET['collection'])&&ctype_digit($_GET['collection'])?(int)$_GET['collection']:null;
$visibleCategories=[];$visibleCollections=[];$moreUpdates=false;$moreMedia=false;
$cmsPdo=null;$cmsUnavailable=false;$story=null;$publicUpdates=[];$publicMedia=[];$filmInfo=[];$publicCharacters=[];
if(($config['database']['password']??'')!==''){
 try{$cmsPdo=database($config);$cmsPdo->query('SELECT id FROM cms_items LIMIT 1');}
 catch(Throwable $error){$cmsPdo=null;$cmsUnavailable=true;error_log('SYA public content unavailable: '.get_class($error));}
}
try{
 if($cmsPdo){
  if(in_array($page,['home','updates'],true)){
   $publicUpdates=c_public_rows($cmsPdo,'updates',21,$page==='updates'?($publicListPage-1)*20:0,$page==='updates'?$categoryFilter:null);
   $moreUpdates=count($publicUpdates)>20;$publicUpdates=array_slice($publicUpdates,0,20);
   if($page==='updates'){$visibleCategories=c_public_filters($cmsPdo,'category');}
  }
  if($page==='media'){
   $publicMedia=c_public_rows($cmsPdo,'media_post',21,($publicListPage-1)*20,null,$collectionFilter);
   $moreMedia=count($publicMedia)>20;$publicMedia=array_slice($publicMedia,0,20);$visibleCollections=c_public_filters($cmsPdo,'collection');
  }
  if($page==='about'){$filmInfo=c_public_rows($cmsPdo,'film_info',20);}
  if(in_array($page,['home','characters'],true)){
   foreach(c_public_rows($cmsPdo,'character',100) as $row){$publicCharacters[$row['slug']]=c_character($cmsPdo,$row);}
  }
  if($page==='updates'&&isset($_GET['story'])){
   if(!is_string($_GET['story'])){http_response_code(404);$page='404';}
   else{
    $story=c_public_one($cmsPdo,$_GET['story']);
    if(!$story||!in_array($story['kind'],['article','diary','announcement','media_post','film_info'],true)){$story=null;http_response_code(404);$page='404';}
    else{$title=$story['seo_title']?:$story['title'];}
   }
  }
 }elseif($page==='updates'&&isset($_GET['story'])){http_response_code($cmsUnavailable?503:404);$page='404';}
}catch(Throwable $error){$cmsUnavailable=true;$story=null;$publicUpdates=[];$publicMedia=[];$filmInfo=[];$publicCharacters=[];error_log('SYA public content unavailable: '.get_class($error));}
try{$homeSettings=c_home_settings($page==='home'?$cmsPdo:null);}catch(Throwable $error){$homeSettings=c_home_settings(null);$cmsUnavailable=true;}
