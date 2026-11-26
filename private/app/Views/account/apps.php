<?php
$consents = $consents ?? [];
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Authorized apps</h1>
        <p class="page-sub">Projects that hold an access token for your account. Revoking removes their consent and invalidates every token they have.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<section class="card">
    <?php if ($consents === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('grid') ?></span>
        <h3>No authorized apps</h3>
        <p>When a project sends you through bbaccounts and you approve the request, it will be listed here.</p>
        <a class="btn btn-secondary" href="<?= e(url('/docs')) ?>"><?= icon('book') ?><span>How OAuth works</span></a>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($consents as $consent): ?>
        <div class="list-row">
            <span class="list-icon" style="background: <?= e($consent['accent_color']) ?>1f; color: <?= e($consent['accent_color']) ?>">
                <?= icon('grid') ?>
            </span>
            <div class="list-main">
                <span class="list-title">
                    <?= e($consent['name']) ?>
                    <?php if ((int) $consent['active_tokens'] === 0): ?><span class="badge">no active token</span><?php endif; ?>
                </span>
                <span class="list-sub"><?= e($consent['domain']) ?> · authorized <?= e(time_ago($consent['created_at'])) ?></span>
                <span class="list-sub">
                    <?php foreach (explode(' ', (string) $consent['scope']) as $scope): ?>
                    <span class="chip"><?= e(scope_label($scope)) ?></span>
                    <?php endforeach; ?>
                </span>
            </div>
            <div class="list-actions">
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/p/' . $consent['slug'])) ?>"><?= icon('external') ?><span>Profile</span></a>
                <form method="post" action="<?= e(url('/account/apps/revoke')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="project_id" value="<?= (int) $consent['project_id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Revoke access for <?= e($consent['name']) ?>? Its tokens stop working immediately.">
                        <?= icon('x') ?><span>Revoke</span>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
