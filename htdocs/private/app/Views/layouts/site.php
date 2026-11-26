<?php
use BBA\Core\Auth;
use BBA\Core\Request;

$account = $account ?? Auth::user();
$title = $title ?? 'bbaccounts';
$isHome = Request::path() === '/';
$nextHere = safe_next_path($_SERVER['REQUEST_URI'] ?? '/');
$switchable = $account !== null
    ? array_values(array_filter(Auth::switchAccounts(), static fn (array $row): bool => (int) $row['id'] !== (int) $account['id']))
    : [];
$homeBg = '';
if ($isHome && public_file('/assets/img/backgroundMain.png')) {
    $homeBg = '/assets/img/backgroundMain.png';
} elseif ($isHome && public_file('/assets/img/backgroundMain.gif')) {
    $homeBg = '/assets/img/backgroundMain.gif';
}
$profileHeaderStyle = '';
$profileHeaderAttrs = '';
$profileBodyBg = '';
$viewedProfile = $profile ?? null;
if (is_array($viewedProfile) && preg_match('#^/u(/|$)#', (string) \BBA\Core\Request::path())) {
    $pOpacity = max(20, min(100, (int) ($viewedProfile['profile_card_opacity'] ?? 100)));
    $pBlur = (int) ($viewedProfile['profile_card_blur'] ?? 1) === 1;
    $profileHeaderAttrs = ' data-card-opacity="' . $pOpacity . '" data-card-blur="' . ($pBlur ? '1' : '0') . '"';
    $profileHeaderStyle = ' style="--card-alpha:' . $pOpacity . '%"';
    $pBg = trim((string) ($viewedProfile['profile_background'] ?? ''));
    if ($pBg !== '') {
        $profileBodyBg = '/uploads/banners/' . rawurlencode($pBg);
    }
}
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
    <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('/assets/img/icon.svg')) ?>" type="image/svg+xml">
    <link rel="shortcut icon" href="<?= e(asset('/assets/img/icon.png')) ?>" type="image/png">
    <link rel="apple-touch-icon" href="<?= e(asset('/assets/img/icon.png')) ?>">
