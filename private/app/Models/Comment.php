<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class Comment
{
    public const TYPES = ['profile', 'project'];
    public const LIMIT = 50;

    public static function forTarget(string $type, int $targetId): array
    {
        return Db::all(
            'SELECT c.id, c.parent_id, c.body, c.created_at, c.author_id,
                    a.display_name, a.username, a.avatar_type, a.avatar_value, a.avatar_color
             FROM comments c
             INNER JOIN accounts a ON a.id = c.author_id
             WHERE c.target_type = ? AND c.target_id = ?
             ORDER BY c.id DESC
             LIMIT ' . self::LIMIT,
            [$type, $targetId]
        );
    }

    public static function count(string $type, int $targetId): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM comments WHERE target_type = ? AND target_id = ?',
            [$type, $targetId]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM comments WHERE id = ? LIMIT 1', [$id]);
    }

    public static function create(int $authorId, string $type, int $targetId, string $body, ?int $parentId = null): int
    {
        if ($parentId !== null) {
            $parent = self::find($parentId);
            $parentId = $parent !== null
                && $parent['target_type'] === $type
                && (int) $parent['target_id'] === $targetId
                && $parent['parent_id'] === null
                ? $parentId
                : null;
        }

        return Db::insert('comments', [
            'author_id' => $authorId,
            'target_type' => $type,
            'target_id' => $targetId,
            'parent_id' => $parentId,
            'body' => $body,
        ]);
    }

    public static function delete(int $id, int $authorId, bool $asAdmin): bool
    {
        $statement = $asAdmin
            ? Db::run('DELETE FROM comments WHERE id = ?', [$id])
            : Db::run('DELETE FROM comments WHERE id = ? AND author_id = ?', [$id, $authorId]);

        return $statement->rowCount() > 0;
    }
}
