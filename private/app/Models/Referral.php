<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Audit;
use BBA\Core\Db;

final class Referral
{
    public const SLOT_BASE = 1;
    public const SLOT_MAX = 10;
    public const SLOT_COST = 25;
    public const DAYS_MIN = 1;
    public const DAYS_MAX = 365;
    public const DEFAULT_DAYS = 30;
    public const DAYS_FREE_MAX = 14;
    public const DAY_STEP = 30;
    public const DAY_STEP_COST = 10;
    public const DAY_COST_MAX = 50;
    public const XP_OWNER = 50;
    public const XP_NEW = 100;

    /** Links up to two weeks are free; longer links cost more the longer they stay active. */
    public static function dayCost(int $days): int
    {
        if ($days <= self::DAYS_FREE_MAX) {
            return 0;
        }

        return min(self::DAY_COST_MAX, (int) ceil(($days - self::DAYS_FREE_MAX) / self::DAY_STEP) * self::DAY_STEP_COST);
    }

    /** An expired link burns its slot for good; the link itself is kept for its history. */
    public static function sweepExpired(int $accountId): void
    {
        $expired = Db::all(
            'SELECT id FROM referrals WHERE account_id = ? AND is_active = 1 AND expires_at IS NOT NULL AND expires_at <= NOW()',
            [$accountId]
        );
        foreach ($expired as $row) {
            $updated = Db::run('UPDATE referrals SET is_active = 0 WHERE id = ? AND is_active = 1', [(int) $row['id']]);
            if ($updated->rowCount() > 0) {
                Db::run('UPDATE accounts SET referral_slots = GREATEST(referral_slots - 1, 0) WHERE id = ?', [$accountId]);
                Audit::log('referral.slot_burned', $accountId, null, 'referral', (int) $row['id']);
            }
        }
    }

    /** Every link on the account, newest first. */
    public static function forAccount(int $accountId): array
    {
        self::sweepExpired($accountId);

        return Db::all(
            'SELECT * FROM referrals WHERE account_id = ? ORDER BY id DESC',
            [$accountId]
        );
    }

    public static function find(int $id, int $accountId): ?array
    {
        return Db::one('SELECT * FROM referrals WHERE id = ? AND account_id = ? LIMIT 1', [$id, $accountId]);
    }

    public static function slotsFor(int $accountId): int
    {
        self::sweepExpired($accountId);
        $slots = (int) Db::value('SELECT referral_slots FROM accounts WHERE id = ?', [$accountId]);

        return max(0, min(self::SLOT_MAX, $slots));
    }

    public static function create(int $accountId, int $days): ?array
    {
        $days = max(self::DAYS_MIN, min(self::DAYS_MAX, $days));
        $existing = (int) Db::value('SELECT COUNT(*) FROM referrals WHERE account_id = ? AND is_active = 1', [$accountId]);
        if ($existing >= self::slotsFor($accountId)) {
            return null;
        }

        $cost = self::dayCost($days);
        if (Db::run('UPDATE accounts SET points = points - ? WHERE id = ? AND points >= ?', [$cost, $accountId, $cost])->rowCount() === 0) {
            return null;
        }

        $id = Db::insert('referrals', [
            'account_id' => $accountId,
            'code' => self::newCode(),
            'expires_at' => date('Y-m-d H:i:s', time() + $days * 86400),
            'is_active' => 1,
        ]);

        return Db::one('SELECT * FROM referrals WHERE id = ? LIMIT 1', [$id]);
    }

    public static function remove(int $id, int $accountId): bool
    {
        return Db::run('DELETE FROM referrals WHERE id = ? AND account_id = ?', [$id, $accountId])->rowCount() > 0;
    }

    public static function buySlot(array $account): bool
    {
        $id = (int) ($account['id'] ?? 0);
        if ($id === 0 || (int) ($account['points'] ?? 0) < self::SLOT_COST
            || self::slotsFor($id) >= self::SLOT_MAX) {
            return false;
        }

        $updated = Db::run(
            'UPDATE accounts SET points = points - ?, referral_slots = referral_slots + 1
             WHERE id = ? AND points >= ? AND referral_slots < ?',
            [self::SLOT_COST, $id, self::SLOT_COST, self::SLOT_MAX]
        );

        return $updated->rowCount() === 1;
    }

    public static function status(array $row): string
    {
        $expires = $row['expires_at'] ?? null;
        if ($expires !== null && $expires !== '' && strtotime((string) $expires) <= time()) {
            return 'expired';
        }
        if ((int) ($row['is_active'] ?? 1) !== 1) {
            return 'disabled';
        }

        return 'active';
    }

    public static function findActiveByCode(string $code): ?array
    {
        $row = Db::one(
            'SELECT r.*, a.status AS owner_status, a.username AS owner_username, a.display_name AS owner_name
             FROM referrals r
             INNER JOIN accounts a ON a.id = r.account_id
             WHERE r.code = ? AND r.is_active = 1
             LIMIT 1',
            [trim($code)]
        );
        if ($row === null || self::status($row) !== 'active') {
            return null;
        }

        return $row;
    }

    public static function click(string $code): void
    {
        $row = self::findActiveByCode($code);
        if ($row === null) {
            return;
        }

        Db::run('UPDATE referrals SET clicks = clicks + 1 WHERE id = ?', [(int) $row['id']]);
    }

    /** Marks the new account as referred and pays both sides. Returns the referrer or null. */
    public static function complete(array $referral, int $newAccountId): ?array
    {
        $row = self::findActiveByCode((string) ($referral['code'] ?? ''));
        if ($row === null) {
            return null;
        }

        $ownerId = (int) $row['account_id'];
        $owner = User::find($ownerId);
        $new = User::find($newAccountId);
        if ($owner === null || $new === null || $ownerId === $newAccountId || !User::isAvailable($owner)) {
            return null;
        }
        if (($new['referred_by'] ?? null) !== null) {
            return null;
        }

        Db::run('UPDATE accounts SET referred_by = ? WHERE id = ?', [$ownerId, $newAccountId]);
        Db::run('UPDATE referrals SET signups = signups + 1 WHERE id = ?', [(int) $row['id']]);
        User::addPoints($ownerId, (int) $row['points_owner']);
        User::addPoints($newAccountId, (int) $row['points_new']);
        User::addXp($ownerId, self::XP_OWNER);
        User::addXp($newAccountId, self::XP_NEW);
        Friend::befriend($ownerId, $newAccountId);

        return $owner;
    }

    public static function invited(int $accountId): array
    {
        return Db::all(
            'SELECT id, display_name, username, avatar_type, avatar_value, avatar_color, updated_at, created_at
             FROM accounts
             WHERE referred_by = ?
             ORDER BY id DESC',
            [$accountId]
        );
    }

    private static function newCode(): string
    {
        do {
            $code = bin2hex(random_bytes(8));
            $taken = Db::one('SELECT id FROM referrals WHERE code = ? LIMIT 1', [$code]);
        } while ($taken !== null);

        return $code;
    }
}
