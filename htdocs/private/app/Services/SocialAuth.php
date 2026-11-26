<?php
declare(strict_types=1);

namespace BBA\Services;

use BBA\Core\Config;

final class SocialAuth
{
    private const PROVIDERS = [
        'google' => [
            'name' => 'Google',
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'scope' => 'openid email profile',
        ],
        'github' => [
            'name' => 'GitHub',
            'authorize' => 'https://github.com/login/oauth/authorize',
            'token' => 'https://github.com/login/oauth/access_token',
            'scope' => 'read:user user:email',
        ],
        'discord' => [
            'name' => 'Discord',
            'authorize' => 'https://discord.com/oauth2/authorize',
            'token' => 'https://discord.com/api/oauth2/token',
            'scope' => 'identify email',
        ],
        'yandex' => [
            'name' => 'Yandex',
            'authorize' => 'https://oauth.yandex.ru/authorize',
            'token' => 'https://oauth.yandex.ru/token',
            'scope' => 'login:email login:info',
        ],
        'steam' => [
            'name' => 'Steam',
            'authorize' => 'https://steamcommunity.com/openid/login',
            'scope' => '',
        ],
        'microsoft' => [
            'name' => 'Microsoft',
            'authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'scope' => 'openid email profile User.Read',
        ],
        'gitlab' => [
            'name' => 'GitLab',
            'authorize' => 'https://gitlab.com/oauth/authorize',
            'token' => 'https://gitlab.com/oauth/token',
            'scope' => 'read_user',
        ],
        'apple' => [
            'name' => 'Apple',
            'authorize' => 'https://appleid.apple.com/auth/authorize',
            'token' => 'https://appleid.apple.com/auth/token',
            'scope' => 'name email',
        ],
    ];

    public static function names(): array
    {
        return array_map(static fn (array $provider): string => $provider['name'], self::PROVIDERS);
    }

    public static function exists(string $provider): bool
    {
        return isset(self::PROVIDERS[$provider]);
    }

    public static function name(string $provider): string
    {
        return self::PROVIDERS[$provider]['name'] ?? ucfirst($provider);
    }

    public static function placeholder(string $provider): bool
    {
        return (bool) (self::PROVIDERS[$provider]['placeholder'] ?? false);
    }

    public static function enabled(string $provider): bool
    {
        if (!self::exists($provider) || self::placeholder($provider)) {
            return false;
        }
        if ($provider === 'steam') {
            return self::clientId($provider) !== '';
        }
        if ($provider === 'apple') {
            return self::clientId($provider) !== ''
                && self::appleConfig('team_id') !== ''
                && self::appleConfig('key_id') !== ''
                && self::appleConfig('private_key') !== '';
        }

        return self::clientId($provider) !== '' && self::clientSecret($provider) !== '';
    }

    public static function clientId(string $provider): string
    {
        return trim((string) Config::get('social.' . $provider . '.client_id', ''));
    }

    private static function clientSecret(string $provider): string
    {
        return trim((string) Config::get('social.' . $provider . '.client_secret', ''));
    }

    private static function appleConfig(string $key): string
    {
        $value = (string) Config::get('social.apple.' . $key, '');

        return trim(str_replace('\\n', "\n", $value));
    }

