# 10 — Business Logic Flows

End-to-end walkthroughs of the most important flows. Each one names the exact controller, trait, and helper involved so you can jump straight to the code.

---

## 1. Public shortlink redirect — `GET /{alias}`

Heart of the product. Route: `app/routes.php:723`.

```
.htaccess → public/index.php → Gem::Bootstrap → Gem::Dispatch
   ↓
LinkController@redirect(Request, $alias)            [app/controllers/LinkController.php]
   1. Traits\Links::getURL($request, $alias)
        BINARY-match alias on DB::url() (custom-domain aware)
        404 if not found
   2. Plugin::dispatch('link.preredirect', $url)    ← extension point
   3. Safety checks:
        - banned user → Gate::disabled
        - $url->disabled → Gate::disabled
        - domain blacklist → mark disabled, Gate::disabled
        - Google Web Risk safe/phish → mark disabled
        - VirusTotal hit → mark disabled
   4. Password gate:
        - $_SESSION["password_$id"] match → skip; else Gate::password
   5. Profile vs link:
        - $url->profileid → DB::profiles()->first(profileid), Gate::profile
        - $url->qrid     → behaves as a QR (handled earlier in QR routes)
   6. Click-cap & expiry:
        - clicks >= cap → Gate::expired (optionally redirect to expiration URL)
        - expiry < today → Gate::expired
   7. Rotators (A/B):
        - $options->rotators array → pick by weight
   8. Geo targeting ($url->location JSON):
        - country / state match → override destination
   9. Device targeting ($url->devices JSON):
        - per-device URL override; iOS/Android → Gate::deeplink
  10. Language targeting ($url->language JSON):
        - Accept-Language match → override
  11. UTM/parameter forwarding from $url->parameters
  12. Plugin::dispatch('link.redirect', $url)
  13. Traits\Links::updateStats($request, $url, $user)
        - INSERT INTO stats (urlid, country, city, os, browser, language, referer, ip[, device])
        - if $user->zapview → Http::url($user->zapview)->post(...)
  14. Plugin::dispatch('link.override', $url) ← last chance to swap destination
  15. Branch on $url->type:
        direct → Gate::direct (HTTP 302)
        splash → Gate::splash
        custom → Gate::custom
        frame  → Gate::frame
        overlay→ Gate::overlay
        media  → Gate::media
        embed  → Gate::embed
        bundle → Gate::bundle
```

**Files involved:** `LinkController.php`, `Traits\Links`, `Helpers\Gate`, `Models\User`, `Core\Plugin`.

---

## 2. Anonymous link shorten — `POST /shorten`

Route: `app/routes.php:69`. Middleware: `BlockBot`, `ShortenThrottle`, `ValidateLoggedCaptcha`, `CSRF` (auto).

```
LinkController@shorten(Request)
   1. Optionally Auth::check (anonymous shortening depends on config('user'))
   2. If team session active: Permissions::can('links.create')
   3. Read $request->url (single) or $request->urls (multi-line newline-separated)
   4. For each URL:
        Traits\Links::createLink($request, $user, $output)
          - validate($url)                  ← regex + reachability
          - domain/word blacklist check
          - safe / phish / virus checks      ← only when user has feature
          - alias = $request->custom OR Traits\Links::alias()
            (alias() uses appConfig('app.aliasformat') chars + Helper::rand)
          - aliasReserved + duplicateurls + isSelf checks
          - DB::url()->create() + set + save
          - attach pixels, deeplink, geo, device options
          - Helpers\App::shortRoute($alias, $domain) → final short URL
   5. Response::factory(['error' => 0, 'url' => $shorturl, 'urls' => [...]])
      ->json()
```

**Throttling:** `ShortenThrottle` limits to 5 hits/min per session. **Captcha:** `ValidateLoggedCaptcha` runs only for guests.

---

## 3. Login + 2FA — `POST /user/login/auth`

Route: `app/routes.php:92`. Middleware: `BlockBot`, `CheckDomain`, `UserLogged`, `ValidateCaptcha`, `CSRF` (auto).

```
UsersController@loginAuth(Request)
   1. Cookie '__bl' tracks failed attempts — block after threshold
   2. Lookup by email or username
   3. Helper::validatePass($input, $user->password)  ← bcrypt with AuthToken salt
        - failure → increment __bl, redirect back
   4. State checks:
        - $user->active === 0       → "account not activated" + resend link
        - $user->banned             → "account banned"
        - config('maintenance')     → block non-admins
   5. Pro expiry auto-downgrade:
        if pro && expiration < today: clear pro/planid/trial, Emails::canceled
   6. Default plan assignment if planid missing
   7. If $user->secret2fa is set:
        - $_SESSION['2FAKEY'] = Helper::encrypt({id:$user->id})
        - redirect to /user/login/2fa
   8. Else: session_regenerate_id, rotate $user->authkey, save
   9. New-IP detection:
        - if $request->ip() not in $user->logins JSON → Emails::newip
  10. Set Auth::COOKIE:
        $payload = Helper::encrypt(['loggedin'=>1, 'key'=>$user->authkey.$user->id])
        cookie for 14 days if rememberme; else session
  11. Helpers\Events::for('login')->user($user->id)->log($data)
  12. redirect to $_SESSION['redirect'] or route('dashboard')

Login2FAValidate → POST /user/login/2fa/validate
   - decrypts 2FAKEY to get user id
   - Sonata\GoogleAuthenticator::checkCode($user->secret2fa, $request->code)
   - on success: same auth-cookie + events sequence as above
```

