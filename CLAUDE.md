# Project: Premium URL Shortener (GemPixel)

PHP web app — URL shortener + QR codes + bio profiles + subscriptions. Built on **GemFramework** (proprietary, Laravel-like; do NOT redistribute/modify framework files).

## Stack
- PHP 7.4+, MySQL, Composer
- Front controller: `.htaccess` rewrites `^(.*)$` → `public/$1`; `public/index.php` → `app/core.php` → `Gem::Bootstrap()`
- Routing: `nikic/fast-route` via `Gem::get|post|route|group` (see `app/routes.php`)
- Payments: Stripe, PayPal, Mollie · Mail: PHPMailer · QR: endroid/qr-code · 2FA: sonata google-authenticator
- Cache: phpfastcache · Logs: monolog

## Layout (only what's not obvious)
- `core/` — framework classes (`Gem`, `DB`, `Auth`, `Model`, `View`, `Http`, `Request`, `Response`, `Middleware`, `Plugin`, `Localization`). Treat as vendor.
- `app/` — your code:
  - `controllers/` (+ `admin/`, `api/`, `user/` subdirs)
  - `models/` — `Url`, `User`, `Plans`, `Role`, `Settings`
  - `middleware/` — Auth, CSRF, Throttle, ShortenThrottle, BlockBot, CheckDomain, CheckMaintenance, CheckPrivate, ValidateCaptcha, RolePermission, Locale, DemoProtect
  - `helpers/` — App, Emails, Events, Permissions, QR, Captcha, FacebookAuth/GoogleAuth, payments/, qr/
  - `traits/` — Links, Overlays, Payments, Pixels, Teams
  - `config/` — api.php, app.php, boot.php, cdn.php
  - `routes.php` — all HTTP routes
- `public/` — webroot. Static at `public/static/`, uploads at `public/content/`
- `storage/` — `logs/`, `cache/`, `languages/`, `plugins/`, `themes/`, `app/`
- `config.php` — DB creds + tokens (root). `BASEPATH='AUTO'`, `DEBUG=0`, `CACHE=true`
- `vendor/` — composer deps

## Conventions
- Controllers: `Name@method` strings in routes resolve to `app/controllers/NameController.php`
- Chainable route modifiers: `->name()`, `->middleware()`; group middleware via `Gem::setMiddleware([...])`
- Locale prefix auto-detected from URL segment 1 against `Localization::listArray()`
- Path constants set in `app/core.php`: `ROOT`, `APP`, `PUB`, `CORE`, `CONTROLLER`, `MODELS`, `MIDDLEWARE`, `UPLOADS`, `STORAGE`, `LOGS`, `LOCALE`, `PLUGIN`

## Server config
- Apache: `.htaccess` at root forwards to `public/`
- Nginx: sample in `nginx.conf` — docroot must be `/public`, `try_files $uri $uri/ /index.php?$query_string`

## Security notes (flag to maintainer)
- `config.php` is committed with what look like **real DB credentials** (`DBpassword`, `AuthToken`, `EncryptionToken`, `PublicToken`). Rotate + add to `.gitignore` if this repo is or will be public.
- `DEBUG=0` and `FORCEURL=true` — keep that way in prod.

## Working in this repo
- Don't edit `core/` or `vendor/` — framework + composer-managed.
- Add routes in `app/routes.php`; add controllers under `app/controllers/` (use existing `admin/`, `api/`, `user/` subdirs for grouping).
- New middleware → `app/middleware/`, register on route via `->middleware('Name')`.
- Models extend the framework's `Core\Model`.
- The `codebase/` directory at the repo root is empty (placeholder) — actual code is at the root.
