<?php
$debug = $debug ?? false;
$exception = $exception ?? null;
?>
<div class="plain-card">
    <span class="plain-mark plain-mark-error"><?= icon('alert') ?></span>
    <p class="mono-label">500</p>
    <h1>Something went wrong</h1>
    <p class="plain-lede">The server could not finish this request. Nothing was changed if the error happened before a write. Try again in a moment.</p>

    <?php if ($debug && $exception instanceof Throwable): ?>
    <div class="code-block" style="margin-top: 20px">
        <pre><code><?= e($exception->getMessage()) ?>

<?= e($exception->getFile()) ?>:<?= (int) $exception->getLine() ?></code></pre>
    </div>
    <?php endif; ?>

    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
        <a class="btn btn-secondary" href="<?= e(url('/docs')) ?>">Documentation</a>
    </div>
</div>
