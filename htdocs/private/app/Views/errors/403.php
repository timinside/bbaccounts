<?php
$message = $message ?? 'You do not have access to this page.';
?>
<div class="plain-card">
    <span class="plain-mark plain-mark-error"><?= icon('lock') ?></span>
    <p class="mono-label">403</p>
    <h1>Access denied</h1>
    <p class="plain-lede"><?= e($message) ?></p>
    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>">Your account</a>
    </div>
</div>
