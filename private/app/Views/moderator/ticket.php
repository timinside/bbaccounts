<?php
$ticket = $ticket ?? [];
$messages = $messages ?? [];
$type = (string) ($ticket['type'] ?? 'help');
$labels = \BBA\Models\Ticket::LABELS;
$isOpen = (string) ($ticket['status'] ?? 'open') === 'open';
?>
<div class="page-head">
    <div>
        <p class="mono-label">Administration · Moderator · <?= e($labels[$type] ?? 'Ticket') ?></p>
        <h1>Ticket #<?= (int) ($ticket['id'] ?? 0) ?></h1>
        <p class="page-sub">
            <span class="badge <?= $isOpen ? 'badge-ok' : 'badge-warn' ?>"><?= $isOpen ? 'Open' : 'Closed' ?></span>
            <?= e((string) ($ticket['display_name'] ?? '')) ?>
            <?= trim((string) ($ticket['username'] ?? '')) !== '' ? '@' . e((string) $ticket['username']) : '' ?>
            · <?= e((string) ($ticket['email'] ?? '')) ?>
        </p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/moderator?tab=' . rawurlencode($type))) ?>"><?= icon('arrow-left') ?><span>Back to queue</span></a>
        <?php if ((int) ($ticket['account_id'] ?? 0) !== 0): ?>
        <a class="btn btn-ghost" href="<?= e(url('/admin/accounts/' . (int) $ticket['account_id'])) ?>"><?= icon('user') ?><span>Account</span></a>
        <?php endif; ?>
    </div>
</div>

<section class="card">
    <div class="card-body">
        <?php require BBA_APP . '/Views/partials/ticket-thread.php'; ?>
    </div>
</section>

<?php if ($isOpen): ?>
<section class="card" style="margin-top: 20px">
    <div class="card-body form">
        <form method="post" action="<?= e(url('/moderator/tickets/' . (int) $ticket['id'] . '/reply')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="ticket_reply">Reply to <?= e((string) ($ticket['display_name'] ?? 'the account')) ?></label>
                <textarea id="ticket_reply" name="body" rows="3" maxlength="2000" autocomplete="off" required></textarea>
            </div>
            <div class="btn-row"><button class="btn btn-primary" type="submit"><?= icon('mail') ?><span>Send reply</span></button></div>
        </form>
        <div class="btn-row" style="margin-top: 14px">
            <?php if ($type === 'tester'): ?>
            <form method="post" action="<?= e(url('/moderator/tickets/' . (int) $ticket['id'] . '/approve-tester')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit"><?= icon('shield') ?><span>Approve tester access</span></button>
            </form>
            <?php elseif ($type === 'developer'): ?>
            <form method="post" action="<?= e(url('/moderator/tickets/' . (int) $ticket['id'] . '/approve-developer')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit"><?= icon('code') ?><span>Approve developer access</span></button>
            </form>
            <?php elseif ($type === 'admin'): ?>
            <?php if (is_super_account($viewer ?? [])): ?>
            <form method="post" action="<?= e(url('/moderator/tickets/' . (int) $ticket['id'] . '/approve-admin')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-secondary" type="submit" data-confirm="Grant administrator access to this account?"><?= icon('shield') ?><span>Approve administrator access</span></button>
            </form>
            <?php else: ?>
            <span class="badge badge-muted">Only a Super Account can approve this application.</span>
            <?php endif; ?>
            <?php elseif ($type === 'deletion'): ?>
            <form method="post" action="<?= e(url('/moderator/tickets/' . (int) $ticket['id'] . '/complete-deletion')) ?>" class="form">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="confirm_deletion">Type <?= e((string) ($ticket['email'] ?? '')) ?> to delete this account</label>
                    <input id="confirm_deletion" name="confirmation" type="text" autocomplete="off" required>
                    <?php if ($error = form_error('confirmation')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </div>
                <div class="btn-row"><button class="btn btn-danger" type="submit" data-confirm="Delete this account and everything it owns?"><?= icon('trash') ?><span>Complete deletion</span></button></div>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/moderator/tickets/' . (int) $ticket['id'] . '/resolve')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-ghost" type="submit"><?= icon('check') ?><span>Close without action</span></button>
            </form>
        </div>
    </div>
</section>
<?php endif; ?>
