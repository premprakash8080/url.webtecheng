# 17 — Frontend Assets & Themes

## Webroot layout

`public/` is the docroot. **Never put server-side PHP outside `public/` that needs direct hit** — the rewrite rules block it.

```
public/
├── index.php                     Bootstraps the framework + dispatches
├── .htaccess (none here)         Root .htaccess handles rewrite
├── web.config                    IIS rewrite rules
├── favicon.ico
├── robots.txt                    (admin-editable via Admin\Tools@tool_robots)
├── googledcba9e6f0ac9898d.html   Google Search Console verification
│
├── content/                      USER-GENERATED uploads (served as /content/*)
│   ├── avatar/                   User avatars
│   ├── blog/                     Blog post images
│   ├── files/                    QR file payloads
│   ├── images/                   Generic uploaded images
│   ├── profiles/                 Bio profile assets
│   ├── qr/                       Generated QR SVGs/PNGs (cached)
│   └── variables.css             Per-install CSS variables (admin-editable)
│
└── static/                       SITE-WIDE static assets (served as /static/*)
    ├── app.js / app.min.js       Dashboard JS
    ├── bio.js / bio.min.js       Bio page renderer JS
    ├── biopages.css / .min.css
    ├── bootstrap.min.css / .rtl  Bootstrap 5
    ├── bundle.pack.js            Pre-compiled dashboard bundle
    ├── webpack.pack.js           Pre-compiled webpack bundle
    ├── Chart.min.js              Charts.js (analytics)
    ├── charts.js / charts.min.js
    ├── content-style.css / .min.css
    ├── crop.js / crop.min.js     Cropper.js wrapper
    ├── custom.js / custom.min.js
    ├── detect.app.js             Mobile-app detection for deeplinks
    ├── bookmarklet.js            Browser bookmarklet shortener
    ├── server.js / server.min.js Backend-driven JS
    ├── style.min.css / original.style.css
    ├── backend/{css,js}          Admin-only bundles
    ├── frontend/{css,js,libs,fonts}  Frontend libs (CKEditor, Spectrum, …)
    ├── fonts/
    └── images/
```

## Helpers that emit asset URLs

| Helper | Output |
|---|---|
| `assets('frontend/css/style.css')` | `CDNASSETS . '/' . $file` if `CDNASSETS` defined, else `<base_url>/static/<file>` |
| `uploads('avatar/abc.jpg', 'avatar')` | `CDNCUSTOMURL`-prefixed if set, else `appConfig('app.storage')[avatar]`, else `<base_url>/content/<file>` |

Use these everywhere — never hardcode `/static/...` or `/content/...`. Both `assets` and `uploads` are global helpers defined in `core/functions/helpers.php`.

## Frontend library delivery (`app/config/cdn.php`)

