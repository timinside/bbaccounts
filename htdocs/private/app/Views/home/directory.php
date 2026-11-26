<?php
$kind = $kind === 'users' ? 'users' : 'projects';
$base = $kind === 'users' ? '/u' : '/p';
$query = $query ?? '';
$status = $status ?? '';
$filter = $filter ?? '';
$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ($pages ?? 1));
$total = (int) ($total ?? 0);

$params = [];
if ($query !== '') {
    $params['q'] = $query;
}
if ($status !== '') {
    $params['status'] = $status;
}
if ($filter !== '') {
    $params[$kind === 'users' ? 'role' : 'configured'] = $filter;
}
$pageUrl = static fn (int $number): string => url($base . '?' . http_build_query($params + ['page' => $number]));
?>
<div class="page-head">
    <div>
        <p class="mono-label">Directory</p>
        <h1><?= $kind === 'users' ? 'Users' : 'Projects' ?></h1>
        <p class="page-sub"><?= $kind === 'users'
            ? 'Every account on this bbaccounts instance. Search by name or handle.'
            : 'Every project on this bbaccounts instance. Search by name, slug, domain, or owner.' ?></p>
    </div>
</div>

<section class="card">
    <form class="admin-toolbar" method="get" action="<?= e(url($base)) ?>">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="<?= $kind === 'users' ? 'Name or @handle' : 'Name, slug, domain, or owner' ?>" aria-label="Search">
        <?php if ($kind === 'users'): ?>
        <select name="role" aria-label="Filter by role">
            <option value="">All roles</option>
            <?php foreach (['admin' => 'Administrators', 'developer' => 'Developers', 'tester' => 'Testers', 'moderator' => 'Moderators', 'member' => 'Members'] as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $filter === $value ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <?php else: ?>
        <select name="configured" aria-label="Filter by setup">
            <option value="">Any setup</option>
            <option value="yes"<?= $filter === 'yes' ? ' selected' : '' ?>>Configured</option>
            <option value="no"<?= $filter === 'no' ? ' selected' : '' ?>>Not configured</option>
        </select>
        <?php endif; ?>
        <select name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <?php if ($kind === 'users'): ?>
            <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="suspended"<?= $status === 'suspended' ? ' selected' : '' ?>>Suspended</option>
            <option value="banned"<?= $status === 'banned' ? ' selected' : '' ?>>Banned</option>
            <?php else: ?>
            <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="disabled"<?= $status === 'disabled' ? ' selected' : '' ?>>Disabled</option>
            <?php endif; ?>
        </select>
        <button class="btn btn-secondary" type="submit"><?= icon('search') ?><span>Search</span></button>
        <?php if ($params !== []): ?>
        <a class="btn btn-ghost" href="<?= e(url($base)) ?>">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($items === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon($kind === 'users' ? 'users' : 'grid') ?></span>
        <h3><?= $params !== [] ? 'No matching ' . ($kind === 'users' ? 'users' : 'projects') : 'Nothing here yet' ?></h3>
        <p><?= $params !== [] ? 'Try a different search or filter.' : 'New entries appear here automatically.' ?></p>
    </div>
    <?php elseif ($kind === 'users'): ?>
    <div class="card-body">
        <div class="friend-grid">
            <?php foreach ($items as $row): ?>
            <?php
            $private = (int) ($row['private_profile'] ?? 0) === 1;
            $handle = trim((string) ($row['username'] ?? ''));
            ?>
            <a class="friend-chip" href="<?= e(account_url($row)) ?>">
                <img class="avatar avatar-sm" src="<?= e(avatar_url($row)) ?>" alt="" width="30" height="30"<?= avatar_style($row) ?>>
                <span>
                    <strong><?= e($row['display_name']) ?></strong>
                    <span class="list-sub"><?= $handle !== '' ? '@' . e($handle) : '#' . (int) $row['id'] ?>
                        <?php if (!$private): ?>
                        <?= verification_mark('profile', (int) $row['id']) ?>
                        <?= account_status_mark($row) ?>
                        <?php foreach (profile_roles($row) as $role): ?>
                        <span class="role-icon <?= e($role['class']) ?> has-tip" data-tooltip="<?= e($role['name']) ?>" tabindex="0"><?= icon($role['icon']) ?></span>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </span>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="stack" style="padding: 22px">
        <?php foreach ($items as $project): ?>
        <?php
        $active = (string) ($project['status'] ?? '') === 'active' && \BBA\Models\User::isAvailable([
            'status' => $project['owner_status'] ?? 'active',
            'status_until' => $project['owner_status_until'] ?? null,
        ]);
        $configured = (int) ($project['is_configured'] ?? 0) === 1;
        $downtime = trim((string) ($project['downtime_message'] ?? ''));
        $description = trim((string) ($project['description'] ?? ''));
        ?>
        <article class="card">
            <div class="card-head">
                <div style="display: flex; gap: 14px; align-items: flex-start; min-width: 0">
                    <?= project_icon($project, 'project-icon') ?>
                    <div style="min-width: 0">
                        <h2 style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                            <a class="text-link" href="<?= e(url('/p/' . rawurlencode((string) $project['slug']))) ?>"><?= e($project['name']) ?></a>
                            <?php if (!$active): ?>
                            <span class="badge badge-warn">Not active</span>
                            <?php elseif (!$configured): ?>
                            <span class="badge badge-warn">Active, not configured</span>
                            <?php else: ?>
                            <span class="badge badge-ok">Active</span>
                            <?php endif; ?>
                        </h2>
                        <p><?= e($project['domain']) ?><?= $description !== '' ? ' · ' . e(short_text($description, 90)) : '' ?></p>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <span class="list-sub">Owner: <a href="<?= e(account_url([
                    'id' => $project['owner_id'],
                    'username' => $project['owner_username'],
                ])) ?>"><?= e($project['owner_name']) ?></a></span>
                <?php if ($downtime !== ''): ?><p class="list-sub">Downtime: <?= e(short_text($downtime, 100)) ?></p><?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
    <div class="pager">
        <span class="list-sub">Page <?= $page ?> of <?= $pages ?> · <?= $total ?> total</span>
        <span class="btn-row">
            <?php if ($page > 1): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e($pageUrl($page + 1)) ?>">Next</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</section>
