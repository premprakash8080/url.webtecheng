# 09 — Helpers & Traits

## Helpers (`app/helpers/`, namespace `Helpers\`)

The "helpers" folder is the app's utility belt — pure-static integration classes plus the link-display engine (`Gate`).

### `Helpers\App` (`App.php`)

Catch-all utility class. Used in nearly every controller and view. Key static methods:

- **Catalogs**: `pages()`, `pricingFaqs()`, `currency()`, `timezone()`, `languages()`, `pageCategories()`, `os()`, `browser()`, `domains()`, `redirects()`, `states()`, `langs()`, `fonts()`, `languagelist()`.
- **Plan & permissions**: `defaultPlan()` (used by `User::plan()` fallback), `features()` (the master feature/limit catalog).
- **Routing helpers**: `shortRoute($alias, $domain)` — builds the canonical short URL for an alias, respecting custom domains.
- **File ops**: `copyFolder($from, $to)`, `deleteFolder($path)`, `cropthumb`, `resizeTmp`, `resize`, `maxSize`, `parseSize`.
- **System checks**: `checkEncryption()` (called at top of `routes.php`), `checkDNS($domain)`, `isExtended()`, `newUpdate()`, `updateChangelog()`, `license()`, `loggedAs()`.
- **Rendering helpers**: `flag($code)`, `pixelName($type)`, `apiMethodColor`, `iframePolicy`, `metaData`, `share`, `themeConfig`, `isDark`, `cookieConsent`, `invertColor`, `formatNumber`, `tableSort`, `randomWord`.
- **Misc**: `possible($length)` (alias generation alphabet), `notifications()`, `ads()`, `rss($url)`, `userHistory($user)`, `delete($table, $id)`.

### `Helpers\AutoUpdate` (`Autoupdate.php`)

Pulls and applies version updates from `https://cdn.gempixel.com/updater`. Verifies an Envato CodeCanyon purchase key. `install()`, `check()`, `download($link)`, `extract()`, `update()`, `clean()` — uses cURL + `ZipArchive`.

### `Helpers\BioWidgets` (`BioWidgets.php`)

Massive widget registry for "link in bio" pages. Each widget type provides:

- `<type>Setup($block)` — admin/builder form HTML
- `<type>Save($request)` — persistence shape
- `<type>Block($block, $url)` — frontend renderer
- optionally `<type>Processor($request, $block)` and `<type>Delete($block)`

Widget types include: `tagline`, `heading`, `divider`, `text`, `link`, `whatsappcall`, `whatsapp`, `phone`, `spotify`, `itunes`, `paypal`, `tiktok`, `tiktokprofile`, `youtube`, `rss`, `image`, `newsletter`, `contact`, `faqs`, `vcard`, `product`, `html`, `opensea`, `twitter`, `soundcloud`, `facebook`, `instagram`, `typeform`, `pinterest`, `reddit`, `calendly`, `threads`, `googlemaps`, `opentable`, `eventbrite`, `snapchat`, `musiclink`, `linkedin`, `video`, `audio`, `pdf`, `intercom`, `tawkto`, `videoembed`, `tidio`.

Plus: `widgets()` (master catalog), `widgetsByCategory`, `socialPlatforms`, `render`, `processors`, `update`, `delete`, `isCountryAllowed`, `isLanguageAllowed`, `isScheduled`, `format`, `e`, `isPreview`, `generateTemplate`, `lists()`.

Uses `Traits\Links` for click stat updates.

### `Helpers\CDN` (`CDN.php`)

S3-compatible object storage abstraction. Providers: AWS S3, DigitalOcean Spaces, Wasabi, Backblaze B2, Contabo, custom S3 endpoint.

Methods: `load($name)`, `providers($type)`, `factory()`, `in($bucket)`, `upload($key, $file, $type)`, `uploadRaw($key, $data, $type)`, `signed($key, $expire)`, `get($key)`, `delete($key)`, `enabled()`, `signatureV2`, `signatureV4`, `signatureV4Array`, `httpSigned`, `host()`, `url($config)`.

Registered with `Request::setCDN(\Helpers\CDN::class)` during `Settings::getSettings()` if CDN is enabled — then `Request::move()` automatically uploads to S3 instead of local disk.

### `Helpers\Captcha` (`Captcha.php`)

Provider-agnostic captcha. `systems($key)` (catalog), `display`, `validate`. Provider-specific: `reCaptchaV2Display`, `reCaptchaV3Display`, `reCaptchaValidate`, `hCaptchaDisplay`, `hCaptchaValidate`, `turnstileDisplay`, `turnstileValidate`, `altchaDisplay`, `altchaChallenge`, `altchaValidate`.

### `Helpers\DeepLinks` (`DeepLinks.php`)

Converts standard web URLs into native app URI schemes so mobile users open the app. `convert($url)` dispatches to per-provider converters: YouTube, Amazon, Facebook, Instagram, Twitter, Spotify, WhatsApp, TikTok, Snapchat, Apple Music, Pinterest, LinkedIn, Walmart, Netflix, Twitch, Zoom, Wolt, Yelp, Grubhub, Airbnb, AliExpress. `list()` returns the catalog.

