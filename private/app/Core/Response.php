<?php
declare(strict_types=1);

namespace BBA\Core;

final class Response
{
    public static function send(string $body, int $status = 200, array $headers = []): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $body;
        exit;
    }

    public static function html(string $body, int $status = 200, array $headers = []): never
    {
        self::send($body, $status, array_merge([
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
        ], $headers));
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            $body = '{"error":"server_error"}';
            $status = 500;
        }

        self::send($body, $status, array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ], $headers));
    }

    public static function redirect(string $location, int $status = 302): never
    {
        $location = str_replace(["\r", "\n"], '', $location);

        self::send('', $status, [
            'Location' => $location,
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function cors(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Max-Age: 86400');
    }
}