    public static function authorizeUrl(string $provider, string $state): string
    {
        $config = self::PROVIDERS[$provider];
        $callback = url('/oauth/social/' . $provider . '/callback');

        if ($provider === 'steam') {
            $realm = rtrim((string) Config::get('url', ''), '/');
            if ($realm === '') {
                $realm = preg_replace('~/oauth/social/steam/callback$~', '', $callback) ?: $callback;
            }

            return $config['authorize'] . '?' . http_build_query([
                'openid.ns' => 'http://specs.openid.net/auth/2.0',
                'openid.mode' => 'checkid_setup',
                'openid.return_to' => $callback . '?state=' . rawurlencode($state),
                'openid.realm' => $realm,
                'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
                'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
            ], '', '&', PHP_QUERY_RFC3986);
        }

        $params = [
            'client_id' => self::clientId($provider),
            'redirect_uri' => $callback,
            'response_type' => 'code',
            'scope' => $config['scope'],
            'state' => $state,
        ];
        if ($provider === 'apple') {
            $params['response_mode'] = 'form_post';
        }

        return $config['authorize'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public static function profile(string $provider, string $code, array $query = []): array
    {
        if ($provider === 'steam') {
            return self::steamProfile($query);
        }
        if ($provider === 'apple') {
            return self::appleProfile($code, $query);
        }

        $token = self::exchange($provider, $code);

        return match ($provider) {
            'google' => self::googleProfile($token),
            'discord' => self::discordProfile($token),
            'yandex' => self::yandexProfile($token),
            'microsoft' => self::microsoftProfile($token),
            'gitlab' => self::gitlabProfile($token),
            default => self::githubProfile($token),
        };
    }

    private static function exchange(string $provider, string $code): string
    {
        $payload = [
            'client_id' => self::clientId($provider),
            'client_secret' => self::clientSecret($provider),
            'code' => $code,
            'redirect_uri' => url('/oauth/social/' . $provider . '/callback'),
            'grant_type' => 'authorization_code',
        ];
        $data = self::request('POST', self::PROVIDERS[$provider]['token'], $payload);

        $token = (string) ($data['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('The provider did not return an access token.');
        }

        return $token;
    }

    private static function googleProfile(string $token): array
    {
        $data = self::request('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [], [
            'Authorization: Bearer ' . $token,
        ]);

        $id = (string) ($data['sub'] ?? '');
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($id === '' || $email === '' || ($data['email_verified'] ?? false) !== true) {
            throw new \RuntimeException('Google did not return a verified email address.');
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => trim((string) ($data['name'] ?? '')),
            'username' => '',
        ];
    }

    private static function discordProfile(string $token): array
    {
        $user = self::request('GET', 'https://discord.com/api/users/@me', [], [
            'Authorization: Bearer ' . $token,
        ]);

        $id = (string) ($user['id'] ?? '');
        $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
        if ($id === '' || $email === '' || ($user['verified'] ?? false) !== true) {
            throw new \RuntimeException('Discord did not return a verified email address.');
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => trim((string) (($user['global_name'] ?? '') ?: ($user['username'] ?? ''))),
            'username' => trim((string) ($user['username'] ?? '')),
        ];
    }

    private static function yandexProfile(string $token): array
    {
        // Yandex expects the OAuth scheme, not Bearer.
        $data = self::request('GET', 'https://login.yandex.ru/info?format=json', [], [
            'Authorization: OAuth ' . $token,
        ]);

        $id = (string) ($data['id'] ?? '');
        $email = mb_strtolower(trim((string) ($data['default_email'] ?? '')));
        if ($id === '' || $email === '') {
            throw new \RuntimeException('Yandex did not return an email address.');
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => trim((string) (($data['display_name'] ?? '') ?: ($data['login'] ?? ''))),
            'username' => trim((string) ($data['login'] ?? '')),
        ];
    }

    private static function githubProfile(string $token): array
    {
        $headers = ['Authorization: Bearer ' . $token];
        $user = self::request('GET', 'https://api.github.com/user', [], $headers);
        $emails = self::request('GET', 'https://api.github.com/user/emails', [], $headers);

        $id = (string) ($user['id'] ?? '');
        $email = '';
        if (is_array($emails)) {
            foreach ($emails as $row) {
                if (is_array($row) && ($row['verified'] ?? false) === true && ($row['primary'] ?? false) === true) {
                    $email = mb_strtolower(trim((string) ($row['email'] ?? '')));
                    break;
                }
            }
        }

        if ($id === '' || $email === '') {
            throw new \RuntimeException('GitHub did not return a verified email address.');
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => trim((string) (($user['name'] ?? '') ?: ($user['login'] ?? ''))),
            'username' => trim((string) ($user['login'] ?? '')),
        ];
    }

    private static function microsoftProfile(string $token): array
    {
        $data = self::request('GET', 'https://graph.microsoft.com/v1.0/me', [], [
            'Authorization: Bearer ' . $token,
        ]);

        $id = (string) ($data['id'] ?? '');
        $email = mb_strtolower(trim((string) ($data['mail'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = mb_strtolower(trim((string) ($data['userPrincipalName'] ?? '')));
        }
        if ($id === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \RuntimeException('Microsoft did not return an email address.');
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => trim((string) ($data['displayName'] ?? '')),
            'username' => '',
        ];
    }

    private static function gitlabProfile(string $token): array
    {
        $data = self::request('GET', 'https://gitlab.com/api/v4/user', [], [
            'Authorization: Bearer ' . $token,
        ]);

        $id = (string) ($data['id'] ?? '');
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $confirmed = $data['confirmed_at'] ?? null;
        if ($id === '' || $email === '' || $confirmed === null || $confirmed === '') {
            throw new \RuntimeException('GitLab did not return a verified email address.');
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => trim((string) (($data['name'] ?? '') ?: ($data['username'] ?? ''))),
            'username' => trim((string) ($data['username'] ?? '')),
        ];
    }

    private static function steamProfile(array $query): array
    {
        $claimed = (string) ($query['openid_claimed_id'] ?? '');
        if (!preg_match('~^https://steamcommunity\.com/openid/id/(\d{17})$~', $claimed, $m)) {
            throw new \RuntimeException('Steam did not return a valid identity.');
        }
        $steamId = $m[1];

        $params = [];
        foreach ($query as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'openid.') && is_string($value)) {
                $params[$key] = $value;
            }
        }
        $params['openid.mode'] = 'check_authentication';
        $response = self::requestRaw('POST', 'https://steamcommunity.com/openid/login', $params);
        if (!str_contains($response, 'is_valid:true')) {
            throw new \RuntimeException('Steam identity verification failed. The hosting server may be blocking outbound HTTPS to steamcommunity.com.');
        }

        $name = 'Steam user ' . $steamId;
        $username = $steamId;
        $apiKey = self::clientId('steam');
        if ($apiKey !== '') {
            try {
                $data = self::request('GET', 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?' . http_build_query([
                    'key' => $apiKey,
                    'steamids' => $steamId,
                ]));
                $player = $data['response']['players'][0] ?? null;
                if (is_array($player)) {
                    $name = trim((string) ($player['personaname'] ?? $name)) ?: $name;
                    $username = trim((string) ($player['personaname'] ?? $steamId)) ?: $steamId;
                }
            } catch (\Throwable $e) {
                // Keep OpenID identity if profile fetch fails.
            }
        }

        return [
            'id' => $steamId,
            'email' => '',
            'name' => $name,
            'username' => $username,
        ];
    }

    private static function appleProfile(string $code, array $query): array
    {
        $data = self::request('POST', self::PROVIDERS['apple']['token'], [
            'client_id' => self::clientId('apple'),
            'client_secret' => self::appleClientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => url('/oauth/social/apple/callback'),
        ]);

        $idToken = (string) ($data['id_token'] ?? '');
        if ($idToken === '') {
            throw new \RuntimeException('Apple did not return an identity token.');
        }

        $claims = self::jwtPayload($idToken);
        $id = (string) ($claims['sub'] ?? '');
        $email = mb_strtolower(trim((string) ($claims['email'] ?? '')));
        $verified = $claims['email_verified'] ?? false;
        $verified = $verified === true || $verified === 'true';
        if ($id === '' || $email === '' || !$verified) {
            throw new \RuntimeException('Apple did not return a verified email address.');
        }

        $name = '';
        $user = $query['user'] ?? '';
        if (is_string($user) && $user !== '') {
            $parsed = json_decode($user, true);
            if (is_array($parsed) && is_array($parsed['name'] ?? null)) {
                $first = trim((string) ($parsed['name']['firstName'] ?? ''));
                $last = trim((string) ($parsed['name']['lastName'] ?? ''));
                $name = trim($first . ' ' . $last);
            }
        }

        return [
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'username' => '',
        ];
    }

    private static function appleClientSecret(): string
    {
        $team = self::appleConfig('team_id');
        $keyId = self::appleConfig('key_id');
        $key = self::appleConfig('private_key');
        if ($team === '' || $keyId === '' || $key === '' || self::clientId('apple') === '') {
            throw new \RuntimeException('Apple sign-in is not configured.');
        }

        $now = time();
        $header = self::base64url((string) json_encode(['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $payload = self::base64url((string) json_encode([
            'iss' => $team,
            'iat' => $now,
            'exp' => $now + 86400 * 180,
            'aud' => 'https://appleid.apple.com',
            'sub' => self::clientId('apple'),
        ], JSON_UNESCAPED_SLASHES));
        $data = $header . '.' . $payload;

        $pkey = openssl_pkey_get_private($key);
        if ($pkey === false) {
            throw new \RuntimeException('Apple private key is invalid.');
        }
        $der = '';
        if (!openssl_sign($data, $der, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Could not sign Apple client secret.');
        }

        return $data . '.' . self::base64url(self::derToJose($der, 64));
    }

    private static function jwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            throw new \RuntimeException('The provider returned an invalid identity token.');
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('The provider returned an invalid identity token.');
        }

        return $decoded;
    }

