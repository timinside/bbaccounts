<?php
$account = $account ?? [];
$accounts = $accounts ?? [];
$next = $next ?? '';
$backUrl = $next !== '' ? $next : '/account';
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Switch account</h1>
        <p class="page-sub">Jump to another account you have signed in with on this device. No password needed.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url($next !== '' ? $next : '/account')) ?>"><?= icon('arrow-left') ?><span><?= $next !== '' ? 'Back' : 'Overview' ?></span></a>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Signed in on this device</h2>
            <p>Switching replaces your current session. You can switch back at any time.</p>
        </div>
    </div>
    <div class="list">
        <div class="list-row">
            <img class="avatar avatar-md" src="<?= e(avatar_url($account)) ?>" alt="" width="44" height="44"<?= avatar_style($account) ?>>
            <div class="list-main">
                <span class="list-title"><?= e($account['display_name'] ?? '') ?></span>
                <span class="list-sub"><?= e($account['email'] ?? '') ?> · current</span>
            </div>
            <span class="badge badge-ok"><?= icon('check') ?>Current</span>
        </div>
        <?php foreach ($accounts as $row): ?>
        <div class="list-row">
            <img class="avatar avatar-md" src="<?= e(avatar_url($row)) ?>" alt="" width="44" height="44"<?= avatar_style($row) ?>>
            <div class="list-main">
                <span class="list-title"><?= e($row['display_name']) ?></span>
                <span class="list-sub"><?= e($row['email']) ?></span>
            </div>
            <form method="post" action="<?= e(url('/account/switch')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="account_id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <button class="btn btn-sm btn-secondary" type="submit"><?= icon('refresh') ?><span>Switch</span></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($accounts === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('users') ?></span>
        <h3>No other accounts</h3>
        <p>Sign in to another account on this device and it will appear here.</p>
        <a class="btn btn-primary" href="<?= e(url('/login?add=1')) ?>"><?= icon('plus') ?><span>Add an account</span></a>
    </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-body">
        <a class="btn btn-secondary" href="<?= e(url('/login?add=1')) ?>"><?= icon('plus') ?><span>Add another account</span></a>
    </div>
</section>
