<aside class="mobile-gate" data-mobile-gate hidden>
    <div class="mobile-gate-card">
        <img class="mobile-gate-icon" src="<?= e(asset('/assets/img/icon.svg')) ?>" alt="" width="56" height="56">
        <p class="mono-label"><?= e(t('Mobile device')) ?></p>
        <h2><?= e(t('bbaccounts works best on desktop')) ?></h2>
        <p class="mobile-gate-text"><?= e(t('You can continue in the browser on mobile, but some pages are easier to use on a larger screen.')) ?></p>
        <div class="mobile-gate-actions">
            <button class="btn btn-primary btn-block" type="button" data-mobile-gate-continue><?= e(t('Continue anyway')) ?></button>
        </div>
    </div>
</aside>
