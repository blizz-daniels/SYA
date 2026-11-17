<?php
$opportunities=[];
if(!empty($config['features']['recruitment'])){
    require_once dirname(__DIR__,2).'/app/admin-security.php';require_once dirname(__DIR__,2).'/app/recruitment.php';
    try{$connection=database($config);$stmt=$connection->prepare("SELECT * FROM recruitment_roles WHERE kind=? AND state='open' AND deadline>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 100");$stmt->execute([$page]);foreach($stmt as $candidate){if(r_open($connection,$candidate)){$opportunities[]=$candidate;}}}
    catch(Throwable $error){error_log('SYA public opportunities unavailable');}
}
?>
<?php if(!$opportunities):?><span class="badge">NO OPEN OPPORTUNITIES</span><h2><?= $page==='casting'?'Every role starts with a story.':'Behind every frame, a team.' ?></h2><p>No <?= $page==='casting'?'acting roles':'crew vacancies' ?> are currently accepting applications. Approved role descriptions, eligibility, locations and deadlines will be published here.</p><button class="button" disabled>Applications unavailable</button><?php else:foreach($opportunities as $opportunity):?><article class="panel"><h2><?= e($opportunity['title']) ?></h2><p><?= nl2br(e($opportunity['description'])) ?></p><p><?= e($opportunity['location']) ?> · Deadline <?= e($opportunity['deadline']) ?> UTC</p><a class="button" href="<?= e(r_url('apply',['role'=>$opportunity['id']])) ?>">View eligibility and apply →</a></article><?php endforeach;endif;?>
<p><a class="text-link" href="<?= e(rtrim($config['base_url'],'/').'/recruitment.php?action=track') ?>">Track an existing application →</a></p>
