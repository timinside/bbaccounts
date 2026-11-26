<?php $messages = $messages ?? []; ?>
<div class="stack">
    <?php foreach ($messages as $message): ?>
    <?php $isStaff = (int) ($message['is_admin'] ?? 0) === 1 || (int) ($message['is_moderator'] ?? 0) === 1; ?>
    <article class="ticket-message<?= $isStaff ? ' ticket-message-staff' : '' ?>">
        <div class="list-row">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($message)) ?>" alt="" width="32" height="32"<?= avatar_style($message) ?>>
            <div class="list-main">
                <span class="list-title"><?= e((string) $message['display_name']) ?><?php if ($isStaff): ?> <span class="badge badge-moderator">moderator</span><?php endif; ?></span>
                <span class="list-sub"><?= e(format_date($message['created_at'] ?? null, 'M j, Y H:i')) ?></span>
                <p class="ticket-message-body"><?= nl2br(e((string) $message['body'])) ?></p>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>
