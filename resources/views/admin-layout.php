<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> — See You Around</title><link rel="icon" href="<?= e(asset('images/icon.svg')) ?>" type="image/svg+xml"><link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>"><link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>"></head>
<body class="admin-body"><a class="skip" href="#main">Skip to content</a>
<header class="admin-header"><a class="wordmark" href="<?= e(url()) ?>">SYA<span>PRODUCTION OFFICE</span></a><a class="text-link" href="<?= e(url()) ?>">Public website →</a></header>
<?php if (!$user): ?>
<main id="main" class="auth-panel"><p class="eyebrow">SEE YOU AROUND / PRIVATE ACCESS</p><h1>Welcome back.</h1><p class="muted">Sign in to your production office. Administrator access only.</p>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" action="<?= e(admin_url('login')) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login">
<label for="username">Username</label><input id="username" name="username" autocapitalize="none" autocorrect="off" spellcheck="false" autocomplete="username" maxlength="48" required autofocus>
<label for="private_code">Private code</label><input type="password" id="private_code" name="private_code" autocomplete="current-password" maxlength="72" required>
<button class="button" type="submit">Sign in <span aria-hidden="true">→</span></button></form><p class="muted">Keep your private code in a password manager. Ask your Super Admin for a reset if you lose it. No email address or 2FA is required.</p></main>
<?php else: ?>
<div class="office"><aside class="admin-sidebar"><p class="eyebrow">PRODUCTION OFFICE</p><p class="signed-in"><?= e($user['username']) ?><span><?= e(ADMIN_ROLES[$user['role']]) ?></span></p><nav aria-label="Administrator navigation"><?php foreach (admin_modules($user['role']) as $key): ?><a href="<?= e(admin_url($key)) ?>"<?= $module === $key ? ' aria-current="page"' : '' ?>><?= e(ADMIN_MODULES[$key]) ?></a><?php endforeach; ?></nav><form method="post" action="<?= e(admin_url($module)) ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="sign-out" type="submit">Sign out →</button></form></aside>
<main id="main" class="admin-main"><p class="eyebrow">SEE YOU AROUND / <?= e(strtoupper(ADMIN_ROLES[$user['role']])) ?></p><h1><?= e($title) ?><span class="title-dot">.</span></h1>
<?php if ($error): ?><p class="alert" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($flash): ?><div class="success" role="status"><ul><?php foreach ($flash as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__ . '/admin-pages.php'; ?></main></div>
<?php endif; ?><footer class="admin-footer">See You Around · Private production office · Recruitment is not open.</footer></body></html>
