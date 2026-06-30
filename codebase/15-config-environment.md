# 15 — Configuration & Environment

This app has **three layers of configuration**, each with different lifetimes and update mechanisms.

## Layer 1 — Runtime constants (`config.php` at repo root)

Defines via `define()`. **Read at bootstrap, never changes during a request.** Editing this file requires PHP-process restart on opcache deployments.

| Constant | Default | Purpose |
|---|---|---|
| `DBhost` | — | MySQL host |
| `DBname` | — | Database name |
| `DBuser` | — | Username |
| `DBpassword` | — | Password |
| `DBprefix` | `''` | Optional table prefix (shared-DB installs) |
| `DBport` | `3306` | MySQL port |
| `BASEPATH` | `'AUTO'` | Subfolder install path; `'AUTO'` = auto-detect |
| `USECDN` | `true` | Toggle CDN delivery of frontend libs (per `app/config/cdn.php`) |
| `CDNASSETS` | `null` | Override base URL for static assets |
| `CDNUPLOADS` | `null` | Override base URL for uploads |
| `FORCEURL` | `true` | If true, force `Gem::$Config->url`; if false accept any resolving host |
| `TIMEZONE` | `'GMT+0'` | PHP timezone string |
| `CACHE` | `true` | Enable in-app data caching (Phpfastcache) |
| `DEBUG` | `0` | Debug level (`0` off, `1` errors, `2` extra) |
| `AuthToken` | (per-install secret) | bcrypt salt suffix for passwords |
| `EncryptionToken` | (per-install Defuse key) | Symmetric encryption key |
| `PublicToken` | (per-install) | Public-facing signature token |

⚠️ **Rotating `AuthToken` breaks every existing password hash.** Rotating `EncryptionToken` breaks every Defuse-encrypted blob (cookies, 2FA sessions, team sessions).

Additional constants defined by `app/core.php` (not in `config.php`):
- `_VERSION` = `"1.1"` — framework version
- `_INAPP` = `TRUE`
- `_STATE` = `"PROD"` — set to `"DEMO"` to enable `DemoProtect` middleware
- `ROOT`, `APP`, `PUB`, `CORE`, `CONTROLLER`, `MODELS`, `MIDDLEWARE`, `LIBRARY`, `UPLOADS`, `STORAGE`, `LOGS`, `LOCALE`, `PLUGIN` — path constants

Defined conditionally by `Settings::getSettings()` at runtime:
- `CDNCUSTOMURL` — only if a custom-URL CDN is enabled.

## Layer 2 — File-based app config (`app/config/*.php`)

PHP files returning arrays. **Read on demand** via `appConfig('file.key')` (cached per request in `Gem::$App['appConfig']`).

### `app/config/app.php`

Main app config. Top-level keys:

| Key | Default | Purpose |
|---|---|---|
| `sitemap.enabled` | `true` | Generate XML sitemap |
| `sitemap.numberoflinks` | `50` | Max links per sitemap |
| `sitemap.numberofbio` | `50` | Max bio profiles per sitemap |
| `language` | `'en'` | Fallback locale code |
| `spamcheck.numberoflinks` | `2` | Spam threshold |
| `spamcheck.postmarkcheck` | `true` | Postmark spam-trap check |
| `spamcheck.regex` | (profanity regex) | Word filter for spam |
| `browserbasedlang` | `true` | Auto-detect locale from Accept-Language |
| `self_shortening` | `false` | Allow shortening URLs that point at this install |
| `duplicateurls` | `false` | Allow multiple aliases for the same destination |
| `antiflood` | `15` | Anti-flood window (minutes) |
| `redirectauto` | `false` | Auto-redirect on alias collision |
| `aliasformat` | `'abc…XYZ'` (alphabet) | Charset for `Traits\Links::alias()` |
| `executables` | `["exe","dll","bin","dat","osx"]` | Blocked file extensions |
| `storage.<engine>` | (paths/URLs) | Storage engine map — `public`, `uploads`, `blog`, `avatar`, `images`, `qr`, `profile`, `files` |
| `geodriver` | `'maxmind'` | Geo lookup driver: `api` / `maxmind` / `custom` |
| `geopath` | `STORAGE.'/app/GeoLite2-City.mmdb'` | MaxMind DB path |
| `maildrivers` | (driver map) | `mailgun` / `sendgrid` / `mailchimp` / `postmark` → `Core\Support\*` |
| `cachepath` | (default) | Phpfastcache root |
| `apiroute` | `'/api'` | API URL prefix |
| `adminroute` | `'/admin'` | Admin URL prefix |
| `throttle` | `[30, 1]` | Default API rate limit (req, minutes) |
| `debug` | mirrors `DEBUG` | |
| `log` | (settings) | |
| `default_theme` | `'default'` | Fallback theme |
| `proxy.enabled` | `false` | Outbound proxy for `Core\Http` |
| `proxy.server` / `port` / `user` / `password` | — | Proxy details |
| `haship` | `false` | Hash visitor IPs in `stats` table |
| `sizes.avatar` | `500` | Avatar max size (KB) |
| `sizes.bio.avatar` / `image` / `link` | `500` | Bio asset limits |
| `sizes.bio.background` | `1024` | Bio background size |
| `sizes.splash.avatar` / `banner` | `500` / `1024` | Splash asset limits |
| `sizes.qrfile` | `2048` | QR file upload size |
| `sizes.qrcsv` | `1024` | QR CSV size |
| `extensions.<type>` | (jpg/png/jpeg…) | Allowed upload extensions per type |
| `nativeqrdownload` | `!class_exists('Imagick')` | Auto-detected GD-only mode |

