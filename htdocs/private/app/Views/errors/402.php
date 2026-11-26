<?php
$message = $message ?? 'This page is not available on the current plan.';
?>
<div class="plain-card">
    <span class="plain-mark plain-mark-error"><?= icon('alert') ?></span>
    <p class="mono-label">402</p>
    <h1>Payment required</h1>
    <p class="plain-lede"><?= e($message) ?></p>
    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/account')) ?>">Your account</a>
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Go home</a>
    </div>
</div>
