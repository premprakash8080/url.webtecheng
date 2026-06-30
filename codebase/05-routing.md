# 05 — Routing

`app/routes.php` (735 lines) is the **single source of truth** for every HTTP endpoint. ~526 routes registered.

## DSL

```php
// Verbs
Gem::get($path, 'Class@method')->name('route.name')->middleware('Mw');
Gem::post($path, 'Class@method');     // automatically gets CSRF middleware
Gem::put / Gem::delete($path, ...)
Gem::route(['GET','POST'], $path, ...); // multi-verb; bypasses POST auto-CSRF if no POST

// Grouping
Gem::group($prefix, function() {
    Gem::setMiddleware(['CheckDomain','Auth']);  // group middleware (apply to routes after this call)
    Gem::get('/foo', '…');                       // applies group middleware
    // …
});

// REST shortcut: 6 routes — index, new, save (POST), edit, update (POST), delete (GET with nonce)
Gem::resources('plans', 'Admin\Plans', ['Auth@admin']);
```

Chained on a route registration:
- `->name($string)` — re-key the route (the route table is keyed by name; `route('foo.bar')` resolves via `Gem::href`).
- `->middleware($single_or_array)` — append to the route's middleware list.

## Locale prefix

Top of file (`routes.php:21-27`):

```php
\Helpers\App::checkEncryption();
$prefix = '';
if(in_array(request()->segment(1), Localization::listArray())){
    $prefix = '/'.request()->segment(1);
    Localization::setLocale(request()->segment(1));
}
```

If the first URL segment is a registered locale, it becomes a `/<lang>` prefix and is applied to public routes (concatenated into the path string). Routes that intentionally bypass localization (admin, API, webhooks, `/{alias}`, `/u/{username}`, `/qr/{id}`) just don't prepend `$prefix`.

---

## Group totals

| # | Group | Routes | Lines | Group middleware | URL prefix |
|---|---|---|---|---|---|
| 1 | Public / Marketing | 31 | 30-58, 69-87 | — (per-route) | `$prefix` (locale) |
| 2 | Blog | 4 | 61-67 | `CheckDomain`, `CheckPrivate` | `$prefix/blog` |
| 3 | Auth flow (guest) | 18 | 91-114 | — (per-route) | `$prefix/user` |
| 4 | User Dashboard | 138 | 117-281 | `CheckDomain`, `Auth` | `$prefix/user` |
| 5 | Admin | 242 | 283-578 | `Auth@admin`, `Locale@admin` | `appConfig('app.adminroute')` (default `/admin`) |
| 6 | API | 42 | 581-643 | `Auth@api`, `Throttle`, `CheckDomain` (but `/oauth/token` is public — declared before `setMiddleware`) | `appConfig('app.apiroute')` (default `/api`) |
| 7 | Cron | 5 | 645-651 | — | `/crons` |
| 8 | Webhooks / Payment callbacks | 10 | 666-680 | per-route | site root |
| 9 | Sitemap + utility | 8 | 653-664 | — | site root |
| 10 | QR public | 3 | 683-685 | — | site root |
| 11 | Shortener-public stats + redirect | 24 | 688-723 | per-route | site root + `$prefix` |
| 12 | Captcha | 1 | 725 | `BlockBot` | site root |
| | **Total** | **526** | | | |

## All 13 unique middleware names

`Auth`, `Auth@admin`, `Auth@api`, `BlockBot`, `CheckDomain`, `CheckMaintenance`, `CheckPrivate`, `Locale@admin`, `ShortenThrottle`, `Throttle`, `UserLogged`, `ValidateCaptcha`, `ValidateLoggedCaptcha`.

Plus `CSRF` is auto-added by `Gem::post()` — not visible in `routes.php` source.

## Route parameters seen in this file

