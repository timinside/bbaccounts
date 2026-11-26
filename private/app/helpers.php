<?php
declare(strict_types=1);

use BBA\Core\Config;
use BBA\Core\Crypto;
use BBA\Core\Db;
use BBA\Core\Request;
use BBA\Core\Session;
use BBA\Models\Project;
use BBA\Models\Verification;
use BBA\Services\SocialAuth;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Inline accent variables with readable text on light or dark accent colors. */
function accent_style(string $color): string
{
    $color = preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? $color : '#2f5cff';
    $r = hexdec(substr($color, 1, 2));
    $g = hexdec(substr($color, 3, 2));
    $b = hexdec(substr($color, 5, 2));
    $light = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255 > 0.6;

    return '--accent: ' . $color . '; --accent-ink: ' . ($light ? '#10131a' : '#ffffff');
}

function url(string $path = ''): string
{
    $base = rtrim((string) Config::get('url', ''), '/');
    if ($path === '') {
        return $base . '/';
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    $path = '/' . ltrim($path, '/');
    $fragment = '';
    if (($hashPos = strpos($path, '#')) !== false) {
        $fragment = substr($path, $hashPos);
        $path = substr($path, 0, $hashPos);
    }
    $query = '';
    if (($qPos = strpos($path, '?')) !== false) {
        $query = substr($path, $qPos);
        $path = substr($path, 0, $qPos);
    }
    $parts = explode('/', $path);
    foreach ($parts as $i => $part) {
        if ($part !== '') {
            $parts[$i] = rawurlencode(rawurldecode($part));
        }
    }

    return $base . implode('/', $parts) . $query . $fragment;
}

function asset(string $path): string
{
    return url($path) . '?v=' . rawurlencode((string) Config::get('version', '1'));
}

/** @return list<string> */
function public_roots(): array
{
    $roots = [];
    foreach ([$_SERVER['DOCUMENT_ROOT'] ?? '', BBA_PUBLIC, dirname(BBA_PRIVATE)] as $root) {
        $root = rtrim((string) $root, '/\\');
        if ($root !== '' && is_dir($root) && !in_array($root, $roots, true)) {
            $roots[] = $root;
        }
    }

    return $roots;
}

function public_file(string $path): bool
{
    $clean = ltrim($path, '/');
    foreach (public_roots() as $root) {
        if (is_file($root . '/' . $clean)) {
            return true;
        }
    }

    return false;
}

function csrf_token(): string
{
    return Crypto::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function icon(string $name, string $class = ''): string
{
    static $paths = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'code' => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'copy' => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="m3 3 18 18"/><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 4.2A10.7 10.7 0 0 1 12 4c6.5 0 10 7 10 7a18 18 0 0 1-2.1 3.1"/><path d="M6.6 6.6A17.5 17.5 0 0 0 2 11s3.5 7 10 7a10.8 10.8 0 0 0 5.4-1.4"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/>',
        'moon' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'laptop' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2 20h20"/>',
        'smartphone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M12 18h.01"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'warning' => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
        'snow' => '<path d="M2 12h20"/><path d="M12 2v20"/><path d="m20 16-4-4 4-4"/><path d="m4 8 4 4-4 4"/><path d="m16 4-4 4-4-4"/><path d="m8 20 4-4 4 4"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'refresh' => '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'terminal' => '<path d="m4 17 6-6-6-6"/><path d="M12 19h8"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21.2l7.8-7.7 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/>',
        'github' => '<path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5a4.6 4.6 0 0 0-1.3-3.2 4.2 4.2 0 0 0-.1-3.2s-1.1-.3-3.5 1.3a12 12 0 0 0-6.2 0C6.5 2.4 5.4 2.7 5.4 2.7a4.2 4.2 0 0 0-.1 3.2A4.6 4.6 0 0 0 4 9.1c0 3.5 3 5.5 6 5.5a4.8 4.8 0 0 0-1 3.5v4"/><path d="M9 18c-4.5 2-5-2-7-2"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'pencil' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="m8.2 13.4-1.2 8L12 18.5l5 2.9-1.2-8"/>',
    ];

    $body = $paths[$name] ?? $paths['grid'];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function social_providers(): array
{
    $providers = [];
    foreach (SocialAuth::names() as $provider => $name) {
        if (SocialAuth::enabled($provider)) {
            $providers[$provider] = ['name' => $name];
        }
    }

    return $providers;
}

/** Every known provider with its readiness, for the "more ways to log in" dialog. */
function social_providers_all(): array
{
    $providers = [];
    foreach (SocialAuth::names() as $provider => $name) {
        $providers[$provider] = [
            'name' => $name,
            'ready' => SocialAuth::enabled($provider),
            'planned' => SocialAuth::placeholder($provider),
        ];
    }

    return $providers;
}

function t(string $text, array $replace = []): string
{
    $translated = \BBA\Core\I18n::get($text);
    foreach ($replace as $key => $value) {
        $translated = str_replace(':' . $key, (string) $value, $translated);
    }

    return $translated;
}

function social_profile_url(string $provider, string $username): ?string
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    return match ($provider) {
        'github' => 'https://github.com/' . rawurlencode($username),
        'steam' => 'https://steamcommunity.com/id/' . rawurlencode($username),
        default => null,
    };
}

function render_mentions(string $text): string
{
    $escaped = e($text);

    return preg_replace_callback('/(?<![a-z0-9_])@([a-z0-9_]{3,30})/i', static function (array $match): string {
        $account = Db::one('SELECT id, username FROM accounts WHERE username = ? LIMIT 1', [mb_strtolower($match[1])]);
        if ($account === null) {
            return '@' . $match[1];
        }

        return '<a class="mention" href="' . e(account_url($account)) . '">@' . $match[1] . '</a>';
    }, $escaped) ?? $escaped;
}

function account_url(array $account): string
{
    $username = trim((string) ($account['username'] ?? ''));

    return url('/u/' . ($username !== '' ? $username : (int) ($account['id'] ?? 0)));
}

function safe_next_path(mixed $next): string
{
    if (!is_string($next)) {
        return '';
    }

    $next = trim($next);
    if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, "\n") || str_contains($next, "\r")) {
        return '';
    }

    return $next;
}

function links_allowed(array $account): bool
{
    return (int) ($account['is_admin'] ?? 0) === 1
        || account_age_days($account) >= (int) Config::get('account.link_days', 30);
}

function showcases_allowed(array $account): bool
{
    return (int) ($account['is_admin'] ?? 0) === 1
        || account_age_days($account) >= (int) Config::get('account.showcase_days', 30);
}

