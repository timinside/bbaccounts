<?php
$codes = $codes ?? [];
$next = $next ?? '';
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Recovery codes</h1>
        <p class="page-sub">These codes are shown once. Each one can reset your password a single time.</p>
    </div>
</div>

<div class="secret-panel">
    <h2><?= icon('alert') ?>Save these now</h2>
    <p>Leaving this page clears the codes from your session. They cannot be displayed again. Only their hashes are stored on the server.</p>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Your ten codes</h2>
            <p>Use one on the recovery page if you forget your password.</p>
        </div>
        <button class="btn btn-sm btn-secondary" type="button" data-copy data-copy-value="<?= e(implode("\n", $codes)) ?>" data-copy-reset="Copy all">
            <?= icon('copy') ?><span data-copy-label>Copy all</span>
        </button>
    </div>
    <div class="card-body">
        <div class="code-grid">
            <?php foreach ($codes as $index => $code): ?>
            <div class="recovery-code">
                <span class="code-index"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <span><?= e($code) ?></span>
                <button class="icon-btn" type="button" data-copy data-copy-value="<?= e($code) ?>" aria-label="Copy code <?= $index + 1 ?>">
                    <?= icon('copy') ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="note" style="margin-top: 20px">
            <?= icon('lock') ?>
            <span>Store them in a password manager or on paper. Anyone with a code and your email can reset the password and sign in.</span>
        </div>

        <div class="button-row">
            <a class="btn btn-primary" href="<?= e(url($next !== '' ? $next : '/account')) ?>">
                <span>I saved them</span><?= icon('arrow-right') ?>
            </a>
            <a class="btn btn-ghost" href="<?= e(url('/account/security')) ?>">Security settings</a>
        </div>
    </div>
</section>
