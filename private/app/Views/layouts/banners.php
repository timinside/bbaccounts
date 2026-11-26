<?php
use BBA\Core\Auth;
use BBA\Core\Db;

$banners = Db::all('SELECT * FROM banners WHERE is_active = 1 ORDER BY id DESC');
$maintenance = setting('maintenance_enabled', '0') === '1';
update_check_schedule();
$update = null;
$adminUser = Auth::user();
if ($adminUser && (int) ($adminUser['is_admin'] ?? 0) === 1) {
    $update = update_check(false, true);
    if (!is_array($update) || empty($update['available'])) $update = null;
}
if (!$maintenance && $banners === [] && $update === null) {
    return;
}
$count = count($banners) + ($maintenance ? 1 : 0) + ($update ? 1 : 0);
?>
<div class="banner-carousel" data-banner-carousel data-banner-interval="10000">
    <div class="banner-track">
        <?php $index = 0; ?>
        <?php if ($maintenance): ?>
        <div class="site-banner<?= $index === 0 ? ' is-active' : '' ?>" data-banner-slide style="background: #dc2626; color: #ffffff">
            <div class="site-banner-inner">
                <span class="site-banner-text">Site is on maintenance</span>
            </div>
        </div>
        <?php $index++; ?>
        <?php endif; ?>
        <?php if ($update): ?>
        <div class="site-banner<?= $index === 0 ? ' is-active' : '' ?>" data-banner-slide style="background: #2563eb; color: #ffffff">
            <div class="site-banner-inner">
                <span class="site-banner-text">Update available: bbaccounts v<?= e((string) $update['latest']) ?> (current v<?= e((string) $update['current']) ?>)</span>
                <a class="site-banner-link" href="<?= e(url('/admin?tab=updates')) ?>" rel="noopener">Open updates<?= icon('arrow-right') ?></a>
            </div>
        </div>
        <?php $index++; ?>
        <?php endif; ?>
        <?php foreach ($banners as $banner): ?>
        <?php
        $bannerStyle = !empty($banner['bg_color'])
            ? ' style="background: ' . e((string) $banner['bg_color']) . '; color: ' . e(readable_on((string) $banner['bg_color'])) . '"'
            : '';
        ?>
        <div class="site-banner<?= $index === 0 ? ' is-active' : '' ?>" data-banner-slide<?= $bannerStyle ?>>
            <div class="site-banner-inner">
                <span class="site-banner-text"><?= markdown((string) $banner['body']) ?></span>
                <?php if (!empty($banner['link_url'])): ?>
                <a class="site-banner-link" href="<?= e((string) $banner['link_url']) ?>" rel="noopener"><?= e($banner['link_text'] ?: 'Learn more') ?><?= icon('arrow-right') ?></a>
                <?php endif; ?>
            </div>
        </div>
        <?php $index++; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($count > 1): ?>
    <button class="banner-nav banner-prev" type="button" data-banner-prev aria-label="Previous announcement"><?= icon('arrow-left') ?></button>
    <button class="banner-nav banner-next" type="button" data-banner-next aria-label="Next announcement"><?= icon('arrow-right') ?></button>
    <div class="banner-progress" aria-hidden="true"><span data-banner-progress></span></div>
    <?php endif; ?>

    <?php if ($count > 2): ?>
    <div class="banner-dots" role="tablist" aria-label="Announcements">
        <?php for ($i = 0; $i < $count; $i++): ?>
        <button class="banner-dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="tab"
            aria-label="Announcement <?= $i + 1 ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
            data-banner-dot="<?= $i ?>"></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
