<?php
$project = $project ?? [];
$isActive = ($project['status'] ?? '') === 'active';
$hasIcon = !empty($project['icon_file']);
$iconApproved = (int) ($project['icon_approved'] ?? 0) === 1;
?>
<div class="page-head">
    <div>
        <p class="mono-label">Administrator · Project actions</p>
        <h1><?= e($project['name'] ?? 'Project') ?></h1>
        <p class="page-sub">Disable this project to revoke its tokens and stop new authorizations, or delete it entirely.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/admin?tab=projects')) ?>"><?= icon('arrow-left') ?><span>All projects</span></a>
    </div>
</div>

<section class="card">
    <div class="card-body">
        <div class="profile-head">
            <?= project_icon($project, 'project-icon-lg', true) ?>
            <div>
                <div class="profile-name"><?= e($project['name'] ?? '') ?></div>
                <div class="profile-mail"><?= e($project['domain'] ?? '') ?> · /p/<?= e($project['slug'] ?? '') ?></div>
                <div class="btn-row" style="margin-top: 8px">
                    <span class="badge <?= $isActive ? 'badge-ok' : 'badge-warn' ?>"><?= e($project['status'] ?? 'unknown') ?></span>
                    <?php if ($hasIcon): ?>
                    <span class="badge <?= $iconApproved ? 'badge-ok' : 'badge-warn' ?>"><?= $iconApproved ? 'icon approved' : 'icon pending' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="meta-grid" style="margin-top: 22px">
            <div><span class="mono-label">Owner</span><span class="meta-value"><?= e($project['owner_name'] ?? '') ?></span><span class="list-sub"><?= e($project['owner_email'] ?? '') ?></span></div>
            <div><span class="mono-label">Redirect URIs</span><span class="meta-value"><?= (int) ($project['redirect_count'] ?? 0) ?></span></div>
            <div><span class="mono-label">Keys</span><span class="meta-value"><?= (int) ($project['credential_count'] ?? 0) ?></span></div>
            <div><span class="mono-label">Live tokens</span><span class="meta-value"><?= (int) ($project['active_token_count'] ?? 0) ?></span></div>
        </div>
        <?php if (!empty($project['login_url'])): ?>
        <p class="list-sub" style="margin-top: 14px">Continue link: <?= e($project['login_url']) ?></p>
        <?php endif; ?>
        <?php if (!empty($project['downtime_message'])): ?>
        <p class="list-sub" style="margin-top: 6px">Downtime message: <?= e($project['downtime_message']) ?></p>
        <?php endif; ?>
    </div>
</section>

<?php $verification = $verification ?? null; ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Verification check</h2>
            <p>A check appears next to this project's name on its public page. Hover text explains what it means.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/projects/' . $project['id'] . '/verification')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="verification_kind">Check type</label>
            <select id="verification_kind" name="kind">
                <option value="none" <?= $verification === null ? 'selected' : '' ?>>No check</option>
                <option value="blue" <?= ($verification['kind'] ?? '') === 'blue' ? 'selected' : '' ?>>Blue — regular verified</option>
                <option value="gold" <?= ($verification['kind'] ?? '') === 'gold' ? 'selected' : '' ?>>Gold — prominent verified</option>
            </select>
        </div>
        <div class="field">
            <label for="verification_note">Hover text</label>
            <input id="verification_note" name="note" type="text" maxlength="255" value="<?= e((string) ($verification['note'] ?? '')) ?>" placeholder="e.g. Official Vynx project">
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Save check</span></button>
        </div>
    </form>
</section>

<?php if ($hasIcon && !$iconApproved): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Project icon</h2>
            <p>This icon is not visible on the public page until it is approved.</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/admin/projects/' . $project['id'] . '/icon')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Approve icon</span></button>
        </form>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2><?= $hasIcon ? 'Replace icon' : 'Add an icon' ?></h2>
            <p>Upload a new image. The owner is notified and the previous file is deleted.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/projects/' . $project['id'] . '/icon')) ?>" enctype="multipart/form-data" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="icon">Image</label>
            <input id="icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
            <?php if ($error = form_error('icon')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <p class="field-hint">JPG, PNG, WebP, or GIF under 2 MB. The new icon is approved automatically.</p>
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('grid') ?><span><?= $hasIcon ? 'Replace icon' : 'Upload icon' ?></span></button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Availability</h2>
            <p><?= $isActive
                ? 'Disabling revokes every live token and unused authorization code.'
                : 'Enabling lets the project authorize users again. Previously revoked tokens stay revoked.' ?></p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/admin/projects/' . $project['id'] . '/status')) ?>">
            <?= csrf_field() ?>
            <button class="btn <?= $isActive ? 'btn-danger' : 'btn-primary' ?>" type="submit">
                <?= icon($isActive ? 'x' : 'check') ?><span><?= $isActive ? 'Disable project' : 'Enable project' ?></span>
            </button>
        </form>
    </div>
</section>

<section class="card danger-zone">
    <div class="card-head">
        <div>
            <h2>Delete project</h2>
            <p>Removes redirect URIs, credentials, tokens, and consent records. This cannot be undone.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/projects/' . $project['id'] . '/delete')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="confirmation">Type the project name to confirm</label>
            <input id="confirmation" name="confirmation" type="text" placeholder="<?= e($project['name'] ?? '') ?>" required autocomplete="off">
            <?php if ($error = form_error('confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        </div>
        <div class="btn-row">
            <button class="btn btn-danger" type="submit" data-confirm="Delete this project and its credentials?"><?= icon('trash') ?><span>Delete forever</span></button>
        </div>
    </form>
</section>
