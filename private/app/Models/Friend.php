<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;
use BBA\Models\User;

final class Friend
{
    public const BASE_LIMIT = 20;
    public const SLOT = 10;
    public const MAX = 30;
    public const SLOT_COST = 15;
    public const POINTS = 5;
    public const XP = 10;
    public const BEST_FRIENDS_MAX = 5;

    public static function limitFor(int $accountId): int
    {
        $account = User::find($accountId);

        return max(1, min(self::MAX, (int) ($account['friend_limit'] ?? self::BASE_LIMIT)));
    }

    public static function full(int $accountId): bool
    {
        return self::count($accountId) >= self::limitFor($accountId);
    }

    public static function buySlot(array $account): bool
    {
        $id = (int) ($account['id'] ?? 0);
        if ($id === 0 || (int) ($account['points'] ?? 0) < self::SLOT_COST
            || (int) ($account['friend_limit'] ?? self::BASE_LIMIT) >= self::MAX) {
            return false;
        }

        $updated = Db::run(
            'UPDATE accounts SET points = points - ?, friend_limit = LEAST(?, friend_limit + ?)
             WHERE id = ? AND points >= ? AND friend_limit < ?',
            [self::SLOT_COST, self::MAX, self::SLOT, $id, self::SLOT_COST, self::MAX]
        );

        return $updated->rowCount() === 1;
    }

    /** Friendship row between two accounts, in either direction. */
    public static function relation(int $a, int $b): ?array
    {
        return Db::one(
            'SELECT * FROM friendships
             WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)
             LIMIT 1',
            [$a, $b, $b, $a]
        );
    }

    /** @return 'sent'|'accepted'|'pending'|'friends'|'self'|'full' */
    public static function request(int $from, int $to): string
    {
        if ($from === $to) {
            return 'self';
        }

        $existing = self::relation($from, $to);
        if ($existing !== null) {
            if ((string) $existing['status'] === 'accepted') {
                return 'friends';
            }
            if ((int) $existing['addressee_id'] === $from) {
                if (self::full((int) $existing['requester_id']) || self::full($from)) {
                    return 'full';
                }
                self::accept((int) $existing['id'], $from);

                return 'accepted';
            }

            return 'pending';
        }

        if (self::full($from) || self::full($to)) {
            return 'full';
        }

        Db::insert('friendships', [
            'requester_id' => $from,
            'addressee_id' => $to,
            'status' => 'pending',
        ]);

        return 'sent';
    }

    public static function pendingById(int $id, int $userId): ?array
    {
        return Db::one(
            'SELECT * FROM friendships WHERE id = ? AND addressee_id = ? AND status = \'pending\' LIMIT 1',
            [$id, $userId]
        );
    }

    public static function accept(int $id, int $userId): ?array
    {
        $row = self::pendingById($id, $userId);
        if ($row === null || self::full((int) $row['requester_id']) || self::full($userId)) {
            return null;
        }

        Db::run('UPDATE friendships SET status = \'accepted\', responded_at = NOW() WHERE id = ?', [$id]);
        // A new friendship earns points and XP for both people, once.
        User::addPoints((int) $row['requester_id'], self::POINTS);
        User::addPoints($userId, self::POINTS);
        User::addXp((int) $row['requester_id'], self::XP);
        User::addXp($userId, self::XP);

        return $row;
    }

    /** Referral friendships are accepted immediately; the referral pays the rewards, not the friendship. */
    public static function befriend(int $a, int $b): bool
    {
        if ($a === $b) {
            return false;
        }

        $existing = self::relation($a, $b);
        if ($existing !== null) {
            if ((string) $existing['status'] !== 'accepted') {
                Db::run('UPDATE friendships SET status = \'accepted\', responded_at = NOW() WHERE id = ?', [(int) $existing['id']]);
            }

            return true;
        }

        // shortcut: referral friendships skip the friend cap; revisit if referrals get abused.
        Db::run(
            'INSERT INTO friendships (requester_id, addressee_id, status, responded_at) VALUES (?, ?, \'accepted\', NOW())',
            [$a, $b]
        );

        return true;
    }

    public static function decline(int $id, int $userId): ?array
    {
        $row = Db::one(
            'SELECT * FROM friendships WHERE id = ? AND addressee_id = ? LIMIT 1',
            [$id, $userId]
        );
        if ($row === null) {
            return null;
        }

        Db::run('DELETE FROM friendships WHERE id = ?', [$id]);

        return $row;
    }

    public static function remove(int $userId, int $friendId): bool
    {
        return Db::run(
            'DELETE FROM friendships
             WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)',
            [$userId, $friendId, $friendId, $userId]
        )->rowCount() > 0;
    }

    public static function forAccount(int $accountId): array
    {
        $rows = Db::all(
            'SELECT f.id AS friendship_id, f.responded_at,
                    a.id, a.display_name, a.username, a.avatar_type, a.avatar_value, a.avatar_color,
                    a.status, a.status_until, a.updated_at
             FROM friendships f
             INNER JOIN accounts a ON a.id = IF(f.requester_id = ?, f.addressee_id, f.requester_id)
             WHERE f.status = \'accepted\' AND (f.requester_id = ? OR f.addressee_id = ?)
             ORDER BY f.responded_at DESC, f.id DESC',
            [$accountId, $accountId, $accountId]
        );

        return array_values(array_filter($rows, static fn (array $row): bool => User::isAvailable($row)));
    }

    public static function pendingIncoming(int $accountId): array
    {
        return Db::all(
            'SELECT f.id AS friendship_id, f.created_at, a.id, a.display_name, a.username,
                    a.avatar_type, a.avatar_value, a.avatar_color, a.updated_at
             FROM friendships f
             INNER JOIN accounts a ON a.id = f.requester_id
             WHERE f.addressee_id = ? AND f.status = \'pending\'
             ORDER BY f.id DESC',
            [$accountId]
        );
    }

    public static function pendingOutgoing(int $accountId): array
    {
        return Db::all(
            'SELECT f.id AS friendship_id, f.created_at, a.id, a.display_name, a.username,
                    a.avatar_type, a.avatar_value, a.avatar_color, a.updated_at
             FROM friendships f
             INNER JOIN accounts a ON a.id = f.addressee_id
             WHERE f.requester_id = ? AND f.status = \'pending\'
             ORDER BY f.id DESC',
            [$accountId]
        );
    }

    public static function count(int $accountId): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM friendships WHERE status = \'accepted\' AND (requester_id = ? OR addressee_id = ?)',
            [$accountId, $accountId]
        );
    }
}
