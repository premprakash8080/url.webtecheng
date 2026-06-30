# 04 — GemFramework Core

GemFramework is a Laravel-shaped thin wrapper around FastRoute + Idiorm/Paris. There is **no DI container**, **no service provider**, **no event/queue system** beyond `Plugin::dispatch`, and **no `.env` abstraction**. State flows through (a) `define()`'d constants, (b) `Gem::$Config` (DB-backed settings), (c) `appConfig()` (file-backed config arrays), and (d) `Plugin::register/dispatch` hooks.

> All paths below are relative to `core/`.

---

## Gem (`Gem.class.php`)

**Application kernel + static routing facade.** Owns bootstrap, dispatch, route registration, and global config access.

Key static state:
- `$controllers` — route table keyed by name
- `$Config` — Settings object (loaded from DB during `Bootstrap()`)
- `$Base` — auto-detected base path
- `$App` — scratch bag (used e.g. for `appConfig` cache, `userplan` cache)

Key methods:
- `Gem::preload()` — session start → walk `appConfig('boot')` callables (currently `[Setup::class, 'check']`). If any returns `false` it exits (installer flow).
- `Gem::Bootstrap()` — connects DB, loads settings, sets locale, configures cache + logger, runs `Plugin::preload()`, builds FastRoute dispatcher, calls `Dispatch()`.
- `Gem::Dispatch()` — strips base prefix from URI, runs FastRoute, runs middleware pipeline, resolves controller, ReflectionMethod-injects method args, invokes handler.
- `Gem::route|get|post|put|delete($methods, $path, $handler)` — registers route. **POST routes always have `"CSRF"` middleware auto-appended.**
- `Gem::group($prefix, $fn)` — opens a route group.
- `Gem::setMiddleware($middleware)` — set group middleware (applies to routes registered *after* this call).
- `Gem::resources($name, $handler, $middleware)` — 6-route REST shortcut.
- `Gem::href($name, $param, $lang)` — reverse router (the `route()` global helper goes through this).
- `Gem::currentRoute()` / `currentRouteURL()` — what's running now.

Controller string resolution: `"Foo@bar"` → `new Foo()` → `Foo->bar(...)`. The `autoloadController` registration in `core/functions/core.php` maps the class name to `CONTROLLER/<NS>/<Name>Controller.php` — i.e. **the file always ends in `Controller.php`, but the class name does not** (so the file is `LinkController.php` but the class is `Link`).

---

## DB (`DB.class.php`)

Connection bootstrap + schema-builder + paginator. **Extends `Core\Support\ORM` (Idiorm)**, so all Idiorm methods are available transparently.

- `DB::Connect()` — `parent::configure('mysql:host=…;port=…;dbname=…', $user, $pass, …, ['MYSQL_ATTR_INIT_COMMAND' => 'SET NAMES utf8mb4'])`. Reads `DBhost`, `DBname`, `DBuser`, `DBpassword`, `DBport` constants. Enables ORM query logging when `DEBUG` is truthy.
- Magic `DB::users()` → `new self(DBprefix.'users')`. Standard way to start a raw query.
- `DB::schema($table, Closure)` / `alter($table, Closure)` — build `CREATE TABLE`/`ALTER TABLE` via a fluent DSL (`engine`, `charset`, `increment`, `int`, `string`, `text`, `enum`, `json`, `timestamp`, `primary`, `unique`, `index`, `fulltext`, `multiindex`). This is how `SetupController`/`UpdateController` create and migrate tables.
- `DB::destroy($table)`, `truncate($table)`, `optimize($table)`.
- `DB::columnExists($table, $column)`, `hasIndex($table, $column)`.
- Instance: `first($id)`, `find()`, `map(Closure)`, `paginate($count, $simple)`.

Default charset everywhere: **utf8mb4**.

---

## Auth (`Auth.class.php`)

Cookie/session-based user auth on top of `Models\User`.

