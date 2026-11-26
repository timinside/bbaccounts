<?php
$notifications = $notifications ?? [];
$unread = 0;
foreach ($notifications as $notification) {
    if ($notification['read_at'] === null) {
        $unread++;
    }
}
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Notifications</h1>
        <p class="page-sub">Messages from the administrators of this instance. You can delete any notification.</p>
    </div>
    <div class="page-head-actions">
        <?php if ($notifications !== []): ?>
        <form method="post" action="<?= e(url('/account/notifications/delete-all')) ?>" data-confirm="Delete all notifications?">
            <?= csrf_field() ?>
            <button class="btn btn-secondary" type="submit"><?= icon('trash') ?><span>Delete all</span></button>
        </form>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<section class="card">
    <?php if ($notifications === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('bell') ?></span>
        <h3>No notifications</h3>
        <p>Role changes and messages from administrators appear here.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($notifications as $notification): ?>
        <div class="list-row">
            <span class="list-icon <?= $notification['read_at'] === null ? 'is-unread' : '' ?>"><?= icon(notification_icon($notification['icon'] ?? null)) ?></span>
            <div class="list-main">
                <span class="list-title"><?= e($notification['subject']) ?></span>
                <span class="list-sub" style="white-space: pre-line"><?= e($notification['body']) ?></span>
                <?php if (!empty($notification['link_url'])): ?>
                <a class="text-link" href="<?= e($notification['link_url']) ?>" rel="noopener"><?= e($notification['link_text'] ?: 'Learn more') ?><?= icon('arrow-right') ?></a>
                <?php endif; ?>
                <span class="list-sub"><?= e(format_date($notification['created_at'], 'M j, Y H:i')) ?></span>
            </div>
            <form method="post" action="<?= e(url('/account/notifications/delete')) ?>" data-confirm="Delete this notification?">
                <?= csrf_field() ?>
                <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?><span>Delete</span></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
