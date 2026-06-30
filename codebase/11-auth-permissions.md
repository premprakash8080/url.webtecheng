# 11 — Authentication, Authorization, Permissions

Four layered concepts in this app, often conflated in code:

1. **Session auth** — who is logged in (cookie + session).
2. **API auth** — who is calling the REST API (Bearer token, OAuth2, or `user.api` key).
3. **Role-based permissions** — what an admin user can do (`Models\Role`).
4. **Plan-based feature gating** — what a paying user can use (`Models\Plans.permission` JSON).
5. **Team permissions** — what a team member can do on behalf of the team owner.

## 1. Session authentication

**Stored in:** an encrypted cookie named `logged_in` (constant `Auth::COOKIE`), with session fallback. Cookie/payload is Defuse-encrypted JSON: `{loggedin: 1, key: "<authkey><user_id>"}` where `authkey` is 60 chars + `user_id` appended.

```
Login flow                                       Read flow (every request that calls Auth::check)
─────────────                                    ──────────────────────────────────────
UsersController@loginAuth                        Auth::check():
  Helper::validatePass($pw, $user->password)       Auth::session() → decrypt cookie/session
  session_regenerate_id                             pull out [id, authkey]
  $user->auth_key = Helper::Encode(uniqueRand)     User::where(['id'=>$id, AUTHKEY=>$authkey])
  $user->save                                       cache in self::$user
  $token = ['loggedin'=>1,                          return bool
            'key'=>$user->auth_key.$user->id]
  cookie('logged_in', Helper::encrypt($token),
         14*24*60 if rememberme else session)
  Events::for('login')->user($id)->log(...)
```

Key facts:
- **Password hash:** `Helper::Encode($pw)` = `password_hash($pw . AuthToken, PASSWORD_BCRYPT)`. **Rotating `AuthToken` invalidates every existing password hash.**
- **Auth key:** new on every successful login. Stored on `user.auth_key`. Cookie carries this, so logging in again logs out other devices for the same account.
- **Cookie crypto:** Defuse\Crypto with `EncryptionToken` as the key. Tampering or token rotation invalidates all cookies.
- **Sessions:** PHP native, started in `Gem::preload()`.

### 2FA

- Field: `user.secret2fa` (base32 TOTP secret).
- Library: `Sonata\GoogleAuthenticator`.
- Flow: on `loginAuth` success, if `secret2fa` set, stash `$_SESSION['2FAKEY'] = Helper::encrypt(['id' => $user->id])` and redirect to `/user/login/2fa`.
- `Users@login2FAValidate` decrypts `2FAKEY`, calls `checkCode($secret, $request->code)`, then completes the login cookie/event flow.
- Recovery: `Users@login2FARecover` and `Users@reset2FA`.

### Social login

`Users@loginWithFacebook|Twitter|Google` — OAuth callbacks. Identity received from provider:
- Look up `user` by `email` or `auth='facebook'/'google'/'twitter'` + `auth_id`.
- If new → create user. If existing → link.
- Then same auth-cookie sequence as password login.

Wired helpers: `Helpers\FacebookAuth`, `Helpers\GoogleAuth`, plus `abraham/twitteroauth` for X/Twitter.

### Logout

`Users@logout` → `Auth::logout()`:
- `unset($_SESSION[Auth::COOKIE])`
- `unset($_SESSION['logged_as'])` (admin "login as" trace)
- Expire the cookie

---

## 2. API authentication

Three accepted credential forms, all resolved by `Auth::ApiUser($key)`:

```
Authorization: Bearer <key>
```

Where `<key>` is one of:

| Form | Regex / lookup | Source table |
|---|---|---|
| OAuth2 access token | `^[a-z0-9]+-\d{5}-[a-z]+-\d{5}$` | `oauth_access_tokens` (joined to client + user) |
| Personal API key | exact match against `user.api` | `user` |
| Named API key | exact match against `apikeys.apikey` | `apikeys` |