When `USECDN = true`, third-party JS/CSS libraries (CKEditor, Summernote, Ace, Spectrum, Cropper, …) load from public CDNs per their entry in `app/config/cdn.php`. Otherwise they load from `public/static/frontend/libs/`. The list is enumerated in [15-config-environment.md](./15-config-environment.md#appconfigcdnphp).

## Theme system

Themes live under `storage/themes/`. This install has two:

- `default/` — primary theme (default value of `appConfig('app.default_theme')`).
- `the23/` — alt theme.

### Theme structure

```
storage/themes/<theme>/
├── config.json               metadata + include + child
├── header.php                rendered before main template (View::render)
├── footer.php                rendered after main template
├── theme.php                 (typical) — included by Plugin::preload via config.json:include
├── errors/
│   ├── 404.php               override 404 page
│   ├── 500.php
│   └── …
├── layouts/                  layout templates (auth, public, etc.)
├── home/                     home page templates
├── user/                     user-dashboard templates
├── admin/                    admin templates
└── …
```

### Theme inheritance

Themes can fall back to other themes via `config.json:child`:

```json
{ "child": true }          // fall back to appConfig('app.default_theme')
{ "child": "default" }     // fall back to a named theme
{ "child": false }         // no fallback
```

`View::template($name)` resolves:
1. `storage/themes/<active_theme>/<name>.php`
2. If missing, `storage/themes/<default_theme>/<name>.php`
3. If `child` directive applies, follow that chain

### Active theme

Controlled by setting `theme` in the DB `settings` table. Switched via `Admin\Themes@activate`.

## Rendering pipeline

```php
// Full render (header + template + footer)
View::render('home.index', ['data' => $foo]);
// or via the global helper:
render('home.index', ['data' => $foo]);

// Template only (no header/footer)
view('partials.button', ['label' => 'OK']);

// Layout-inheritance pattern
// child template:
<?php View::with('layouts.public', ['title' => 'Hi'])->extend('layouts.public'); ?>
<h1>My page</h1>
<?php
// layout template (layouts/public.php):
?><html><body><?php section(); /* outputs the child */ ?></body></html>
```

Inside templates these globals are useful:
- `e($string, $count, $vars)` / `ee(...)` — translate (and echo).
- `route($name, $param)` — reverse-route URL.
- `assets($file)`, `uploads($file, $storage)` — asset URLs.
- `csrf()` — hidden CSRF input.
- `meta()` — emit OG/Twitter/canonical meta.
- `block('header')`, `push($content, $type)` — inject scripts/styles.
- `request()->{var}` / `old('name')` — form repopulation.
- `Auth::user()` / `user()` — current user.
- `message()` — flash messages.
- `pagination(...)`, `timeago(...)` — UI helpers.

## CSS variables / branding

Admin-editable branding lives in `public/content/variables.css` — a CSS file with custom properties. Edited via `Admin\Themes@custom` / `customUpdate`.

## Asset compilation

`View::compile(array $sources, $destination, $linkonly)` concatenates CSS or JS files into a single destination, with each line cache-busted via `?mtime`. Used to build the bundled JS/CSS at deploy time. Production already ships with `.min` and `.pack` bundles — `View::compile` is more for live editor scenarios in the admin panel.

`View::createFile($content, $destination)` writes a minified version (strips comments + whitespace).

## CDN strategy

There are **two CDN layers** that should not be confused:

### Layer A — Frontend libraries CDN

| Toggle | `USECDN` constant + `app/config/cdn.php` |
| Purpose | Serve CKEditor, Ace, Spectrum, Cropper, etc. from public CDNs |
| Implementation | `View::assets()` consults `cdn.php` library map |

### Layer B — Object-storage CDN

| Toggle | `settings.cdn.enabled = true` + provider config |
| Purpose | Serve user uploads from S3-compatible storage |
| Implementation | `Helpers\CDN` registered with `Request::setCDN()` at bootstrap; `Request::move()` uploads to CDN; `View::uploads()` returns the CDN URL |
| Providers | AWS S3, DO Spaces, Wasabi, Backblaze B2, Contabo, custom S3 |

## Admin theme editor

`Admin\Themes` controller exposes:
- `index` — list installed themes, mark active.
- `clone` — copy a theme directory under a new name.
- `editor` — live-edit theme template files (`update` saves).
- `upload` — install a theme from `.zip`.
- `settings` — per-theme key/value settings.
- `custom` / `customUpdate` — custom CSS overrides.
- `menu` / `menuUpdate` — site menu configuration.
- `activate` / `delete`.

## Frontend forms

Standard pattern in templates:

```php
<form method="POST" action="<?= route('login.auth') ?>">
    <?= csrf() ?>
    <input type="email" name="email" value="<?= old('email') ?>">
    <input type="password" name="password">
    <button>Login</button>
</form>
```

POST automatically gets CSRF middleware (see [05-routing.md](./05-routing.md)), so `csrf()` is mandatory.

For AJAX form submissions, fetch `csrf_token()` and send as `_token` in the body.
