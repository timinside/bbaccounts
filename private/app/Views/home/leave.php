<?php
$destination = $destination ?? '';
$host = $host ?? '';
?>
<div class="leave-page">
    <section class="card leave-card">
        <div class="card-body">
            <span class="leave-icon"><?= icon('external') ?></span>
            <p class="mono-label">External link</p>
            <h1>You are leaving bbaccounts</h1>
            <p class="page-sub">This link opens another website. bbaccounts does not control what it shows or how it handles your data.</p>
            <p class="leave-url"><?= e($destination) ?></p>
            <div class="btn-row">
                <a class="btn btn-primary" href="<?= e($destination) ?>" rel="noopener noreferrer" data-direct>
                    <?= icon('external') ?><span>Continue to <?= e($host !== '' ? $host : 'the site') ?></span>
                </a>
                <a class="btn btn-secondary" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?><span>Back to bbaccounts</span></a>
            </div>
        </div>
    </section>
</div>
