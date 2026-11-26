<?php
use BBA\Core\Auth;

$brand = $brand ?? null;
$next = $next ?? '';
$title = $title ?? 'Sign in';
$account = Auth::user();
$accent = is_array($brand) ? (string) $brand['accent'] : '#2f5cff';
?>
<!doctype html>
<html lang="<?= e(\BBA\Core\I18n::language()) ?>" data-theme="<?= \BBA\Core\Config::get('allow_white_mode_beta', true) ? 'auto' : 'dark' ?>"<?= \BBA\Core\Config::get('allow_white_mode_beta', true) ? '' : ' data-theme-locked="dark"' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#0a0c10">
    <title><?= e($title) ?> · bbaccounts</title>
    <script src="<?= e(asset('/assets/js/theme.js')) ?>"></script>
    <?= turnstile_script() ?>
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('/assets/img/icon.svg')) ?>" type="image/svg+xml">
    <link rel="shortcut icon" href="<?= e(asset('/assets/img/icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset('/assets/img/icon.png')) ?>">
</head>
<body class="auth-body" style="<?= e(accent_style($accent)) ?>" data-leave-warning="<?= e(url('/leave')) ?>" data-trusted-sites="<?= e((string) json_encode(trusted_sites())) ?>" data-app-version="<?= e(\BBA\Core\Config::get('version', '')) ?>">
<div class="auth-shell auth-shell-single">
    <main class="auth-main">
        <div class="auth-main-top">
            <a class="auth-back" href="<?= e(url('/')) ?>">
                <img class="brand-mark" src="<?= e(asset('/assets/img/icon.svg')) ?>" alt="" width="24" height="24">
                <span>bbaccounts</span>
            </a>
            <?php if (\BBA\Core\Config::get('allow_white_mode_beta', true)): ?>
            <button class="icon-btn" type="button" data-theme-toggle aria-label="Change theme" title="Change theme">
                <?= icon('sun', 'icon-sun') ?><?= icon('moon', 'icon-moon') ?>
            </button>
            <?php endif; ?>
        </div>

        <div class="auth-card">
            <?php include BBA_APP . '/Views/layouts/flashes.php'; ?>
            <?= $content ?>
        </div>

        <p class="auth-legal">By continuing you agree to the <a href="<?= e(url('/rules')) ?>">rules</a> and acceptable use of this service.</p>
    </main>
</div>
<?php include BBA_APP . '/Views/layouts/mobile-gate.php'; ?>
<script src="<?= e(asset('/assets/js/app.js')) ?>"></script>
</body>
</html>
