<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Db;

final class Showcase
{
    public const LIMIT = 3;
    public const SLOT_MAX = 10;
    public const SLOT_COST = 15;
    public const TYPES = ['text', 'projects', 'badges', 'media', 'repos', 'links', 'reviews', 'friends', 'bestfriends', 'team'];
    public const FIXED_TYPES = ['badges', 'friends'];
    public const MEDIA_COST = 20;
    public const REVIEW_MAX = 5;
    public const WIDTHS = [
        'mini' => 'Mini (one quarter)',
        'normal' => 'Normal (one column)',
        'wide' => 'Wide (two columns)',
    ];
    public const DEFAULT_TITLES = [
        'projects' => 'My projects',
        'badges' => 'Badge Earned',
        'repos' => 'Repositories',
        'media' => 'Media',
        'links' => 'Links',
        'reviews' => 'Project reviews',
        'friends' => 'Friends',
        'bestfriends' => 'Best friends',
        'team' => 'Teams',
    ];

    /** New accounts start with the two showcases every profile has. */
    public static function createDefaults(int $accountId): void
    {
        foreach (['badges', 'friends'] as $type) {
            self::create($accountId, $type, self::DEFAULT_TITLES[$type], null);
        }
    }

    public static function slotsFor(array $account): int
    {
        return max(self::LIMIT, (int) ($account['showcase_slots'] ?? self::LIMIT));
    }

    public static function forAccount(int $accountId): array
    {
        return Db::all(
            'SELECT * FROM showcases WHERE account_id = ? ORDER BY sort_order ASC, id ASC',
            [$accountId]
        );
    }

    public static function visibleForAccount(int $accountId): array
    {
        return Db::all(
            'SELECT * FROM showcases WHERE account_id = ? AND is_visible = 1 ORDER BY sort_order ASC, id ASC',
            [$accountId]
        );
    }

    /** Persist the order sent by the studio/classic drag handle. Unknown ids are ignored. */
    public static function reorder(int $accountId, array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $owned = array_map('intval', array_column(self::forAccount($accountId), 'id'));
        $position = 0;
        foreach ($ids as $id) {
            if (!in_array($id, $owned, true)) {
                continue;
            }
            Db::run(
                'UPDATE showcases SET sort_order = ? WHERE id = ? AND account_id = ?',
                [++$position, $id, $accountId]
            );
        }
        foreach (array_diff($owned, $ids) as $id) {
            Db::run(
                'UPDATE showcases SET sort_order = ? WHERE id = ? AND account_id = ?',
                [++$position, $id, $accountId]
            );
        }
    }

    public static function find(int $id, int $accountId): ?array
    {
        return Db::one('SELECT * FROM showcases WHERE id = ? AND account_id = ? LIMIT 1', [$id, $accountId]);
    }

    public static function count(int $accountId): int
    {
        return (int) Db::value("SELECT COUNT(*) FROM showcases WHERE account_id = ? AND type <> 'team'", [$accountId]);
    }

    public static function create(int $accountId, string $type, string $title, ?string $body, ?string $meta = null): int
    {
        $next = (int) Db::value('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM showcases WHERE account_id = ?', [$accountId]);

        return Db::insert('showcases', [
            'account_id' => $accountId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'meta' => $meta,
            'is_visible' => 1,
            'sort_order' => $next,
        ]);
    }

    public static function meta(array $showcase): array
    {
        $meta = json_decode((string) ($showcase['meta'] ?? ''), true);

        return is_array($meta) ? $meta : [];
    }

    public static function width(array $showcase): string
    {
        $width = (string) (self::meta($showcase)['width'] ?? '');

        return isset(self::WIDTHS[$width]) ? $width : 'normal';
    }

    public static function opacity(array $showcase): int
    {
        $opacity = (int) (self::meta($showcase)['opacity'] ?? 100);

        return max(20, min(100, $opacity));
    }

    public static function setMeta(array $showcase, array $meta): void
    {
        Db::run('UPDATE showcases SET meta = ? WHERE id = ? AND account_id = ?', [
            json_encode($meta), (int) $showcase['id'], (int) $showcase['account_id'],
        ]);
    }

