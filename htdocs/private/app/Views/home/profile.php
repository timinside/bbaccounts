<?php
use BBA\Core\Auth;

$profile = $profile ?? [];
$social = $social ?? [];
$badges = $badges ?? [];
$showcases = $showcases ?? [];
$projects = $projects ?? [];
$teams = $teams ?? [];
$viewer = Auth::user();
$isOwner = $viewer !== null && (int) $viewer['id'] === (int) ($profile['id'] ?? 0);
$canReport = $viewer !== null && ($isOwner || (int) ($viewer['is_admin'] ?? 0) === 1 || (int) ($viewer['is_moderator'] ?? 0) === 1);
$roles = profile_roles($profile);
$description = trim((string) ($profile['description'] ?? ''));
$warningText = trim((string) ($profile['warning_text'] ?? ''));
$handle = trim((string) ($profile['username'] ?? ''));
$likeCount = (int) ($likeCount ?? 0);
$liked = (bool) ($liked ?? false);
$canLike = (bool) ($canLike ?? false);
$comments = $comments ?? [];
$likers = $likers ?? [];
$friends = $friends ?? [];
$friendState = $friendState ?? null;
$friendRequestId = (int) ($friendRequestId ?? 0);
$contacts = array_values(array_filter(
    profile_contacts($profile),
    static fn (array $row): bool => (string) $row['kind'] !== 'other'
));
if (isset($social['github']) && trim((string) $social['github']) !== '') {
    $contacts[] = ['kind' => 'github', 'value' => (string) $social['github']];
    unset($social['github']);
}
$privateView = (bool) ($privateView ?? false);
$fullView = (bool) ($fullView ?? true);
if (!$fullView) {
    $roles = [];
    $description = '';
    $warningText = '';
    $contacts = [];
}
$banner = trim((string) ($profile['profile_banner'] ?? ''));
$bannerColor = trim((string) ($profile['profile_banner_color'] ?? ''));
$bannerDisabled = (int) ($profile['profile_banner_disabled'] ?? 0) === 1;
$background = trim((string) ($profile['profile_background'] ?? ''));
$cardOpacity = max(20, min(100, (int) ($profile['profile_card_opacity'] ?? 100)));
$cardBlur = (int) ($profile['profile_card_blur'] ?? 1) === 1;
$cardStyle = ' data-card-opacity="' . $cardOpacity . '" data-card-blur="' . ($cardBlur ? '1' : '0') . '" style="--card-alpha:' . $cardOpacity . '%"';
$bannerBlur = max(0, min(20, (int) ($profile['profile_banner_blur'] ?? 0)));
$backgroundBlur = max(0, min(20, (int) ($profile['profile_background_blur'] ?? 0)));
$profilePath = '/u/' . rawurlencode($handle !== '' ? $handle : (string) (int) ($profile['id'] ?? 0));
$studioAllowed = $isOwner && (bool) ($studioAllowed ?? false);
$preview = $isOwner && (bool) ($preview ?? false);
$studio = $studioAllowed && !$preview;
?>
<?php if ($background !== ''): ?>
<div class="profile-bg<?= $backgroundBlur > 0 ? ' has-blur' : '' ?>"<?= $backgroundBlur > 0 ? ' style="--profile-blur: ' . $backgroundBlur . 'px"' : '' ?>>
<div class="profile-bg-layer" style="background-image: url('<?= e(url('/uploads/banners/' . rawurlencode($background))) ?>')"></div>
<?php endif; ?>


<?php if ($studio): ?>
<?php include BBA_APP . '/Views/partials/profile-studio.php'; ?>
<?php endif; ?>

