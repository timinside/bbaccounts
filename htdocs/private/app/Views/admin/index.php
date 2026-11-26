<?php
$accounts = $accounts ?? [];
$projects = $projects ?? [];
$banners = $banners ?? [];
$referrals = $referrals ?? [];
$totals = $totals ?? ['total' => 0, 'projects' => 0];
$tab = $tab ?? 'accounts';
$query = $query ?? '';
$role = $role ?? '';
$status = $status ?? '';
$page = $page ?? 1;
$pages = $pages ?? 1;
$total = $total ?? 0;
$filters = '';
if ($tab === 'accounts') {
    $filters .= $role !== '' ? '&role=' . rawurlencode($role) : '';
    $filters .= $status !== '' ? '&status=' . rawurlencode($status) : '';
} elseif ($tab === 'projects') {
    $filters .= $status !== '' ? '&status=' . rawurlencode($status) : '';
} elseif ($tab === 'referrals') {
    $filters .= $status !== '' ? '&status=' . rawurlencode($status) : '';
}
$base = '/admin?tab=' . $tab . ($query !== '' ? '&q=' . rawurlencode($query) : '') . $filters;
?>
<div class="page-head">
    <div>
        <p class="mono-label">Administrator</p>
        <h1>Service overview</h1>
        <p class="page-sub">Every account and project on this bbaccounts instance. This area is visible only to administrators.</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat">
        <span class="stat-top"><span class="stat-label">Accounts</span><span class="stat-icon"><?= icon('users') ?></span></span>
        <span class="stat-value"><?= (int) $totals['total'] ?></span>
        <span class="stat-label">Registered</span>
    </div>
    <div class="stat">
        <span class="stat-top"><span class="stat-label">Projects</span><span class="stat-icon"><?= icon('grid') ?></span></span>
        <span class="stat-value"><?= (int) $totals['projects'] ?></span>
        <span class="stat-label">OAuth clients</span>
    </div>
</div>

<nav class="tabs" aria-label="Administrator views">
    <a class="tab <?= $tab === 'accounts' ? 'is-active' : '' ?>" href="<?= e(url('/admin?tab=accounts')) ?>">Accounts</a>
    <a class="tab <?= $tab === 'projects' ? 'is-active' : '' ?>" href="<?= e(url('/admin?tab=projects')) ?>">Projects</a>
    <a class="tab <?= $tab === 'banners' ? 'is-active' : '' ?>" href="<?= e(url('/admin?tab=banners')) ?>">Banners</a>
    <a class="tab <?= $tab === 'referrals' ? 'is-active' : '' ?>" href="<?= e(url('/admin?tab=referrals')) ?>">Referrals</a>
    <a class="tab <?= $tab === 'maintenance' ? 'is-active' : '' ?>" href="<?= e(url('/admin?tab=maintenance')) ?>">Maintenance</a>
    <a class="tab <?= $tab === 'updates' ? 'is-active' : '' ?>" href="<?= e(url('/admin?tab=updates')) ?>">Updates</a>
</nav>

