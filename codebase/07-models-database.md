# 07 — Models & Database

## Critical context

**No SQL or migration files ship with this source tree.** Schema is built by `Core\DB::schema(...)` calls inside `SetupController` (first install) and `UpdateController` (version upgrades). To inspect the live schema, dump it from MySQL directly.

There are only **5 concrete model classes**. Almost every other table is accessed as raw rows via `\Core\DB::<tablename>()` query builders (Idiorm). The "ORM" is Paris (`core/Model.class.php`), but **none of the 5 models declare relationships using Paris's `has_one`/`has_many`/`belongs_to` helpers** — every relation is hand-rolled with `\Core\DB`.

## The 5 models

### `Models\User` — table `<prefix>user`

The big one. Extends `Core\Model`.

```php
class User extends Core\Model {
    public static $_table = DBprefix.'user';
    const AUTHKEY = 'auth_key';
}
```

Instance methods (all hand-rolled — no Paris relationships):

| Method | Purpose |
|---|---|
| `rID()` | Returns `$this->id` or the active team's owner id if a team session exists |
| `avatar()` | Resolves avatar URL (uploaded → Facebook Graph → Gravatar → default) |
| `refresh()` | Clears `Gem::$App['userplan']` cache |
| `plan($limit = null)` | Caches plan object in `Gem::$App['userplan']`. Falls back to `Helpers\App::defaultPlan()` |
| `has($permission)` | Checks plan JSON `permission->{$perm}->enabled` |
| `hasLimit($permission)` | Reads `permission->{$perm}->count` |
| `hasRolePermission($p)` | Delegates to `Role::hasPermission()` |
| `getRole()` | `Models\Role::first($this->admin)` |
| `assignRole($id)` / `removeRole()` | Sets `$this->admin` and saves |
| `team()` | Reads active team via decrypted `team_<id>` session, joins `members` |
| `teams()` | Lists active team memberships |
| `teamPermission(string $p)` | Read team-member JSON permission array |
| `pixels()` | Loads user's pixels grouped by `\Helpers\App::pixelName()` |
| `pro()` | Pro flag (own, team-inherited, or admin) |
| `hasPortal()` | True if user has an Active Stripe or Paddle subscription |
| `starredChannels()`, `channels()` | Owned channels, optionally limited by plan |
| `notifications()` | Reads `appevents` (latest 10) filtered by `userid`/`planid`/`expires_at` |
| `keyCan($permission)` | When request is on a custom API key, checks `apikeys.permissions` JSON |

### `Models\Url` — table `<prefix>url`

Static "scopes" (called via `Url::recent()`, `Url::archived()`, `Url::expired()`):

| Scope | Predicate |
|---|---|
| `recent()` | `(alias != '' OR alias IS NOT NULL OR custom != '' OR custom IS NOT NULL) AND archived=0 AND qrid IS NULL AND profileid IS NULL AND (expiry IS NULL OR expiry > CURDATE())` |
| `archived()` | same but `archived=1` |
| `expired()` | same but `expiry < CURDATE()` |

Instance method: `channels()` — many-to-many fetch via `tochannels` join (`type = 'links'`).

The `qrid`/`profileid` columns mean: this table is polymorphic. A row with `qrid` set is a QR code; with `profileid` set, a bio profile entry; otherwise a real short link. This is why every "scope" filters them out.

### `Models\Plans` — table `<prefix>plans`

Two utility methods (no instance behavior beyond Active Record):

- `static notAllowed()` — redirects unauthorized callers to dashboard or pricing.
- `static checkLimit($count, $total)` — gates feature use against a numeric plan cap.

The actual plan row has a giant `permission` JSON column shaped like `{ "<perm>": { "enabled": bool, "count": int } }`. The keys observed across the code: `custom`, `team`, `splash`, `overlay`, `pixels`, `domain`, `multiple`, `alias`, `device`, `geo`, `bundle`, `parameters`, `export`, `api`, `links`, `clicks`, `retention`, `channels`, `qrlogo`, `qrframes`, `apirate`.

Default plan (when none assigned) comes from `Helpers\App::defaultPlan()`.

### `Models\Role` — table `<prefix>roles`

```php
hasPermission($permission)  // id==1 → superadmin shortcut; else in_array(...)
getUsers()                  // DB::user()->where('admin', $this->id)->findMany()
getUserCount()              // same, ->count()
static getAvailablePermissions()  // returns the master permission catalog
```

