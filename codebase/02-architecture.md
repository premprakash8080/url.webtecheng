# 02 — Architecture

## Stack

| Layer | Tech |
|---|---|
| Language | PHP **7.4+** (`public/index.php` hard-checks via `version_compare`) |
| Web server | Apache (`.htaccess` rewrites `^(.*)$ → public/$1`) **or** Nginx (sample `nginx.conf` provided; docroot must be `/public`) |
| Dependency manager | Composer |
| Router | `nikic/fast-route` (wrapped by `Core\Gem`) |
| ORM | `Core\Support\ORM` — vendored, namespaced **Idiorm** (`core/support/ORM.class.php` is the literal Idiorm source) with **Paris** (`core/Model.class.php`) on top |
| Database | MySQL (only driver wired; `mysql:host=...` DSN hardcoded in `DB::Connect()`) |
| Cache | `phpfastcache/phpfastcache` (driver `files`, configurable path) |
| Templating | Hand-rolled PHP `include`-based engine in `Core\View` (no Blade/Twig) |
| HTTP client | Hand-rolled cURL wrapper in `Core\Http` |
| Mailer | PHPMailer + 4 transactional drivers (Mailgun, SendGrid, Postmark, Mailchimp/Mandrill) |
| Logging | `monolog/monolog` ^2.0 via `GemError` |
| Encryption | `defuse/php-encryption` (Defuse\Crypto) keyed off `EncryptionToken` |
| QR | `endroid/qr-code` (GD path) + `bacon/bacon-qr-code` (Imagick path) |
| Payments | Stripe, Mollie, PayPal REST, Paddle Billing — vendored SDKs |
| 2FA | `sonata-project/google-authenticator` |
| Captcha | reCAPTCHA, hCaptcha, Turnstile, Altcha |
| Bot block | `jaybizzle/crawler-detect` |
| GeoIP | `maxmind-db/reader` + bundled `GeoLite2-City.mmdb` |

## High-level layers

```
┌─────────────────────────────────────────────────────────────┐
│  HTTP entry: .htaccess / nginx try_files → public/index.php │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
                ┌─────────────────────────────┐
                │  app/core.php               │  defines path constants (ROOT, APP, …)
                │  - includes core functions  │  installs autoloaders, error handlers
                │  - includes vendor autoload │  loads Composer libraries
                │  - includes Gem class       │
                │  - Gem::preload()           │  runs boot.php callbacks → SetupController::check
                │  - includes config.php      │  defines DB*, AuthToken, EncryptionToken…
                │  - includes app/routes.php  │  registers all 526 routes
                └──────────────┬──────────────┘
                               │
                               ▼
                ┌─────────────────────────────┐
                │  Gem::Bootstrap()           │  (called from public/index.php)
                │  1. DB::Connect             │  PDO MySQL
                │  2. Settings::getSettings   │  load DB settings into Gem::$Config
                │  3. Locale, base URL, theme │
                │  4. Plugin::preload         │  include theme + plugin files
                │  5. FastRoute dispatcher    │
                │  6. Gem::Dispatch           │  resolve → middleware → controller
                └──────────────┬──────────────┘
                               │
                ┌──────────────┴──────────────┐
                ▼                             ▼
        Middleware pipeline           Controller resolution
        (per route)                   "Name@method" or [class, method]
                                          │
                                          ▼
                                  Controller action
                                  - uses Core\DB / Models
                                  - uses Helpers\, Traits\
                                  - returns via Core\Response
                                    or Core\View::render
```

## Bootstrap sequence (exact order)

`public/index.php` is intentionally minimal:

```php
if (version_compare(phpversion(), '7.4', '<')) die(...);
include(dirname(dirname(__FILE__))."/app/core.php");
Gem::Bootstrap();
```

`app/core.php` runs in this order:

1. Defines path constants: `ROOT`, `APP`, `PUB`, `CORE`, `CONTROLLER`, `MODELS`, `MIDDLEWARE`, `LIBRARY (=vendor)`, `UPLOADS (=public/content)`, `STORAGE`, `LOGS`, `LOCALE`, `PLUGIN`.
2. `include(CORE."/functions/core.php")` — registers `autoloadCore` + `autoloadController` via `spl_autoload_register`, registers global error handler `GemError(...)` and shutdown handler `FatalError()`, and defines global helpers `config()`, `appConfig()`, `clean()`, `user()`, `auth()`, `parseIfJSON()`, `currentPage()`. At the end it `include`s `core/functions/helpers.php` which defines `url()`, `route()`, `csrf()`, `csrf_token()`, `meta()`, `render()`, `view()`, `e()`, `_e()`, `ee()`, `request()`, `old()`, `back()`, `assets()`, `uploads()`, `pagination()`, `simplePagination()`, `plug()`, `stop()`, `middleware()`, and ~10 more.
3. `include(CORE."/GemError.class.php")` — error/logger class.
4. `include(LIBRARY."/autoload.php")` — Composer.
5. `include(CORE."/Gem.class.php")` — kernel.
6. `Gem::preload()` — starts session, walks `appConfig('boot')` (currently `[[Setup::class, 'check']]`), calls each callable; if any returns `false` it exits early (used by the installer).
7. `include(ROOT."/config.php")` — defines `DBhost/DBname/...`, `AuthToken`, `EncryptionToken`, `BASEPATH`, `DEBUG`, `CACHE`, `FORCEURL`, `USECDN`, `TIMEZONE`.
8. `include(APP."/routes.php")` — runs every `Gem::get|post|route|group(...)` call to populate `Gem::$controllers`.

Then `Gem::Bootstrap()` (from `public/index.php`):

