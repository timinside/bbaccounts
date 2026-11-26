<?php
use BBA\Core\Auth;

$account = $account ?? Auth::user();
$notifications = $notifications ?? [];
$points = $points ?? (int) ($account['points'] ?? 0);
$levelInfo = level_info($account);
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$first = explode(' ', trim((string) $account['display_name']))[0];
$isDeveloper = (int) $account['is_developer'] === 1;
$isTester = (int) ($account['is_tester'] ?? 0) === 1;
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account overview</p>
        <h1><?= e($greeting) ?>, <?= e($first) ?></h1>
        <p class="page-sub">This is your identity across every project that uses bbaccounts. Review your balance, progress and connected apps.</p>
        <div class="btn-row" style="margin-top: 8px">
            <?php if ($isTester): ?><span class="badge badge-accent"><?= icon('shield') ?>Tester</span><?php endif; ?>
            <?php if ($isDeveloper): ?><span class="badge badge-ok"><?= icon('code') ?>Developer</span><?php endif; ?>
        </div>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account/profile')) ?>"><?= icon('user') ?><span>Account settings</span></a>
        <a class="btn btn-primary" href="<?= e(url($isDeveloper ? '/developer' : '/account/security')) ?>">
            <?= icon($isDeveloper ? 'code' : 'shield') ?><span><?= $isDeveloper ? 'Projects' : 'Security' ?></span>
        </a>
    </div>
</div>

<div class="stat-grid">
    <a class="stat<?= $points === 0 ? ' stat-warn' : '' ?>" href="<?= e(url('/account/profile')) ?>">
        <span class="stat-top"><span class="stat-label">Points</span><span class="stat-icon"><?= icon('award') ?></span></span>
        <span class="stat-value"><?= $points ?></span>
        <span class="stat-label">Profile balance</span>
    </a>
    <a class="stat" href="<?= e(url('/account/profile')) ?>">
        <span class="stat-top"><span class="stat-label">Level</span><span class="stat-icon"><?= icon('activity') ?></span></span>
        <span class="stat-value"><?= e($levelInfo['label']) ?></span>
        <span class="stat-label"><?= $levelInfo['next_xp'] === null ? 'Maximum reached' : $levelInfo['xp'] . ' / ' . $levelInfo['next_xp'] . ' XP' ?></span>
    </a>
    <a class="stat" href="<?= e(url('/account/apps')) ?>">
        <span class="stat-top"><span class="stat-label">Authorized apps</span><span class="stat-icon"><?= icon('grid') ?></span></span>
        <span class="stat-value"><?= count($consents) ?></span>
        <span class="stat-label">Projects with access</span>
    </a>
    <?php if ($isDeveloper): ?>
    <a class="stat" href="<?= e(url('/developer')) ?>">
        <span class="stat-top"><span class="stat-label">Your projects</span><span class="stat-icon"><?= icon('code') ?></span></span>
        <span class="stat-value"><?= (int) $projectCount ?></span>
        <span class="stat-label">OAuth integrations</span>
    </a>
    <?php endif; ?>
</div>

<?php if ($notifications !== []): ?>
<section class="card" style="margin-bottom: 20px">
    <div class="card-head">
        <div>
            <h2>Notifications</h2>
            <p>Messages from the service team.</p>
        </div>
        <a class="text-link" href="<?= e(url('/account/notifications')) ?>">View all</a>
    </div>
    <div class="list">
        <?php foreach (array_slice($notifications, 0, 3) as $notification): ?>
        <div class="list-row">
            <span class="list-icon"><?= icon(notification_icon($notification['icon'] ?? null)) ?></span>
            <div class="list-main">
                <span class="list-title"><?= e($notification['subject']) ?></span>
                <span class="list-sub" style="white-space: pre-line"><?= e($notification['body']) ?></span>
                <?php if (!empty($notification['link_url'])): ?>
                <a class="text-link" href="<?= e($notification['link_url']) ?>" rel="noopener"><?= e($notification['link_text'] ?: 'Learn more') ?><?= icon('arrow-right') ?></a>
                <?php endif; ?>
                <span class="list-sub"><?= e(time_ago($notification['created_at'])) ?></span>
            </div>
            <form method="post" action="<?= e(url('/account/notifications/delete')) ?>" data-confirm="Delete this notification?">
                <?= csrf_field() ?>
                <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?><span>Delete</span></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php $forumNews = $forumNews ?? []; $forumNewsError = $forumNewsError ?? null; $isAdminViewer = (int) ($account['is_admin'] ?? 0) === 1; ?>
