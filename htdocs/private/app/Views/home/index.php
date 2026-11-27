<?php
use BBA\Core\Auth;

$account = $account ?? Auth::user();
?>
<section class="hero">
    <div>
        <img class="brand-mark" src="<?= e(asset('/assets/img/icon.svg')) ?>" alt="bbaccounts logo" width="72" height="72">
        <h1>bbaccounts</h1>
        <p class="hero-lede">
            <strong><?= e(t('One account to sign in to connected projects.')) ?></strong>
            <?= e(t('bbaccounts is an identity provider and sign-in service. You can create a bbaccounts account and use it to sign in to connected applications with secure OAuth 2.0 authorization, without sharing your password with those apps.')) ?>
        </p>
        <div class="hero-actions">
            <?php if ($account === null): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/register')) ?>"><?= e(t('Create account')) ?><?= icon('arrow-right') ?></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('/login')) ?>"><?= icon('user') ?><span><?= e(t('Sign in')) ?></span></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('/docs')) ?>"><?= icon('book') ?><span><?= e(t('Read the docs')) ?></span></a>
            <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('/account')) ?>"><?= e(t('Open your account')) ?><?= icon('arrow-right') ?></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('/developer')) ?>"><?= icon('code') ?><span><?= e(t('Developer projects')) ?></span></a>
            <?php endif; ?>
        </div>
        <div class="hero-meta">
            <span><?= icon('shield') ?>OAuth 2.0 + PKCE</span>
            <span><?= icon('lock') ?><?= e(t('scoped access')) ?></span>
            <span><?= icon('key') ?><?= e(t('you control connected apps')) ?></span>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-body prose">
        <h2><?= e(t('What is bbaccounts?')) ?></h2>
        <p><?= e(t('bbaccounts is a service for creating and managing a user account that can be used to sign in to connected projects and applications. When an app asks you to sign in with bbaccounts, you review the requested permissions (such as your profile or email address), approve or deny access, and can revoke that access later from your account settings.')) ?></p>
        <p><?= e(t('The purpose of bbaccounts is account management and secure sign-in: registering an account, signing in, managing your profile, connecting sign-in providers, and controlling which applications are authorized to access your information.')) ?></p>
        <p><a href="<?= e(url('/privacy')) ?>"><?= e(t('Read the privacy policy')) ?></a> · <a href="<?= e(url('/terms')) ?>"><?= e(t('Read the terms of service')) ?></a></p>
    </div>
</section>

<div class="section-head-standalone">
    <p class="mono-label"><?= e(t('How it works')) ?></p>
</div>

<div class="steps">
    <div class="step">
        <img src="<?= e(url('/assets/img/tutorial/step-1.png')) ?>" alt="" loading="lazy" style="display:block;width:100%;border-radius:12px;margin:0 0 12px" onerror="this.remove()">
        <span class="step-num">01 / account</span>
        <h3><?= e(t('Create your account')) ?></h3>
        <p><?= e(t('Register once on bbaccounts to use a single account across connected projects.')) ?></p>
    </div>
    <div class="step">
        <img src="<?= e(url('/assets/img/tutorial/step-2.png')) ?>" alt="" loading="lazy" style="display:block;width:100%;border-radius:12px;margin:0 0 12px" onerror="this.remove()">
        <span class="step-num">02 / consent</span>
        <h3><?= e(t('Approve access')) ?></h3>
        <p><?= e(t('When a connected app requests sign-in, bbaccounts shows what data is requested and you choose whether to allow it.')) ?></p>
    </div>
    <div class="step">
        <img src="<?= e(url('/assets/img/tutorial/step-3.png')) ?>" alt="" loading="lazy" style="display:block;width:100%;border-radius:12px;margin:0 0 12px" onerror="this.remove()">
        <span class="step-num">03 / manage</span>
        <h3><?= e(t('Stay in control')) ?></h3>
        <p><?= e(t('You can review and revoke authorized apps, manage your profile, and protect your account from your settings.')) ?></p>
    </div>
</div>

<?php $forumNews = $forumNews ?? []; $forumNewsError = $forumNewsError ?? null; $isAdminViewer = is_array($account ?? null) && (int) ($account['is_admin'] ?? 0) === 1; ?>
<?php if ($forumNews !== [] || $forumNewsError !== null): ?>
<section class="card" style="margin-top: 24px">
    <div class="card-head">
        <div>
            <h2>News from bbforum</h2>
            <p>Latest posts from the bbaccounts blog tag.</p>
        </div>
        <a class="text-link" href="<?= e(rtrim((string) \BBA\Core\Config::get('forum.base_url', 'https://bbforum.unaux.com/public'), '/') . '/t/' . rawurlencode((string) \BBA\Core\Config::get('forum.news_tag', 'bbaccounts'))) ?>" target="_blank" rel="noopener noreferrer">Open in forum</a>
    </div>
    <?php if ($forumNews !== []): ?>
    <div class="list">
        <?php foreach ($forumNews as $item): ?>
        <a class="list-row" href="<?= e((string) ($item['url'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">
            <span class="list-icon"><?= icon('book') ?></span>
            <div class="list-main">
                <span class="list-title"><?= e((string) ($item['title'] ?? '')) ?></span>
                <span class="list-sub"><?= e(time_ago($item['created_at'] ?? null)) ?><?php if ((int) ($item['comments'] ?? 0) > 0): ?> · <?= (int) $item['comments'] ?> <?= (int) $item['comments'] === 1 ? 'reply' : 'replies' ?><?php endif; ?></span>
            </div>
            <?= icon('arrow-right') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card-body">
        <p class="page-sub" style="margin:0">Could not load news.</p>
        <?php if ($isAdminViewer && $forumNewsError !== null): ?>
        <details style="margin-top:8px">
            <summary class="text-link" style="cursor:pointer">See why (only for admins)</summary>
            <p class="field-hint" style="margin-top:8px"><?= e((string) $forumNewsError) ?></p>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