<div class="profile-top">
<section class="card profile-card<?= $studio ? ' profile-studio' : '' ?>"<?= $cardStyle ?>>

    <?php if (!$bannerDisabled && ($banner !== '' || preg_match('/^#[0-9a-f]{6}$/i', $bannerColor) === 1)): ?>
    <?php
    $bannerStyles = [];
    if ($banner === '' && $bannerColor !== '') {
        $bannerStyles[] = 'background: ' . $bannerColor;
    }
    if ($bannerBlur > 0) {
        $bannerStyles[] = '--profile-blur: ' . $bannerBlur . 'px';
    }
    ?>
    <div class="profile-banner<?= $bannerBlur > 0 ? ' has-blur' : '' ?>"<?= $bannerStyles === [] ? '' : ' style="' . e(implode('; ', $bannerStyles)) . '"' ?>>
        <?php if ($banner !== ''): ?><img src="<?= e(url('/uploads/banners/' . rawurlencode($banner))) ?>" alt="" loading="lazy"><?php endif; ?>
        <?php if ($studio): ?><button class="studio-edit-button" type="button" data-studio-edit="studio-details" data-tooltip="Edit banner &amp; background" aria-label="Edit banner and background"><?= icon('pencil') ?></button><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="card-body">
        <?php if ($studioAllowed && $preview): ?>
        <div class="btn-row profile-actions">
            <a class="btn btn-primary" href="<?= e(url($profilePath)) ?>"><?= icon('pencil') ?><span>Edit profile</span></a>
        </div>
        <?php endif; ?>
        <?php if ($canLike || $privateView): ?>
        <div class="btn-row profile-actions">
            <?php if ($canLike): ?>
            <?php if ($friendState === 'friends'): ?>
            <form method="post" action="<?= e(url('/friends/' . (int) $profile['id'] . '/remove')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                <button class="btn btn-secondary" type="submit"><?= icon('check') ?><span>Friends</span></button>
            </form>
            <?php elseif ($friendState === 'sent'): ?>
            <span class="badge badge-muted"><?= icon('users') ?>Request sent</span>
            <?php elseif ($friendState === 'incoming'): ?>
            <form method="post" action="<?= e(url('/friends/' . $friendRequestId . '/accept')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                <button class="btn btn-primary" type="submit"><?= icon('check') ?><span>Accept friend</span></button>
            </form>
            <form method="post" action="<?= e(url('/friends/' . $friendRequestId . '/decline')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                <button class="btn btn-ghost" type="submit"><?= icon('x') ?><span>Decline</span></button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= e(url('/u/' . (int) $profile['id'] . '/friend')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit"><?= icon('users') ?><span>Add friend</span></button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url($profilePath . '/like')) ?>">
                <?= csrf_field() ?>
                <button class="btn <?= $liked ? 'btn-primary' : 'btn-secondary' ?>" type="submit">
                    <?= icon('heart') ?><span><?= $liked ? 'Liked' : 'Like' ?></span>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($privateView): ?>
            <span class="badge badge-muted has-tip" data-tooltip="This profile is private" tabindex="0"><?= icon('lock') ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="profile-head">
            <div class="studio-avatar-wrap">
                <img class="avatar avatar-xl" src="<?= e(avatar_url($profile)) ?>" alt="" width="96" height="96"<?= avatar_style($profile) ?>>
                <?php if ($studio): ?><button class="studio-edit-button" type="button" data-studio-edit="studio-details" data-tooltip="Change avatar" aria-label="Change avatar"><?= icon('pencil') ?></button><?php endif; ?>
            </div>
            <div>
                <div class="profile-name"><?= e($profile['display_name'] ?? '') ?><?php if ($studio): ?><button class="studio-edit-button" type="button" data-studio-edit="studio-details" data-tooltip="Edit name" aria-label="Edit name"><?= icon('pencil') ?></button><?php endif; ?><?php if ($fullView): ?><?= verification_mark('profile', (int) ($profile['id'] ?? 0)) ?><?= account_status_mark($profile) ?><?php foreach ($roles as $role): ?><span class="role-icon <?= e($role['class']) ?> has-tip" data-tooltip="<?= e($role['name']) ?>" tabindex="0"><?= icon($role['icon']) ?></span><?php endforeach; ?><?php endif; ?></div>
                <?php if ($handle !== ''): ?>
                <div class="profile-handle">@<?= e($handle) ?><?php if ($studio): ?><button class="studio-edit-button" type="button" data-studio-edit="studio-details" data-tooltip="Edit handle (level <?= level_required('handle') ?>)" aria-label="Edit handle"><?= icon('pencil') ?></button><?php endif; ?></div>
                <?php endif; ?>
                <div class="profile-handle">ID: <?= (int) ($profile['id'] ?? 0) ?></div>
                <?php if ($fullView): ?>
                <?php $level = level_info($profile); ?>
                <div class="btn-row" style="margin-top:8px;gap:8px">
                    <button class="badge badge-accent" type="button" data-dialog-open="profile-level"><?= e($level['label']) ?></button>
                    <button class="badge profile-like-count <?= $liked ? 'badge-accent' : '' ?>" type="button" data-dialog-open="profile-likers"><?= icon('heart') ?><span><?= $likeCount ?> <?= $likeCount === 1 ? 'like' : 'likes' ?></span></button>
                </div>
                <?php endif; ?>
                <?php if ($contacts !== []): ?>
                <div class="profile-contacts">
                    <?php foreach ($contacts as $contact): ?>
                    <?php
                    $kind = (string) $contact['kind'];
                    $value = (string) $contact['value'];
                    $href = contact_href($kind, $value);
                    $text = $kind === 'steam' ? 'Steam' : $value;
                    ?>
                    <?php if ($href !== null): ?>
                    <a class="provider-chip" href="<?= e($href) ?>"<?= str_starts_with($href, 'mailto:') ? '' : ' target="_blank" rel="noopener noreferrer nofollow"' ?>><?= contact_icon($kind) ?><span><?= e($text) ?></span></a>
                    <?php else: ?>
                    <span class="provider-chip"><?= contact_icon($kind) ?><span><?= e($text) ?></span></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($fullView): ?>
                <p class="list-sub" style="margin-top: 8px">Joined <?= e(format_date($profile['created_at'] ?? null)) ?> · <?= e(last_online_label($profile['last_login_at'] ?? null)) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$studio && $description !== ''): ?>
        <p class="profile-description"><?= render_mentions($description) ?></p>
        <?php endif; ?>

        <?php if ($warningText !== ''): ?>
        <div class="profile-warning"><?= icon('alert') ?><span><?= e($warningText) ?></span></div>
        <?php endif; ?>

    </div>