- `Auth::COOKIE = "logged_in"` — cookie/session name.
- `Auth::session()` — decrypts the cookie (or session fallback) and returns `[user_id, authkey]`.
- `Auth::check()` — validates session against `User::where(['id' => …, User::AUTHKEY => …])`. Caches user.
- `Auth::user()` — current user, or `null`.
- `Auth::id()` — current user id.
- `Auth::login($username, $password, $rememberme)` — looks up by email, bcrypt-verify via `Helper::validatePass`, rotates `authkey`, sets cookie (14-day if remembered) or session.
- `Auth::loginId($id)` — passwordless login by user id. ⚠️ Bug: it sets `$user->auth_key` instead of `$user->authkey`. Same-spelled property; works because of magic `__get/__set` on the ORM row, but `Models\User::AUTHKEY` constant is `'auth_key'` (note the underscore) — so this is **probably correct** despite looking inconsistent with other call sites. Verify before changing.
- `Auth::ApiUser($key)` — auth via API key (3 forms: OAuth token regex, `user.api` column, `apikeys.apikey`).
- `Auth::validate($username, $password)` — like `login` but no persistence.
- `Auth::logged($role)` — `check()` + optional role check (the final `return true` short-circuits the role branch — likely bug).
- `Auth::logout()` — clears session + cookie.

---

## Model (`Model.class.php`) — Paris (Active Record)

Two classes: `ORMWrapper` and `Model`. Both extend `Core\Support\ORM`.

**`Model`** is what user-land models subclass:

- `public static $_table` — table name (set by subclass).
- `static factory($class_name, $connection_name = null)` — returns ORMWrapper bound to the resolved table.
- `__callStatic($method, $params)` forwards to `factory()` — that's how `User::where('email', $email)->first()` works.
- Relationship helpers (protected): `has_one`, `has_many`, `has_many_through`, `belongs_to`. (None of the 5 concrete models use these — all relationships are hand-rolled with `\Core\DB`.)
- Magic `__get`/`__set`/`__isset`/`__unset` proxy to the row.
- `save()`, `delete()`, `id()`, `hydrate($data)`, `is_dirty($prop)`, `is_new()`, `as_array(...)`, `map(Closure)`.
- `__call($name, $args)` converts camelCase → snake_case (so `findOne()` calls `find_one()`).

**`ORMWrapper`** is what's returned by `Model::factory()` — adapts Idiorm to hydrate typed model instances on `find_one()`/`find_many()`/`first()`/`find()`. Also exposes `paginate($count, $simple)`.

---

## View (`View.class.php`)

Hand-rolled template engine + meta-tag/asset helpers. Extends `Gem` for access to `$Config` and `currentRouteURL()`.

Constants: `ASSET = "static"`, `UPLOADS = "content"`.

- `View::set($key, $value)` — set one of `title`, `description`, `keywords`, `url`, `image`, `bodyClass`, `type`, `path`.
- `View::meta()` — emits `<title>` + Open Graph + Twitter + canonical + favicon.
- `View::assets($file)` — returns `CDNASSETS . '/' . $file` if `CDNASSETS` defined, else `<url>/static/<file>`.
- `View::uploads($file, $storage)` — same idea for user uploads (`CDNCUSTOMURL` first, else `appConfig('app.storage')[$storage]`, else `<url>/content/<file>`).
- `View::template($name, $data)` — the resolver. Dots → slashes. Looks in `STORAGE/themes/<active_theme>/<name>.php`; falls back to `appConfig('app.default_theme')`; supports a `child` directive in theme `config.json` for theme inheritance.
- `View::render($name, $data)` — `header($data)` + `template($name, $data)` + `footer($data)`.
- `View::dryRender($name, $data)` — template only (the `view()` global helper).
- `View::error($code, $title, $description)` — renders themed `errors/<code>.php` or fallback.
- `View::compile(array $sources, $destination, $linkonly)` — concatenates CSS/JS files with cache-busting `?mtime`. Used to build `bundle.pack.js`/`style.min.css`.
- `View::push($content, $type)->toHeader()|toFooter()|toBlock($name)` — inject `<link>`/`<script>` elements.
- `View::with($tpl, $params)->extend($layout)` + `View::content()` — minimal layout inheritance.

---

## Helper (`Helper.class.php`)

Grab-bag of static utilities. Most-used:

| Concern | Methods |
|---|---|
| Redirects + flash | `redirect($route)->with($type, $msg)->to($url)->back()->exit()`, `back()`, `message($style)`, `setMessage($type, $msg)` |
| Sanitization | `clean($string, $level=1)` (level 3 strips `on*=` + htmlspecialchars), `purify($content)`, `SanitizePost`, `RequestClean` |
| CSRF | `CSRF($form=true)` (HTML or token), `CSRFNAME = "__CRSF"` (typo, but consistent) |
| Auth | `Encode($string)` (bcrypt with `AuthToken` suffix), `validatePass($string, $hash)` |
| Time | `timeago($time)`, `dtime($time, $format)` |
| Pagination | `paginate(total, perpage, page, format)`, `pagination($class, $li, $a)`, `simplePagination(...)` |
| Cache | `cacheGet`, `cacheSet`, `cacheUpdate`, `cacheExpiry`, `cacheDelete`, `cacheClear`, `cacheConfig($path)` — all return early if `CACHE` constant is false |
| Crypto | `encrypt($data)` / `decrypt($data)` via Defuse, keyed off `EncryptionToken` |
| Strings | `slug($text)`, `rand($length, $prefix, $format)`, `truncate`, `readmore`, `Email($email)`, `isURL($url)`, `username($s)` |
| Geo | `Country($code, $asForm, $reverse)` (hardcoded ISO-2 → name map), `devices($code)` |
| Misc | `nonce($action, $duration)`, `validateNonce($nonce, $action)`, `parseUrl($url, $selector)`, `extension($input)` |

The static `set('config', …)` is how `Gem::$Config` becomes available to `config()` globally.

---

## Request (`Request.class.php`) — `#[AllowDynamicProperties]`

Captures the HTTP request, file uploads, session, cookies, geo-IP.

Construction: reads `$_GET`/`$_POST` (cleans GET via `Helper::clean(..., 3, true)`), wraps into `_HTTPrequest` stdClass, processes `$_FILES` via private `catchFile()` (normalizes single + array forms into `$_HTTPfiles->{key}` stdClass with `allowed/name/ext/type/location/size/sizekb/sizemb/mimematch/isvalid`).

Notable methods:
- `__get($var)` / `get($var)` / `all($asArray)` — request params (typed `__HTTPrequest`).
- `getBody()` (raw `php://input`), `getJSON()`.
- `typeof()` (lowercase HTTP method), `isPost()`, `isAjax()`, `isSecure()` (HTTPS, port 443, X-Forwarded-Proto, **CF-Visitor** — Cloudflare-aware).
- `host()`, `uri($withquery)`, `referer()`, `path($withquery)`, `query($q)`, `segment(int)`.
- `ip()` — multi-header fallback (CF-Connecting-IP → X-Real-IP → … → REMOTE_ADDR).
- `userAgent()`, `device()` (regex OS detect), `browser()` (regex browser detect).
- `country($ip)` — via `appConfig('app.geodriver')`: `api` (cURL), `maxmind` (`MaxMind\Db\Reader` + bundled `GeoLite2-City.mmdb`), or `custom` callable.
- File uploads: `file($input)`, `move($request, $directory, $name)` — uses `Request::setCDN(Helpers\CDN::class)` to push to S3 instead of `PUB/content/...`.
- `cookie($name, $value, $time)`, `cookieClear($name)`.
- `session($name, $value)`, `unset($name)`.
- `validate($input, $rule)` — built-in rules: `int`, `email`, `url`, `username`, numeric length.

---

## Response (`Response.class.php`)

Tiny response builder.

- `Response::factory($body, $code=200, $headers=[])` — chained constructor.
- `setHeader([name, value])`, `setStatusCode($code)`, `setBody($response)`.
- `send()` — `print_r` for arrays, `json_encode` for objects, raw for strings.
- `exit()` — send + exit.
- `json()` — sets `Content-Type: application/json`, json-encodes, prints. API controllers almost universally end with `Response::factory($data)->json()`.

---

## Middleware (`Middleware.class.php`)

The base class is a **stub**. The actual middleware pipeline lives in `Gem::Dispatch()` — for each middleware string `"Class[@method]"` it `require`s `MIDDLEWARE/<class>.php`, news up `\Middleware\<Class>`, calls `->{method|handle}($request)`, and aborts if the result is `false` (most implementations `exit;` directly).

Provides:
- `protected $_exempt = []`
- `check(Request $request)` — returns `false` if any exempt pattern (with `*` stripped) substring-matches `$request->path()`. Subclasses use this to opt out.
- `add($exempts)` — append to exempt list.

Registration model: middleware names are attached to routes via `Gem::post()/get()/.../setMiddleware()/->middleware()`. `Gem::post()` always force-appends `"CSRF"`.

---