/** Contact rows saved on the account, with the account email as the final fallback. */
function profile_contacts(array $account): array
{
    $rows = json_decode((string) ($account['contacts'] ?? ''), true);
    $contacts = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $kind = (string) ($row['kind'] ?? '');
            $value = trim((string) ($row['value'] ?? ''));
            if ($value !== '' && in_array($kind, ['email', 'telegram', 'discord', 'steam'], true)) {
                $contacts[] = ['kind' => $kind, 'value' => $value];
            }
        }
    }

    if (!array_filter($contacts, static fn (array $row): bool => $row['kind'] === 'email')) {
        $email = trim((string) ($account['email'] ?? ''));
        if (valid_email($email)) {
            array_unshift($contacts, ['kind' => 'email', 'value' => $email]);
        }
    }

    return $contacts;
}

function contact_href(string $kind, string $value): ?string
{
    return match ($kind) {
        'email' => 'mailto:' . $value,
        'telegram' => 'https://t.me/' . rawurlencode(ltrim($value, '@')),
        'github' => 'https://github.com/' . rawurlencode($value),
        'steam' => preg_match('/^\d{17}$/', $value) === 1
            ? 'https://steamcommunity.com/profiles/' . $value
            : 'https://steamcommunity.com/id/' . rawurlencode($value),
        default => null,
    };
}

function contact_icon(string $kind): string
{
    return $kind === 'email' ? icon('mail') : provider_mark($kind);
}

function contact_label(string $kind): string
{
    return match ($kind) {
        'email' => 'Email',
        'telegram' => 'Telegram',
        'discord' => 'Discord',
        'steam' => 'Steam',
        default => 'Contact',
    };
}

/** Embed URL for a YouTube or Vimeo link; other links render as images. */
function media_embed_url(string $url): string
{
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $url, $match) === 1) {
        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($match[1]);
    }
    if (preg_match('~vimeo\.com/(\d+)~i', $url, $match) === 1) {
        return 'https://player.vimeo.com/video/' . rawurlencode($match[1]);
    }

    return $url;
}

/** Badge artwork from /assets/img/Badges, falling back to a line icon. */
function badge_mark(array $badge): string
{
    $name = ucwords(str_replace('-', ' ', (string) ($badge['slug'] ?? '')));
    foreach ([$name . '.svg', $name . '@2x.png'] as $file) {
        $path = '/assets/img/Badges/' . $file;
        if (public_file($path)) {
            return '<img class="badge-icon" src="' . e(asset($path)) . '" alt="" width="18" height="18">';
        }
    }

    return icon((string) ($badge['icon'] ?? 'grid'), 'badge-icon');
}

/** Public path to a badge's artwork, or null when none was supplied. */
function badge_image(array $badge): ?string
{
    $label = ucwords(str_replace('-', ' ', (string) ($badge['slug'] ?? '')));
    foreach ([$label . '.svg', $label . '.png', $label . '@2x.png'] as $file) {
        $path = '/assets/img/Badges/' . $file;
        if (public_file($path)) {
            return asset($path);
        }
    }

    return null;
}

/** Roblox-style badge: artwork only, with the name revealed on hover. Opens the badge page. */
function badge_medal(array $badge, bool $showName = false): string
{
    $name = e((string) $badge['name']);
    $href = e(url('/badges/' . rawurlencode((string) ($badge['slug'] ?? ''))));
    $image = badge_image($badge);
    $label = $showName ? '<span class="badge-medal-name">' . $name . '</span>' : '';
    if ($image !== null) {
        return '<a class="badge-medal-wrap has-tip" data-tooltip="' . $name . '" href="' . $href . '" aria-label="' . $name . '">'
            . '<img class="badge-medal" src="' . e($image) . '" alt="' . $name
            . '" width="44" height="44" loading="lazy">' . $label . '</a>';
    }

    return '<a class="badge-medal badge-medal-fallback" href="' . $href . '">' . $name . '</a>';
}

function is_super_account(array $account): bool
{
    $ids = array_map('intval', (array) Config::get('admin.super_ids', []));

    return in_array((int) ($account['id'] ?? 0), $ids, true);
}

function profile_roles(array $account): array
{
    $roles = [];
    if (is_super_account($account)) {
        $roles[] = ['name' => 'Super Account', 'icon' => 'zap', 'class' => 'badge-super'];
    }
    if ((int) ($account['is_admin'] ?? 0) === 1 && (int) ($account['show_role_admin'] ?? 1) === 1) {
        $roles[] = ['name' => 'Admin', 'icon' => 'shield', 'class' => 'badge-admin'];
    }
    if ((int) ($account['is_developer'] ?? 0) === 1 && (int) ($account['show_role_developer'] ?? 1) === 1) {
        $roles[] = ['name' => 'Developer', 'icon' => 'code', 'class' => 'badge-ok'];
    }
    if ((int) ($account['is_tester'] ?? 0) === 1 && (int) ($account['show_role_tester'] ?? 1) === 1) {
        $roles[] = ['name' => 'Tester', 'icon' => 'shield', 'class' => 'badge-tester'];
    }
    if ((int) ($account['is_moderator'] ?? 0) === 1 && (int) ($account['show_role_moderator'] ?? 1) === 1) {
        $roles[] = ['name' => 'Moderator', 'icon' => 'shield', 'class' => 'badge-moderator'];
    }

    return $roles;
}

