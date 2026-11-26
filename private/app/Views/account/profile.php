<?php
$account = $account ?? [];
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
$hasPassword = (int) ($account['password_set'] ?? 1) === 1;
$isAdmin = (int) ($account['is_admin'] ?? 0) === 1;
$isTester = (int) ($account['is_tester'] ?? 0) === 1;
$isModerator = (int) ($account['is_moderator'] ?? 0) === 1;
$isSuper = $isSuper ?? false;
$testerEligible = $testerEligible ?? false;
$testerDays = $testerDays ?? 30;
$testerDaysLeft = $testerDaysLeft ?? 0;
$testerRequested = $testerRequested ?? false;
$adminDays = $adminDays ?? 90;
$adminDaysLeft = $adminDaysLeft ?? 0;
$adminEligible = $adminEligible ?? false;
$emojiGroups = [
    'Smileys' => ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥸', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🫣', '🤭', '🫢', '🤫', '🤥', '😶', '😐', '😑', '😬', '🫠', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕'],
    'Animals' => ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐻‍❄️', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🪱', '🐛', '🦋', '🐌', '🐞', '🐜', '🪰', '🪲', '🪳', '🦟', '🦗', '🕷️', '🕸️', '🦂', '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞', '🦀', '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈', '🐊', '🐅', '🐆', '🦓', '🦍', '🦧', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘', '🦬', '🐃', '🐂', '🐄', '🐎', '🐖', '🐏', '🐑', '🦙', '🐐', '🦌', '🐕', '🐩', '🦮', '🐈', '🪶', '🐓', '🦃', '🦤', '🦚', '🦜', '🦢', '🦩', '🕊️', '🐇', '🦝', '🦨', '🦡', '🦫', '🦦', '🦥', '🐁', '🐀', '🐿️', '🦔'],
    'Food' => ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🫑', '🌽', '🥕', '🫒', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇', '🥓', '🥩', '🍗', '🍖', '🦴', '🌭', '🍔', '🍟', '🍕', '🫓', '🥪', '🥙', '🧆', '🌮', '🌯', '🫔', '🥗', '🥘', '🫕', '🥫', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤', '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🥧', '🧁', '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍿', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛', '🍼', '☕', '🫖', '🍵', '🧃', '🥤', '🧋', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃', '🍸', '🍹', '🧉', '🍾'],
    'Activity' => ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '🤾', '🏌️', '🏇', '🧘', '🏄', '🏊', '🤽', '🚣', '🧗', '🚵', '🚴', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🏵️', '🎗️', '🎫', '🎟️', '🎪', '🤹', '🎭', '🩰', '🎨', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🪘', '🎷', '🎺', '🪗', '🎸', '🪕', '🎻', '🎲', '♟️', '🎯', '🎳', '🎮', '🎰', '🧩'],
    'Travel' => ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽', '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋', '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️', '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥', '🚏', '🗺️', '🗿', '🗽', '🗼', '🏰', '🏯', '🏟️', '🎡', '🎢', '🎠', '⛲', '⛱️', '🏖️', '🏝️', '🏜️', '🌋', '⛰️', '🏔️', '🗻', '🏕️', '⛺', '🛖', '🏠', '🏡', '🏘️', '🏚️', '🏗️', '🏭', '🏢', '🏬', '🏣', '🏤', '🏥', '🏦', '🏨', '🏪', '🏫', '🏩', '💒', '🏛️', '⛪', '🕌', '🕍', '🛕', '🕋', '⛩️'],
    'Objects' => ['⌚', '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '📹', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🪫', '🔌', '💡', '🔦', '🕯️', '🪔', '🧯', '🛢️', '💸', '💵', '💴', '💶', '💷', '🪙', '💰', '💳', '🪪', '💎', '⚖️', '🪜', '🧰', '🪛', '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪚', '🔩', '⚙️', '🪤', '🧱', '⛓️', '🧲', '🔫', '💣', '🧨', '🪓', '🔪', '🗡️', '⚔️', '🛡️', '🚬', '⚰️', '🪦', '⚱️', '🏺', '🔮', '📿', '🧿', '💈', '⚗️', '🔭', '🔬', '🕳️', '🩹', '🩺', '🩻', '💊', '💉', '🩸', '🧬', '🦠', '🧫', '🧪', '🌡️', '🧹', '🪠', '🧺', '🧻', '🚽', '🚰', '🚿', '🛁', '🛀', '🧼', '🪥', '🪒', '🧽', '🪣', '🧴', '🛎️', '🔑', '🗝️', '🚪', '🪑', '🛋️', '🛏️', '🛌', '🧸', '🪆', '🖼️', '🪞', '🪟', '🛍️', '🛒', '🎁', '🎈', '🎏', '🎀', '🪄', '🪅', '🎊', '🎉', '🎎', '🏮', '🎐', '🧧', '✉️', '📩', '📨', '📧', '💌', '📥', '📤', '📦', '🏷️', '🪧', '📪', '📫', '📬', '📭', '📮', '📯', '📜', '📃', '📄', '📑', '🧾', '📊', '📈', '📉', '🗒️', '🗓️', '📆', '📅', '🗑️', '📇', '🗃️', '🗳️', '🗄️', '📋', '📁', '📂', '🗂️', '🗞️', '📰', '📓', '📔', '📒', '📕', '📗', '📘', '📙', '📚', '📖', '🔖', '🧷', '🔗', '📎', '🖇️', '📐', '📏', '🧮', '📌', '📍', '✂️', '🖊️', '🖋️', '✒️', '🖌️', '🖍️', '📝', '✏️', '🔍', '🔎', '🔏', '🔐', '🔒', '🔓'],
    'Symbols' => ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️', '📴', '📳', '🈶', '🈚', '🈸', '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈹', '🈲', '🅰️', '🅱️', '🆎', '🆑', '🅾️', '🆘', '❌', '⭕', '🛑', '⛔', '📛', '🚫', '💯', '💢', '♨️', '🚷', '🚯', '🚳', '🚱', '🔞', '📵', '🚭', '❗', '❕', '❓', '❔', '‼️', '⁉️', '🔅', '🔆', '〽️', '⚠️', '🚸', '🔱', '⚜️', '🔰', '♻️', '✅', '🈯', '💹', '❇️', '✳️', '❎', '🌐', '💠', 'Ⓜ️', '🌀', '💤', '🏧', '🚾', '♿', '🅿️', '🛗', '🈳', '🈂️', '🛂', '🛃', '🛄', '🛅', '🚹', '🚺', '🚼', '⚧️', '🚻', '🚮', '🎦', '📶', '🈁', '🔣', 'ℹ️', '🔤', '🔡', '🔠', '🆖', '🆗', '🆙', '🆒', '🆕', '🆓', '0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟', '🔢', '#️⃣', '*️⃣', '⏏️', '▶️', '⏸️', '⏯️', '⏹️', '⏺️', '⏭️', '⏮️', '⏩', '⏪', '⏫', '⏬', '◀️', '🔼', '🔽', '➡️', '⬅️', '⬆️', '⬇️', '↗️', '↘️', '↙️', '↖️', '↕️', '↔️', '↩️', '↪️', '⤴️', '⤵️', '🔀', '🔁', '🔂', '🔄', '🔃', '🎵', '🎶', '➕', '➖', '➗', '✖️', '🟰', '♾️', '💲', '💱', '™️', '©️', '®️', '👁️‍🗨️', '🔚', '🔙', '🔛', '🔝', '🔜', '〰️', '➰', '➿', '✔️', '☑️', '🔘', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤', '🔺', '🔻', '🔸', '🔹', '🔶', '🔷', '🔳', '🔲', '▪️', '▫️', '◾', '◽', '◼️', '◻️', '🟥', '🟧', '🟨', '🟩', '🟦', '🟪', '⬛', '⬜', '🟫'],
];
$selectedEmoji = (string) old('avatar_emoji', $currentEmoji);
$selectedAvatar = (string) old('avatar', $currentAvatar);
$betaFeatures = array_filter(explode(',', (string) ($account['beta_features'] ?? '')));
$badges = $badges ?? [];
$friends = $friends ?? [];
$showcases = $showcases ?? [];
$showcaseSlots = (int) ($showcaseSlots ?? 3);
$slotCost = (int) ($slotCost ?? \BBA\Models\Showcase::SLOT_COST);
$mediaCost = (int) ($mediaCost ?? \BBA\Models\Showcase::MEDIA_COST);
$points = (int) ($account['points'] ?? 0);
$level = level_info($account);
$showcasesAllowed = $showcasesAllowed ?? false;
$showcaseDays = (int) ($showcaseDays ?? 30);
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
$showcaseLabels = [
    'text' => 'Custom text',
    'projects' => 'My projects',
    'badges' => 'Badge Earned',
    'media' => 'Media',
    'repos' => 'GitHub repositories',
    'links' => 'Links',
    'reviews' => 'Project reviews',
    'friends' => 'Friends',
    'bestfriends' => 'Best friends',
];
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Profile</h1>
        <p class="page-sub">How your name, description, and avatar appear on your public page and to projects you authorize.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(account_url($account)) ?>"><?= icon('eye') ?><span>How my profile looks</span></a>
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<section class="card">
    <div class="card-body">
        <div class="profile-head">
            <img class="avatar avatar-lg" src="<?= e(avatar_url($account)) ?>" alt="Current avatar" width="72" height="72" data-avatar-color-target<?= avatar_style($account) ?>>
            <div>
                <div class="profile-name"><?= e($account['display_name']) ?> <span class="badge badge-accent has-tip" data-tooltip="<?= $level['next_xp'] === null ? 'Maximum level reached' : $level['xp'] . ' / ' . $level['next_xp'] . ' XP' ?>" tabindex="0"><?= e($level['label']) ?></span></div>
                <div class="profile-mail">@<?= e($account['username'] ?? '') ?> · <?= e($account['email']) ?></div>
                <div class="list-sub" style="margin-top: 4px">Account #<?= (int) $account['id'] ?> · created <?= e(format_date($account['created_at'])) ?></div>
            </div>
        </div>
    </div>
