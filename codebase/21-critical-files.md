# 21 — Critical Files

The ~30 files you'd want to read first if you had to debug a production incident at 2 AM. Ordered by how load-bearing they are.

## Tier 1 — Touch at your peril

| File | Why it matters |
|---|---|
| `core/Gem.class.php` | Bootstrap + routing + middleware pipeline + controller resolution. Every request goes through here. |
| `core/DB.class.php` | PDO/MySQL connection. Schema builder. Extends Idiorm. All DB access flows through this or `Core\Model`. |
| `core/Auth.class.php` | Session-based auth. Cookie format, password verification, API key resolution. |
| `core/Model.class.php` | Paris base class. Static factory + `__callStatic` magic. Every `Models\*` uses this. |
| `core/support/ORM.class.php` | 2620 lines of vendored Idiorm — the actual SQL builder. |
| `app/routes.php` | Single source of truth for all 526 HTTP routes. Catch-all `/{alias}` is at line 723. |
| `app/controllers/LinkController.php` | Public shortlink redirect, link create, link edit, profile, verify. The heart of the product. |
| `app/traits/Links.php` | Link creation engine + click tracking + safety checks. Used by 13 callers. |
| `app/helpers/Gate.php` | Renders every kind of link-display page (direct, splash, password, frame, overlay, media, profile, bundle, deeplink). |
| `config.php` | DB credentials + `AuthToken` (password salt) + `EncryptionToken` (Defuse key). Editing rotates secrets. |

## Tier 2 — Core auth, billing, request flow

| File | Why it matters |
|---|---|
| `core/Request.class.php` | Request object. Captures GET/POST/FILES, geo-IP, sessions, cookies. File uploads (with CDN routing). |
| `core/Response.class.php` | JSON / HTML / redirect emitter. Every API endpoint uses `Response::factory(...)->json()`. |
| `core/Helper.class.php` | bcrypt, CSRF, encrypt/decrypt, cache, slugs, sanitization, pagination. Used everywhere. |
| `core/View.class.php` | Template engine, theme resolution, layout inheritance, asset URLs. |
| `core/Plugin.class.php` | Only event/hook system. Theme loading + plugin loading. |
| `core/GemError.class.php` | Error handlers, Monolog channels, error-page rendering. |
| `app/models/User.php` | The User model. `plan()`, `has()`, `hasLimit()`, `rID()` (team-aware), `notifications()`, `pixels()`. |
| `app/models/Settings.php` | DB-backed config loader. `Settings::getSettings()` builds `Gem::$Config` at bootstrap. |
| `app/controllers/UsersController.php` | Login, 2FA, register, password reset, social login, SSO, account activation, invitations. |
| `app/controllers/SubscriptionController.php` | Pricing page, checkout, payment processing dispatcher. |
| `app/controllers/WebhookController.php` | Payment provider webhook router. Affiliate credit closure. Slack `/shorten` command. |
| `app/traits/Payments.php` | Gateway registry. Single method `processor($type, $action)`. |
| `app/middleware/Auth.php` | Auth gate for `/user/*` + `Auth@admin` for `/admin/*` + `Auth@api` for `/api/*`. |

## Tier 3 — Important supporting files

| File | Why it matters |
|---|---|
| `app/core.php` | Path constants, autoloaders, `Gem::preload()` orchestration. |
| `public/index.php` | The literal entry point. Hard-codes PHP ≥ 7.4 check. |
| `.htaccess` (root) | Rewrites `^(.*)$ → public/$1` for Apache. |
| `nginx.conf` | Sample Nginx config. Docroot must be `public/`. |
| `core/functions/core.php` | Registers autoloaders + error handlers. Defines `config()`, `appConfig()`, `auth()`, `user()`, `clean()`, `parseIfJSON()`, `currentPage()`. |
| `core/functions/helpers.php` | ~30 global helpers (`url`, `route`, `csrf`, `e`, `render`, `assets`, `uploads`, `back`, `plug`, `stop`, …). |
| `app/config/app.php` | Storage paths, throttle defaults, geodriver, maildrivers, admin/api routes, default theme. |
| `app/middleware/CSRF.php` | Auto-attached to every POST. Hardcoded exempt list + plugin-extensible. |
| `app/middleware/CheckDomain.php` | Custom-domain resolution. White-label / branded domain mode. |
| `app/controllers/CronController.php` | Token-protected scheduled jobs: downgrade expired pros, prune stats, recheck URLs, send reminders, process imports. |
| `app/helpers/Emails.php` | All transactional email templates. Driver setup via `Core\Email::factory`. |
| `app/helpers/App.php` | Catch-all utility class. `shortRoute()`, `defaultPlan()`, `features()`, `checkEncryption()`, plus formatters. |