function provider_mark(string $provider): string
{
    $marks = [
        'google' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true">'
            . '<path fill="#4285F4" d="M23.52 12.27c0-.79-.07-1.54-.2-2.27H12v4.51h6.47a5.54 5.54 0 0 1-2.4 3.63v3h3.88c2.27-2.09 3.57-5.17 3.57-8.87z"/>'
            . '<path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3c-1.08.72-2.45 1.15-4.05 1.15-3.12 0-5.76-2.11-6.7-4.94H1.29v3.09A11.99 11.99 0 0 0 12 24z"/>'
            . '<path fill="#FBBC05" d="M5.3 14.3a7.2 7.2 0 0 1 0-4.6V6.61H1.29a12 12 0 0 0 0 10.78L5.3 14.3z"/>'
            . '<path fill="#EA4335" d="M12 4.75c1.76 0 3.34.61 4.58 1.8l3.44-3.44A11.98 11.98 0 0 0 12 0 11.99 11.99 0 0 0 1.29 6.61L5.3 9.7c.94-2.83 3.58-4.95 6.7-4.95z"/>'
            . '</svg>',
        'github' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 .5C5.65.5.5 5.65.5 12c0 5.08 3.29 9.39 7.86 10.91.58.11.79-.25.79-.55v-2.15c-3.2.7-3.87-1.36-3.87-1.36-.52-1.33-1.28-1.69-1.28-1.69-1.05-.72.08-.71.08-.71 1.16.08 1.77 1.19 1.77 1.19 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.55-.29-5.24-1.28-5.24-5.68 0-1.25.45-2.28 1.19-3.08-.12-.29-.52-1.46.11-3.04 0 0 .97-.31 3.18 1.18a11.1 11.1 0 0 1 5.79 0c2.21-1.49 3.18-1.18 3.18-1.18.63 1.58.23 2.75.11 3.04.74.8 1.19 1.83 1.19 3.08 0 4.41-2.69 5.38-5.25 5.67.41.36.78 1.05.78 2.12v3.14c0 .3.21.66.8.55A11.5 11.5 0 0 0 23.5 12C23.5 5.65 18.35.5 12 .5z"/></svg>',
        'discord' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="#5865F2" d="M20.32 4.37a19.8 19.8 0 0 0-4.89-1.52.07.07 0 0 0-.08.04c-.21.37-.44.87-.61 1.25a18.3 18.3 0 0 0-5.49 0 12.6 12.6 0 0 0-.61-1.25.08.08 0 0 0-.08-.04 19.7 19.7 0 0 0-4.88 1.52.07.07 0 0 0-.03.03C.53 9.05-.32 13.58.1 18.06a.08.08 0 0 0 .03.06 19.9 19.9 0 0 0 5.99 3.03.08.08 0 0 0 .09-.03c.46-.63.87-1.3 1.22-1.99a.08.08 0 0 0-.04-.11 13.1 13.1 0 0 1-1.87-.89.08.08 0 0 1-.01-.13l.37-.29a.07.07 0 0 1 .08-.01c3.93 1.79 8.18 1.79 12.06 0a.07.07 0 0 1 .08.01l.37.29a.08.08 0 0 1-.01.13c-.6.35-1.22.65-1.87.89a.08.08 0 0 0-.04.11c.36.69.78 1.36 1.22 1.99a.08.08 0 0 0 .09.03 19.8 19.8 0 0 0 6-3.03.08.08 0 0 0 .03-.06c.5-5.18-.84-9.67-3.55-13.66a.06.06 0 0 0-.03-.03zM8.02 15.33c-1.18 0-2.16-1.08-2.16-2.42s.96-2.42 2.16-2.42c1.21 0 2.18 1.1 2.16 2.42 0 1.34-.96 2.42-2.16 2.42zm7.97 0c-1.18 0-2.16-1.08-2.16-2.42s.96-2.42 2.16-2.42c1.21 0 2.18 1.1 2.16 2.42 0 1.34-.95 2.42-2.16 2.42z"/></svg>',
        'yandex' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true">'
            . '<rect width="24" height="24" rx="4" fill="#FC3F1D"/>'
            . '<text x="12" y="17" text-anchor="middle" font-family="Arial,sans-serif" font-size="14" font-weight="700" fill="#ffffff">Я</text>'
            . '</svg>',
        'telegram' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="#229ED9" d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>',
        'steam' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.031 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.605 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.5 1.009 2.455-.397.957-1.497 1.41-2.454 1.012H7.54zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.663 0 3.015-1.35 3.015-3.015zm-5.273-.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.253 0-2.265-1.014-2.265-2.265z"/></svg>',
        'microsoft' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="1" width="10" height="10" fill="#F25022"/><rect x="13" y="1" width="10" height="10" fill="#7FBA00"/><rect x="1" y="13" width="10" height="10" fill="#00A4EF"/><rect x="13" y="13" width="10" height="10" fill="#FFB900"/></svg>',
        'gitlab' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="#E24329" d="M12 21.42l3.684-11.333H8.316L12 21.42z"/><path fill="#FC6D26" d="M12 21.42L8.316 10.087H3.147L12 21.42z"/><path fill="#FCA326" d="M3.147 10.087l-1.12 3.447a.762.762 0 0 0 .277.852L12 21.42 3.147 10.087z"/><path fill="#E24329" d="M3.147 10.087h5.169L6.094 3.257a.381.381 0 0 0-.725 0L3.147 10.087z"/><path fill="#FC6D26" d="M12 21.42l3.684-11.333h5.169L12 21.42z"/><path fill="#FCA326" d="M20.853 10.087l1.12 3.447a.762.762 0 0 1-.277.852L12 21.42l8.853-11.333z"/><path fill="#E24329" d="M20.853 10.087h-5.169l2.222-6.83a.381.381 0 0 1 .725 0l2.222 6.83z"/></svg>',
        'apple' => '<svg class="provider-mark" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12.152 6.896c-.948 0-2.415-1.078-3.96-1.04-2.04.027-3.91 1.183-4.961 3.014-2.117 3.675-.546 9.103 1.519 12.09 1.013 1.454 2.208 3.09 3.792 3.039 1.52-.065 2.09-.987 3.935-.987 1.831 0 2.35.987 3.96.948 1.637-.026 2.676-1.48 3.676-2.948 1.156-1.688 1.636-3.325 1.662-3.415-.039-.013-3.182-1.221-3.22-4.857-.026-3.04 2.48-4.494 2.597-4.559-1.429-2.09-3.623-2.324-4.39-2.376-2-.156-3.675 1.09-4.61 1.09zM15.53 3.83c.843-1.012 1.4-2.427 1.245-3.83-1.207.052-2.662.805-3.532 1.818-.78.896-1.454 2.338-1.273 3.714 1.338.104 2.715-.688 3.559-1.701"/></svg>',
    ];

    return $marks[$provider] ?? icon('link', 'provider-mark');
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::get('_old', []);

    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

function form_error(string $key): ?string
{
    $errors = Session::get('_errors', []);

    return is_array($errors) && isset($errors[$key]) ? (string) $errors[$key] : null;
}

function has_errors(): bool
{
    $errors = Session::get('_errors', []);

    return is_array($errors) && $errors !== [];
}

function first_error(): ?string
{
    $errors = Session::get('_errors', []);
    if (!is_array($errors) || $errors === []) {
        return null;
    }

    return (string) reset($errors);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

    if ($parts === []) {
        return '?';
    }

    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';

    return mb_strtoupper($first . $last);
}

function avatar_url(?array $account): string
{
    if ($account === null) {
        return url('/assets/img/avatars/1.png');
    }

    if (($account['avatar_type'] ?? 'initials') === 'upload' && !empty($account['avatar_value'])) {
        return url('/uploads/avatars/' . rawurlencode((string) $account['avatar_value']));
    }

    if (($account['avatar_type'] ?? 'initials') === 'preset' && !empty($account['avatar_value'])) {
        // Unmigrated numeric presets still point at the old bundled PNGs.
        if (preg_match('/^\d{1,2}$/', (string) $account['avatar_value']) === 1) {
            return url('/assets/img/avatars/' . rawurlencode((string) $account['avatar_value']) . '.png');
        }
    }

    $version = empty($account['updated_at']) ? 0 : (int) strtotime((string) $account['updated_at']);

    return url('/avatar/' . (int) $account['id'] . '.svg' . ($version > 0 ? '?v=' . $version : ''));
}

function avatar_style(?array $account): string
{
    if (($account['avatar_type'] ?? '') !== 'preset') {
        return '';
    }

    $color = (string) ($account['avatar_color'] ?? '');

    return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? ' style="background:' . e($color) . '"' : '';
}

function welcome_notification(int $accountId): void
{
    Db::insert('notifications', [
        'account_id' => $accountId,
        'subject' => 'Welcome to bbaccounts',
        'body' => 'Your account is ready. You can set up security, profile, and connected apps from your account settings.',
        'icon' => 'shield',
        'link_text' => 'Open account',
        'link_url' => url('/account'),
    ]);
}

function notification_icons(): array
{
    return [
        'bell' => 'Bell', 'shield' => 'Shield', 'alert' => 'Alert', 'check' => 'Check',
        'mail' => 'Mail', 'key' => 'Key', 'code' => 'Code', 'lock' => 'Lock',
        'activity' => 'Activity', 'users' => 'Users', 'grid' => 'Grid', 'settings' => 'Settings',
        'zap' => 'Lightning',
        'trash' => 'Trash', 'refresh' => 'Refresh', 'book' => 'Book', 'link' => 'Link',
        'terminal' => 'Terminal', 'database' => 'Database', 'search' => 'Search', 'plus' => 'Plus',
        'eye' => 'Eye', 'external' => 'External', 'laptop' => 'Laptop', 'smartphone' => 'Phone',
        'sun' => 'Sun', 'moon' => 'Moon', 'home' => 'Home', 'user' => 'User',
    ];
}

function notification_icon(?string $name): string
{
    return $name !== null && array_key_exists($name, notification_icons()) ? $name : 'bell';
}

function readable_on(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '#ffffff';
    }

    $luminance = (0.299 * hexdec(substr($hex, 0, 2))
        + 0.587 * hexdec(substr($hex, 2, 2))
        + 0.114 * hexdec(substr($hex, 4, 2))) / 255;

    return $luminance > 0.62 ? '#12141a' : '#ffffff';
}

