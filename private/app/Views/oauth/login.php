<?php
$brand = $brand ?? null;
$next = $next ?? '';
$params = $params ?? [];
$fields = $fields ?? [];
$formError = form_error('form');
$projectName = is_array($brand) ? (string) $brand['name'] : (string) ($params['project']['name'] ?? 'this project');
$projectDomain = is_array($brand) ? (string) $brand['domain'] : (string) ($params['project']['domain'] ?? '');
?>
<div class="auth-form-head">
    <p class="mono-label">Continue to <?= e($projectName) ?></p>
    <h1>Sign in to continue</h1>
    <p class="auth-sub">
        <?= e($projectName) ?> uses bbaccounts for sign-in. Your password is never shared with the project.
    </p>
</div>

<div class="consent-preview">
    <div class="consent-preview-row">
        <span class="consent-preview-key">project</span>
        <span><?= e($projectDomain) ?></span>
    </div>
    <div class="consent-preview-row">
        <span class="consent-preview-key">requested</span>
        <span><?php foreach (($params['scopes'] ?? []) as $scope): ?><span class="chip"><?= e(scope_label($scope)) ?></span><?php endforeach; ?></span>
    </div>
</div>

<?php if ($formError !== null): ?>
<div class="form-alert" role="alert"><?= icon('alert') ?><span><?= e($formError) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" class="form">
    <?= csrf_field() ?>
    <?php foreach ($fields as $name => $value): ?>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="email" inputmode="email" required autofocus>
        <?php if ($error = form_error('email')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <div class="label-row">
            <label for="password">Password</label>
            <a class="text-link" href="<?= e(url('/recover')) ?>">Use a recovery code</a>
        </div>
        <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="current-password" required data-password>
            <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
        </div>
        <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <?= turnstile_widget() ?>

    <button class="btn btn-primary btn-block btn-lg" type="submit">Continue<?= icon('arrow-right') ?></button>
</form>

<?php include BBA_APP . '/Views/auth/social.php'; ?>

<p class="auth-switch">
    New to bbaccounts?
    <a href="<?= e(url('/register?next=' . rawurlencode($next))) ?>">Create an account</a>
</p>
