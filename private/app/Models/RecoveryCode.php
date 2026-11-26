<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Crypto;
use BBA\Core\Db;

final class RecoveryCode
{
    public static function generate(int $accountId, int $count = 10): array
    {
        Db::run('DELETE FROM recovery_codes WHERE account_id = ?', [$accountId]);

        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(Crypto::random(10)));
            $formatted = substr($raw, 0, 5) . '-' . substr($raw, 5, 5) . '-' . substr($raw, 10, 5) . '-' . substr($raw, 15, 5);
            $codes[] = $formatted;
            Db::insert('recovery_codes', [
                'account_id' => $accountId,
                'code_hash' => Crypto::hash($formatted),
            ]);
        }

        return $codes;
    }

    public static function verify(int $accountId, string $code): ?int
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        if ($normalized === '') {
            return null;
        }

        $formatted = substr($normalized, 0, 5) . '-' . substr($normalized, 5, 5) . '-' . substr($normalized, 10, 5) . '-' . substr($normalized, 15, 5);
        $row = Db::one(
            'SELECT id FROM recovery_codes WHERE account_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1',
            [$accountId, Crypto::hash($formatted)]
        );

        return $row === null ? null : (int) $row['id'];
    }

    public static function markUsed(int $id): void
    {
        Db::run('UPDATE recovery_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$id]);
    }

    public static function countUnused(int $accountId): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM recovery_codes WHERE account_id = ? AND used_at IS NULL',
            [$accountId]
        );
    }
}
