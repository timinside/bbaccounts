<?php
use BBA\Core\Request;
use BBA\Models\Team;

$owned = $owned ?? [];
$related = $related ?? [];
$allTeams = $allTeams ?? [];
$cost = (int) ($cost ?? Team::COST);
$requiredLevel = (int) ($requiredLevel ?? 0);
$selectedId = (int) Request::query('team', 0);
$selected = null;
foreach (array_merge($owned, $related) as $candidate) {
    if ((int) $candidate['id'] === $selectedId) {
        $selected = $candidate;
    }
}
if ($selected === null && $owned !== []) {
    $selected = $owned[0];
}
$followedIds = [];
foreach (array_merge($owned, $related) as $candidate) {
    $followedIds[(int) $candidate['id']] = true;
}
$discover = array_values(array_filter($allTeams, static fn (array $team): bool => !isset($followedIds[(int) $team['id']])));
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Teams</h1>
        <p class="page-sub">Group projects under a team, assign roles, and let people follow announcements and team discussions.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<div class="stack">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>Create a team</h2>
                <p>Requires level <?= $requiredLevel ?> and <?= $cost ?> points. Your profile gets a Teams showcase listing your teams.</p>
            </div>
            <span class="stat-icon"><?= icon('users') ?></span>
        </div>
        <form method="post" action="<?= e(url('/account/teams')) ?>" class="card-body form">
            <?= csrf_field() ?>
            <label class="field">
                <span class="field-label">Team name</span>
                <input type="text" name="name" maxlength="80" required placeholder="My studio">
            </label>
            <label class="field">
                <span class="field-label">Description <span class="field-hint">optional</span></span>
                <textarea name="description" rows="2" maxlength="280"></textarea>
            </label>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit" <?= !($canCreate ?? false) ? 'disabled' : '' ?>>
                    <?= icon('plus') ?><span>Create team · <?= $cost ?> points</span>
                </button>
            </div>
            <?php if (!($canCreate ?? false)): ?>
            <p class="field-hint">You need level <?= $requiredLevel ?> and at least <?= $cost ?> points. Points are spent once.</p>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($owned !== []): ?>
    <section class="card">
        <div class="card-head"><div><h2>Your teams</h2><p>Teams you own.</p></div></div>
        <div class="card-body">
            <div class="btn-row">
                <?php foreach ($owned as $team): ?>
                <a class="btn <?= (int) $team['id'] === (int) ($selected['id'] ?? 0) ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/account/teams?team=' . (int) $team['id'])) ?>"><?= e((string) $team['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="card">
        <div class="card-head"><div><h2>Discover teams</h2><p>Follow a team to get its announcements and its discussion chat in bbchat.</p></div></div>
        <div class="card-body">
            <?php if ($discover === []): ?>
            <p class="list-sub">You already follow every team.</p>
            <?php else: ?>
            <div class="list">
                <?php foreach ($discover as $team): ?>
                <div class="list-row">
                    <div class="list-main">
                        <a class="list-title" href="<?= e(url('/account/teams?team=' . (int) $team['id'])) ?>"><?= e((string) $team['name']) ?></a>
                        <span class="list-sub"><?= e((string) ($team['description'] ?? '')) ?> · <?= (int) $team['subscribers'] ?> subscriber(s)</span>
                    </div>
                    <form method="post" action="<?= e(url('/account/teams/' . (int) $team['id'] . '/subscribe')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-secondary" type="submit">Subscribe</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($selected !== null): ?>
    <?php
    $teamId = (int) $selected['id'];
    $members = Team::members($teamId);
    $announcements = Team::announcements($teamId);
    $role = Team::roleOf($teamId, (int) ($account['id'] ?? 0));
    $canManage = in_array($role, ['Owner', 'Co-owner'], true);
    ?>
    <section class="card">
        <div class="card-head">
            <div>
                <h2><?= e((string) $selected['name']) ?></h2>
                <p><?= e((string) ($selected['description'] ?? '')) ?></p>
            </div>
            <span class="badge badge-accent"><?= e((string) ($role ?? 'subscriber')) ?></span>
        </div>
        <div class="card-body">
            <div class="list">
                <?php foreach ($members as $member): ?>
                <div class="list-row">
                    <img class="avatar avatar-sm" src="<?= e(avatar_url($member)) ?>" alt="" width="36" height="36"<?= avatar_style($member) ?>>
                    <div class="list-main">
                        <a class="list-title" href="<?= e(account_url($member)) ?>"><?= e((string) $member['display_name']) ?></a>
                        <span class="list-sub">@<?= e((string) ($member['username'] ?? '')) ?> · <?= e((string) $member['role']) ?></span>
                    </div>
                    <?php if ($role === 'Owner' && (string) $member['role'] !== 'Owner'): ?>
                    <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/members/' . (int) $member['id'] . '/role')) ?>" class="form">
                        <?= csrf_field() ?>
                        <select name="role" aria-label="Team role">
                            <?php foreach (Team::ROLES as $option): ?>
                            <?php if ($option === 'Owner') { continue; } ?>
                            <option value="<?= e($option) ?>" <?= (string) $member['role'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-ghost" type="submit">Save</button>
                    </form>
                    <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/members/' . (int) $member['id'] . '/remove')) ?>" data-confirm="Remove this member from the team?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/members')) ?>" class="form" style="margin-top:16px">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="field-label">Add a member by username</span>
                    <input type="text" name="username" maxlength="30" required placeholder="username">
                </label>
                <div class="btn-row"><button class="btn btn-secondary" type="submit"><?= icon('plus') ?><span>Add member</span></button></div>
            </form>
            <?php endif; ?>

            <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/announce')) ?>" class="form" style="margin-top:20px">
                <?= csrf_field() ?>
                <label class="field">
                    <span class="field-label">Send an announcement</span>
                    <input type="text" name="subject" maxlength="120" required placeholder="Subject">
                </label>
                <label class="field">
                    <textarea name="body" rows="3" maxlength="4000" required placeholder="Message for members and subscribers"></textarea>
                </label>
                <div class="btn-row"><button class="btn btn-secondary" type="submit"><?= icon('bell') ?><span>Send announcement</span></button></div>
                <p class="field-hint">Members and subscribers get a notification. Up to 5 announcements per 15 minutes.</p>
            </form>
            <?php endif; ?>

            <h3 style="margin-top:20px">Announcements</h3>
            <?php if ($announcements === []): ?>
            <p class="list-sub">No announcements yet.</p>
            <?php else: ?>
            <div class="list">
                <?php foreach ($announcements as $announcement): ?>
                <div class="list-row">
                    <div class="list-main">
                        <span class="list-title"><?= e((string) $announcement['subject']) ?></span>
                        <span class="list-sub" style="white-space: pre-line"><?= e((string) $announcement['body']) ?></span>
                        <span class="list-sub">by <?= e((string) ($announcement['display_name'] ?? $announcement['username'] ?? 'Former member')) ?> · <?= e(format_date($announcement['created_at'], 'M j, Y H:i')) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($role === null): ?>
            <?php if (Team::subscribed($teamId, (int) ($account['id'] ?? 0))): ?>
            <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/unsubscribe')) ?>" class="form" style="margin-top:16px">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit">Unsubscribe from team updates</button>
            </form>
            <?php else: ?>
            <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/subscribe')) ?>" class="form" style="margin-top:16px">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit">Subscribe to team updates</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>

            <?php if ($role === 'Owner'): ?>
            <form method="post" action="<?= e(url('/account/teams/' . $teamId . '/delete')) ?>" class="form" style="margin-top:16px" data-confirm="Delete this team? Its projects will no longer show a team.">
                <?= csrf_field() ?>
                <button class="btn btn-danger" type="submit"><?= icon('trash') ?><span>Delete team</span></button>
            </form>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($related !== []): ?>
    <section class="card">
        <div class="card-head"><div><h2>Teams you follow or belong to</h2><p>These appear in bbchat after your first sign-in there.</p></div></div>
        <div class="card-body">
            <div class="list">
                <?php foreach ($related as $team): ?>
                <div class="list-row">
                    <div class="list-main">
                        <a class="list-title" href="<?= e(url('/account/teams?team=' . (int) $team['id'])) ?>"><?= e((string) $team['name']) ?></a>
                        <span class="list-sub"><?= e((string) ($team['role'] ?? 'subscriber')) ?><?= (int) ($team['subscribed'] ?? 0) === 1 ? ' · subscribed' : '' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>
