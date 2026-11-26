<?php
$ticket = $ticket ?? [];
$messages = $messages ?? [];
$type = (string) ($ticket['type'] ?? 'help');
$labels = \BBA\Models\Ticket::LABELS;
$isOpen = (string) ($ticket['status'] ?? 'open') === 'open';
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account · <?= e($labels[$type] ?? 'Support') ?></p>
        <h1>Ticket #<?= (int) ($ticket['id'] ?? 0) ?></h1>
        <p class="page-sub">
            <span class="badge <?= $isOpen ? 'badge-ok' : 'badge-warn' ?>"><?= $isOpen ? 'Open' : 'Closed' ?></span>
            opened <?= e(format_date($ticket['created_at'] ?? null)) ?>
        </p>
    </div>
    <div class="page-head-actions">
        <a class="btn btn-secondary" href="<?= e(url('/account/help')) ?>"><?= icon('arrow-left') ?><span>Help</span></a>
    </div>
</div>

<section class="card">
    <div class="card-body">
        <?php require BBA_APP . '/Views/partials/ticket-thread.php'; ?>
    </div>
</section>

<?php if ($isOpen): ?>
<section class="card" style="margin-top: 20px">
    <div class="card-body">
        <form method="post" action="<?= e(url('/account/tickets/' . (int) $ticket['id'] . '/reply')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="ticket_reply">Reply</label>
                <textarea id="ticket_reply" name="body" rows="3" maxlength="2000" autocomplete="off" required></textarea>
            </div>
            <div class="btn-row"><button class="btn btn-secondary" type="submit"><?= icon('mail') ?><span>Send reply</span></button></div>
        </form>
        <form method="post" action="<?= e(url('/account/tickets/' . (int) $ticket['id'] . '/cancel')) ?>" style="margin-top: 12px" data-confirm="Cancel this request?">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit"><?= icon('x') ?><span>Cancel request</span></button>
        </form>
    </div>
</section>
<?php endif; ?>