1. `DB::Connect()` — PDO MySQL with `MYSQL_ATTR_INIT_COMMAND => SET NAMES utf8mb4`.
2. `Gem::$Config = \Models\Settings::getSettings()` — loads every row of the `settings` table into an `stdClass`, with each row's `config` becoming a property and `var` (JSON-parsed) becoming the value. Side-effects: sets PHP timezone, picks the active language, bootstraps `Localization`, wires `Helpers\CDN` if enabled.
3. `Gem::$Base` resolved from `BASEPATH` constant or auto-detected.
4. `GemError::logger(LOGS)` + `Helper::cacheConfig(...)`.
5. Unless `FORCEURL`, overwrite `Gem::$Config->url` from current Host.
6. `Plugin::preload()` — `include_once`s each file in the active theme's `config.json:include` list, then `include`s each active plugin's `plugin.php`.
7. Build `FastRoute\simpleDispatcher` from `Gem::$controllers`.
8. `Gem::Dispatch()` — finds route, runs middleware, calls controller.

## Request lifecycle (after bootstrap)

```
.htaccess rewrites /{anything} → /public/{anything}
   ↓
public/index.php loads everything above
   ↓
Gem::Dispatch():
  - new Core\Request (captures GET/POST/FILES, normalizes)
  - strip Gem::$Base prefix from REQUEST_URI, drop ?query
  - FastRoute::dispatch(method, path)
     ├─ NOT_FOUND       → GemError::trigger(404) → render errors/404 template
     ├─ METHOD_NOT_ALLOW → GemError::trigger(405)
     └─ FOUND           ↓
  - sanitize route vars via Helper::RequestClean (level-3 XSS strip)
  - stash vars on $request->_HTTPPARAMETERS
  - for each middleware in route['middleware']:
      require MIDDLEWARE/<Class>.php
      $mw = new \Middleware\<Class>()
      $result = $mw->{method|handle}($request)
      if ($result === false) → abort (the middleware itself usually exit;'d already)
  - resolve handler:
      Closure → call directly
      [class, method] OR "Class@method" string → split, new $class()
  - via ReflectionMethod, type-hint inject method args (default-construct typed params,
    reuse $request for Core\Request, append route vars positionally)
  - call_user_func_array([$controller, $method], $args)
```

`Gem::post()` **always** appends `"CSRF"` to the route's middleware list at registration time. There is no opt-out flag — use `Gem::route(['POST'], …)` for a truly public POST.

## Data flow examples

### Public shortlink redirect (`GET /abc`)

```
.htaccess → public/index.php → app/core.php → Gem::Bootstrap → Gem::Dispatch
  match /{alias} → Link@redirect (root LinkController)
  middleware: none (catch-all)
  Link::redirect(Request, $alias='abc'):
    Trait\Links::getURL($alias)
      DB::url()->where('alias','abc')->first()  (with custom-domain handling via CheckDomain)
    Plugin::dispatch('link.preredirect', $url)
    safety checks: banned user, disabled, blacklist, Web Risk, Phish, VirusTotal
    branch on type:
      profile → Gate::profile(...) → render bio
      password → session check or Gate::password(...)
      direct → Gate::direct(...) → header('Location: …')
    Trait\Links::updateStats($request, $url, $user)  ← writes stats row, optional zapview webhook
```

### Create short link (`POST /shorten`)

```
public form / API call
  POST /shorten → Link@shorten
  middleware: BlockBot, ShortenThrottle, ValidateLoggedCaptcha, CSRF (auto)
  Link::shorten(Request):
    Auth::check (some configs allow anonymous)
    Permissions::can('links.create') for team members
    foreach url in payload:
      Trait\Links::createLink($request, $user)
        validate($url) → safe/phish/virus checks
        alias generation via Helper::rand($len, $prefix, $format)
        Helpers\App::shortRoute(...) to form final short URL
        DB::url()->create()->save()
    Response::factory($result)->json()
```

### Checkout (`POST /checkout/{id}/{type}`)

```
SubscriptionController@process
  Trait\Payments::processor($request->payment, 'payment') → [Helpers\Payments\<Gateway>, 'payment']
  call_user_func_array → gateway redirects to provider or settles inline
  Webhook arrives at POST /webhook[/{provider}] → WebhookController@index
  routed to gateway's webhook() → updates DB::subscription(), DB::payment()
  Plugin::dispatch('payment.success', $payment) → affiliate credit if applicable
```

## Cross-cutting concerns

- **Sessions:** PHP native sessions (started in `Gem::preload`). Auth cookie name `logged_in`, content is Defuse-encrypted JSON `{loggedin, key: authkey.id}`.
- **CSRF:** `$_SESSION["__CRSF"]` (typo in `Helper::CSRFNAME`) generated lazily; injected by `csrf()` helper. Validated by `Middleware\CSRF` for every POST.
- **i18n:** `Core\Localization` reads `storage/languages/<lang>/<file>.php`. URL prefix `/{lang}` auto-detected from segment 1 in `routes.php:21-27`.
- **CDN:** Two CDN concepts — (a) `USECDN`/`appConfig('cdn')` for third-party JS/CSS library delivery via public CDNs; (b) `Helpers\CDN` for S3-compatible storage of uploads (AWS, DO Spaces, Wasabi, B2, Contabo, custom).
- **Plugins:** `Core\Plugin::register('hook.name', callable)` + `Plugin::dispatch('hook.name', $payload)`. Activated plugins live under `storage/plugins/<name>/plugin.php` and are loaded by `Plugin::preload()` at bootstrap.

See [04-framework-core.md](./04-framework-core.md) for class-level detail.