</section>


</div>

<?php if ($showcases !== []): ?>
<div class="showcase-grid">
    <?php foreach ($showcases as $showcase): ?>
    <?php
    $type = (string) $showcase['type'];
    $body = trim((string) ($showcase['body'] ?? ''));
    $width = \BBA\Models\Showcase::width($showcase);
    $wide = $width === 'wide';
    $scOpacity = \BBA\Models\Showcase::opacity($showcase);
    $scStyle = $scOpacity < 100
        ? ' data-card-opacity="' . $scOpacity . '" data-card-blur="' . ($cardBlur ? '1' : '0') . '" style="--card-alpha:' . $scOpacity . '%"'
        : $cardStyle;
    $subtitle = match ($type) {
        'projects' => 'Public projects',
        'badges' => 'Earned badges',
        'media' => 'Media',
        'repos' => 'GitHub repositories',
        'links' => 'Links',
        'reviews' => 'Reviews of other projects',
        'friends' => 'All friends',
        'bestfriends' => 'Picked friends',
        'contact' => 'Contact',
        'team' => 'Teams and your roles',
        default => 'Showcase',
    };
    ?>
    <section class="card showcase-card showcase-card-<?= e($width) ?>"<?= $scStyle ?>>
        <div class="card-head">
            <div>
                <h2><?= e($showcase['title']) ?></h2>
                <p><?= e($subtitle) ?></p>
            </div>
            <?php if ($studio): ?><button class="studio-edit-button" type="button" data-studio-edit="studio-showcases" data-tooltip="Edit this showcase" aria-label="Edit showcase"><?= icon('pencil') ?></button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($type === 'projects'): ?>
                <?php if ($projects === []): ?>
                <p class="list-sub">No active public projects yet.</p>
                <?php else: ?>
                <div class="showcase-projects">
                    <?php foreach ($projects as $project): ?>
                    <a class="showcase-project" href="<?= e(url('/p/' . $project['slug'])) ?>">
                        <?= project_icon($project, 'project-icon project-icon-sm', true) ?>
                        <span>
                            <strong><?= e($project['name']) ?></strong>
                            <?php if ($wide): ?><span class="list-sub"><?= e((string) ($project['description'] ?? '')) ?></span><?php endif; ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php elseif ($type === 'badges'): ?>
                <?php if ($badges === []): ?>
                <p class="list-sub">No badges earned yet.</p>
                <?php else: ?>
                <div class="badge-medals">
                    <?php foreach ($badges as $badge): ?>
                    <?= badge_medal($badge, $wide) ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php elseif ($type === 'media'): ?>
                <?php
                $mediaFile = basename($body);
                $mediaPath = preg_match('~^[a-f0-9]{24}\.(jpg|png|webp|gif|mp4|webm)$~', $mediaFile) === 1
                    ? '/uploads/media/' . $mediaFile : '';
                $legacyMedia = preg_match('~^https?://~i', $body) === 1;
                ?>
                <?php if ($legacyMedia && preg_match('~\.(mp4|webm|ogv)(\?|#|$)~i', $body) === 1): ?>
                <video class="showcase-media" controls preload="metadata" src="<?= e($body) ?>"></video>
                <?php elseif ($legacyMedia && preg_match('~(youtube\.com|youtu\.be|vimeo\.com)~i', $body) === 1): ?>
                <div class="showcase-media-embed">
                    <iframe src="<?= e(media_embed_url($body)) ?>" title="<?= e($showcase['title']) ?>" loading="lazy" allowfullscreen referrerpolicy="no-referrer"></iframe>
                </div>
                <?php elseif ($legacyMedia): ?>
                <a class="showcase-media" href="<?= e($body) ?>" target="_blank" rel="noopener noreferrer nofollow">
                    <img src="<?= e($body) ?>" alt="<?= e($showcase['title']) ?>" loading="lazy">
                </a>
                <?php elseif ($mediaPath === ''): ?>
                <p class="list-sub">No media uploaded yet.</p>
                <?php elseif (preg_match('~\.(mp4|webm)$~', $mediaFile) === 1): ?>
                <video class="showcase-media" controls preload="metadata" src="<?= e(url($mediaPath)) ?>"></video>
                <?php else: ?>
                <div class="showcase-media"><img src="<?= e(url($mediaPath)) ?>" alt="<?= e($showcase['title']) ?>" loading="lazy"></div>
                <?php endif; ?>
            <?php elseif ($type === 'repos'): ?>
                <div class="showcase-repos">
                    <?php foreach (array_filter(array_map('trim', preg_split('/\R/', $body) ?: [])) as $line): ?>
                    <?php
                    [$repo, $note] = array_pad(explode('|', $line, 2), 2, '');
                    $repo = trim($repo);
                    $note = trim($note);
                    ?>
                    <a class="showcase-repo" href="<?= e($repo) ?>" target="_blank" rel="noopener noreferrer nofollow">
                        <span class="showcase-repo-head"><?= icon('github') ?><strong><?= e(preg_replace('~^https://github\.com/~i', '', rtrim($repo, '/'))) ?></strong></span>
                        <?php if ($note !== ''): ?><span class="list-sub"><?= e($note) ?></span><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'links'): ?>
                <div class="showcase-repos">
                    <?php foreach (array_filter(array_map('trim', preg_split('/\R/', $body) ?: [])) as $line): ?>
                    <?php
                    [$linkUrl, $linkLabel] = array_pad(explode('|', $line, 2), 2, '');
                    $linkUrl = trim($linkUrl);
                    $linkLabel = trim($linkLabel);
                    if (!filter_var($linkUrl, FILTER_VALIDATE_URL) || preg_match('~^https?://~i', $linkUrl) !== 1) {
                        continue;
                    }
                    ?>
                    <a class="showcase-repo" href="<?= e($linkUrl) ?>" target="_blank" rel="noopener noreferrer nofollow">
                        <span class="showcase-repo-head"><?= icon('link') ?><strong><?= e($linkLabel !== '' ? $linkLabel : $linkUrl) ?></strong></span>
                        <span class="list-sub"><?= e($linkUrl) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'reviews'): ?>
                <?php $reviews = \BBA\Models\Showcase::reviews($showcase); ?>
                <?php if ($reviews === []): ?>
                <p class="list-sub">No reviews yet.</p>
                <?php else: ?>
                <div class="showcase-reviews">
                    <?php foreach ($reviews as $review): ?>
                    <?php $rating = max(0, min(5, (int) ($review['rating'] ?? 0))); ?>
                    <article class="showcase-review">
                        <div class="showcase-review-head">
                            <strong><?= e((string) ($review['project'] ?? 'Project')) ?></strong>
                            <span class="review-stars" aria-label="<?= $rating ?> out of 5 stars"><?= str_repeat('★', $rating) ?><?= str_repeat('☆', 5 - $rating) ?></span>
                        </div>
                        <p class="showcase-review-text"><?= e((string) ($review['text'] ?? '')) ?></p>
                        <?php if (trim((string) ($review['url'] ?? '')) !== ''): ?>
                        <a class="text-link showcase-review-link" href="<?= e((string) $review['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= icon('link') ?><span>Visit project</span></a>
                        <?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            <?php elseif ($type === 'friends' || $type === 'bestfriends'): ?>
                <?php
                if ($type === 'bestfriends') {
                    $picked = json_decode($body, true);
                    $picked = is_array($picked) ? array_map('intval', $picked) : [];
                    $list = array_values(array_filter($friends, static fn (array $f): bool => in_array((int) $f['id'], $picked, true)));
                    $list = array_slice($list, 0, 5);
                } else {
                    $list = array_slice($friends, 0, 20);
                }
                $shown = array_slice($list, 0, 5);
                $dialogId = 'friends-showcase-' . (int) $showcase['id'];
                ?>
                <?php if ($shown === []): ?>
                <p class="list-sub">No friends to show yet.</p>
                <?php else: ?>
                <div class="friend-grid">
                    <?php foreach ($shown as $friend): ?>
                    <a class="friend-chip" href="<?= e(account_url($friend)) ?>">
                        <img class="avatar avatar-sm" src="<?= e(avatar_url($friend)) ?>" alt="" width="32" height="32"<?= avatar_style($friend) ?>>
                        <span><strong><?= e((string) $friend['display_name']) ?></strong>
                            <?php if (trim((string) ($friend['username'] ?? '')) !== ''): ?><span class="list-sub">@<?= e((string) $friend['username']) ?></span><?php endif; ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if (count($list) > 5): ?>
                <div class="btn-row">
                    <button class="btn btn-sm btn-ghost" type="button" data-dialog-open="<?= e($dialogId) ?>">Load more...</button>
                </div>
                <dialog class="dialog" id="<?= e($dialogId) ?>" aria-label="All friends">
                    <div class="dialog-head">
                        <div><h2><?= e($showcase['title']) ?></h2><p><?= count($list) ?> <?= count($list) === 1 ? 'friend' : 'friends' ?></p></div>
                        <button class="icon-btn" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button>
                    </div>
                    <div class="list">
                        <?php foreach ($list as $friend): ?>
                        <div class="list-row">
                            <img class="avatar avatar-sm" src="<?= e(avatar_url($friend)) ?>" alt="" width="36" height="36"<?= avatar_style($friend) ?>>
                            <div class="list-main">
                                <a class="list-title" href="<?= e(account_url($friend)) ?>"><?= e((string) $friend['display_name']) ?></a>
                                <span class="list-sub"><?= trim((string) ($friend['username'] ?? '')) !== '' ? '@' . e((string) $friend['username']) : 'Friend' ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </dialog>
                <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($type === 'team'): ?>
                <?php
                $teamData = $teams[(int) $showcase['id']] ?? ['rows' => [], 'include_subscribed' => false];
                $teamRows = (array) ($teamData['rows'] ?? []);
                $teamDialogId = 'teams-showcase-' . (int) $showcase['id'];
                ?>
                <?php if ($teamRows === []): ?>
                <p class="list-sub">No teams to show yet.</p>
                <?php else: ?>
                <div class="list">
                    <?php foreach (array_slice($teamRows, 0, 5) as $teamRow): ?>
                    <?php include BBA_APP . '/Views/partials/team-row.php'; ?>
                    <?php endforeach; ?>
                </div>
                <?php if (count($teamRows) > 5): ?>
                <div class="btn-row">
                    <button class="btn btn-sm btn-ghost" type="button" data-dialog-open="<?= e($teamDialogId) ?>">Load more...</button>
                </div>
                <dialog class="dialog" id="<?= e($teamDialogId) ?>" aria-label="All teams">
                    <div class="dialog-head">
                        <div>
                            <h2><?= e((string) $showcase['title']) ?></h2>
                            <p><?= count($teamRows) ?> <?= count($teamRows) === 1 ? 'team' : 'teams' ?></p>
                        </div>
                        <button class="icon-btn" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button>
                    </div>
                    <div class="list">
                        <?php foreach ($teamRows as $teamRow): ?>
                        <?php include BBA_APP . '/Views/partials/team-row.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </dialog>
                <?php endif; ?>
                <?php endif; ?>
            <?php elseif ($type === 'contact'): ?>
                <div class="profile-contacts">
                    <?php foreach ($contacts as $contact): ?>
                    <?php
                    $kind = (string) $contact['kind'];
                    $value = (string) $contact['value'];
                    $href = contact_href($kind, $value);
                    $text = $kind === 'steam' ? 'Steam' : $value;
                    ?>
                    <?php if ($href !== null): ?>
                    <a class="provider-chip" href="<?= e($href) ?>"<?= str_starts_with($href, 'mailto:') ? '' : ' target="_blank" rel="noopener noreferrer nofollow"' ?>><?= contact_icon($kind) ?><span><?= e($text) ?></span></a>
                    <?php else: ?>
                    <span class="provider-chip"><?= contact_icon($kind) ?><span><?= e($text) ?></span></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
            <p class="showcase-body"><?= render_mentions($body) ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($fullView): ?>
