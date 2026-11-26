<div class="plain-card">
    <span class="plain-mark plain-mark-warn"><?= icon('refresh') ?></span>
    <p class="mono-label">419</p>
    <h1>This form expired</h1>
    <p class="plain-lede">Your session or the security token in the form is no longer valid. Reload the page and submit again.</p>
    <div class="button-row">
        <button class="btn btn-primary" type="button" data-history-back>Go back</button>
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Home</a>
    </div>
</div>
