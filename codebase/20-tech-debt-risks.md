# 20 — Technical Debt & Risks

A senior-engineer's honest read of the surface area you should treat with care.

## Security risks

### Secrets committed to git

`config.php` at the repo root contains what look like **real** values for `DBpassword`, `AuthToken`, `EncryptionToken`, and `PublicToken`. This file is tracked (it's not in `.gitignore`).

- If this repo is public or has ever been public, treat all four secrets as compromised: rotate the DB password, rotate `AuthToken` (forces all users to reset passwords), rotate `EncryptionToken` (invalidates all cookies and encrypted sessions).
- Add `config.php` to `.gitignore` and ship a `config_sample.php` instead (the installer expects this — `SetupController` generates `config.php` from a sample).

### `Helper::Encode`'s `$encoding` arg is a lie

```php
public static function Encode($string, $encoding = "bcrypt") {
    return password_hash($string.AuthToken, PASSWORD_BCRYPT, ['cost' => 10]);
}
```

The `$encoding` parameter is ignored — always bcrypt. Most callers pass nothing, so no real risk; just don't trust the signature.

### CSRF session key is misspelled

`Helper::CSRFNAME = "__CRSF"` (sic). Consistent everywhere, so it works — but the misspelling has propagated through all code that reads `$_SESSION["__CRSF"]` directly. Rename with caution; many callers don't go through the constant.

### `Auth::loginId` uses `auth_key` instead of `authkey`

```php
public static function loginId(int $id) {
    $user = User::first($id);
    $user->auth_key = ...;          // ← inconsistent with login()'s "authkey"
}
```

Since `User::AUTHKEY = 'auth_key'` (snake_case), `loginId` is actually **correct** — but the rest of the codebase mixes `authkey` and `auth_key`. Easy to copy-paste wrong. Always use the constant: `$user->{User::AUTHKEY}`.

### `Auth::logged($role)` always returns true

```php
public static function logged($role = null) {
    if (!self::check()) return false;
    if ($role) {
        // intends to check the role
    }
    return true;                    // ← short-circuits any role check above
}
```

The role-check branch is dead code. Callers that pass a `$role` argument get a false positive. Rely on `Helpers\Permissions::can()` for actual role checks.

### `Collection::chunk` is broken

```php
public function chunk(int $num) {
    return array_chunk($items, $num);   // ← $items is undefined here
}
```

Will throw a notice/warning. Not called from anywhere in the first-party codebase as far as visible.

### Mailchimp / Postmark drivers have minor bugs

- `Core\Support\Mailchimp::fallback()` references `$this->data['message']['from_email']` which doesn't exist in `$data`.
- `Core\Support\Postmark::send()` sets `TrackOpens` twice (probably meant `TrackLinks` once).

Both still functional in the happy path; the bugs surface only in fallback/edge cases.

### Verbose SDK exposure

When `DEBUG=2`, `Core\Email` enables PHPMailer SMTP debug output and `Core\Http` disables SSL verification. **Never use in production.** A `DEBUG` constant left on in production would leak SMTP credentials in the response stream and accept invalid TLS certs.

### Bot detection is a sledgehammer

`Middleware\BlockBot::handle()` calls `die()` with no message, no status, no log entry. Any false positive — legitimate scraping, monitoring, accessibility tool — produces a connection close that's hard to diagnose. Consider replacing `die()` with `Response::factory('', 403)->send()` for diagnosability.

### Inline HTML in `core/GemError.class.php`

The fallback debug error page (`GemError::template`) prints request data and (when xdebug is available) a function stack trace. With `DEBUG > 0` in production, this is a substantial information disclosure. Always ship with `DEBUG = 0`.

### Auto-update over plain HTTPS, requiring purchase key

`Helpers\AutoUpdate` pulls release zips from `cdn.gempixel.com/updater`. If the vendor's TLS chain is ever compromised, an attacker could inject a malicious update. The mechanism is single-vendor and there's no signature verification beyond TLS.

## Architectural risks

### One God-controller

`app/controllers/admin/UsersController.php` has **30+ public actions** across CRUD, bans, roles, testimonials, teams, plan changes, import, login-activity, login-as. Touching it is risky — split-by-concern would help but is a big change.

### Heavy traits used as namespaces

`Traits\Links` is `use`d by 13 callers including middleware, controllers, and helpers. Effectively a god-class accessed via mixin. Adding methods means re-reading all 13 sites to spot collisions.

### No DI, no service layer

Every controller `new`'s up its own dependencies (mostly static facades). Testing is essentially impossible without process-level fixtures. Refactoring requires global understanding.

### No tests

Zero automated tests. Every refactor risks invisible regressions. Verify changes manually by exercising the route. High-value places to start hand-testing if you change anything load-bearing:

- `Link@redirect` — every link click flows through here.
- `UsersController@loginAuth` — auth + 2FA + new-IP detection.
- `WebhookController@index` — Stripe/Paddle/PayPal webhooks.
- `Cron@*` — long-running batch logic.

### "Empty" admin controller

`app/controllers/admin/IntegrationsController.php` is **1 byte** (empty file). If a route or menu link points at `Admin\Integrations@index`, it will fail mysteriously. Verify before relying on integrations admin.

### Catch-all route fragility

`Gem::route(['GET', 'POST'], '/{alias}', 'Link@redirect')` at line 723 must remain the **last** root-level route. Any new root-level route added after this becomes unreachable (FastRoute respects definition order for static-first dispatch, but for routes with parameters it falls back to definition order). Adding before this requires checking for collisions with existing aliases.

### `routes.php` size

735 lines of mostly-flat route definitions. No grouping by file. Adding a new feature involves scrolling through hundreds of lines to find the right section.

### API documentation and routes can drift

`app/config/api.php` (63KB) is hand-maintained alongside `app/routes.php`. They will diverge over time — a route added in `routes.php` won't appear in `/developers` until someone updates `api.php`. There's no automated check.

## Operational risks

### No migrations

There are no SQL migration files. Schema is built in-line by `SetupController` (initial install) and `UpdateController` (version bumps). Inspecting the current schema requires a live DB connection. Adding a new column requires:
1. Editing `UpdateController` to add an `if (!DB::columnExists(…))` block.
2. Editing `SetupController` to include the new column in fresh installs.
3. Bumping a version flag (varies per release).

This is fragile and undocumented.

### Cron tokens are derivable

Cron tokens are `md5('<endpoint>'.AuthToken)`. If `AuthToken` leaks, anyone can fire `/crons/users/...` and force-downgrade users or delete stats. Treat the cron endpoints as authenticated only as long as `AuthToken` stays secret.

### Asset cache busting is filesystem-mtime based

`View::compile` and `View::assets` use `?mtime=<timestamp>` for cache busting. Deploys that touch files without changing content invalidate caches unnecessarily; deploys that change content but preserve mtime (rare) skip cache invalidation. Generally OK, but watch out on rsync deploys with `--times`.

### Cache disables silently

`Helper::cacheGet` and friends short-circuit when `CACHE = false`. If you disable caching for a debug session and forget to flip it back, app behavior changes silently in places (rate limits stop working — `ShortenThrottle` and `Throttle` become no-ops).

### Single-driver assumption

`Core\DB::Connect()` builds a `mysql:` DSN unconditionally. Switching to PostgreSQL or SQLite would require editing the framework — not a config change.

## Coupling hotspots

| Concept | Coupled across |
|---|---|
| Settings | Every controller via `config()` global |
| Plans/permissions | `Models\User`, every controller, every plan-gated feature |
| `Traits\Links` | 13 callers — middleware, controllers (root, admin, api, user), helpers (`BioWidgets`) |
| `Helpers\Gate` | `LinkController`, `CheckDomain` middleware, `Traits\Links` |
| `Models\Settings::getSettings()` side effects | Sets timezone, locale, CDN wiring, defines `CDNCUSTOMURL` — touching it ripples everywhere |
| `Gem::$Config` | Read everywhere — a global mutable object |

## What to plan around

- **Renaming routes is dangerous.** Reverse-routing via `route('foo.bar')` is everywhere, including in templates.
- **Renaming controller classes is dangerous.** The autoloader expects `<Name>Controller.php` exactly; route strings reference `"Class@method"` literally.
- **Renaming DB columns is dangerous.** Models hand-roll column references; missing one means silent data loss on update/save.
- **Schema changes need both `SetupController` and `UpdateController`.**
- **Adding a new gateway requires editing `Traits\Payments::processor()` OR a `payment.extend` plugin.** Adding new routes (`/webhook/X`, `/callback/X`) is automatic — they all go through `WebhookController@index`.
- **Adding a new theme** means creating `storage/themes/<name>/` with at least `config.json`, `header.php`, `footer.php`, and any error templates you want.

## Quick wins (low-risk improvements)

- Add `config.php` to `.gitignore`. Document that operators must keep their copy.
- Replace `die()` in `BlockBot` with a logged 403.
- Add a CHANGELOG note when bumping `_VERSION` in `app/core.php`.
- Add `vlucas/phpdotenv` and migrate the `define()`s in `config.php` to environment variables (still loaded as constants, but values come from `.env`).
- Consider PHP 8.x — the framework checks PHP ≥ 7.4 but the installer recommends 8.2, and several Composer deps now require 8.x.

## Risky changes that need a wide impact analysis

- Replacing the routing layer (FastRoute → Symfony Router): touches `Gem::Bootstrap`/`Dispatch`, every route definition, reverse-routing via `Gem::href`.
- Switching ORMs (Idiorm/Paris → Eloquent): touches every `DB::*()` and every model. Very large.
- Adding a queue: needs `Cron@*` migration and rework of `Traits\Links::updateStats` (currently synchronous).
- Replacing custom `View` with Twig/Blade: touches every template, every `render`/`view` global, every layout-inheritance call site.

All of these are "rewrite" scale, not "refactor" scale. Plan accordingly.
