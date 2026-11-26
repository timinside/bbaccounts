<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\Auth;
use BBA\Core\Db;
use BBA\Models\Friend;
use BBA\Models\User;

final class FriendController extends Controller
{
    public function store(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $target = User::find((int) ($params['id'] ?? 0));
        if ($target === null || (string) ($target['status'] ?? '') === 'deleted') {
            $this->fail('That account does not exist.');
            $this->redirect('/');
        }

        $result = Friend::request((int) $account['id'], (int) $target['id']);
        $back = account_url($target);

        switch ($result) {
            case 'sent':
                self::notify((int) $target['id'], (int) $account['id'], 'Friend request',
                    $account['display_name'] . ' wants to be friends.', 'users', 'Open friends', '/account/friends');
                $this->succeed('Friend request sent.');
                break;
            case 'accepted':
                self::notify((int) $target['id'], (int) $account['id'], 'Friend request accepted',
                    $account['display_name'] . ' accepted your friend request.', 'users', 'Open profile', $back);
                $this->succeed('You are now friends with ' . $target['display_name'] . '.');
                break;
            case 'friends':
                $this->fail('You are already friends.');
                break;
            case 'pending':
                $this->fail('There is already a pending request.');
                break;
            case 'full':
                $this->fail('One of these accounts has no free friend slots.');
                break;
            default:
                $this->fail('You cannot add yourself.');
        }

        $this->redirect($back);
    }

    public function accept(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $request = Friend::pendingById((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($request !== null && (Friend::full((int) $request['requester_id']) || Friend::full((int) $account['id']))) {
            $this->fail('One of these accounts has no free friend slots.');
            $this->redirect($this->returnPath());
        }

        $row = Friend::accept((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($row === null) {
            $this->fail('That request is no longer available.');
        } else {
            self::notify((int) $row['requester_id'], (int) $account['id'], 'Friend request accepted',
                $account['display_name'] . ' accepted your friend request.', 'users', 'Open profile',
                account_url($account));
            $this->succeed('Friend request accepted.');
        }
        $this->redirect($this->returnPath());
    }

    public function decline(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        if (Friend::decline((int) ($params['id'] ?? 0), (int) $account['id']) === null) {
            $this->fail('That request is no longer available.');
        } else {
            $this->succeed('Friend request declined.');
        }
        $this->redirect($this->returnPath());
    }

    public function remove(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $target = User::find((int) ($params['id'] ?? 0));
        if ($target === null) {
            $this->fail('That account does not exist.');
            $this->redirect($this->returnPath());
        }

        if (Friend::remove((int) $account['id'], (int) $target['id'])) {
            $this->succeed('Friend removed.');
        } else {
            $this->fail('You are not friends with that account.');
        }
        $this->redirect($this->returnPath());
    }

    private static function notify(int $accountId, int $actorId, string $subject, string $body, string $icon, string $linkText, string $linkUrl): void
    {
        Db::insert('notifications', [
            'account_id' => $accountId,
            'actor_id' => $actorId,
            'subject' => $subject,
            'body' => $body,
            'icon' => $icon,
            'link_text' => $linkText,
            'link_url' => $linkUrl,
        ]);
    }

    private function returnPath(): string
    {
        $next = safe_next_path((string) $this->input('next', ''));

        return $next !== '' ? $next : '/account/friends';
    }
}
