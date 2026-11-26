<?php
declare(strict_types=1);

return [
    'debug' => false,
    'allow_white_mode_beta' => true,
    'allow_tutorial' => true,
    'version' => '2.0.22',
    'update' => [
        'enabled' => true,
        'repo' => 'timinside/bbaccounts',
        'branch' => 'main',
    ],
    'docs_url' => 'https://timinside.github.io/bbaccounts/docs',
    'url' => 'https://example.com', // CHANGE ME!
    'timezone' => 'UTC', // CHANGE ME, IF NEEDED!
    'key' => '', // random CHANGE ME!
    'trust_proxy' => false,
    'locales' => [
        'en' => ['name' => 'English', 'planned' => false],
        'ru' => ['name' => 'Русский', 'planned' => false],
    ],
    'db' => [
        'host' => 'host.example.com', // CHANGE ME!
        'port' => 3306, // CHANGE ME, IF NEEDED!
        'name' => 'database_name123', // CHANGE ME!
        'user' => 'user123', // CHANGE ME!
        'pass' => 'cool_password123', // CHANGE ME!
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => '__Host-bb_ui',
        'cookie' => '__Host-bb_session',
        'lifetime' => 2592000,
        'secure' => true,
    ],
	// Connections tab - Empty = disabled
    'social' => [
        'google' => [
            'client_id' => '',
            'client_secret' => '',
        ],
        'github' => [
            'client_id' => '',
            'client_secret' => '',
        ],
        'discord' => [
            'client_id' => '',
            'client_secret' => '',
        ],
        'yandex' => [
            'client_id' => '',
            'client_secret' => '',
        ],
        'steam' => ['client_id' => '', 'client_secret' => ''], // does not need client_secret
        'microsoft' => ['client_id' => '', 'client_secret' => ''],
        'gitlab' => ['client_id' => '', 'client_secret' => ''],
        'apple' => ['client_id' => '', 'team_id' => '', 'key_id' => '', 'private_key' => ''],
    ],
    // Cloudflare Turnstile ("Verify you are human"). Fill both keys to enable the check.
    'turnstile' => [
        'site_key' => '',
        'secret_key' => '',
    ],
    'guard' => [
        'service_url' => '', // still empty!
    ],
    // Telegram bot for 2FA codes and notifications. Leave token empty to disable.
    'telegram' => [
        'bot_token' => '',
        'bot_username' => '',
        'webhook_secret' => '',
    ],
    // bbforum news feed shown on the account home. base_url may include /public.
    'forum' => [
        'base_url' => 'https://bbforum.unaux.com/public',
        'news_tag' => 'bbaccounts',
        'news_limit' => 5,
        'cache_ttl' => 600,
    ],
    'account' => [
        'deletion_email' => 'example@author.com', // CHANGE ME TO OWNER EMAIL!
        'purge_days' => 90,
        'tester_days' => 30,
        'admin_days' => 90,
        'avatar_upload_days' => 14,
        'project_icon_days' => 30,
        'link_days' => 30,
        'showcase_days' => 30,
    ],
    // Minimum bbaccounts level for each gated action. Admins bypass every gate.
    'level' => [
        'handle' => 2,
        'text' => 2,
        'avatar' => 5,
        'contacts' => 5,
        'media' => 8,
        'repos' => 8,
        'links' => 20,
        'banner' => 12,
        'reviews' => 15,
        'tester' => 20,
        'developer' => 25,
        'team' => 35,
        'admin' => 40,
    ],
    // Only these accounts may grant or revoke the administrator role.
    'admin' => [
        'super_ids' => [2],
    ],
    'limits' => [
        'login' => 8,
        'register' => 5,
        'recovery' => 6,
        'token' => 30,
        'window' => 900,
    ],
];
