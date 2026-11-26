<?php
declare(strict_types=1);

namespace BBA\Models;

use BBA\Core\Crypto;

/** RFC 6238 TOTP with a 30-second step and six digits. */
final class Guard
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const STEP = 30;
    public const DIGITS = 6;

    public static function newSecret(): string
    {
        $bytes = Crypto::random(20);
        $buffer = 0;
        $bits = 0;
        $secret = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $secret .= self::ALPHABET[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $secret .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }

        return $secret;
    }

    public static function code(string $secret, ?int $time = null): string
    {
        $step = intdiv($time ?? time(), self::STEP);
        $counter = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
        $hmac = hash_hmac('sha1', $counter, self::decode($secret), true);
        $offset = ord(substr($hmac, -1)) & 0x0f;
        $value = unpack('N', substr($hmac, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code, int $lastStep = 0, bool $allowUsed = false, ?int &$matchedStep = null): bool
    {
        $matchedStep = null;
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $current = intdiv(time(), self::STEP);
        foreach ([$current, $current - 1, $current + 1] as $step) {
            if (!$allowUsed && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step * self::STEP), $code)) {
                $matchedStep = $step;
                return true;
            }
        }

        return false;
    }

    public static function step(): int
    {
        return intdiv(time(), self::STEP);
    }

    public static function expiresIn(): int
    {
        return self::STEP - (time() % self::STEP);
    }

    public static function enabled(array $account): bool
    {
        return (int) ($account['guard_enabled'] ?? 0) === 1
            && trim((string) ($account['guard_secret'] ?? '')) !== '';
    }

    public static function secretFor(array $account): string
    {
        return Crypto::decrypt((string) ($account['guard_secret'] ?? ''));
    }

    public static function uri(string $secret, string $email): string
    {
        $label = rawurlencode('bbaccounts:' . $email);

        return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret)
            . '&issuer=bbaccounts&algorithm=SHA1&digits=6&period=30';
    }

    private static function decode(string $secret): string
    {
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $secret));
        $buffer = 0;
        $bits = 0;
        $bytes = '';
        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $index;
            $bits += 5;
            while ($bits >= 8) {
                $bits -= 8;
                $bytes .= chr(($buffer >> $bits) & 0xff);
            }
        }

        return $bytes;
    }
}