`{alias}` (shortlink slug), `{username}`, `{post}` (blog slug), `{page}` (CMS), `{slug}` (help article), `{category}` (help), `{id}` (generic), `{token}` (security/email), `{nonce}` (one-shot confirm), `{type}` (plan/channel/subscription), `{provider}` (OAuth/webhook), `{action}`, `{markas}`, `{format}` (QR), `{size}` (optional, `[/{size}]`), `{ext}`, `{days}`, `{block}` (bio), `{i}` (bio toggle index), `{item}`, `{name}`, `{vote}`, `{userid}`, `{link}`, `{config}` (settings page), `{tag}` (plugin marketplace).

## The catch-all `/{alias}` (line 723)

```php
Gem::route(['GET', 'POST'], '/{alias}', 'Link@redirect')->name('redirect');
```

This is the **public shortlink resolver**. Any single-segment URL not matched by an earlier route falls through here. **It must remain the last root-level route — adding anything after it makes the new route unreachable.**

Routes that must be defined **before** the catch-all to take precedence:
- `/{id}/i`, `/{id}/ico`, `/{id}/qr`, `/{id}/qr/download/{format}` — preview/icon/QR images for a short link
- `/{id}/stats` (and all 14 `stats.*` siblings) — stats pages, locale-prefixed
- `/u/{username}` — bio profile
- `/r/{alias}`, `/u/{username}/{alias}` — campaign shortlinks
- `/{alias}+` — alias with `+` suffix → simple-stats view (`Stats@simple`)
- `/qr/{id}` — public QR rendering
- `/bookmark`, `/q`, `/fullpage`, `/script.js`, `/sitemap*`, `/update`, `/webhook*`, `/ipn`, `/server/*`, `/callback/*`, `/captcha/challenge` — utility/integration

---

## Group detail

### 1. Public / Marketing (lines 30-87)

| Method | Path | Action | Name |
|---|---|---|---|
| GET/POST | `$prefix/` | `Home@index` | `home` |
| GET | `$prefix/pricing` | `Subscription@pricing` | `pricing` |
| GET | `$prefix/checkout/{id}/{type}` | `Subscription@checkout` | `checkout` |
| POST | `$prefix/checkout/{id}/{type}` | `Subscription@process` | `checkout.process` |
| GET | `$prefix/checkout/{id}/{type}/coupon` | `Subscription@coupon` | `checkout.coupon` |
| GET | `$prefix/checkout/{id}/{type}/tax` | `Subscription@tax` | `checkout.tax` |
| POST | `$prefix/checkout/redeem` | `Subscription@redeem` | `checkout.redeem` |
| GET | `$prefix/page/{page}` | `Page@index` | `page` |
| GET | `$prefix/qr-codes` | `Page@qr` | `page.qr` |
| GET | `$prefix/bio-profiles` | `Page@bio` | `page.bio` |
| GET | `$prefix/contact` | `Page@contact` | `contact` |
| POST | `$prefix/contact/send` | `Page@contactSend` | `contact.send` |
| GET | `$prefix/report` | `Page@report` | `report` |
| POST | `$prefix/report/send` | `Page@reportSend` | `report.send` |
| GET | `$prefix/developers` | `Page@api` | `apidocs` |
| GET | `$prefix/consent` | `Page@consent` | `consent` |
| GET | `$prefix/oauth/authorize` | `API\OAuth@authorize` | `oauth.authorize` |
| POST | `$prefix/oauth/authorize/proceed` | `API\OAuth@proceed` | `oauth.proceed` |
| GET/POST | `$prefix/verify/links` | `Link@verify` | `links.verify` |
| POST | `$prefix/shorten` | `Link@shorten` | `shorten` |
| GET | `$prefix/faq` | `Page@faq` | `faq` |
| GET | `$prefix/help` | `Help@index` | `help` |
| GET | `$prefix/help/topic/{category}` | `Help@category` | `help.category` |
| GET | `$prefix/help/search/` | `Help@search` | `help.search` |
| GET | `$prefix/help/article/{slug}` | `Help@single` | `help.single` |
| POST | `$prefix/help/article/{slug}/{vote}` | `Help@vote` | `help.vote` |
| GET | `$prefix/affiliate` | `Page@affiliate` | `affiliate` |
| GET/POST | `/u/{username}` | `Link@profile` | `profile` |
| GET | `/user/login/facebook` | `Users@loginWithFacebook` | `login.facebook` |
| GET | `/user/login/twitter` | `Users@loginWithTwitter` | `login.twitter` |
| GET | `/user/login/google` | `Users@loginWithGoogle` | `login.google` |