</section>

<section class="card" id="media">
    <div class="card-head">
        <div>
            <h2>Display name and avatar</h2>
            <p>Shown to projects after you approve access.</p>
        </div>
    </div>
    <form id="profile-settings" method="post" action="<?= e(url('/account/profile')) ?>" enctype="multipart/form-data" class="card-body form">
        <?= csrf_field() ?>
        <input type="hidden" name="save_all" value="1">

        <div class="field">
            <label for="display_name">Display name</label>
            <input id="display_name" name="display_name" type="text" value="<?= e(old('display_name', $account['display_name'])) ?>" maxlength="60" minlength="2" autocomplete="nickname" required>
            <?php if ($error = form_error('display_name')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <p class="field-hint">Between 2 and 60 characters. Your email is always shown on your public profile.</p>
        </div>

        <div class="field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="<?= e(old('username', $account['username'] ?? '')) ?>" maxlength="30" pattern="[a-zA-Z][a-zA-Z0-9_]{2,29}" autocomplete="username" required>
            <?php if ($error = form_error('username')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <p class="field-hint">Your public @handle. 3-30 letters, numbers, or underscores, starting with a letter.</p>
        </div>

        <div class="field">
            <label for="description">Account description <span class="field-hint">optional</span></label>
            <textarea id="description" name="description" rows="3" maxlength="280" placeholder="A short line about you"><?= e(old('description', $account['description'] ?? '')) ?></textarea>
            <?php if ($error = form_error('description')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <p class="field-hint">Up to 280 characters. Shown on your public profile and in the consent screen.</p>
        </div>

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
            <?php if ($isAdmin || account_age_days($account) >= $uploadDays): ?>
            <input id="avatar_file" name="avatar_file" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-avatar-upload>
            <p class="field-hint">JPG, PNG, WebP, or GIF under 2 MB. Replacing it deletes the previous file. Uploading overrides the choice above.</p>
            <?php else: ?>
            <p class="field-hint">Image uploads unlock <?= $uploadDays ?> days after registration (<?= max(0, $uploadDays - account_age_days($account)) ?> days left). Emoji and initials work now.</p>
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

        <div class="btn-row">
            <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Save changes</span></button>
        </div>
    </form>
</section>

<?php if (!$isAdmin): ?>
<section class="card" id="tester">
    <div class="card-head">
        <div>
            <h2>Tester access</h2>
            <?php if ($isTester): ?>
            <p>Your account has tester access. Choose the beta features you want to try.</p>
            <?php elseif ($testerEligible): ?>
            <p>Your account is old enough to apply. Testers get early access to unfinished features.</p>
            <?php else: ?>
            <p>Accounts can apply for tester access <?= (int) $testerDays ?> days after registration. You have <?= (int) $testerDaysLeft ?> days left.</p>
            <?php endif; ?>
        </div>
        <span class="badge <?= $isTester ? 'badge-tester' : 'badge-ok' ?>"><?= icon('shield') ?><?= $isTester ? 'Tester' : ($testerEligible ? 'Eligible' : 'Locked') ?></span>
    </div>
    <?php if ($isTester): ?>
    <div class="card-body"><p class="field-hint" style="margin-top: 0">No beta features are available right now.</p></div>
    <?php elseif ($testerEligible && ($testerRequested ?? false)): ?>
    <div class="card-body"><div class="note"><?= icon('check') ?><span>Your application is open. A moderator will review it <a href="<?= e(url('/account/tickets/' . (int) ($testerTicketId ?? 0))) ?>">here</a>. You can cancel it at any time.</span></div></div>
    <?php elseif ($testerEligible): ?>
    <form method="post" action="<?= e(url('/account/tickets/tester')) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="tester_note">Why do you want to test? <span class="field-hint">optional</span></label>
            <textarea id="tester_note" name="note" rows="2" maxlength="500" autocomplete="off"></textarea>
        </div>
        <div class="btn-row"><button class="btn btn-secondary" type="submit"><?= icon('shield') ?><span>Apply for tester access</span></button></div>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="card" id="roles">
    <div class="card-head">
        <div>
            <h2>Role upgrades</h2>
            <p>Tester → Moderator → Administrator. Moderators review applications in your account ticket; only the Super Account approves administrator applications.</p>
        </div>
        <span class="stat-icon"><?= icon('shield') ?></span>
    </div>
    <div class="card-body">
        <div class="list">
            <div class="list-row">
                <div class="list-main">
                    <span class="list-title">1. Tester</span>
                    <span class="list-sub">Early access to unfinished features. Requires level <?= level_required('tester') ?> and <?= (int) $testerDays ?> days.</span>
                </div>
                <?php if ($isAdmin): ?>
                <span class="badge badge-ok"><?= $isTester ? 'Granted' : 'Included' ?></span>
                <?php elseif ($isTester): ?>
                <span class="badge badge-ok">Granted</span>
                <?php elseif ($testerRequested ?? false): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/account/tickets/' . (int) ($testerTicketId ?? 0))) ?>">Open ticket</a>
                <?php elseif ($testerEligible && level_ok($account, 'tester')): ?>
                <a class="btn btn-sm btn-secondary" href="#tester">Apply below</a>
                <?php else: ?>
                <span class="badge badge-muted">Level <?= level_required('tester') ?> · <?= (int) $testerDaysLeft ?> days left</span>
                <?php endif; ?>
            </div>
            <div class="list-row">
                <div class="list-main">
                    <span class="list-title">2. Moderator</span>
                    <span class="list-sub">Reviews reports and applications. Appointed by an administrator after the Tester stage.</span>
                </div>
                <?php if ($isAdmin): ?>
                <span class="badge badge-ok"><?= $isModerator ? 'Granted' : 'Included' ?></span>
                <?php elseif ($isModerator): ?>
                <span class="badge badge-ok">Granted</span>
                <?php elseif ($isTester): ?>
                <span class="badge badge-muted">Appointment only</span>
                <?php else: ?>
                <span class="badge badge-muted">Requires Tester</span>
                <?php endif; ?>
            </div>
            <div class="list-row">
                <div class="list-main">
                    <span class="list-title">3. Administrator</span>
                    <span class="list-sub">Manage accounts, projects and moderation. Requires level <?= level_required('admin') ?> and <?= (int) $adminDays ?> days.</span>
                </div>
                <?php if ($isAdmin): ?>
                <span class="badge badge-ok"><?= $isSuper ? 'Super Account' : 'Granted' ?></span>
                <?php elseif ($adminRequested ?? false): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/account/tickets/' . (int) ($adminTicketId ?? 0))) ?>">Open ticket</a>
                <?php elseif ($adminEligible && level_ok($account, 'admin')): ?>
                <form method="post" action="<?= e(url('/account/tickets/admin')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-secondary" type="submit">Apply</button>
                </form>
                <?php else: ?>
                <span class="badge badge-muted">Level <?= level_required('admin') ?> · <?= (int) $adminDaysLeft ?> days left</span>
                <?php endif; ?>
            </div>
            <div class="list-row">
                <div class="list-main">
                    <a class="list-title" href="<?= e(url('/developer')) ?>">Developer</a>
                    <span class="list-sub">Additional role: create projects and OAuth clients. Requires level <?= level_required('developer') ?>.</span>
                </div>
                <?php if ((int) ($account['is_developer'] ?? 0) === 1): ?>
                <span class="badge badge-ok">Granted</span>
                <?php elseif ($developerRequested ?? false): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('/account/tickets/' . (int) ($developerTicketId ?? 0))) ?>">Open ticket</a>
                <?php elseif (level_ok($account, 'developer')): ?>
                <form method="post" action="<?= e(url('/account/tickets/developer')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-secondary" type="submit">Apply</button>
                </form>
                <?php else: ?>
                <span class="badge badge-muted">Level <?= level_required('developer') ?> required</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Email address</h2>
            <p>Used to sign in. Changing it requires your password.</p>
        </div>
        <span class="badge badge-ok"><?= icon('mail') ?>Primary</span>
    </div>
    <div class="card-body">
        <?php if (!$hasPassword): ?>
        <div class="note note-warn">
            <?= icon('alert') ?>
            <span>Set a password in <a href="<?= e(url('/account/security')) ?>">Security</a> before changing your email address.</span>
        </div>
        <?php else: ?>
        <form method="post" action="<?= e(url('/account/email')) ?>" class="form">
            <?= csrf_field() ?>

            <div class="field">
                <label for="email">New email</label>
                <input id="email" name="email" type="email" value="<?= e(old('email', $account['email'])) ?>" autocomplete="email" maxlength="254" required>
                <?php if ($error = form_error('email')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="email_password">Current password</label>
                <div class="input-wrap">
                    <input id="email_password" name="password" type="password" autocomplete="current-password" required data-password>
                    <button class="input-action" type="button" data-toggle-password aria-label="Show password"><?= icon('eye') ?></button>
                </div>
                <?php if ($error = form_error('password')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>

            <div class="btn-row">
                <button class="btn btn-secondary" type="submit"><?= icon('mail') ?><span>Update email</span></button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<section class="card" id="privacy">
    <div class="card-head">
        <div>
            <h2>Privacy</h2>
            <p>Choose what appears on your public profile and what projects receive.</p>
        </div>
        <span class="stat-icon"><?= icon('lock') ?></span>
    </div>
    <div class="card-body form">
        <label class="check-row">
            <input type="checkbox" form="profile-settings" name="private_profile" value="1" <?= (int) ($account['private_profile'] ?? 0) === 1 ? 'checked' : '' ?>>
            <span>Private profile — maximum privacy</span>
        </label>
        <p class="field-hint">Visitors see only your avatar, banner, background, display name and handle. Friends also see what is listed below. Showcases are switched off while private, but nothing is deleted.</p>
        <label class="check-row">
            <input type="checkbox" form="profile-settings" name="share_social" value="1" <?= (int) ($account['share_social'] ?? 1) === 1 ? 'checked' : '' ?>>
            <span>Share linked provider usernames</span>
        </label>
        <p class="field-hint">
            When enabled, <code>/oauth/userinfo</code> includes a <code>social</code> object such as
            <code>{"github":"octocat"}</code>. Each linked provider also has its own checkbox in
            <a href="<?= e(url('/account/connections?tab=social')) ?>">Connections</a>, so you can keep this on and still hide a single provider.
            Your email, display name, and avatar are always shared because apps need them to show who signed in.
        </p>
        <div class="field">
            <span class="field-label">Roles shown on your public profile</span>
            <label class="check-row">
                <input type="checkbox" form="profile-settings" name="show_role_admin" value="1" <?= (int) ($account['show_role_admin'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Show the Admin role</span>
            </label>
            <label class="check-row">
                <input type="checkbox" form="profile-settings" name="show_role_developer" value="1" <?= (int) ($account['show_role_developer'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Show the Developer role</span>
            </label>
            <label class="check-row">
                <input type="checkbox" form="profile-settings" name="show_role_tester" value="1" <?= (int) ($account['show_role_tester'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Show the Tester role</span>
            </label>
            <label class="check-row">
                <input type="checkbox" form="profile-settings" name="show_role_moderator" value="1" <?= (int) ($account['show_role_moderator'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Show the Moderator role</span>
            </label>
        </div>
        <p class="field-hint">Saved with the button above.</p>
    </div>
</section>

<section class="card" id="badges">
    <div class="card-head">
        <div>
            <h2>Badges</h2>
            <p>Badges come from actions or from the site admin. Hide any badge you do not want on your public profile.</p>
        </div>
        <span class="badge"><?= count($badges) ?> earned</span>
    </div>
    <?php if ($badges === []): ?>
    <div class="card-body">
        <p class="page-sub" style="margin: 0">No badges yet. Creating a project or keeping your account active can earn one.</p>
    </div>
    <?php else: ?>
    <div class="card-body form">
        <div class="grid-2">
            <?php foreach ($badges as $badge): ?>
            <label class="check-row">
                <input type="checkbox" form="profile-settings" name="visible_badges[]" value="<?= (int) $badge['id'] ?>" <?= (int) $badge['is_visible'] === 1 ? 'checked' : '' ?>>
                <span>
                    <span class="badge <?= e($badge['style']) ?>"><?= badge_mark($badge) ?><?= e($badge['name']) ?></span>
                    <span class="list-sub"><?= e((string) ($badge['description'] ?? '')) ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <p class="field-hint">Saved with the button above.</p>
    </div>
    <?php endif; ?>
</section>

<section class="card" id="showcases">
    <div class="card-head">
        <div>
            <h2>Showcases</h2>
            <p>Small blocks on your public profile: projects, badges, media, GitHub repositories, links, or friends.</p>
        </div>
        <div class="btn-row">
            <span class="badge"><?= count($showcases) ?>/<?= $showcaseSlots ?> slots</span>
            <span class="badge badge-accent"><?= icon('heart') ?><?= $points ?> points</span>
        </div>
    </div>
    <div class="card-body form">
        <p class="field-hint" style="margin-top: 0">Each profile like earns <?= \BBA\Models\ProfileLike::POINTS ?> points, and each accepted friendship earns <?= \BBA\Models\Friend::POINTS ?> points. One extra showcase slot costs <?= $slotCost ?> points. A Media showcase costs <?= $mediaCost ?> points each time and is not refunded when removed.</p>

        <?php if (!$showcasesAllowed): ?>
        <div class="note note-warn">
            <?= icon('lock') ?>
            <span>Showcases unlock <?= $showcaseDays ?> days after registration (<?= max(0, $showcaseDays - account_age_days($account)) ?> days left).</span>
        </div>
        <?php else: ?>

        <?php if ($showcases !== []): ?>
        <input type="hidden" form="profile-settings" name="showcase_visibility_present" value="1">
        <div class="form" data-showcase-list>
            <?php foreach ($showcases as $showcase): ?>
            <div class="showcase-sort-row" draggable="true" data-showcase-sort data-showcase-id="<?= (int) $showcase['id'] ?>">
                <input type="hidden" form="profile-settings" name="showcase_order[]" value="<?= (int) $showcase['id'] ?>" data-showcase-order>
                <button class="showcase-drag" type="button" aria-label="Drag to reorder" title="Drag to reorder">⠿</button>
                <label class="check-row">
                    <input type="checkbox" form="profile-settings" name="visible_showcases[]" value="<?= (int) $showcase['id'] ?>" <?= (int) $showcase['is_visible'] === 1 ? 'checked' : '' ?>>
                    <span><strong><?= e($showcase['title']) ?></strong> <span class="field-hint"><?= e($showcaseLabels[(string) $showcase['type']] ?? ucfirst((string) $showcase['type'])) ?></span></span>
                </label>
            </div>
            <?php endforeach; ?>
            <p class="field-hint">Drag a row to move it up or down. Drag the corner handle on a showcase to resize it; it snaps to Mini, Normal or Wide. Saved with the button above.</p>
        </div>

        <?php foreach ($showcases as $showcase): ?>
        <?php
        $isFixed = in_array((string) $showcase['type'], \BBA\Models\Showcase::FIXED_TYPES, true);
        $showcaseType = (string) $showcase['type'];
        $showcaseReviews = $showcaseType === 'reviews' ? \BBA\Models\Showcase::reviews($showcase) : [];
        $showcaseWidth = \BBA\Models\Showcase::width($showcase);
        $showcaseOpacity = \BBA\Models\Showcase::opacity($showcase);
        ?>
        <div class="link-row" data-showcase-id="<?= (int) $showcase['id'] ?>">
            <div>
                <strong><?= e($showcase['title']) ?></strong>
                <span class="list-sub"><?= e($showcaseLabels[$showcaseType] ?? ucfirst($showcaseType)) ?><?php if (in_array($showcaseType, ['text', 'media', 'repos', 'links'], true) && trim((string) ($showcase['body'] ?? '')) !== ''): ?> · <?= e(short_text((string) $showcase['body'], 80)) ?><?php endif; ?></span>
            </div>
            <div class="showcase-row-actions">
                <div class="showcase-width-form" data-showcase-width-control>
                    <button class="showcase-resize" type="button" data-showcase-resize aria-label="Drag to resize" title="Drag to resize">↔</button>
                    <select form="profile-settings" name="showcase_width[<?= (int) $showcase['id'] ?>]" aria-label="Showcase width" data-showcase-width-select>
                        <?php foreach (\BBA\Models\Showcase::WIDTHS as $widthKey => $widthLabel): ?>
                        <option value="<?= e($widthKey) ?>" <?= $showcaseWidth === $widthKey ? 'selected' : '' ?>><?= e($widthLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="showcase-width-label" data-showcase-width-label><?= e(ucfirst($showcaseWidth)) ?></span>
                </div>
                <label class="showcase-width-form" style="gap:6px">
                    <span class="field-hint" style="margin:0">Opacity</span>
                    <select form="profile-settings" name="showcase_opacity[<?= (int) $showcase['id'] ?>]" aria-label="Showcase opacity">
                        <?php foreach ([100, 90, 80, 70, 60, 50, 40, 30, 20] as $op): ?>
                        <option value="<?= $op ?>" <?= $showcaseOpacity === $op ? 'selected' : '' ?>><?= $op ?>%</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($isFixed): ?>
                <span class="field-hint">Always on this profile</span>
                <?php else: ?>
                <form method="post" action="<?= e(url('/account/showcases/' . (int) $showcase['id'] . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-ghost" type="submit" data-confirm="<?= $showcaseType === 'media' ? 'Remove this Media showcase? The ' . $mediaCost . ' points you spent will not be refunded.' : 'Remove this showcase?' ?>"><?= icon('trash') ?><span>Remove</span></button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($showcaseType === 'reviews'): ?>
        <div class="showcase-reviews-admin">
            <?php if ($showcaseReviews === []): ?>
            <p class="field-hint">No reviews yet. Add up to <?= \BBA\Models\Showcase::REVIEW_MAX ?>.</p>
            <?php else: ?>
            <?php foreach ($showcaseReviews as $reviewIndex => $review): ?>
            <div class="link-row">
                <div>
                    <strong><?= e((string) ($review['project'] ?? 'Project')) ?></strong>
                    <span class="list-sub"><?= str_repeat('★', (int) ($review['rating'] ?? 0)) ?><?= str_repeat('☆', max(0, 5 - (int) ($review['rating'] ?? 0))) ?> · <?= e(short_text((string) ($review['text'] ?? ''), 90)) ?></span>
                </div>
                <form method="post" action="<?= e(url('/account/showcases/' . (int) $showcase['id'] . '/reviews/' . $reviewIndex . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Remove this review?"><?= icon('trash') ?><span>Delete</span></button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php if (count($showcaseReviews) < \BBA\Models\Showcase::REVIEW_MAX): ?>
            <form method="post" action="<?= e(url('/account/showcases/' . (int) $showcase['id'] . '/reviews')) ?>" class="form">
                <?= csrf_field() ?>
                <div class="grid-2">
                    <div class="field">
                        <label for="review_project_<?= (int) $showcase['id'] ?>">Project name</label>
                        <input id="review_project_<?= (int) $showcase['id'] ?>" name="review_project" type="text" value="<?= e(old('review_project')) ?>" maxlength="60" autocomplete="off" required>
                        <?php if ($error = form_error('review_project')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="review_rating_<?= (int) $showcase['id'] ?>">Rating</label>
                        <select id="review_rating_<?= (int) $showcase['id'] ?>" name="review_rating">
                            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <option value="<?= $stars ?>" <?= (int) old('review_rating', 5) === $stars ? 'selected' : '' ?>><?= $stars ?> <?= $stars === 1 ? 'star' : 'stars' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="review_url_<?= (int) $showcase['id'] ?>">Project link <span class="field-hint">optional</span></label>
                    <input id="review_url_<?= (int) $showcase['id'] ?>" name="review_url" type="url" value="<?= e(old('review_url')) ?>" maxlength="300" autocomplete="off" placeholder="https://example.com">
                    <?php if ($error = form_error('review_url')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="review_text_<?= (int) $showcase['id'] ?>">Review</label>
                    <textarea id="review_text_<?= (int) $showcase['id'] ?>" name="review_text" rows="2" maxlength="500" autocomplete="off" required><?= e(old('review_text')) ?></textarea>
                    <?php if ($error = form_error('review_text')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <?php if ($error = form_error('review_rating')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                <div class="btn-row"><button class="btn btn-sm btn-secondary" type="submit"><?= icon('plus') ?><span>Add review</span></button></div>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php
        $ownedTypes = array_column($showcases, 'type');
        $availableTypes = array_values(array_diff(\BBA\Models\Showcase::TYPES, $ownedTypes, \BBA\Models\Showcase::FIXED_TYPES, ['team']));
        $freeSlots = count($showcases) < $showcaseSlots;
        ?>
        <?php if ($freeSlots && $availableTypes !== []): ?>
        <form method="post" action="<?= e(url('/account/showcases')) ?>" class="form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="field">
                <label for="showcase_type">Type</label>
                <select id="showcase_type" name="type" data-showcase-type>
                    <?php foreach ($availableTypes as $showcaseType): ?>
                    <?php $isMedia = $showcaseType === 'media'; ?>
                    <option value="<?= e($showcaseType) ?>" <?= old('type', 'text') === $showcaseType ? 'selected' : '' ?><?= $isMedia && $points < $mediaCost ? ' disabled' : '' ?>><?= e($showcaseLabels[$showcaseType]) ?><?= $isMedia ? ' · ' . $mediaCost . ' points' : '' ?><?= $isMedia && $points < $mediaCost ? ' (not enough points)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" data-showcase-title>
                <label for="showcase_title">Title <span class="field-hint" data-showcase-title-hint>optional</span></label>
                <input id="showcase_title" name="showcase_title" type="text" value="<?= e(old('showcase_title')) ?>" maxlength="60" autocomplete="off" placeholder="What I build" data-showcase-title-input>
                <?php if ($error = form_error('title')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field" data-showcase-body>
                <label for="showcase_body" data-showcase-body-label>Text</label>
                <textarea id="showcase_body" name="showcase_text" rows="3" maxlength="500" autocomplete="off" data-showcase-body-input placeholder="Games I play, tools I use, or a project note."><?= e(old('showcase_text')) ?></textarea>
                <p class="field-hint" data-showcase-body-hint>Shown as a text card. @mentions become links.</p>
                <?php if ($error = form_error('body')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field" data-showcase-media hidden>
                <label for="showcase_media">Image or video</label>
                <input id="showcase_media" name="media_file" type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" data-showcase-media-input>
                <p class="field-hint">Uploads cost <?= $mediaCost ?> points. Images or MP4/WebM video up to 20 MB.</p>
                <?php if ($error = form_error('media')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="field" data-showcase-friends hidden>
                <span class="field-label">Best friends (up to 5)</span>
                <?php if ($friends === []): ?>
                <p class="field-hint">Add friends first.</p>
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
                        <label for="review_project_new">Project name</label>
                        <input id="review_project_new" name="review_project" type="text" value="<?= e(old('review_project')) ?>" maxlength="60" autocomplete="off" data-review-required>
                        <?php if ($error = form_error('review_project')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="review_rating_new">Rating</label>
                        <select id="review_rating_new" name="review_rating">
                            <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <option value="<?= $stars ?>" <?= (int) old('review_rating', 5) === $stars ? 'selected' : '' ?>><?= $stars ?> <?= $stars === 1 ? 'star' : 'stars' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="field">
                    <label for="review_url_new">Project link <span class="field-hint">optional</span></label>
                    <input id="review_url_new" name="review_url" type="url" value="<?= e(old('review_url')) ?>" maxlength="300" autocomplete="off" placeholder="https://example.com">
                    <?php if ($error = form_error('review_url')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label for="review_text_new">Review</label>
                    <textarea id="review_text_new" name="review_text" rows="2" maxlength="500" autocomplete="off" data-review-required><?= e(old('review_text')) ?></textarea>
                    <?php if ($error = form_error('review_text')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <?php if ($error = form_error('review_rating')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <?php if ($error = form_error('form')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <div class="btn-row">
                <button class="btn btn-secondary" type="submit"><?= icon('plus') ?><span>Add showcase</span></button>
            </div>
        </form>
        <?php else: ?>
        <?php if ($availableTypes === []): ?>
        <div class="note"><?= icon('grid') ?><span>Every showcase type is already on your profile.</span></div>
        <?php endif; ?>
        <?php if (!$freeSlots): ?>
        <?php $atSlotMax = $showcaseSlots >= \BBA\Models\Showcase::SLOT_MAX; ?>
        <div class="note">
            <?= icon('grid') ?>
            <span><?= $atSlotMax ? 'All showcase slots are in use. Remove one to add another.' : 'All showcase slots are in use. Remove one, or buy another slot for ' . $slotCost . ' points.' ?></span>
        </div>
        <?php if (!$atSlotMax): ?>
        <form method="post" action="<?= e(url('/account/showcases/slots')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="btn-row">
                <button class="btn btn-secondary" type="submit" <?= $points < $slotCost ? 'disabled' : '' ?>><?= icon('plus') ?><span>Buy showcase slot · <?= $slotCost ?> points</span></button>
            </div>
            <p class="field-hint">You have <?= $points ?> points. <?= $points < $slotCost ? 'Get more by having your profile liked.' : '' ?></p>
        </form>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
