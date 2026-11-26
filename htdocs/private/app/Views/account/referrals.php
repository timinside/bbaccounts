<?php
use BBA\Models\Referral;

$account = $account ?? [];
$links = $links ?? [];
$slots = $slots ?? Referral::SLOT_BASE;
$invited = $invited ?? [];
$points = (int) ($account['points'] ?? 0);
$labels = ['active' => 'Active', 'expired' => 'Expired', 'disabled' => 'Disabled'];
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Referrals</h1>
        <p class="page-sub">Invite someone to bbaccounts. When they register through your link, you both get points and become friends automatically.</p>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Your links</h2>
            <p><?= count(array_filter($links, static fn (array $link): bool => Referral::status($link) === 'active')) ?> of <?= (int) $slots ?> <?= $slots === 1 ? 'slot' : 'slots' ?> in use. Choose how long a link stays active — longer links cost more points. An expired link burns its slot permanently; buy another slot to create a new one.</p>
        </div>
        <span class="badge badge-accent"><?= $points ?> points</span>
    </div>
    <div class="card-body">
        <?php if ($links === []): ?>
        <div class="note">
            <?= icon('link') ?>
            <span>No referral link yet. Create one and choose how many days it stays active.</span>
        </div>
        <?php endif; ?>

        <?php foreach ($links as $link): ?>
        <?php
        $status = Referral::status($link);
        $linkUrl = url('/r/' . rawurlencode((string) $link['code']));
        $expires = $link['expires_at'] ?? null;
        ?>
        <article class="referral-row">
            <div class="list-row">
                <div class="list-main">
                    <span class="list-title">
                        <span class="badge <?= $status === 'active' ? 'badge-ok' : ($status === 'expired' ? 'badge-warn' : 'badge-danger') ?>"><?= e($labels[$status]) ?></span>
                        <?php if ($status === 'active' && $expires !== null): ?>
                        <span class="list-sub">until <?= e(format_date((string) $expires, 'j F Y')) ?></span>
                        <?php elseif ($status === 'expired' && $expires !== null): ?>
                        <span class="list-sub">ended <?= e(format_date((string) $expires, 'j F Y')) ?></span>
                        <?php else: ?>
                        <span class="list-sub">disabled by an administrator</span>
                        <?php endif; ?>
                    </span>
                    <span class="list-sub"><?= (int) $link['clicks'] ?> clicks · <?= (int) $link['signups'] ?> joined · created <?= e(format_date($link['created_at'] ?? null)) ?></span>
                </div>
            </div>

            <div class="field" style="margin-top: 12px">
                <label for="referral_link_<?= (int) $link['id'] ?>">Link</label>
                <div class="input-wrap">
                    <input id="referral_link_<?= (int) $link['id'] ?>" type="text" readonly value="<?= e($linkUrl) ?>" autocomplete="off">
                    <button class="input-action" type="button" data-copy data-copy-value="<?= e($linkUrl) ?>" aria-label="Copy referral link"><?= icon('copy') ?></button>
                </div>
            </div>

            <div class="btn-row">
                <form method="post" action="<?= e(url('/account/referrals/' . (int) $link['id'] . '/delete')) ?>" data-confirm="Delete this referral link? The slot becomes free, but clicks and registrations stay recorded.">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('trash') ?><span>Delete link</span></button>
                </form>
            </div>
        </article>
        <?php endforeach; ?>

        <?php if (count($links) < $slots): ?>
        <form method="post" action="<?= e(url('/account/referrals')) ?>" class="form referral-create">
            <?= csrf_field() ?>
            <div class="grid-2">
                <div class="field">
                    <label for="referral_days">Link stays active for</label>
                    <select id="referral_days" name="days">
                        <?php foreach ([1, 7, 14, 30, 60, 90, 180, 365] as $days): ?>
                        <?php $cost = Referral::dayCost($days); ?>
                        <option value="<?= $days ?>" data-cost="<?= $cost ?>" <?= $days === Referral::DEFAULT_DAYS ? 'selected' : '' ?><?= $points < $cost ? ' disabled' : '' ?>><?= $days ?> <?= $days === 1 ? 'day' : 'days' ?> · <?= $cost === 0 ? 'free' : $cost . ' points' ?><?= $points < $cost ? ' (not enough points)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Links up to <?= Referral::DAYS_FREE_MAX ?> days are free. Longer links cost points when created and are not refunded if you delete the link. After expiry the link stops accepting registrations.</p>
                </div>
            </div>
            <div class="btn-row">
                <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Create referral link</span></button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Referral slots</h2>
            <p>One link per slot. Each extra slot costs <?= Referral::SLOT_COST ?> points, up to <?= Referral::SLOT_MAX ?> slots.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="meta-grid">
            <div>
                <span class="mono-label">Slots</span>
                <span class="meta-value"><?= (int) $slots ?> / <?= Referral::SLOT_MAX ?></span>
            </div>
            <div>
                <span class="mono-label">Used</span>
                <span class="meta-value"><?= count($links) ?></span>
            </div>
            <div>
                <span class="mono-label">Extra slot</span>
                <span class="meta-value"><?= Referral::SLOT_COST ?> points</span>
            </div>
            <div>
                <span class="mono-label">Your balance</span>
                <span class="meta-value"><?= $points ?> points</span>
            </div>
        </div>
        <?php if ((int) $slots < Referral::SLOT_MAX): ?>
        <form method="post" action="<?= e(url('/account/referrals/slots')) ?>" class="form" style="margin-top: 16px">
            <?= csrf_field() ?>
            <div class="btn-row">
                <button class="btn btn-secondary" type="submit" <?= $points < Referral::SLOT_COST ? 'disabled' : '' ?>><?= icon('plus') ?><span>Buy a slot for <?= Referral::SLOT_COST ?> points</span></button>
                <?php if ($points < Referral::SLOT_COST): ?>
                <span class="field-hint">You need <?= Referral::SLOT_COST - $points ?> more points.</span>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<section class="card" style="margin-top: 20px">
    <div class="card-head">
        <div>
            <h2>Accounts you invited</h2>
            <p><?= count($invited) ?> <?= count($invited) === 1 ? 'account' : 'accounts' ?></p>
        </div>
    </div>
    <?php if ($invited === []): ?>
    <div class="empty">
        <span class="empty-icon"><?= icon('users') ?></span>
        <h3>No registrations yet</h3>
        <p>Share a link to see invited accounts here.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($invited as $row): ?>
        <a class="list-row" href="<?= e(account_url($row)) ?>">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($row)) ?>" alt="" width="32" height="32"<?= avatar_style($row) ?>>
            <span class="list-main">
                <span class="list-title"><?= e((string) $row['display_name']) ?></span>
                <span class="list-sub">@<?= e((string) ($row['username'] ?? '')) ?> · joined <?= e(time_ago($row['created_at'] ?? null)) ?></span>
            </span>
            <?= icon('arrow-right') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
