# 08 — Middleware

14 middleware classes under `app/middleware/`, namespace `Middleware\`. The actual middleware pipeline lives in `Gem::Dispatch()` — not in `Core\Middleware`. Each class implements `handle($request)` (and optionally other named methods that routes can target via `"Name@method"` syntax).

Conventions:
- Returning `false` from `handle` aborts the request (most middleware also `exit;` after sending a response).
- AJAX requests usually get JSON `{error: true, message: ...}`, otherwise the user is redirected back with a `danger` flash.
- All middleware classes are stateless (no DI, no constructor args).

## The 14 middleware

### `Auth.php`

The most-used middleware. Three handler methods, three different gates:

| Method | Used by | Blocks if | On block |
|---|---|---|---|
| `handle()` | `/user/*` after `Gem::setMiddleware(['CheckDomain','Auth'])` | Not logged in; banned; team's owner no longer has a team; pro expired (auto-downgrades to free first) | Redirect to `route('login')` with flash |
| `admin()` | `/admin/*` group (`Auth@admin`) | Not admin | `GemError::trigger(404)` — pretends the route doesn't exist |
| `api()` | `/api/*` group (`Auth@api`) | `config('api')` disabled · missing `Authorization: Bearer …` · key resolves to no user · user lacks `api` feature · user is banned · account not activated | JSON 403 `{error: 1, ...}`. Also chains in `Throttle` via `Gem::addMiddleware('Throttle')` |

### `BlockBot.php`

`Jaybizzle\CrawlerDetect\CrawlerDetect::isCrawler()` — `die()` immediately on match. No status, no message, no log. Used on public form posts (contact, captcha challenge, shorten).

### `CSRF.php`

Validates `$_SESSION["__CRSF"]` against `$request->_token`. Hardcoded exempt paths: `shorten`, `user/qr/preview`, `api/*`, `admin/languages/translate`, `user/bio/`. Extra exempts are appended via the `middleware.csrf.exempt` plugin hook.

Failure → AJAX `{error: true, message}`; non-AJAX → redirect back with `danger` flash. Success → token is consumed (`unset`).

**Auto-attached:** every `Gem::post()` call appends `"CSRF"` to the route middleware list. No opt-out.

### `CheckDomain.php`

Custom-domain resolver. Runs on virtually every public route.

- Sets a 90-day `urid` affiliate cookie when `?ref=` is in the query.
- If `Host` ≠ `config('url')`:
  1. Look up host in `DB::domains()`.
  2. If row has `bioid` → render bio profile via `Helpers\Gate::profile()` and `exit`.
  3. If row has `redirect` → 302 to that URL and `exit`.
  4. If host is listed in `config('domain_names')` (allowed but unmapped) → redirect to `config('url')`.
  5. Otherwise render the `gates.domain` "your domain is working" landing and `exit`.

Uses `\Traits\Links` for `updateStats()`. IDN hosts are normalized via `idn_to_utf8()`.

### `CheckMaintenance.php`

When `config('maintenance')` is on and the visitor is not logged in → render `maintenance` view inside `layouts.auth` and `exit`. Logged-in users pass through (so admins can still browse).

### `CheckPrivate.php`

When `config('private')` is on:
- Admins pass.
- If `config('home_redir')` is set → 302 to that URL.
- Else render `private` view inside `layouts.auth` and `exit`.

Used to restrict an entire install to admins (white-label / development).

### `DemoProtect.php`

Reads the global constant `_STATE`. When `_STATE === 'DEMO'`:
- AJAX → JSON `{error: true, message: 'This feature is disabled in demo.'}`
- Otherwise → redirect back with `danger` flash.

Used to neuter destructive demo actions. Not auto-attached anywhere visible — needs explicit `->middleware('DemoProtect')`.

### `Locale.php`

Two handlers:
- `handle()` — no-op (never blocks).
- `admin()` — swaps in admin translation bundle: `Localization::setFile('admin'); Localization::update();`. Used on every `/admin/*` route.

### `RolePermission.php`

`handle($permission = null)` — pass a permission string when adding the middleware (`->middleware('RolePermission@plans.view')` style):

- Not logged in → redirect to login with danger flash.
- `$user->admin` truthy → pass (superadmin shortcut).
- `$user->hasRolePermission($permission)` → pass; else redirect to `admin` with "You do not have permission".

### `ShortenThrottle.php`

Per-session rate limit for the anonymous `/shorten` endpoint. Static config `$ratelimiter = [5, 1]` — 5 hits per 1 minute.

- Generates 12-char `throttlekey` stored in session.
- Tracks via `Helper::cacheGet/cacheSet/cacheUpdate('shorten'.$key)`.
- Emits `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset` headers.
- On exceed → JSON 429 with `Retry-After` and `exit`.
- `CACHE === false` short-circuits to pass (no throttling).

### `Throttle.php`

Per-API-key rate limit for `/api/*`. Defaults from `appConfig('app.throttle')` (default `[30, 1]` = 30 per minute), overridden per-user via the `apirate` feature limit. A user's `apirate` of `0` means unlimited.

Same shape as `ShortenThrottle` but keyed off the `Authorization: Bearer <token>` string. `CACHE === false` skips throttling.

### `UserLogged.php`

Block already-logged-in users from auth pages (login, register, forgot, reset). Logged in → 302 to `route('dashboard')`.

### `ValidateCaptcha.php`

Instantiates `\Helpers\Captcha` and calls `$captcha->validate($request)`. On exception: AJAX JSON `{error: true, message, token}` or redirect-back with `danger`. Then `exit`.

Supports reCAPTCHA v2/v3, hCaptcha, Cloudflare Turnstile, Altcha — provider chosen by `config('captcha')`.

### `ValidateLoggedCaptcha.php`

Same as `ValidateCaptcha` but `Auth::logged()` short-circuits to pass. Used on `/shorten` so logged-in users skip the captcha but anonymous visitors don't.

---

## Where each is applied

| Middleware | Usage pattern |
|---|---|
| `Auth` | Group middleware on `/user/*` (138 routes) |
| `Auth@admin` | Group middleware on `/admin/*` (242 routes) |
| `Auth@api` | Group middleware on `/api/*` (41 of 42 routes — `/oauth/token` is public) |
| `Locale@admin` | Group middleware on `/admin/*` |
| `CheckDomain` | On nearly every public route, blog, all user dashboard routes, API routes |
| `CheckMaintenance` | Public marketing routes (home, pricing, pages, etc.) |
| `CheckPrivate` | Public marketing + content routes |
| `UserLogged` | Auth pages (login, register, password reset, activation, invited, 2FA) |
| `BlockBot` | Public form posts: shorten, contact send, report send, captcha challenge |
| `ValidateCaptcha` | Auth POSTs (login, register, password reset), public form sends |
| `ValidateLoggedCaptcha` | `/shorten` (anonymous + logged-in) |
| `ShortenThrottle` | `/shorten` |
| `Throttle` | API group + chained dynamically by `Auth@api` on failure |
| `CSRF` | **All POST routes** (auto-attached by `Gem::post()`) — no opt-out |
| `DemoProtect` | Manually added per-route in demo deployments |
| `RolePermission` | Manually added on admin sub-routes (gated by named permission) |

## Adding a new middleware

1. Create `app/middleware/X.php` with `namespace Middleware;` and `class X { public function handle($request) { … } }`.
2. Apply via route chain: `->middleware('X')` or group: `Gem::setMiddleware(['X', 'Y'])`.
3. To target a non-`handle` method, use `"X@method"` syntax.
4. Returning `false` aborts the pipeline; most middleware also `exit;` directly.
