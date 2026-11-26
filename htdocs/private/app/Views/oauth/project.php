<?php
$project = $project ?? [];
$owner = $owner ?? null;
$active = $active ?? false;
$configured = $configured ?? false;
$name = (string) ($project['name'] ?? '');
$domain = (string) ($project['domain'] ?? '');
$description = (string) ($project['description'] ?? '');
$accent = (string) ($project['accent_color'] ?? '#2f5cff');
$loginUrl = trim((string) ($project['login_url'] ?? ''));
$continueUrl = $loginUrl !== '' ? $loginUrl : 'https://' . $domain;
$downtime = trim((string) ($project['downtime_message'] ?? ''));
$viewer = $viewer ?? null;
$viewerHandle = trim((string) ($viewer['username'] ?? ''));
$comments = $comments ?? [];
$ownerHandle = trim((string) ($owner['username'] ?? ''));
$canReport = $viewer !== null && (
    (is_array($owner) && (int) $viewer['id'] === (int) $owner['id'])
    || (int) ($viewer['is_admin'] ?? 0) === 1
    || (int) ($viewer['is_moderator'] ?? 0) === 1
);
$switchHome = '/account/switch?next=' . rawurlencode('/p/' . (string) ($project['slug'] ?? ''));
?>
<section class="project-public" style="<?= e(accent_style($accent)) ?>">
    <div class="project-public-inner">
        <p class="mono-label">Project on bbaccounts</p>
        <div class="project-public-title">
            <?= project_icon($project, 'project-emblem project-public-emblem') ?>
            <h1><?= e($name) ?> <?= verification_mark('project', (int) ($project['id'] ?? 0)) ?></h1>
        </div>
        <p class="project-public-domain"><?= e($domain) ?></p>
        <?php if ($description !== ''): ?><p class="project-public-description"><?= render_mentions($description) ?></p><?php endif; ?>

        <?php if (($ownerWarning ?? '') !== ''): ?>
        <p class="project-public-note project-public-warning">
            <?= icon('alert') ?>
            <span><strong>Warning about the owner.</strong> <?= e($ownerWarning) ?>. Signing in will ask you to confirm.</span>
        </p>
        <?php endif; ?>

        <div class="project-public-status">
            <?php if (!$active): ?>
                <span class="status-dot status-warn"></span><span>Not active</span>
            <?php elseif (!$configured): ?>
                <span class="status-dot status-warn"></span><span>Active, not configured</span>
            <?php else: ?>
                <span class="status-dot status-ok"></span><span>Active</span>
            <?php endif; ?>
        </div>

        <?php if (is_array($team ?? null)): ?>
        <div class="project-owner-pill">
            <span class="avatar avatar-emoji"><?= icon('users') ?></span>
            <span class="project-owner-text">
                <span class="project-owner-label">Project team</span>
                <strong><?= e((string) $team['name']) ?></strong>
                <span class="list-sub">@<?= e((string) ($team['slug'] ?? '')) ?></span>
            </span>
        </div>
        <?php elseif (is_array($owner)): ?>
        <a class="project-owner-pill" href="<?= e(account_url($owner)) ?>">
            <img class="avatar" src="<?= e(avatar_url($owner)) ?>" alt="" width="32" height="32"<?= avatar_style($owner) ?>>
            <span class="project-owner-text">
                <span class="project-owner-label">Project owner</span>
                <strong><?= e((string) $owner['display_name']) ?><?= verification_mark('profile', (int) $owner['id']) ?><?= warning_mark($owner) ?><?= $ownerHandle !== '' ? ' <span class="list-sub">@' . e($ownerHandle) . '</span>' : '' ?></strong>
                <span class="list-sub"><?= e((string) $owner['email']) ?></span>
            </span>
        </a>
        <?php endif; ?>

        <?php if ($viewer !== null): ?>
        <div class="project-viewer">
            <span class="mono-label">Signed in as</span>
            <strong><?= $viewerHandle !== '' ? '@' . e($viewerHandle) : e($viewer['display_name'] ?? '') ?></strong>
            <a class="text-link" href="<?= e(url($switchHome)) ?>">Use a different account</a>
        </div>
        <?php endif; ?>

        <?php if (!$active): ?>
        <p class="project-public-note">
            <?= $downtime !== '' ? e($downtime) : 'This project is currently disabled by its owner. Sign-in is paused until it comes back.' ?>
        </p>
        <?php elseif (!$configured): ?>
        <p class="project-public-note">
            This project has not finished setting up sign-in yet. Once it registers a client and a redirect URI, it will be ready to use.
        </p>
        <?php else: ?>
        <p class="project-public-note">
            Sign-in starts on <?= e($name) ?>. The project sends you here to authenticate, then receives a scoped token. This page only describes the project; it does not start an authorization request.
        </p>
        <?php endif; ?>

        <?php if ($active): ?>
        <div class="button-row">
            <a class="btn btn-primary" href="<?= e($continueUrl) ?>" rel="noopener">Continue to <?= e($name) ?><?= icon('external') ?></a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php
$commentAction = '/p/' . rawurlencode((string) ($project['slug'] ?? '')) . '/comment';
$commentNext = '/p/' . rawurlencode((string) ($project['slug'] ?? ''));
require BBA_APP . '/Views/partials/comments.php';
?>
