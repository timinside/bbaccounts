<?php
use BBA\Core\Auth;
use BBA\Core\Db;
use BBA\Core\Request;

$account = Auth::user();
$title = $title ?? 'Overview';
$path = Request::path();
$unread = (int) Db::value(
    'SELECT COUNT(*) FROM notifications WHERE account_id = ? AND read_at IS NULL',
    [(int) $account['id']]
);
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
<body class="panel-body" data-leave-warning="<?= e(url('/leave')) ?>" data-trusted-sites="<?= e((string) json_encode(trusted_sites())) ?>" data-tutorial="<?= \BBA\Core\Config::get('allow_tutorial', true) ? '1' : '0' ?>" data-app-version="<?= e(\BBA\Core\Config::get('version', '')) ?>">
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <a class="brand" href="<?= e(url(preg_match('#^/(account|admin|moderator|developer)(/|$)#', (string) \BBA\Core\Request::path()) ? '/account' : '/')) ?>">
            <img class="brand-mark" src="<?= e(asset('/assets/img/icon.svg')) ?>" alt="" width="32" height="32">
            <span class="brand-name">bbaccounts</span>
        </a>

        <nav class="sidebar-nav">
            <p class="nav-label">Account</p>
            <a class="nav-link <?= nav_class('/account', true) ?>" href="<?= e(url('/account')) ?>">
                <?= icon('home') ?><span>Overview</span>
            </a>
            <a class="nav-link" href="<?= e(account_url($account)) ?>">
                <?= icon('user') ?><span>My profile</span>
            </a>
            <a class="nav-link <?= nav_class('/account/friends') ?>" href="<?= e(url('/account/friends')) ?>">
                <?= icon('users') ?><span>Friends</span>
            </a>
            <a class="nav-link <?= nav_class('/account/teams') ?>" href="<?= e(url('/account/teams')) ?>">
                <?= icon('users') ?><span>Teams</span>
            </a>
            <a class="nav-link <?= nav_class('/account/referrals') ?>" href="<?= e(url('/account/referrals')) ?>">
                <?= icon('link') ?><span>Referrals</span>
            </a>
            <a class="nav-link <?= nav_class('/account/security') ?>" href="<?= e(url('/account/security')) ?>">
                <?= icon('shield') ?><span>Security</span>
            </a>
            <a class="nav-link <?= nav_class('/account/apps') ?>" href="<?= e(url('/account/apps')) ?>">
                <?= icon('grid') ?><span>Authorized apps</span>
            </a>
            <a class="nav-link <?= nav_class('/account/connections') ?>" href="<?= e(url('/account/connections')) ?>">
                <?= icon('link') ?><span>Connections</span>
            </a>
            <a class="nav-link <?= nav_class('/account/help') ?>" href="<?= e(url('/account/help')) ?>">
                <?= icon('mail') ?><span>Help</span>
            </a>

            <?php if ((int) $account['is_developer'] === 1): ?>
            <p class="nav-label">Developer</p>
            <a class="nav-link <?= nav_class('/developer') ?>" href="<?= e(url('/developer')) ?>">
                <?= icon('code') ?><span>Projects</span>
            </a>
            <?php endif; ?>

            <?php if ((int) $account['is_admin'] === 1 || (int) ($account['is_moderator'] ?? 0) === 1): ?>
            <p class="nav-label">Administration</p>
            <?php if ((int) $account['is_admin'] === 1): ?>
            <a class="nav-link <?= nav_class('/admin') ?>" href="<?= e(url('/admin')) ?>">
                <?= icon('users') ?><span>Administrator</span>
            </a>
            <?php endif; ?>
            <a class="nav-link <?= nav_class('/moderator') ?>" href="<?= e(url('/moderator')) ?>">
                <?= icon('shield') ?><span>Moderator</span>
                <?php $openReports = \BBA\Models\CommentReport::openCount(); ?>
                <?php if ($openReports > 0): ?><span class="nav-count"><?= $openReports ?></span><?php endif; ?>
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-foot">
            <a class="account-chip" href="<?= e(account_url($account)) ?>" title="How my profile looks">
                <img class="avatar avatar-sm" src="<?= e(avatar_url($account)) ?>" alt="" width="32" height="32"<?= avatar_style($account) ?>>
                <span class="account-chip-text">
                    <strong><?= e($account['display_name']) ?></strong>
                    <span><?= e($account['email']) ?></span>
                </span>
            </a>
            <a class="btn btn-ghost btn-sm btn-block" href="<?= e(url('/account/switch')) ?>"><?= icon('users') ?><span>Switch account</span></a>
            <form method="post" action="<?= e(url('/logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm btn-block" type="submit"><?= icon('logout') ?><span>Sign out</span></button>
            </form>
        </div>
    </aside>

    <div class="sidebar-scrim" data-sidebar-close hidden></div>

    <div class="app-main">
        <?php include BBA_APP . '/Views/layouts/banners.php'; ?>
        <header class="topbar">
            <button class="icon-btn only-mobile" type="button" data-sidebar-toggle aria-label="Open navigation" aria-controls="sidebar">
                <?= icon('menu') ?>
            </button>
            <div class="topbar-title">
                <span class="topbar-kicker"><?= e(str_starts_with($path, '/account') ? 'Account' : (str_starts_with($path, '/admin') ? 'Admin' : (str_starts_with($path, '/developer') ? 'Developer' : 'Workspace'))) ?></span>
                <strong><?= e($title) ?></strong>
            </div>
            <div class="topbar-actions">
                <a class="topbar-link" href="<?= e(docs_url('/docs')) ?>"><?= icon('book') ?><span>Docs</span></a>
                <a class="topbar-link" href="<?= e(url('/rules')) ?>"><?= icon('shield') ?><span>Rules</span></a>
                <a class="icon-btn notification-bell" href="<?= e(url('/account/notifications')) ?>" aria-label="Notifications" title="Notifications">
                    <?= icon('bell') ?>
                    <?php if ($unread > 0): ?><span class="notification-count"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
                </a>
                <?php if (\BBA\Core\Config::get('allow_white_mode_beta', true)): ?>
                <button class="icon-btn" type="button" data-theme-toggle aria-label="Change theme" title="Change theme">
                    <?= icon('sun', 'icon-sun') ?><?= icon('moon', 'icon-moon') ?>
                </button>
                <?php endif; ?>
            </div>
        </header>

        <main class="content" id="main">
            <?php include BBA_APP . '/Views/layouts/flashes.php'; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<?php if (\BBA\Core\Config::get('allow_tutorial', true)): ?>
