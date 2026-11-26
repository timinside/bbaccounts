<?php
$reports = $reports ?? [];
$openCount = (int) ($openCount ?? 0);
$tab = (string) ($tab ?? 'reports');
$tickets = $tickets ?? [];
$ticketCounts = $ticketCounts ?? ['tester' => 0, 'deletion' => 0, 'help' => 0];
$tabs = [
    'reports' => 'Reports' . ($openCount > 0 ? ' (' . $openCount . ')' : ''),
    'tester' => 'Tester' . (($ticketCounts['tester'] ?? 0) > 0 ? ' (' . (int) $ticketCounts['tester'] . ')' : ''),
    'developer' => 'Developer' . (($ticketCounts['developer'] ?? 0) > 0 ? ' (' . (int) $ticketCounts['developer'] . ')' : ''),
    'admin' => 'Admin' . (($ticketCounts['admin'] ?? 0) > 0 ? ' (' . (int) $ticketCounts['admin'] . ')' : ''),
    'deletion' => 'Deletion' . (($ticketCounts['deletion'] ?? 0) > 0 ? ' (' . (int) $ticketCounts['deletion'] . ')' : ''),
    'help' => 'Help' . (($ticketCounts['help'] ?? 0) > 0 ? ' (' . (int) $ticketCounts['help'] . ')' : ''),
];
$headings = [
    'reports' => ['Reported comments', 'Dismiss a report or delete the comment and its replies.'],
    'tester' => ['Tester applications', 'Approve an application to grant tester access, or reply with a question.'],
    'developer' => ['Developer applications', 'Approve an application to let an account create projects.'],
    'admin' => ['Administrator applications', 'Only a Super Account can approve these.'],
    'deletion' => ['Deletion requests', 'Each request includes a verified unused recovery key. Complete it or reply first.'],
    'help' => ['Support chat', 'Open chats from the account Help page. Reply here to continue the conversation.'],
];
?>
<div class="page-head">
    <div>
        <p class="mono-label">Administration · Moderator</p>
        <h1><?= e($headings[$tab][0]) ?></h1>
        <p class="page-sub"><?= e($headings[$tab][1]) ?></p>
    </div>
</div>

<nav class="tabs" aria-label="Moderator sections">
    <?php foreach ($tabs as $key => $label): ?>
    <a class="tab<?= $tab === $key ? ' is-active' : '' ?>" href="<?= e(url('/moderator?tab=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'reports'): ?>
    <?php if ($reports === []): ?>
    <section class="card" style="margin-top: 20px">
        <div class="empty">
            <span class="empty-icon"><?= icon('check') ?></span>
            <h3>Nothing to review</h3>
            <p>New reports from profile and project owners appear here.</p>
        </div>
    </section>
    <?php else: ?>
    <div class="stack" style="margin-top: 20px">
        <?php foreach ($reports as $report): ?>
        <?php
        $handle = trim((string) ($report['target_handle'] ?? ''));
        $target = $report['target_type'] === 'profile'
            ? '/u/' . rawurlencode($handle !== '' ? $handle : (string) (int) $report['target_id'])
            : '/p/' . rawurlencode($handle);
        ?>
        <article class="card">
            <div class="card-body">
                <div class="list-row">
                    <div class="list-main">
                        <span class="list-title"><?= e((string) $report['body']) ?></span>
                        <span class="list-sub">
                            <?= e((string) $report['author_name']) ?>
                            <?= trim((string) ($report['author_username'] ?? '')) !== '' ? ' @' . e((string) $report['author_username']) : '' ?>
                            · reported by <?= e((string) $report['reporter_name']) ?>
                            · <?= e(format_date($report['reported_at'] ?? null)) ?>
                            · <?= e((string) $report['target_type']) ?>
                        </span>
                        <?php if (trim((string) ($report['reason'] ?? '')) !== ''): ?>
                        <p class="list-sub" style="margin-top: 6px">Reason: <?= e((string) $report['reason']) ?></p>
                        <?php endif; ?>
                        <a class="text-link" href="<?= e(url($target . '#comment-' . (int) $report['comment_id'])) ?>">Open on site</a>
                    </div>
                    <div class="btn-row">
                        <form method="post" action="<?= e(url('/moderator/reports/' . (int) $report['report_id'] . '/resolve')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-secondary" type="submit"><?= icon('check') ?><span>Dismiss</span></button>
                        </form>
                        <form method="post" action="<?= e(url('/moderator/comments/' . (int) $report['comment_id'] . '/delete')) ?>" data-confirm="Delete this comment and its replies?">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-danger" type="submit"><?= icon('trash') ?><span>Delete comment</span></button>
                        </form>
                    </div>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($tickets === []): ?>
    <section class="card" style="margin-top: 20px">
        <div class="empty">
            <span class="empty-icon"><?= icon('check') ?></span>
            <h3>Nothing open</h3>
            <p>New tickets in this section appear here.</p>
        </div>
    </section>
    <?php else: ?>
    <div class="list" style="margin-top: 20px">
        <?php foreach ($tickets as $ticket): ?>
        <a class="list-row" href="<?= e(url('/moderator/tickets/' . (int) $ticket['id'])) ?>">
            <span class="list-icon"><?= icon($tab === 'deletion' ? 'alert' : ($tab === 'tester' ? 'shield' : 'mail')) ?></span>
            <span class="list-main">
                <span class="list-title">#<?= (int) $ticket['id'] ?> · <?= e((string) $ticket['display_name']) ?> <?= trim((string) ($ticket['username'] ?? '')) !== '' ? '@' . e((string) $ticket['username']) : '' ?></span>
                <span class="list-sub"><?= e((string) $ticket['email']) ?> · <?= (int) $ticket['message_count'] ?> <?= (int) $ticket['message_count'] === 1 ? 'message' : 'messages' ?> · <?= e(time_ago($ticket['created_at'] ?? null)) ?></span>
            </span>
            <?= icon('arrow-right') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>