Once resolved:
- `$user = Auth::user()` works (cached internally).
- `$user->keyCan($scope)` checks per-key scopes (only meaningful if logged in via `apikeys`).
- `apikeys.permissions = ['all']` is a sentinel that grants everything.

### OAuth2 token endpoint

`POST /api/oauth/token` (public — declared before `setMiddleware` in the API group).

Inputs: `grant_type`, `client_id`, `client_secret`, plus either `code` (authorization_code flow) or `refresh_token`.

Verified against `oauth_clients` table. Token format from `Helper::rand`: `<a-z0-9>-<5d>-<a-z>-<5d>`. Stored in `oauth_access_tokens`.

### Authorization page

`GET /oauth/authorize` → `API\OAuth@authorize`. Renders a consent page. `POST /oauth/authorize/proceed` → `API\OAuth@proceed` issues the auth code.

---

## 3. Role-based permissions (admin / staff)

**Storage:** `roles.permissions` is a JSON array of permission strings.
**User → role:** `user.admin` column holds the role id. `admin = 0` → no role. `admin = 1` → super-admin shortcut (treated as having every permission without consulting the JSON).

### Check

```php
$user->hasRolePermission('users.edit')        // delegates to Models\Role::hasPermission
Helpers\Permissions::can('users.edit')        // same, more ergonomic
```

`Role::hasPermission($p)`:
```php
if ($this->id == 1) return true;            // super-admin
return in_array($p, json_decode($this->permissions, true));
```

### Catalog

`Role::getAvailablePermissions()` returns the master tree. Groups:

`users` · `links` · `qr` · `bio` · `plans` · `settings` · `statistics` · `content` · `subscriptions` · `payments` · `tools` · `affiliates`

Examples within each:
- `users.{view, create, edit, delete, ban, verify}`
- `links.{view, create, edit, delete, approve, disable}`
- `qr.{view, create, edit, delete}`
- `bio.{view, create, edit, delete}`
- `plans.{view, create, edit, delete}`
- `settings.{view, edit, system}`
- `stats.{view, export}`
- `pages.{view, create, edit, delete}` · `blog.{view, …}` · `faq.{view, …}`
- `subscriptions.view` · `payments.view`
- `tools.{view, backup, restore, update}`
- `affiliates.{view, pay}`

### Enforcement

- Middleware `Auth@admin` blocks non-admins from `/admin/*` (404).
- Middleware `RolePermission` (via `->middleware('RolePermission@<perm>')`) gates individual admin sub-routes.
- `Admin\Roles` controller manages roles + permission assignment.

### "Login as"

`Admin\Users@loginAs($id, $nonce)` allows admins to impersonate any user. Stores `$_SESSION['logged_as']` so the UI can show a "you are impersonating" banner and the original admin can return via `Users@return`.

---

## 4. Plan-based feature gating (paying users)

**Storage:** `plans.permission` is a JSON map: `{ "<perm>": { "enabled": bool, "count": int } }`. Each user has a `planid` → `plans.id`.

### Check

```php
$user->plan()              // load plan once per request, cached in Gem::$App['userplan']
$user->has('custom')       // bool: feature enabled?
$user->hasLimit('links')   // int: capped count (used by Plans::checkLimit)
```

### Common permission keys (observed)

| Key | Meaning |
|---|---|
| `links` | Total active links |
| `clicks` | Total clicks tracked |
| `retention` | Stats retention in days |
| `custom` | Custom aliases allowed |
| `alias` | Premium alias allowed |
| `multiple` | Bulk shorten |
| `team` | Teams feature |
| `splash` | Splash pages |
| `overlay` | Overlay CTAs |
| `pixels` | Tracking pixels |
| `domain` | Custom domains |
| `device` | Device targeting |
| `geo` | Geo targeting |
| `bundle` | Bio bundles |
| `parameters` | UTM forwarding |
| `export` | CSV/JSON export |
| `api` | API access (required for `Auth@api` to pass) |
| `apirate` | API requests/min override (0 = unlimited) |
| `channels` | Channel count |
| `qrlogo` / `qrframes` | Premium QR styling |
| `verifylink` | Public link verification feature |
| `publicqr` | Public QR generator |

