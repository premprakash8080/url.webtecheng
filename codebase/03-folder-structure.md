# 03 — Folder Structure

```
url.webtecheng/
├── .htaccess               Apache front controller: rewrites /^(.*)$/ → public/$1
├── nginx.conf              Sample Nginx config (docroot must be /public)
├── index.php               Tiny stub w/ proprietary licence block
├── LICENSE                 GemPixel proprietary licence
├── composer.json           PHP deps (Stripe, PayPal, Mollie, Endroid QR, …)
├── composer.lock
├── config.php              ⚠️ Runtime constants: DB creds, AuthToken, EncryptionToken
├── codebase/               ← This Second Brain folder (docs only)
│
├── app/                    Application code
│   ├── core.php            Path constants + framework includes + Gem::preload()
│   ├── index.php           Stub (licence only)
│   ├── routes.php          ★ All 526 routes (735 lines)
│   ├── config/             Static PHP-array configs
│   │   ├── app.php         Feature toggles, storage paths, throttle, drivers
│   │   ├── api.php         63KB API DOCUMENTATION manifest (not the router)
│   │   ├── boot.php        Boot-time callables (currently [Setup::check])
│   │   └── cdn.php         Third-party JS/CSS library version + paths
│   ├── controllers/        ★ HTTP handlers
│   │   ├── {root}.php      15 public/non-namespaced controllers (Home, Link, Users, …)
│   │   ├── admin/          30 controllers, namespace \Admin
│   │   ├── api/            12 controllers, namespace \API
│   │   └── user/           19 controllers, namespace \User
│   ├── models/             5 models extending \Core\Model
│   │   ├── User.php
│   │   ├── Url.php
│   │   ├── Plans.php
│   │   ├── Role.php
│   │   └── Settings.php
│   ├── middleware/         14 classes, namespace \Middleware
│   ├── helpers/            ~17 root helpers + payments/ + qr/
│   │   ├── *.php           App, Autoupdate, BioWidgets, CDN, Captcha, DeepLinks,
│   │   │                   Emails, Events, FacebookAuth, Gate, GoogleAuth,
│   │   │                   GoogleTranslate, Permissions, QR, QrGd, QrImagick, Slack
│   │   ├── payments/       8 gateway classes + IpnListener
│   │   └── qr/             18 module/eye/frame classes for stylised QR
│   └── traits/             Shared behavior
│       ├── Links.php       ★ Core link engine — used by 13 callers
│       ├── Overlays.php
│       ├── Payments.php    ★ Gateway registry (`processor()`)
│       ├── Pixels.php
│       └── Teams.php
│
├── core/                   🛑 GemFramework — DO NOT EDIT
│   ├── *.class.php         Gem, DB, Auth, Model, View, Helper, Request, Response,
│   │                       Middleware, Plugin, Localization, File, Email, Http,
│   │                       GemError, Collection
│   ├── index.php           Stub
│   ├── functions/
│   │   ├── core.php        Autoloaders + error handlers + config()/appConfig()/auth()/user()
│   │   └── helpers.php     ~30 global helpers (url, route, csrf, e, render, …)
│   └── support/            Vendored libraries (re-namespaced under Core\Support)
│       ├── ORM.class.php   Full Idiorm source — THE real ORM (2620 lines)
│       ├── Mailgun.class.php
│       ├── Mailchimp.class.php    Mandrill driver despite the name
│       ├── Sendgrid.class.php
│       └── Postmark.class.php
│
├── public/                 ★ Webroot — point your virtualhost here
│   ├── index.php           Loads app/core.php and calls Gem::Bootstrap()
│   ├── .htaccess           (none in webroot directly — handled by root .htaccess)
│   ├── web.config          IIS rewrite rules
│   ├── favicon.ico
│   ├── robots.txt
│   ├── googledcba9e6f0ac9898d.html   Google Search Console verification
│   ├── content/            User uploads (served as /content/<path>)
│   │   ├── avatar/         User avatars
│   │   ├── blog/           Blog post images
│   │   ├── files/          QR file payloads
│   │   ├── images/         Generic uploaded images
│   │   ├── profiles/       Bio profile assets
│   │   ├── qr/             Generated QR SVGs/PNGs (cached)
│   │   └── variables.css   Per-install CSS variables
│   └── static/             ★ Site-wide static assets (served as /static/<path>)
│       ├── app.js / app.min.js          Dashboard JS
│       ├── bio.js / bio.min.js          Bio-page renderer JS
│       ├── biopages.css / .min.css
│       ├── bootstrap.min.css / .rtl
│       ├── bundle.pack.js               Concatenated dashboard JS bundle
│       ├── webpack.pack.js              Concatenated webpack bundle
│       ├── Chart.min.js                 Charts.js for stats
│       ├── charts.js / charts.min.js
│       ├── content-style.css / .min.css
│       ├── crop.js / crop.min.js        Cropper.js wrapper
│       ├── custom.js / custom.min.js
│       ├── detect.app.js                Mobile-app detection for deeplinks
│       ├── bookmarklet.js
│       ├── server.js / server.min.js    Backend-driven JS
│       ├── style.min.css
│       ├── original.style.css           Source for style.min.css
│       ├── backend/{css,js}             Admin-only bundles
│       ├── frontend/{css,js,libs,fonts} Frontend libraries (CKEditor, Spectrum, …)
│       ├── fonts/
│       └── images/
│
├── storage/                Runtime mutable state
│   ├── app/                Misc app state (e.g. imports/, GeoLite2-City.mmdb)
│   ├── cache/              Phpfastcache "files" driver root
│   ├── languages/          i18n files: <lang>/{app,admin,api,email,sample}.php
│   ├── logs/               Monolog stream files: Log-MM-DD-YYYY.log
│   ├── plugins/            Plugin directories — plugin.php is entrypoint
│   │   ├── helloworld/     Sample plugin
│   │   └── index.php
│   └── themes/             Theme directories
│       ├── default/        Primary theme (referenced by appConfig('app.default_theme'))
│       └── the23/          Alt theme
│
└── vendor/                 🛑 Composer-managed — DO NOT EDIT
    ├── autoload.php
    ├── composer/
    └── <each package>/
```

