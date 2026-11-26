<?php
$error = $error ?? 'invalid_request';
$description = $description ?? 'The authorization request could not be completed.';
?>
<div class="plain-card">
    <span class="plain-mark plain-mark-error"><?= icon('alert') ?></span>
    <p class="mono-label">OAuth error</p>
    <h1>Authorization stopped</h1>
    <p class="plain-lede"><?= e($description) ?></p>
    <p class="code-inline"><?= e($error) ?></p>
    <p class="plain-muted">Close this tab and return to the project, or start again from the project's sign-in button.</p>
    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go to bbaccounts</a>
        <a class="btn btn-secondary" href="<?= e(url('/docs')) ?>">Read the docs</a>
    </div>
</div>