### Default plan

When `$user->planid` is null OR `config('pro')` is off OR `$user->admin` is truthy, `User::plan()` falls back to `Helpers\App::defaultPlan()` — a hardcoded "everything enabled" plan.

### Limit enforcement

```php
$count = DB::url()->where('userid', $user->id)->count();
if (Plans::checkLimit($count, $user->hasLimit('links'))) {
    // over limit → 402/upgrade UI
}
```

---

## 5. Team permissions

Teams are owner-based: one user invites others as members of "their team". The invited member gets their own row in `members`.

**Table `members`:**
- `teamid` → owner's `user.id`
- `userid` → member's `user.id`
- `status` (1 = active)
- `permission` (JSON array of strings)

**Active team context:** stored in an encrypted session `team_<owner_id>`. Set via `User\Teams::switch($token)`, cleared on switch-back.

### Check

```php
$user->team()                       // returns active members row or null
$user->teamPermission('links.create')// bool: array_intersect with member's permission JSON
$user->rID()                        // returns team owner's id when in team context, else $user->id
```

**Why `rID()`:** when a team member creates a link, the link is stored against the owner (the paying account). `rID()` is used wherever the "effective owner" of a resource is needed.

### Catalog

`Traits\Teams::permissions()` returns the catalog the UI uses when assigning permissions to a team member.

### Switching

`/user/teams/switch/{token}` (route `teams.switch`) → `User\Teams::switch($token)`:
- Verifies the team-switch token
- Sets `$_SESSION['team_<owner_id>'] = Helper::encrypt({userid: $member->id, teamid: $owner->id, ...})`
- Redirects to dashboard — now `Auth::user()` is the owner, `rID()` returns the owner, but `Auth::user()` actions are attributed to the member via `Events`.

---

## How the layers compose for a typical request

`POST /api/url/add` (create a link via API) goes through:

```
1. Auth@api               ← session-style auth bypass; check Bearer token
   ↳ Auth::ApiUser → resolves user
   ↳ checks config('api'), banned, active, has('api')
2. Throttle               ← per-key rate limit (configured by user.apirate)
3. CheckDomain            ← custom domain handling (usually no-op for API)
4. API\Links::create
   ↳ Auth::ApiUser()->keyCan('links')   ← key scope check
   ↳ Traits\Links::createLink($request, $user)
     ↳ Permissions / plan / team checks before insert
```

---

## Cookie/session/token map

| Name | Type | Storage | Lifetime | Purpose |
|---|---|---|---|---|
| `logged_in` | Cookie (encrypted JSON) | Defuse | 14 days w/ rememberme, session otherwise | Primary auth |
| `__bl` | Cookie | plain | minutes | Failed-login backoff counter |
| `urid` | Cookie | plain | 90 days | Affiliate referrer id |
| `__CRSF` | Session | plain | session | CSRF token (typo'd "CRSF") |
| `__message` | Session | plain | one-flash | Flash messages |
| `__backtosource` | Session | plain | session | Redirect-back source |
| `2FAKEY` | Session (encrypted) | Defuse | login flow only | Pending 2FA user id |
| `team_<owner_id>` | Session (encrypted) | Defuse | session | Active team context |
| `redirect` | Session | plain | session | Post-login destination |
| `logged_as` | Session | plain | session | Admin impersonation banner |
| `password_<url_id>` | Session | plain | session | Successful password-gate state per link |
| `throttlekey` | Session | plain | session | Anonymous shorten throttle key |

---

## Critical secrets

| Constant | Effect of rotating |
|---|---|
| `AuthToken` | Invalidates all bcrypt password hashes — every user must reset. |
| `EncryptionToken` | Invalidates every Defuse-encrypted blob: cookies (logout-all), 2FA pending sessions, team sessions, settings JSON encrypted via `Helper::encrypt`. |
| `PublicToken` | Used in public-facing signatures; rotation breaks any outstanding signed URLs. |

All three live in `config.php` at the repo root.