function valid_email(string $email): bool
{
    $email = mb_strtolower(trim($email));
    if ($email === '' || mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }

    // Reject placeholder domains like a@a.a: require a real label and a 2+ letter TLD.
    return preg_match('/^[^@\s]+@(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $email) === 1;
}

function contains_link(string $text): bool
{
    if (preg_match('~https?://|www\.~i', $text) === 1) {
        return true;
    }

    return preg_match('~\b[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\.[a-z]{2,24}\b~iu', $text) === 1;
}

function account_age_days(array $account): int
{
    $created = strtotime((string) ($account['created_at'] ?? ''));
    if ($created === false) {
        return 0;
    }

    return max(0, (int) floor((time() - $created) / 86400));
}

/** bbaccounts levels: 100 XP per level, 99 is the cap; extra XP shows MAX LEVEL. */
function level_from_xp(int $xp): int
{
    return max(0, min(99, (int) floor(max(0, $xp) / 100)));
}

function level_info(array $account): array
{
    $xp = max(0, (int) ($account['xp'] ?? 0));
    $level = level_from_xp($xp);

    return [
        'level' => $level,
        'xp' => $xp,
        'label' => $xp >= 10000 ? 'MAX LEVEL' : 'Level ' . $level,
        'next_xp' => $level >= 99 ? null : ($level + 1) * 100,
        'progress' => $level >= 99 ? 100 : (int) (($xp % 100) / 100 * 100),
    ];
}

function level_required(string $key): int
{
    return max(0, (int) Config::get('level.' . $key, 0));
}

function level_ok(array $account, string $key): bool
{
    return (int) ($account['is_admin'] ?? 0) === 1
        || (int) ($account['level'] ?? level_from_xp((int) ($account['xp'] ?? 0))) >= level_required($key);
}

function level_gate_error(string $key): string
{
    return 'This requires bbaccounts level ' . level_required($key) . '.';
}

/** Emoji categories shared by the classic form and the profile v2 editor. */
function emoji_groups(): array
{
    return [
        'Smileys' => ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜', '🤪', '🤨', '🧐', '🤓', '😎', '🥸', '🤩', '🥳', '😏', '😒', '😞', '😔', '😟', '😕', '🙁', '☹️', '😣', '😖', '😫', '😩', '🥺', '😢', '😭', '😤', '😠', '😡', '🤬', '🤯', '😳', '🥵', '🥶', '😱', '😨', '😰', '😥', '😓', '🤗', '🤔', '🫣', '🤭', '🫢', '🤫', '🤥', '😶', '😐', '😑', '😬', '🫠', '🙄', '😯', '😦', '😧', '😮', '😲', '🥱', '😴', '🤤', '😪', '😵', '🤐', '🥴', '🤢', '🤮', '🤧', '😷', '🤒', '🤕'],
        'Animals' => ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼', '🐻‍❄️', '🐨', '🐯', '🦁', '🐮', '🐷', '🐸', '🐵', '🙈', '🙉', '🙊', '🐒', '🐔', '🐧', '🐦', '🐤', '🦆', '🦅', '🦉', '🦇', '🐺', '🐗', '🐴', '🦄', '🐝', '🪱', '🐛', '🦋', '🐌', '🐞', '🐜', '🪰', '🪲', '🪳', '🦟', '🦗', '🕷️', '🕸️', '🦂', '🐢', '🐍', '🦎', '🦖', '🦕', '🐙', '🦑', '🦐', '🦞', '🦀', '🐡', '🐠', '🐟', '🐬', '🐳', '🐋', '🦈', '🐊', '🐅', '🐆', '🦓', '🦍', '🦧', '🐘', '🦛', '🦏', '🐪', '🐫', '🦒', '🦘', '🦬', '🐃', '🐄', '🐎', '🐖', '🐏', '🐑', '🦙', '🐐', '🦌', '🐕', '🐩', '🦮', '🐈', '🪶', '🐓', '🦃', '🦤', '🦚', '🦜', '🦢', '🦩', '🕊️', '🐇', '🦝', '🦨', '🦡', '🦫', '🦦', '🦥', '🐁', '🐀', '🐿️', '🦔'],
        'Food' => ['🍏', '🍎', '🍐', '🍊', '🍋', '🍌', '🍉', '🍇', '🍓', '🫐', '🍈', '🍒', '🍑', '🥭', '🍍', '🥥', '🥝', '🍅', '🍆', '🥑', '🥦', '🥬', '🥒', '🌶️', '🫑', '🌽', '🥕', '🫒', '🧄', '🧅', '🥔', '🍠', '🥐', '🥯', '🍞', '🥖', '🥨', '🧀', '🥚', '🍳', '🧈', '🥞', '🧇', '🥓', '🥩', '🍗', '🍖', '🦴', '🌭', '🍔', '🍟', '🍕', '🫓', '🥪', '🥙', '🧆', '🌮', '🌯', '🫔', '🥗', '🥘', '🫕', '🥫', '🍝', '🍜', '🍲', '🍛', '🍣', '🍱', '🥟', '🦪', '🍤', '🍙', '🍚', '🍘', '🍥', '🥠', '🥮', '🍢', '🍡', '🍧', '🍨', '🍦', '🥧', '🧁', '🍰', '🎂', '🍮', '🍭', '🍬', '🍫', '🍿', '🍩', '🍪', '🌰', '🥜', '🍯', '🥛', '🍼', '☕', '🫖', '🍵', '🧃', '🥤', '🧋', '🍶', '🍺', '🍻', '🥂', '🍷', '🥃', '🍸', '🍹', '🧉', '🍾'],
        'Activity' => ['⚽', '🏀', '🏈', '⚾', '🥎', '🎾', '🏐', '🏉', '🥏', '🎱', '🪀', '🏓', '🏸', '🏒', '🏑', '🥍', '🏏', '🪃', '🥅', '⛳', '🪁', '🏹', '🎣', '🤿', '🥊', '🥋', '🎽', '🛹', '🛼', '🛷', '⛸️', '🥌', '🎿', '⛷️', '🏂', '🪂', '🏋️', '🤼', '🤸', '🤺', '🤾', '🏌️', '🏇', '🧘', '🏄', '🏊', '🤽', '🚣', '🧗', '🚵', '🚴', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '🏵️', '🎗️', '🎫', '🎟️', '🎪', '🤹', '🎭', '🩰', '🎨', '🎬', '🎤', '🎧', '🎼', '🎹', '🥁', '🪘', '🎷', '🎺', '🪗', '🎸', '🪕', '🎻', '🎲', '♟️', '🎯', '🎳', '🎮', '🎰', '🧩'],
        'Travel' => ['🚗', '🚕', '🚙', '🚌', '🚎', '🏎️', '🚓', '🚑', '🚒', '🚐', '🛻', '🚚', '🚛', '🚜', '🦯', '🦽', '🦼', '🛴', '🚲', '🛵', '🏍️', '🛺', '🚨', '🚔', '🚍', '🚘', '🚖', '🚡', '🚠', '🚟', '🚃', '🚋', '🚞', '🚝', '🚄', '🚅', '🚈', '🚂', '🚆', '🚇', '🚊', '🚉', '✈️', '🛫', '🛬', '🛩️', '💺', '🛰️', '🚀', '🛸', '🚁', '🛶', '⛵', '🚤', '🛥️', '🛳️', '⛴️', '🚢', '⚓', '🪝', '⛽', '🚧', '🚦', '🚥', '🚏', '🗺️', '🗿', '🗽', '🗼', '🏰', '🏯', '🏟️', '🎡', '🎢', '🎠', '⛲', '⛱️', '🏖️', '🏝️', '🏜️', '🌋', '⛰️', '🏔️', '🗻', '🏕️', '⛺', '🛖', '🏠', '🏡', '🏘️', '🏚️', '🏗️', '🏭', '🏢', '🏬', '🏣', '🏤', '🏥', '🏦', '🏨', '🏪', '🏫', '🏩', '💒', '🏛️', '⛪', '🕌', '🕍', '🛕', '🕋', '⛩️'],
        'Objects' => ['⌚', '📱', '📲', '💻', '⌨️', '🖥️', '🖨️', '🖱️', '🖲️', '🕹️', '🗜️', '💽', '💾', '💿', '📀', '📼', '📷', '📸', '🎥', '📽️', '🎞️', '📞', '☎️', '📟', '📠', '📺', '📻', '🎙️', '🎚️', '🎛️', '🧭', '⏱️', '⏲️', '⏰', '🕰️', '⌛', '⏳', '📡', '🔋', '🪫', '🔌', '💡', '🔋', '🕯️', '🪔', '🧯', '🛢️', '💸', '💵', '💴', '💶', '💷', '🪙', '💰', '💳', '🪪', '💎', '⚖️', '🪜', '🧰', '🪛', '🔧', '🔨', '⚒️', '🛠️', '⛏️', '🪚', '🔩', '⚙️', '🪤', '🧱', '⛓️', '🧲', '🔫', '💣', '🧨', '🪓', '🔪', '🗡️', '⚔️', '🛡️', '🚬', '⚰️', '🪦', '⚱️', '🏺', '🔮', '📿', '🧿', '💈', '⚗️', '🔭', '🔬', '🕳️', '🩹', '🩺', '🩻', '💊', '💉', '🩸', '🧬', '🦠', '🦠', '🧫', '🧪', '🌡️', '🧹', '🪠', '🧺', '🧻', '🚽', '🚰', '🚿', '🛁', '🛀', '🧼', '🪥', '🪒', '🧽', '🪣', '🧴', '🛎️', '🔑', '🗝️', '🚪', '🪑', '🛋️', '🛏️', '🛌', '🧸', '🪆', '🖼️', '🪞', '🪟', '🛍️', '🛒', '🎁', '🎈', '🎏', '🎀', '🪄', '🪅', '🎊', '🎉', '🎎', '🏮', '🎐', '🧧', '✉️', '📩', '📨', '📧', '💌', '📥', '📤', '📦', '🏷️', '🪧', '📪', '📫', '📬', '📭', '📮', '📯', '📜', '📃', '📄', '📑', '🧾', '📊', '📈', '📉', '🗒️', '🗓️', '📆', '📅', '🗑️', '📇', '🗃️', '🗳️', '🗄️', '📋', '📁', '📂', '🗂️', '🗞️', '📰', '📓', '📔', '📒', '📕', '📗', '📘', '📙', '📚', '📖', '🔖', '🧷', '🔗', '📎', '🖇️', '📐', '📏', '🧮', '📌', '📍', '✂️', '🖊️', '🖋️', '✒️', '🖌️', '🖍️', '📝', '✏️', '🔍', '🔎', '🔏', '🔐', '🔒', '🔓'],
        'Symbols' => ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '💟', '☮️', '✝️', '☪️', '🕉️', '☸️', '✡️', '🔯', '🕎', '☯️', '☦️', '🛐', '⛎', '♈', '♉', '♊', '♋', '♌', '♍', '♎', '♏', '♐', '♑', '♒', '♓', '🆔', '⚛️', '🉑', '☢️', '☣️', '📴', '📳', '🈶', '🈚', '🈸', '🈺', '🈷️', '✴️', '🆚', '💮', '🉐', '㊙️', '㊗️', '🈴', '🈵', '🈶', '🈹', '🈲', '🅰️', '🅱️', '🆎', '🆑', '🅾️', '🆘', '❌', '⭕', '🛑', '⛔', '📛', '🚫', '💯', '💢', '♨️', '🚷', '🚱', '🔞', '📵', '🚭', '❗', '❕', '❓', '❔', '‼️', '⁉️', '🔅', '🔆', '〽️', '⚠️', '🚸', '🔱', '⚜️', '🔰', '♻️', '✅', '🈯', '💹', '❇️', '✳️', '❎', '🌐', '💠', 'Ⓜ️', '🌀', '💤', '🏧', '🚾', '♿', '🅿️', '🛗', '🈳', '🈂️', '🛂', '🛃', '🛄', '🛅', '🚹', '🚺', '🚼', '⚧️', '🚻', '🚮', '🎦', '📶', '🈁', '🔣', 'ℹ️', '🔤', '🔡', '🔠', '🆖', '🆗', '🆙', '🆒', '🆕', '🆓', '0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣', '6️⃣', '7️⃣', '8️⃣', '9️⃣', '🔟', '🔢', '#️⃣', '*️⃣', '⏏️', '▶️', '⏸️', '⏯️', '⏹️', '⏺️', '⏭️', '⏮️', '⏩', '⏪', '⏫', '⏬', '◀️', '🔼', '🔽', '➡️', '⬅️', '⬆️', '⬇️', '↗️', '↘️', '↙️', '↖️', '↕️', '↔️', '↩️', '↪️', '⤴️', '⤵️', '🔀', '🔁', '🔂', '🔄', '🔃', '🎵', '🎶', '➕', '➖', '➗', '✖️', '🟰', '♾️', '💲', '💱', '™️', '©️', '®️', '👁️‍🗨️', '🔚', '🔙', '🔛', '🔝', '🔜', '〰️', '➰', '➿', '✔️', '☑️', '🔘', '🔴', '🟠', '🟡', '🟢', '🔵', '🟣', '⚫', '⚪', '🟤', '🔺', '🔻', '🔸', '🔹', '🔶', '🔷', '🔳', '🔲', '▪️', '▫️', '◾', '◽', '◼️', '◻️', '🟥', '🟧', '🟨', '🟩', '🟦', '🟪', '⬛', '⬜', '🟫'],
    ];
}