## Quick lookup by concern

| If you're debugging… | Open these first |
|---|---|
| Login fails | `app/controllers/UsersController.php` (`loginAuth`), `core/Auth.class.php` (`login`, `validatePass`), `core/Helper.class.php` (`Encode`, `validatePass`). |
| Shortlink redirects to wrong place | `app/controllers/LinkController.php` (`redirect`), `app/traits/Links.php` (`getURL`, `updateStats`), `app/helpers/Gate.php`. |
| Webhook not crediting plan | `app/controllers/WebhookController.php` (`index`), `app/helpers/Payments/<Gateway>.php` (`webhook`), check `storage/logs/Log-MM-DD-YYYY.log`. |
| API returning 403 | `app/middleware/Auth.php` (`api` method), `core/Auth.class.php` (`ApiUser`), check `config('api')` and `$user->has('api')`. |
| Cron silently failing | `app/controllers/CronController.php` (verify token = `md5('<name>'.AuthToken)`), check `storage/logs/<name>.log`. |
| Custom domain not resolving | `app/middleware/CheckDomain.php`, then `DB::domains()` table contents. |
| Upload not landing on S3 | `app/helpers/CDN.php`, `core/Request.class.php` (`move`, `setCDN`), check `settings.cdn.enabled`. |
| Error page is the inline white one | Theme is missing `errors/<code>.php`; `GemError::trigger` is using the framework fallback. |
| CSRF rejected | `app/middleware/CSRF.php` — check session `__CRSF` (note the typo) and exempt list. |
| 429 on API | `app/middleware/Throttle.php`, check `appConfig('app.throttle')` and `$user->plan('apirate')`. |
| 2FA blocking login | `core/Auth.class.php`, `app/controllers/UsersController.php` (`login2FA`, `login2FAValidate`), `sonata-project/google-authenticator`. |
| QR code not generating | `app/helpers/QR.php` (factory chooses GD vs Imagick), `app/helpers/QrGd.php` or `app/helpers/QrImagick.php`. |
| Plugin not loading | `core/Plugin.class.php` (`preload`), check `settings.plugins` JSON and `storage/plugins/<name>/plugin.php`. |
| Theme not switching | `core/View.class.php` (`template`, `config`), check `settings.theme` and `storage/themes/<name>/config.json`. |

## Files NEVER to edit

| File | Reason |
|---|---|
| Anything under `core/` | Proprietary framework. Vendor licence forbids modification. |
| Anything under `vendor/` | Composer-managed. Regenerated on `composer install`. |
| `core/support/ORM.class.php` | Vendored Idiorm — third-party code. |
| `app/config/api.php` | Documentation manifest — touch only when adding API endpoints (and keep `routes.php` synchronized). |

## Files YOU usually edit

| Need | File |
|---|---|
| Add HTTP endpoint | `app/routes.php` |
| Add controller | `app/controllers/[admin|api|user]/<X>Controller.php` |
| Add model | `app/models/<X>.php` |
| Add middleware | `app/middleware/<X>.php` |
| Add helper | `app/helpers/<X>.php` |
| Add gateway | `app/helpers/payments/<X>.php` + register in `Traits\Payments::processor()` |
| Override behavior without forking | `storage/plugins/<x>/plugin.php` (event-driven) |
| Customize template | `storage/themes/<theme>/<path>.php` |
| Add language | `storage/languages/<code>/app.php` (+ optional admin/api/email files) |
