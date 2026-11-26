<?php
$target = $target ?? [];
$currentAdminId = $currentAdminId ?? 0;
$canGrantAdmin = $canGrantAdmin ?? false;
$sessionCount = $sessionCount ?? 0;
$projectCount = $projectCount ?? 0;
$notifications = $notifications ?? [];
$showcases = $showcases ?? [];
$connections = $connections ?? [];
$recoveryCodesCount = $recoveryCodesCount ?? 0;
$showcaseLabels = $showcaseLabels ?? [];
$sessions = $sessions ?? [];
$isSelf = (int) ($target['id'] ?? 0) === (int) $currentAdminId;
$canManage = $canManage ?? false;
$isActive = ($target['status'] ?? '') === 'active';
$defaultAction = $isActive ? 'suspend' : 'restore';
?>
<div class="page-head">
    <div>
        <p class="mono-label">Administrator · Account actions</p>
        <h1><?= e($target['display_name'] ?? 'Account') ?></h1>
        <p class="page-sub">Suspend, ban, notify, or delete this account. Changes take effect immediately.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/admin?tab=accounts')) ?>"><?= icon('arrow-left') ?><span>All accounts</span></a>
    </div>
</div>

<section class="card">
    <div class="card-body">
        <div class="profile-head">
            <img class="avatar avatar-lg" src="<?= e(avatar_url($target)) ?>" alt="" width="72" height="72"<?= avatar_style($target) ?>>
            <div>
                <div class="profile-name"><?= e($target['display_name'] ?? '') ?></div>
                <div class="profile-mail"><?= e($target['email'] ?? '') ?></div>
                <div class="btn-row" style="margin-top: 8px">
                    <span class="badge <?= $isActive ? 'badge-ok' : (($target['status'] ?? '') === 'suspended' ? 'badge-warn' : 'badge-danger') ?>"><?= e($target['status'] ?? 'unknown') ?></span>
                    <?php if (!empty($target['status_until'])): ?>
                    <span class="list-sub">until <?= e(format_date($target['status_until'], 'M j, Y H:i')) ?></span>
                    <?php endif; ?>
                    <?php if (is_super_account($target)): ?><span class="badge badge-super">super account</span><?php endif; ?>
                    <?php if ((int) ($target['is_admin'] ?? 0) === 1): ?><span class="badge badge-accent">admin</span><?php endif; ?>
                    <?php if ((int) ($target['is_developer'] ?? 0) === 1): ?><span class="badge badge-ok">developer</span><?php endif; ?>
                    <?php if ((int) ($target['is_tester'] ?? 0) === 1): ?><span class="badge badge-tester">tester</span><?php endif; ?>
                    <?php if ((int) ($target['is_moderator'] ?? 0) === 1): ?><span class="badge badge-moderator">moderator</span><?php endif; ?>
                </div>
                <?php if (trim((string) ($target['status_reason'] ?? '')) !== ''): ?>
                <p class="list-sub" style="margin-top: 8px">Reason: <?= e($target['status_reason']) ?></p>
                <?php endif; ?>
                <?php if (!empty($target['purge_after'])): ?>
                <p class="list-sub" style="margin-top: 4px">Auto-deletes on <?= e(format_date($target['purge_after'], 'M j, Y')) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="meta-grid" style="margin-top: 22px">
            <div><span class="mono-label">Account ID</span><span class="meta-value">#<?= (int) ($target['id'] ?? 0) ?></span></div>
            <div><span class="mono-label">Active sessions</span><span class="meta-value"><?= (int) $sessionCount ?></span></div>
            <div><span class="mono-label">Projects</span><span class="meta-value"><?= (int) $projectCount ?></span></div>
            <div><span class="mono-label">Joined</span><span class="meta-value"><?= e(format_date($target['created_at'] ?? null)) ?></span></div>
            <div><span class="mono-label">Last sign-in</span><span class="meta-value"><?= e(time_ago($target['last_login_at'] ?? null)) ?></span></div>
        </div>
    </div>
</section>

<?php if (!$canManage): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2><?= $isSelf ? 'This is your account' : 'Super Account' ?></h2>
            <p><?= $isSelf ? 'Status changes and deletion are disabled for the administrator using this page.' : 'Only the Super Account can manage this account.' ?></p>
        </div>
    </div>