<?php if ($tab === 'banners'): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Site banner</h2>
            <p>Shown at the very top of every public page. Markdown is supported.</p>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/admin/banners')) ?>" class="form">
            <?= csrf_field() ?>
            <label class="field">
                <span class="field-label">Text</span>
                <textarea name="body" rows="3" required placeholder="Maintenance on Sunday, 10:00 UTC. **Sign-in stays online.**"></textarea>
            </label>
            <div class="grid-2">
                <label class="field">
                    <span class="field-label">Link text <span class="field-hint">optional</span></span>
                    <input type="text" name="link_text" maxlength="60" placeholder="Read more">
                </label>
                <label class="field">
                    <span class="field-label">Link URL <span class="field-hint">optional</span></span>
                    <input type="url" name="link_url" placeholder="https://example.com/status">
                </label>
            </div>
            <label class="field">
                <span class="field-label">Background color <span class="field-hint">optional, default gradient otherwise</span></span>
                <div class="color-row">
                    <input type="color" name="bg_color" value="#4f46e5">
                    <label class="check-row">
                        <input type="checkbox" name="use_default_bg" value="1" checked>
                        <span>Use the default gradient</span>
                    </label>
                </div>
            </label>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Publish banner</span></button>
            </div>
        </form>
    </div>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Home page side banners</h2>
            <p>Fixed images on the left and right of the home page. Hidden below 1500px; upload 340 × 1400 for sharp screens.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <?php foreach (['left', 'right'] as $slot): ?>
            <?php
            $custom = setting('home_ad_' . $slot . '_image');
            $custom = $custom !== '' && public_file('/uploads/ads/' . $custom) ? $custom : '';
            $preview = $custom !== ''
                ? url('/uploads/ads/' . rawurlencode($custom))
                : asset('/assets/img/ad0' . ($slot === 'left' ? '1' : '2') . '.png');
            ?>
            <form method="post" action="<?= e(url('/admin/home-ads')) ?>" class="form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="slot" value="<?= e($slot) ?>">
                <span class="field-label"><?= e(ucfirst($slot)) ?> banner</span>
                <img class="home-ad-preview" src="<?= e($preview) ?>" alt="Current <?= e($slot) ?> banner">
                <label class="field">
                    <span class="field-label">Image <span class="field-hint">optional, JPG/PNG/WebP/GIF under 2 MB</span></span>
                    <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
                </label>
                <label class="field">
                    <span class="field-label">Link <span class="field-hint">optional</span></span>
                    <input type="url" name="url" value="<?= e(setting('home_ad_' . $slot . '_url')) ?>" placeholder="https://example.com">
                </label>
                <?php if ($custom !== ''): ?>
                <label class="check-row">
                    <input type="checkbox" name="remove" value="1">
                    <span>Remove the custom image and use the bundled one</span>
                </label>
                <?php endif; ?>
                <div class="btn-row">
                    <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Save <?= e($slot) ?> banner</span></button>
                </div>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Trusted sites</h2>
            <p>Links to these domains open directly, without the "You are leaving bbaccounts" page. One domain per line; subdomains are included.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/trusted-sites')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="trusted_sites">Domains</label>
            <textarea id="trusted_sites" name="sites" rows="5" placeholder="example.com&#10;app.example.org"><?= e(implode("\n", trusted_sites())) ?></textarea>
            <p class="field-hint">For example <code>vynx.example.com</code> covers <code>sub.vynx.example.com</code>. Leave empty to warn on every external link.</p>
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Save trusted sites</span></button>
        </div>
    </form>
</section>

<?php if ($banners === []): ?>
<section class="card" style="margin-top: 20px">
    <div class="empty">
        <span class="empty-icon"><?= icon('bell') ?></span>
        <h3>No banners</h3>
        <p>Published banners appear at the top of the public site.</p>
    </div>
