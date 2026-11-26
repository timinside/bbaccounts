<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class User
{
    public const DIRECTORY_PAGE_SIZE = 20;

    private const PALETTE = [
        ['#4f46e5', '#7c3aed'],
        ['#0d9488', '#06b6d4'],
        ['#db2777', '#f97316'],
        ['#2563eb', '#0891b2'],
        ['#7c3aed', '#db2777'],
        ['#059669', '#84cc16'],
        ['#dc2626', '#f59e0b'],
        ['#334155', '#64748b'],
    ];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM accounts WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM accounts WHERE email = ? LIMIT 1', [mb_strtolower(trim($email))]);
    }

    public static function findByUsername(string $username): ?array
    {
        return Db::one('SELECT * FROM accounts WHERE username = ? LIMIT 1', [mb_strtolower(trim($username))]);
    }

    public static function create(string $email, string $passwordHash, string $displayName, bool $passwordSet = true, ?string $username = null): int
    {
        $handle = $username !== null && trim($username) !== ''
            ? mb_strtolower(trim($username))
            : self::suggestUsername($displayName, $email);

        $id = Db::insert('accounts', [
            'email' => mb_strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'password_set' => $passwordSet ? 1 : 0,
            'display_name' => $displayName,
            'username' => $handle,
            'avatar_type' => 'initials',
            'avatar_value' => null,
            'status' => 'active',
        ]);
        Showcase::createDefaults($id);

        return $id;
    }

    public static function addPoints(int $id, int $amount): void
    {
        Db::run('UPDATE accounts SET points = GREATEST(points + ?, 0) WHERE id = ?', [$amount, $id]);
    }

    public static function addXp(int $id, int $amount): void
    {
        if ($amount === 0) {
            return;
        }
        Db::run(
            'UPDATE accounts SET level = LEAST(99, GREATEST(0, FLOOR(GREATEST(xp + ?, 0) / 100))),
             xp = GREATEST(xp + ?, 0) WHERE id = ?',
            [$amount, $amount, $id]
        );
    }

    public static function usernameValid(string $username): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{2,29}$/', $username) === 1
            && !in_array($username, ['admin', 'api', 'account', 'login', 'register', 'docs', 'oauth', 'u', 'bbaccounts'], true);
    }

    public static function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM accounts WHERE username = ?';
        $params = [mb_strtolower(trim($username))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) Db::value($sql, $params) > 0;
    }

    public static function suggestUsername(string $displayName, string $email = ''): string
    {
        $base = mb_strtolower($displayName);
        $base = (string) preg_replace('/[^a-z0-9_]+/', '', $base);
        if (mb_strlen($base) < 3) {
            $local = strstr($email, '@', true);
            $base = $local === false ? '' : (string) preg_replace('/[^a-z0-9_]+/', '', mb_strtolower($local));
        }
        if (!preg_match('/^[a-z]/', $base)) {
            $base = 'user' . $base;
        }
        $base = substr($base, 0, 24);
        if (mb_strlen($base) < 3) {
            $base = 'user';
        }

        $candidate = $base;
        $suffix = 1;
        while (self::usernameTaken($candidate)) {
            $candidate = $base . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }

        Db::update('accounts', $data, 'id = ?', [$id]);
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM accounts WHERE email = ?';
        $params = [mb_strtolower(trim($email))];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        return (int) Db::value($sql, $params) > 0;
    }

    public static function palette(int $id): array
    {
        return self::PALETTE[$id % count(self::PALETTE)];
    }

    public static function isAvailable(array $account): bool
    {
        $status = (string) ($account['status'] ?? 'active');
        if ($status === 'active') {
            return true;
        }
        if ($status !== 'suspended' && $status !== 'banned') {
            return false;
        }

        $until = $account['status_until'] ?? null;
        $timestamp = $until === null || $until === '' ? false : strtotime((string) $until);

        return $timestamp !== false && $timestamp <= time();
    }

    public static function restoreExpired(): void
    {
        Db::run(
            'UPDATE accounts SET status = \'active\', status_until = NULL, status_reason = NULL, purge_after = NULL
             WHERE status IN (\'suspended\', \'banned\') AND status_until IS NOT NULL AND status_until <= NOW()'
        );
    }

    public static function purgeDue(): void
    {
        Db::run('DELETE FROM accounts WHERE status = \'banned\' AND purge_after IS NOT NULL AND purge_after <= NOW()');
    }

    public static function blockMessage(array $account): string
    {
        $status = (string) ($account['status'] ?? 'active');
        $reason = trim((string) ($account['status_reason'] ?? ''));

        if ($status === 'banned') {
            $message = 'This account has been permanently banned.';
            $purge = $account['purge_after'] ?? null;
            if ($purge !== null && $purge !== '') {
                $message .= ' It and the data it owns will be deleted after ' . format_date((string) $purge, 'M j, Y') . '.';
            }
        } elseif ($status === 'suspended') {
            $message = 'This account is temporarily suspended.';
            $until = $account['status_until'] ?? null;
            if ($until !== null && $until !== '') {
                $message .= ' Access returns on ' . format_date((string) $until, 'M j, Y H:i') . ' UTC.';
            }
        } else {
            $message = 'This account is not available.';
        }

        if ($reason !== '') {
            $message .= ' Reason: ' . $reason;
        }

        return $message;
    }

    public static function activeSessionCount(int $id): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM auth_sessions WHERE account_id = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [$id]
        );
    }

    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public static function directory(string $query = '', string $role = '', string $status = '', int $page = 1, int $perPage = self::DIRECTORY_PAGE_SIZE): array
    {
        $conditions = ["a.status <> 'deleted'"];
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $like = '%' . $query . '%';
            $conditions[] = '(a.display_name LIKE ? OR a.username LIKE ?)';
            array_push($params, $like, $like);
        }
        if (in_array($role, ['admin', 'developer', 'tester', 'moderator', 'member'], true)) {
            $conditions[] = match ($role) {
                'admin' => 'a.is_admin = 1',
                'developer' => 'a.is_developer = 1',
                'tester' => 'a.is_tester = 1',
                'moderator' => 'a.is_moderator = 1',
                'member' => 'a.is_admin = 0 AND a.is_developer = 0 AND a.is_tester = 0 AND a.is_moderator = 0',
            };
        }
        if (in_array($status, ['active', 'suspended', 'banned'], true)) {
            $conditions[] = 'a.status = ?';
            $params[] = $status;
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $total = (int) Db::value('SELECT COUNT(*) FROM accounts a ' . $where, $params);
        $perPage = max(1, min(50, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $rows = Db::all(
            'SELECT a.id, a.display_name, a.username, a.avatar_type, a.avatar_value, a.avatar_color,
                    a.is_admin, a.is_developer, a.is_tester, a.is_moderator,
                    a.show_role_admin, a.show_role_developer, a.show_role_tester, a.show_role_moderator,
                    a.private_profile, a.status, a.status_until, a.status_reason, a.warning_text, a.created_at
             FROM accounts a ' . $where . '
             ORDER BY a.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function recentActivity(int $id, int $limit = 8): array
    {
        return Db::all(
            'SELECT l.action, l.ip, l.created_at, p.name AS project_name
             FROM audit_log l
             LEFT JOIN projects p ON p.id = l.project_id
             WHERE l.actor_id = ?
             ORDER BY l.id DESC
             LIMIT ' . max(1, $limit),
            [$id]
        );
    }
}
