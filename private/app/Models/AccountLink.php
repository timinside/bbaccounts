<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class AccountLink
{
    public const LIMIT = 5;

    public static function forAccount(int $accountId): array
    {
        return Db::all(
            'SELECT * FROM account_links WHERE account_id = ? ORDER BY id ASC',
            [$accountId]
        );
    }

    public static function count(int $accountId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM account_links WHERE account_id = ?', [$accountId]);
    }

    public static function create(int $accountId, string $label, string $url): int
    {
        return Db::insert('account_links', [
            'account_id' => $accountId,
            'label' => $label,
            'url' => $url,
        ]);
    }

    public static function delete(int $id, int $accountId): void
    {
        Db::run('DELETE FROM account_links WHERE id = ? AND account_id = ?', [$id, $accountId]);
    }
}