<?php if ($forumNews !== [] || $forumNewsError !== null): ?>
<section class="card" style="margin-bottom: 20px">
    <div class="card-head">
        <div>
            <h2>News from bbforum</h2>
            <p>Latest posts from the bbaccounts blog tag.</p>
        </div>
        <a class="text-link" href="<?= e(rtrim((string) \BBA\Core\Config::get('forum.base_url', 'https://bbforum.unaux.com/public'), '/') . '/t/' . rawurlencode((string) \BBA\Core\Config::get('forum.news_tag', 'bbaccounts'))) ?>" target="_blank" rel="noopener noreferrer">Open in forum</a>
    </div>
    <?php if ($forumNews !== []): ?>
    <div class="list">
        <?php foreach ($forumNews as $item): ?>
        <a class="list-row" href="<?= e((string) ($item['url'] ?? '')) ?>" target="_blank" rel="noopener noreferrer">
            <span class="list-icon"><?= icon('book') ?></span>
            <div class="list-main">
                <span class="list-title"><?= e((string) ($item['title'] ?? '')) ?></span>
                <span class="list-sub"><?= e(time_ago($item['created_at'] ?? null)) ?><?php if ((int) ($item['comments'] ?? 0) > 0): ?> · <?= (int) $item['comments'] ?> <?= (int) $item['comments'] === 1 ? 'reply' : 'replies' ?><?php endif; ?></span>
            </div>
            <?= icon('arrow-right') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card-body">
        <p class="page-sub" style="margin:0">Could not load news.</p>
        <?php if ($isAdminViewer && $forumNewsError !== null): ?>
        <details style="margin-top:8px">
            <summary class="text-link" style="cursor:pointer">See why (only for admins)</summary>
            <p class="field-hint" style="margin-top:8px"><?= e((string) $forumNewsError) ?></p>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Authorized apps</h2>
                <p>Projects that can read your profile.</p>
            </div>
            <a class="text-link" href="<?= e(url('/account/apps')) ?>">Manage</a>
        </div>
        <?php if ($consents === []): ?>
        <div class="empty">
            <span class="empty-icon"><?= icon('grid') ?></span>
            <h3>No apps yet</h3>
            <p>When you approve a project through bbaccounts, it appears here.</p>
        </div>
        <?php else: ?>
        <div class="list">
            <?php foreach (array_slice($consents, 0, 4) as $consent): ?>
            <div class="list-row">
                <span class="list-icon" style="background: <?= e($consent['accent_color']) ?>1f; color: <?= e($consent['accent_color']) ?>">
                    <?= icon('grid') ?>
                </span>
                <div class="list-main">
                    <span class="list-title"><?= e($consent['name']) ?></span>
                    <span class="list-sub"><?= e($consent['domain']) ?> · <?= e(time_ago($consent['updated_at'])) ?></span>
                </div>
                <div class="list-actions">
                    <a class="btn btn-sm btn-ghost" href="<?= e(url('/p/' . $consent['slug'])) ?>">Details</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Recent activity</h2>
                <p>Actions recorded on this account.</p>
            </div>
            <a class="text-link" href="<?= e(url('/account/security')) ?>">Security</a>
        </div>
        <?php if ($activity === []): ?>
        <div class="empty">
            <span class="empty-icon"><?= icon('activity') ?></span>
            <h3>Nothing recorded yet</h3>
            <p>Sign-ins, password changes, and app grants will appear here.</p>
        </div>
        <?php else: ?>
        <div class="list">
            <?php foreach ($activity as $event): ?>
            <div class="list-row">
                <span class="list-icon"><?= icon('activity') ?></span>
                <div class="list-main">
                    <span class="list-title"><?= e(activity_label((string) $event['action'])) ?><?php if (!empty($event['project_name'])): ?> · <?= e($event['project_name']) ?><?php endif; ?></span>
                    <span class="list-sub"><?= e(time_ago($event['created_at'])) ?><?php if (!empty($event['ip'])): ?> · <?= e($event['ip']) ?><?php endif; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Session</h2>
            <p>You are signed in as <?= e($account['email']) ?>.</p>
        </div>
        <span class="badge badge-ok"><?= icon('shield') ?>Active</span>
    </div>
    <div class="card-body">
        <div class="meta-grid">
            <div><span class="mono-label">Signed in</span><span class="meta-value"><?= e(format_date($account['last_login_at'] ?? null, 'M j, Y · H:i')) ?></span></div>
            <div><span class="mono-label">Session expires</span><span class="meta-value"><?= e(format_date($account['session_expires_at'] ?? null, 'M j, Y')) ?></span></div>
            <div><span class="mono-label">Last active</span><span class="meta-value"><?= e(time_ago($account['session_last_active'] ?? null)) ?></span></div>
        </div>
    </div>
</section>