## Plugin (`Plugin.class.php`)

The only event/hook system in the framework.

- `Plugin::preload()` — runs in `Gem::Bootstrap()`. Reads `View::config('include')` (the active theme's `config.json:include`) and `include_once`s each file (falls back to default theme or a `child` theme). Reads `config('plugins')` (DB setting — JSON map of active plugins) and `include`s `PLUGIN/<name>/plugin.php` for each.
- `Plugin::register($area, $fn, $param)` — register a handler. Accepts callable, `[class, method]`, or function name string.
- `Plugin::dispatch($area, $param)` — invoke all handlers under `$area`. Returns array of return values.
- `Plugin::staticPlug($area, $param)` — return handlers without invoking.
- `Plugin::extend($name, $param)` — shorthand for `dispatch($name.'.extend', $param)`.

Built-in events the framework fires:
- `error.exception` (from `GemError::exception`)
- `error.fatal` (from `GemError::fatal`)
- `error.trigger` (from `GemError::trigger`)

Application-level events you'll find:
- `link.preredirect`, `link.redirect`, `link.override` — `LinkController@redirect`
- `payment.success` — `WebhookController@index` (affiliate credit)
- `payment.extend` — `Traits\Payments::processor()` (third-party gateways)
- `middleware.csrf.exempt` — `Middleware\CSRF::handle` (extend exempt list)

---

## Email (`Email.class.php`)

PHPMailer wrapper + transport switchboard.

- `Email::factory($transport, $config)` switches between:
  - `smtp` → PHPMailer SMTP
  - `sendmail` → PHPMailer sendmail
  - `null` → PHP `mail()`
  - any key in `appConfig('app.maildrivers')` (`mailgun`, `sendgrid`, `mailchimp`, `postmark`) → returns `new $driver_class($config)` instead. The driver classes live in `core/support/` and implement the same fluent shape (`to`, `from`, `replyto`, `template`, `send`, `fallback`).
- Instance: `via($mode)`, `to`, `from`, `replyto`, `attach`, `template($name)`, `send($data)`.
- Static `Email::parse($template, $data)` does `{{ key }}` interpolation.

---

## Http (`Http.class.php`)

Fluent cURL client.

- `Http::url($url)` factory.
- Headers: `with($name, $content)`, `withHeaders([])`, `auth($user, $pass)`, `body($content)` (auto-JSON-encodes if Content-Type is JSON).
- Verbs: `get($options)`, `post($options)`, `put`, `patch`, `delete`. Honors `appConfig('app.proxy')`. Disables SSL verify when `DEBUG` is on. Logs errors via `GemError::log`.
- Response: `getBody()`, `bodyObject()` / `json()`, `httpCode()`, `ok()` (200-299).
- `__toString()` returns `getBody()`.

---

## File (`File.class.php`)

Wraps `SplFileObject` plus storage/download helpers.

- `File::factory($input, $engine='public')` — engine maps to `appConfig('app.storage')[engine]`.
- `File::contentDownload($name, Closure $fn)` — streams `$fn()` output as an HTTP attachment. Used by QR download endpoints.
- `File::resize($source, $output, $width, $height='auto', $quality=0.8)` — GD-based resize, format-aware (jpeg/png/gif).
- Instance: `download($newname)`, `source()`, `storage($engine)`, `link()` (URL), `path()` (FS), `move($dir)`, `copy($filename)`.

---

## GemError (`GemError.class.php`)

Monolog wrapper + error-page renderer + global PHP error handlers.

- `GemError::logger($path)` — initializes the `system` channel (`Log-<m-d-Y>.log`, `LineFormatter`).
- `GemError::log($error, $data, $channel, $type)` — main logging entry.
- `GemError::exception($code, $msg, $file, $line)` — bound to `set_error_handler` (in `core/functions/core.php`). Dispatches `error.exception` plugin event.
- `GemError::fatal($msg)` — `register_shutdown_function`'d. Dispatches `error.fatal`.
- `GemError::trigger($code, $error, $uri)` — set HTTP status, log (skip 404), dispatch `error.trigger`, render themed `errors/<code>.php` if present else built-in template.
- `GemError::channel($name, $path)` — additional Monolog channel (`Logger::DEBUG`). Reserved name: `system`. Used by `CronController` for per-cron channels.

---

## Localization (`Localization.class.php`)

Simple file-based i18n.

- Language files: `storage/languages/<lang>/<file>.php` returning `['name','code','region','author','rtl','data']`. Default file: `'app'`.
- `Localization::setLocale($code)`, `setFile($name)`, `locale()`, `bootstrap()`, `update()`.
- `Localization::translate($string, $count=null, $variables=[])` — supports `singular|plural` strings (split on `|`), substitutes `{key}` placeholders. Called by the `e()`, `_e()`, `ee()`, `_ee()` global helpers.
- `Localization::listArray($limit)` — array of installed locale codes. Used in `routes.php:23` to detect the locale URL prefix.
- `Localization::localUrl($lang)` — switch the current URL to a different locale.

---

## Collection (`Collection.class.php`)

Lightweight `ArrayObject` wrapper for arbitrary lists.

- `Collection::with($items)` factory.
- `all()`, `limit(n)`, `range(a, b)`, `set/get/remove(k)`, `map(Closure)`, `flatten()`, `toJson()`, `chunk(n)` ⚠️ (references undefined `$items` — buggy), `collapse()`.

Rarely used directly; `ORMWrapper`/`IdiormResultSet` are usually what you actually iterate.

---

## ORM (`support/ORM.class.php`)

The full **Idiorm** source, namespaced as `Core\Support\ORM` (~2620 lines). This is **the** query builder underneath both `Core\DB` and `Core\Model`. Everything in the codebase that uses `where(...)`, `find_one(...)`, `find_many(...)`, `order_by_*`, `limit`, `offset`, `count`, `save`, `delete` is going through this class.

Companion classes (same file):
- `IdiormResultSet` — `Countable + IteratorAggregate + ArrayAccess + Serializable` result wrapper. Forwards `__call` to every element.
- `IdiormString` — internal helper.
- `IdiormStringException`, `IdiormMethodMissingException` — exception types.

The 60-line PHPDoc block at the top of the file enumerates every camelCase magic method (`findOne`, `findMany`, `whereEqual`, `forceAllDirty`, etc.) — that's your method reference.

---

## Global helpers (loaded from `core/functions/helpers.php`)

All wrapped in `if (!function_exists(...))` so plugins/apps can override.

| Helper | What it does |
|---|---|
| `url($path)` | `Gem::$Config->url . '/' . $path` |
| `route($name, $param, $lang)` | `Gem::href(...)` — reverse router |
| `csrf()` / `csrf_token()` | Hidden `<input>` / raw token |
| `meta()` | `View::meta()` |
| `render($name, $data)` | Full render (header + template + footer) |
| `view($name, $data)` | Template only |
| `section()` | `View::content()` — for layout-inheritance |
| `block($type)` | Echo `View::block` (header/footer/custom) |
| `push($content, $type)` | Inject `<link>`/`<script>` |
| `assets($name)` | Static asset URL |
| `uploads($name, $storage)` | User-upload URL |
| `e($string, $count, $vars)` | Translate |
| `_e($string, $vars)` | Translate w/o count |
| `ee($string, ...)`, `_ee(...)` | Echo translate |
| `request()` | `new Core\Request` |
| `old($name)` | Recall previous form input from session |
| `back()` | `Helper::redirect()->back()` |
| `plug($name, $param)` | `Plugin::dispatch($name, $param)` |
| `stop($code, $text)` | `GemError::trigger` + exit |
| `middleware($mw)` | Run an extra middleware ad-hoc |
| `message()` | Echo flash |
| `timeago($t)` | Relative time |
| `pagination(...)` / `simplePagination(...)` | Render pager |
| `config($key)` | `Gem::$Config->$key` (DB-backed) |
| `appConfig($file.key)` | Load `app/config/<file>.php`, cached |
| `user()` | `Auth::user()` |
| `auth($fn, $param)` | `Auth::$fn($param)` |
| `clean($string)` | Level-3 sanitize |
| `parseIfJSON($string)` | Try `json_decode`, else return string |
| `currentPage()` | `$_GET['page']` or `"1"` |
| `gvd(...)` | `var_dump` + exit |
| `adminmenu(array)` | Render an admin sidebar `<li>` |
| `appendquery(array)` | Replace keys in the current query string |
| `uppercountryname($name)` | `ucwords` with " and " lowering |

These are loaded by `core/functions/core.php` → `core/functions/helpers.php`. They're available everywhere because they live in the global namespace.
