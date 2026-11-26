<?php
$account = $account ?? [];
$sessions = $sessions ?? [];
$activity = $activity ?? [];
$recoveryCount = $recoveryCount ?? 0;
$guardEnabled = $guardEnabled ?? false;
$guardSecret = $guardSecret ?? '';
$guardUri = $guardUri ?? '';
$currentSession = (int) ($account['auth_session_id'] ?? 0);
$hasPassword = (int) ($account['password_set'] ?? 1) === 1;
$deviceAccounts = $deviceAccounts ?? [];
$accountsByDevice = $accountsByDevice ?? [];
$switchableIds = $switchableIds ?? [];
$telegramConfigured = (bool) ($telegramConfigured ?? false);
$telegramBotUsername = trim((string) ($telegramBotUsername ?? ''));
$telegramLinked = (bool) ($telegramLinked ?? false);
$telegramUsername = trim((string) ($telegramUsername ?? ''));
$telegram2fa = (bool) ($telegram2fa ?? false);
$telegramNotifyLogin = (bool) ($telegramNotifyLogin ?? true);
$telegramNotifySecurity = (bool) ($telegramNotifySecurity ?? true);
$telegramLinkCode = trim((string) ($telegramLinkCode ?? ''));
$telegramLinkExpires = (string) ($telegramLinkExpires ?? '');
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Security</h1>
        <p class="page-sub">Password, recovery codes, and every device currently signed in to your account.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<div class="grid-2">
    <section class="card">
        <div class="card-head">
            <div>
                <h2><?= $hasPassword ? 'Change password' : 'Set a password' ?></h2>
                <p><?= $hasPassword ? 'Other sessions are signed out when the password changes.' : 'No password is set. Add one to keep email and password sign-in available.' ?></p>
            </div>
            <span class="stat-icon"><?= icon('lock') ?></span>
        </div>
        <form method="post" action="<?= e(url('/account/password')) ?>" class="card-body form">
            <?= csrf_field() ?>

            <?php if ($hasPassword): ?>
            <div class="field">
                <label for="current_password">Current password</label>
                <div class="input-wrap">
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required data-password>
                    <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
                </div>
                <?php if ($error = form_error('current_password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="field">
                <label for="password">New password</label>
                <div class="input-wrap">
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required data-password>
                    <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
                </div>
                <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                <p class="field-hint">At least 10 characters.</p>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="10" required>
                <?php if ($error = form_error('password_confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>

            <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?= icon('shield') ?><span><?= $hasPassword ? 'Change password' : 'Set password' ?></span></button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Recovery codes</h2>
                <p>Single-use codes replace email password resets.</p>
            </div>
            <span class="badge <?= $recoveryCount > 0 ? 'badge-ok' : 'badge-danger' ?>"><?= (int) $recoveryCount ?> left</span>
        </div>
        <div class="card-body">
            <?php if ($recoveryCount === 0): ?>
            <div class="note note-warn">
                <?= icon('alert') ?>
                <span>You have no recovery codes. If you forget your password, this account cannot be recovered. Generate a new set now.</span>
            </div>
            <?php else: ?>
            <p class="page-sub" style="margin-top: 0">
                Each code works once. Store them somewhere offline. Generating a new set invalidates every old code immediately.
            </p>
            <?php endif; ?>

            <?php if ($hasPassword): ?>
            <form method="post" action="<?= e(url('/account/recovery-codes')) ?>" class="form" style="margin-top: 18px">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="recovery_password">Confirm with password</label>
                    <div class="input-wrap">
                        <input id="recovery_password" name="password" type="password" autocomplete="current-password" required data-password>
                        <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
                    </div>
                    <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="btn-row">
                    <button class="btn btn-secondary" type="submit" data-confirm="Generate new recovery codes? Every existing code stops working.">
                        <?= icon('refresh') ?><span>Generate new codes</span>
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="note" style="margin-top: 18px">
                <?= icon('lock') ?>
                <span>Set a password first. Generating new codes asks for your password, so this button appears after one is set.</span>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Authenticator app (TOTP)</h2>
                <p>Use Google Authenticator or any TOTP app for 2FA sign-in confirmation. This is the Guard method.</p>
            </div>
            <span class="badge <?= $guardEnabled ? 'badge-ok' : 'badge-warn' ?>"><?= $guardEnabled ? 'Active' : 'Off' ?></span>
        </div>
        <div class="card-body">
            <?php if ($guardEnabled): ?>
            <p class="page-sub" style="margin-top: 0">Sign-ins now require a 2FA code. Use your authenticator app (Google Authenticator, Authy, etc.). Keep your secret safe; it is not shown here again.</p>
            <form method="post" action="<?= e(url('/account/guard/disable')) ?>" class="form" style="margin-top: 18px">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="disable_guard_code">Confirm with a current code</label>
                    <input id="disable_guard_code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
                    <?php if ($error = form_error('code')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="btn-row">
                    <button class="btn btn-secondary" type="submit"><?= icon('lock') ?><span>Turn Guard off</span></button>
                </div>
            </form>
            <?php elseif ($guardSecret !== ''): ?>
            <div class="secret-panel">
                <div class="secret-rows">
                    <div>
                        <span class="secret-key">Secret</span>
                        <code><?= e($guardSecret) ?></code>
                        <button class="btn btn-sm btn-ghost" type="button" data-copy data-copy-value="<?= e($guardSecret) ?>">
                            <?= icon('copy') ?><span data-copy-label>Copy</span>
                        </button>
                    </div>
                    <div>
                        <span class="secret-key">Setup URI</span>
                        <code><?= e($guardUri) ?></code>
                        <button class="btn btn-sm btn-ghost" type="button" data-copy data-copy-value="<?= e($guardUri) ?>">
                            <?= icon('copy') ?><span data-copy-label>Copy</span>
                        </button>
                    </div>
                </div>
                <div style="margin-top:16px">
                    <span class="secret-key">QR code</span>
                    <div id="guard-qr" data-qr="<?= e($guardUri) ?>" style="margin-top:8px;background:#fff;padding:10px;border-radius:8px;display:inline-block"></div>
                    <p class="field-hint" style="margin-top:8px">Scan with Google Authenticator or any TOTP app.</p>
                </div>
            </div>
            <script src="<?= e(asset('/assets/js/qrcode.min.js')) ?>"></script>
            <script>
            (function(){var el=document.getElementById('guard-qr');if(!el||!el.dataset.qr||typeof QRCode==='undefined')return;new QRCode(el,{text:el.dataset.qr,width:180,height:180});})();
            </script>
            <p class="field-hint">Add this secret to Google Authenticator or any TOTP app, then enter the code it shows to finish setup.</p>
            <form method="post" action="<?= e(url('/account/guard/enable')) ?>" class="form" style="margin-top: 18px">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="enable_guard_code">Current authenticator code</label>
                    <input id="enable_guard_code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
                    <?php if ($error = form_error('code')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="btn-row">
                    <button class="btn btn-primary" type="submit"><?= icon('shield') ?><span>Enable Guard</span></button>
                </div>
            </form>
            <?php else: ?>
            <p class="page-sub" style="margin-top: 0">Generate a secret, add it to the companion app, and confirm with a code.</p>
            <form method="post" action="<?= e(url('/account/guard/start')) ?>" class="form" style="margin-top: 18px">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit"><?= icon('refresh') ?><span>Generate Guard secret</span></button>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="card" id="telegram">
        <div class="card-head">
            <div>
                <h2>Telegram</h2>
                <p>Use Telegram for login codes and security notifications.</p>
            </div>
            <span class="badge <?= $telegramLinked ? 'badge-ok' : 'badge-muted' ?>"><?= $telegramLinked ? 'Linked' : 'Not linked' ?></span>
        </div>
        <div class="card-body">
            <?php if (!$telegramConfigured): ?>
            <p class="field-hint">Telegram bot is not configured on this server.</p>
            <?php elseif (!$telegramLinked): ?>
            <p class="page-sub" style="margin-top:0">Link your Telegram account to receive 2FA codes and notifications.</p>
            <?php if ($telegramLinkCode !== '' && $telegramLinkExpires !== '' && strtotime($telegramLinkExpires) > time()): ?>
            <div class="secret-panel"><div class="secret-rows"><div>
                <span class="secret-key">Code</span>
                <code>/start <?= e($telegramLinkCode) ?></code>
                <button class="btn btn-sm btn-ghost" type="button" data-copy data-copy-value="/start <?= e($telegramLinkCode) ?>"><?= icon('copy') ?><span data-copy-label>Copy</span></button>
            </div></div></div>
            <p class="field-hint">Send this command to <?php if ($telegramBotUsername !== ''): ?><a href="https://t.me/<?= e($telegramBotUsername) ?>" target="_blank" rel="noopener">@<?= e($telegramBotUsername) ?></a><?php else: ?>the configured bot<?php endif; ?>. Code expires <?= e(format_date($telegramLinkExpires, 'M j, Y · H:i')) ?> UTC.</p>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/account/telegram/link')) ?>" class="form" style="margin-top:12px">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit"><?= icon('send') ?><span><?= $telegramLinkCode !== '' ? 'Generate new code' : 'Link Telegram' ?></span></button>
            </form>
            <?php else: ?>
            <p class="page-sub" style="margin-top:0">Linked<?= $telegramUsername !== '' ? ' as @' . e($telegramUsername) : '' ?>.</p>
            <form method="post" action="<?= e(url('/account/telegram/settings')) ?>" class="form" style="margin-top:12px">
                <?= csrf_field() ?>
                <label class="checkbox"><input type="checkbox" name="telegram_2fa" value="1" <?= $telegram2fa ? 'checked' : '' ?>> <span>Use Telegram codes for sign-in 2FA</span></label>
                <label class="checkbox"><input type="checkbox" name="telegram_notify_login" value="1" <?= $telegramNotifyLogin ? 'checked' : '' ?>> <span>Notify on sign-in</span></label>
                <label class="checkbox"><input type="checkbox" name="telegram_notify_security" value="1" <?= $telegramNotifySecurity ? 'checked' : '' ?>> <span>Notify on security changes</span></label>
                <div class="btn-row">
                    <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Save Telegram settings</span></button>
                </div>
            </form>
            <form method="post" action="<?= e(url('/account/telegram/unlink')) ?>" class="form" style="margin-top:8px">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit" data-confirm="Disconnect Telegram from this account?"><?= icon('x') ?><span>Disconnect Telegram</span></button>
            </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($deviceAccounts !== []): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Accounts on this device</h2>
            <p>Switch between accounts without typing a password again. Every account still has its own sessions.</p>
        </div>
        <a class="btn btn-sm btn-secondary" href="<?= e(url('/account/switch')) ?>"><?= icon('users') ?><span>Manage</span></a>
    </div>
    <div class="list">
        <?php foreach ($deviceAccounts as $deviceAccount): ?>
        <div class="list-row">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($deviceAccount)) ?>" alt="" width="32" height="32"<?= avatar_style($deviceAccount) ?>>
            <div class="list-main">
                <span class="list-title">
                    <?= e($deviceAccount['display_name']) ?>
                    <?php if ((int) $deviceAccount['id'] === (int) $account['id']): ?><span class="badge badge-accent">current</span><?php endif; ?>
                </span>
                <span class="list-sub"><?= trim((string) ($deviceAccount['username'] ?? '')) !== '' ? '@' . e($deviceAccount['username']) . ' · ' : '' ?><?= e($deviceAccount['email']) ?></span>
            </div>
            <?php if ((int) $deviceAccount['id'] !== (int) $account['id']): ?>
            <div class="list-actions">
                <?php if (in_array((int) $deviceAccount['id'], $switchableIds, true)): ?>
                <form method="post" action="<?= e(url('/account/switch')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="account_id" value="<?= (int) $deviceAccount['id'] ?>">
                    <input type="hidden" name="next" value="/account/security">
                    <button class="btn btn-sm btn-secondary" type="submit"><?= icon('refresh') ?><span>Switch</span></button>
                </form>
                <?php else: ?>
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/login?next=' . rawurlencode('/account/security'))) ?>"><?= icon('user') ?><span>Sign in</span></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Sessions</h2>
            <p>Every browser or app with a valid session for this account. Rename a session to recognise it later.</p>
        </div>
        <?php if (count($sessions) > 1): ?>
        <form method="post" action="<?= e(url('/account/sessions/revoke-others')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Sign out every other session?"><?= icon('logout') ?><span>Sign out others</span></button>
        </form>
        <?php endif; ?>
    </div>
    <div class="list">
        <?php foreach ($sessions as $session): ?>
        <?php
        $isCurrent = (int) $session['id'] === $currentSession;
        $customLabel = trim((string) ($session['label'] ?? ''));
        $device = device_label((string) ($session['user_agent'] ?? ''), (string) ($session['app_name'] ?? ''));
        ?>
        <div class="list-row">
            <span class="list-icon"><?= icon(device_icon((string) $session['user_agent'])) ?></span>
            <div class="list-main">
                <span class="list-title">
                    <?= e($customLabel !== '' ? $customLabel : $device) ?>
                    <?php if ($isCurrent): ?><span class="badge badge-accent">current</span><?php endif; ?>
                </span>
                <?php if ($customLabel !== ''): ?>
                <span class="list-sub"><?= e($device) ?></span>
                <?php endif; ?>
                <span class="list-sub"><?= e($session['ip'] ?: 'unknown IP') ?> · active <?= e(time_ago($session['last_active_at'])) ?> · expires <?= e(format_date($session['expires_at'])) ?></span>
                <details class="session-rename">
                    <summary>Rename</summary>
                    <form method="post" action="<?= e(url('/account/sessions/rename')) ?>" class="inline-form-row">
                        <?= csrf_field() ?>
                        <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                        <input type="text" name="label" value="<?= e($customLabel) ?>" maxlength="60" placeholder="e.g. Work laptop" aria-label="Session name">
                        <button class="btn btn-sm btn-secondary" type="submit">Save</button>
                    </form>
                    <p class="field-hint">Leave empty to show the detected device again.</p>
                </details>
                <?php $sessionAccounts = $accountsByDevice[(string) ($session['device_id'] ?? '')] ?? []; ?>
                <?php if ($sessionAccounts !== []): ?>
                <span class="list-sub session-accounts">On this device:
                    <?php foreach ($sessionAccounts as $sessionAccount): ?>
                    <span class="session-account"><?= icon('user') ?><?= e((string) $sessionAccount['display_name']) ?></span>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="list-actions">
                <form method="post" action="<?= e(url('/account/sessions/revoke')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="session_id" value="<?= (int) $session['id'] ?>">
                    <button class="btn btn-sm btn-ghost" type="submit" <?= $isCurrent ? 'data-confirm="Sign out of this device?"' : 'data-confirm="Sign out this session?"' ?>>
                        <?= icon('logout') ?><span><?= $isCurrent ? 'Sign out' : 'Revoke' ?></span>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Sign-in activity</h2>
            <p>The last 20 authentication events on this account.</p>
        </div>
    </div>
    <?php if ($activity === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('activity') ?></span>
        <h3>No activity yet</h3>
        <p>Sign-ins and failed attempts are listed here.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($activity as $event): ?>
        <div class="list-row">
            <span class="list-icon"><?= icon('activity') ?></span>
            <div class="list-main">
                <span class="list-title"><?= e(activity_label((string) $event['action'])) ?></span>
                <span class="list-sub"><?= e(format_date($event['created_at'], 'M j, Y · H:i')) ?><?php if (!empty($event['ip'])): ?> · <?= e($event['ip']) ?><?php endif; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php
$deletionKeyCount = (int) ($deletionKeyCount ?? 0);
$deletionTicketId = (int) ($deletionTicketId ?? 0);
?>
<section class="card danger-zone">
    <div class="card-head">
        <div>
            <h2>Delete account</h2>
            <p>Send a deletion request to the moderators. Nothing is removed until they approve it.</p>
        </div>
        <span class="stat-icon"><?= icon('alert') ?></span>
    </div>
    <div class="card-body">
        <p class="page-sub" style="margin-top: 0">
            Confirm with this account's email address and one unused recovery key. The key proves the request
            comes from you. Projects, tokens, and linked providers owned by this account go with it.
        </p>
        <?php if ($deletionKeyCount === 0): ?>
        <div class="note note-warn">
            <?= icon('alert') ?>
            <span>You have no unused recovery keys. Generate a new set above before requesting deletion.</span>
        </div>
        <?php endif; ?>
        <?php if ($deletionTicketId > 0): ?>
        <div class="note note-warn">
            <?= icon('alert') ?>
            <span>You already have an open deletion request. <a href="<?= e(url('/account/tickets/' . $deletionTicketId)) ?>">View it or cancel it</a> before sending another.</span>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/account/tickets/deletion')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="deletion_email">Account email</label>
                <input id="deletion_email" type="text" readonly value="<?= e((string) ($account['email'] ?? '')) ?>">
            </div>
            <div class="field">
                <label for="deletion_key">Unused recovery key</label>
                <input id="deletion_key" name="recovery_key" type="text" autocomplete="off" placeholder="XXXXX-XXXXX-XXXXX-XXXXX" required>
                <?php if ($error = form_error('recovery_key')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="btn-row">
                <button class="btn btn-danger" type="submit" data-confirm="Send a deletion request to the moderators?"><?= icon('trash') ?><span>Request deletion</span></button>
            </div>
        </form>
        <p class="field-hint">Your account stays active until a moderator completes the request.</p>
    </div>
</section>