    private static function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function derToJose(string $der, int $size): string
    {
        $len = strlen($der);
        if ($len < 8 || ord($der[0]) !== 0x30) {
            throw new \RuntimeException('Invalid Apple signature.');
        }
        $offset = ord($der[1]) === 0x81 ? 3 : 2;
        if ($offset >= $len || ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid Apple signature.');
        }
        $rLen = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLen);
        $offset += 2 + $rLen;
        if ($offset + 1 >= $len || ord($der[$offset]) !== 0x02) {
            throw new \RuntimeException('Invalid Apple signature.');
        }
        $sLen = ord($der[$offset + 1]);
        $s = substr($der, $offset + 2, $sLen);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        $half = intdiv($size, 2);

        return str_pad($r, $half, "\x00", STR_PAD_LEFT) . str_pad($s, $half, "\x00", STR_PAD_LEFT);
    }

    private static function requestRaw(string $method, string $url, array $data = []): string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('HTTP client is unavailable.');
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'bbaccounts/1.0',
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
        }
        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);
        if (!is_string($body)) {
            throw new \RuntimeException('The provider returned an empty response.' . ($error !== '' ? ' ' . $error : ''));
        }

        return $body;
    }

    private static function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \RuntimeException('HTTP client is unavailable.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'bbaccounts/1.0',
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($handle, $options);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '') {
            throw new \RuntimeException($error !== '' ? $error : 'The provider returned an empty response.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('The provider returned an unexpected response.');
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('The provider rejected the request.');
        }

        return $decoded;
    }
}
