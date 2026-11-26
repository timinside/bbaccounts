<?php
declare(strict_types=1);

namespace BBA\Services;

use BBA\Core\Config;

final class ForumNews
{
    /** @return array{items: array<int, array{id:int,title:string,url:string,created_at:string,excerpt:string,comments:int}>, error: ?string} */
    public static function latest(): array
    {
        $base = rtrim((string) Config::get('forum.base_url', ''), '/');
        if ($base === '') {
            return ['items' => [], 'error' => null];
        }

        $ttl = max(60, (int) Config::get('forum.cache_ttl', 600));
        $cacheFile = BBA_PRIVATE . '/storage/cache/forum-news.json';
        $cached = self::readCache($cacheFile, $ttl);
        if ($cached !== null) {
            return ['items' => $cached, 'error' => null];
        }

        $result = self::fetch($base);
        if ($result['items'] !== []) {
            self::writeCache($cacheFile, $result['items']);
        }

        return $result;
    }

    /** @return array<int, array{id:int,title:string,url:string,created_at:string,excerpt:string,comments:int}>|null */
    private static function readCache(string $file, int $ttl): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $age = time() - (int) filemtime($file);
        if ($age > $ttl) {
            return null;
        }
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json) || !array_key_exists('items', $json) || !is_array($json['items'])) {
            return null;
        }

        return $json['items'];
    }

    /** @param array<int, array{id:int,title:string,url:string,created_at:string,excerpt:string,comments:int}> $items */
    private static function writeCache(string $file, array $items): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode(['items' => $items, 'cached_at' => time()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** @return array{items: array<int, array{id:int,title:string,url:string,created_at:string,excerpt:string,comments:int}>, error: ?string} */
    private static function fetch(string $base): array
    {
        $tag = trim((string) Config::get('forum.news_tag', 'bbaccounts'));
        $limit = max(1, min(10, (int) Config::get('forum.news_limit', 5)));
        $api = $base . '/api/discussions?' . http_build_query([
            'filter[tag]' => $tag,
            'sort' => '-createdAt',
            'page[limit]' => $limit,
        ]);

        [$body, $error] = self::httpGet($api);
        if ($body === null) {
            $stale = self::staleCache();
            if ($stale !== []) {
                return ['items' => $stale, 'error' => null];
            }
            return ['items' => [], 'error' => $error ?? 'Request failed.'];
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data'])) {
            $stale = self::staleCache();
            if ($stale !== []) {
                return ['items' => $stale, 'error' => null];
            }
            return ['items' => [], 'error' => 'Unexpected response from forum API.'];
        }

        $items = [];
        foreach ($json['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $attr = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
            $id = (int) ($row['id'] ?? 0);
            $title = trim((string) ($attr['title'] ?? ''));
            if ($id <= 0 || $title === '') {
                continue;
            }
            $slug = trim((string) ($attr['slug'] ?? ''));
            $items[] = [
                'id' => $id,
                'title' => $title,
                'url' => $base . '/d/' . $id . ($slug !== '' ? '-' . rawurlencode($slug) : ''),
                'created_at' => (string) ($attr['createdAt'] ?? $attr['lastPostedAt'] ?? ''),
                'excerpt' => trim((string) ($attr['excerpt'] ?? '')),
                'comments' => max(0, (int) ($attr['commentCount'] ?? 0) - 1),
            ];
        }

        return ['items' => $items, 'error' => null];
    }

    /** @return array<int, array{id:int,title:string,url:string,created_at:string,excerpt:string,comments:int}> */
    private static function staleCache(): array
    {
        $file = BBA_PRIVATE . '/storage/cache/forum-news.json';
        if (!is_file($file)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json) || !is_array($json['items'] ?? null)) {
            return [];
        }

        return $json['items'];
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function httpGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return [null, 'curl_init failed.'];
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_USERAGENT => 'bbaccounts/2.0 (+https://bbaccounts.unaux.com)',
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.api+json, application/json'],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if (is_string($body) && $status >= 200 && $status < 300) {
                return [$body, null];
            }
            if (!is_string($body) || $body === false) {
                return [null, 'cURL error: ' . ($err !== '' ? $err : 'empty response')];
            }
            return [null, 'HTTP ' . $status . ' from forum API.'];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/vnd.api+json, application/json\r\nUser-Agent: bbaccounts/2.0\r\n",
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            return [null, 'HTTP request failed (no curl, empty response).'];
        }
        $statusLine = $http_response_header[0] ?? '';
        if (is_string($statusLine) && preg_match('/\s(\d{3})\s/', $statusLine, $m) && ((int) $m[1] < 200 || (int) $m[1] >= 300)) {
            return [null, 'HTTP ' . $m[1] . ' from forum API.'];
        }

        return [$body, null];
    }
}
