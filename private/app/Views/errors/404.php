<div class="plain-card">
    <span class="plain-mark"><?= icon('grid') ?></span>
    <p class="mono-label">404</p>
    <h1>Page not found</h1>
    <p class="plain-lede">The address does not match anything on this instance. It may have moved, or the link may be incomplete.</p>
    <div class="button-row">
        <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
        <a class="btn btn-secondary" href="<?= e(url('/docs')) ?>">Documentation</a>
    </div>
</div>
