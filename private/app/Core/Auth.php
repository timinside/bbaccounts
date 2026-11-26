<?php
declare(strict_types=1);

namespace BBA\Core;

use BBA\Models\User;

final class Auth
{
    private static ?array $account = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$account;
        }

        self::$resolved = true;
        $token = $_COOKIE[(string) Config::get('session.cookie', 'bb_session')] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }

        $hash = Crypto::hash($token);
        $row = Db::one(
            'SELECT a.*, s.id AS auth_session_id, s.expires_at AS session_expires_at, s.last_active_at AS session_last_active
             FROM auth_sessions s
             INNER JOIN accounts a ON a.id = s.account_id
             WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > NOW()
             LIMIT 1',
            [$hash]
        );

        if ($row === null || !User::isAvailable($row)) {
            self::clearCookie();

            return null;
        }

        $lifetime = (int) Config::get('session.lifetime', 2592000);
        $expires = strtotime((string) $row['session_expires_at']);
        if ($expires !== false && $expires < time() + $lifetime - 86400) {
            $newExpiry = date('Y-m-d H:i:s', time() + $lifetime);
            Db::run('UPDATE auth_sessions SET expires_at = ? WHERE id = ?', [$newExpiry, (int) $row['auth_session_id']]);
            self::setCookie($token, time() + $lifetime);
            $row['session_expires_at'] = $newExpiry;
        }

        $lastActive = strtotime((string) $row['session_last_active']);
        if ($lastActive === false || $lastActive < time() - 300) {
            Db::run('UPDATE auth_sessions SET last_active_at = NOW() WHERE id = ?', [(int) $row['auth_session_id']]);
        }

        self::$account = $row;

        return self::$account;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $email, string $password): ?array
    {
        $email = mb_strtolower(trim($email));
        $account = Db::one(
            'SELECT * FROM accounts WHERE email = ? LIMIT 1',
            [$email]
        );

        if ($account === null) {
            password_verify($password, '$2y$12$C6UzMDM.H6dfI/f/IKcEe.2z0M5v0L6n5E2T3j4Q5w6e7r8t9y0u1');
            return null;
        }

        if (!password_verify($password, (string) $account['password_hash'])) {
            return null;
        }

        if (!User::isAvailable($account)) {
            // Only reveal the real reason to someone who proved they know the password.
            $account['_blocked'] = true;

            return $account;
        }

        if (Crypto::passwordNeedsRehash((string) $account['password_hash'])) {
            Db::run('UPDATE accounts SET password_hash = ? WHERE id = ?', [
                Crypto::passwordHash($password),
                (int) $account['id'],
            ]);
        }

        return $account;
    }

    public static function login(array $account, string $appName = ''): void
    {
        $token = Crypto::token(32);
        $lifetime = (int) Config::get('session.lifetime', 2592000);
        $appName = mb_substr(trim($appName), 0, 60);
        $agent = Request::userAgent();
        $deviceId = self::deviceId();

        // One row per browser: signing in again on the same device replaces the old session.
        if ($agent !== '') {
            Db::run(
                'UPDATE auth_sessions SET revoked_at = NOW() WHERE account_id = ? AND user_agent = ? AND revoked_at IS NULL',
                [(int) $account['id'], $agent]
            );
        }

        Db::insert('auth_sessions', [
            'account_id' => (int) $account['id'],
            'token_hash' => Crypto::hash($token),
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'device_id' => $deviceId,
            'app_name' => $appName !== '' ? $appName : null,
            'last_active_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', time() + $lifetime),
        ]);

        Db::run('UPDATE accounts SET last_login_at = NOW() WHERE id = ?', [(int) $account['id']]);
        self::rememberSwitch($token, $account);
        self::setCookie($token, time() + $lifetime);
        Session::regenerate();
        self::$account = $account;
        self::$resolved = true;
        Audit::log('auth.login', (int) $account['id']);
        self::telegramNotify($account, 'telegram_notify_login', "bbaccounts: new sign-in to your account from " . (Request::ip() ?: 'unknown IP') . ".");
    }

    public static function logout(): void
    {
        $account = self::user();
        $token = $_COOKIE[(string) Config::get('session.cookie', 'bb_session')] ?? null;
        if (is_string($token) && $token !== '') {
            Db::run('UPDATE auth_sessions SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL', [
                Crypto::hash($token),
            ]);
        }

        if ($account !== null) {
            Audit::log('auth.logout', (int) $account['id']);
            self::telegramNotify($account, 'telegram_notify_security', 'bbaccounts: you signed out of your account.');
        }
        if (is_string($token) && $token !== '') {
            self::forgetSwitch($token);
        }

        self::clearCookie();
        self::$account = null;
        self::$resolved = true;
        Session::destroy();
    }

    public static function revokeSession(int $sessionId, int $accountId): void
    {
        Db::run(
            'UPDATE auth_sessions SET revoked_at = NOW() WHERE id = ? AND account_id = ? AND revoked_at IS NULL',
            [$sessionId, $accountId]
        );
    }

    public static function revokeOtherSessions(int $accountId, int $currentSessionId): void
    {
        Db::run(
            'UPDATE auth_sessions SET revoked_at = NOW() WHERE account_id = ? AND id <> ? AND revoked_at IS NULL',
            [$accountId, $currentSessionId]
        );
    }

    public static function revokeAllSessions(int $accountId): void
    {
        Db::run('UPDATE auth_sessions SET revoked_at = NOW() WHERE account_id = ? AND revoked_at IS NULL', [$accountId]);
    }

    /** Every distinct account with a live session created from this browser. */
    public static function deviceAccounts(): array
    {
        $deviceId = self::deviceId();
        $rows = Db::all(
            'SELECT a.id, a.display_name, a.username, a.email, a.avatar_type, a.avatar_value, a.avatar_color,
                    a.status, a.status_until, a.updated_at
             FROM accounts a
             WHERE a.id IN (
                 SELECT s.account_id FROM auth_sessions s
                 WHERE s.device_id = ? AND s.revoked_at IS NULL AND s.expires_at > NOW()
             )
             ORDER BY a.last_login_at DESC, a.id ASC',
            [$deviceId]
        );

        return array_values(array_filter($rows, static fn (array $row): bool => User::isAvailable($row)));
    }

    private static function deviceId(): string
    {
        $name = (string) Config::get('session.device_cookie', 'bb_device');
        $value = $_COOKIE[$name] ?? '';
        if (is_string($value) && preg_match('/^[a-f0-9]{32}$/', $value) === 1) {
            return $value;
        }

        $value = bin2hex(random_bytes(16));
        $_COOKIE[$name] = $value;
        if (!headers_sent()) {
            setcookie($name, $value, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => (bool) Config::get('session.secure', true),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        return $value;
    }

    /** Accounts that can be switched to without signing in again, current one included. */
    public static function switchAccounts(): array
    {
        $items = self::switchCookie();
        $accounts = [];
        foreach ($items as $item) {
            $token = is_array($item) ? (string) ($item['token'] ?? '') : '';
            if ($token === '') {
                continue;
            }
            $row = Db::one(
                'SELECT a.id, a.display_name, a.username, a.email, a.avatar_type, a.avatar_value, a.avatar_color, a.status,
                        a.status_until, a.updated_at
                 FROM auth_sessions s
                 INNER JOIN accounts a ON a.id = s.account_id
                 WHERE s.token_hash = ? AND s.revoked_at IS NULL AND s.expires_at > NOW()
                 LIMIT 1',
                [Crypto::hash($token)]
            );
            if ($row === null || !User::isAvailable($row)) {
                continue;
            }
            $row['token'] = $token;
            $accounts[(int) $row['id']] = $row;
        }

        return array_values($accounts);
    }

    public static function switchToAccount(int $accountId): bool
    {
        foreach (self::switchAccounts() as $account) {
            if ((int) $account['id'] === $accountId) {
                self::setCookie((string) $account['token'], time() + (int) Config::get('session.lifetime', 2592000));
                self::clearResolved();
                Audit::log('auth.account_switched', $accountId);

                return true;
            }
        }

        return false;
    }

    public static function rememberSwitch(string $token, array $account): void
    {
        $items = [];
        foreach (self::switchCookie() as $item) {
            $existing = is_array($item) ? (string) ($item['token'] ?? '') : '';
            if ($existing !== '' && !hash_equals($existing, $token)) {
                $items[] = ['token' => $existing];
            }
        }
        $items[] = ['token' => $token];
        self::writeSwitchCookie(array_slice($items, -6));
    }

    public static function forgetSwitch(string $token): void
    {
        $items = [];
        foreach (self::switchCookie() as $item) {
            $existing = is_array($item) ? (string) ($item['token'] ?? '') : '';
            if ($existing !== '' && !hash_equals($existing, $token)) {
                $items[] = ['token' => $existing];
            }
        }
        self::writeSwitchCookie($items);
    }

    private static function switchCookie(): array
    {
        $raw = $_COOKIE[(string) Config::get('session.switch_cookie', 'bb_accounts')] ?? '';
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private static function writeSwitchCookie(array $items): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie((string) Config::get('session.switch_cookie', 'bb_accounts'), json_encode(array_values($items)), [
            'expires' => time() + (int) Config::get('session.lifetime', 2592000),
            'path' => '/',
            'secure' => (bool) Config::get('session.secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearResolved(): void
    {
        self::$account = null;
        self::$resolved = false;
    }

    public static function setCookie(string $token, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie((string) Config::get('session.cookie', 'bb_session'), $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => (bool) Config::get('session.secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function clearCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie((string) Config::get('session.cookie', 'bb_session'), '', [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => (bool) Config::get('session.secure', true),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function telegramNotify(array $account, string $setting, string $message): void
    {
        $chatId = trim((string) ($account['telegram_chat_id'] ?? ''));
        if ($chatId === '' || (int) ($account[$setting] ?? 0) !== 1) {
            return;
        }
        \BBA\Controllers\AuthController::telegramSend($chatId, $message);
    }
}
