# 19 — Coding Conventions

## Namespaces & file naming

| Tier | Namespace | File pattern | Class name |
|---|---|---|---|
| Framework | `Core\` | `core/<Name>.class.php` | `Name` |
| Framework support | `Core\Support\` | `core/support/<Name>.class.php` | `Name` |
| Controllers (root) | (global) | `app/controllers/<Name>Controller.php` | `Name` |
| Controllers (sub) | `Admin\`, `API\`, `User\` | `app/controllers/<ns>/<Name>Controller.php` | `Name` |
| Models | `Models\` | `app/models/<Name>.php` | `Name` |
| Middleware | `Middleware\` | `app/middleware/<Name>.php` | `Name` |
| Helpers | `Helpers\`, `Helpers\Payments\`, `Helpers\Qr\` | `app/helpers/<Name>.php` (or sub) | `Name` |
| Traits | `Traits\` | `app/traits/<Name>.php` | `Name` |
| Plugins | `Plugins\` | `storage/plugins/<name>/<Name>.php` | `Name` |
| Themes | `Themes\` | `storage/themes/<name>/<Name>.php` | `Name` |

**Key oddity:** controller class names do **not** end in `Controller` — the *file* does. So `app/controllers/admin/UsersController.php` defines class `Admin\Users`, not `Admin\UsersController`. The autoloader `autoloadController` knows to append `Controller.php` when resolving.

## Autoloaders

Registered in `core/functions/core.php`:

```php
// 1. Generic autoloader
spl_autoload_register('autoloadCore');
//    core      → ROOT/core/...        + .class.php
//    plugins   → ROOT/storage/plugins/...
//    themes    → ROOT/storage/themes/...
//    everything else (Models, Middleware, Helpers, Traits)
//              → APP/<ns>/<path>.php

// 2. Controller autoloader (separate — handles the "Controller.php" suffix)
spl_autoload_register('autoloadController');
//    Foo         → CONTROLLER/FooController.php
//    Admin\Foo   → CONTROLLER/admin/FooController.php
```

## Route → controller resolution

```
"Name@method"
  ↓
explode('@', $handler) → [$class, $method]
  ↓
new $class()             ← autoloadController loads file
  ↓
ReflectionMethod parameter inspection:
  - typed Core\Request param → reuse captured request
  - typed non-builtin param  → new $type() (no constructor args)
  - untyped params           → route variables, positional
  ↓
call_user_func_array([$controller, $method], $args)
```

**Implication:** controllers should have parameterless constructors (or constructors that work with no args). Use the constructor for cross-cutting checks (e.g. `Auth::ApiUser()` in every `API\*` controller).

## Database access

Two equivalent styles. Pick the one closest to the surrounding code:

```php
// Raw table — bypasses Models entirely. Default in most places.
DB::url()->where('userid', $user->id)->findMany();
DB::settings()->where('config', 'stripe')->first();

// Typed Model — only used for the 5 declared models.
User::where('email', $email)->first();
Url::recent()->where('userid', $user->id)->findOne();
```

Both return Idiorm/Paris row objects with magic `__get`/`__set`. Properties are columns. Save via `->save()`. Delete via `->delete()`.

JSON columns: encode on write, `json_decode` (or `parseIfJSON`) on read. No automatic casting.

## Response idioms

```php
// HTML response — view rendering
View::render('admin.users.edit', ['user' => $user]);

// Or the global helper
render('admin.users.edit', ['user' => $user]);

// JSON (API)
Response::factory(['error' => 0, 'data' => $items])->json();
exit;          // ⚠️ explicit exit after JSON in many places

// Redirect with flash
Helper::redirect(route('home'))->with('success', e('Done'))->exit();

// Back with flash
back()->with('danger', e('Failed'))->exit();
// or
Helper::redirect()->back()->exit();

