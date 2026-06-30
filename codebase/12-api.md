# 12 — Public REST API

Configurable prefix via `appConfig('app.apiroute')` (default `/api`). 42 routes total. One public (`POST /oauth/token`), 41 authenticated.

## Authentication

Every authed endpoint requires:

```
Authorization: Bearer <token>
```

Token can be:
- An OAuth2 access token (format `^[a-z0-9]+-\d{5}-[a-z]+-\d{5}$`).
- A personal API key (`user.api` column).
- A scoped API key (`apikeys.apikey`).

See [11-auth-permissions.md](./11-auth-permissions.md#2-api-authentication) for details.

Middleware applied to the group: `Auth@api`, `Throttle`, `CheckDomain`. `Auth@api` returns JSON 403 for any failure (missing key, banned user, inactive account, `config('api')` disabled, user lacks `api` plan feature).

Rate limiting: `Throttle` middleware. Default from `appConfig('app.throttle')` = `[30, 1]` (30 req/min). Per-user override via plan limit `apirate` (0 = unlimited). Headers emitted: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. 429 with `Retry-After` on exceed.

All responses go through `Core\Response::factory(...)->json()` → `Content-Type: application/json`.

## Endpoint groups

### OAuth2 (`API\OAuth`)

```
POST /api/oauth/token        api.oauth.token      ← PUBLIC — token issuance
GET  /oauth/authorize        oauth.authorize      ← user consent page (web flow)
POST /oauth/authorize/proceed oauth.proceed       ← consent submit
```

Token request body:
```
grant_type=authorization_code | refresh_token | client_credentials
client_id=...
client_secret=...
code=...                (if authorization_code)
refresh_token=...       (if refresh_token)
```

Response:
```json
{
  "access_token": "abc-12345-xyz-67890",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "..."
}
```

### Index (`API\Index`)

```
GET  /api/                                       ← API root; lists groups/version
```

### Account (`API\Account`)

```
GET  /api/account            api.account.get      ← get authenticated user profile
PUT  /api/account/update     api.account.update   ← update profile
```

### Links (`API\Links`)

```
GET    /api/urls                       api.urls.get        ← list user's links (paginated)
GET    /api/url/{id}                   api.url.single      ← get one
POST   /api/url/add                    api.url.create      ← create
PUT    /api/url/{id}/update            api.url.update      ← update
DELETE /api/url/{id}/delete            api.url.delete      ← delete
```

`GET /api/urls?short=<url>` looks up a link by its short URL (parses alias + custom domain).

`POST /api/url/add` accepts the same options the dashboard does:
- `url` (destination, required)
- `custom` (custom alias)
- `domain` (custom domain — must belong to user or be on `domain_names`)
- `password`
- `expiry` (date)
- `cap` (click cap)
- `location` (geo targeting JSON)
- `devices` (device targeting JSON)
- `language` (language targeting JSON)
- `parameters` (UTM forwarding map)
- `deeplink` (bool)
- `meta_title`, `meta_description`, `meta_image`
- `type` (`direct`, `splash`, `frame`, `overlay`, `password`, `media`, etc.)
- `splashid`, `overlayid` (FK)
- `pixels` (array of pixel ids)
- `channel` (channel id)
- `campaign` (campaign id)

### QR (`API\QR`)

```
GET    /api/qrs                        api.qr.get
GET    /api/qr/{id}                    api.qr.single
POST   /api/qr/add                     api.qr.create
PUT    /api/qr/{id}/update             api.qr.update
DELETE /api/qr/{id}/delete             api.qr.delete
```

### Domains (`API\Domains`)

```
GET    /api/domains                    api.domain.get
POST   /api/domain/add                 api.domain.create
PUT    /api/domain/{id}/update         api.domain.update
DELETE /api/domain/{id}/delete         api.domain.delete
```

### Campaigns (`API\Campaigns`)

```
GET    /api/campaigns                  api.campaigns.get
POST   /api/campaign/add               api.campaigns.create
PUT    /api/campaign/{id}/update       api.campaigns.update
DELETE /api/campaign/{id}/delete       api.campaigns.delete
POST   /api/campaign/{id}/assign/{link} api.campaigns.assign  ← attach a link to a campaign
```

### Channels (`API\Channels`)

```
GET    /api/channels                            api.channels.get
GET    /api/channel/{id}                        api.channels.single
POST   /api/channel/add                         api.channels.create
PUT    /api/channel/{id}/update                 api.channels.update
DELETE /api/channel/{id}/delete                 api.channels.delete
POST   /api/channel/{id}/assign/{type}/{link}   api.channels.assign  ← polymorphic add
```

### Splash (`API\Splash`)

```
GET    /api/splashes                   api.splash.get
```

### Overlay (`API\Overlay`)

```
GET    /api/overlays                   api.overlay.get
```

### Pixels (`API\Pixels`)

```
GET    /api/pixels                     api.pixel.get
POST   /api/pixel/add                  api.pixel.create
PUT    /api/pixel/{id}/update          api.pixel.update
DELETE /api/pixel/{id}/delete          api.pixel.delete
```

### Plans (admin-only) (`API\Plans`)

```
GET    /api/plans                      api.plans.get
PUT    /api/plan/{id}/user/{userid}    api.plan.subscribe   ← subscribe a user to a plan
```

Admin-only: the constructor returns 403 if `Auth::ApiUser()->admin` is falsy.

### Users (admin-only) (`API\Users`)

```
GET    /api/users                      api.user.get
GET    /api/user/{id}                  api.user.single
POST   /api/user/add                   api.user.create
DELETE /api/user/{id}                  api.user.delete
GET    /api/user/{id}/login            api.user.login       ← mint SSO/login token
```

Filtering on `GET /api/users`: `?filter=admin|pro|free`, `?email=`.

---

## Standard response shapes

### Success

```json
{ "error": 0, "data": { ... } }
```

Some endpoints flatten and return the resource directly. List endpoints typically return:

```json
{
  "error": 0,
  "data": [ ... ],
  "pagination": { "page": 1, "per_page": 15, "total": 230, "pages": 16 }
}
```

### Error

```json
{ "error": 1, "message": "Human-readable error." }
```

Auth failures return HTTP 403 with the error envelope. Throttle exceed returns 429 with:

```json
{
  "error": 429,
  "message": "Too Many Requests. Please retry later.",
  "Retry-After": 47
}
```

### Validation

Per-controller — typically `{ "error": 1, "message": "Field X is required" }`.

---

## API documentation source

The 63KB file at `app/config/api.php` is the **API documentation manifest** consumed by the in-app developer docs page (`Page@api`, `apidocs` route). It's a giant PHP array with this shape:

```php
return [
  '<group>' => [
    'admin'       => bool,
    'title'       => string,        // localized
    'description' => string|null,
    'endpoints'   => [
      [
        'title'       => string,
        'method'      => 'GET'|'POST'|'PUT'|'DELETE',
        'route'       => route('api.url.create'),
        'description' => string,
        'parameters'  => [ name => description, ... ],
        'code'        => [ ... ],      // example request body
        'response'    => [ ... ],      // example response
      ],
      ...
    ],
  ],
  ...
];
```

The 10 top-level groups: `account`, `domains`, `splash`, `cta`, `channels`, `links`, `pixels`, `qr`, `plans` (admin), `users` (admin).

**Note:** this file is **documentation**, not a route registry — routes are still registered in `app/routes.php` (lines 581-643). Keep both in sync when adding new endpoints.

---

## Adding a new API endpoint

1. Add route inside the API group in `app/routes.php`:
   ```php
   Gem::get('/widgets', 'API\Widgets@get')->name('api.widgets.get');
   ```
2. Create `app/controllers/api/WidgetsController.php`:
   ```php
   <?php
   namespace API;
   use Core\Auth;
   use Core\Request;
   use Core\Response;
   use Core\DB;

   class Widgets {
       public function __construct() {
           if (!Auth::ApiUser() || !Auth::user()->keyCan('widgets')) {
               Response::factory(['error' => 1, 'message' => 'Unauthorized'], 403)->json();
               exit;
           }
       }
       public function get(Request $request) {
           $items = DB::widgets()->where('userid', Auth::id())->findMany();
           Response::factory(['error' => 0, 'data' => array_map(fn($w) => $w->asArray(), $items)])->json();
       }
   }
   ```
3. Add documentation entry to `app/config/api.php`.
4. If the endpoint needs new scopes, add them to `apikeys.permissions` UI in `User\Developers`.
