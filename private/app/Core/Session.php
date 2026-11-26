<?php
declare(strict_types=1);

namespace BBA\Core;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;

            return;
        }

        $secure = (bool) Config::get('session.secure', true);
        $name = (string) Config::get('session.name', 'bb_ui');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        $savePath = BBA_PRIVATE . '/storage/sessions';
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0775, true);
        }
        if (!is_dir($savePath) || !is_writable($savePath)) {
            $savePath = rtrim(sys_get_temp_dir(), '/\\') . '/bbaccounts_sessions';
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0770, true);
            }
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
        session_start();
        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function readFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash'][$key] ?? $default;
    }

    public static function forgetFlash(string $key): void
    {
        unset($_SESSION['_flash'][$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$started = false;
    }

    public static function intended(string $path): void
    {
        if ($path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')) {
            self::set('_intended', $path);
        }
    }

    public static function pullIntended(string $default = '/account'): string
    {
        $path = self::pull('_intended', $default);

        return is_string($path) && $path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')
            ? $path
            : $default;
    }

    private const ONE_SHOT = ['_errors', '_old', '_success', '_recovery_codes', '_new_secret', '_project_secret'];

    /** Keys already pending at request start; those set during the request survive for the next one. */
    public static function pending(): array
    {
        return array_values(array_filter(self::ONE_SHOT, static fn (string $key): bool => array_key_exists($key, $_SESSION)));
    }

    public static function sweep(array $keys = []): void
    {
        foreach ($keys as $key) {
            self::forget($key);
        }
    }
}
