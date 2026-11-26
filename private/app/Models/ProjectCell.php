<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class ProjectCell
{
    public const MAX_BYTES = 262144;
    public const MAX_KEY = 120;

    public static function findById(int $id): ?array
    {
        return $id > 0 ? Db::one('SELECT * FROM project_cells WHERE id = ? LIMIT 1', [$id]) : null;
    }

    public static function findByScope(int $projectId, int $accountId, string $key): ?array
    {
        return Db::one(
            'SELECT * FROM project_cells WHERE project_id = ? AND account_id = ? AND cell_key = ? LIMIT 1',
            [$projectId, $accountId, $key]
        );
    }

    /** @return array{rows: list<array<string, mixed>>, page: int, pages: int, total: int} */
    public static function list(int $projectId, int $accountId = -1, string $key = '', int $page = 1, int $limit = 50): array
    {
        $where = ['project_id = ?'];
        $params = [$projectId];
        if ($accountId >= 0) {
            $where[] = 'account_id = ?';
            $params[] = $accountId;
        }
        if ($key !== '') {
            $where[] = 'cell_key = ?';
            $params[] = $key;
        }
        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $total = (int) Db::value('SELECT COUNT(*) FROM project_cells ' . $sqlWhere, $params);
        $limit = max(1, min(100, $limit));
        $pages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $limit;
        $rows = Db::all(
            'SELECT * FROM project_cells ' . $sqlWhere . ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        return ['rows' => $rows, 'page' => $page, 'pages' => $pages, 'total' => $total];
    }

    public static function validateKey(string $key): ?string
    {
        if ($key === '' || mb_strlen($key) > self::MAX_KEY) {
            return 'key must be 1-' . self::MAX_KEY . ' characters.';
        }
        if (preg_match('/^[A-Za-z0-9._:-]+$/', $key) !== 1) {
            return 'key may contain only letters, numbers, dot, underscore, colon, and hyphen.';
        }
        return null;
    }

    public static function validateValue(string $value): ?string
    {
        if (strlen($value) > self::MAX_BYTES) {
            return 'value must be at most 256KB.';
        }
        return null;
    }

    /** Create or update a cell in the given project+account scope. account_id 0 = project scope. */
    public static function upsert(int $projectId, int $accountId, string $key, string $value): array
    {
        $existing = self::findByScope($projectId, $accountId, $key);
        $bytes = strlen($value);
        if ($existing !== null) {
            Db::run(
                'UPDATE project_cells SET cell_value = ?, value_bytes = ?, updated_at = NOW() WHERE id = ?',
                [$value, $bytes, (int) $existing['id']]
            );
            return (array) self::findById((int) $existing['id']);
        }
        $id = Db::insert('project_cells', [
            'project_id' => $projectId,
            'account_id' => $accountId,
            'cell_key' => $key,
            'cell_value' => $value,
            'value_bytes' => $bytes,
        ]);
        return (array) self::findById((int) $id);
    }

    public static function updateValue(int $id, string $value): ?array
    {
        Db::run('UPDATE project_cells SET cell_value = ?, value_bytes = ?, updated_at = NOW() WHERE id = ?', [$value, strlen($value), $id]);
        return self::findById($id);
    }

    public static function delete(int $id): void
    {
        Db::run('DELETE FROM project_cells WHERE id = ?', [$id]);
    }

    /** @return array<string, mixed> */
    public static function serialize(array $row): array
    {
        $accountId = (int) ($row['account_id'] ?? 0);
        return [
            'id' => (int) $row['id'],
            'key' => (string) $row['cell_key'],
            'value' => (string) $row['cell_value'],
            'bytes' => (int) ($row['value_bytes'] ?? strlen((string) ($row['cell_value'] ?? ''))),
            'account_id' => $accountId > 0 ? $accountId : null,
            'scope' => $accountId > 0 ? 'user' : 'project',
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