**Social login** (`loginWithFacebook|Twitter|Google`) follows the same auth-cookie sequence at the end but receives user identity from the OAuth provider.

---

## 4. Subscription checkout — `POST /checkout/{id}/{type}`

Route: `app/routes.php:35`. Middleware: `Auth`, `CSRF` (auto).

```
SubscriptionController@process(Request, $id, $type)
   1. config('pro') check (constructor 404s if pro disabled)
   2. Auth::check (no team context allowed)
   3. Save billing address to DB::user() (country, address, vat, etc.)
   4. Cancel existing subscription via processor('<active>', 'cancel') if needed
   5. Plugin/POST hook: config('saleszapier') webhook
   6. Lookup plan: DB::plans()->first($id)
   7. Apply coupon if provided (Subscription@coupon endpoint feeds this)
   8. Apply tax if applicable (Subscription@tax endpoint feeds this)
   9. Dispatch to chosen gateway:
        $callable = Traits\Payments::processor($request->payment, 'payment')
        call_user_func_array($callable, [$request, $id, $type])

   Gateway-specific behavior:
   - Stripe:        creates Customer (if missing), creates PaymentLink/Checkout Session,
                    redirects to Stripe-hosted page
   - PaypalApi:     creates Payment, redirects to PayPal approval URL
   - PaddleBilling: builds Paddle transaction, redirects to Paddle Checkout
   - Mollie:        creates Payment, redirects to Mollie hosted page
   - PayStack:      initiates transaction, redirects to PayStack
   - Bank:          inserts pending payment, shows "transfer details" view

   User completes at gateway → returns to /callback/<provider> or webhook fires
```

### Webhook → subscription update

```
POST /webhook[/{provider}]                            (route: app/routes.php:674)
   WebhookController@index(Request, $provider = null)
   1. $provider defaults to 'stripe', 'paypal' is rewritten to 'paypalapi'
   2. Plugin::register('payment.success', affiliateCreditClosure)
        On every successful payment row, look up urid cookie → credit DB::affiliates
   3. Dispatch:
        if method exists on Webhook itself → call it
        else $callable = Traits\Payments::processor($provider, 'webhook')
             call_user_func_array($callable, [$request])

   Provider webhook (e.g. Stripe@webhook):
   1. Verify signature (Stripe::Webhook::constructEvent for Stripe)
   2. Branch on event type:
        invoice.paid → INSERT INTO payment, UPDATE subscription
        customer.subscription.created/updated → UPDATE subscription
        customer.subscription.deleted → status='Cancelled'
   3. Plugin::dispatch('payment.success', $payment)  ← affiliate credit fires here
```

**Files involved:** `SubscriptionController`, `WebhookController`, `Traits\Payments`, `Helpers\Payments\<gateway>`.

---

## 5. Cron jobs

External cron daemon hits the `/crons/*` endpoints. Each handler verifies its token: `md5('<name>'.AuthToken)`.

| Endpoint | Handler | What it does |
|---|---|---|
| `/crons/users/{token}` | `Cron@user` | Iterate up to 500 paid non-admin users. Downgrade anyone whose pro/trial `expiration` has passed: clear `pro`, `planid`, `trial`; `Emails::canceled($user)`. |
| `/crons/data/{token}` | `Cron@data` | Enforce per-plan retention: `DELETE FROM stats WHERE clicked_at < NOW() - INTERVAL plan.retention DAY`. 500 users per run. |
| `/crons/urls/{token}` | `Cron@urls` | Sample 500 random active non-QR/non-profile URLs. Re-check meta + reachability. |
| `/crons/remind/{days}/{token}` | `Cron@remind` | Email trial users whose `expiration = TODAY + days`. |
| `/crons/imports/{token}` | `Cron@imports` | Pop next pending row from `DB::imports()`. Read CSV from `STORAGE/app/imports/<file>`. Process up to 250 rows via `Traits\Links::createLink`. Rewrite file with remainder (or delete on completion). Update `processed`, `status`. |

Each handler logs to its own Monolog channel via `GemError::channel('<name>', LOGS)`.

**Suggested external cron:**

