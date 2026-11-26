<?php
declare(strict_types=1);

namespace BBA\Core;

final class I18n
{
    private static string $language = 'en';
    private static array $strings = [];

    public static function init(): void
    {
        self::load('en');
        $lang = (string) ($_COOKIE['bb_lang'] ?? '');
        if ($lang !== '' && $lang !== 'en' && self::pathAllowsLanguage() && self::set($lang)) {
            return;
        }
    }

    /** Languages from config, keyed by code, each with a name and a planned flag. */
    public static function available(): array
    {
        $locales = (array) Config::get('locales', []);
        if ($locales === []) {
            return ['en' => ['name' => 'English', 'planned' => false]];
        }

        return $locales;
    }

    public static function language(): string
    {
        return self::$language;
    }

    public static function set(string $language): bool
    {
        $available = self::available();
        if (!isset($available[$language]) || !empty($available[$language]['planned'])) {
            return false;
        }

        self::load($language);

        return true;
    }

    public static function get(string $key): string
    {
        return self::$strings[$key] ?? $key;
    }

    public static function remember(string $language): void
    {
        if (headers_sent()) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        setcookie('bb_lang', $language, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Beta language applies outside account/profile surfaces. */
    public static function pathAllowsLanguage(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? '/' . ltrim($path, '/') : '/';
        return !preg_match('#^/(account|u)(/|$)#', $path);
    }

    private static function load(string $language): void
    {
        self::$language = $language;
        self::$strings = [];
        $file = BBA_PRIVATE . '/lang/' . $language . '.php';
        if (is_file($file)) {
            $strings = require $file;
            if (is_array($strings)) {
                self::$strings = $strings;
            }
        }
    }
}
