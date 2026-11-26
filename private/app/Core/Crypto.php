<?php
declare(strict_types=1);

namespace BBA\Core;

final class Crypto
{
    public static function key(): string
    {
        return (string) Config::get('key');
    }

    public static function random(int $bytes = 32): string
    {
        return random_bytes($bytes);
    }

    public static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function token(int $bytes = 32): string
    {
        return self::base64Url(self::random($bytes));
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    /** Encrypt a value that must be read back, such as a TOTP seed. */
    public static function encrypt(string $value): string
    {
        $iv = self::random(12);
        $key = hash('sha256', self::key() . '|guard', true);
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Could not encrypt the secret.');
        }

        return self::base64Url($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode(
            strtr($payload, '-_', '+/') . str_repeat('=', (4 - strlen($payload) % 4) % 4),
            true
        );
        if ($raw === false || strlen($raw) < 28) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $key = hash('sha256', self::key() . '|guard', true);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    public static function equals(string $known, string $given): bool
    {
        return hash_equals($known, $given);
    }

    public static function clientId(): string
    {
        return bin2hex(self::random(12));
    }

    public static function clientSecret(): string
    {
        return 'bbcs_' . self::token(32);
    }

    public static function codeChallenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    public static function csrfToken(): string
    {
        $existing = Session::get('_csrf');
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $random = bin2hex(self::random(16));
        $token = $random . '.' . self::hash($random);
        Session::set('_csrf', $token);

        return $token;
    }

    public static function csrfValid(?string $token): bool
    {
        if ($token === null) {
            return false;
        }
        $stored = Session::get('_csrf');

        return is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }

    public static function passwordHash(string $password): string
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $options = $algorithm === PASSWORD_ARGON2ID ? [] : ['cost' => 12];

        return password_hash($password, $algorithm, $options);
    }

    public static function passwordNeedsRehash(string $hash): bool
    {
        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        return password_needs_rehash($hash, $algorithm, $algorithm === PASSWORD_ARGON2ID ? [] : ['cost' => 12]);
    }
}