```cron
0 * * * *   curl -s https://example.com/crons/users/$(php -r 'echo md5("user".AuthToken);')
0 2 * * *   curl -s https://example.com/crons/data/$(php -r 'echo md5("data".AuthToken);')
*/15 * * * * curl -s https://example.com/crons/imports/$(php -r 'echo md5("imports".AuthToken);')
0 9 * * *   curl -s https://example.com/crons/remind/3/$(php -r 'echo md5("remind".AuthToken);')
0 9 * * *   curl -s https://example.com/crons/remind/1/$(php -r 'echo md5("remind".AuthToken);')
```

(Actual production setup will use stored tokens, not inline PHP.)

---

## 6. Slack `/shorten` slash command

Route: any `Webhook@slack` mapping (registered in admin/OAuth flow). Slack POSTs a slash command.

```
WebhookController@slack(Request)
   1. URL verification challenge response (Slack handshake)
   2. event_callback 'app_uninstalled' → clear user.slackid/slackteamid
   3. Else:
      Helpers\Slack::validate(config('slacksigningsecret'))
      Parse command text:
        - "(CUSTOM_ALIAS) https://..." → use that custom alias
        - "clicks: https://short.url" → return stats for last 5 clicks
        - else → just shorten
      Traits\Links::createLink($request, $user)
      If processing > 3 s: POST result to Slack response_url
      Else: print response inline
```

---

## 7. Public REST API request

Route group: `/api/*` (configurable prefix). Middleware on group: `Auth@api`, `Throttle`, `CheckDomain`.

```
GET /api/url/{id} with Authorization: Bearer <token>
   1. Auth@api middleware:
        - parse Bearer token
        - Auth::ApiUser($token):
             a) OAuth pattern (e.g. abc-12345-xyz-67890) → DB::oauth_access_tokens()
             b) user.api column match
             c) DB::apikeys() match
        - 403 JSON if no user / banned / not activated / config('api') off
   2. Throttle middleware:
        - Helper::cacheGet keyed by token
        - per-user override: $user->plan('apirate'), 0 = unlimited
        - 429 JSON with Retry-After if exceeded
   3. API\Links::single($id):
        $url = DB::url()->where('userid', $apiuser->id)->where('id', $id)->first()
        Response::factory($url->asArray())->json()
```

**OAuth2 token endpoint** is the only public API route:

```
POST /api/oauth/token  (no middleware)
   API\OAuth::token(Request)
   1. Read grant_type, client_id, client_secret, [code | refresh_token]
   2. Validate against DB::oauth_clients()
   3. Issue access_token via Helper::rand() formatted as <a-z0-9>-<5d>-<a-z>-<5d>
   4. INSERT INTO oauth_access_tokens
   5. Response::factory(['access_token'=>..., 'token_type'=>'Bearer', ...])->json()
```

---

## 8. Custom domain resolution

Every public request goes through `Middleware\CheckDomain`:

```
request Host vs config('url')
   ├─ same → pass through
   └─ different →
        DB::domains()->where('domain', $host)->first()
        ├─ row.bioid     → Gate::profile($profile, $user); exit
        ├─ row.redirect  → header("Location: {row.redirect}"); exit
        ├─ in config('domain_names') → redirect to config('url')
        └─ unknown       → render gates.domain landing; exit
```

This is how white-label / branded short-domain mode works: customers point their DNS at the host, register the domain via `User\Domains`, and choose whether it shortens (default), redirects, or fronts a bio.

---

## 9. Imports (CSV bulk shorten)

```
User\Import::importLinks(Request)
   1. Upload CSV → STORAGE/app/imports/<random>.csv
   2. INSERT INTO imports (userid, file, total, processed=0, status='pending')

Cron@imports (called by external scheduler)
   3. Pop oldest pending import
   4. Read file, process up to 250 lines via Traits\Links::createLink
   5. Write remaining lines back to file
   6. processed += 250
   7. If file empty: delete + status='completed'
```

Status visible to user under `/user/import`. Cancellable via `User\Import@cancel`.

---

## 10. Plugin lifecycle

```
Bootstrap                  Plugin::preload()
                            ├─ active theme's config.json:include → include_once each
                            └─ config('plugins') JSON → include PLUGIN/<name>/plugin.php
                                                          (each plugin.php registers hooks)

Runtime              Plugin::register('hook.name', callable)
                     Plugin::dispatch('hook.name', $payload)
                       → invokes every registered callable, returns array of returns

Built-in events
   error.exception        from GemError::exception (PHP error handler)
   error.fatal            from GemError::fatal     (shutdown function)
   error.trigger          from GemError::trigger   (404/500/etc)

App events you'll find:
   link.preredirect       LinkController@redirect
   link.redirect          LinkController@redirect
   link.override          LinkController@redirect
   payment.success        WebhookController@index
   payment.extend         Traits\Payments::processor (extend gateway list)
   middleware.csrf.exempt Middleware\CSRF (extend exempt paths)
```

A sample plugin lives at `storage/plugins/helloworld/`.
