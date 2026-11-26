<?php
$projects = $projects ?? [];
?>
<div class="page-head">
    <div>
        <p class="mono-label">Developer</p>
        <h1>Projects</h1>
        <p class="page-sub">Each project gets an OAuth client, redirect URIs, and a branded sign-in screen.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="<?= e(url('/developer/projects/new')) ?>"><?= icon('plus') ?><span>New project</span></a>
    </div>
</div>

<?php if ($projects === []): ?>
<section class="card">
    <div class="empty">
        <span class="empty-icon"><?= icon('code') ?></span>
        <h3>No projects yet</h3>
        <p>Create a project to get a client ID and secret, then point your app at the bbaccounts authorization endpoint.</p>
        <a class="btn btn-primary" href="<?= e(url('/developer/projects/new')) ?>"><?= icon('plus') ?><span>Create your first project</span></a>
    </div>
</section>
<?php else: ?>
<div class="stack">
    <?php foreach ($projects as $project): ?>
    <article class="card">
        <div class="card-head">
            <div style="display: flex; gap: 14px; align-items: flex-start; min-width: 0">
                <?= project_icon($project, 'project-icon') ?>
                <div style="min-width: 0">
                    <h2 style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
                        <?= e($project['name']) ?>
                        <?php if ($project['status'] !== 'active'): ?>
                        <span class="badge badge-danger"><?= e($project['status']) ?></span>
                        <?php elseif ((int) $project['redirect_count'] === 0 || (int) $project['credential_count'] === 0): ?>
                        <span class="badge badge-warn">active, not configured</span>
                        <?php else: ?>
                        <span class="badge badge-ok">active</span>
                        <?php endif; ?>
                    </h2>
                    <p><?= e($project['domain']) ?><?php if ($project['description'] !== ''): ?> · <?= e(short_text((string) $project['description'], 80)) ?><?php endif; ?></p>
                </div>
            </div>
            <div class="btn-row">
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/p/' . $project['slug'])) ?>"><?= icon('external') ?><span>Public page</span></a>
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/developer/projects/' . $project['id'])) ?>"><?= icon('settings') ?><span>Manage</span></a>
            </div>
        </div>
        <div class="card-body">
            <div class="meta-grid">
                <div><span class="mono-label">Client ID</span><span class="meta-value mono"><?= e($project['slug']) ?>…</span></div>
                <div><span class="mono-label">Redirect URIs</span><span class="meta-value"><?= (int) $project['redirect_count'] ?></span></div>
                <div><span class="mono-label">Active keys</span><span class="meta-value"><?= (int) $project['credential_count'] ?></span></div>
                <div><span class="mono-label">Live tokens</span><span class="meta-value"><?= (int) $project['active_token_count'] ?></span></div>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Integration checklist</h2>
            <p>What a working client needs.</p>
        </div>
        <a class="text-link" href="<?= e(url('/docs')) ?>">Read the docs</a>
    </div>
    <div class="card-body">
        <div class="list" style="margin: -22px">
            <div class="list-row">
                <span class="list-icon"><?= icon('key') ?></span>
                <div class="list-main">
                    <span class="list-title">Client ID and secret</span>
                    <span class="list-sub">Client ID is available in the project panel. Secrets are shown once at creation and stored as hashes.</span>
                </div>
            </div>
            <div class="list-row">
                <span class="list-icon"><?= icon('link') ?></span>
                <div class="list-main">
                    <span class="list-title">Registered redirect URI</span>
                    <span class="list-sub">Authorization requests only redirect to an exact match. HTTPS everywhere; HTTP only for localhost.</span>
                </div>
            </div>
            <div class="list-row">
                <span class="list-icon"><?= icon('shield') ?></span>
                <div class="list-main">
                    <span class="list-title">PKCE verifier on the client</span>
                    <span class="list-sub">Send a code challenge, then the verifier during token exchange. S256 is recommended.</span>
                </div>
            </div>
        </div>
    </div>
</section>
