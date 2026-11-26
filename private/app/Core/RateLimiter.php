<?php
declare(strict_types=1);

namespace BBA\Core;

final class RateLimiter
{
    public static function attempt(string $bucket, string $key, int $max, int $window): bool
    {
        if (random_int(1, 200) === 1) {
            Db::run('DELETE FROM rate_limits WHERE reset_at < NOW()');
        }

        $key = mb_substr($key, 0, 120);
        $resetAt = date('Y-m-d H:i:s', time() + $window);

        Db::run(
            'INSERT INTO rate_limits (bucket, rl_key, attempts, reset_at) VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE
                attempts = IF(reset_at <= NOW(), 1, attempts + 1),
                reset_at = IF(reset_at <= NOW(), VALUES(reset_at), reset_at)',
            [$bucket, $key, $resetAt]
        );

        $attempts = (int) Db::value(
            'SELECT attempts FROM rate_limits WHERE bucket = ? AND rl_key = ?',
            [$bucket, $key]
        );

        return $attempts <= $max;
    }

    public static function clear(string $bucket, string $key): void
    {
        Db::run('DELETE FROM rate_limits WHERE bucket = ? AND rl_key = ?', [$bucket, mb_substr($key, 0, 120)]);
    }
}
