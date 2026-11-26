<?php
$params = $params ?? [];
$fields = $fields ?? [];
$account = $account ?? null;
$project = $params['project'] ?? [];
$scopes = $params['scopes'] ?? [];
$projectName = (string) ($project['name'] ?? 'this project');
$domain = (string) ($project['domain'] ?? '');
$handle = trim((string) ($account['username'] ?? ''));
$switchUrl = $switchUrl ?? '';
$ownerWarning = $ownerWarning ?? '';
$ownerName = $ownerName ?? '';
?> 
<div class="auth-form-head">
    <p class="mono-label">Authorization request</p>
    <h1>Continue to <?= e($projectName) ?>?</h1>
    <p class="auth-sub">You are signed in as <strong><?= e($account['email'] ?? '') ?></strong>. Review what this project receives.</p>
</div>

<?php if ($ownerWarning !== ''): ?>
<div class="form-alert form-alert-warn" role="alert">
    <?= icon('alert') ?>
    <span><strong>Double check.</strong> The owner of <?= e($projectName) ?><?= $ownerName !== '' ? ' (' . e($ownerName) . ')' : '' ?> has a warning on their account: <?= e($ownerWarning) ?>. Are you sure you want to sign in to this project?</span>
</div>
<?php endif; ?>

<div class="consent-account">
    <img class="avatar avatar-md" src="<?= e(avatar_url($account)) ?>" alt="" width="44" height="44"<?= avatar_style($account) ?>>
    <div>
        <strong><?= e($account['display_name'] ?? '') ?></strong>
        <span><?= $handle !== '' ? '@' . e($handle) . ' · ' : '' ?><?= e($account['email'] ?? '') ?></span>
    </div>
    <?php if ($switchUrl !== ''): ?>
    <a class="text-link consent-switch" href="<?= e(url($switchUrl)) ?>">Use a different account</a>
    <?php endif; ?>
</div>

<div class="scope-list">
    <?php foreach ($scopes as $scope): ?>
    <div class="scope-row">
        <span class="scope-icon"><?= icon($scope === 'email' ? 'mail' : 'user') ?></span>
        <div>
            <strong><?= e(scope_label($scope)) ?></strong>
            <span><?= $scope === 'email' ? 'The project can read your email address.' : ($scope === 'guard' ? 'The project can read your Guard secret and current code.' : 'The project can read your display name, username, and avatar.') ?></span>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<p class="consent-note"><?= icon('lock') ?><span><?= e($projectName) ?> never receives your password. It receives an access token and only the fields above. You can revoke access at any time.</span></p>

<form method="post" action="<?= e(url('/oauth/authorize')) ?>" class="form">
    <?= csrf_field() ?>
    <?php foreach ($fields as $name => $value): ?>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <?php if ($ownerWarning !== ''): ?>
    <label class="check-row">
        <input type="checkbox" name="acknowledge" value="1" required>
        <span>I understand the warning and still want to continue to <?= e($projectName) ?>.</span>
    </label>
    <?php endif; ?>
    <div class="button-row">
        <button class="btn btn-primary btn-lg" type="submit" name="decision" value="allow">Allow access<?= icon('arrow-right') ?></button>
        <button class="btn btn-secondary btn-lg" type="submit" name="decision" value="deny">Deny</button>
    </div>
</form>

<p class="consent-domain">Redirecting to <span class="mono"><?= e($domain) ?></span> after your decision.</p>
