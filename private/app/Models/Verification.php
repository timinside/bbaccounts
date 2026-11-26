<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class Verification
{
    public static function for(string $type, int $id): ?array
    {
        return Db::one(
            'SELECT * FROM verifications WHERE target_type = ? AND target_id = ? LIMIT 1',
            [$type, $id]
        );
    }

    public static function save(string $type, int $id, string $kind, string $note, int $grantedBy): void
    {
        Db::run(
            'INSERT INTO verifications (target_type, target_id, kind, note, granted_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE kind = VALUES(kind), note = VALUES(note), granted_by = VALUES(granted_by)',
            [$type, $id, $kind, $note, $grantedBy]
        );
    }

    public static function remove(string $type, int $id): void
    {
        Db::run('DELETE FROM verifications WHERE target_type = ? AND target_id = ?', [$type, $id]);
    }
}
