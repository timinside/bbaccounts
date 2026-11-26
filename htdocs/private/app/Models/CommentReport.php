<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class CommentReport
{
    public static function exists(int $commentId, int $reporterId): bool
    {
        return Db::value(
            'SELECT 1 FROM comment_reports WHERE comment_id = ? AND reporter_id = ? LIMIT 1',
            [$commentId, $reporterId]
        ) !== null;
    }

    public static function create(int $commentId, int $reporterId, string $reason): void
    {
        if (self::exists($commentId, $reporterId)) {
            return;
        }

        Db::insert('comment_reports', [
            'comment_id' => $commentId,
            'reporter_id' => $reporterId,
            'reason' => $reason !== '' ? $reason : null,
        ]);
    }

    public static function openCount(): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM comment_reports WHERE status = 'open'");
    }

    /** Reported comments waiting for a moderator, newest first. */
    public static function open(): array
    {
        return Db::all(
            'SELECT r.id AS report_id, r.reason, r.status, r.created_at AS reported_at,
                    c.id AS comment_id, c.body, c.target_type, c.target_id,
                    a.display_name AS author_name, a.username AS author_username,
                    rep.display_name AS reporter_name, rep.username AS reporter_username,
                    CASE WHEN c.target_type = \'profile\'
                        THEN (SELECT username FROM accounts WHERE id = c.target_id)
                        ELSE (SELECT slug FROM projects WHERE id = c.target_id) END AS target_handle
             FROM comment_reports r
             INNER JOIN comments c ON c.id = r.comment_id
             INNER JOIN accounts a ON a.id = c.author_id
             INNER JOIN accounts rep ON rep.id = r.reporter_id
             WHERE r.status = \'open\'
             ORDER BY r.id DESC'
        );
    }

    public static function resolve(int $id): void
    {
        Db::run("UPDATE comment_reports SET status = 'resolved' WHERE id = ?", [$id]);
    }
}