<?php $levelInfo = level_info($profile); $lvl = (int) $levelInfo['level']; $xp = (int) $levelInfo['xp']; $nextXp = $levelInfo['next_xp']; $progress = (int) $levelInfo['progress']; ?>
<dialog class="dialog" id="profile-level" aria-label="Level">
    <div class="dialog-head">
        <div>
            <h2><?= e($levelInfo['label']) ?></h2>
            <p>Your bbaccounts level and XP progress.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button>
    </div>
    <div class="level-popup">
        <div class="level-progress-wrap">
            <div class="level-progress-labels">
                <span class="level-progress-start">Level <?= $lvl ?></span>
                <span class="level-progress-end"><?= $nextXp === null ? 'MAX' : 'Level ' . ($lvl + 1) ?></span>
            </div>
            <div class="level-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>" aria-label="Level progress">
                <div class="level-progress-fill" style="width:<?= $progress ?>%"></div>
            </div>
        </div>
        <div class="level-xp"><?= $xp ?> XP<?= $nextXp !== null ? ' / ' . $nextXp . ' XP' : ' · max level reached' ?></div>
        <div class="level-info">
            <p><strong>How levels work</strong></p>
            <p>100 XP = 1 level. Maximum level is 99.</p>
            <p>You earn XP from profile activity and rewards: likes, friends, badges, referrals, and teams.</p>
        </div>
    </div>
