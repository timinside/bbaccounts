<?php
$next = $next ?? '';
$guard = (bool) ($guard ?? false);
$telegram = (bool) ($telegram ?? false);
$telegramUsername = trim((string) ($telegramUsername ?? ''));
?>
<div class="auth-form-head">
    <p class="mono-label"><?= e(t('Two-factor confirmation')) ?></p>
    <h1><?= e(t('Confirm it is you')) ?></h1>
    <p class="auth-sub"><?= e(t('Enter a code from your authenticator app or Telegram to finish signing in.')) ?></p>
</div>

<?php if ($guard): ?>
<form method="post" action="<?= e(url('/2fa')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="method" value="totp">
    <div class="field">
        <label for="code"><?= e(t('Authenticator code')) ?></label>
        <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required autofocus>
        <?php if ($error = form_error('code')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint"><?= e(t('Use the 6-digit code from Google Authenticator or any compatible TOTP app.')) ?></p>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('Verify')) ?><?= icon('arrow-right') ?></button>
</form>
<?php endif; ?>

<?php if ($telegram): ?>
<div class="<?= $guard ? 'auth-divider' : '' ?>" style="<?= $guard ? 'margin:20px 0' : '' ?>"><?php if ($guard): ?><span><?= e(t('or')) ?></span><?php endif; ?></div>
<form method="post" action="<?= e(url('/2fa/telegram/send')) ?>" class="form" style="margin-bottom:12px">
    <?= csrf_field() ?>
    <button class="btn btn-secondary btn-block" type="submit"><?= icon('send') ?><span><?= e(t('Send code to Telegram')) ?><?= $telegramUsername !== '' ? ' (@' . e($telegramUsername) . ')' : '' ?></span></button>
</form>
<form method="post" action="<?= e(url('/2fa')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="method" value="telegram">
    <div class="field">
        <label for="telegram_code"><?= e(t('Telegram code')) ?></label>
        <input id="telegram_code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="000000" <?= $guard ? '' : 'required autofocus' ?>>
    </div>
    <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(t('Verify Telegram code')) ?><?= icon('arrow-right') ?></button>
</form>
<?php endif; ?>

<div class="<?= ($guard || $telegram) ? 'auth-divider' : '' ?>" style="<?= ($guard || $telegram) ? 'margin:20px 0' : '' ?>"><?php if ($guard || $telegram): ?><span><?= e(t('or')) ?></span><?php endif; ?></div>
<form method="post" action="<?= e(url('/2fa')) ?>" class="form">
    <?= csrf_field() ?>
    <input type="hidden" name="method" value="recovery">
    <div class="field">
        <label for="recovery_code"><?= e(t('Recovery code')) ?></label>
        <input id="recovery_code" name="code" type="text" autocomplete="off" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" <?= (!$guard && !$telegram) ? 'required autofocus' : '' ?>>
        <p class="field-hint"><?= e(t('Use one of your saved recovery codes if you cannot access other methods.')) ?></p>
    </div>
    <button class="btn btn-secondary btn-block" type="submit"><?= e(t('Use recovery code')) ?></button>
</form>

<p class="auth-switch"><a href="<?= e(url('/login' . ($next !== '' ? '?next=' . rawurlencode($next) : ''))) ?>"><?= e(t('Back to sign in')) ?></a></p>
