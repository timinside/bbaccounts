<?php
$account = $account ?? [];
$tab = $tab ?? 'password';
$providers = $providers ?? [];
$identityCount = $identityCount ?? 0;
$hasPassword = (int) ($account['password_set'] ?? 1) === 1;
$socialError = form_error('social');
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Connections</h1>
        <p class="page-sub">Ways you can sign in to bbaccounts. Keep at least one working method at all times.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<div class="tabs" role="tablist" aria-label="Connection types">
    <a class="tab <?= $tab === 'password' ? 'is-active' : '' ?>" href="<?= e(url('/account/connections')) ?>" role="tab" aria-selected="<?= $tab === 'password' ? 'true' : 'false' ?>">Password</a>
    <a class="tab <?= $tab === 'social' ? 'is-active' : '' ?>" href="<?= e(url('/account/connections?tab=social')) ?>" role="tab" aria-selected="<?= $tab === 'social' ? 'true' : 'false' ?>">Social Providers</a>
</div>

<?php if ($tab === 'password'): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Email and password</h2>
            <p><?= $hasPassword ? 'Sign in with the email address on your account.' : 'No password is set on this account yet.' ?></p>
        </div>
        <span class="badge <?= $hasPassword ? 'badge-ok' : 'badge-warn' ?>"><?= $hasPassword ? 'connected' : 'password not set' ?></span>
    </div>
    <div class="list">
        <div class="list-row">
            <span class="list-icon"><?= icon('mail') ?></span>
            <div class="list-main">
                <span class="list-title"><?= e($account['email']) ?></span>
                <span class="list-sub"><?= $hasPassword ? 'Password sign-in is active.' : 'Set a password to sign in without a social provider.' ?></span>
            </div>
            <div class="list-actions">
                <?php if ($hasPassword): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/account/security')) ?>"><?= icon('settings') ?><span>Manage</span></a>
                <?php else: ?>
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/account/security')) ?>"><?= icon('lock') ?><span>Set password</span></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="list-row">
            <span class="list-icon"><?= icon('shield') ?></span>
            <div class="list-main">
                <span class="list-title">Recovery codes</span>
                <span class="list-sub">Single-use codes replace email password resets.</span>
            </div>
            <div class="list-actions">
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/account/security')) ?>"><?= icon('key') ?><span>View</span></a>
            </div>
        </div>
    </div>
</section>


<?php else: ?>
<?php if ($socialError !== null): ?>
<div class="form-alert" role="alert"><?= icon('alert') ?><span><?= e($socialError) ?></span></div>
<?php endif; ?>

<?php if (!$hasPassword): ?>
<div class="note note-warn">
    <?= icon('alert') ?>
    <span>This account has no password. Set one in <a href="<?= e(url('/account/security')) ?>">Security</a> before you disconnect your last social provider.</span>
</div>
<?php endif; ?>

<div class="provider-grid">
    <?php foreach ($providers as $provider => $providerData): ?>
    <?php $identity = $providerData['identity'] ?? null; ?>
    <section class="card provider-card">
        <div class="provider-head">
            <span class="provider-logo"><?= provider_mark($provider) ?></span>
            <div class="provider-name">
                <h2><?= e($providerData['name']) ?></h2>
                <?php if ($identity !== null): ?>
                <span class="badge badge-ok">connected</span>
                <?php elseif (!empty($providerData['placeholder'])): ?>
                <span class="badge badge-warn">coming soon</span>
                <?php elseif ($providerData['enabled']): ?>
                <span class="badge">not connected</span>
                <?php else: ?>
                <span class="badge badge-warn">not configured</span>
                <?php endif; ?>
                <?php if (!empty($providerData['enabled']) && in_array($provider, ['discord', 'google'], true)): ?><span class="badge badge-tester">Beta</span><?php endif; ?>
            </div>
        </div>
        <div class="provider-body">
            <?php if ($identity !== null): ?>
            <p class="list-sub">Linked <?= e(format_date($identity['created_at'])) ?><?php if (!empty($identity['email'])): ?> · <?= e($identity['email']) ?><?php endif; ?></p>
            <label class="check-row">
                <input type="checkbox" form="provider-visibility" name="visible[]" value="<?= e($provider) ?>" <?= (int) ($identity['is_visible'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Is visible to other projects</span>
            </label>
            <p class="field-hint">Adds your <?= e($providerData['name']) ?> username to the <code>social</code> object in <code>/oauth/userinfo</code> when you approve a project.</p>
            <form method="post" action="<?= e(url('/account/connections/social/' . $provider . '/unlink')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-danger" type="submit" data-confirm="Disconnect <?= e($providerData['name']) ?> from this account?"><?= icon('x') ?><span>Disconnect</span></button>
            </form>
            <?php elseif (!empty($providerData['placeholder'])): ?>
            <p class="list-sub"><?= e($providerData['name']) ?> sign-in is planned. The card is here so the provider list is ready before the OAuth client exists.</p>
            <button class="btn btn-sm btn-ghost" type="button" disabled><?= icon('lock') ?><span>Coming soon</span></button>
            <?php elseif ($providerData['enabled']): ?>
            <p class="list-sub">Use your <?= e($providerData['name']) ?> account to sign in to bbaccounts.</p>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('/oauth/social/' . $provider . '?link=1')) ?>"><?= icon('link') ?><span>Connect</span></a>
            <?php else: ?>
            <p class="list-sub">Add a <?= e($providerData['name']) ?> OAuth client in <span class="mono">config.php</span> to enable this provider.</p>
            <button class="btn btn-sm btn-ghost" type="button" disabled><?= icon('lock') ?><span>Unavailable</span></button>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>

<?php if ($identityCount > 0): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Provider visibility</h2>
            <p>Choose which linked usernames projects may receive.</p>
        </div>
    </div>
    <form id="provider-visibility" method="post" action="<?= e(url('/account/connections/visibility')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <p class="field-hint" style="margin-top: 0">
            Each checkbox above controls one provider. The master switch in
            <a href="<?= e(url('/account/profile')) ?>">Profile → Privacy</a> must also be on,
            otherwise no provider usernames are shared.
            <?php if ((int) ($account['share_social'] ?? 1) !== 1): ?>
            <strong>Sharing is currently turned off globally.</strong>
            <?php endif; ?>
        </p>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Save visibility</span></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2>How linking works</h2>
            <p>One account, several ways in.</p>
        </div>
        <span class="badge"><?= (int) $identityCount ?> linked</span>
    </div>
    <div class="card-body">
        <p class="page-sub" style="margin-top: 0">
            A provider links automatically when its verified email matches your bbaccounts email.
            Social-only accounts can set a password at any time without losing linked providers.
        </p>
    </div>
</section>
<?php endif; ?>