</section>
<?php else: ?>
<section class="card" id="security" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Security</h2>
            <p>Reset credentials or sign the account out everywhere. The account gets a notification with what changed.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="grid-2">
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/password')) ?>" class="form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="admin_password">New password</label>
                    <input id="admin_password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                    <p class="field-hint">At least 10 characters. All sessions are signed out.</p>
                </div>
                <div class="field">
                    <label for="admin_password_confirmation">Confirm password</label>
                    <input id="admin_password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
                </div>
                <button class="btn btn-secondary" type="submit"><?= icon('key') ?><span>Reset password</span></button>
            </form>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/email')) ?>" class="form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="admin_email">Email address</label>
                    <input id="admin_email" name="email" type="email" maxlength="254" value="<?= e((string) $target['email']) ?>" required>
                    <p class="field-hint">Verification is reset. All sessions are signed out.</p>
                </div>
                <button class="btn btn-secondary" type="submit"><?= icon('mail') ?><span>Change email</span></button>
            </form>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/registration')) ?>" class="form" id="details">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="admin_created_at">Registration date</label>
                    <input id="admin_created_at" name="created_at" type="datetime-local" value="<?= e(date('Y-m-d\TH:i', strtotime((string) ($target['created_at'] ?? 'now')))) ?>" required>
                    <p class="field-hint">Changes the account join date shown on the profile and admin panel.</p>
                </div>
                <button class="btn btn-secondary" type="submit"><?= icon('calendar') ?><span>Save date</span></button>
            </form>
        </div>
    </div>
</section>