`getAvailablePermissions()` enumerates the full permission tree. Groups: `users`, `links`, `qr`, `bio`, `plans`, `settings`, `statistics`, `content`, `subscriptions`, `payments`, `tools`, `affiliates`. Sample permissions: `users.{view,create,edit,delete,ban,verify}`, `links.{view,create,edit,delete,approve,disable}`, etc.

User → role link: `user.admin` column holds the role id. `admin=0` → no role, `admin=1` → super-admin (the `id==1` short-circuit).

### `Models\Settings` — table `<prefix>settings`

Key-value store with overridden ID column:

```php
public static $_table = DBprefix.'settings';
// ID column is 'config' (overridden via ORM::configure)
```

Each row: `{ config: <key>, var: <json-or-scalar> }`.

`static getSettings()` reads every row and hydrates an `stdClass` where each `config` becomes a property, each `var` becomes its value (JSON-parsed via `parseIfJSON`). This single call returns the entire app configuration — accessible as `Gem::$Config->whatever` or `config('whatever')`.

Side effects of `getSettings()`:
- Sets PHP timezone from `timezone` setting.
- Picks active language from `default_lang`, `HTTP_ACCEPT_LANGUAGE`, `language` cookie, `?lang=` query, or URL segment 1.
- Bootstraps `Core\Localization`.
- Wires `Helpers\CDN` via `Request::setCDN()` if `cdn.enabled`.
- Defines the `CDNCUSTOMURL` constant.

`static setSetting($name, $value)` — update a single config row.
`static updateSettings()` — re-load and re-broadcast via `Helper::set('config', ...)`.

---

## Inferred database schema

> **Caveat:** This is the union of every column read or written in code, not the literal `CREATE TABLE` statement. Indexes, defaults, and nullable flags are educated guesses. Confirm against the live DB before any schema work.