function markdown(string $text): string
{
    $safe = e($text);
    $safe = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $safe) ?? $safe;
    $safe = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
    $safe = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $safe) ?? $safe;
    $safe = preg_replace('/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" rel="noopener">$1</a>', $safe) ?? $safe;

    return nl2br($safe, false);
}

function project_icon(array $project, string $class = 'project-icon', bool $ignoreApproval = false): string
{
    $name = (string) ($project['name'] ?? '?');
    $accent = (string) ($project['accent_color'] ?? '#5b6cff');
    $transparent = (int) ($project['icon_transparent'] ?? 0) === 1;
    $bg = $transparent ? 'background: transparent;' : 'background: ' . $accent . '1f;';
    if (!empty($project['icon_file']) && ($ignoreApproval || (int) ($project['icon_approved'] ?? 0) === 1)) {
        $style = $transparent ? ' style="background: transparent"' : '';
        return '<img class="' . e($class) . '" src="' . e(url('/uploads/projects/' . rawurlencode((string) $project['icon_file']))) . '" alt="" width="44" height="44"' . $style . '>';
    }

    return '<span class="' . e($class) . '" style="' . e($bg) . ' color: ' . e($accent) . '">' . e(mb_strtoupper(mb_substr($name, 0, 1))) . '</span>';
}

