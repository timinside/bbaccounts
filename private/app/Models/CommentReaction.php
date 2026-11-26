<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class CommentReaction
{
    public const KINDS = ['up' => '👍', 'down' => '👎'];

    /** kind => count for one comment. */
    public static function counts(int $commentId): array
    {
        $counts = array_fill_keys(array_keys(self::KINDS), 0);
        foreach (Db::all(
            'SELECT kind, COUNT(*) AS total FROM comment_reactions WHERE comment_id = ? GROUP BY kind',
            [$commentId]
        ) as $row) {
            $kind = (string) $row['kind'];
            if (array_key_exists($kind, $counts)) {
                $counts[$kind] = (int) $row['total'];
            }
        }

        return $counts;
    }

    public static function mine(int $commentId, int $accountId): ?string
    {
        $value = Db::value(
            'SELECT kind FROM comment_reactions WHERE comment_id = ? AND account_id = ? LIMIT 1',
            [$commentId, $accountId]
        );

        return $value === null ? null : (string) $value;
    }

    /** One reaction per account per comment; clicking the active one removes it. */
    public static function toggle(int $commentId, int $accountId, string $kind): void
    {
        if (!array_key_exists($kind, self::KINDS)) {
            return;
        }
        if (self::mine($commentId, $accountId) === $kind) {
            Db::run('DELETE FROM comment_reactions WHERE comment_id = ? AND account_id = ?', [$commentId, $accountId]);

            return;
        }
        Db::run(
            'INSERT INTO comment_reactions (comment_id, account_id, kind) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE kind = VALUES(kind)',
            [$commentId, $accountId, $kind]
        );
    }
}
