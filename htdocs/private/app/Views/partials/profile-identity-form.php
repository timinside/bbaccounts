<?php
/**
 * Shared profile identity form. Used by the classic settings page and by the
 * studio (profile v2) inline editor so both post to the same validated action.
 * Variables: $account (required), $formAction, $nextPath, $includeDescription,
 * $formId, $saveAll, $saveLabel, $showActions (optional).
 */
$account = $account ?? [];
$formAction = $formAction ?? url('/account/profile');
$nextPath = (string) ($nextPath ?? '');
$includeDescription = (bool) ($includeDescription ?? true);
$formId = (string) ($formId ?? 'profile-settings');
$saveAll = (bool) ($saveAll ?? false);
$saveLabel = (string) ($saveLabel ?? 'Save profile');
$showActions = (bool) ($showActions ?? true);

$avatarType = (string) ($account['avatar_type'] ?? 'initials');
$avatarValue = (string) ($account['avatar_value'] ?? '');
$legacyEmoji = [
    '1' => '😀', '2' => '😎', '3' => '🦊', '4' => '🐱', '5' => '🐼', '6' => '🚀',
    '7' => '🌙', '8' => '⚡', '9' => '🎮', '10' => '🎧', '11' => '🌸', '12' => '🍀',
];
if ($avatarType === 'preset' && isset($legacyEmoji[$avatarValue])) {
    $avatarValue = $legacyEmoji[$avatarValue];
}
$currentAvatar = $avatarType === 'preset' ? 'emoji' : ($avatarType === 'upload' ? 'upload' : 'initials');
$currentEmoji = $currentAvatar === 'emoji' ? $avatarValue : '';
$avatarColor = (string) ($account['avatar_color'] ?? '');
$colorValue = (string) old('avatar_color', $avatarColor !== '' ? $avatarColor : '#4f46e5');
$isAdmin = (int) ($account['is_admin'] ?? 0) === 1;
$emojiGroups = emoji_groups();
$selectedEmoji = (string) old('avatar_emoji', $currentEmoji);
$selectedAvatar = (string) old('avatar', $currentAvatar);

