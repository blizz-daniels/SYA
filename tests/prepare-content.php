<?php
declare(strict_types=1);
require __DIR__.'/prepare-production.php';
$pdo->exec("INSERT INTO cms_items(kind,slug) VALUES ('character','isioma'),('character','ayo')");
$pdo->exec("INSERT INTO cms_categories(name,slug) VALUES ('Announcements','announcements'),('Production diaries','production-diaries')");
$pdo->exec("INSERT INTO media_collections(name) VALUES ('Official posters'),('Behind the scenes')");
$im=imagecreatetruecolor(1200,800);$bg=imagecolorallocate($im,64,48,79);imagefill($im,0,0,$bg);$stone=imagecolorallocate($im,213,197,183);imagefilledrectangle($im,400,100,700,700,$stone);imagepng($im,__DIR__.'/artifacts/cms-test.png');imagedestroy($im);file_put_contents(__DIR__.'/artifacts/cms-test.png','<?php echo \"TEST_STRIPPED_PAYLOAD\"; ?>',FILE_APPEND);
$im=imagecreatetruecolor(300,300);imagepng($im,__DIR__.'/artifacts/cms-second.png');imagedestroy($im);
echo "Milestone 5 synthetic content fixture prepared. No real actor information used.\n";
