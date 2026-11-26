<?php
$brand = $brand ?? null;
$next = $next ?? '';
$email = $email ?? '';
$add = $add ?? false;
$formError = form_error('form');
?>
<div class="auth-form-head">
    <p class="mono-label"><?= is_array($brand) ? e(t('Continue to')) . ' ' . e($brand['name']) : e(t('Sign in')) ?></p>
    <h1><?= is_array($brand) ? e(t('Use your account')) : e(t('Welcome back')) ?></h1>
    <p class="auth-sub">
        <?php if (is_array($brand)): ?>
            <?= e(t('Sign in with bbaccounts and return to')) ?> <strong><?= e($brand['domain']) ?></strong>.
        <?php else: ?>
            <?= e(t('Sign in to manage your account, sessions, and connected projects.')) ?>
        <?php endif; ?>
    </p>
</div>

<?php if (isset($_GET['logout'])): ?>
<div class="note note-muted"><?= e(t('You have been signed out.')) ?></div>
<?php endif; ?>

<?php if ($formError !== null): ?>
<div class="form-alert" role="alert"><?= icon('alert') ?><span><?= e($formError) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/login')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e(old('next', $next)) ?>">
    <?php if ($add): ?><input type="hidden" name="add" value="1"><?php endif; ?>

    <div class="field">
        <label for="email"><?= e(t('Email')) ?></label>
        <input id="email" name="email" type="email" value="<?= e(old('email', $email)) ?>" autocomplete="email" inputmode="email" required autofocus>
        <?php if ($error = form_error('email')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <div class="label-row">
            <label for="password"><?= e(t('Password')) ?></label>
            <a class="text-link" href="<?= e(url('/recover' . ($add ? '?add=1' : ''))) ?>"><?= e(t('Use a recovery code')) ?></a>
        </div>
        <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="current-password" required data-password>
            <button class="input-action" type="button" data-toggle-password aria-label="<?= e(t('Show password')) ?>"><?= icon('eye') ?></button>
        </div>
        <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <?= turnstile_widget() ?>

    <button class="btn btn-primary btn-block btn-lg" type="submit">
        <?= is_array($brand) ? e(t('Continue')) : e(t('Sign in')) ?><?= icon('arrow-right') ?>
    </button>
</form>

<?php include BBA_APP . '/Views/auth/social.php'; ?>

<p class="auth-switch">
    <?= e(t('New to bbaccounts?')) ?>
    <a href="<?= e(url('/register' . ($next !== '' || $add ? '?' . http_build_query(array_filter(['next' => $next, 'add' => $add ? '1' : ''])) : ''))) ?>"><?= e(t('Create an account')) ?></a>
</p>