// Hard 404
GemError::trigger(404);
exit;
// or
stop(404);     // global helper, calls GemError::trigger + exit
```

## Translation

All user-facing strings go through `e()`:

```php
e('Welcome back')                          // → translated string
ee('Welcome back')                         // → echo translated
e('You have 1 link|You have :count links', $count, ['count' => $count])
                                            // singular|plural, with placeholder
_e('Login', ['name' => $user->name])       // no count, just placeholders
```

Backend uses `e()`, templates use `ee()` (echoed). Admin strings use `Localization::setFile('admin')` (auto-applied by the `Locale@admin` middleware).

## Sanitization

Three levels via `Helper::clean($s, $level)`:

- Level 1 — basic trim + filter.
- Level 2 — strip tags.
- Level 3 — strip `on*=` attributes, strip dangerous tags, htmlspecialchars.

Convenience:
- `clean($s)` global — level 3 with `chars=true`.
- `Helper::SanitizePost()` — apply to all of `$_POST`.

Route variables are auto-sanitized at level 3 by `Gem::Dispatch` before reaching the controller.

## Form / CSRF

Every POST automatically requires a CSRF token. Templates must include `csrf()`:

```php
<form method="POST" action="<?= route('foo') ?>">
    <?= csrf() ?>
    ...
</form>
```

Exempt paths: `shorten`, `user/qr/preview`, `api/*`, `admin/languages/translate`, `user/bio/` (plus plugin extensions).

## Plan & permission checks

```php
$user = Auth::user();
if (!$user->has('domain')) {
    return back()->with('danger', e('Upgrade required'));
}
if ($count >= $user->hasLimit('links')) {
    return back()->with('danger', e('Plan link limit reached'));
}
if (!Helpers\Permissions::can('users.edit')) {
    return GemError::trigger(404);
}
if (!$user->teamPermission('links.create')) {
    return back()->with('danger', e('Not allowed in this team'));
}
```

Use `$user->rID()` (not `$user->id`) when storing resource ownership — it accounts for team context.

## Date / time

```php
Helper::dtime('now', 'Y-m-d H:i:s')         // formatted current time in app timezone
Helper::timeago($timestamp)                  // "3 minutes ago"
timeago($timestamp)                          // same, via global helper
```

Don't use raw `date()` — bypasses the app timezone setting.

## Pagination

```php
$results = DB::url()->where('userid', $user->id)->paginate(20);
// → array slice for current page
// Inside the template:
echo pagination('pagination', 'page-item', 'page-link');
```

`paginate()` populates `Helper::$paginate` static state; `pagination()` reads it.

## Module / event registration

In plugins or theme `include`d files:

```php
Plugin::register('link.preredirect', function($url) { /* … */ });
```

Custom events:

```php
$results = Plugin::dispatch('myapp.something', [$payload]);
// returns array of return values from every registered handler
```

`plug('event.name', $payload)` is the global-helper shortcut for `Plugin::dispatch`.

## Tests

There are no tests in this codebase. **Verify changes manually**: hit the relevant routes, inspect logs (`storage/logs/`), check the DB.

## Style basics observed

- Indentation: 4 spaces (some files use 2 — inconsistent).
- Methods: `camelCase`. Database columns: `snake_case`. Variables: usually `camelCase` but `$db_thing` snake_case appears where it mirrors a column.
- Strings: `'single quotes'` mostly; `"double"` only when interpolating.
- Closing PHP tag: omitted from most files (Laravel convention).
- Type hints: optional. Mostly absent in older files, added in newer ones.
- Constants: `UPPER_SNAKE` for `define()`'d constants, `UpperCamel` for class consts.
- Docblocks: every file starts with the proprietary GemFramework licence header — leave it untouched.

## Where not to put code

| Place | Why not |
|---|---|
| `core/` | Proprietary framework |
| `vendor/` | Composer-managed |
| `public/` | Webroot — only the front-controller belongs |
| `app/config/api.php` | Documentation manifest, not a router |
| Inside route closures in `routes.php` | All real logic should be in controllers |
| Inside views | Logic goes in controllers/helpers; views display |
