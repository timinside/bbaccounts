<?php
$friends = $friends ?? [];
$incoming = $incoming ?? [];
$outgoing = $outgoing ?? [];
$friendLimit = (int) ($friendLimit ?? 20);
$friendMax = (int) ($friendMax ?? 30);
$friendCost = (int) ($friendCost ?? 10);
$friendSlot = (int) ($friendSlot ?? 10);
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account</p>
        <h1>Friends</h1>
        <p class="page-sub">Send a request from anyone's public profile. Accepted friends appear on your profile.</p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<?php if ($incoming !== []): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Requests</h2>
            <p><?= count($incoming) ?> waiting for your answer.</p>
        </div>
    </div>
    <div class="list">
        <?php foreach ($incoming as $request): ?>
        <div class="list-row">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($request)) ?>" alt="" width="36" height="36"<?= avatar_style($request) ?>>
            <div class="list-main">
                <a class="list-title" href="<?= e(account_url($request)) ?>"><?= e((string) $request['display_name']) ?></a>
                <span class="list-sub"><?= trim((string) ($request['username'] ?? '')) !== '' ? '@' . e((string) $request['username']) : 'Wants to be friends' ?></span>
            </div>
            <div class="list-actions">
                <form method="post" action="<?= e(url('/friends/' . (int) $request['friendship_id'] . '/accept')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="/account/friends">
                    <button class="btn btn-sm btn-primary" type="submit"><?= icon('check') ?><span>Accept</span></button>
                </form>
                <form method="post" action="<?= e(url('/friends/' . (int) $request['friendship_id'] . '/decline')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="/account/friends">
                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('x') ?><span>Decline</span></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Your friends</h2>
            <p><?= count($friends) ?>/<?= $friendLimit ?> slots used. Each accepted friendship earns <?= \BBA\Models\Friend::POINTS ?> points.</p>
        </div>
        <?php if ($friendLimit < $friendMax): ?>
        <form method="post" action="<?= e(url('/account/friends/slots')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-secondary" type="submit"><?= icon('plus') ?><span>+<?= $friendSlot ?> friends for <?= $friendCost ?> points</span></button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($friends === []): ?>
    <div class="card-body">
        <p class="list-sub">No friends yet. Open a public profile and use <strong>Add friend</strong>.</p>
    </div>
    <?php else: ?>
    <div class="list">
        <?php foreach ($friends as $friend): ?>
        <div class="list-row">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($friend)) ?>" alt="" width="36" height="36"<?= avatar_style($friend) ?>>
            <div class="list-main">
                <a class="list-title" href="<?= e(account_url($friend)) ?>"><?= e((string) $friend['display_name']) ?></a>
                <span class="list-sub"><?= trim((string) ($friend['username'] ?? '')) !== '' ? '@' . e((string) $friend['username']) : 'Friend' ?></span>
            </div>
            <div class="list-actions">
                <form method="post" action="<?= e(url('/friends/' . (int) $friend['id'] . '/remove')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="/account/friends">
                    <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Remove <?= e((string) $friend['display_name']) ?> from your friends?"><?= icon('trash') ?><span>Remove</span></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<?php if ($outgoing !== []): ?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Sent requests</h2>
            <p>Waiting for an answer.</p>
        </div>
    </div>
    <div class="list">
        <?php foreach ($outgoing as $request): ?>
        <div class="list-row">
            <img class="avatar avatar-sm" src="<?= e(avatar_url($request)) ?>" alt="" width="36" height="36"<?= avatar_style($request) ?>>
            <div class="list-main">
                <a class="list-title" href="<?= e(account_url($request)) ?>"><?= e((string) $request['display_name']) ?></a>
                <span class="list-sub"><?= trim((string) ($request['username'] ?? '')) !== '' ? '@' . e((string) $request['username']) : 'Pending' ?></span>
            </div>
            <div class="list-actions">
                <form method="post" action="<?= e(url('/friends/' . (int) $request['friendship_id'] . '/remove')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="next" value="/account/friends">
                    <button class="btn btn-sm btn-ghost" type="submit"><?= icon('x') ?><span>Cancel</span></button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