### `app/config/boot.php`

```php
return [ [Setup::class, 'check'] ];
```

Single-callable array. Each entry is invoked by `Gem::preload()`; return `false` to halt bootstrap (the installer uses this).

### `app/config/cdn.php`

Frontend asset/library manifest. Returns:

```php
return [
  'editor'        => ['version' => '5.35.3', 'js' => [...], 'css' => [...]],   // CKEditor
  'simpleeditor'  => ['version' => '0.8.20', ...],                              // Summernote
  'datetimepicker'=> [...],
  'codeeditor'    => [...],                                                      // Ace
  'spectrum'      => [...],
  'autocomplete'  => [...],
  'daterangepicker' => [...],
  'hljs'          => [...],
  'blockadblock'  => [...],
  'coloris'       => [...],
  'cropper'       => [...],
];
```

When `USECDN = true` the app picks the CDN URL; otherwise it loads from `public/static/frontend/libs/`.

### `app/config/api.php`

**Not router config — it's the developer-docs payload.** 63KB PHP array consumed by `PageController@api` (`/developers` page). Each top-level group has `admin`, `title`, `description`, `endpoints[]`. Used to render curl-style example requests and responses. See [12-api.md](./12-api.md#api-documentation-source).

## Layer 3 — DB-backed settings (`settings` table)

Loaded into `Gem::$Config` (an `stdClass`) at bootstrap by `Models\Settings::getSettings()`. Each row's `config` becomes a property; each `var` becomes its value (JSON-parsed via `parseIfJSON`).

Access:
```php
config('title')                 // → Gem::$Config->title
config('stripe')->enabled       // nested objects when var is JSON
Gem::$Config->cdn->key          // direct
```

Update from controllers:
```php
Settings::setSetting('stripe', json_encode([...]));
Settings::updateSettings();     // reload + rebroadcast
```

### Common setting keys

| Key | Meaning |
|---|---|
| `title`, `description`, `keywords` | Site metadata |
| `url` | Canonical site URL (overridden if `FORCEURL=false`) |
| `language` | Active locale |
| `default_lang` | Default locale code |
| `timezone` | PHP timezone string |
| `maintenance` | Maintenance-mode toggle |
| `private` | Admins-only mode |
| `home_redir` | Redirect target when in private mode |
| `pro` | Master toggle for paid features (pricing, checkout, plans) |
| `api` | Master toggle for API |
| `user` | Allow user registration |
| `userlogging` | Write to `appevents` |
| `verifylink` | Public link verification feature |
| `publicqr` | Public QR generator |
| `qrlogo`, `qrframes` | Premium QR feature flags |
| `freetrial` | Free-trial enabled |
| `currency`, `vatrate` | Money |
| `cdn` | `{enabled, key, secret, region, url, provider}` |
| `stripe`, `paddle`, `paddlebilling`, `paypal`, `paypalapi`, `mollie`, `paystack`, `bank` | Per-gateway config |
| `mailgun`, `sendgrid`, `postmark`, `mailchimp`, `smtp` | Per-mailer config |
| `recaptcha`, `hcaptcha`, `turnstile`, `altcha` | Captcha config |
| `domain_names` | Allowed external domains |
| `plugins` | `{plugin_name: true|false}` — active plugins (loaded by `Plugin::preload`) |
| `slacksigningsecret` | Slack request signature secret |
| `saleszapier` | Post-sale webhook URL |
| `webrisk`, `virustotal` | Safety check API keys |

Settings are edited from `Admin\Settings` (`/admin/settings` and `/admin/settings/{config}`). The admin UI is generated from form definitions discoverable per setting key.

## How `config()` and `appConfig()` differ

| Helper | Source | When to use |
|---|---|---|
| `config('foo')` | `Gem::$Config->foo` — DB settings | Anything an admin can edit at runtime |
| `appConfig('app.key')` | `app/config/<file>.php` array | Compile-time / deploy-time defaults |

Both are global functions defined in `core/functions/core.php`.

## Environment-style variables — there is no `.env`

The framework does **not** read `.env` files. Everything is either a `define()` in `config.php` or a DB setting. If you want `.env`-style configuration, you'd have to add it manually (e.g. add `vlucas/phpdotenv` to composer, load it from `app/core.php` before `config.php`).

## Theme config (`storage/themes/<theme>/config.json`)

Read by `View::config()`. Used by `Plugin::preload()` to load theme-specific includes, and by `View::template()` to follow the `child` directive (theme inheritance):

```json
{
  "name": "Default",
  "version": "1.0",
  "author": "GemPixel",
  "child": false,
  "include": ["theme.php"]
}
```

`child: true` falls back to the default theme. `child: "the23"` falls back to a specific theme.

## Build / deploy

There is **no build pipeline** for first-party PHP. JavaScript bundles in `public/static/` are pre-compiled (`bundle.pack.js`, `webpack.pack.js`, `*.min.js`) — likely generated by an out-of-tree process.

Deploy steps for a fresh install:
1. Upload all files to server.
2. Point virtualhost docroot to `public/`.
3. Run `composer install` if vendor not shipped.
4. Hit any URL — `SetupController` shows the installer, generates `config.php` from `config_sample.php` (not in this repo — must exist for fresh installs).
5. Cron jobs: see [10-business-logic.md](./10-business-logic.md#5-cron-jobs).

Deploy steps for upgrade:
1. Replace files (keep `config.php`, `storage/`, `public/content/`).
2. Hit `/update` → `UpdateController` runs schema migrations and one-off corrections (`extracorrections`, `importFaqs`, `migrateTeams`, `migratepixels`).