### 2. Blog (`/blog`)

```
GET  /blog/                     → Blog@index       (blog)
GET  /blog/category/{post}      → Blog@category    (blog.category)
GET  /blog/search               → Blog@search      (blog.search)
GET  /blog/{post}               → Blog@post        (blog.post)
```

### 3. Auth flow (guests only — `/user/login`, `/user/register`, …)

Per-route middleware: `CheckDomain` + `UserLogged` (blocks logged-in users). 18 routes covering login, 2FA (`login.2fa`, `login.2fa.validate`, `login.2fa.recover`, `reset2fa`), SSO, register, password reset, account activation, invitation acceptance, email unsubscribe.

### 4. User Dashboard (`/user/*`, Auth-protected)

138 routes organized by sub-feature. Highlights:

- **Links** (17) — list, archived, edit, update, delete, bulk archive/delete, add to campaign.
- **Campaigns** (9) — CRUD + per-campaign stats (clicks, map, browser, OS).
- **Account** (10) — settings, billing, terminate, 2FA, invoice, regenerate API key, social connect.
- **Splash** (7), **Overlay** (6), **Pixels** (7), **Domains** (6) — CRUD per feature.
- **Teams** (8) — invite, edit, switch, accept, toggle, delete.
- **QR** (12) — regular + bulk (`createbulk`, `savebulk`, `downloadall`, `deleteall`).
- **Bio** (14) — pages CRUD plus per-block updates (`/bio/{id}/update/{block}`, `/bio/{id}/update/toggle/{i}`), widgets, default-toggle, duplicate.
- **Stats** (9) — links, clicks chart, map, platforms, browsers, languages.
- **Channels** (7), **Affiliate** (4), **Verification** (2), **Export** (4), **Import** (3), **Search** (1), **Tools** (3), **Developers** (3), **Security** (1), **Integrations** (1).

### 5. Admin (`/admin/*`, configurable prefix)

242 routes. Group middleware `Auth@admin` (404 for non-admins) + `Locale@admin` (load admin translation bundle). Major sub-areas:

| Sub-area | Routes | Controllers |
|---|---|---|
| Dashboard + tools | 9 | `Admin\Dashboard`, `Admin\Tools` |
| Stats | 8 | `Admin\Stats` |
| Plans / subscriptions / payments / finance | 16 | `Admin\Plans`, `Admin\Membership`, `Admin\Finance` |
| Coupons / vouchers / tax | 17 | `Admin\Coupons`, `Admin\Vouchers`, `Admin\Tax` |
| Links moderation | 21 | `Admin\Links` |
| Users (CRUD, bans, roles, testimonials, login-as) | 34 | `Admin\Users` |
| Roles & permissions | 10 | `Admin\Roles` |
| Verifications (KYC) | 3 | `Admin\Verifications` |
| Bio + BioThemes | 9 | `Admin\Bio`, `Admin\BioThemes` |
| QR + QrTemplates | 8 | `Admin\Qr`, `Admin\QrTemplates` |
| Pages CMS + Blog CMS | 17 | `Admin\Pages`, `Admin\Blog` |
| Domains | 9 | `Admin\Domains` |
| FAQs | 11 | `Admin\Faqs` |
| Affiliates + withdrawals | 9 | `Admin\Affiliates` |
| Ads / Themes / Plugins | 25 | `Admin\Ads`, `Admin\Themes`, `Admin\Plugins` |
| Settings / OAuth / Languages | 21 | `Admin\Settings`, `Admin\OAuth`, `Admin\Languages` |
| Email manager + Notifications | 12 | `Admin\EmailManager`, `Admin\Notifications` |
| Self-update + crons | 5 | `Admin\Dashboard` |