function format_date(?string $value, string $format = 'M j, Y'): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? '—' : date($format, $timestamp);
}

function time_ago(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '—';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . 'd ago';
    }

    return date('M j, Y', $timestamp);
}

function is_current(string $path): bool
{
    $current = Request::path();
    if ($path === '/') {
        return $current === '/';
    }

    return $current === $path || str_starts_with($current, rtrim($path, '/') . '/');
}

function nav_class(string $path, bool $exact = false): string
{
    $current = Request::path();
    $active = $exact
        ? $current === $path
        : ($current === $path || str_starts_with($current, rtrim($path, '/') . '/'));

    return $active ? 'is-active' : '';
}

function activity_label(string $action): string
{
    static $labels = [
        'auth.login' => 'Signed in',
        'auth.logout' => 'Signed out',
        'auth.login_failed' => 'Failed sign-in',
        'auth.rate_limited' => 'Sign-in rate limited',
        'auth.registered' => 'Account created',
        'auth.password_recovered' => 'Password recovered',
        'auth.recovery_failed' => 'Recovery failed',
        'auth.social_login' => 'Signed in with social provider',
        'auth.social_registered' => 'Account created with social provider',
        'auth.social_linked' => 'Social provider linked',
        'account.password_set' => 'Password set',
        'account.social_linked' => 'Social provider connected',
        'account.social_unlinked' => 'Social provider disconnected',
        'account.profile_updated' => 'Profile updated',
        'account.email_updated' => 'Email updated',
        'account.password_changed' => 'Password changed',
        'account.recovery_codes_regenerated' => 'Recovery codes regenerated',
        'account.session_revoked' => 'Session revoked',
        'account.session_renamed' => 'Session renamed',
        'account.other_sessions_revoked' => 'Other sessions revoked',
        'account.access_revoked' => 'App access revoked',
        'developer.project_created' => 'Project created',
        'developer.project_updated' => 'Project updated',
        'developer.project_deleted' => 'Project deleted',
        'developer.redirect_added' => 'Redirect URI added',
        'developer.redirect_removed' => 'Redirect URI removed',
        'developer.credential_created' => 'Client secret created',
        'developer.credential_revoked' => 'Client secret revoked',
        'oauth.consent_granted' => 'Access granted',
        'oauth.consent_denied' => 'Access denied',
        'oauth.code_issued' => 'Authorization code issued',
        'oauth.token_issued' => 'Access token issued',
        'oauth.token_refreshed' => 'Access token refreshed',
        'oauth.token_revoked' => 'Access token revoked',
    ];

    return $labels[$action] ?? ucfirst(str_replace(['.', '_'], ' ', $action));
}

function scope_label(string $scope): string
{
    return match ($scope) {
        'profile' => 'Display name and avatar',
        'email' => 'Email address',
        'guard' => 'Guard secret and codes',
        'points' => 'Point balance',
        'friends' => 'Friend list',
        'badges' => 'Create and issue badges',
        'teams' => 'Teams and team roles',
        default => $scope,
    };
}

function device_icon(string $userAgent): string
{
    return preg_match('/android|iphone|ipad|mobile|windows phone/i', $userAgent) === 1 ? 'smartphone' : 'laptop';
}

function device_label(string $userAgent, string $appName = ''): string
{
    $systems = [
        '/android/i' => 'Android',
        '/iphone|ipod/i' => 'iPhone',
        '/ipad/i' => 'iPad',
        '/windows/i' => 'Windows',
        '/macintosh|mac os x/i' => 'macOS',
        '/linux|x11/i' => 'Linux',
    ];
    $system = 'Unknown device';
    foreach ($systems as $pattern => $name) {
        if (preg_match($pattern, $userAgent) === 1) {
            $system = $name;
            break;
        }
    }

    $appName = trim($appName);
    if ($appName !== '') {
        return $appName . ' on ' . $system;
    }

    $browsers = [
        '/edg\//i' => 'Edge',
        '/opr\/|opera/i' => 'Opera',
        '/firefox\//i' => 'Firefox',
        '/chrome\//i' => 'Chrome',
        '/safari\//i' => 'Safari',
    ];
    foreach ($browsers as $pattern => $name) {
        if (preg_match($pattern, $userAgent) === 1) {
            return $name . ' on ' . $system;
        }
    }

    return $system;
}

