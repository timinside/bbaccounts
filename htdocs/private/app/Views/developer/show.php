<?php
$project = $project ?? [];
$redirects = $redirects ?? [];
$credentials = $credentials ?? [];
$newSecret = $newSecret ?? null;
$consentCount = $consentCount ?? 0;
$tokenCount = $tokenCount ?? 0;
$accent = old('accent_color', $project['accent_color'] ?? '#5b6cff');
$activeCredentials = array_filter($credentials, static fn (array $credential): bool => $credential['revoked_at'] === null);
$isActive = (string) $project['status'] === 'active';
$iconDays = (int) BBA\Core\Config::get('account.project_icon_days', 30);
$iconUnlocked = account_age_days($account) >= $iconDays || (int) ($project['icon_approved'] ?? 0) === 1;
?>
<div class="page-head">
    <div>
        <p class="mono-label">Developer · <?= e($project['slug']) ?></p>
        <h1><?= e($project['name']) ?></h1>
        <p class="page-sub"><?= e($project['domain']) ?></p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/p/' . $project['slug'])) ?>"><?= icon('external') ?><span>Public page</span></a>
        <a class="btn btn-ghost" href="<?= e(url('/developer')) ?>"><?= icon('arrow-left') ?><span>All projects</span></a>
    </div>
</div>