### `user`
`id` PK · `email` · `username` · `password` (bcrypt with `AuthToken` salt) · `auth_key` (rotated on login; the `Auth::COOKIE` payload references this) · `auth` (social provider name, e.g. `facebook`) · `auth_id` (provider's user id) · `admin` → `roles.id` · `planid` → `plans.id` · `pro` (bool) · `trial` (bool) · `expiration` (date) · `avatar` · `secret2fa` · `api` (API key) · `slackid` · `slackteamid` · `zapview` (per-click webhook URL) · `customkey` (per-key permission scope marker) · `created_at` etc.

### `url`
`id` PK · `userid` → `user.id` · `alias` · `custom` (custom alias on a custom domain) · `domain` · `url` (destination) · `type` (`direct`, `splash`, `frame`, `overlay`, `password`, `media`, `bundle`, `profile`, `embed`) · `password` · `expiry` (date or null) · `archived` (0/1) · `clicks` · `qrid` (FK marker — row is a QR) · `profileid` (FK marker — row is a bio entry) · `defaultbio` (1 for the user's default bio) · `options` (JSON: rotators, deeplink, etc.) · `location` (JSON: country/state geo targeting) · `devices` (JSON: device targeting) · `language` (JSON: Accept-Language targeting) · `meta`, `title`, `description`, `image` · `disabled` · `bad`, `pending`, `report` · `created_at` etc.

### `plans`
`id` PK · `name` · `free` (0/1) · `monthly`, `yearly`, `lifetime` (prices) · `discount` · `currency` · `status`, `hidden` · `permission` (JSON feature matrix described above) · plus provider-specific plan IDs (Stripe plan id, Paddle plan id, etc.) · `created_at`.

### `roles`
`id` PK (special: `1` = superadmin) · `name` · `permissions` (JSON array of strings — see `Role::getAvailablePermissions`) · `created_at`.

### `settings`
`config` PK · `var`. Logical keys: `title`, `description`, `url`, `language`, `default_lang`, `timezone`, `pro`, `private`, `maintenance`, `home_redir`, `user`, `userlogging`, `verifylink`, `publicqr`, `qrlogo`, `qrframes`, `api`, `stripe`, `paddle`, `paypal`, `mollie`, `paystack`, `cdn`, `domain_names`, `plugins`, `saleszapier`, `slacksigningsecret`, etc.

### `members` (no model file)
`teamid` → `user.id` (owner) · `userid` → `user.id` (member) · `status` (1=active) · `permission` (JSON array of strings).

### `channels` (no model file)
`id` PK · `userid` → `user.id` · `name` · `starred` (0/1) · `parentid` (folders can nest) · `created_at`.

### `tochannels` (link table)
`channelid` → `channels.id` · `itemid` (polymorphic) · `type` (`links` confirmed; likely also `qr`, `bio`).

### `subscription` (no model file)
`userid` → `user.id` · `status` (`Active`, `Pending`, `Cancelled`, ...) · `processor` · `data` (JSON: `paymentmethod` ∈ {`Stripe`, `Paddle`, ...}, gateway ids, period info) · `expiration` · `planid` · `coupon` · `created_at`.

### `payment` (no model file — referenced by `Webhook::index` and `Admin\Membership`)
`userid` · `planid` · `amount` · `currency` · `type` · `processor` · `transaction_id` · `coupon` · `tax` · `status` · `data` (JSON) · `created_at`.

### `appevents` (no model file)
`id` · `type` (`notification`, `login`, etc.) · `userid` (nullable; null = broadcast) · `planid` (nullable; targeted plan) · `expires_at` · `data` (JSON, includes `content`) · `created_at`.

### `apikeys` (no model file)
`id` · `userid` → `user.id` · `apikey` · `name` · `permissions` (JSON array of scopes; `all` is sentinel) · `created_at`.

### `oauth_access_tokens` (no model file)
Used by `Auth::ApiUser` — token format regex `^[a-z0-9]+-\d{5}-[a-z]+-\d{5}$`. Columns: `token`, `userid`, `clientid`, `scopes`, `expires_at`.

### `oauth_clients` (no model file — admin OAuth app management)
`id` · `userid` · `name` · `client_id` · `client_secret` · `redirect_uri` · `created_at`.

### `pixels` (no model file)
`userid` → `user.id` · `type` (FB, Google Ads, GA, GTM, LinkedIn, etc.) · `tag` · `name`.

### `domains` (no model file)
`id` · `userid` · `domain` · `bioid` (when domain points to a bio) · `redirect` (when domain just redirects) · `status` (pending/active/disabled) · `verifycode`.

### `profiles` (no model file)
Bio pages. `id` · `userid` · `username` · `name` · `title` · `image` · `banner` · `theme` · `urlid` → `url.id` · `blocks` (JSON array of bio widgets) · `settings` (JSON) · `created_at`.

### `qrs` (no model file)
`id` · `userid` · `urlid` → `url.id` (optional — dynamic QR) · `type` (text/link/email/sms/phone/wifi/staticvcard/event/geo/crypto/file/application) · `data` (JSON payload + styling) · `file` (cached SVG filename) · `created_at`.

### `imports` (no model file — used by `Cron::imports` and `User\Import`)
`id` · `userid` · `file` (CSV filename in `STORAGE/app/imports/`) · `processed` · `total` · `status`.

### `stats` (no model file — primary click log)
`urlid` · `clicked_at` · `country` · `city` · `os` · `browser` · `language` · `referer` · `ip` (optionally hashed if `haship`) · `device`.

### `affiliates` (no model file)
`userid` · `referrerid` → `user.id` · `paymentid` → `payment.id` · `amount` · `type` (fixed/percentage) · `recurring` (0/1) · `status` · `created_at`.

### `coupons` / `vouchers` / `taxrates` / `pages` / `posts` / `categories` / `faqs` / `ads` / `notifications` / `helps`
All admin-managed CRUD — see the corresponding admin controllers.

### `loginlog` (referenced by `Admin\Users::logins`)
`userid` · `ip` · `useragent` · `country` · `created_at`.

### `verifications` (KYC)
`userid` · `document` · `status` · `notes` · `created_at`.

---

## Common ORM patterns to recognise

```php
// Raw row (most common — bypasses Models entirely)
DB::url()->where('alias', $alias)->where('archived', 0)->findOne();
DB::url()->where('userid', $user->id)->orderByDesc('created_at')->paginate(20);

// Typed model
User::where('email', $email)->first();
Url::recent()->where('userid', $user->id)->findMany();

// Insert
$row = DB::url()->create();   // or Url::factory()->create()
$row->alias = 'abc';
$row->url   = 'https://…';
$row->save();

// Update
$row = DB::url()->where('id', $id)->findOne();
$row->set('archived', 1);
$row->save();

// Delete
$row->delete();
```

JSON columns are typically `json_encode`'d on write and `json_decode($row->col, true)` (or `parseIfJSON`) on read. There is no automatic cast.
