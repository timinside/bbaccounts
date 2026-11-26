<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Audit;
use BBA\Core\Config;
use BBA\Core\Db;
use BBA\Core\Request;
use BBA\Models\Comment;
use BBA\Models\CommentReport;
use BBA\Models\Ticket;
use BBA\Models\User;

final class ModeratorController extends Controller
{
    public function index(): void
    {
        $this->moderator();

        $tab = Request::query('tab');
        if (!in_array($tab, ['reports', 'tester', 'developer', 'admin', 'deletion', 'help'], true)) {
            $tab = 'reports';
        }

        $this->view('moderator/index', [
            'title' => 'Moderator',
            'tab' => $tab,
            'reports' => CommentReport::open(),
            'openCount' => CommentReport::openCount(),
            'tickets' => $tab === 'reports' ? [] : Ticket::open($tab),
            'ticketCounts' => [
                'tester' => count(Ticket::open('tester')),
                'developer' => count(Ticket::open('developer')),
                'admin' => count(Ticket::open('admin')),
                'deletion' => count(Ticket::open('deletion')),
                'help' => count(Ticket::open('help')),
            ],
        ]);
    }

    /** Full ticket thread, shown inside the matching tab. */
    public function ticket(array $params): void
    {
        $viewer = $this->moderator();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null) {
            App::notFound();
        }

        $this->view('moderator/ticket', [
            'title' => 'Ticket #' . $ticket['id'],
            'ticket' => $ticket,
            'viewer' => $viewer,
            'messages' => Ticket::messages((int) $ticket['id']),
        ], 'layouts/panel');
    }

    public function resolve(array $params): void
    {
        $this->csrf();
        $account = $this->moderator();
        $id = (int) ($params['id'] ?? 0);

        CommentReport::resolve($id);
        Audit::log('moderator.report_resolved', (int) $account['id'], null, 'comment_report', $id);
        $this->succeed('Report dismissed.');
        $this->redirect('/moderator?tab=reports');
    }

    public function deleteComment(array $params): void
    {
        $this->csrf();
        $account = $this->moderator();
        $id = (int) ($params['id'] ?? 0);

        if (Comment::delete($id, 0, true)) {
            Audit::log('moderator.comment_deleted', (int) $account['id'], null, 'comment', $id);
            $this->succeed('Comment deleted.');
        } else {
            $this->fail('That comment is already gone.');
        }

        $this->redirect('/moderator?tab=reports');
    }

    public function replyTicket(array $params): void
    {
        $this->csrf();
        $moderator = $this->moderator();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        $body = trim((string) $this->input('body'));
        if ($ticket === null || $body === '') {
            $this->fail('Write a reply first.');
            $this->redirect('/moderator/tickets/' . (int) ($params['id'] ?? 0));
        }

        Ticket::message((int) $ticket['id'], (int) $moderator['id'], $body);
        Db::insert('notifications', [
            'account_id' => (int) $ticket['account_id'],
            'subject' => 'Moderator replied to your ticket',
            'body' => mb_substr($body, 0, 255),
            'icon' => 'mail',
            'link_text' => 'Open ticket',
            'link_url' => url('/account/tickets/' . (int) $ticket['id']),
        ]);
        Audit::log('moderator.ticket_replied', (int) $moderator['id'], null, 'ticket', (int) $ticket['id']);
        $this->succeed('Reply sent.');
        $this->redirect('/moderator/tickets/' . (int) $ticket['id']);
    }

    public function resolveTicket(array $params): void
    {
        $this->csrf();
        $moderator = $this->moderator();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null) {
            $this->fail('That ticket was not found.');
            $this->redirect('/moderator');
        }

        Ticket::resolve((int) $ticket['id']);
        Audit::log('moderator.ticket_resolved', (int) $moderator['id'], null, 'ticket', (int) $ticket['id']);
        $this->succeed('Ticket closed.');
        $this->redirect('/moderator?tab=' . rawurlencode((string) $ticket['type']));
    }

    public function approveTester(array $params): void
    {
        $this->csrf();
        $moderator = $this->moderator();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null || (string) $ticket['type'] !== 'tester') {
            $this->fail('That is not a tester application.');
            $this->redirect('/moderator?tab=tester');
        }

        User::update((int) $ticket['account_id'], ['is_tester' => 1]);
        Ticket::resolve((int) $ticket['id']);
        Db::insert('notifications', [
            'account_id' => (int) $ticket['account_id'],
            'subject' => 'Tester access granted',
            'body' => 'Your tester application was approved. Beta features are now available in your profile settings.',
            'icon' => 'shield',
            'link_text' => 'Open profile settings',
            'link_url' => url('/account/profile'),
        ]);
        Audit::log('moderator.tester_approved', (int) $moderator['id'], null, 'account', (int) $ticket['account_id']);
        $this->succeed('Tester access granted.');
        $this->redirect('/moderator?tab=tester');
    }

    public function approveDeveloper(array $params): void
    {
        $this->csrf();
        $moderator = $this->moderator();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null || (string) $ticket['type'] !== 'developer') {
            $this->fail('That is not a developer application.');
            $this->redirect('/moderator?tab=developer');
        }

        User::update((int) $ticket['account_id'], ['is_developer' => 1]);
        Ticket::resolve((int) $ticket['id']);
        Audit::log('moderator.developer_approved', (int) $moderator['id'], null, 'account', (int) $ticket['account_id']);
        $this->succeed('Developer access granted.');
        $this->redirect('/moderator?tab=developer');
    }

    public function approveAdmin(array $params): void
    {
        $this->csrf();
        $moderator = $this->moderator();
        $superIds = array_map('intval', (array) Config::get('admin.super_ids', []));
        if (!in_array((int) $moderator['id'], $superIds, true)) {
            App::forbidden('Only a Super Account may grant administrator access.');
        }
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null || (string) $ticket['type'] !== 'admin') {
            $this->fail('That is not an administrator application.');
            $this->redirect('/moderator?tab=admin');
        }

        User::update((int) $ticket['account_id'], ['is_admin' => 1]);
        Ticket::resolve((int) $ticket['id']);
        Audit::log('moderator.admin_approved', (int) $moderator['id'], null, 'account', (int) $ticket['account_id']);
        $this->succeed('Administrator access granted.');
        $this->redirect('/moderator?tab=admin');
    }

    public function completeDeletion(array $params): void
    {
        $this->csrf();
        $moderator = $this->moderator();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null || (string) $ticket['type'] !== 'deletion') {
            $this->fail('That is not a deletion request.');
            $this->redirect('/moderator?tab=deletion');
        }
        if (!hash_equals((string) $ticket['email'], (string) $this->input('confirmation'))) {
            $this->back(['confirmation' => 'Type the account email exactly to delete it.'], [], '/moderator/tickets/' . (int) $ticket['id']);
        }
        $fresh = Ticket::find((int) $ticket['id']);
        if ($fresh === null || (string) $fresh['status'] !== 'open') {
            $this->fail('That deletion request is already closed.');
            $this->redirect('/moderator?tab=deletion');
        }

        $id = (int) $ticket['account_id'];
        Audit::log('moderator.account_deleted', (int) $moderator['id'], null, 'account', $id, [
            'email' => $ticket['email'],
            'ticket_id' => (int) $ticket['id'],
        ]);
        Db::run('DELETE FROM accounts WHERE id = ?', [$id]);
        $this->succeed('Account deleted.');
        $this->redirect('/moderator?tab=deletion');
    }
}
