<?php
declare(strict_types=1);

namespace BBA\Core;

final class Request
{
    private static ?array $json = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        $json = self::json();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $_GET[$key] ?? $default;
    }

    public static function json(): ?array
    {
        if (self::$json !== null) {
            return self::$json;
        }

        $type = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (!str_contains(strtolower($type), 'application/json')) {
            self::$json = [];

            return self::$json;
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw === false ? '' : $raw, true);
        self::$json = is_array($decoded) ? $decoded : [];

        return self::$json;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        if ($value !== null) {
            return (string) $value;
        }

        if (strcasecmp($name, 'Content-Type') === 0) {
            return isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : null;
        }

        if (strcasecmp($name, 'Authorization') === 0) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION', 'AUTHORIZATION'] as $serverKey) {
                $value = $_SERVER[$serverKey] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }
            $value = $_SERVER['HTTP_X_AUTHORIZATION'] ?? $_SERVER['HTTP_X_HTTP_AUTHORIZATION'] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
            if (function_exists('apache_request_headers')) {
                foreach (apache_request_headers() as $headerName => $headerValue) {
                    if (strcasecmp((string) $headerName, 'Authorization') === 0 && is_string($headerValue) && trim($headerValue) !== '') {
                        return $headerValue;
                    }
                }
            }
        }

        return null;
    }

    public static function bearerToken(): ?string
    {
        $header = self::header('Authorization');
        if ($header !== null && preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return trim($matches[1]);
        }

        foreach (['access_token', 'token'] as $key) {
            $value = $_GET[$key] ?? $_POST[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    public static function ip(): string
    {
        if ((bool) Config::get('trust_proxy', false)) {
            $forwarded = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            $candidate = trim(explode(',', (string) $forwarded)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                return $candidate;
            }
        }

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function wantsJson(): bool
    {
        if (str_starts_with(self::path(), '/oauth/')) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_contains($accept, 'application/json');
    }

    public static function fullUrl(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return 'https://' . $host . $uri;
    }
}
