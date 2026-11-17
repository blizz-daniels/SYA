<?php
declare(strict_types=1);
function v_defaults(string $kind):array{
    $labels=$kind==='casting'?['Emotional authenticity','Dialogue delivery','Character interpretation','Body language','Chemistry and responsiveness']:['Technical ability','Relevant experience','Portfolio quality','Communication','Availability'];
    return array_map(fn($n,$label)=>['id'=>'c'.$n,'label'=>$label,'weight'=>1,'order'=>$n*10],array_keys($labels),$labels);
}
function v_schema(array $rows):array{
    if(count($rows)>50){throw new DomainException('Use at most 20 assessment criteria.');}$result=[];$ids=[];
    foreach($rows as $row){if(!is_array($row)||!empty($row['remove'])){continue;}$label=is_string($row['label']??null)?trim($row['label']):'';if($label===''){continue;}$id=$row['id']??'';$weight=$row['weight']??'1';if($weight===''){$weight='1';}
        if(!is_string($id)||!preg_match('/\Ac[a-z0-9_]{1,28}\z/D',$id)||isset($ids[$id])){throw new DomainException('Each criterion needs its own identifier.');}
        if(!is_scalar($weight)||!preg_match('/\A(?:[0-9]{1,2}(?:\.[0-9]{1,2})?|100(?:\.0{1,2})?)\z/D',(string)$weight)||(float)$weight<=0||(float)$weight>100){throw new DomainException('Weights must be greater than zero and at most 100, with at most two decimal places.');}
        $ids[$id]=true;$result[]=['id'=>$id,'label'=>r_text($label,120),'weight'=>(float)$weight,'order'=>(int)($row['order']??0)];
    }if(!$result||count($result)>20){throw new DomainException('Use between one and 20 nonempty criteria.');}usort($result,fn($a,$b)=>$a['order']<=>$b['order']);return $result;
}
function v_score(array $schema,array $posted):array{
    $scores=[];$weighted=0.0;$weights=0.0;
    foreach($schema as $criterion){$value=$posted[$criterion['id']]??'';
        if(!is_string($value)||!preg_match('/\A(?:[0-9](?:\.[0-9]{1,2})?|10(?:\.0{1,2})?)\z/D',$value)){throw new DomainException('Score '.$criterion['label'].' from 0 to 10, with at most two decimal places.');}
        $scores[$criterion['id']]=(float)$value;$weighted+=(float)$value*(float)$criterion['weight'];$weights+=(float)$criterion['weight'];
    }return ['scores'=>$scores,'average'=>round($weighted/$weights,2)];
}
function v_action(PDO $pdo,array $user,string $kind,string $action):array{
    r_permission($user,$kind);
    $pdo->beginTransaction();try{
        if(in_array($action,['rubric_save','rubric_publish'],true)){
            $role=r_role($pdo,p_id('role_id'));if($role['kind']!==$kind){http_response_code(403);throw new DomainException('This role is outside your department.');}
            $stmt=$pdo->prepare('SELECT id FROM recruitment_roles WHERE id=? FOR UPDATE');$stmt->execute([$role['id']]);$stmt->fetch();
            if($action==='rubric_save'){$schema=v_schema(is_array($_POST['criteria']??null)?$_POST['criteria']:[]);$stmt=$pdo->prepare('INSERT INTO evaluation_rubrics (role_id,draft_schema) VALUES (?,?) ON DUPLICATE KEY UPDATE draft_schema=VALUES(draft_schema)');$stmt->execute([$role['id'],json_encode($schema,JSON_THROW_ON_ERROR)]);audit($pdo,(int)$user['id'],'evaluation.rubric_saved',(int)$role['id']);$message='Assessment criteria draft saved. Publish it before recording scores.';}
            else{$stmt=$pdo->prepare('SELECT * FROM evaluation_rubrics WHERE role_id=? FOR UPDATE');$stmt->execute([$role['id']]);$rubric=$stmt->fetch();if(!$rubric){throw new DomainException('Save a rubric draft first.');}$version=(int)$rubric['published_version']+1;
                $stmt=$pdo->prepare('INSERT INTO evaluation_rubric_versions (role_id,version,schema_json,published_by) VALUES (?,?,?,?)');$stmt->execute([$role['id'],$version,$rubric['draft_schema'],$user['id']]);$stmt=$pdo->prepare('UPDATE evaluation_rubrics SET published_version=? WHERE role_id=?');$stmt->execute([$version,$role['id']]);audit($pdo,(int)$user['id'],'evaluation.rubric_published',(int)$role['id'],'Version '.$version);$message='Published a new immutable assessment version. Earlier evaluations are preserved.';}
        }elseif($action==='evaluation_save'){
            $app=p_app($pdo,$user,$kind,p_id('application_id'),true);$stmt=$pdo->prepare('SELECT published_version FROM evaluation_rubrics WHERE role_id=?');$stmt->execute([$app['role_id']]);$version=(int)$stmt->fetchColumn();
            if(!$version||$version!==p_id('rubric_version')){http_response_code(409);throw new DomainException('The assessment version changed. Reload before scoring.');}
            $stmt=$pdo->prepare('SELECT schema_json FROM evaluation_rubric_versions WHERE role_id=? AND version=?');$stmt->execute([$app['role_id'],$version]);$schema=json_decode($stmt->fetchColumn(),true,512,JSON_THROW_ON_ERROR);
            $score=v_score($schema,is_array($_POST['scores']??null)?$_POST['scores']:[]);$recommendation=field('recommendation');if(!isset(P_RECOMMENDATIONS[$recommendation])){throw new DomainException('Choose a valid recommendation.');}
            $stmt=$pdo->prepare('SELECT id,revision FROM evaluations WHERE application_id=? AND reviewer_id=? ORDER BY id DESC LIMIT 1');$stmt->execute([$app['id'],$user['id']]);$previous=$stmt->fetch();
            if(p_id('supersedes_id')!==(int)($previous['id']??0)){http_response_code(409);throw new DomainException('Your review changed or was already saved. Reload it before creating a revision.');}
            $stmt=$pdo->prepare('INSERT INTO evaluations (application_id,role_id,rubric_version,reviewer_id,reviewer_name,revision,supersedes_id,scores_json,weighted_score,recommendation,private_feedback) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$app['id'],$app['role_id'],$version,$user['id'],$user['username'],(int)($previous['revision']??0)+1,$previous['id']??null,json_encode($score['scores'],JSON_THROW_ON_ERROR),$score['average'],$recommendation,r_text(field('private_feedback'),4000,false)]);
            audit($pdo,(int)$user['id'],'evaluation.saved',(int)$app['id'],'Private reviewer recommendation; no recruitment decision changed.');$message='Private evaluation saved. Recommendations do not change public status, final selection or actor reveals.';
        }else{http_response_code(403);throw new DomainException('Unknown evaluation action.');}
        $pdo->commit();return [$message];
    }catch(Throwable $error){if($pdo->inTransaction()){$pdo->rollBack();}throw $error;}
}
