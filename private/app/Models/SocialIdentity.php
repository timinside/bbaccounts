<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class SocialIdentity
{
    public static function find(string $provider, string $providerUserId): ?array
    {
        return Db::one(
            'SELECT * FROM auth_identities WHERE provider = ? AND provider_user_id = ? LIMIT 1',
            [$provider, $providerUserId]
        );
    }

    public static function forAccount(int $accountId): array
    {
        return Db::all(
            'SELECT * FROM auth_identities WHERE account_id = ? ORDER BY provider ASC',
            [$accountId]
        );
    }

    public static function link(int $accountId, string $provider, string $providerUserId, ?string $email, ?string $username = null): int
    {
        return Db::insert('auth_identities', [
            'account_id' => $accountId,
            'provider' => $provider,
            'provider_user_id' => $providerUserId,
            'email' => $email,
            'username' => $username !== null && trim($username) !== '' ? trim($username) : null,
        ]);
    }

    public static function updateUsername(int $id, string $username): void
    {
        $username = trim($username);
        if ($username !== '') {
            Db::update('auth_identities', ['username' => $username], 'id = ?', [$id]);
        }
    }

    public static function unlink(int $id, int $accountId): void
    {
        Db::run('DELETE FROM auth_identities WHERE id = ? AND account_id = ?', [$id, $accountId]);
    }

    public static function setVisibility(int $id, int $accountId, bool $visible): void
    {
        Db::run(
            'UPDATE auth_identities SET is_visible = ? WHERE id = ? AND account_id = ?',
            [$visible ? 1 : 0, $id, $accountId]
        );
    }

    public static function countForAccount(int $accountId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM auth_identities WHERE account_id = ?', [$accountId]);
    }
}