</dialog>
<dialog class="dialog" id="profile-likers" aria-label="Liked by">
    <div class="dialog-head">
        <div>
            <h2>Liked by</h2>
            <p><?= $likeCount ?> <?= $likeCount === 1 ? 'account' : 'accounts' ?> liked this profile.</p>
        </div>
        <button class="icon-btn" type="button" data-dialog-close aria-label="Close"><?= icon('x') ?></button>
    </div>
    <?php if ($likers === []): ?>
    <p class="field-hint" style="padding:0 4px">No likes yet.</p>
    <?php else: ?>
    <div class="list" data-likers-list>
        <?php foreach ($likers as $i => $liker): ?>
        <a class="list-row" href="<?= e(account_url($liker)) ?>" data-likers-item data-page="<?= intdiv($i, 10) + 1 ?>"<?= $i >= 10 ? ' hidden' : '' ?>>
            <img class="avatar avatar-sm" src="<?= e(avatar_url($liker)) ?>" alt="" width="32" height="32"<?= avatar_style($liker) ?>>
            <div class="list-main">
                <span class="list-title"><?= e((string) $liker['display_name']) ?></span>
                <?php if (trim((string) ($liker['username'] ?? '')) !== ''): ?><span class="list-sub">@<?= e((string) $liker['username']) ?></span><?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php $likerPages = max(1, (int) ceil(count($likers) / 10)); ?>
    <?php if ($likerPages > 1): ?>
    <div class="btn-row" style="justify-content:space-between;margin-top:14px" data-likers-pager data-pages="<?= $likerPages ?>">
        <button class="btn btn-sm btn-ghost" type="button" data-likers-prev><?= icon('arrow-left') ?><span>Prev</span></button>
        <span class="field-hint" data-likers-status>1 / <?= $likerPages ?></span>
        <button class="btn btn-sm btn-ghost" type="button" data-likers-next><span>Next</span><?= icon('arrow-right') ?></button>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</dialog>
<?php
$commentAction = '/u/' . rawurlencode($handle !== '' ? $handle : (string) (int) ($profile['id'] ?? 0)) . '/comment';
$commentNext = '/u/' . rawurlencode($handle !== '' ? $handle : (string) (int) ($profile['id'] ?? 0));
require BBA_APP . '/Views/partials/comments.php';
?>
<?php endif; ?>
<?php if ($background !== ''): ?>
</div>
<?php endif; ?>