$savedContacts = json_decode((string) ($account['contacts'] ?? ''), true);
$savedContacts = is_array($savedContacts) ? $savedContacts : [];
$contactValue = static function (string $kind, int $index = 0) use ($savedContacts): string {
    $values = [];
    foreach ($savedContacts as $row) {
        if (is_array($row) && (string) ($row['kind'] ?? '') === $kind) {
            $values[] = (string) ($row['value'] ?? '');
        }
    }
    return $values[$index] ?? '';
};
$bannerFile = (string) ($account['profile_banner'] ?? '');
$backgroundFile = (string) ($account['profile_background'] ?? '');
$bannerColorValue = (string) old('profile_banner_color', (string) ($account['profile_banner_color'] ?? ''));
$bannerBlur = (int) old('profile_banner_blur', (int) ($account['profile_banner_blur'] ?? 0));
$bannerDisabled = (string) old('profile_banner_disabled', (string) ((int) ($account['profile_banner_disabled'] ?? 0))) === '1';
$backgroundBlur = (int) old('profile_background_blur', (int) ($account['profile_background_blur'] ?? 0));
$cardOpacity = max(20, min(100, (int) old('profile_card_opacity', (int) ($account['profile_card_opacity'] ?? 100))));
$cardBlur = (string) old('profile_card_blur', (string) ((int) ($account['profile_card_blur'] ?? 1))) === '1';
?>
<form id="<?= e($formId) ?>" method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data" class="card-body form">
    <?= csrf_field() ?>
    <?php if ($saveAll): ?><input type="hidden" name="save_all" value="1"><?php endif; ?>
    <?php if ($nextPath !== ''): ?><input type="hidden" name="next" value="<?= e($nextPath) ?>"><?php endif; ?>

    <div class="field">
        <label for="display_name">Display name</label>
        <input id="display_name" name="display_name" type="text" value="<?= e(old('display_name', $account['display_name'])) ?>" maxlength="60" minlength="2" autocomplete="nickname" required>
        <?php if ($error = form_error('display_name')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint">Between 2 and 60 characters. Your email is always shown on your public profile.</p>
    </div>

    <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="<?= e(old('username', $account['username'] ?? '')) ?>" maxlength="30" pattern="[a-zA-Z][a-zA-Z0-9_]{2,29}" autocomplete="username" required data-handle-input data-handle-check="<?= e(url('/account/handle-check')) ?>">
        <?php if ($error = form_error('username')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint">Your public @handle. 3-30 letters, numbers, or underscores, starting with a letter.</p>
        <p class="field-hint" data-handle-status></p>
    </div>

    <?php if ($includeDescription): ?>
    <div class="field">
        <label for="description">Account description <span class="field-hint">optional</span></label>
        <textarea id="description" name="description" rows="3" maxlength="280" placeholder="A short line about you"><?= e(old('description', $account['description'] ?? '')) ?></textarea>
        <?php if ($error = form_error('description')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint">Up to 280 characters. Shown on your public profile and in the consent screen.</p>
    </div>
    <?php endif; ?>

    <div class="field">
        <span class="field-label">Contact</span>
        <p class="field-hint" style="margin-top: 0">Shown on your public profile instead of a single email. Leave a field empty to hide it. You can add up to two emails.</p>
        <div class="grid-2">
            <div class="field">
                <label for="contact_telegram">Telegram username</label>
                <input id="contact_telegram" name="contact_telegram" type="text" value="<?= e(old('contact_telegram', $contactValue('telegram'))) ?>" maxlength="32" placeholder="username">
                <?php if ($error = form_error('contact_telegram')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="contact_discord">Discord username</label>
                <input id="contact_discord" name="contact_discord" type="text" value="<?= e(old('contact_discord', $contactValue('discord'))) ?>" maxlength="32" placeholder="username">
                <?php if ($error = form_error('contact_discord')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="contact_steam">Steam ID <span class="field-hint">optional</span></label>
                <input id="contact_steam" name="contact_steam" type="text" value="<?= e(old('contact_steam', $contactValue('steam'))) ?>" maxlength="64" placeholder="SteamID64 or custom name">
                <?php if ($error = form_error('contact_steam')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="contact_email1">Email</label>
                <input id="contact_email1" name="contact_email1" type="email" value="<?= e(old('contact_email1', $contactValue('email', 0))) ?>" maxlength="254" placeholder="<?= e((string) $account['email']) ?>">
                <?php if ($error = form_error('contact_email1')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="contact_email2">Second email <span class="field-hint">optional</span></label>
                <input id="contact_email2" name="contact_email2" type="email" value="<?= e(old('contact_email2', $contactValue('email', 1))) ?>" maxlength="254">
                <?php if ($error = form_error('contact_email2')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="field">
        <span class="field-label">Profile banner</span>
        <?php if ($bannerFile !== ''): ?>
        <img class="profile-banner-preview" src="<?= e(url('/uploads/banners/' . rawurlencode($bannerFile))) ?>" alt="Current profile banner">
        <label class="check-row">
            <input type="checkbox" name="remove_banner" value="1">
            <span>Remove the banner image</span>
        </label>
        <?php endif; ?>
        <input id="profile_banner_file" name="profile_banner_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
        <?php if ($error = form_error('profile_banner_file')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <div class="color-row" style="margin-top: 10px">
            <input id="profile_banner_color" name="profile_banner_color" type="color" value="<?= e($bannerColorValue !== '' ? $bannerColorValue : '#2f5cff') ?>">
            <label class="field-hint" for="profile_banner_color">Fallback color when there is no image</label>
        </div>
        <p class="field-hint">Shown above your avatar on the public profile. JPG, PNG, WebP, or GIF under 2 MB.</p>
        <label class="check-row" style="margin-top: 10px">
            <input type="checkbox" name="profile_banner_disabled" value="1" <?= $bannerDisabled ? 'checked' : '' ?>>
            <span>Hide banner on your profile</span>
        </label>
    </div>

    <div class="field">
        <span class="field-label">Profile background</span>
        <?php if ($backgroundFile !== ''): ?>
        <img class="profile-banner-preview" src="<?= e(url('/uploads/banners/' . rawurlencode($backgroundFile))) ?>" alt="Current profile background">
        <label class="check-row">
            <input type="checkbox" name="remove_background" value="1">
            <span>Remove the background image</span>
        </label>
        <?php endif; ?>
        <input id="profile_background_file" name="profile_background_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
        <?php if ($error = form_error('profile_background_file')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint">Sits behind your whole public profile. A wide, darker image works best. JPG, PNG, WebP, or GIF under 2 MB.</p>
    </div>

    <div class="field">
        <label for="profile_banner_blur">Banner blur</label>
        <div class="range-row">
            <input id="profile_banner_blur" name="profile_banner_blur" type="range" min="0" max="20" step="1" value="<?= max(0, min(20, $bannerBlur)) ?>" data-blur-range>
            <output for="profile_banner_blur" data-blur-output><?= max(0, min(20, $bannerBlur)) ?>px</output>
        </div>
        <p class="field-hint">0 turns blur off. Softens a small or busy banner image.</p>
    </div>

    <div class="field">
        <label for="profile_background_blur">Page background blur</label>
        <div class="range-row">
            <input id="profile_background_blur" name="profile_background_blur" type="range" min="0" max="20" step="1" value="<?= max(0, min(20, $backgroundBlur)) ?>" data-blur-range>
            <output for="profile_background_blur" data-blur-output><?= max(0, min(20, $backgroundBlur)) ?>px</output>
        </div>
        <p class="field-hint">0 turns blur off. Applies only to the background image behind the profile.</p>
    </div>

    <div class="field">
        <label for="profile_card_opacity">Card transparency</label>
        <div class="range-row">
            <input id="profile_card_opacity" name="profile_card_opacity" type="range" min="20" max="100" step="5" value="<?= $cardOpacity ?>" data-opacity-range>
            <output for="profile_card_opacity" data-opacity-output><?= $cardOpacity ?>%</output>
        </div>
        <label class="check-row" style="margin-top: 10px">
            <input type="checkbox" name="profile_card_blur" value="1" <?= $cardBlur ? 'checked' : '' ?>>
            <span>Blur behind transparent cards</span>
        </label>
        <p class="field-hint">Transparency makes cards see-through. Blur softens the background behind them. Applies to your profile card and showcases.</p>
    </div>

    <div class="field">
        <span class="mono-label">Avatar</span>
        <div class="avatar-picker" role="radiogroup" aria-label="Avatar">
            <label class="avatar-option">
                <input type="radio" name="avatar" value="initials" <?= $selectedAvatar === 'initials' ? 'checked' : '' ?>>
                <span class="avatar-initials" data-avatar-preview<?= $avatarColor !== '' ? ' style="background: ' . e($avatarColor) . '"' : '' ?>><?= e(initials((string) $account['display_name'])) ?></span>
            </label>
            <label class="avatar-option avatar-option-emoji">
                <input type="radio" name="avatar" value="emoji" <?= $selectedAvatar === 'emoji' ? 'checked' : '' ?>>
                <span class="avatar avatar-emoji" data-avatar-color-target data-emoji-preview<?= $avatarColor !== '' ? ' style="background: ' . e($avatarColor) . '"' : '' ?>><?= e($selectedEmoji !== '' ? $selectedEmoji : '🙂') ?></span>
            </label>
            <?php if ($avatarType === 'upload'): ?>
            <label class="avatar-option">
                <input type="radio" name="avatar" value="upload" <?= $selectedAvatar === 'upload' ? 'checked' : '' ?>>
                <img class="avatar" src="<?= e(avatar_url($account)) ?>" alt="Current uploaded avatar" width="46" height="46">
            </label>
            <?php endif; ?>
        </div>
        <?php if ($error = form_error('avatar')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint">Pick a system emoji, or keep generated initials. The background color applies to both.</p>
    </div>

    <div class="field">
        <span class="field-label">Emoji</span>
        <div class="emoji-current">
            <span class="avatar avatar-emoji" data-avatar-color-target data-emoji-preview<?= $avatarColor !== '' ? ' style="background: ' . e($avatarColor) . '"' : '' ?>><?= e($selectedEmoji !== '' ? $selectedEmoji : '🙂') ?></span>
            <button class="btn btn-secondary" type="button" data-emoji-open><?= icon('grid') ?><span>Choose emoji</span></button>
            <input type="hidden" name="avatar_emoji" value="<?= e($selectedEmoji) ?>" data-emoji-input>
        </div>
        <p class="field-hint">Open the full emoji keyboard and pick any emoji. It becomes your avatar with the background color below.</p>
    </div>

    <dialog class="emoji-dialog" data-emoji-dialog aria-label="Choose an emoji">
        <div class="emoji-dialog-head">
            <strong>Choose an emoji</strong>
            <button class="icon-btn" type="button" data-emoji-close aria-label="Close emoji picker"><?= icon('x') ?></button>
        </div>
        <div class="emoji-tabs" role="tablist" aria-label="Emoji categories">
            <?php $firstGroup = true; foreach ($emojiGroups as $group => $items): ?>
            <button class="emoji-tab<?= $firstGroup ? ' is-active' : '' ?>" type="button" data-emoji-tab="<?= e($group) ?>" role="tab" aria-selected="<?= $firstGroup ? 'true' : 'false' ?>"><?= e($group) ?></button>
            <?php $firstGroup = false; endforeach; ?>
        </div>
        <div class="emoji-scroll" data-emoji-scroll>
            <?php $firstGroup = true; foreach ($emojiGroups as $group => $items): ?>
            <div class="emoji-group" data-emoji-group="<?= e($group) ?>"<?= $firstGroup ? '' : ' hidden' ?>>
                <p class="mono-label"><?= e($group) ?></p>
                <div class="emoji-grid">
                    <?php foreach ($items as $emoji): ?>
                    <button class="emoji-choice<?= $selectedEmoji === $emoji ? ' is-active' : '' ?>" type="button" data-emoji="<?= e($emoji) ?>" aria-label="Use <?= e($emoji) ?>"><?= e($emoji) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $firstGroup = false; endforeach; ?>
        </div>
    </dialog>

    <?php $uploadDays = (int) BBA\Core\Config::get('account.avatar_upload_days', 14); ?>
    <div class="field">
        <label for="avatar_file">Upload your own image</label>
        <?php if ($isAdmin || account_age_days($account) >= $uploadDays && level_ok($account, 'avatar')): ?>
        <input id="avatar_file" name="avatar_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-avatar-upload>
        <p class="field-hint">JPG, PNG, WebP, or GIF under 2 MB. Replacing it deletes the previous file. Uploading overrides the choice above.</p>
        <?php else: ?>
        <p class="field-hint">Image uploads unlock <?= $uploadDays ?> days after registration and at level <?= level_required('avatar') ?>. Emoji and initials work now.</p>
        <?php endif; ?>
        <?php if ($error = form_error('avatar_file')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
    </div>

    <div class="field">
        <label for="avatar_color">Avatar background</label>
        <div class="color-row">
            <input id="avatar_color" name="avatar_color" type="color" value="<?= e($colorValue) ?>" data-avatar-color>
            <label class="check-row">
                <input type="checkbox" name="use_default_color" value="1" <?= $avatarColor === '' ? 'checked' : '' ?>>
                <span>Use the default gradient</span>
            </label>
        </div>
        <?php if ($error = form_error('avatar_color')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
        <p class="field-hint">Applies to initials and emoji avatars. Uploaded photos keep their own background.</p>
    </div>

    <?php if ($showActions): ?>
    <div class="btn-row">
        <button class="btn btn-primary" type="submit"><?= icon('check') ?><span><?= e($saveLabel) ?></span></button>
        <?php if ($nextPath !== ''): ?><a class="btn btn-ghost" href="<?= e(url($nextPath)) ?>"><?= icon('x') ?><span>Cancel</span></a><?php endif; ?>
    </div>
    <?php endif; ?>
</form>