    public static function setWidth(array $showcase, string $width): void
    {
        $meta = self::meta($showcase);
        $meta['width'] = isset(self::WIDTHS[$width]) ? $width : 'normal';
        Db::run(
            'UPDATE showcases SET meta = ? WHERE id = ? AND account_id = ?',
            [json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $showcase['id'], (int) $showcase['account_id']]
        );
    }

    /** @return array<int, array{project: string, url: string, rating: int, text: string}> */
    public static function reviews(array $showcase): array
    {
        $rows = self::meta($showcase)['reviews'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    public static function addReview(array $showcase, array $review): bool
    {
        $meta = self::meta($showcase);
        $reviews = self::reviews($showcase);
        if (count($reviews) >= self::REVIEW_MAX) {
            return false;
        }

        $reviews[] = $review;
        $meta['reviews'] = $reviews;
        Db::run(
            'UPDATE showcases SET meta = ? WHERE id = ? AND account_id = ?',
            [json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $showcase['id'], (int) $showcase['account_id']]
        );

        return true;
    }

    public static function deleteReview(array $showcase, int $index): void
    {
        $meta = self::meta($showcase);
        $reviews = self::reviews($showcase);
        if (!isset($reviews[$index])) {
            return;
        }

        array_splice($reviews, $index, 1);
        $meta['reviews'] = array_values($reviews);
        Db::run(
            'UPDATE showcases SET meta = ? WHERE id = ? AND account_id = ?',
            [json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $showcase['id'], (int) $showcase['account_id']]
        );
    }

    public static function buySlot(array $account): bool
    {
        if ((int) ($account['points'] ?? 0) < self::SLOT_COST || self::slotsFor($account) >= self::SLOT_MAX) {
            return false;
        }

        $updated = Db::run(
            'UPDATE accounts SET points = points - ?, showcase_slots = showcase_slots + 1
             WHERE id = ? AND points >= ? AND showcase_slots < ?',
            [self::SLOT_COST, (int) $account['id'], self::SLOT_COST, self::SLOT_MAX]
        );

        return $updated->rowCount() === 1;
    }

    public static function delete(int $id, int $accountId): void
    {
        Db::run('DELETE FROM showcases WHERE id = ? AND account_id = ?', [$id, $accountId]);
    }

    public static function setVisibility(int $accountId, int $showcaseId, bool $visible): void
    {
        Db::run(
            'UPDATE showcases SET is_visible = ? WHERE id = ? AND account_id = ?',
            [$visible ? 1 : 0, $showcaseId, $accountId]
        );
    }

    public static function setBody(int $id, int $accountId, ?string $body): void
    {
        Db::run('UPDATE showcases SET body = ? WHERE id = ? AND account_id = ?', [$body, $id, $accountId]);
    }

    public static function findType(int $accountId, string $type): ?array
    {
        return Db::one('SELECT * FROM showcases WHERE account_id = ? AND type = ? LIMIT 1', [$accountId, $type]);
    }

    /** Private profiles disable showcases but keep their previous visibility for later. */
    public static function saveVisible(int $accountId): void
    {
        foreach (self::forAccount($accountId) as $showcase) {
            $meta = self::meta($showcase);
            $meta['saved_visible'] = (int) $showcase['is_visible'] === 1;
            self::writeMeta($showcase, $meta);
            self::setVisibility($accountId, (int) $showcase['id'], false);
        }
    }

    public static function restoreVisible(int $accountId): void
    {
        foreach (self::forAccount($accountId) as $showcase) {
            $meta = self::meta($showcase);
            if (!array_key_exists('saved_visible', $meta)) {
                continue;
            }
            $visible = (bool) $meta['saved_visible'];
            unset($meta['saved_visible']);
            self::writeMeta($showcase, $meta);
            self::setVisibility($accountId, (int) $showcase['id'], $visible);
        }
    }

    private static function writeMeta(array $showcase, array $meta): void
    {
        Db::run(
            'UPDATE showcases SET meta = ? WHERE id = ? AND account_id = ?',
            [json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $showcase['id'], (int) $showcase['account_id']]
        );
    }
}