function oauth_app_name(string $next): string
{
    if ($next === '' || parse_url($next, PHP_URL_PATH) !== '/oauth/authorize') {
        return '';
    }

    parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
    $clientId = (string) ($query['client_id'] ?? '');
    if ($clientId === '') {
        return '';
    }

    $project = Project::findByClientId($clientId);

    return $project === null ? '' : mb_substr((string) $project['name'], 0, 60);
}

function short_text(string $text, int $limit = 60): string
{
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit - 1) . '…';
}

function upload_dir(string $sub): string
{
    $sub = trim($sub, '/');
    foreach (public_roots() as $root) {
        $dir = $root . '/uploads/' . $sub;
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }
    foreach (public_roots() as $root) {
        $dir = $root . '/uploads/' . $sub;
        $parent = dirname($dir);
        if (is_dir($parent) && is_writable($parent)) {
            @mkdir($dir, 0775, true);
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }
    }
    $fallback = BBA_PUBLIC . '/uploads/' . $sub;
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0775, true);
    }

    return $fallback;
}

/** Stores a validated image and returns its new filename, or null plus an error. */
function save_image_upload(array $file, string $sub): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return [null, 'Choose an image under 2 MB.'];
    }

    $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
    $types = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    $type = is_array($info) ? (int) $info[2] : 0;
    if (!isset($types[$type])) {
        return [null, 'Use a JPG, PNG, WebP, or GIF image.'];
    }

    $name = bin2hex(random_bytes(12)) . '.' . $types[$type];
    $path = upload_dir($sub) . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $path)) {
        return [null, 'The image could not be saved.'];
    }
    if (!is_file($path)) {
        return [null, 'Upload failed: file is missing on the server after save.'];
    }
    $saved = @getimagesize($path);
    $savedType = is_array($saved) ? (int) $saved[2] : 0;
    if (!isset($types[$savedType])) {
        @unlink($path);
        return [null, 'Upload failed: saved file format could not be verified.'];
    }

    return [$name, null];
}

/** Stores a validated image or video for a Media showcase. */
function save_media_upload(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
        return [null, 'Choose an image or video under 20 MB.'];
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = @mime_content_type($tmp) ?: '';
    $types = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'video/mp4' => 'mp4', 'video/webm' => 'webm',
    ];
    if (!isset($types[$mime])) {
        return [null, 'Use a JPG, PNG, WebP, GIF, MP4, or WebM file.'];
    }

    $name = bin2hex(random_bytes(12)) . '.' . $types[$mime];
    if (!move_uploaded_file($tmp, upload_dir('media') . '/' . $name)) {
        return [null, 'The file could not be saved.'];
    }

    return [$name, null];
}

function delete_upload(string $sub, ?string $file): void
{
    if ($file === null || $file === '' || str_contains($file, '/') || str_contains($file, '\\')) {
        return;
    }

    foreach (public_roots() as $root) {
        $path = rtrim($root, '/\\') . '/uploads/' . trim($sub, '/') . '/' . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function setting(string $name, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = [];
            foreach (Db::all('SELECT name, value FROM settings') as $row) {
                $cache[(string) $row['name']] = (string) ($row['value'] ?? '');
            }
        } catch (\Throwable $e) {
            $cache = [];
        }
    }

    return $cache[$name] ?? $default;
}

function setting_save(string $name, ?string $value): void
{
    if ($value === null || $value === '') {
        Db::run('DELETE FROM settings WHERE name = ?', [$name]);

        return;
    }

    Db::run(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?',
        [$name, $value, $value]
    );
}

/** Home page side banner: custom upload or the bundled file, plus its link. */
function home_ad(string $slot): ?array
{
    $custom = setting('home_ad_' . $slot . '_image');
    if ($custom === '' || !public_file('/uploads/ads/' . $custom)) {
        $custom = '';
    }

    $default = '/assets/img/ad0' . ($slot === 'left' ? '1' : '2') . '.png';
    if ($custom === '' && !public_file($default)) {
        return null;
    }

    return [
        'src' => $custom !== '' ? url('/uploads/ads/' . rawurlencode($custom)) : asset($default),
        'url' => setting('home_ad_' . $slot . '_url'),
    ];
}

/** Admin-issued check next to a profile or project name, with its explanatory tooltip. */
function verification_mark(string $type, int $id): string
{
    $row = Verification::for($type, $id);
    if ($row === null) {
        return '';
    }

    $kind = (string) $row['kind'] === 'gold' ? 'gold' : 'blue';
    $note = trim((string) $row['note']);
    $title = $note !== '' ? $note : ($type === 'profile' ? 'Verified profile' : 'Verified project');

    return '<span class="verification verification-' . $kind . ' has-tip" data-tooltip="' . e($title)
        . '" tabindex="0" aria-label="' . e($title) . '">' . icon('check') . '</span>';
}

/** Red ban or blue freeze mark; disappears on its own when the status expires. */
function account_status_mark(array $account): string
{
    $status = (string) ($account['status'] ?? 'active');
    if ($status !== 'banned' && $status !== 'suspended') {
        return '';
    }

    $until = trim((string) ($account['status_until'] ?? ''));
    $timestamp = $until !== '' ? strtotime($until) : false;
    if ($timestamp !== false && $timestamp <= time()) {
        return '';
    }

    $reason = trim((string) ($account['status_reason'] ?? ''));
    $label = ($status === 'banned' ? 'Banned' : 'Frozen')
        . ($reason !== '' ? ': ' . $reason : '')
        . ($timestamp !== false ? ', until ' . format_date($until, 'j F Y') : '');

    return '<span class="account-mark ' . ($status === 'banned' ? 'account-mark-ban' : 'account-mark-freeze')
        . ' has-tip" data-tooltip="' . e($label) . '" tabindex="0" aria-label="' . e($label) . '">'
        . icon($status === 'banned' ? 'warning' : 'snow') . '</span>';
}

/** Admin warning note shown on the profile next to the name. */
function warning_mark(array $account): string
{
    $text = trim((string) ($account['warning_text'] ?? ''));
    if ($text === '') {
        return '';
    }

    return '<span class="warning-mark has-tip" data-tooltip="' . e($text) . '" tabindex="0" aria-label="' . e($text) . '">'
        . icon('alert') . '</span>';
}

function last_online_label(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Never signed in';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return 'Never signed in';
    }
    if (time() - $timestamp < 300) {
        return 'Online now';
    }

    return 'Last online ' . time_ago($value);
}

/** Domains whose links may leave bbaccounts without the warning page. */
function trusted_sites(): array
{
    $rows = json_decode((string) setting('trusted_sites'), true);
    if (!is_array($rows)) {
        return [];
    }

    return array_values(array_filter(array_map(static function (mixed $row): string {
        $site = strtolower(trim((string) $row));
        $site = preg_replace('~^\*\.~', '', $site) ?? '';

        return preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~i', $site) === 1 ? $site : '';
    }, $rows)));
}

