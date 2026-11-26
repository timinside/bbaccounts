# bbaccounts

Self-hosted identity provider for bbprojects and third-party apps. One account for sign-in, profiles, OAuth, projects, badges, teams, friends, points, and storage cells.

- **Stack**: PHP 8.2+, MySQL 8 / MariaDB 10.6+, plain PHP. No framework, no Composer.
- **Hosting target**: ordinary shared hosting (ProFreeHost-compatible).
- **Public instance**: https://bbaccounts.unaux.com
- **Docs**: https://timinside.github.io/bbaccounts/docs

## Features

- **OAuth 2.0 + PKCE**: Authorization Code flow, HMAC-hashed codes/tokens, 1h access tokens, 30d rotating refresh tokens, scoped consent.
- **Account security**: password auth, social sign-in providers, recovery codes (no email recovery), Guard/Telegram/2FA support, session/device management.
- **Profiles**: public handles, avatars, banners, badges, showcases, friends, teams, projects, comments, likes.
- **Developer tools**: projects, redirect URIs, credentials, scoped APIs, verified-project APIs.
- **APIs**: profile, points, friends, badges, teams, cells, guard. Bearer token via `Authorization`, `?access_token=`, or `?token=`.
- **Admin/moderation**: roles, banners, maintenance, reports/tickets, update check, account/project management.

## Quick start (local)

Requirements: PHP 8.2+ (`pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl`), MySQL/MariaDB.

```bash
git clone https://github.com/timinside/bbaccounts
cd bbaccounts
cp htdocs/private/config/config.example.php htdocs/private/config/config.php
# edit config.php: set url, key, db.*
mysql -u root -e "CREATE DATABASE bbaccounts_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root bbaccounts_v2 < htdocs/private/database/schema.sql
php -S localhost:8090 -t htdocs router.php
```

Open http://localhost:8090.

For existing installs, apply `htdocs/private/database/production-upgrade.sql`. Do not run `schema.sql` on an existing production database.

## Install / update

`htdocs/setup.php` is a standalone installer/updater:
- asks DB credentials on every POST (not stored);
- install: runs `schema.sql`;
- update: runs `production-upgrade.sql` and requires the app `key` from `config.php`;
- can write `config.php` for the app DB connection.

Delete or protect `setup.php` after use on public hosting.

## Project layout

| Path | Purpose |
|---|---|
| `htdocs/` | Web root, front controller, assets, uploads, setup |
| `htdocs/private/app/` | Core, Controllers, Models, Services, Views, routes, helpers |
| `htdocs/private/config/` | `config.php`, `config.local.php`, `config.example.php` |
| `htdocs/private/database/` | `schema.sql`, `production-upgrade.sql` |
| `htdocs/private/lang/` | i18n strings (`en`, `ru` beta on allowed paths) |
| `htdocs/private/storage/` | logs/cache/update-check state (preserve on deploy) |
| `examples/` | OAuth/API client examples |

## OAuth quick view

1. Register a project in Developer panel, set redirect URI and scopes.
2. Send user to `/oauth/authorize` with `response_type=code`, `client_id`, `redirect_uri`, `scope`, `state`, and PKCE for public clients.
3. Exchange code at `POST /oauth/token`.
4. Call `/oauth/userinfo` or scoped `/api/*` endpoints with Bearer token.

See [docs](https://timinside.github.io/bbaccounts/docs) for full request/response details, scopes, errors, and examples.

## Development notes

- No Composer/framework. Keep shared-hosting compatibility.
- Every state-changing POST needs CSRF.
- Lint touched PHP: `php -l`. Check JS: `node --check htdocs/assets/js/app.js`.
- Schema changes go into `schema.sql`; keep upgrade SQL in `production-upgrade.sql` when needed.
- Never commit secrets (`config.php`, `config.local.php`). Never deploy by overwriting prod config/storage/uploads.
- Agent reference: [AGENTS.md](AGENTS.md).

