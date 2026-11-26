<?php
$tickets = $tickets ?? [];
$ticketLabels = \BBA\Models\Ticket::LABELS;
?>
<div class="page-head">
    <div>
        <p class="mono-label">Account · Support</p>
        <h1>Help</h1>
        <p class="page-sub">Ask a moderator anything about your account. Replies arrive here and as a notification.</p>
    </div>
    <div class="page-head-actions">
        <?php if (\BBA\Core\Config::get('allow_tutorial', true)): ?>
        <button class="btn btn-secondary" type="button" data-tutorial-replay><?= icon('book') ?><span>Replay tutorial</span></button>
        <?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(url('/account')) ?>"><?= icon('arrow-left') ?><span>Overview</span></a>
    </div>
</div>

<section class="card">
    <div class="card-head">
        <div><h2>Open a chat</h2><p>Describe your question in the first message.</p></div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= e(url('/account/tickets/help')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="help_body">Your message</label>
                <textarea id="help_body" name="body" rows="4" maxlength="2000" autocomplete="off" required></textarea>
                <?php if ($error = form_error('body')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            </div>
            <div class="btn-row"><button class="btn btn-primary" type="submit"><?= icon('mail') ?><span>Open support chat</span></button></div>
        </form>
    </div>
</section>

<?php if ($tickets !== []): ?>
<section class="card" style="margin-top: 20px">
    <div class="card-head"><div><h2>Your chats</h2><p><?= count($tickets) ?> recent conversations.</p></div></div>
    <div class="list">
        <?php foreach ($tickets as $ticket): ?>
        <a class="list-row" href="<?= e(url('/account/tickets/' . (int) $ticket['id'])) ?>">
            <span class="list-icon"><?= icon((string) $ticket['status'] === 'open' ? 'mail' : 'check') ?></span>
            <span class="list-main">
                <span class="list-title">Chat #<?= (int) $ticket['id'] ?></span>
                <span class="list-sub"><?= e((string) $ticket['status']) ?> · opened <?= e(format_date($ticket['created_at'] ?? null)) ?></span>
            </span>
            <?= icon('arrow-right') ?>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
