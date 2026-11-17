<?php
function p_csrf():void{echo '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">';}
function p_hidden(string $name,string|int $value):void{echo '<input type="hidden" name="'.e($name).'" value="'.e((string)$value).'">';}
function p_form_date(?string $utc,string $zone):string{return $utc?(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($zone))->format('Y-m-d\TH:i'):'';}
?>
<nav class="pager" aria-label="Production tools"><a class="text-link" href="<?= e(admin_url($kind)) ?>">Recruitment review →</a><?php if($kind==='casting'):?><a class="text-link" href="<?= e(p_url('casting','auditions')) ?>">Auditions →</a><?php endif;?><a class="text-link" href="<?= e(p_url($kind,'evaluations')) ?>">Evaluations →</a></nav>
<?php if($section==='auditions'):require __DIR__.'/production-auditions.php';else:require __DIR__.'/production-evaluations.php';endif;?>