</head>
<?php $bodyBg = $homeBg !== '' ? $homeBg : $profileBodyBg; ?>
<body class="site-body<?= $bodyBg !== '' ? ' home-bg' : '' ?>" data-leave-warning="<?= e(url('/leave')) ?>" data-trusted-sites="<?= e((string) json_encode(trusted_sites())) ?>" data-app-version="<?= e(\BBA\Core\Config::get('version', '')) ?>"<?= $bodyBg !== '' ? ' style="--home-image: url(' . e(url($bodyBg)) . ')"' : '' ?>>
<?php include BBA_APP . '/Views/layouts/banners.php'; ?>
<header class="site-header"<?= $profileHeaderAttrs . $profileHeaderStyle ?>>
    <a class="brand" href="<?= e(url(preg_match('#^/(account|admin|moderator|developer)(/|$)#', (string) \BBA\Core\Request::path()) ? '/account' : '/')) ?>">
        <img class="brand-mark" src="<?= e(asset('/assets/img/icon.svg')) ?>" alt="" width="32" height="32">
        <span class="brand-name">bbaccounts</span>
    </a>
    <nav class="site-nav" aria-label="Site">
        <a href="<?= e(docs_url('/docs')) ?>"><?= e(t('Docs')) ?></a>
        <a href="<?= e(url('/p')) ?>"><?= e(t('Projects')) ?></a>
        <a href="<?= e(url('/u')) ?>"><?= e(t('Users')) ?></a>
        <a href="<?= e(url('/rules')) ?>"><?= e(t('Rules')) ?></a>
        <a href="<?= e(url('/privacy')) ?>"><?= e(t('Privacy')) ?></a>
        <a href="<?= e(url('/terms')) ?>"><?= e(t('Terms')) ?></a>
        <?php if ($account !== null): ?>
        <a href="<?= e(url(((int) $account['is_developer'] === 1) ? '/developer' : '/account')) ?>">
            <?= e((int) $account['is_developer'] === 1 ? t('Developer') : t('Account')) ?>
        </a>
        <details class="account-menu">
            <summary class="btn btn-sm btn-primary"><?= e(t('Open account')) ?></summary>
            <div class="account-menu-panel">
                <a class="account-menu-row" href="<?= e(url('/account')) ?>">
                    <img class="avatar avatar-sm" src="<?= e(avatar_url($account)) ?>" alt="" width="30" height="30"<?= avatar_style($account) ?>>
                    <span class="account-menu-text">
                        <strong><?= e($account['display_name']) ?></strong>
                        <span><?= trim((string) ($account['username'] ?? '')) !== '' ? '@' . e($account['username']) . ' · ' : '' ?><?= e($account['email']) ?></span>
                    </span>
                </a>
                <?php foreach ($switchable as $row): ?>
                <form method="post" action="<?= e(url('/account/switch')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="account_id" value="<?= (int) $row['id'] ?>">
                    <input type="hidden" name="next" value="<?= e($nextHere) ?>">
                    <button class="account-menu-row" type="submit">
                        <img class="avatar avatar-sm" src="<?= e(avatar_url($row)) ?>" alt="" width="30" height="30"<?= avatar_style($row) ?>>
                        <span class="account-menu-text">
                            <strong><?= e($row['display_name']) ?></strong>
                            <span><?= trim((string) ($row['username'] ?? '')) !== '' ? '@' . e($row['username']) . ' · ' : '' ?><?= e($row['email']) ?></span>
                        </span>
                        <span class="badge"><?= e(t('Switch account')) ?></span>
                    </button>
                </form>
                <?php endforeach; ?>
                <a class="account-menu-foot" href="<?= e(url('/account/switch?next=' . rawurlencode($nextHere))) ?>"><?= e(t('Manage accounts')) ?></a>
            </div>
        </details>
        <?php else: ?>
        <a href="<?= e(url('/login')) ?>"><?= e(t('Sign in')) ?></a>
        <a class="btn btn-sm btn-primary" href="<?= e(url('/register')) ?>"><?= e(t('Create account')) ?></a>
        <?php endif; ?>
        <?php if (\BBA\Core\I18n::pathAllowsLanguage()): ?>
        <?php $lang = \BBA\Core\I18n::language(); ?>
        <label class="lang-select-wrap" title="Language">
            <span class="sr-only">Language</span>
            <select class="lang-select" onchange="if(this.value)location.href=this.value">
                <option value="<?= e(url('/lang/en?next=' . rawurlencode($nextHere))) ?>" <?= $lang === 'en' ? 'selected' : '' ?>>EN</option>
                <option value="<?= e(url('/lang/ru?next=' . rawurlencode($nextHere))) ?>" <?= $lang === 'ru' ? 'selected' : '' ?>>RU · Beta</option>
            </select>
        </label>
        <?php endif; ?>
        <?php if (\BBA\Core\Config::get('allow_white_mode_beta', true)): ?>
        <button class="icon-btn" type="button" data-theme-toggle aria-label="<?= e(t('Change theme')) ?>" title="<?= e(t('Change theme')) ?>">
            <?= icon('sun', 'icon-sun') ?><?= icon('moon', 'icon-moon') ?>
        </button>
        <?php endif; ?>
    </nav>
</header>

<?php if ($isHome): ?>
<?php foreach (['left', 'right'] as $slot): ?>
<?php $ad = home_ad($slot); ?>
<?php if ($ad === null) { continue; } ?>
<?php if ($ad['url'] !== ''): ?>
<a class="home-ad-link" href="<?= e($ad['url']) ?>" rel="noopener noreferrer nofollow sponsored">
<?php endif; ?>
<img class="home-ad home-ad-<?= e($slot) ?>" src="<?= e($ad['src']) ?>" alt="Promoted project" loading="lazy">
<?php if ($ad['url'] !== ''): ?>
</a>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>

<main class="site-main">
    <?php include BBA_APP . '/Views/layouts/flashes.php'; ?>
    <?= $content ?>
</main>

<footer class="site-footer">
    <p><strong>bbaccounts</strong> — <?= e(t('an identity and sign-in service for connected projects.')) ?></p>
    <nav class="site-footer-nav" aria-label="Legal">
        <a href="<?= e(url('/privacy')) ?>"><?= e(t('Privacy policy')) ?></a>
        <a href="<?= e(url('/terms')) ?>"><?= e(t('Terms of service')) ?></a>
    </nav>
</footer>

<?php include BBA_APP . '/Views/layouts/mobile-gate.php'; ?>
<script src="<?= e(asset('/assets/js/app.js')) ?>"></script>
</body>
</html>
