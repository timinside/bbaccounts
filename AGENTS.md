# AGENTS.md - bbaccounts install

Guide for agents installing or updating bbaccounts on a server. Do not develop features here; only install, update, and verify.

## What you are installing

- **bbaccounts**: self-hosted identity/account platform (PHP 8.2+, MySQL 8 / MariaDB 10.6+, plain PHP, no framework/Composer).
- **Web root**: `htdocs/`. Point the domain/vhost document root to `htdocs/`.
- **Public URL**: set in config as `url` (production example: `https://bbaccounts.unaux.com`).

## Files you must not damage

- Never overwrite or delete on an existing install: `htdocs/private/config/config.php`, `htdocs/private/config/config.local.php` (if present on prod), `htdocs/private/storage/`, `htdocs/uploads/`.
- Never print, commit, or publish secrets from `config.php` / `config.local.php`.
- Never import a local/dev database into production.
- Never run FTP/deploy automation unless the user explicitly provides it; prefer giving the user exact files/steps.

## Clean install

1. Upload/copy the contents of `htdocs/` to the server web root.
2. Ensure PHP 8.2+ and MySQL/MariaDB are available. Create an empty database and DB user with access to it.
3. Make sure `htdocs/private/config/` is writable for setup, and `htdocs/private/storage/` and `htdocs/uploads/` are writable by PHP.
4. Open `https://your-domain/setup.php`.
5. Fill DB credentials, app URL, and app key. Setup asks for DB creds on every POST and does not store them; it writes app config when requested.
6. Run install. Setup executes `private/database/schema.sql`.
7. Create the first admin/account as the app flow requires, then verify login.
8. **Delete `setup.php` immediately after install**, or restrict access to it. Do not leave it publicly accessible.

## Update existing install

1. Back up files and database before updating.
2. Upload new `htdocs/` files, but **preserve** `private/config/config.php`, `private/config/config.local.php` (if intentionally used), `private/storage/`, and `uploads/`.
3. If the install already has config, open `setup.php`. Update mode requires the app `key` from `config.php`.
4. Run update. Setup executes `private/database/production-upgrade.sql` when present.
5. **Delete `setup.php` or restrict access after update**.
6. Verify: home page loads, login works, `/docs` redirect works, language selector works, admin panel loads for admins.

## Config notes

- Use `private/config/config.example.php` as reference; real config is `private/config/config.php`.
- `config.local.php` is an optional local override merged over `config.php`. On production, do not deploy a local dev override that points DB to `localhost`/`127.0.0.1` unless that is actually the prod DB.
- Required/important keys: `version`, `url`, `key`, `debug=false` on prod, `db.*`, `session.secure` on HTTPS, `docs_url` (default GitHub Pages docs).
- `key` must stay stable after install; changing it invalidates HMAC-based tokens/codes.

## Verification checklist

- `index.php` loads without redirect loop; missing config should redirect to `/setup.php`.
- Login/register/password recovery-code flows work.
- `/privacy`, `/terms`, `/rules`, `/docs` redirect to configured docs/pages as expected.
- `private/storage/` is writable; no secrets exposed in web responses.
- `setup.php` is removed or access-restricted.

## Gotchas

- **Do not run `schema.sql` on an existing production DB.** Use `production-upgrade.sql` for upgrades.
- **ProFreeHost/anti-bot**: some hosts may challenge non-browser requests before PHP. Browser install is fine; API/OAuth clients may need host-side challenge bypass/proxy for `/api/*,/oauth/*`.
- **Outbound HTTPS**: update checks/news/Telegram may fail on hosts that block outbound curl/HTTPS; core install can still work.
- **No 127.0.0.1 in committed config examples for prod use**; use real host DB settings.
