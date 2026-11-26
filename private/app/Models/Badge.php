<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class Badge
{
    public const XP = 100;
    public const POINTS = 5;

    public static function all(): array
    {
        return Db::all('SELECT * FROM badges ORDER BY id ASC');
    }

    public static function findBySlug(string $slug): ?array
    {
        return Db::one('SELECT * FROM badges WHERE slug = ? LIMIT 1', [mb_strtolower(trim($slug))]);
    }

    public static function findById(int $id): ?array
    {
        return $id > 0 ? Db::one('SELECT * FROM badges WHERE id = ? LIMIT 1', [$id]) : null;
    }

    public static function forProject(int $projectId): array
    {
        return Db::all('SELECT * FROM badges WHERE project_id = ? ORDER BY id ASC', [$projectId]);
    }

    /** Verified projects may create their own badges and issue them through the API. */
    public static function createForProject(int $projectId, string $name, string $description): ?array
    {
        $base = mb_strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/u', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            return null;
        }
        $base = mb_substr($base, 0, 30);
        $slug = $base;
        $counter = 1;
        while (self::findBySlug($slug) !== null) {
            $slug = $base . '-p' . $projectId . ($counter > 1 ? '-' . $counter : '');
            $counter++;
        }

        $id = Db::insert('badges', [
            'slug' => $slug,
            'name' => mb_substr(trim($name), 0, 60),
            'description' => $description !== '' ? mb_substr($description, 0, 160) : null,
            'icon' => 'star',
            'style' => 'badge',
            'rule' => 'manual',
            'project_id' => $projectId,
        ]);

        return self::findBySlug($slug);
    }

    public static function ownedByProject(int $badgeId, int $projectId): bool
    {
        return (int) Db::value('SELECT project_id FROM badges WHERE id = ? LIMIT 1', [$badgeId]) === $projectId;
    }

    public static function holderCount(int $badgeId): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM account_badges ab
             INNER JOIN accounts a ON a.id = ab.account_id
             WHERE ab.badge_id = ? AND ab.is_visible = 1 AND (a.status IS NULL OR a.status = \'active\')',
            [$badgeId]
        );
    }

    public static function holders(int $badgeId, int $limit = 12): array
    {
        return Db::all(
            'SELECT a.* FROM account_badges ab
             INNER JOIN accounts a ON a.id = ab.account_id
             WHERE ab.badge_id = ? AND ab.is_visible = 1 AND (a.status IS NULL OR a.status = \'active\')
             ORDER BY ab.created_at ASC
             LIMIT ' . max(1, min(50, $limit)),
            [$badgeId]
        );
    }

    public static function forAccount(int $accountId): array
    {
        return Db::all(
            'SELECT b.*, ab.is_visible, ab.assigned_by, ab.created_at AS assigned_at
             FROM badges b
             INNER JOIN account_badges ab ON ab.badge_id = b.id
             WHERE ab.account_id = ?
             ORDER BY b.id ASC',
            [$accountId]
        );
    }

    public static function visibleForAccount(int $accountId): array
    {
        return Db::all(
            'SELECT b.*
             FROM badges b
             INNER JOIN account_badges ab ON ab.badge_id = b.id
             WHERE ab.account_id = ? AND ab.is_visible = 1
             ORDER BY b.id ASC',
            [$accountId]
        );
    }

    public static function syncAutomatic(array $account): void
    {
        $accountId = (int) ($account['id'] ?? 0);
        if ($accountId === 0) {
            return;
        }

        $hasProject = (int) Db::value('SELECT COUNT(*) FROM projects WHERE owner_id = ?', [$accountId]) > 0;
        $created = strtotime((string) ($account['created_at'] ?? ''));
        $veteran = $created !== false && $created <= strtotime('-30 days');

        foreach (self::all() as $badge) {
            $earned = match ((string) $badge['rule']) {
                'project_owner' => $hasProject,
                'account_30d' => $veteran,
                default => false,
            };
            if ($earned) {
                $inserted = Db::run(
                    'INSERT IGNORE INTO account_badges (account_id, badge_id, is_visible) VALUES (?, ?, 1)',
                    [$accountId, (int) $badge['id']]
                );
                if ($inserted->rowCount() === 1) {
                    User::addXp($accountId, self::XP);
                    User::addPoints($accountId, self::POINTS);
                }
            }
        }
    }

    public static function assign(int $accountId, int $badgeId, ?int $assignedBy = null): void
    {
        $inserted = Db::run(
            'INSERT INTO account_badges (account_id, badge_id, assigned_by, is_visible) VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), is_visible = 1',
            [$accountId, $badgeId, $assignedBy]
        );
        if ($inserted->rowCount() === 1) {
            User::addXp($accountId, self::XP);
            User::addPoints($accountId, self::POINTS);
        }
    }

    public static function revoke(int $accountId, int $badgeId): void
    {
        Db::run('DELETE FROM account_badges WHERE account_id = ? AND badge_id = ?', [$accountId, $badgeId]);
    }

    public static function setVisibility(int $accountId, int $badgeId, bool $visible): void
    {
        Db::run(
            'UPDATE account_badges SET is_visible = ? WHERE account_id = ? AND badge_id = ?',
            [$visible ? 1 : 0, $accountId, $badgeId]
        );
    }
}
