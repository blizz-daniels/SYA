<?php if(empty($homeSettings['enabled']['hero'])):?><h1 class="sr-only">See You Around</h1><?php endif;foreach($homeSettings['order'] as $sectionKey):if(empty($homeSettings['enabled'][$sectionKey])){continue;}switch($sectionKey): ?>
<?php case 'hero': ?>
<section class="hero"><div class="hero-art" aria-hidden="true"><div class="window-light"></div><div class="figure figure-one"></div><div class="figure figure-two"></div></div><div class="hero-content"><p class="eyebrow">AN INDEPENDENT NIGERIAN FILM</p><h1>SEE YOU<br><em>AROUND</em><span class="title-dot">.</span></h1><p class="hero-line">Some things are hard to say.<br>Some connections stay with us.</p><div class="actions"><a class="button" href="<?= e(url('about')) ?>">Discover the film <span aria-hidden="true">→</span></a><a class="text-link" href="<?= e(url('casting')) ?>">Be part of the story <span aria-hidden="true">→</span></a></div></div><div class="hero-bottom"><span>FRIENDSHIP. LOSS. THE WAY FORWARD.</span><a href="<?= !empty($homeSettings['enabled']['story'])?'#story':e(url('about')) ?>">Explore <span aria-hidden="true">↓</span></a></div></section>

<?php break; ?>
<?php case 'story': ?>
<section id="story" class="section story"><p class="eyebrow">01 / THE STORY</p><div><h2>Finding a way back.<br><em>One connection at a time.</em></h2><p>See You Around explores friendship, grief, guilt and emotional isolation — and the difficult process of recovery. At its heart are Isioma and Ayo, two men with different experiences of loss and personal struggle.</p><p class="muted">The official synopsis will be shared when approved.</p><a class="text-link" href="<?= e(url('about')) ?>">About the film <span aria-hidden="true">→</span></a></div></section>

<?php break; ?>
<?php case 'characters': ?>
<section class="section"><div class="section-heading"><div><p class="eyebrow">02 / THE PEOPLE IN THE STORY</p><h2>Not every face<br><em>has been revealed.</em></h2></div><a class="text-link" href="<?= e(url('characters')) ?>">Meet the characters →</a></div><?php require __DIR__.'/characters.php'; ?></section>

<?php break; ?>
<?php case 'join': ?>
<section class="join section"><p class="eyebrow">03 / MAKE SOMETHING MEANINGFUL</p><h2>A story takes people.<br><em>Perhaps it will take you.</em></h2><p><?php if(!empty($config['features']['recruitment'])):?>Explore casting and crew opportunities below.<br>Only published roles accept applications before their deadlines.<?php else:?>Future acting and crew opportunities will be published here.<br>Recruitment is not open. Applications are not being accepted.<?php endif;?></p><div class="opportunity-grid"><a href="<?= e(url('casting')) ?>"><span>IN FRONT OF THE CAMERA</span><h3>Casting calls <span aria-hidden="true">→</span></h3><p>Explore the future casting space.</p></a><a href="<?= e(url('crew')) ?>"><span>BEHIND THE CAMERA</span><h3>Join the crew <span aria-hidden="true">→</span></h3><p>A place for creative and technical collaborators.</p></a></div></section>

<?php break; ?>
<?php case 'updates': ?>
<section class="section"><div class="section-heading"><div><p class="eyebrow">04 / FROM THE PRODUCTION</p><h2>The story <em>as it unfolds.</em></h2></div><a class="text-link" href="<?= e(url('updates')) ?>">All updates →</a></div><?php if($publicUpdates):?><div class="content-grid"><?php foreach(array_slice($publicUpdates,0,3) as $entry):?><article class="content-card"><p class="eyebrow"><?= e($entry['category']??C_KINDS[$entry['kind']]) ?></p><h3><a href="<?= e(c_public_url($entry)) ?>"><?= e($entry['title']) ?></a></h3><p><?= e($entry['excerpt']) ?></p></article><?php endforeach;?></div><?php elseif($cmsUnavailable):?><p class="notice">Published updates are temporarily unavailable.</p><?php else:?><div class="empty-update"><span class="outline-number" aria-hidden="true">SYA</span><div><p class="eyebrow">AWAITING OFFICIAL ANNOUNCEMENTS</p><h3>A space for what comes next.</h3><p>Approved production news, diaries and announcements will appear here. No updates have been published.</p></div></div><?php endif;?></section>

<?php break; ?>
<?php case 'connected': ?>
<section class="connected section"><p class="eyebrow">05 / STAY CLOSE</p><h2>There’s more <em>ahead.</em></h2><p>Return here for official updates. Newsletter subscriptions and official social links are not available yet.</p><a class="text-link" href="<?= e(url('contact')) ?>">Stay connected →</a></section>

<?php break; ?>
<?php endswitch;endforeach;?>