<div class="stat-grid">
    <div class="stat">
        <span class="stat-top"><span class="stat-label">Redirect URIs</span><span class="stat-icon"><?= icon('link') ?></span></span>
        <span class="stat-value"><?= count($redirects) ?></span>
        <span class="stat-label">Exact-match allowlist</span>
    </div>
    <div class="stat">
        <span class="stat-top"><span class="stat-label">Active keys</span><span class="stat-icon"><?= icon('key') ?></span></span>
        <span class="stat-value"><?= count($activeCredentials) ?></span>
        <span class="stat-label">Active credentials</span>
    </div>
    <div class="stat">
        <span class="stat-top"><span class="stat-label">Users</span><span class="stat-icon"><?= icon('users') ?></span></span>
        <span class="stat-value"><?= (int) $consentCount ?></span>
        <span class="stat-label">Granted consent</span>
    </div>
    <div class="stat">
        <span class="stat-top"><span class="stat-label">Live tokens</span><span class="stat-icon"><?= icon('shield') ?></span></span>
        <span class="stat-value"><?= (int) $tokenCount ?></span>
        <span class="stat-label">Unexpired access tokens</span>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Availability</h2>
                <p><?= $isActive ? 'Disabling pauses sign-in and revokes live tokens.' : 'The public page shows “Not active” until you enable it again.' ?></p>
            </div>
            <span class="badge <?= $isActive ? 'badge-ok' : 'badge-danger' ?>"><?= e($project['status']) ?></span>
        </div>
        <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/status')) ?>" class="card-body form">
            <?= csrf_field() ?>
            <?php if ($isActive): ?>
            <div class="field">
                <label for="downtime_message">Downtime message <span class="field-hint">optional</span></label>
                <textarea id="downtime_message" name="downtime_message" maxlength="280" placeholder="We are upgrading sign-in and will be back shortly."></textarea>
                <?php if ($error = form_error('downtime_message')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <?php elseif (!empty($project['downtime_message'])): ?>
            <p class="list-sub">Current message: <?= e($project['downtime_message']) ?></p>
            <?php endif; ?>
            <div class="btn-row">
                <button class="btn <?= $isActive ? 'btn-danger' : 'btn-primary' ?>" type="submit">
                    <?= icon($isActive ? 'x' : 'check') ?><span><?= $isActive ? 'Disable project' : 'Enable project' ?></span>
                </button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Project icon</h2>
                <p>Shown on the public page and in consent screens.</p>
            </div>
        </div>
        <div class="card-body">
            <div class="profile-head" style="margin-bottom: 16px">
                <?= project_icon($project, 'project-icon-lg', true) ?>
                <div>
                    <div class="profile-name"><?= e($project['name']) ?></div>
                    <div class="profile-mail"><?= !empty($project['icon_file']) ? ((int) $project['icon_approved'] === 1 ? 'Visible on the public page' : 'Waiting for administrator approval') : 'No custom icon yet' ?></div>
                </div>
            </div>
            <?php if ($iconUnlocked): ?>
            <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/icon')) ?>" enctype="multipart/form-data" class="form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="icon">Upload a square image</label>
                    <input id="icon" name="icon" type="file" accept="image/png,image/jpeg,image/webp,image/gif" required>
                    <p class="field-hint">JPG, PNG, WebP, or GIF under 2 MB. Replacing it deletes the old file.</p>
                    <?php if ($error = form_error('icon')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="btn-row">
                    <button class="btn btn-secondary" type="submit"><?= icon('upload') ?><span>Upload icon</span></button>
                </div>
            </form>
            <?php else: ?>
            <p class="list-sub">Custom icons unlock <?= $iconDays ?> days after registration. An administrator can approve one earlier.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Project details</h2>
            <p>Slug <span class="mono"><?= e($project['slug']) ?></span> is permanent.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/update')) ?>" class="card-body form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="name">Project name</label>
            <input id="name" name="name" type="text" value="<?= e(old('name', $project['name'])) ?>" maxlength="80" minlength="2" required>
            <?php if ($error = form_error('name')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        </div>

        <div class="field">
            <label for="description">Short description</label>
            <textarea id="description" name="description" maxlength="280"><?= e(old('description', $project['description'])) ?></textarea>
            <?php if ($error = form_error('description')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="domain">Primary domain</label>
                <input id="domain" name="domain" type="text" value="<?= e(old('domain', $project['domain'])) ?>" maxlength="190" required>
                <?php if ($error = form_error('domain')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="accent_color">Accent color</label>
                <div class="color-row">
                    <input id="accent_color" name="accent_color" type="color" value="<?= e($accent) ?>">
                    <span class="mono"><?= e($accent) ?></span>
                </div>
            </div>
            <label class="check-row">
                <input type="checkbox" name="icon_transparent" value="1" <?= (int) ($project['icon_transparent'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span>Transparent icon background</span>
            </label>
        </div>

        <div class="field">
            <label for="login_url">Continue link <span class="field-hint">optional</span></label>
            <input id="login_url" name="login_url" type="url" value="<?= e(old('login_url', $project['login_url'] ?? '')) ?>" maxlength="500" placeholder="https://app.example.com/login">
            <p class="field-hint">Where the public project page sends people. Defaults to your primary domain.</p>
            <?php if ($error = form_error('login_url')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        </div>

        <?php if (($teams ?? []) !== []): ?>
        <div class="field">
            <label for="team_id">Team <span class="field-hint">optional</span></label>
            <select id="team_id" name="team_id">
                <option value="0">No team</option>
                <?php foreach ($teams as $team): ?>
                <option value="<?= (int) $team['id'] ?>" <?= (int) ($project['team_id'] ?? 0) === (int) $team['id'] ? 'selected' : '' ?>><?= e((string) $team['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="field-hint">A team is shown as the project creator alongside you.</p>
        </div>
        <?php endif; ?>

        <div class="btn-row">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Save changes</span></button>
        </div>
    </form>
</section>

<?php if ($verified ?? false): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Badges</h2>
            <p>Create badges for your app and issue them through the badges API. Artwork stays the default badge icon.</p>
        </div>
        <span class="badge badge-ok">verified project</span>
    </div>
    <?php if (($badges ?? []) !== []): ?>
    <div class="list">
        <?php foreach ($badges as $badge): ?>
        <div class="list-row">
            <div>
                <span class="list-title"><?= e($badge['name']) ?></span>
                <span class="list-sub mono">ID <?= (int) $badge['id'] ?> · <?= e($badge['slug']) ?></span>
                <span class="list-sub"><?= e($badge['description'] ?? '') ?></span>
            </div>
            <div class="list-actions">
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/badges/' . rawurlencode((string) $badge['slug']))) ?>"><?= icon('external') ?><span>View</span></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/badges')) ?>" class="card-body form" style="border-top: 1px solid var(--line)">
        <?= csrf_field() ?>
        <div class="field-grid">
            <div class="field">
                <label for="badge_name">Badge name</label>
                <input id="badge_name" name="name" type="text" value="<?= e(old('name')) ?>" maxlength="60" minlength="2" required>
                <?php if ($error = form_error('name')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="badge_description">Description <span class="field-hint">optional</span></label>
                <input id="badge_description" name="description" type="text" value="<?= e(old('description')) ?>" maxlength="160">
                <?php if ($error = form_error('description')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('plus') ?><span>Create badge</span></button>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Redirect URIs</h2>
            <p>Authorization responses only go to an exact match in this list.</p>
        </div>
        <span class="badge"><?= count($redirects) ?></span>
    </div>
    <?php if ($redirects === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('link') ?></span>
        <h3>No redirect URIs</h3>
        <p>Add the callback URL of your application. Without one, authorization requests are rejected.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($redirects as $redirect): ?>
        <div class="list-row">
            <span class="list-icon"><?= icon('link') ?></span>
            <div class="list-main">
                <span class="list-sub mono" style="font-size: 12.5px; color: var(--text)"><?= e($redirect['uri']) ?></span>
                <span class="list-sub">Added <?= e(format_date($redirect['created_at'])) ?></span>
            </div>
            <div class="list-actions">
                <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/redirects/remove')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="redirect_id" value="<?= (int) $redirect['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Remove this redirect URI?"><?= icon('trash') ?><span>Remove</span></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/redirects')) ?>" class="card-body" style="border-top: 1px solid var(--line)">
        <?= csrf_field() ?>
        <div class="inline-form-row">
            <div class="field" style="flex: 1">
                <label for="uri" class="mono-label">New redirect URI</label>
                <input id="uri" name="uri" type="url" value="<?= e(old('uri')) ?>" placeholder="https://app.example.com/auth/callback" maxlength="500" required>
                <?php if ($error = form_error('uri')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <button class="btn btn-secondary" type="submit" style="margin-top: 23px"><?= icon('plus') ?><span>Add URI</span></button>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Client credentials</h2>
            <p>Multiple labeled keys per project. Revoking one does not affect the others.</p>
        </div>
        <span class="badge"><?= count($activeCredentials) ?> active</span>
    </div>
    <?php if ($credentials === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('key') ?></span>
        <h3>No credentials</h3>
        <p>Create a client secret to connect the project.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($credentials as $credential): ?>
        <div class="list-row">
            <span class="list-icon"><?= icon('key') ?></span>
            <div class="list-main">
                <span class="list-title">
                    <?= e($credential['label']) ?>
                    <?php if ($credential['revoked_at'] !== null): ?>
                    <span class="badge badge-danger">revoked</span>
                    <?php elseif ((int) ($credential['is_public'] ?? 0) === 1): ?>
                    <span class="badge badge-accent">public</span>
                    <?php else: ?>
                    <span class="badge badge-ok">active</span>
                    <?php endif; ?>
                </span>
                <div class="field" style="margin-top:8px">
                    <label class="mono-label">Client ID</label>
                    <div class="input-wrap">
                        <input type="password" value="<?= e($credential['client_id']) ?>" readonly autocomplete="off" class="mono">
                        <button class="input-action" type="button" data-toggle-password aria-label="Show client ID"><?= icon('eye') ?></button>
                        <button class="input-action" type="button" data-copy data-copy-value="<?= e($credential['client_id']) ?>" aria-label="Copy client ID"><?= icon('copy') ?></button>
                    </div>
                </div>
                <div class="field" style="margin-top:8px">
                    <label class="mono-label">Client secret</label>
                    <?php if ((int) ($credential['is_public'] ?? 0) === 1): ?>
                    <p class="field-hint">Public client — no secret. Use PKCE (S256).</p>
                    <?php elseif (is_array($newSecret) && (int) ($newSecret['id'] ?? 0) === (int) $credential['id'] && !empty($newSecret['client_secret'])): ?>
                    <div class="input-wrap">
                        <input type="text" value="<?= e($newSecret['client_secret']) ?>" readonly autocomplete="off" class="mono">
                        <button class="input-action" type="button" data-copy data-copy-value="<?= e($newSecret['client_secret']) ?>" aria-label="Copy client secret"><?= icon('copy') ?></button>
                    </div>
                    <p class="field-hint">Shown once. Save it now - it cannot be recovered later. Use it as <code>client_secret</code> in OAuth token requests, not as a Bearer access token.</p>
                    <?php else: ?>
                    <input type="password" value="Hidden after creation" readonly autocomplete="off" class="mono" disabled>
                    <p class="field-hint">Stored as a hash and shown only once when created. If lost, revoke this key and create a new one. Use it as <code>client_secret</code> in OAuth token requests, not as a Bearer access token.</p>
                    <?php endif; ?>
                </div>
                <span class="list-sub">
                    Created <?= e(format_date($credential['created_at'])) ?>
                    · <?= $credential['last_used_at'] ? 'last used ' . e(time_ago($credential['last_used_at'])) : 'never used' ?>
                </span>
            </div>
            <div class="list-actions">
                <button class="btn btn-sm btn-ghost" type="button" data-copy data-copy-value="<?= e($credential['client_id']) ?>"><?= icon('copy') ?><span>Copy ID</span></button>
                <?php if ($credential['revoked_at'] === null): ?>
                <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/credentials/revoke')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="credential_id" value="<?= (int) $credential['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Revoke this client secret? Apps using it stop working."><?= icon('x') ?><span>Revoke</span></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/credentials')) ?>" class="card-body" style="border-top: 1px solid var(--line)">
        <?= csrf_field() ?>
        <div class="inline-form-row">
            <div class="field" style="flex: 1">
                <label for="label" class="mono-label">Key label</label>
                <input id="label" name="label" type="text" value="<?= e(old('label')) ?>" placeholder="Production" maxlength="60" required>
                <?php if ($error = form_error('label')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <button class="btn btn-secondary" type="submit" style="margin-top: 23px"><?= icon('plus') ?><span>Create key</span></button>
        </div>
        <label class="check-row" style="margin-top: 12px">
            <input type="checkbox" name="is_public" value="1" <?= checked(old('is_public') === '1') ?>>
            <span><strong>Public client</strong> — no client secret. Use PKCE. For desktop apps, CLIs, and single-page apps.</span>
        </label>
    </form>
</section>

<section class="card danger-zone">
    <div class="card-head">
        <div>
            <h2>Delete project</h2>
            <p>Removes the project, redirect URIs, credentials, tokens, and consents. This cannot be undone.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/developer/projects/' . $project['id'] . '/delete')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field-grid">
            <div class="field">
                <label for="confirmation">Type <span class="mono"><?= e($project['name']) ?></span> to confirm</label>
                <input id="confirmation" name="confirmation" type="text" autocomplete="off" required>
                <?php if ($error = form_error('confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="delete_password">Your password</label>
                <input id="delete_password" name="password" type="password" autocomplete="current-password" required>
                <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
        </div>
        <div class="btn-row">
            <button class="btn btn-danger" type="submit" data-confirm="Permanently delete <?= e($project['name']) ?>?"><?= icon('trash') ?><span>Delete project</span></button>
        </div>
    </form>
</section>
