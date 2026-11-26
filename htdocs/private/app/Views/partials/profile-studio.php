<?php
/**
 * Profile v2 studio editor. Settings fields join one `studio-settings` form with a
 * single Save button; content editors keep their own action-specific buttons and
 * post to existing validated AccountController actions via a safe `next` path.
 * Variables come from HomeController::profile(): $profile, $profilePath, $studioShowcases,
 * $studioBadges, $friends, $showcaseSlots, $slotCost, $mediaCost, $showcaseDays,
 * $showcasesAllowed, $teams.
 */
$profile = $profile ?? [];
$profilePath = $profilePath ?? '/u/' . (int) ($profile['id'] ?? 0);
$nextPath = $profilePath;
$studioShowcases = $studioShowcases ?? [];
$studioBadges = $studioBadges ?? [];
$friends = $friends ?? [];
$showcaseSlots = (int) ($showcaseSlots ?? \BBA\Models\Showcase::LIMIT);
$slotCost = (int) ($slotCost ?? \BBA\Models\Showcase::SLOT_COST);
$mediaCost = (int) ($mediaCost ?? \BBA\Models\Showcase::MEDIA_COST);
$showcaseDays = (int) ($showcaseDays ?? 30);
$showcasesAllowed = (bool) ($showcasesAllowed ?? false);
$points = (int) ($profile['points'] ?? 0);
$isPrivate = (int) ($profile['private_profile'] ?? 0) === 1;
$labels = \BBA\Models\Showcase::DEFAULT_TITLES + ['text' => 'Custom text'];
$reviewMax = \BBA\Models\Showcase::REVIEW_MAX;
?>
<div class="studio-editors" id="studio-editors">
    <div class="studio-save-bar">
        <button class="btn btn-primary" type="submit" form="studio-settings"><?= icon('check') ?><span>Save changes</span></button>
        <a class="btn btn-secondary" href="<?= e(url($profilePath . '?preview=1')) ?>"><?= icon('eye') ?><span>How does my profile look</span></a>
        <span class="field-hint">One button saves everything on this page.</span>
    </div>
    <details class="studio-editor" id="studio-details">
        <summary><?= icon('user') ?><span>Name, handle, description, avatar, contacts, banner &amp; background</span></summary>
        <p class="field-hint">Level gates: handle <?= level_required('handle') ?>, avatar <?= level_required('avatar') ?>, contacts <?= level_required('contacts') ?>, banner &amp; background <?= level_required('banner') ?>. Admins bypass every gate.</p>
        <?php
        $account = $profile;
        $formAction = url('/account/profile');
        $nextPath = $profilePath;
        $includeDescription = true;
        $formId = 'studio-settings';
        $saveAll = true;
        $saveLabel = 'Save changes';
        $showActions = false;
        include BBA_APP . '/Views/partials/profile-identity-form.php';
        ?>
    </details>

    <details class="studio-editor" id="studio-badges">
        <summary><?= icon('award') ?><span>Badge Earned — show or hide</span></summary>
        <?php if ($studioBadges === []): ?>
        <p class="field-hint">No badges yet. Earn one from a project or an admin grant.</p>
        <?php else: ?>
        <div class="form">
            <div class="grid-2">
                <?php foreach ($studioBadges as $badge): ?>
                <label class="check-row">
                    <input type="checkbox" form="studio-settings" name="visible_badges[]" value="<?= (int) $badge['id'] ?>" <?= (int) $badge['is_visible'] === 1 ? 'checked' : '' ?>>
                    <span><?= e((string) $badge['name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </details>

    <details class="studio-editor" id="studio-privacy">
        <summary><?= icon('lock') ?><span>Privacy &amp; visibility</span></summary>
        <div class="form">
            <label class="check-row"><input type="checkbox" form="studio-settings" name="private_profile" value="1" <?= $isPrivate ? 'checked' : '' ?>> <span>Private profile</span></label>
            <label class="check-row"><input type="checkbox" form="studio-settings" name="share_social" value="1" <?= (int) ($profile['share_social'] ?? 1) === 1 ? 'checked' : '' ?>> <span>Share linked provider usernames</span></label>
            <?php foreach (['admin', 'developer', 'tester', 'moderator'] as $role): ?>
            <label class="check-row"><input type="checkbox" form="studio-settings" name="show_role_<?= $role ?>" value="1" <?= (int) ($profile['show_role_' . $role] ?? 1) === 1 ? 'checked' : '' ?>> <span>Show <?= ucfirst($role) ?> role</span></label>
            <?php endforeach; ?>
            <p class="field-hint">A private profile hides everything except your avatar, banner, background, name and handle. Showcases switch off but are never deleted. Saved with the button above.</p>
        </div>
    </details>

    <details class="studio-editor" id="studio-showcases">
        <summary><?= icon('grid') ?><span>Showcases — edit, width, hide &amp; remove</span></summary>
        <?php if ($isPrivate): ?>
        <p class="field-hint">Showcases stay switched off while your profile is private. Turn privacy off to edit them.</p>
        <?php endif; ?>
        <?php if ($studioShowcases !== []): ?><input type="hidden" form="studio-settings" name="showcase_visibility_present" value="1"><div class="form" data-showcase-list><?php endif; ?>
        <?php foreach ($studioShowcases as $showcase): ?>
        <?php
        $type = (string) $showcase['type'];
        $showcaseId = (int) $showcase['id'];
        $width = \BBA\Models\Showcase::width($showcase);
        $scOpacity = \BBA\Models\Showcase::opacity($showcase);
        $isFixed = in_array($type, \BBA\Models\Showcase::FIXED_TYPES, true);
        $isTeam = $type === 'team';
        $editable = in_array($type, ['text', 'repos', 'links'], true);
        ?>
        <div class="studio-showcase">
            <div class="showcase-sort-row" draggable="true" data-showcase-sort data-showcase-id="<?= $showcaseId ?>">
                <input type="hidden" form="studio-settings" name="showcase_order[]" value="<?= $showcaseId ?>" data-showcase-order>
                <button class="showcase-drag" type="button" aria-label="Drag to reorder" title="Drag to reorder">⠿</button>
                <div class="studio-showcase-head">
                    <strong><?= e((string) $showcase['title']) ?></strong>
                    <span class="field-hint"><?= e($labels[$type] ?? ucfirst($type)) ?></span>
                </div>
            </div>
            <div class="btn-row">
                <label class="check-row">
                    <input type="checkbox" form="studio-settings" name="visible_showcases[]" value="<?= $showcaseId ?>" <?= (int) $showcase['is_visible'] === 1 ? 'checked' : '' ?>>
                    <span>Visible</span>
                </label>
                <div class="showcase-width-form" data-showcase-width-control>
                    <button class="showcase-resize" type="button" data-showcase-resize aria-label="Drag to resize" title="Drag to resize">↔</button>
                    <select form="studio-settings" name="showcase_width[<?= $showcaseId ?>]" aria-label="Width" data-showcase-width-select>
                        <?php foreach (\BBA\Models\Showcase::WIDTHS as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $width === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="showcase-width-label" data-showcase-width-label><?= e(ucfirst($width)) ?></span>
                </div>
                <label class="showcase-width-form" style="gap:6px">
                    <span class="field-hint" style="margin:0">Opacity</span>
                    <select form="studio-settings" name="showcase_opacity[<?= $showcaseId ?>]" aria-label="Showcase opacity">
                        <?php foreach ([100, 90, 80, 70, 60, 50, 40, 30, 20] as $op): ?>
                        <option value="<?= $op ?>" <?= $scOpacity === $op ? 'selected' : '' ?>><?= $op ?>%</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($isTeam): ?>
                <label class="check-row"><input type="checkbox" form="studio-settings" name="teams_include_subscribed" value="1" <?= (int) (\BBA\Models\Showcase::meta($showcase)['include_subscribed'] ?? 0) === 1 ? 'checked' : '' ?>> <span>Include teams I follow</span></label>
                <?php endif; ?>
                <?php if (!$isFixed): ?>
                <form method="post" action="<?= e(url('/account/showcases/' . $showcaseId . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                    <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Remove this showcase?"><?= icon('trash') ?><span>Remove</span></button>
                </form>
                <?php endif; ?>
            </div>
            <?php if ($editable): ?>
            <form method="post" action="<?= e(url('/account/showcases/' . $showcaseId . '/edit')) ?>" class="form studio-subform">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                <div class="field">
                    <label>Title <span class="field-hint">optional</span></label>
                    <input name="showcase_title" type="text" value="<?= e((string) $showcase['title']) ?>" maxlength="60">
                </div>
                <div class="field">
                    <label><?= $type === 'text' ? 'Text' : ($type === 'repos' ? 'Repository links' : 'Links') ?></label>
                    <textarea name="showcase_text" rows="3" maxlength="500"><?= e((string) ($showcase['body'] ?? '')) ?></textarea>
                    <p class="field-hint"><?= $type === 'repos' ? 'One full GitHub link per line, up to 5.' : ($type === 'links' ? 'One link per line as URL | label, up to 10.' : 'Up to 500 characters. @mentions become links.') ?></p>
                </div>
                <div class="btn-row"><button class="btn btn-sm btn-primary" type="submit"><?= icon('check') ?><span>Save text</span></button></div>
            </form>
            <?php endif; ?>
            <?php if ($type === 'bestfriends'): ?>
            <?php $picked = json_decode((string) ($showcase['body'] ?? ''), true); $picked = is_array($picked) ? array_map('intval', $picked) : []; ?>
            <form method="post" action="<?= e(url('/account/bestfriends')) ?>" class="form studio-subform">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                <span class="field-label">Best friends (up to 5)</span>
                <?php if ($friends === []): ?>
                <p class="field-hint">Add friends first.</p>
                <?php else: ?>
                <?php foreach ($friends as $friend): ?>
                <label class="check-row"><input type="checkbox" name="bestfriends[]" value="<?= (int) $friend['id'] ?>" <?= in_array((int) $friend['id'], $picked, true) ? 'checked' : '' ?>> <span><?= e((string) $friend['display_name']) ?></span></label>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="btn-row"><button class="btn btn-sm btn-primary" type="submit"><?= icon('check') ?><span>Save friends</span></button></div>
            </form>
            <?php endif; ?>
            <?php if ($type === 'reviews'): ?>
            <?php $reviews = \BBA\Models\Showcase::reviews($showcase); ?>
            <form method="post" action="<?= e(url('/account/showcases/' . $showcaseId . '/reviews')) ?>" class="form studio-subform">
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                <div class="field"><label>Project name</label><input name="review_project" type="text" maxlength="60" required></div>
                <div class="field"><label>Rating</label><select name="review_rating"><?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"><?= $s ?> stars</option><?php endfor; ?></select></div>
                <div class="field"><label>Review</label><textarea name="review_text" rows="2" maxlength="500" required></textarea></div>
                <div class="btn-row"><button class="btn btn-sm btn-primary" type="submit"><?= icon('plus') ?><span>Add review (max <?= $reviewMax ?>)</span></button></div>
            </form>
            <?php foreach ($reviews as $index => $review): ?>
            <div class="link-row">
                <div><strong><?= e((string) ($review['project'] ?? 'Project')) ?></strong><span class="list-sub"><?= str_repeat('★', (int) ($review['rating'] ?? 0)) ?> · <?= e(short_text((string) ($review['text'] ?? ''), 80)) ?></span></div>
                <form method="post" action="<?= e(url('/account/showcases/' . $showcaseId . '/reviews/' . $index . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($profilePath) ?>">
                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?><span>Delete</span></button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if ($studioShowcases !== []): ?></div><?php endif; ?>
        <p class="field-hint">Drag a row to move it up or down. Drag ↔ to resize; it snaps to Mini, Normal or Wide. One button above saves everything.</p>
    </details>

    <?php if ($showcasesAllowed && !$isPrivate): ?>
    <?php
    $ownedTypes = array_column($studioShowcases, 'type');
    $availableTypes = array_values(array_diff(\BBA\Models\Showcase::TYPES, $ownedTypes, \BBA\Models\Showcase::FIXED_TYPES, ['team']));
    // Team showcases are automatic and must not consume editable slots.
    $usedSlots = \BBA\Models\Showcase::count((int) ($profile['id'] ?? 0));
    $freeSlot = $usedSlots < $showcaseSlots;
    $typeGate = ['text' => 'text', 'media' => 'media', 'repos' => 'repos', 'links' => 'links', 'reviews' => 'reviews'];
    $requirements = [
        'text' => 'Level ' . level_required('text'),
        'projects' => 'No requirement',
        'media' => 'Level ' . level_required('media') . ' · ' . $mediaCost . ' points',
        'repos' => 'Level ' . level_required('repos'),
        'links' => 'Level ' . level_required('links'),
        'reviews' => 'Level ' . level_required('reviews'),
        'bestfriends' => 'Add friends first',
        'team' => 'Create a team first',
    ];
    ?>
    <?php if ($availableTypes !== []): ?>
    <details class="studio-editor" id="studio-add">
        <summary><?= icon('plus') ?><span>Add object</span></summary>
        <?php if ($freeSlot): ?>
        <p class="field-hint"><?= $usedSlots ?>/<?= $showcaseSlots ?> slots used. One free slot, plus slots bought with <?= $slotCost ?> points each. Showcases unlock after <?= $showcaseDays ?> days.</p>
        <form method="post" action="<?= e(url('/account/showcases')) ?>" class="form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($profilePath) ?>">
            <div class="field">
                <label for="studio_showcase_type">Type</label>
                <select id="studio_showcase_type" name="type" data-showcase-type>
                    <?php foreach ($availableTypes as $showcaseType): ?>
                    <?php
                    $gate = $typeGate[$showcaseType] ?? null;
                    $blocked = $gate !== null && !level_ok($profile, $gate);
                    $blocked = $blocked || ($showcaseType === 'media' && $points < $mediaCost);
                    ?>
                    <option value="<?= e($showcaseType) ?>"<?= $blocked ? ' disabled' : '' ?>><?= e($labels[$showcaseType] ?? ucfirst($showcaseType)) ?> · <?= e($requirements[$showcaseType] ?? '') ?><?= $blocked ? ' (locked)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" data-showcase-title><label>Title <span class="field-hint" data-showcase-title-hint>optional</span></label><input name="showcase_title" type="text" maxlength="60" data-showcase-title-input></div>
            <div class="field" data-showcase-body><label data-showcase-body-label>Text</label><textarea name="showcase_text" rows="3" maxlength="500" data-showcase-body-input></textarea><p class="field-hint" data-showcase-body-hint></p></div>
            <div class="field" data-showcase-media hidden><label>Image or video</label><input name="media_file" type="file" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" data-showcase-media-input></div>
            <div class="field" data-showcase-friends hidden><span class="field-label">Best friends (up to 5)</span><?php if ($friends === []): ?><p class="field-hint">Add friends first.</p><?php else: ?><?php foreach ($friends as $friend): ?><label class="check-row"><input type="checkbox" name="bestfriends[]" value="<?= (int) $friend['id'] ?>"> <span><?= e((string) $friend['display_name']) ?></span></label><?php endforeach; ?><?php endif; ?></div>
            <div class="field" data-showcase-reviews hidden><label>Project name</label><input name="review_project" type="text" maxlength="60" data-review-required><label>Review</label><textarea name="review_text" rows="2" maxlength="500" data-review-required></textarea><label>Rating</label><select name="review_rating"><?php for ($s = 5; $s >= 1; $s--): ?><option value="<?= $s ?>"><?= $s ?> stars</option><?php endfor; ?></select></div>
            <div class="btn-row"><button class="btn btn-primary btn-sm" type="submit"><?= icon('plus') ?><span>Add object</span></button></div>
        </form>
        <?php elseif ($showcaseSlots < \BBA\Models\Showcase::SLOT_MAX): ?>
        <div class="note"><?= icon('grid') ?><span><?= $usedSlots ?>/<?= $showcaseSlots ?> slots used. Buy another slot for <?= $slotCost ?> points to add an object, or remove one first.</span></div>
        <form method="post" action="<?= e(url('/account/showcases/slots')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($profilePath) ?>">
            <div class="btn-row"><button class="btn btn-secondary btn-sm" type="submit" <?= $points < $slotCost ? 'disabled' : '' ?>><?= icon('plus') ?><span>Buy showcase slot · <?= $slotCost ?> points</span></button></div>
            <p class="field-hint">You have <?= $points ?> points. <?= $points < $slotCost ? 'Get more by having your profile liked.' : '' ?></p>
        </form>
        <?php else: ?>
        <div class="note"><?= icon('grid') ?><span>All <?= $showcaseSlots ?> showcase slots are in use. Remove one to add another.</span></div>
        <?php endif; ?>
    </details>
    <?php endif; ?>
    <?php endif; ?>
</div>