### 6. API (`/api/*`)

42 routes. `POST /oauth/token` is **public** (declared before `setMiddleware`); all others go through `Auth@api`, `Throttle`, `CheckDomain`. See [12-api.md](./12-api.md) for the full endpoint list.

### 7. Cron (`/crons/*`)

```
GET /crons/users/{id}        → Cron@user       (crons.user)     — downgrade expired pros
GET /crons/data/{id}         → Cron@data       (crons.data)     — prune stats by retention
GET /crons/urls/{id}         → Cron@urls       (crons.urls)     — re-check URL metadata
GET /crons/remind/{days}/{id}→ Cron@remind     (crons.remind)   — trial-expiry email
GET /crons/imports/{id}      → Cron@imports    (crons.imports)  — process CSV imports
```

`{id}` here is a token check: `md5('<name>'.AuthToken)`. Wire to external cron daemon.

### 8. Webhooks / Payment callbacks

```
POST  /server/contact            → Server@contact
POST  /server/subscribe          → Server@subscribe
POST  /server/vote               → Server@vote
GET   /server/states             → \Helpers\App@states
GET   /server/deeplink           → Server@deeplink    (Auth)
GET|POST /ipn                    → Webhook@ipn        (webhook.paypal) — classic PayPal IPN
GET|POST /webhook[/{provider}]   → Webhook@index      (webhook)        — Stripe/PayPal API/etc
GET   /callback/paddle           → \Helpers\Payments\Paddle@callback
GET   /callback/paystack         → \Helpers\Payments\PayStack@callback  (Auth)
GET   /callback/mollie           → \Helpers\Payments\Mollie@callback    (Auth)
```

### 9. Sitemap + utility

```
GET   /q                      → Link@quick
GET   /fullpage               → Link@fullpage
GET   /script.js              → Link@scriptjs       — JS shortener widget
GET   /sitemap.xml            → Sitemap@index
GET   /sitemap/site.xml       → Sitemap@site
GET   /sitemap/links.xml      → Sitemap@links
GET   /sitemap/biopages.xml   → Sitemap@bio
GET|POST /update              → Update@index        — installer/upgrader
```

### 10. QR public

```
POST  /server/generateqr           → QR@generateqr   (qr.generateqr)
GET   /qr/{id}                     → QR@generate     (qr.generate)
GET   /qr/{id}/download/{format}[/{size}] → QR@download (qr.download)
```

### 11. Shortener-public stats + catch-all redirect

```
GET   /r/{alias}                 → Link@campaign         (campaign)
GET   /u/{username}/{alias}      → Link@campaignList     (campaign.list)
GET   /{alias}+                  → Stats@simple          (stats.alt)         — "+" suffix = stats view
GET   /bookmark                  → Link@bookmark

GET   $prefix/{id}/stats         → Stats@index           (stats)
GET   $prefix/{id}/stats/activity → Stats@activity
GET   $prefix/{id}/stats/clicks  → Stats@clicks
GET   $prefix/{id}/data/clicks   → Stats@dataClicks
... (14 stats endpoints total per-link) ...
GET   /{id}/i                    → Link@image
GET   /{id}/ico                  → Link@icon
GET   /{id}/qr[/{size}]          → Link@qr
GET   /{id}/qr/download/{format}[/{size}] → Link@qrDownload

GET|POST /{alias}                → Link@redirect         (redirect)          — THE CATCH-ALL
```

### 12. Captcha

```
GET   /captcha/challenge         → \Helpers\Captcha@altchaChallenge   (BlockBot middleware)
```

---

## Reverse routing

```php
route('home');                      // → /
route('redirect', ['abc'])          // → /abc
route('admin.users.edit', [42]);    // → /admin/users/42/edit
route('api.url.single', [123]);     // → /api/url/123
```

Goes through `Gem::href($name, $param, $lang)`. Indexed array → substitute placeholders; no placeholders left → appended as `?http_build_query($param)`.
