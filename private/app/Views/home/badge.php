<?php
$badge = $badge ?? [];
$holders = $holders ?? [];
$holderCount = (int) ($holderCount ?? 0);
$image = badge_image($badge);
$rule = (string) ($badge['rule'] ?? 'manual');
$automatic = $rule !== 'manual';
$requirement = match ($rule) {
    'project_owner' => 'Create at least one project.',
    'account_30d' => 'Keep your account for at least 30 days.',
    default => 'Awarded by an administrator.',
};
?>
<div class="page-head">
    <div>
        <p class="mono-label">Badge</p>
        <h1><?= e($badge['name'] ?? 'Badge') ?></h1>
        <p class="page-sub"><?= e((string) ($badge['description'] ?? '')) ?></p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>"><?= icon('arrow-left') ?><span>Home</span></a>
    </div>
</div>

<section class="card">
    <div class="card-body badge-page">
        <?php if ($image !== null): ?>
        <img class="badge-page-medal" src="<?= e($image) ?>" alt="<?= e((string) ($badge['name'] ?? 'Badge')) ?>" width="112" height="112">
        <?php else: ?>
        <span class="badge-page-medal badge-page-medal-fallback"><?= e((string) ($badge['name'] ?? 'Badge')) ?></span>
        <?php endif; ?>
        <div class="badge-page-body">
            <p class="badge-page-description"><?= e((string) ($badge['description'] ?? 'No description yet.')) ?></p>
            <div class="meta-grid">
                <div>
                    <span class="mono-label">Type</span>
                    <span class="meta-value"><?= $automatic ? 'Automatic' : 'Manual' ?></span>
                </div>
                <div>
                    <span class="mono-label">How to earn</span>
                    <span class="meta-value"><?= e($requirement) ?></span>
                </div>
                <div>
                    <span class="mono-label">Holders</span>
                    <span class="meta-value"><?= $holderCount ?></span>
                </div>
            </div>

            <?php if ($holders !== []): ?>
            <h2 class="badge-holders-title">Earned by</h2>
            <div class="badge-holders">
                <?php foreach ($holders as $holder): ?>
                <a class="provider-chip" href="<?= e(account_url($holder)) ?>">
                    <img class="avatar avatar-sm" src="<?= e(avatar_url($holder)) ?>" alt="" width="24" height="24"<?= avatar_style($holder) ?>>
                    <span><?= e((string) $holder['display_name']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
