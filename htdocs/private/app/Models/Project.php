<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Crypto;
use BBA\Core\Db;

final class Project
{
    public const PAGE_SIZE = 20;

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM projects WHERE id = ? LIMIT 1', [$id]);
    }

    public static function findForOwner(int $id, int $ownerId): ?array
    {
        return Db::one('SELECT * FROM projects WHERE id = ? AND owner_id = ? LIMIT 1', [$id, $ownerId]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Db::one(
            'SELECT p.*, a.display_name AS owner_name,
                    a.status AS owner_status, a.status_until AS owner_status_until, a.status_reason AS owner_status_reason
             FROM projects p
             INNER JOIN accounts a ON a.id = p.owner_id
             WHERE p.slug = ?
             LIMIT 1',
            [$slug]
        );
    }

    public static function forOwner(int $ownerId): array
    {
        return Db::all(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM project_redirects r WHERE r.project_id = p.id) AS redirect_count,
                    (SELECT COUNT(*) FROM project_credentials c WHERE c.project_id = p.id AND c.revoked_at IS NULL) AS credential_count,
                    (SELECT COUNT(*) FROM oauth_tokens t WHERE t.project_id = p.id AND t.revoked_at IS NULL AND t.expires_at > NOW()) AS active_token_count
             FROM projects p
             WHERE p.owner_id = ?
             ORDER BY p.created_at DESC',
            [$ownerId]
        );
    }

    public static function create(int $ownerId, string $name, string $slug, string $description, string $domain, string $accent): int
    {
        return Db::insert('projects', [
            'owner_id' => $ownerId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'domain' => $domain,
            'accent_color' => $accent,
            'status' => 'active',
        ]);
    }

    public static function update(int $id, int $ownerId, array $data): void
    {
        Db::update('projects', $data, 'id = ? AND owner_id = ?', [$id, $ownerId]);
    }

    public static function delete(int $id, int $ownerId): void
    {
        Db::run('DELETE FROM projects WHERE id = ? AND owner_id = ?', [$id, $ownerId]);
    }

    public static function slugAvailable(string $slug): bool
    {
        return (int) Db::value('SELECT COUNT(*) FROM projects WHERE slug = ?', [$slug]) === 0;
    }

    public static function makeSlug(string $name): string
    {
        $base = mb_strtolower(trim($name));
        $base = preg_replace('/[^a-z0-9]+/u', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'project';
        }
        $base = mb_substr($base, 0, 32);
        $slug = $base;
        $counter = 1;
        while (!self::slugAvailable($slug)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public static function redirects(int $projectId): array
    {
        return Db::all('SELECT * FROM project_redirects WHERE project_id = ? ORDER BY id ASC', [$projectId]);
    }

    public static function addRedirect(int $projectId, string $uri): int
    {
        return Db::insert('project_redirects', [
            'project_id' => $projectId,
            'uri' => $uri,
        ]);
    }

    public static function removeRedirect(int $id, int $projectId): void
    {
        Db::run('DELETE FROM project_redirects WHERE id = ? AND project_id = ?', [$id, $projectId]);
    }

    public static function redirectExists(int $projectId, string $uri): bool
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM project_redirects WHERE project_id = ? AND uri = ?',
            [$projectId, $uri]
        ) > 0;
    }

    public static function credentials(int $projectId): array
    {
        return Db::all(
            'SELECT * FROM project_credentials WHERE project_id = ? ORDER BY revoked_at IS NULL DESC, id DESC',
            [$projectId]
        );
    }

    public static function createCredential(int $projectId, string $label, bool $isPublic = false): array
    {
        $clientId = Crypto::clientId();
        $secret = $isPublic ? null : Crypto::clientSecret();

        $id = Db::insert('project_credentials', [
            'project_id' => $projectId,
            'client_id' => $clientId,
            'secret_hash' => Crypto::hash($secret ?? Crypto::token(32)),
            'label' => $label,
            'is_public' => $isPublic ? 1 : 0,
        ]);

        return [
            'id' => $id,
            'client_id' => $clientId,
            'client_secret' => $secret,
            'label' => $label,
            'is_public' => $isPublic ? 1 : 0,
        ];
    }

    public static function revokeCredential(int $id, int $projectId): void
    {
        Db::run(
            'UPDATE project_credentials SET revoked_at = NOW() WHERE id = ? AND project_id = ? AND revoked_at IS NULL',
            [$id, $projectId]
        );
    }

    public static function findByClientId(string $clientId): ?array
    {
        return Db::one(
            'SELECT p.*, c.id AS credential_id, c.client_id, c.secret_hash, c.label, c.is_public,
                    p.status AS project_status,
                    a.status AS owner_status, a.status_until AS owner_status_until, a.status_reason AS owner_status_reason
             FROM project_credentials c
             INNER JOIN projects p ON p.id = c.project_id
             INNER JOIN accounts a ON a.id = p.owner_id
             WHERE c.client_id = ? AND c.revoked_at IS NULL
             LIMIT 1',
            [$clientId]
        );
    }

    public static function ownerAvailable(array $project): bool
    {
        return User::isAvailable([
            'status' => $project['owner_status'] ?? 'active',
            'status_until' => $project['owner_status_until'] ?? null,
        ]);
    }

    public static function configured(int $projectId): bool
    {
        return self::firstCredential($projectId) !== null
            && (int) Db::value('SELECT COUNT(*) FROM project_redirects WHERE project_id = ?', [$projectId]) > 0;
    }

    public static function touchCredential(int $credentialId): void
    {
        Db::run('UPDATE project_credentials SET last_used_at = NOW() WHERE id = ?', [$credentialId]);
    }

    public static function firstCredential(int $projectId): ?array
    {
        return Db::one(
            'SELECT * FROM project_credentials WHERE project_id = ? AND revoked_at IS NULL ORDER BY id ASC LIMIT 1',
            [$projectId]
        );
    }

    public static function consentCount(int $projectId): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM oauth_consents WHERE project_id = ?', [$projectId]);
    }

    public static function tokenCount(int $projectId): int
    {
        return (int) Db::value(
            'SELECT COUNT(*) FROM oauth_tokens WHERE project_id = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [$projectId]
        );
    }

    /** @return array{rows: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public static function directory(string $query = '', string $status = '', string $configured = '', int $page = 1, int $perPage = self::PAGE_SIZE): array
    {
        $conditions = [];
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $like = '%' . $query . '%';
            $conditions[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.domain LIKE ? OR a.display_name LIKE ? OR a.username LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (in_array($status, ['active', 'disabled'], true)) {
            $conditions[] = 'p.status = ?';
            $params[] = $status;
        }
        if (in_array($configured, ['yes', 'no'], true)) {
            $ready = '(EXISTS(SELECT 1 FROM project_credentials c WHERE c.project_id = p.id AND c.revoked_at IS NULL)
                       AND EXISTS(SELECT 1 FROM project_redirects r WHERE r.project_id = p.id))';
            $conditions[] = $configured === 'yes' ? $ready : 'NOT ' . $ready;
        }
        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $total = (int) Db::value(
            'SELECT COUNT(*) FROM projects p INNER JOIN accounts a ON a.id = p.owner_id ' . $where,
            $params
        );
        $perPage = max(1, min(50, $perPage));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $rows = Db::all(
            'SELECT p.*, a.display_name AS owner_name, a.username AS owner_username, a.avatar_type AS owner_avatar_type,
                    a.avatar_value AS owner_avatar_value, a.avatar_color AS owner_avatar_color, a.status AS owner_status,
                    a.status_until AS owner_status_until, a.warning_text AS owner_warning_text,
                    (SELECT COUNT(*) FROM oauth_consents oc WHERE oc.project_id = p.id) AS consent_count,
                    (EXISTS(SELECT 1 FROM project_credentials c WHERE c.project_id = p.id AND c.revoked_at IS NULL)
                     AND EXISTS(SELECT 1 FROM project_redirects r WHERE r.project_id = p.id)) AS is_configured
             FROM projects p
             INNER JOIN accounts a ON a.id = p.owner_id ' . $where . '
             ORDER BY p.created_at DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
