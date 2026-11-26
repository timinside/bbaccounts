<?php
$message = $message ?? 'Sign in to continue.';
?>
<div class="plain-card">
    <span class="plain-mark plain-mark-error"><?= icon('lock') ?></span>
    <p class="mono-label">401</p>
    <h1>Authentication required</h1>
    <p class="plain-lede"><?= e($message) ?></p>
    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Sign in</a>
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Go home</a>
    </div>
</div>