### `Helpers\Emails` (`Emails.php`)

Transactional email composer. `setup()` reads the active mailer settings and dispatches via `Core\Email::factory('mailgun'|'sendgrid'|'postmark'|'mailchimp'|'smtp', $config)`. Templates: `approveURL`, `renewEmail`, `registered`, `reset`, `activate`, `passwordChanged`, `affiliatePayment`, `invite`, `canceled`, `remind`, `notifyAdmin`, `reset2FA`, `newip`.

### `Helpers\Events` (`Events.php`)

Fluent app-event logger. `Events::for('login')->user($id)->log($data)` writes a row to `appevents` when `config('userlogging')` is on. Methods: `for($type)`, `user($id)`, `plan($id)`, `log($data)`.

### `Helpers\FacebookAuth` (`FacebookAuth.php`)

Facebook OAuth client. `redirectURI($request)`, `getAccessToken($request)`, `getUser()`. Talks to `graph.facebook.com/v20.0`.

### `Helpers\Gate` (`Gate.php`)

The **link-display engine** — renders the page a user sees after clicking a short URL. All static.

| Method | When |
|---|---|
| `inactive($url)` | Deactivated link |
| `disabled($url)` | Admin-disabled |
| `expired($url)` | Expired by date or click cap |
| `password($url)` | Password gate — accepts password, then re-dispatches |
| `direct($url, $user)` | Plain `Location:` redirect (the common path) |
| `frame($url, $user)` | Render destination inside an iframe with site chrome |
| `splash($url, $user)` | Built-in splash/intermediate page |
| `custom($url, $splash, $user)` | Custom splash template |
| `overlay($url, $user)` | Click-overlay popup |
| `embed(array $data)` | Embed view (video/audio/etc.) |
| `media($url, $media, $user)` | Native media player |
| `profile($profile, $user, $url)` | Bio profile renderer (used for `/u/{username}` and bio-mapped custom domains) |
| `bundle($profile, $bundle, $user)` | Bio sub-bundle |
| `deeplink($url, $user, $device, $deeplink)` | App deeplink page |
| `injectPixels($pixels, $user)` (protected) | Drop pixel snippets into the rendered page |

### `Helpers\GoogleAuth` (`GoogleAuth.php`)

Google OAuth client. `redirectURI`, `getAccessToken`, `getUser`. Talks to `googleapis.com/userinfo/v2/me`.

### `Helpers\GoogleTranslate` (`GoogleTranslate.php`)

Unofficial Google Translate scraper (Statickidz library, dropped in here). `translate($source, $target, $text)`.

### `Helpers\Permissions` (`Permissions.php`)

Convenience wrapper around role permissions. `can($p)`, `canAny([$p])`, `canAll([$p])`, `getRole()`, `getRoleName()`.

### `Helpers\QR` (`QR.php`)

QR factory + payload builders. `factory($request, $size, $margin)` returns either `Helpers\QrGd` (no Imagick) or `Helpers\QrImagick` (Imagick available). `types($type)` is the catalog. Payload builders: `typeText`, `typeLink`, `typeEmail`, `typePhone`, `typeSms`, `typeSmsonly`, `typeWhatsapp`, `typeVcard`, `typeStaticvcard`, `typeOauth`, `typeWifi`, `typeGeo`, `typeCrypto`, `typeFile`, `typeEvent`, `typeApplication`.

### `Helpers\QrGd` (`QrGd.php`)

GD-backed QR renderer using `endroid/qr-code`. Methods: `withLogo`, `errorCorrection`, `format` (png/eps/pdf/svg), `color`, `string`, `extension`, `create($output, $file)`.

### `Helpers\QrImagick` (`QrImagick.php`)

Imagick-backed QR renderer using `bacon/bacon-qr-code` plus custom modules/eyes/frames from `Helpers\Qr\*`. Adds: gradient fills, custom module shapes (heart, hexagon, diamond, …), custom eye shapes, decorative frames.

### `Helpers\Slack` (`Slack.php`)

Slack OAuth + slash-command app. `process()`, `validate($signing_secret)` (request signature check), `error()`, `redirect()`, `generateAuth()`, `manifest()`, `http($endpoint, $data)`. Used by `WebhookController@slack` for the `/shorten` command and OAuth install flow.

### `Helpers\Payments\` (`app/helpers/payments/`)

See [13-payments.md](./13-payments.md) for full detail. Eight gateway classes: `Bank`, `Mollie`, `Paddle`, `PaddleBilling`, `PayStack`, `Paypal`, `PaypalApi`, `Stripe` — plus `IpnListener` (PayPal classic IPN helper).

### `Helpers\Qr\` (`app/helpers/qr/`)

QR rendering primitives for Imagick mode. 18 classes:
- **EyeInterface** implementations: `Butterfly`, `Circle`, `Diamond`, `Eye`, `EyeGenerator`, `EyeInverted`, `Hexagon`, `RoundedCornerSquare`, `RoundedSquare`.
- **ModuleInterface** implementations: `DiamondModule`, `DistortedSquareModule`, `HeartModule`, `HexagonModule`, `LongRoundedModule`, `SquareSpaceModule`, `TallRoundedModule`, `ThreeDModule`.
- `FrameGenerator` — decorative QR frames (window, popup, camera, phone, arrow, labeled, roundedlines).

