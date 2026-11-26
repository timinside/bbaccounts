<?php
use BBA\Core\Auth;

$comments = $comments ?? [];
$commentAction = $commentAction ?? '';
$commentNext = $commentNext ?? '/';
$canReport = (bool) ($canReport ?? false);
$cardStyle = $cardStyle ?? '';
$viewer = Auth::user();
$viewerIsAdmin = $viewer !== null && (int) ($viewer['is_admin'] ?? 0) === 1;
$roots = [];
$replies = [];
foreach ($comments as $comment) {
    $parentId = (int) ($comment['parent_id'] ?? 0);
    if ($parentId === 0) {
        $roots[] = $comment;
    } else {
        $replies[$parentId][] = $comment;
    }
}
// Replies read oldest-first under their parent, like a thread.
foreach ($replies as &$thread) {
    $thread = array_reverse($thread);
}
unset($thread);
?>
<section class="card comments" id="comments"<?= $cardStyle ?>>
    <div class="card-head">
        <div>
            <h2>Comments</h2>
            <p><?= count($comments) === 0 ? 'No comments yet. Start the conversation.' : count($comments) . ' comment' . (count($comments) === 1 ? '' : 's') . ' on this page.' ?></p>
        </div>
        <span class="stat-icon"><?= icon('users') ?></span>
    </div>

    <?php if ($viewer === null): ?>
    <div class="card-body">
        <a class="btn btn-secondary" href="<?= e(url('/login?next=' . rawurlencode($commentNext))) ?>"><?= icon('user') ?><span>Sign in to comment</span></a>
    </div>
    <?php else: ?>
    <form method="post" action="<?= e(url($commentAction)) ?>" class="card-body form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="comment_body">Write a comment</label>
            <textarea id="comment_body" name="body" rows="3" maxlength="1000" placeholder="Keep it useful and kind." required><?= e(old('body')) ?></textarea>
            <?php if ($error = form_error('body')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
            <p class="field-hint">Up to 1000 characters. @mentions become links.</p>
        </div>
        <div class="btn-row">
            <button class="btn btn-secondary" type="submit"><?= icon('plus') ?><span>Post comment</span></button>
        </div>
    </form>
    <?php endif; ?>

    <?php if ($roots !== []): ?>
    <div class="list comment-list">
        <?php foreach ($roots as $comment): ?>
        <?php $canDelete = $viewer !== null && ($viewerIsAdmin || (int) $viewer['id'] === (int) $comment['author_id']); ?>
        <article class="comment" id="comment-<?= (int) $comment['id'] ?>">
            <a href="<?= e(account_url($comment)) ?>"><img class="avatar avatar-sm" src="<?= e(avatar_url($comment)) ?>" alt="" width="32" height="32"<?= avatar_style($comment) ?>></a>
            <div class="comment-body">
                <div class="comment-meta">
                    <a href="<?= e(account_url($comment)) ?>"><?= e((string) $comment['display_name']) ?></a>
                    <?php if (trim((string) ($comment['username'] ?? '')) !== ''): ?>
                    <span class="list-sub">@<?= e((string) $comment['username']) ?></span>
                    <?php endif; ?>
                    <span class="list-sub"><?= e(format_date($comment['created_at'] ?? null)) ?></span>
                </div>
                <p class="comment-text"><?= render_mentions((string) $comment['body']) ?></p>
                <?php
                $counts = \BBA\Models\CommentReaction::counts((int) $comment['id']);
                $mine = $viewer !== null ? \BBA\Models\CommentReaction::mine((int) $comment['id'], (int) $viewer['id']) : null;
                ?>
                <div class="comment-reactions">
                    <?php foreach (\BBA\Models\CommentReaction::KINDS as $kind => $emoji): ?>
                    <?php if ($viewer === null): ?>
                    <span class="reaction" title="<?= e($kind) ?>"><span class="reaction-emoji"><?= $emoji ?></span><?= $counts[$kind] ?></span>
                    <?php else: ?>
                    <form method="post" action="<?= e(url('/comments/' . (int) $comment['id'] . '/react')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kind" value="<?= e($kind) ?>">
                        <input type="hidden" name="next" value="<?= e($commentNext) ?>">
                        <button class="reaction<?= $mine === $kind ? ' is-active' : '' ?>" type="submit" aria-label="React with <?= e($kind) ?>"><span class="reaction-emoji"><?= $emoji ?></span><?= $counts[$kind] ?></button>
                    </form>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($viewer !== null): ?>
                    <details class="reply-box">
                        <summary>Reply</summary>
                        <form method="post" action="<?= e(url($commentAction)) ?>" class="form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="parent_id" value="<?= (int) $comment['id'] ?>">
                            <label class="sr-only" for="reply_<?= (int) $comment['id'] ?>">Reply to <?= e((string) $comment['display_name']) ?></label>
                            <textarea id="reply_<?= (int) $comment['id'] ?>" name="body" rows="2" maxlength="1000" placeholder="Write a reply" required></textarea>
                            <button class="btn btn-sm btn-secondary" type="submit">Send reply</button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <?php if ($canReport): ?>
                    <?php if ($viewer !== null && \BBA\Models\CommentReport::exists((int) $comment['id'], (int) $viewer['id'])): ?>
                    <span class="comment-action is-muted">Reported</span>
                    <?php else: ?>
                    <details class="reply-box">
                        <summary>Report</summary>
                        <form method="post" action="<?= e(url('/comments/' . (int) $comment['id'] . '/report')) ?>" class="form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="next" value="<?= e($commentNext) ?>">
                            <label class="sr-only" for="report_<?= (int) $comment['id'] ?>">Reason</label>
                            <input id="report_<?= (int) $comment['id'] ?>" name="reason" type="text" maxlength="500" placeholder="Why report this comment? (optional)">
                            <button class="btn btn-sm btn-secondary" type="submit">Send report</button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <form method="post" action="<?= e(url('/comments/' . (int) $comment['id'] . '/delete')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="next" value="<?= e($commentNext) ?>">
                        <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Delete this comment?"><?= icon('trash') ?><span>Delete</span></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>

        <?php foreach ($replies[(int) $comment['id']] ?? [] as $reply): ?>
        <?php $canDeleteReply = $viewer !== null && ($viewerIsAdmin || (int) $viewer['id'] === (int) $reply['author_id']); ?>
        <article class="comment comment-reply" id="comment-<?= (int) $reply['id'] ?>">
            <a href="<?= e(account_url($reply)) ?>"><img class="avatar avatar-sm" src="<?= e(avatar_url($reply)) ?>" alt="" width="28" height="28"<?= avatar_style($reply) ?>></a>
            <div class="comment-body">
                <div class="comment-meta">
                    <a href="<?= e(account_url($reply)) ?>"><?= e((string) $reply['display_name']) ?></a>
                    <span class="list-sub"><?= e(format_date($reply['created_at'] ?? null)) ?></span>
                </div>
                <p class="comment-text"><?= render_mentions((string) $reply['body']) ?></p>
                <?php
                $replyCounts = \BBA\Models\CommentReaction::counts((int) $reply['id']);
                $replyMine = $viewer !== null ? \BBA\Models\CommentReaction::mine((int) $reply['id'], (int) $viewer['id']) : null;
                ?>
                <div class="comment-reactions">
                    <?php foreach (\BBA\Models\CommentReaction::KINDS as $kind => $emoji): ?>
                    <?php if ($viewer === null): ?>
                    <span class="reaction"><span class="reaction-emoji"><?= $emoji ?></span><?= $replyCounts[$kind] ?></span>
                    <?php else: ?>
                    <form method="post" action="<?= e(url('/comments/' . (int) $reply['id'] . '/react')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="kind" value="<?= e($kind) ?>">
                        <input type="hidden" name="next" value="<?= e($commentNext) ?>">
                        <button class="reaction<?= $replyMine === $kind ? ' is-active' : '' ?>" type="submit"><span class="reaction-emoji"><?= $emoji ?></span><?= $replyCounts[$kind] ?></button>
                    </form>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($canReport): ?>
                    <?php if ($viewer !== null && \BBA\Models\CommentReport::exists((int) $reply['id'], (int) $viewer['id'])): ?>
                    <span class="comment-action is-muted">Reported</span>
                    <?php else: ?>
                    <details class="reply-box">
                        <summary>Report</summary>
                        <form method="post" action="<?= e(url('/comments/' . (int) $reply['id'] . '/report')) ?>" class="form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="next" value="<?= e($commentNext) ?>">
                            <input name="reason" type="text" maxlength="500" placeholder="Why report this comment? (optional)">
                            <button class="btn btn-sm btn-secondary" type="submit">Send report</button>
                        </form>
                    </details>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($canDeleteReply): ?>
                    <form method="post" action="<?= e(url('/comments/' . (int) $reply['id'] . '/delete')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="next" value="<?= e($commentNext) ?>">
                        <button class="btn btn-sm btn-ghost" type="submit" data-confirm="Delete this comment?"><?= icon('trash') ?><span>Delete</span></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