function is_trusted_url(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    foreach (trusted_sites() as $site) {
        if ($host === $site || str_ends_with($host, '.' . $site)) {
            return true;
        }
    }

    return false;
}

/** Cloudflare Turnstile stays off until both keys are configured. */
function turnstile_enabled(): bool
{
    return trim((string) Config::get('turnstile.site_key', '')) !== ''
        && trim((string) Config::get('turnstile.secret_key', '')) !== '';
}

function turnstile_widget(): string
{
    if (!turnstile_enabled()) {
        return '';
    }

    $error = form_error('turnstile');
    $siteKey = e((string) Config::get('turnstile.site_key', ''));

    return '<div class="field turnstile-field">'
        . '<div class="cf-turnstile" data-sitekey="' . $siteKey . '" data-theme="auto"></div>'
        . ($error !== null ? '<p class="field-error">' . e($error) . '</p>' : '')
        . '</div>';
}

function turnstile_script(): string
{
    return turnstile_enabled()
        ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
        : '';
}

/** True when the check is disabled or the visitor solved the challenge. */
function turnstile_verify(): bool
{
    if (!turnstile_enabled()) {
        return true;
    }

    $token = trim((string) Request::input('cf-turnstile-response', ''));
    if ($token === '') {
        return false;
    }

    $handle = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($handle === false) {
        return false;
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => (string) Config::get('turnstile.secret_key', ''),
            'response' => $token,
            'remoteip' => Request::ip(),
        ]),
    ]);

    $body = curl_exec($handle);
    curl_close($handle);

    $decoded = is_string($body) ? json_decode($body, true) : null;

    return is_array($decoded) && ($decoded['success'] ?? false) === true;
}

function selected(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function update_check(bool $force = false, bool $cacheOnly = false): ?array
{
    $cfg = (array) Config::get('update', []);
    if (($cfg['enabled'] ?? true) !== true) return null;
    $repo = trim((string) ($cfg['repo'] ?? ''));
    $current = (string) Config::get('version', '');
    if ($repo === '' || $current === '') return null;

    $cacheFile = BBA_PRIVATE . '/storage/update-check.json';
    $cacheTtl = 86400;
    $cached = null;
    if (is_file($cacheFile)) {
        $decoded = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($decoded)) $cached = $decoded;
    }
    $fresh = is_array($cached) && (int) ($cached['checked_at'] ?? 0) > time() - $cacheTtl;
    if (!$force && ($fresh || $cacheOnly)) {
        if (!is_array($cached)) return null;
        $cached['current'] = $current;
        $cached['available'] = version_compare((string) ($cached['latest'] ?? '0'), $current, '>');
        return $cached;
    }

    $lockFile = BBA_PRIVATE . '/storage/update-check.lock';
    $lock = @fopen($lockFile, 'c');
    if (is_resource($lock)) {
        if (!@flock($lock, LOCK_EX | LOCK_NB)) {
            @fclose($lock);
            if (is_array($cached)) {
                $cached['current'] = $current;
                $cached['available'] = version_compare((string) ($cached['latest'] ?? '0'), $current, '>');
                return $cached;
            }
            return null;
        }
        // Another process may have refreshed while we waited for the lock.
        $decoded = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($decoded)) $cached = $decoded;
        $fresh = is_array($cached) && (int) ($cached['checked_at'] ?? 0) > time() - $cacheTtl;
        if (!$force && $fresh) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
            $cached['current'] = $current;
            $cached['available'] = version_compare((string) ($cached['latest'] ?? '0'), $current, '>');
            return $cached;
        }
    }

    $latest = '';
    $url = 'https://github.com/' . $repo . '/releases/latest';
    $api = 'https://api.github.com/repos/' . $repo . '/releases/latest';
    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($api);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'User-Agent: bbaccounts-update-check'],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($status === 200 && is_string($body)) {
                $json = json_decode($body, true);
                $tag = is_array($json) ? (string) ($json['tag_name'] ?? $json['name'] ?? '') : '';
                $latest = ltrim(trim($tag), 'v');
                if (is_array($json) && !empty($json['html_url'])) $url = (string) $json['html_url'];
            }
        }
    } catch (\Throwable $e) {
        $latest = '';
    }

    if (is_resource($lock ?? null)) {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    if ($latest === '') {
        if (is_array($cached)) {
            $cached['current'] = $current;
            $cached['available'] = version_compare((string) ($cached['latest'] ?? '0'), $current, '>');
            return $cached;
        }
        return null;
    }

    $data = ['checked_at' => time(), 'current' => $current, 'latest' => $latest, 'url' => $url, 'repo' => $repo];
    @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_SLASHES));
    $data['available'] = version_compare($latest, $current, '>');
    return $data;
}

/** Schedules a daily update check after the response when possible, without blocking normal pages. */
function update_check_schedule(): void
{
    static $scheduled = false;
    if ($scheduled) return;
    $cfg = (array) Config::get('update', []);
    if (($cfg['enabled'] ?? true) !== true) return;
    $repo = trim((string) ($cfg['repo'] ?? ''));
    $current = (string) Config::get('version', '');
    if ($repo === '' || $current === '') return;

    $cacheFile = BBA_PRIVATE . '/storage/update-check.json';
    $fresh = false;
    if (is_file($cacheFile)) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        $fresh = is_array($cached) && (int) ($cached['checked_at'] ?? 0) > time() - 86400;
    }
    if ($fresh) return;

    $scheduled = true;
    register_shutdown_function(static function (): void {
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } elseif (PHP_SAPI !== 'cli-server') {
            // Without response flush, avoid slowing down a user request.
            return;
        }
        try {
            update_check();
        } catch (\Throwable $e) {
            // Background check must never break a request.
        }
    });
}

function docs_url(string $path = '/docs'): string
{
    $external = trim((string) Config::get('docs_url', ''));
    $path = '/' . ltrim($path, '/');
    $static = [
        '/docs' => '/docs.html',
        '/docs/' => '/docs.html',
        '/docs/installation' => '/docs-installation.html',
        '/privacy' => '/privacy.html',
        '/privacypolicy' => '/privacy.html',
        '/privacypolicy.php' => '/privacy.html',
        '/terms' => '/terms.html',
        '/termsofservice' => '/terms.html',
        '/termsofservice.php' => '/terms.html',
        '/rules' => '/rules.html',
    ];
    if ($external !== '') {
        $external = rtrim($external, '/');
        if (str_ends_with($external, '.html') || str_ends_with($external, '/docs')) {
            $base = str_ends_with($external, '.html')
                ? preg_replace('~/[^/]+\.html$~', '', $external)
                : substr($external, 0, -5);
            if (isset($static[$path])) return $base . $static[$path];
            return $base . $path;
        }
        if (isset($static[$path])) return $external . preg_replace('~^/docs~', '', $static[$path]);
        return $external . ($path === '/docs' ? '' : preg_replace('~^/docs~', '', $path));
    }
    return url($path);
}