<div class="grid-2" style="margin-top: 20px">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Status</h2>
                <p>Suspending or banning signs the account out and blocks new sign-ins.</p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/status')) ?>" class="card-body form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="action">Action</label>
                <select id="action" name="action" data-status-action>
                    <option value="suspend" <?= $defaultAction === 'suspend' ? 'selected' : '' ?>>Suspend</option>
                    <option value="ban" <?= $defaultAction === 'ban' ? 'selected' : '' ?>>Ban</option>
                    <option value="restore" <?= $defaultAction === 'restore' ? 'selected' : '' ?>>Restore</option>
                </select>
            </div>
            <div class="field" data-status-field="until">
                <label for="until">Until (optional, UTC)</label>
                <input id="until" name="until" type="datetime-local">
                <p class="field-hint">Leave empty for no automatic expiry. Permanent bans are deleted automatically after the configured number of days.</p>
            </div>
            <div class="field" data-status-field="reason">
                <label for="reason">Reason shown to the account (optional)</label>
                <textarea id="reason" name="reason" rows="3" maxlength="500" placeholder="Why this account is suspended or banned"></textarea>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?= icon('shield') ?><span>Apply</span></button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Send notification</h2>
                <p>Appears on the account overview the next time this person signs in.</p>
            </div>
        </div>
        <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/notify')) ?>" class="card-body form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="subject">Subject</label>
                <input id="subject" name="subject" type="text" maxlength="120" required>
            </div>
            <div class="field">
                <label for="body">Message</label>
                <textarea id="body" name="body" rows="5" maxlength="4000" required></textarea>
            </div>
            <div class="grid-2">
                <div class="field">
                    <label for="icon">Icon</label>
                    <select id="icon" name="icon">
                        <?php foreach (notification_icons() as $iconName => $iconLabel): ?>
                        <option value="<?= e($iconName) ?>"><?= e($iconLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="link_text">Link text <span class="field-hint">optional</span></label>
                    <input id="link_text" name="link_text" type="text" maxlength="60" placeholder="Open dashboard">
                </div>
            </div>
            <div class="field">
                <label for="link_url">Link URL <span class="field-hint">optional</span></label>
                <input id="link_url" name="link_url" type="url" placeholder="https://example.com">
            </div>
            <div class="btn-row">
                <button class="btn btn-secondary" type="submit"><?= icon('bell') ?><span>Send</span></button>
            </div>
        </form>
    </section>
</div>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Roles</h2>
            <p>Changing a role notifies the account automatically.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/roles')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="grid-2">
            <label class="check">
                <input type="checkbox" name="is_developer" value="1" <?= (int) ($target['is_developer'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><strong>Developer</strong><span class="list-sub">Can create projects and OAuth clients.</span></span>
            </label>
            <label class="check">
                <input type="checkbox" name="is_tester" value="1" <?= (int) ($target['is_tester'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><strong>Tester</strong><span class="list-sub">Early access to unfinished features.</span></span>
            </label>
            <label class="check">
                <input type="checkbox" name="is_moderator" value="1" <?= (int) ($target['is_moderator'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><strong>Moderator</strong><span class="list-sub">Reviews reported comments; can be shown as a role on the public profile.</span></span>
            </label>
            <?php if ($canGrantAdmin): ?>
            <label class="check">
                <input type="checkbox" name="is_admin" value="1" <?= (int) ($target['is_admin'] ?? 0) === 1 ? 'checked' : '' ?>>
                <span><strong>Administrator</strong><span class="list-sub">Full access to this admin area.</span></span>
            </label>
            <?php else: ?>
            <div class="field">
                <span class="field-label">Administrator: <?= (int) ($target['is_admin'] ?? 0) === 1 ? 'yes' : 'no' ?></span>
                <p class="field-hint">Only the site owner can grant or revoke this role.</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('shield') ?><span>Save roles</span></button>
        </div>
    </form>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Verification check</h2>
            <p>A check appears next to this profile's name. Hover text explains what it means.</p>
        </div>
    </div>
    <?php $verification = $verification ?? null; ?>
    <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/verification')) ?>" class="card-body form">
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
            <input id="verification_note" name="note" type="text" maxlength="255" value="<?= e((string) ($verification['note'] ?? '')) ?>" placeholder="e.g. Confirmed developer of Vynx">
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Save check</span></button>
        </div>
    </form>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Warning note</h2>
            <p>A public note next to this profile's name, e.g. "This account shows suspicious activity". Projects owned by this account show a double-check before sign-in. Leave empty to remove it.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/warning')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="warning">Warning text</label>
            <input id="warning" name="warning" type="text" maxlength="255" value="<?= e((string) ($target['warning_text'] ?? '')) ?>" placeholder="e.g. Suspicious activity reported, review before signing in">
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('alert') ?><span>Save warning</span></button>
        </div>
    </form>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Profile moderation</h2>
            <p>Change the public description or remove an avatar that breaks the rules. The account is notified.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/profile')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="description">Account description</label>
            <textarea id="description" name="description" rows="3" maxlength="280"><?= e($target['description'] ?? '') ?></textarea>
            <?php if ($error = form_error('description')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        </div>
        <?php if (($target['avatar_type'] ?? 'initials') !== 'initials'): ?>
        <label class="check-row">
            <input type="checkbox" name="remove_avatar" value="1">
            <span>Remove the current avatar and reset it to initials</span>
        </label>
        <?php endif; ?>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('user') ?><span>Save profile</span></button>
        </div>
    </form>
</section>

<section class="card" id="points" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Points &amp; showcases</h2>
            <p>Set the point balance and add or remove showcases on this account's profile.</p>
        </div>
        <span class="badge badge-accent"><?= (int) ($target['points'] ?? 0) ?> points</span>
    </div>
    <div class="card-body form">
        <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/points')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="admin_points">Point balance</label>
                <input id="admin_points" name="points" type="number" min="0" max="1000000" value="<?= (int) ($target['points'] ?? 0) ?>">
            </div>
            <div class="btn-row"><button class="btn btn-sm btn-secondary" type="submit"><?= icon('check') ?><span>Save points</span></button></div>
        </form>

        <?php if ($showcases !== []): ?>
        <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/showcases/visibility')) ?>" class="form">
            <?= csrf_field() ?>
            <?php foreach ($showcases as $showcase): ?>
            <?php
            $showcaseType = (string) $showcase['type'];
            $isFixed = in_array($showcaseType, \BBA\Models\Showcase::FIXED_TYPES, true);
            $showcaseWidth = \BBA\Models\Showcase::width($showcase);
            $showcaseReviews = $showcaseType === 'reviews' ? \BBA\Models\Showcase::reviews($showcase) : [];
            ?>
            <label class="check-row">
                <input type="checkbox" name="visible_showcases[]" value="<?= (int) $showcase['id'] ?>" <?= (int) $showcase['is_visible'] === 1 ? 'checked' : '' ?>>
                <span><strong><?= e($showcase['title']) ?></strong> <span class="field-hint"><?= e($showcaseLabels[$showcaseType] ?? ucfirst($showcaseType)) ?><?php if ($isFixed): ?> · always on profile<?php endif; ?></span></span>
            </label>
            <?php endforeach; ?>
            <div class="btn-row"><button class="btn btn-sm btn-secondary" type="submit"><?= icon('check') ?><span>Save showcase visibility</span></button></div>
        </form>

        <?php foreach ($showcases as $showcase): ?>
        <?php
        $showcaseType = (string) $showcase['type'];
        $isFixed = in_array($showcaseType, \BBA\Models\Showcase::FIXED_TYPES, true);
        $showcaseWidth = \BBA\Models\Showcase::width($showcase);
        $showcaseReviews = $showcaseType === 'reviews' ? \BBA\Models\Showcase::reviews($showcase) : [];
        ?>
        <div class="link-row">
            <span class="list-sub"><?= e($showcaseLabels[$showcaseType] ?? ucfirst($showcaseType)) ?> · <?= $isFixed ? 'always on profile' : ($showcaseWidth === 'wide' ? 'wide' : 'normal') ?></span>
                <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/showcases/' . (int) $showcase['id'] . '/width')) ?>" class="showcase-width-form">
                    <?= csrf_field() ?>
                    <select name="width" aria-label="Showcase width">
                        <?php foreach (\BBA\Models\Showcase::WIDTHS as $widthKey => $widthLabel): ?>
                        <option value="<?= e($widthKey) ?>" <?= $showcaseWidth === $widthKey ? 'selected' : '' ?>><?= e($widthLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-ghost" type="submit">Save width</button>
                </form>
                <?php if (!$isFixed): ?>
                <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/showcases/' . (int) $showcase['id'] . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Remove this showcase?"><?= icon('trash') ?><span>Remove</span></button>
                </form>
                <?php endif; ?>
            </div>
            <?php if ($showcaseReviews !== []): ?>
            <p class="field-hint" style="margin: 0 0 12px">Reviews: <?php foreach ($showcaseReviews as $review): ?><?= e((string) ($review['project'] ?? 'Project')) ?> (<?= (int) ($review['rating'] ?? 0) ?>/5) · <?php endforeach; ?></p>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php
        $ownedTypes = array_column($showcases, 'type');
        $availableTypes = array_values(array_diff(\BBA\Models\Showcase::TYPES, $ownedTypes, \BBA\Models\Showcase::FIXED_TYPES));
        ?>
        <?php if ($availableTypes !== []): ?>
        <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/showcases')) ?>" class="form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
                <label for="admin_showcase_type">Add a showcase</label>
                <select id="admin_showcase_type" name="type" data-showcase-type>
                    <?php foreach ($availableTypes as $showcaseType): ?>
                    <option value="<?= e($showcaseType) ?>"><?= e($showcaseLabels[$showcaseType] ?? ucfirst($showcaseType)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" data-showcase-title>
                <label for="admin_showcase_title">Title <span class="field-hint" data-showcase-title-hint>optional</span></label>
                <input id="admin_showcase_title" name="admin_showcase_title" type="text" maxlength="60" autocomplete="off" data-showcase-title-input>
            </div>
            <div class="field" data-showcase-body>
                <label for="admin_showcase_body" data-showcase-body-label>Text or links</label>
                <textarea id="admin_showcase_body" name="admin_showcase_text" rows="2" maxlength="500" autocomplete="off" data-showcase-body-input></textarea>
                <p class="field-hint" data-showcase-body-hint>Shown as a text card. @mentions become links.</p>
            </div>
            <div class="field" data-showcase-media hidden>
                <label for="admin_showcase_media">Media URL</label>
                <input id="admin_showcase_media" name="media_url" type="url" maxlength="500" autocomplete="off" placeholder="https://example.com/image.jpg" data-showcase-media-input>
                <p class="field-hint">An image, video page, or direct media link shown in a Media showcase.</p>
            </div>
            <div class="field" data-showcase-friends hidden>
                <span class="field-label">Best friends (up to 5)</span>
                <?php if (empty($friends)): ?>
                <p class="field-hint">This account has no friends yet.</p>
                <?php else: ?>
                <?php foreach ($friends as $friend): ?>
                <label class="check-row">
                    <input type="checkbox" name="bestfriends[]" value="<?= (int) $friend['id'] ?>">
                    <span><?= e((string) $friend['display_name']) ?></span>
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="field" data-showcase-reviews hidden>
                <span class="field-label">First review</span>
                <div class="grid-2">
                    <div class="field">
                        <label for="admin_review_project">Project name</label>
                        <input id="admin_review_project" name="review_project" type="text" maxlength="60" autocomplete="off" data-review-required>
                    </div>
                    <div class="field">
                        <label for="admin_review_rating">Rating</label>
                        <select id="admin_review_rating" name="review_rating">
                            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <option value="<?= $stars ?>"><?= $stars ?> <?= $stars === 1 ? 'star' : 'stars' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="admin_review_url">Project link <span class="field-hint">optional</span></label>
                    <input id="admin_review_url" name="review_url" type="url" maxlength="300" autocomplete="off" placeholder="https://example.com">
                </div>
                <div class="field">
                    <label for="admin_review_text">Review</label>
                    <textarea id="admin_review_text" name="review_text" rows="2" maxlength="500" autocomplete="off" data-review-required></textarea>
                </div>
            </div>
            <div class="btn-row"><button class="btn btn-sm btn-secondary" type="submit"><?= icon('plus') ?><span>Add showcase</span></button></div>
        </form>
        <?php endif; ?>
    </div>
</section>

<section class="card" id="badges" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Badges</h2>
            <p>Grant or revoke manual badges. Automatic badges are earned by account activity.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/badges')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="grid-2">
            <?php foreach ($badges as $badge): ?>
            <?php $auto = (string) $badge['rule'] !== 'manual'; ?>
            <label class="check-row">
                <input type="checkbox" name="badges[]" value="<?= (int) $badge['id'] ?>" <?= isset($assignedBadges[(int) $badge['id']]) ? 'checked' : '' ?> <?= $auto ? 'disabled' : '' ?>>
                <span>
                    <span class="badge <?= e($badge['style']) ?>"><?= badge_mark($badge) ?><?= e($badge['name']) ?></span>
                    <span class="list-sub"><?= e((string) ($badge['description'] ?? '')) ?><?= $auto ? ' · automatic' : '' ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Save badges</span></button>
        </div>
    </form>
</section>

<section class="card" id="connections" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Connections</h2>
            <p>Linked sign-in providers for this account. Unlinking removes the connection immediately.</p>
        </div>
    </div>
    <?php if ($connections === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('link') ?></span>
        <h3>No connections</h3>
        <p>This account has no linked sign-in providers.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($connections as $identity): ?>
        <?php
        $provider = (string) ($identity['provider'] ?? '');
        $label = \BBA\Services\SocialAuth::name($provider);
        $detail = trim((string) ($identity['username'] ?? ''));
        if ($detail === '') { $detail = trim((string) ($identity['email'] ?? '')); }
        if ($detail === '') { $detail = trim((string) ($identity['provider_user_id'] ?? '')); }
        ?>
        <div class="list-row">
            <span class="list-icon"><?= provider_mark($provider) ?></span>
            <div class="list-main">
                <span class="list-title"><?= e($label) ?></span>
                <span class="list-sub"><?= e($detail) ?><?= !empty($identity['is_visible']) ? ' · visible on profile' : ' · hidden' ?></span>
            </div>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/connections/unlink')) ?>" data-confirm="Unlink <?= e($label) ?> from this account?">
                <?= csrf_field() ?>
                <input type="hidden" name="identity_id" value="<?= (int) $identity['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?><span>Unlink</span></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="card" id="twofa" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Two-factor authentication</h2>
            <p>View and reset 2FA methods for this account. Disabling a method takes effect immediately.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="meta-grid" style="margin-bottom: 16px">
            <div><span class="mono-label">Authenticator / Guard</span><span class="meta-value"><?= \BBA\Models\Guard::enabled($target) ? 'Enabled' : 'Disabled' ?></span></div>
            <div><span class="mono-label">Telegram 2FA</span><span class="meta-value"><?= ((int) ($target['telegram_2fa'] ?? 0) === 1 && trim((string) ($target['telegram_chat_id'] ?? '')) !== '') ? 'Enabled' : 'Disabled' ?></span></div>
            <div><span class="mono-label">Recovery codes</span><span class="meta-value"><?= (int) $recoveryCodesCount ?> unused</span></div>
        </div>
        <div class="btn-row">
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/2fa')) ?>" data-confirm="Disable Authenticator/Guard 2FA for this account?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disable_guard">
                <button class="btn btn-sm btn-secondary" type="submit" <?= \BBA\Models\Guard::enabled($target) ? '' : 'disabled' ?>><?= icon('shield') ?><span>Disable Guard</span></button>
            </form>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/2fa')) ?>" data-confirm="Disable Telegram 2FA for this account?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="disable_telegram">
                <button class="btn btn-sm btn-secondary" type="submit" <?= ((int) ($target['telegram_2fa'] ?? 0) === 1) ? '' : 'disabled' ?>><?= icon('x') ?><span>Disable Telegram 2FA</span></button>
            </form>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/2fa')) ?>" data-confirm="Revoke all recovery codes for this account?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke_recovery">
                <button class="btn btn-sm btn-ghost" type="submit" <?= $recoveryCodesCount > 0 ? '' : 'disabled' ?>><?= icon('trash') ?><span>Revoke recovery codes</span></button>
            </form>
        </div>
    </div>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Notifications sent</h2>
            <p>Delete a notification to remove it from this account.</p>
        </div>
    </div>
    <?php if ($notifications === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('bell') ?></span>
        <h3>No notifications</h3>
        <p>Nothing has been sent to this account yet.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($notifications as $notification): ?>
        <div class="list-row">
            <span class="list-icon"><?= icon(notification_icon($notification['icon'] ?? null)) ?></span>
            <div class="list-main">
                <span class="list-title"><?= e($notification['subject']) ?></span>
                <span class="list-sub" style="white-space: pre-line"><?= e($notification['body']) ?></span>
                <?php if (!empty($notification['link_url'])): ?>
                <a class="text-link" href="<?= e($notification['link_url']) ?>" rel="noopener"><?= e($notification['link_text'] ?: 'Learn more') ?><?= icon('arrow-right') ?></a>
                <?php endif; ?>
                <span class="list-sub"><?= e(format_date($notification['created_at'], 'M j, Y H:i')) ?><?= $notification['read_at'] === null ? ' · unread' : ' · read' ?></span>
            </div>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/notifications/delete')) ?>" data-confirm="Delete this notification?">
                <?= csrf_field() ?>
                <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?><span>Delete</span></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="card" id="sessions" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Sessions</h2>
            <p><?= count($sessions) ?> active <?= count($sessions) === 1 ? 'session' : 'sessions' ?>. Revoking one signs that device out immediately.</p>
        </div>
        <?php if ($sessions !== []): ?>
        <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/sessions/revoke-all')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Sign out every session of this account?"><?= icon('logout') ?><span>Sign out everywhere</span></button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($sessions === []): ?>
    <div class="card-body"><div class="note"><?= icon('check') ?><span>No active sessions.</span></div></div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($sessions as $session): ?>
        <div class="list-row">
            <span class="list-icon"><?= icon(device_icon((string) ($session['user_agent'] ?? ''))) ?></span>
            <div class="list-main">
                <span class="list-title"><?= e(device_label((string) ($session['user_agent'] ?? ''), (string) ($session['app_name'] ?? ''))) ?></span>
                <span class="list-sub"><?= e((string) ($session['ip'] ?: 'unknown IP')) ?> · active <?= e(time_ago($session['last_active_at'])) ?> · expires <?= e(format_date($session['expires_at'])) ?></span>
                <?php if (trim((string) ($session['label'] ?? '')) !== ''): ?><span class="list-sub">Name: <?= e((string) $session['label']) ?></span><?php endif; ?>
            </div>
            <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/sessions/revoke')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Sign out this session?"><?= icon('logout') ?><span>Revoke</span></button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="card danger-zone" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Delete account</h2>
            <p>Removes the account, its projects, sessions, tokens, and connections. This cannot be undone.</p>
        </div>
    </div>
    <form method="post" action="<?= e(url('/admin/accounts/' . $target['id'] . '/delete')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="confirmation">Type the account email to confirm</label>
            <input id="confirmation" name="confirmation" type="text" placeholder="<?= e($target['email']) ?>" required autocomplete="off">
            <?php if ($error = form_error('confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        </div>
        <div class="btn-row">
            <button class="btn btn-danger" type="submit" data-confirm="Delete this account and everything it owns?"><?= icon('trash') ?><span>Delete forever</span></button>
        </div>
    </form>
</section>
<?php endif; ?>
