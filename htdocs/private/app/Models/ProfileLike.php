<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;
use BBA\Models\User;

final class ProfileLike
{
    public const POINTS = 2;
    public const XP = 10;

    public static function exists(int $accountId, int $profileId): bool
    {
        return Db::value(
            'SELECT 1 FROM profile_likes WHERE account_id = ? AND profile_id = ? LIMIT 1',
            [$accountId, $profileId]
        ) !== null;
    }

    public static function count(int $profileId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM profile_likes WHERE profile_id = ?', [$profileId]);
    }

    /** Accounts who liked a profile, newest first. */
    public static function likers(int $profileId): array
    {
        return Db::all(
            'SELECT a.id, a.display_name, a.username, a.avatar_type, a.avatar_value, a.avatar_color, a.updated_at
             FROM profile_likes l INNER JOIN accounts a ON a.id = l.account_id
             WHERE l.profile_id = ? ORDER BY l.created_at DESC, l.account_id DESC LIMIT 50',
            [$profileId]
        );
    }

    /** Adds or removes one like and keeps the profile owner's points and XP in step. */
    public static function toggle(int $accountId, int $profileId): bool
    {
        if ($accountId === $profileId || self::exists($accountId, $profileId)) {
            $deleted = Db::run('DELETE FROM profile_likes WHERE account_id = ? AND profile_id = ?', [$accountId, $profileId])->rowCount();
            if ($deleted > 0) {
                User::addPoints($profileId, -self::POINTS);
                User::addXp($profileId, -self::XP);
            }

            return false;
        }

        $inserted = Db::run('INSERT INTO profile_likes (account_id, profile_id) VALUES (?, ?)', [$accountId, $profileId])->rowCount();
        if ($inserted > 0) {
            User::addPoints($profileId, self::POINTS);
            User::addXp($profileId, self::XP);
        }

        return true;
    }
}
