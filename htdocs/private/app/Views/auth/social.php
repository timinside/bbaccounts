<?php
$next = $next ?? '';
$add = $add ?? false;
$all = social_providers_all();
$primary = ['google', 'github'];
$params = [];
if ($next !== '') {
    $params['next'] = $next;
}
if ($add) {
    $params['add'] = '1';
}
$query = $params !== [] ? '?' . http_build_query($params) : '';
?>
<div class="auth-divider"><span><?= e(t('or')) ?></span></div>
<div class="social-login">
    <?php foreach ($primary as $provider): ?>
    <?php if (isset($all[$provider])): ?>
    <a class="btn btn-secondary btn-block social-btn" href="<?= e(url('/oauth/social/' . $provider . $query)) ?>">
        <?= provider_mark($provider) ?><span>Continue with <?= e($all[$provider]['name']) ?></span>
        <?php if (in_array($provider, ['discord', 'google'], true)): ?><span class="badge badge-tester"><?= e(t('Beta')) ?></span><?php endif; ?>
    </a>
    <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn btn-ghost btn-block" type="button" data-dialog-open="social-more"><?= e(t('Still need more ways to log in?')) ?></button>
</div>

<dialog class="dialog" id="social-more" aria-label="<?= e(t('More ways to log in')) ?>">
    <div class="dialog-head">
        <div>
            <h2><?= e(t('More ways to log in')) ?></h2>
            <p><?= e(t('Everything planned for bbaccounts, including providers that still need setup.')) ?></p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="list">
        <?php foreach ($all as $provider => $data): ?>
        <?php if (in_array($provider, $primary, true)) { continue; } ?>
        <div class="list-row">
            <span class="provider-mark-wrap"><?= provider_mark((string) $provider) ?></span>
            <div class="list-main">
                <span class="list-title"><?= e($data['name']) ?></span>
                <span class="list-sub">
                    <?php if ($data['ready']): ?><?= e(t('Ready to use.')) ?>
                    <?php elseif ($data['planned']): ?><?= e(t('Planned. No code yet.')) ?>
                    <?php else: ?><?= e(t('Code is ready, credentials are not set up yet.')) ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($data['ready']): ?>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('/oauth/social/' . $provider . $query)) ?>"><?= icon('external') ?><span><?= e(t('Continue')) ?></span><?php if (in_array($provider, ['discord', 'google'], true)): ?><span class="badge badge-tester"><?= e(t('Beta')) ?></span><?php endif; ?></a>
            <?php else: ?>
            <span class="badge <?= $data['planned'] ? 'badge-warn' : 'badge-muted' ?>"><?= e($data['planned'] ? t('Planned') : t('Not configured')) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</dialog>
