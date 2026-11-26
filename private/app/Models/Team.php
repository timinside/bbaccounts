<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class Team
{
    public const COST = 100;
    public const XP = 200;
    public const ROLES = ['Owner', 'Co-owner', 'Member'];
    private const ROLE_RANK = ['Owner' => 0, 'Co-owner' => 1, 'Member' => 2];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM teams WHERE id = ? LIMIT 1', [$id]);
    }

    public static function forOwner(int $accountId): array
    {
        return Db::all('SELECT * FROM teams WHERE owner_id = ? ORDER BY id ASC', [$accountId]);
    }

    /** All teams offered on the account page so non-members can follow them. */
    public static function catalog(): array
    {
        return Db::all(
            'SELECT t.*, (SELECT COUNT(*) FROM team_subscriptions s WHERE s.team_id = t.id) AS subscribers
             FROM teams t ORDER BY t.id DESC'
        );
    }

    /** Teams the account belongs to or follows, used by bbchat to create chats after sign-in. */
    public static function forAccount(int $accountId): array
    {
        return Db::all(
            'SELECT t.*, m.role, CASE WHEN s.account_id IS NULL THEN 0 ELSE 1 END AS subscribed,
                    (SELECT COUNT(*) FROM team_subscriptions s2 WHERE s2.team_id = t.id) AS subscribers
             FROM teams t
             LEFT JOIN team_members m ON m.team_id = t.id AND m.account_id = ?
             LEFT JOIN team_subscriptions s ON s.team_id = t.id AND s.account_id = ?
             WHERE m.account_id IS NOT NULL OR s.account_id IS NOT NULL
             ORDER BY t.id ASC',
            [$accountId, $accountId]
        );
    }

    /** Teams for the "Teams" showcase: highest role first, subscriptions last. */
    public static function forProfile(int $accountId, bool $includeSubscribed): array
    {
        $rows = self::forAccount($accountId);
        if (!$includeSubscribed) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $team): bool => trim((string) ($team['role'] ?? '')) !== ''
            ));
        }
        usort($rows, static function (array $a, array $b): int {
            $rankA = self::ROLE_RANK[(string) ($a['role'] ?? '')] ?? 3;
            $rankB = self::ROLE_RANK[(string) ($b['role'] ?? '')] ?? 3;

            return ($rankA <=> $rankB) ?: ((int) $a['id'] <=> (int) $b['id']);
        });

        return $rows;
    }

    public static function apiForAccount(int $accountId): array
    {
        $teams = [];
        foreach (self::forAccount($accountId) as $team) {
            $teams[] = [
                'id' => (int) $team['id'],
                'name' => (string) $team['name'],
                'slug' => (string) $team['slug'],
                'role' => (string) ($team['role'] !== null ? $team['role'] : 'none'),
                'subscribed' => (int) ($team['subscribed'] ?? 0) === 1,
                'members' => array_map(static fn (array $member): array => [
                    'account_id' => (int) $member['id'],
                    'username' => (string) ($member['username'] ?? ''),
                    'role' => (string) $member['role'],
                ], self::members((int) $team['id'])),
            ];
        }

        return ['count' => count($teams), 'teams' => $teams];
    }

    public static function members(int $teamId): array
    {
        return Db::all(
            'SELECT a.id, a.username, a.display_name, a.avatar_type, a.avatar_value, a.avatar_color, a.updated_at, m.role
             FROM team_members m
             INNER JOIN accounts a ON a.id = m.account_id
             WHERE m.team_id = ?
             ORDER BY FIELD(m.role, \'Owner\', \'Co-owner\', \'Member\'), a.id ASC',
            [$teamId]
        );
    }

    public static function roleOf(int $teamId, int $accountId): ?string
    {
        $role = Db::value('SELECT role FROM team_members WHERE team_id = ? AND account_id = ? LIMIT 1', [$teamId, $accountId]);

        return $role === null || $role === false ? null : (string) $role;
    }

    public static function create(int $accountId, string $name, string $description): ?array
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
        while (Db::value('SELECT id FROM teams WHERE slug = ? LIMIT 1', [$slug]) !== null) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        $id = Db::insert('teams', [
            'name' => mb_substr(trim($name), 0, 80),
            'slug' => $slug,
            'description' => $description !== '' ? mb_substr($description, 0, 280) : null,
            'owner_id' => $accountId,
        ]);
        Db::insert('team_members', ['team_id' => $id, 'account_id' => $accountId, 'role' => 'Owner']);

        // One "Teams" showcase per account, not one per team.
        $showcaseId = (int) Db::value(
            "SELECT COALESCE(MIN(id), 0) FROM showcases WHERE account_id = ? AND type = 'team'",
            [$accountId]
        );
        if ($showcaseId === 0) {
            $showcaseId = Showcase::create(
                $accountId,
                'team',
                'Teams',
                null,
                (string) json_encode(['include_subscribed' => 0])
            );
            Db::run('UPDATE showcases SET is_visible = 1 WHERE id = ?', [$showcaseId]);
        }

        return self::find($id);
    }

    public static function addMember(int $teamId, int $accountId, string $role = 'Member'): bool
    {
        if (!in_array($role, self::ROLES, true) || $role === 'Owner') {
            return false;
        }
        $inserted = Db::run(
            'INSERT IGNORE INTO team_members (team_id, account_id, role) VALUES (?, ?, ?)',
            [$teamId, $accountId, $role]
        );

        return $inserted->rowCount() > 0;
    }

    public static function setRole(int $teamId, int $accountId, string $role): bool
    {
        if (!in_array($role, self::ROLES, true) || $role === 'Owner') {
            return false;
        }
        $team = self::find($teamId);
        if ($team === null || (int) $team['owner_id'] === $accountId) {
            return false;
        }

        return Db::run(
            'UPDATE team_members SET role = ? WHERE team_id = ? AND account_id = ?',
            [$role, $teamId, $accountId]
        )->rowCount() > 0;
    }

    public static function removeMember(int $teamId, int $accountId): bool
    {
        $team = self::find($teamId);
        if ($team === null || (int) $team['owner_id'] === $accountId) {
            return false;
        }

        return Db::run('DELETE FROM team_members WHERE team_id = ? AND account_id = ?', [$teamId, $accountId])->rowCount() > 0;
    }

    public static function subscribe(int $teamId, int $accountId): void
    {
        Db::run('INSERT IGNORE INTO team_subscriptions (team_id, account_id) VALUES (?, ?)', [$teamId, $accountId]);
    }

    public static function unsubscribe(int $teamId, int $accountId): void
    {
        Db::run('DELETE FROM team_subscriptions WHERE team_id = ? AND account_id = ?', [$teamId, $accountId]);
    }

    public static function subscribed(int $teamId, int $accountId): bool
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM team_subscriptions WHERE team_id = ? AND account_id = ?',
            [$teamId, $accountId]
        ) > 0;
    }

    public static function announcements(int $teamId): array
    {
        return Db::all(
            'SELECT a.*, acc.display_name, acc.username
             FROM team_announcements a
             LEFT JOIN accounts acc ON acc.id = a.author_id
             WHERE a.team_id = ?
             ORDER BY a.id DESC LIMIT 20',
            [$teamId]
        );
    }

    public static function announce(int $teamId, int $authorId, string $subject, string $body): int
    {
        return Db::insert('team_announcements', [
            'team_id' => $teamId,
            'author_id' => $authorId,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /** Members and followers receive announcements; the author does not need their own notification. */
    public static function announcementRecipients(int $teamId, int $authorId): array
    {
        $rows = Db::all(
            'SELECT DISTINCT account_id FROM (
                SELECT account_id FROM team_members WHERE team_id = ?
                UNION
                SELECT account_id FROM team_subscriptions WHERE team_id = ?
            ) AS recipients WHERE account_id <> ?',
            [$teamId, $teamId, $authorId]
        );

        return array_map(static fn (array $row): int => (int) $row['account_id'], $rows);
    }

    public static function projects(int $teamId): array
    {
        return Db::all('SELECT * FROM projects WHERE team_id = ? ORDER BY id ASC', [$teamId]);
    }

    public static function delete(int $teamId): void
    {
        $team = self::find($teamId);
        Db::run('UPDATE projects SET team_id = NULL WHERE team_id = ?', [$teamId]);
        Db::run('DELETE FROM teams WHERE id = ?', [$teamId]);
        if ($team !== null) {
            $ownerId = (int) $team['owner_id'];
            $remaining = (int) Db::value('SELECT COUNT(*) FROM teams WHERE owner_id = ?', [$ownerId]);
            if ($remaining === 0) {
                Db::run("DELETE FROM showcases WHERE account_id = ? AND type = 'team'", [$ownerId]);
            }
        }
    }
}