---

## Traits (`app/traits/`, namespace `Traits\`)

### `Traits\Links` (`Links.php`) — THE link engine

Used by 13 callers (controllers + helpers + middleware). The most important trait in the app.

| Method | Purpose |
|---|---|
| `getURL($request, $alias)` | Look up a short URL by alias (custom-domain aware via `BINARY` match) |
| `createLink($request, $user, $output)` | Create a new short link — handles alias gen, custom alias, validation, all options (geo/device/lang targeting, deeplink, pixels, channels, campaigns) |
| `updateLink($request, $link, $user)` | Update an existing link |
| `updateStats($request, $url, $user)` (private) | Write a `stats` row + optional `zapview` webhook ping |
| `deleteLink($id, $user)` | Delete + cleanup |
| `validate($url)` (protected) | Compose safety/blacklist/anti-phish checks |
| `domainBlacklisted($url)` | Static blacklist check |
| `wordBlacklisted($url)` | Profanity / word blacklist |
| `safe($url)` | Google Web Risk API check |
| `phish($url)` | Google Web Risk (PHISH category) check |
| `virus($url)` | VirusTotal v2 API check |
| `isMedia($url)` | Media URL detector |
| `validateDomainNames($domain, $user, $return)` | Custom domain validation |
| `isSelf($url)` (private) | Detect self-shortening |
| `aliasReserved($string)` (private) | Reserved-word check (admin, api, login, etc.) |
| `aliasPremium($alias)` | Premium-alias check (length-based) |
| `slug($string)` | Slug helper |
| `alias()` (protected) | Auto-generate a random alias using `appConfig('app.aliasformat')` |
| `addHistory($result)` (protected) | Append to a per-request action log |

**Used by:** `CheckDomain` middleware, root `LinkController`, root `StatsController`, root `CronController`, root `WebhookController`, all admin/api/user controllers that touch links, `Helpers\BioWidgets` (click stats on bio link clicks).

### `Traits\Payments` (`Payments.php`)

Central payment-gateway registry. **Single method:** `processor($type = null, $action = null)`.

- `processor()` → full registry array.
- `processor('stripe')` → just Stripe's entry.
- `processor('stripe', 'payment')` → `[\Helpers\Payments\Stripe::class, 'payment']` — a callable pair.

Consumers then `call_user_func_array($callable, $args)` to invoke. Registry includes `stripe`, `mollie`, `paypal`, `paypalapi`, `paddle`, `paddlebilling`, `paystack`, `bank` — plus anything registered via the `payment.extend` plugin hook.

**Used by:** `SubscriptionController`, `WebhookController`, `User\AccountController`, `Admin\Membership`, `Admin\Settings`, `Admin\Plans`, `Admin\Coupons`, `Admin\Tax`, `Admin\Vouchers`.

### `Traits\Pixels` (`Pixels.php`)

Tracking pixel registry + injection. `pixels($type, $action)` (catalog), `validate($type, $tag)`, `display($type, $tag)`. Per-vendor validators (`fbRule`, `gaRule`, `gtmRule`, `tiktokRule`, …) and injectors (`fbpixel`, `gtmpixel`, `gapixel`, …).

Supported vendors: Facebook Pixel, Google Ads, LinkedIn Insight, X/Twitter Pixel, AdRoll, Quora, GTM, GA4, Pinterest, Snapchat, Reddit, Bing UET, TikTok.

**Used by:** `PageController`, `User\PixelsController`, `API\PixelsController`.

### `Traits\Overlays` (`Overlays.php`)

Overlay/CTA catalog + renderers. `types($type, $action)`, `contactView`, `imageView`, `messageView`, `pollView`, `newsletterView`, `couponView`.

**Used by:** `User\OverlayController`.

### `Traits\Teams` (`Teams.php`)

Team-member permission catalog. Single method: `permissions()` — returns the permission groups available to team-member roles. The actual permission check happens via `User::teamPermission($p)`.

**Used by:** `User\TeamsController`.

---

## How to know which to use

| Need | Reach for |
|---|---|
| Look up a short link | `\Core\DB::url()->where(...)` or `Models\Url::recent()` |
| Create a short link | `Traits\Links::createLink()` — never write your own |
| Render the click landing | `Helpers\Gate::*` — `direct`, `splash`, `password`, `profile`, … |
| Charge a card | `Traits\Payments::processor($gateway, 'payment')` |
| Send mail | `Helpers\Emails::<template>(...)` or `Core\Email::factory(...)` |
| Generate a QR | `Helpers\QR::factory($req, $size)->format('svg')->color(...)->create($out, $file)` |
| Inject tracking pixels | `Traits\Pixels::display($type, $tag)` |
| Check a plan limit | `$user->has($perm)` or `$user->hasLimit($perm)` |
| Check a role permission | `Helpers\Permissions::can($perm)` |
| Upload a file | `$request->move($key, $directory)` — auto-routes to CDN if enabled |