<dialog class="dialog tutorial-dialog" id="account-tutorial" aria-label="Account tutorial">
    <div class="dialog-head">
        <div>
            <p class="mono-label">Tutorial</p>
            <h2 id="tutorial-title">Welcome to bbaccounts</h2>
            <p id="tutorial-sub">Quick tour of the main tabs. You can skip it now and replay it later in Help.</p>
        </div>
        <button class="icon-btn" type="button" data-tutorial-close aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="tutorial-body" id="tutorial-body"></div>
    <div class="btn-row" style="justify-content:space-between;margin-top:16px">
        <button class="btn btn-ghost" type="button" data-tutorial-skip>Skip</button>
        <div class="btn-row" style="gap:8px">
            <button class="btn btn-secondary" type="button" data-tutorial-prev>Back</button>
            <button class="btn btn-primary" type="button" data-tutorial-next>Next</button>
        </div>
    </div>
</dialog>
<script>
window.BBA_TUTORIAL_STEPS = [
    {title:'Overview', sub:'Account', body:'Your home dashboard: quick status, account summary, and shortcuts to important actions.'},
    {title:'My profile', sub:'Account', body:'Edit your public profile, avatar/banner, showcases, bio, and see how others view your page.'},
    {title:'Friends', sub:'Account', body:'Manage friend requests, your friends list, and friend-related profile features.'},
    {title:'Teams', sub:'Account', body:'View teams you belong to and team roles linked to your account.'},
    {title:'Referrals', sub:'Account', body:'Track referral links and rewards you get for inviting people.'},
    {title:'Security', sub:'Account', body:'Change password, manage sessions/devices, 2FA, recovery codes, and security settings.'},
    {title:'Authorized apps', sub:'Account', body:'See apps you gave OAuth access to, review scopes, and revoke access when needed.'},
    {title:'Connections', sub:'Account', body:'Link social accounts (Google, GitHub, Discord, etc.) for sign-in and profile connections.'},
    {title:'Help', sub:'Account', body:'Contact support/moderators, open tickets, and replay this tutorial any time.'},
    {title:'Projects', sub:'Developer', body:'If you have developer access: create OAuth projects, redirect URIs, client credentials, and manage API keys.'}
];
</script>
<?php endif; ?>
<?php include BBA_APP . '/Views/layouts/mobile-gate.php'; ?>
<script src="<?= e(asset('/assets/js/app.js')) ?>"></script>
</body>
</html>