## Entry points

| URL | Resolves to | What runs |
|---|---|---|
| Any HTTP request | `public/index.php` | Bootstraps framework, dispatches route |
| `/admin` (configurable) | `Admin\Dashboard@index` | Admin home |
| `/api/oauth/token` | `API\OAuth@token` | Public OAuth2 token endpoint |
| `/{alias}` | `Link@redirect` | The shortlink catch-all (LAST route) |
| `/u/{username}` | `Link@profile` | Public bio profile |
| `/qr/{id}` | `QR@generate` | Public QR rendering |
| `/sitemap.xml` | `Sitemap@index` | XML sitemap index |
| `/update` | `Update@index` | Installer/upgrader |
| `/webhook[/{provider}]` | `Webhook@index` | Payment provider webhooks |
| `/crons/*` | `Cron@*` | Token-protected scheduled jobs (call from external cron) |

## "Do not edit" zones

| Path | Why |
|---|---|
| `core/` | Proprietary framework. Licence forbids modification. Any patch you need belongs in a plugin (`storage/plugins/<x>/plugin.php`) hooking `Plugin::dispatch`/`register`. |
| `vendor/` | Composer-managed; regenerated on `composer install`. |
| `core/support/ORM.class.php` | This is a vendored copy of Idiorm; treat as third-party. |

## Where to add new code

| Need | Add here |
|---|---|
| New public route | `app/routes.php` — bottom of the relevant group, **never after the `/{alias}` catch-all** at line 723 |
| New admin route | Inside `Gem::group(appConfig('app.adminroute'), …)` block (lines 283-578) |
| New API endpoint | Inside the API group (lines 581-643); namespace `API\` |
| New controller | `app/controllers/[admin|api|user]/<X>Controller.php` — class name `X`, *not* `XController` (the autoloader appends `Controller.php` to the file name only) |
| New middleware | `app/middleware/<X>.php` — class `Middleware\<X>`, implement `handle(Request $request)` |
| New helper | `app/helpers/<X>.php` — namespace `Helpers\` |
| New model | `app/models/<X>.php` — extend `Core\Model`, set `public static $_table = DBprefix.'tablename'` |
| New payment gateway | `app/helpers/payments/<X>.php` + register in `Traits\Payments::processor()` |
| Cross-cutting hook | A plugin under `storage/plugins/<name>/plugin.php` using `Plugin::register('event.name', callable)` |
