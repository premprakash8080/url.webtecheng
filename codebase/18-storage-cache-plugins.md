# 18 — Storage, Cache, Plugins

## Storage layout (`storage/`)

Runtime-mutable state. Must be writable by the web user.

```
storage/
├── app/                      Misc app state (imports/, GeoLite2-City.mmdb, …)
├── cache/                    Phpfastcache 'files' driver root
├── languages/                i18n files: <lang>/{app,admin,api,email,sample}.php
├── logs/                     Monolog stream files: Log-MM-DD-YYYY.log + per-channel logs
├── plugins/                  Plugin directories — plugin.php is each plugin's entrypoint
│   ├── helloworld/           Sample plugin
│   └── index.php             Stub
└── themes/                   Theme directories
    ├── default/
    └── the23/
```

| Path | Purpose | Set by |
|---|---|---|
| `storage/app/` | App-internal state | Free-form |
| `storage/app/imports/` | Pending CSV imports | `User\Import` writes; `Cron@imports` reads |
| `storage/app/GeoLite2-City.mmdb` | MaxMind GeoIP DB | Bundled or admin-uploaded |
| `storage/cache/` | Phpfastcache files | `Helper::cacheConfig(appConfig('app.cachepath'))` |
| `storage/languages/<lang>/<file>.php` | Translation strings | `Core\Localization` |
| `storage/logs/Log-MM-DD-YYYY.log` | System channel | `GemError::logger(LOGS)` |
| `storage/logs/<channel>.log` | Custom Monolog channels | `GemError::channel($name, LOGS)` |
| `storage/plugins/<name>/plugin.php` | Plugin entrypoint | `Plugin::preload` includes when active |
| `storage/themes/<name>/` | Theme | `View::template` resolves from here |

## Cache (Phpfastcache)

Driver: `files`. Root: configurable via `appConfig('app.cachepath')` (defaults to `storage/cache/`).

API (all on `Core\Helper`):

```php
Helper::cacheGet($key);
Helper::cacheSet($key, $value, $expiry = 86400);  // seconds
Helper::cacheUpdate($key, $value);
Helper::cacheExpiry($key);
Helper::cacheDelete($key);
Helper::cacheClear();
Helper::cacheInstance();   // raw Phpfastcache instance
```

**All methods short-circuit when the `CACHE` constant is `false`.** Set `CACHE=false` in `config.php` to disable caching globally (useful for debugging).

### What's cached

The framework itself caches very little — it's mostly app-level usage:

- `ShortenThrottle` and `Throttle` middleware key rate-limit counters here.
- `User::notifications()` and other read-heavy queries cache results explicitly.
- Settings + plan are cached **in-memory per-request** in `Gem::$App`, not in the Phpfastcache layer.

### Clearing cache

- Admin: `Admin\Tools@tool_clearcache` (`/admin/tools/action/clearcache`).
- CLI: delete contents of `storage/cache/`.

## Plugins

Pluggable extension system. Activated plugins are loaded at every bootstrap.

### Plugin structure

```
storage/plugins/<plugin-name>/
├── plugin.php           Entrypoint — registers hooks
├── <other files…>       free-form
```

Minimal `plugin.php`:

```php
<?php
use Core\Plugin;

Plugin::register('link.preredirect', function($url) {
    // do something every time a short link is hit
});

Plugin::register('payment.success', function($payment) {
    // log all payments to an external service
});

Plugin::register('payment.extend', function() {
    return [
        'mygateway' => [
            'enabled' => true,
            'name'    => 'My Gateway',
            'payment' => [\MyGateway::class, 'pay'],
            'webhook' => [\MyGateway::class, 'hook'],
        ],
    ];
});
```

### Loading order

`Plugin::preload()` runs from `Gem::Bootstrap()`:

1. **Theme includes** — reads `View::config('include')` (the active theme's `config.json:include` list) and `include_once`s each file. Falls back to default theme or a `child` theme. Useful for theme-level PHP overrides.
2. **Active plugins** — reads `config('plugins')` (a DB setting — JSON map `{plugin: bool}`). For each truthy entry, `include`s `PLUGIN/<name>/plugin.php`.

### Built-in events plugins can hook

| Event | Fired by | Payload |
|---|---|---|
| `error.exception` | `GemError::exception` | `[$code, $msg, $file, $line]` |
| `error.fatal` | `GemError::fatal` | `[$message]` |
| `error.trigger` | `GemError::trigger` | `[$code, $error, $uri]` |
| `link.preredirect` | `LinkController@redirect` | `$url` |
| `link.redirect` | `LinkController@redirect` | `$url` |
| `link.override` | `LinkController@redirect` | `$url` |
| `payment.success` | `WebhookController@index` | `$payment` |
| `payment.extend` | `Traits\Payments::processor` | — (returns gateway list) |
| `middleware.csrf.exempt` | `Middleware\CSRF::handle` | — (returns extra exempt paths) |

App-level events plugin developers can also register / dispatch from custom code. There's no master registry; grep the codebase for `Plugin::dispatch(` to find every event.

### Plugin admin

Admin can install / activate / disable / delete plugins via `/admin/plugins` (`Admin\Plugins`):

- Upload `.zip` via `upload`.
- Browse marketplace via `directory` and `single`.
- `activate` / `disable` toggles the entry in `settings.plugins` JSON.
- `delete` removes the directory.

### Sample plugin

`storage/plugins/helloworld/` ships as a working example.

## Languages

Each locale lives at `storage/languages/<code>/`. Standard files inside:

- `app.php` — public-site + dashboard strings
- `admin.php` — admin-panel strings
- `api.php` — API doc strings
- `email.php` — transactional email strings
- `sample.php` — example for translators

Each language file returns:

```php
return [
    'name'   => 'English',
    'code'   => 'en_US',
    'region' => 'US',
    'author' => '…',
    'rtl'    => 0,
    'data'   => [
        'Hello' => 'Hello',
        // …
    ],
];
```

Used by `Core\Localization`. URL prefix `/{lang}` is auto-detected when the first segment matches a registered code (see `routes.php:23`).

Admin can add languages via `/admin/languages` (`Admin\Languages`): create, upload, edit, sync, auto-translate via `Helpers\GoogleTranslate`.

## File uploads

`Request::move($name, $directory, $filename)` handles uploads:

```php
$file = $request->file('avatar');
if (!$file->isvalid) {
    return back()->with('danger', $file->error);
}
$path = $request->move('avatar', UPLOADS.'/avatar', $file->name);
```

When the CDN is enabled (`settings.cdn.enabled = true`), `Request::move()` automatically uploads to S3 via `Helpers\CDN::upload($key, $path, $type)` instead of writing to local disk.

Allowed extensions per upload type live in `appConfig('app.extensions')`. Size limits live in `appConfig('app.sizes')`.

## Backups

`Admin\Tools@backup` (`/admin/tools/backup`) and `Admin\Tools@restore` provide a simple SQL-dump backup/restore mechanism. Generated dumps land under `storage/app/`.

## Imports

CSV link imports are queued through a two-step process:

1. User uploads CSV via `User\Import@importLinks` → file written to `storage/app/imports/`, row inserted in `imports` table with `status='pending'`.
2. Cron daemon hits `/crons/imports/{token}` → `Cron@imports` processes up to 250 lines per run, rewriting the file with the remainder.

User can cancel a pending import via `User\Import@cancel`.
