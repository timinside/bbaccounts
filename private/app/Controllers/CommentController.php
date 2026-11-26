<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\Audit;
use BBA\Core\Request;
use BBA\Models\Comment;
use BBA\Models\CommentReaction;
use BBA\Models\CommentReport;
use BBA\Models\Ticket;
use BBA\Models\Project;
use BBA\Models\User;

final class CommentController extends Controller
{
    public function storeProfile(array $params): void
    {
        $handle = trim((string) ($params['id'] ?? ''));
        $account = ctype_digit($handle) ? User::find((int) $handle) : User::findByUsername($handle);
        if ($account === null || (string) ($account['status'] ?? '') === 'deleted') {
            $this->redirect('/');
        }

        $this->store('profile', (int) $account['id'], account_url($account) . '#comments');
    }

    public function storeProject(array $params): void
    {
        $project = Project::findBySlug((string) ($params['slug'] ?? ''));
        if ($project === null) {
            $this->redirect('/');
        }

        $this->store('project', (int) $project['id'], '/p/' . rawurlencode((string) $project['slug']) . '#comments');
    }

    public function destroy(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $next = safe_next_path($this->input('next', '/'));

        $deleted = Comment::delete(
            (int) ($params['id'] ?? 0),
            (int) $account['id'],
            (int) ($account['is_admin'] ?? 0) === 1
        );
        if ($deleted) {
            Audit::log('comment.deleted', (int) $account['id'], null, 'comment', (int) ($params['id'] ?? 0));
            $this->succeed('Comment deleted.');
        } else {
            $this->fail('You can only delete your own comments.');
        }

        $this->redirect(($next !== '' ? $next : '/') . '#comments');
    }

    public function report(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $comment = Comment::find((int) ($params['id'] ?? 0));
        if ($comment === null) {
            $this->redirect('/');
        }

        $allowed = (int) ($account['is_admin'] ?? 0) === 1 || (int) ($account['is_moderator'] ?? 0) === 1;
        if (!$allowed && $comment['target_type'] === 'profile') {
            $target = User::find((int) $comment['target_id']);
            $allowed = $target !== null && (int) $target['id'] === (int) $account['id'];
        } elseif (!$allowed) {
            $project = Project::find((int) $comment['target_id']);
            $allowed = $project !== null && (int) $project['owner_id'] === (int) $account['id'];
        }

        if (!$allowed) {
            $this->fail('Only the owner of this profile or project can report comments.');
            $this->redirect('/');
        }

        $reason = mb_substr(trim((string) $this->input('reason')), 0, 500);
        CommentReport::create((int) $comment['id'], (int) $account['id'], $reason);
        Audit::log('comment.reported', (int) $account['id'], null, 'comment', (int) $comment['id']);
        $this->succeed('Comment reported to a moderator.');

        $next = safe_next_path($this->input('next', '/'));
        $this->redirect(($next !== '' ? $next : '/') . '#comments');
    }

    public function react(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $commentId = (int) ($params['id'] ?? 0);
        $kind = (string) $this->input('kind');
        if (!array_key_exists($kind, CommentReaction::KINDS)) {
            $this->redirect('/');
        }

        CommentReaction::toggle($commentId, (int) $account['id'], $kind);
        Audit::log('comment.reacted', (int) $account['id'], null, 'comment', $commentId, ['kind' => $kind]);

        $next = safe_next_path($this->input('next', '/'));
        $this->redirect(($next !== '' ? $next : '/') . '#comments');
    }

    private function store(string $type, int $targetId, string $returnTo): void
    {
        $this->csrf();
        $account = $this->auth();
        $body = trim((string) Request::input('body', ''));
        $parentRaw = $this->input('parent_id');
        $parentId = ctype_digit((string) $parentRaw) && (int) $parentRaw > 0 ? (int) $parentRaw : null;

        if (mb_strlen($body) < 2 || mb_strlen($body) > 1000) {
            $this->back(['body' => 'Comments must be between 2 and 1000 characters.'], ['body' => $body], $returnTo);
        }
        if (contains_link($body)) {
            Audit::log('moderation.link_blocked', (int) $account['id'], null, $type, $targetId, ['where' => 'comment', 'body' => mb_substr($body, 0, 200)]);
            if (!Ticket::hasOpen((int) $account['id'], 'help')) {
                Ticket::create((int) $account['id'], 'help', 'Automated moderation report: attempted to post a link/domain in a ' . $type . ' comment. Blocked content: "' . mb_substr($body, 0, 500) . '"');
            }
            $this->back(['body' => 'Links and domains are not allowed in comments.'], ['body' => $body], $returnTo);
        }

        Comment::create((int) $account['id'], $type, $targetId, $body, $parentId);
        Audit::log('comment.created', (int) $account['id'], null, $type, $targetId);
        $this->succeed('Comment added.');

        $this->redirect($returnTo);
    }
}
