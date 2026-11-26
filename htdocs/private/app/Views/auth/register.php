<?php
$brand = $brand ?? null;
$next = $next ?? '';
$add = $add ?? false;
$formError = form_error('form');
?>
<div class="auth-form-head">
    <p class="mono-label"><?= is_array($brand) ? e(t('Continue to')) . ' ' . e($brand['name']) : e(t('New account')) ?></p>
    <h1><?= e(t('Create your account')) ?></h1>
    <p class="auth-sub">
        <?= e(t('One identity for every project that uses bbaccounts. No email confirmation is required on this instance.')) ?>
    </p>
</div>

<?php if ($formError !== null): ?>
<div class="form-alert" role="alert"><?= icon('alert') ?><span><?= e($formError) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/register')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e(old('next', $next)) ?>">
    <?php if ($add): ?><input type="hidden" name="add" value="1"><?php endif; ?>

    <div class="field">
        <label for="display_name"><?= e(t('Display name')) ?></label>
        <input id="display_name" name="display_name" type="text" value="<?= e(old('display_name')) ?>" autocomplete="nickname" maxlength="60" required autofocus>
        <?php if ($error = form_error('display_name')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="username"><?= e(t('Username')) ?> <span class="field-hint"><?= e(t('optional')) ?></span></label>
        <input id="username" name="username" type="text" value="<?= e(old('username')) ?>" autocomplete="username" maxlength="30" pattern="[a-zA-Z][a-zA-Z0-9_]{2,29}" placeholder="your_handle">
        <p class="field-hint">3-30 characters: letters, numbers, underscores. Used for @mentions. Left blank, one is generated for you.</p>
        <?php if ($error = form_error('username')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="email" inputmode="email" maxlength="254" required>
        <?php if ($error = form_error('email')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required data-password>
            <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
        </div>
        <p class="field-hint">At least 10 characters. Do not reuse a password from another site.</p>
        <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required>
        <?php if ($error = form_error('password_confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <label class="check-row">
        <input type="checkbox" name="terms" value="1" <?= old('terms') === '1' ? 'checked' : '' ?> required>
        <span>I understand that this instance has no email delivery. I will keep my recovery codes safe.</span>
    </label>
    <?php if ($error = form_error('terms')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>

    <?= turnstile_widget() ?>

    <button class="btn btn-primary btn-block btn-lg" type="submit">
        Create account<?= icon('arrow-right') ?>
    </button>
</form>

<p class="auth-switch">
    Already have an account?
    <a href="<?= e(url('/login' . ($next !== '' || $add ? '?' . http_build_query(array_filter(['next' => $next, 'add' => $add ? '1' : ''])) : ''))) ?>">Sign in</a>
</p>
