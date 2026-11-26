<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class Ticket
{
    public const TYPES = ['tester', 'developer', 'admin', 'deletion', 'help'];
    public const LABELS = [
        'tester' => 'Tester applications',
        'developer' => 'Developer applications',
        'admin' => 'Administrator applications',
        'deletion' => 'Deletion requests',
        'help' => 'Support chat',
    ];

    public static function create(int $accountId, string $type, string $body): int
    {
        $id = Db::insert('tickets', ['account_id' => $accountId, 'type' => $type]);
        self::message($id, $accountId, $body);

        return $id;
    }

    public static function open(string $type): array
    {
        return Db::all(
            'SELECT t.*, a.display_name, a.username, a.email,
                    (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS message_count
             FROM tickets t
             INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.type = ? AND t.status = \'open\'
             ORDER BY t.id DESC',
            [$type]
        );
    }

    public static function openCount(): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM tickets WHERE status = \'open\'');
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT t.*, a.display_name, a.username, a.email
             FROM tickets t INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.id = ? LIMIT 1',
            [$id]
        );
    }

    public static function forAccount(int $accountId, string $type): array
    {
        return Db::all(
            'SELECT * FROM tickets WHERE account_id = ? AND type = ? ORDER BY id DESC LIMIT 20',
            [$accountId, $type]
        );
    }

    public static function hasOpen(int $accountId, string $type): bool
    {
        return self::openId($accountId, $type) !== null;
    }

    public static function openId(int $accountId, string $type): ?int
    {
        $id = Db::value(
            'SELECT id FROM tickets WHERE account_id = ? AND type = ? AND status = \'open\' ORDER BY id DESC LIMIT 1',
            [$accountId, $type]
        );

        return $id === null || $id === false ? null : (int) $id;
    }

    /** @return array<int, array<string, mixed>> */
    public static function messages(int $ticketId): array
    {
        return Db::all(
            'SELECT m.*, a.display_name, a.username, a.avatar_type, a.avatar_value, a.avatar_color, a.updated_at,
                    a.is_admin, a.is_moderator
             FROM ticket_messages m
             INNER JOIN accounts a ON a.id = m.account_id
             WHERE m.ticket_id = ? ORDER BY m.id ASC',
            [$ticketId]
        );
    }

    public static function message(int $ticketId, int $accountId, string $body): void
    {
        Db::insert('ticket_messages', [
            'ticket_id' => $ticketId,
            'account_id' => $accountId,
            'body' => mb_substr(trim($body), 0, 2000),
        ]);
        Db::run('UPDATE tickets SET status = \'open\', resolved_at = NULL WHERE id = ?', [$ticketId]);
    }

    public static function resolve(int $id): void
    {
        Db::run('UPDATE tickets SET status = \'resolved\', resolved_at = NOW() WHERE id = ?', [$id]);
    }
}
