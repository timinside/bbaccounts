<?php
$message = $message ?? 'The service is temporarily unavailable. Try again in a few minutes.';
?>
<div class="plain-card">
    <span class="plain-mark plain-mark-error"><?= icon('refresh') ?></span>
    <p class="mono-label">503</p>
    <h1>Service unavailable</h1>
    <p class="plain-lede"><?= e($message) ?></p>
    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Try again</a>
    </div>
</div>
