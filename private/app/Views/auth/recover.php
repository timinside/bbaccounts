<?php
$email = $email ?? '';
$add = $add ?? false;
$formError = form_error('form');
?>
<div class="auth-form-head">
    <p class="mono-label">Recovery</p>
    <h1>Use a recovery code</h1>
    <p class="auth-sub">
        This instance cannot send email. Each account gets ten single-use recovery codes when it is created.
    </p>
</div>

<?php if ($formError !== null): ?>
<div class="form-alert" role="alert"><?= icon('alert') ?><span><?= e($formError) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e(url('/recover')) ?>" class="form">
    <?= csrf_field() ?>
    <?php if ($add): ?><input type="hidden" name="add" value="1"><?php endif; ?>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= e(old('email', $email)) ?>" autocomplete="email" inputmode="email" required autofocus>
        <?php if ($error = form_error('email')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="code">Recovery code</label>
        <input id="code" name="code" type="text" value="<?= e(old('code')) ?>" autocomplete="one-time-code" inputmode="text" spellcheck="false" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required>
        <p class="field-hint">The code is consumed after use. Remaining codes stay valid.</p>
        <?php if ($error = form_error('code')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="password">New password</label>
        <div class="input-wrap">
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required data-password>
            <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
        </div>
        <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required>
        <?php if ($error = form_error('password_confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <?= turnstile_widget() ?>

    <button class="btn btn-primary btn-block btn-lg" type="submit">Reset password<?= icon('arrow-right') ?></button>
</form>

<div class="note note-warn">
    <?= icon('alert') ?>
    <span>If you have lost every recovery code, this account cannot be recovered automatically. There is no email fallback on the free tier.</span>
</div>

<p class="auth-switch"><a href="<?= e(url('/login' . ($add ? '?add=1' : ''))) ?>">Back to sign in</a></p>