</section>
<?php else: ?>
<div class="stack" style="margin-top: 20px">
    <?php foreach ($banners as $banner): ?>
    <article class="card">
        <div class="card-body">
            <div class="list-row">
                <div class="list-main">
                    <span class="list-title"><?= markdown((string) $banner['body']) ?></span>
                    <span class="list-sub">
                        <?= e(format_date($banner['created_at'])) ?>
                        <?php if (!empty($banner['link_url'])): ?> · <?= e((string) $banner['link_text'] ?: $banner['link_url']) ?><?php endif; ?>
                        <?php if (!empty($banner['bg_color'])): ?> · <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:<?= e((string) $banner['bg_color']) ?>;vertical-align:-1px"></span> <?= e((string) $banner['bg_color']) ?><?php endif; ?>
                    </span>
                </div>
                <form method="post" action="<?= e(url('/admin/banners/' . $banner['id'] . '/delete')) ?>" data-confirm="Delete this banner?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?><span>Delete</span></button>
                </form>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'referrals'): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Referral links</h2>
            <p>Accounts create their own links for a chosen number of days. Both sides receive the points below after a completed registration and become friends.</p>
        </div>
        <span class="badge badge-accent"><?= (int) $total ?> total</span>
    </div>

    <form class="admin-toolbar" method="get" action="<?= e(url('/admin')) ?>">
        <input type="hidden" name="tab" value="referrals">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search by owner, email, or code" aria-label="Search">
        <select name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <button class="btn btn-secondary" type="submit"><?= icon('search') ?><span>Search</span></button>
        <?php if ($query !== '' || $status !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(url('/admin?tab=referrals')) ?>">Clear</a>
        <?php endif; ?>
    </form>
</section>

<?php if ($referrals === []): ?>
<section class="card" style="margin-top: 20px">
    <div class="empty">
        <span class="empty-icon"><?= icon('link') ?></span>
        <h3><?= $query !== '' || $status !== '' ? 'No matching referral links' : 'No referral links yet' ?></h3>
        <p><?= $query !== '' || $status !== '' ? 'Try a different search or status.' : 'Links appear here after an account creates one.' ?></p>
    </div>
</section>
<?php else: ?>
<div class="stack" style="margin-top: 20px">
    <?php foreach ($referrals as $row): ?>
    <?php
    $refLink = url('/r/' . rawurlencode((string) $row['code']));
    $refStatus = \BBA\Models\Referral::status($row);
    ?>
    <article class="card">
        <div class="card-body">
            <form method="post" action="<?= e(url('/admin/referrals/' . (int) $row['id'])) ?>" class="form">
                <?= csrf_field() ?>
                <div class="list-row">
                    <div class="list-main">
                        <span class="list-title"><?= e((string) $row['display_name']) ?></span>
                        <span class="list-sub"><?= e((string) $row['email']) ?><?= $row['username'] ? ' · @' . e((string) $row['username']) : '' ?> · <?= (int) $row['clicks'] ?> clicks · <?= (int) $row['signups'] ?> joined</span>
                        <span class="list-sub">Created <?= e(format_date($row['created_at'] ?? null)) ?><?= $row['expires_at'] !== null ? ' · expires ' . e(format_date((string) $row['expires_at'], 'j F Y')) : '' ?></span>
                    </div>
                    <span class="badge <?= $refStatus === 'active' ? 'badge-ok' : ($refStatus === 'expired' ? 'badge-warn' : 'badge-danger') ?>"><?= e(ucfirst($refStatus)) ?></span>
                </div>

                <div class="field">
                    <label for="referral_link_<?= (int) $row['id'] ?>">Link</label>
                    <div class="input-wrap">
                        <input id="referral_link_<?= (int) $row['id'] ?>" type="text" readonly value="<?= e($refLink) ?>" autocomplete="off">
                        <button class="input-action" type="button" data-copy data-copy-value="<?= e($refLink) ?>" aria-label="Copy referral link"><?= icon('copy') ?></button>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label for="referral_owner_<?= (int) $row['id'] ?>">Points for the link owner</label>
                        <input id="referral_owner_<?= (int) $row['id'] ?>" name="points_owner" type="number" min="0" max="1000" value="<?= (int) $row['points_owner'] ?>">
                    </div>
                    <div class="field">
                        <label for="referral_new_<?= (int) $row['id'] ?>">Points for the new account</label>
                        <input id="referral_new_<?= (int) $row['id'] ?>" name="points_new" type="number" min="0" max="1000" value="<?= (int) $row['points_new'] ?>">
                    </div>
                </div>

                <label class="check-row">
                    <input type="checkbox" name="active" value="1" <?= (int) $row['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Link is active</span>
                </label>

                <div class="btn-row">
                    <button class="btn btn-sm btn-secondary" type="submit"><?= icon('check') ?><span>Save referral</span></button>
                </div>
            </form>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<?php if ($pages > 1): ?>
<div class="pager">
    <span class="list-sub">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $total ?> total</span>
    <span class="btn-row">
        <?php if ($page > 1): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url($base . '&page=' . ($page - 1))) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(url($base . '&page=' . ($page + 1))) ?>">Next</a>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($tab === 'updates'): ?>
<?php $u = is_array($updateInfo ?? null) ? $updateInfo : null; ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Updates</h2>
            <p>Manual update check for installs taken from GitHub. No automatic installation.</p>
        </div>
        <?php if ($u && !empty($u['available'])): ?>
            <span class="badge badge-warn">Update available</span>
        <?php else: ?>
            <span class="badge badge-ok">Up to date / unknown</span>
        <?php endif; ?>
    </div>
    <div class="card-body form">
        <div class="field">
            <span class="field-label">Current version</span>
            <div>v<?= e((string) \BBA\Core\Config::get('version', '')) ?></div>
        </div>
        <div class="field">
            <span class="field-label">Latest GitHub version</span>
            <div><?= $u ? 'v' . e((string) ($u['latest'] ?? '')) : 'Could not check (outbound HTTPS may be blocked).' ?></div>
        </div>
        <?php if ($u && !empty($u['url'])): ?>
        <div class="field">
            <span class="field-label">Release</span>
            <div><a href="<?= e((string) $u['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $u['url']) ?></a></div>
        </div>
        <?php endif; ?>
        <div class="field">
            <span class="field-label">How to update</span>
            <ol style="margin:8px 0 0 20px; color: var(--text-muted); line-height:1.7">
                <li>Back up your database and <code>htdocs/private/config/config.php</code>, <code>private/storage/</code>, <code>uploads/</code>.</li>
                <li>Download the latest source from GitHub and upload files over your install (do not overwrite config/storage/uploads).</li>
                <li>Run the update SQL: open <a href="<?= e(url('/setup.php?mode=update')) ?>"><code>/setup.php?mode=update</code></a>, enter DB credentials, and run. The script asks for credentials every time and does not store them.</li>
                <li>Delete or protect <code>setup.php</code> after updating.</li>
            </ol>
        </div>
        <div class="btn-row">
            <a class="btn btn-primary" href="<?= e(url('/setup.php?mode=update')) ?>">Open update script</a>
            <a class="btn btn-secondary" href="<?= e(url('/admin?tab=updates')) ?>">Refresh check</a>
        </div>
    </div>
</section>

<?php elseif ($tab === 'maintenance'): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Maintenance mode</h2>
            <p>Visitors see a 503 page while maintenance is on. Sign-in, sign-out and administrators stay reachable, so you can always turn it back off.</p>
        </div>
        <span class="badge <?= setting('maintenance_enabled', '0') === '1' ? 'badge-warn' : 'badge-ok' ?>">
            <?= setting('maintenance_enabled', '0') === '1' ? 'Enabled' : 'Disabled' ?>
        </span>
    </div>
    <form method="post" action="<?= e(url('/admin/maintenance')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <label class="check-row">
            <input type="checkbox" name="enabled" value="1" <?= setting('maintenance_enabled', '0') === '1' ? 'checked' : '' ?>>
            <span>Put the site on maintenance</span>
        </label>
        <label class="field">
            <span class="field-label">Message <span class="field-hint">shown on the 503 page, optional</span></span>
            <textarea name="message" rows="3" maxlength="255" placeholder="The site is down for maintenance. Try again in a few minutes."><?= e(setting('maintenance_message')) ?></textarea>
        </label>
        <div class="btn-row">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Save maintenance settings</span></button>
        </div>
    </form>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>System</h2>
            <p>Update the app version shown to users or simulate dialogs for testing.</p>
        </div>
        <span class="badge badge-accent">v<?= e((string) \BBA\Core\Config::get('version', '')) ?></span>
    </div>
    <form method="post" action="<?= e(url('/admin/system')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <label class="field">
            <span class="field-label">App version</span>
            <input type="text" name="version" value="<?= e((string) \BBA\Core\Config::get('version', '')) ?>" maxlength="32" pattern="[0-9A-Za-z._-]+" required>
            <span class="field-hint">Changing this prompts users to refresh on next page load.</span>
        </label>
        <div class="btn-row">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Save version</span></button>
        </div>
    </form>
    <div class="card-body" style="border-top: 1px solid var(--line)">
        <p class="field-label" style="margin-bottom: 10px">Simulate dialogs <span class="field-hint">(admin only, this browser)</span></p>
        <div class="btn-row">
            <button class="btn btn-secondary" type="button" data-simulate="version"><?= icon('refresh') ?><span>Version update</span></button>
            <button class="btn btn-secondary" type="button" data-simulate="tutorial"><?= icon('book') ?><span>Tutorial</span></button>
            <button class="btn btn-ghost" type="button" data-simulate="tutorial-reset"><?= icon('x') ?><span>Reset tutorial seen</span></button>
        </div>
    </div>
</section>

<?php else: ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2><?= $tab === 'accounts' ? 'Accounts' : 'Projects' ?></h2>
            <p><?= $tab === 'accounts'
                ? 'Suspend, ban, notify, or delete an account from its Actions page.'
                : 'Disable a project to revoke its tokens and block new sign-ins.' ?></p>
        </div>
    </div>

    <form class="admin-toolbar" method="get" action="<?= e(url('/admin')) ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="<?= $tab === 'accounts' ? 'Search by name or email' : 'Search by name, slug, domain, or owner' ?>" aria-label="Search">
        <?php if ($tab === 'accounts'): ?>
        <select name="role" aria-label="Filter by role">
            <option value="">All roles</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrators</option>
            <option value="developer" <?= $role === 'developer' ? 'selected' : '' ?>>Developers</option>
            <option value="tester" <?= $role === 'tester' ? 'selected' : '' ?>>Testers</option>
            <option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Members</option>
        </select>
        <select name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Banned</option>
        </select>
        <?php else: ?>
        <select name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Disabled</option>
        </select>
        <?php endif; ?>
        <button class="btn btn-secondary" type="submit"><?= icon('search') ?><span>Search</span></button>
        <?php if ($query !== '' || $role !== '' || $status !== ''): ?>
        <a class="btn btn-ghost" href="<?= e(url('/admin?tab=' . $tab)) ?>">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($tab === 'accounts' && $accounts === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('users') ?></span>
        <h3><?= $query !== '' ? 'No matching accounts' : 'No accounts yet' ?></h3>
        <p><?= $query !== '' ? 'Try a different name or email.' : 'New sign-ups appear here.' ?></p>
    </div>
    <?php elseif ($tab === 'projects' && $projects === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('grid') ?></span>
        <h3><?= $query !== '' ? 'No matching projects' : 'No projects yet' ?></h3>
        <p><?= $query !== '' ? 'Try a different name, slug, domain, or owner.' : 'Projects created by developers appear here.' ?></p>
    </div>
    <?php elseif ($tab === 'accounts'): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Account</th><th>Role</th><th>Status</th><th>Sessions</th><th>Joined</th><th>Last sign-in</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $row): ?>
                <tr>
                    <td>
                        <span style="display: flex; align-items: center; gap: 10px">
                            <img class="avatar avatar-sm" src="<?= e(avatar_url($row)) ?>" alt="" width="28" height="28"<?= avatar_style($row) ?>>
                            <span style="min-width: 0">
                                <strong style="display: block"><?= e($row['display_name']) ?></strong>
                                <span class="list-sub"><?= e($row['email']) ?></span>
                            </span>
                        </span>
                    </td>
                    <td>
                        <?php if ((int) $row['is_admin'] === 1): ?>
                        <span class="badge badge-accent">admin</span>
                        <?php endif; ?>
                        <?php if ((int) $row['is_developer'] === 1): ?>
                        <span class="badge badge-ok">developer</span>
                        <?php endif; ?>
                        <?php if ((int) ($row['is_tester'] ?? 0) === 1): ?>
                        <span class="badge badge-tester">tester</span>
                        <?php endif; ?>
                        <?php if ((int) $row['is_admin'] !== 1 && (int) $row['is_developer'] !== 1 && (int) ($row['is_tester'] ?? 0) !== 1): ?>
                        <span class="list-sub">member</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $row['status'] === 'active' ? 'badge-ok' : ($row['status'] === 'suspended' ? 'badge-warn' : 'badge-danger') ?>"><?= e($row['status']) ?></span>
                        <?php if (!empty($row['status_until'])): ?>
                        <span class="list-sub" style="display: block">until <?= e(format_date($row['status_until'], 'M j, H:i')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $row['session_count'] ?></td>
                    <td><?= e(format_date($row['created_at'])) ?></td>
                    <td><?= e(time_ago($row['last_login_at'])) ?></td>
                    <td style="text-align: right">
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('/admin/accounts/' . $row['id'])) ?>"><?= icon('settings') ?><span>Actions</span></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Project</th><th>Owner</th><th>Status</th><th>Redirect URIs</th><th>Keys</th><th>Live tokens</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($projects as $project): ?>
                <tr>
                    <td>
                        <strong style="display: block"><?= e($project['name']) ?></strong>
                        <span class="list-sub"><?= e($project['domain']) ?></span>
                    </td>
                    <td>
                        <span style="display: block"><?= e($project['owner_name']) ?></span>
                        <span class="list-sub"><?= e($project['owner_email']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $project['status'] === 'active' ? 'badge-ok' : 'badge-warn' ?>"><?= e($project['status']) ?></span>
                    </td>
                    <td><?= (int) $project['redirect_count'] ?></td>
                    <td><?= (int) $project['credential_count'] ?></td>
                    <td><?= (int) $project['active_token_count'] ?></td>
                    <td style="text-align: right">
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('/admin/projects/' . $project['id'])) ?>"><?= icon('settings') ?><span>Actions</span></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
    <div class="pager">
        <span class="list-sub">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $total ?> total</span>
        <span class="btn-row">
            <?php if ($page > 1): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url($base . '&page=' . ($page - 1))) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url($base . '&page=' . ($page + 1))) ?>">Next</a>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
