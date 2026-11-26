<?php
$title = $title ?? 'bbaccounts';
$error = $error ?? null;
$description = $description ?? null;
?>
<!doctype html>
<html lang="en" data-theme="<?= \BBA\Core\Config::get('allow_white_mode_beta', true) ? 'auto' : 'dark' ?>"<?= \BBA\Core\Config::get('allow_white_mode_beta', true) ? '' : ' data-theme-locked="dark"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title><?= e($title) ?> · bbaccounts</title>
    <script src="<?= e(asset('/assets/js/theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('/assets/img/icon.svg')) ?>" type="image/svg+xml">
    <link rel="shortcut icon" href="<?= e(asset('/assets/img/icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset('/assets/img/icon.png')) ?>">
</head>
<body class="plain-body" data-app-version="<?= e(\BBA\Core\Config::get('version', '')) ?>">
<div class="plain-shell">
    <header class="plain-top">
        <a class="brand" href="<?= e(url(preg_match('#^/(account|admin|moderator|developer)(/|$)#', (string) \BBA\Core\Request::path()) ? '/account' : '/')) ?>">
            <img class="brand-mark" src="<?= e(asset('/assets/img/icon.svg')) ?>" alt="" width="32" height="32">
            <span class="brand-name">bbaccounts</span>
        </a>
        <?php if (\BBA\Core\Config::get('allow_white_mode_beta', true)): ?>
        <button class="icon-btn" type="button" data-theme-toggle aria-label="Change theme" title="Change theme">
            <?= icon('sun', 'icon-sun') ?><?= icon('moon', 'icon-moon') ?>
        </button>
        <?php endif; ?>
    </header>
    <main class="plain-main">
        <?= $content ?>
    </main>
    <footer class="plain-foot">
        <a href="<?= e(url('/')) ?>">Home</a>
        <a href="<?= e(docs_url('/docs')) ?>">Docs</a>
        <a href="<?= e(url('/login')) ?>">Sign in</a>
    </footer>
</div>
<script src="<?= e(asset('/assets/js/app.js')) ?>"></script>
</body>
</html>
