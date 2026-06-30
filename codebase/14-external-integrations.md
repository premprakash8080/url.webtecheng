# 14 — External Integrations

Every third-party service this app talks to, and where it's wired.

## Payment gateways

| Service | Class | Endpoint(s) |
|---|---|---|
| Stripe | `Helpers\Payments\Stripe` | `api.stripe.com` (via `stripe/stripe-php`) |
| Mollie | `Helpers\Payments\Mollie` | `api.mollie.com` (via `mollie/mollie-api-php`) |
| PayPal Classic (IPN) | `Helpers\Payments\Paypal` + `IpnListener` | `paypal.com/cgi-bin/webscr` + sandbox |
| PayPal REST | `Helpers\Payments\PaypalApi` | `api.paypal.com` / `api.sandbox.paypal.com` (via `paypal/rest-api-sdk-php`) |
| Paddle Classic | `Helpers\Payments\Paddle` | `vendors.paddle.com`, `checkout.paddle.com` |
| Paddle Billing | `Helpers\Payments\PaddleBilling` | `api.paddle.com` |
| PayStack | `Helpers\Payments\PayStack` | `api.paystack.co` |

See [13-payments.md](./13-payments.md) for details.

## Email transports

| Driver | Class | Config |
|---|---|---|
| SMTP | `Core\Email` (PHPMailer) | `smtp` settings |
| PHP `mail()` | `Core\Email` fallback | — |
| Sendmail | `Core\Email::via('sendmail')` | — |
| Mailgun | `Core\Support\Mailgun` | `api.mailgun.net/v3/<domain>/messages` |
| SendGrid | `Core\Support\Sendgrid` | `api.sendgrid.com/v3/mail/send` |
| Postmark | `Core\Support\Postmark` | `api.postmarkapp.com/email` |
| Mailchimp (Mandrill) | `Core\Support\Mailchimp` | `mandrillapp.com/api/1.0` |

Driver selection: `Email::factory($transport, $config)` where `$transport` matches a key in `appConfig('app.maildrivers')`.

`Helpers\Emails::setup()` is the central setup helper used by every transactional template (`registered`, `reset`, `activate`, `passwordChanged`, `affiliatePayment`, `invite`, `canceled`, `remind`, `notifyAdmin`, `reset2FA`, `newip`).

## Authentication / Identity

| Service | Class | Endpoint(s) |
|---|---|---|
| Google OAuth | `Helpers\GoogleAuth` | `accounts.google.com/oauth`, `googleapis.com/userinfo/v2/me` |
| Facebook OAuth | `Helpers\FacebookAuth` | `graph.facebook.com/v20.0` |
| Twitter/X OAuth | `Users::loginWithTwitter` via `abraham/twitteroauth` | api.twitter.com |
| Slack OAuth + slash commands | `Helpers\Slack` | `slack.com/api`, `slack.com/oauth/v2/authorize` |
| Google Authenticator (2FA) | `sonata-project/google-authenticator` | (offline TOTP) |

## Captcha / bot mitigation

| Provider | Class | Endpoint(s) |
|---|---|---|
| Google reCAPTCHA v2 + v3 | `Helpers\Captcha::reCaptcha*` | `google.com/recaptcha/api/siteverify`, `google.com/recaptcha/api.js` |
| hCaptcha | `Helpers\Captcha::hCaptcha*` | `hcaptcha.com/siteverify` |
| Cloudflare Turnstile | `Helpers\Captcha::turnstile*` | `challenges.cloudflare.com/turnstile/v0/...` |
| Altcha | `Helpers\Captcha::altcha*` + `altchaorg/altcha` | `cdn.jsdelivr.net/npm/altcha` |
| Crawler/bot block | `Middleware\BlockBot` via `jaybizzle/crawler-detect` | (offline) |

## Link safety

| Service | Used in | Endpoint(s) |
|---|---|---|
| Google Web Risk (safe + phish) | `Traits\Links::safe()`, `phish()` | `webrisk.googleapis.com/v1/uris:search?key=` |
| VirusTotal v2 | `Traits\Links::virus()` | `virustotal.com/vtapi/v2/url/report?apikey=` |

Triggered on link creation when the user has the corresponding feature enabled.

## Geo IP

| Service | Class | Endpoint(s) / Data |
|---|---|---|
| MaxMind GeoLite2 | `Core\Request::country()` via `maxmind-db/reader` | `storage/app/GeoLite2-City.mmdb` (bundled) |
| API driver | `Core\Request::country()` over `Core\Http` | configured via `appConfig('app.geodriver')` = `api` |
| Custom callable | `Core\Request::country()` | configured via `appConfig('app.geodriver')` = `custom` |

## Storage / CDN

| Service | Class | Endpoint(s) |
|---|---|---|
| AWS S3 | `Helpers\CDN` | `s3.<region>.amazonaws.com` |
| DigitalOcean Spaces | `Helpers\CDN` | `<bucket>.<region>.digitaloceanspaces.com` |
| Wasabi | `Helpers\CDN` | `s3.<region>.wasabisys.com` |
| Backblaze B2 | `Helpers\CDN` | `<bucket>.s3.<region>.backblazeb2.com` |
| Contabo | `Helpers\CDN` | (S3-compatible) |
| Custom S3 endpoint | `Helpers\CDN` | (S3-compatible) |

Active when `settings.cdn.enabled = true`. `Request::move()` automatically routes uploads through `CDN::upload($key, $path, $type)`.

## Tracking pixels (rendered to end-users' browsers)

Code in `Traits\Pixels` validates the pixel tag and emits the vendor's JS snippet on Gate-rendered pages. The pixels load against:

- Facebook Pixel — `connect.facebook.net`
- Google Ads / GA / GTM — `googletagmanager.com`, `google-analytics.com`
- LinkedIn Insight — `snap.licdn.com`
- X/Twitter Pixel — `static.ads-twitter.com`
- AdRoll — `s.adroll.com`
- Quora — `q.quora.com`
- Pinterest — `s.pinimg.com`
- Snapchat — `sc-static.net`
- Reddit — `redditstatic.com`
- Microsoft (Bing) UET — `bat.bing.com`
- TikTok Pixel — `analytics.tiktok.com`

## Deep links (mobile native app URI schemes)

`Helpers\DeepLinks` rewrites web URLs to native schemes when a clicked link has the deeplink feature enabled. No HTTP traffic — just URL transformation.

Apps targeted: YouTube, Amazon, Facebook, Instagram, X/Twitter, Spotify, WhatsApp, TikTok, Snapchat, Apple Music, Pinterest, LinkedIn, Walmart, Netflix, Twitch, Zoom, Wolt, Yelp, Grubhub, Airbnb, AliExpress.

## QR code rendering

| Service | Class | Library |
|---|---|---|
| GD QR (no Imagick) | `Helpers\QrGd` | `endroid/qr-code` |
| Imagick QR (stylized) | `Helpers\QrImagick` + `Helpers\Qr\*` | `bacon/bacon-qr-code` |

Both libraries vendored via Composer.

## Bio-page widget embeds (external services embedded on bio pages)

`Helpers\BioWidgets` builds HTML that loads embeds from:

Spotify · iTunes · PayPal Donate · TikTok · YouTube · RSS feeds · X/Twitter · SoundCloud · Facebook · Instagram · Typeform · Pinterest · Reddit · Calendly · Threads · Google Maps · OpenTable · Eventbrite · Snapchat · music-link aggregators · LinkedIn · OpenSea · Intercom · Tawk.to · Tidio.

These are rendered into the user's bio page; the app itself doesn't initiate HTTP calls to them.

## Translation (admin auto-translate feature)

`Helpers\GoogleTranslate` — unofficial Google Translate scraper. Endpoint `translate.googleapis.com`. Used by `Admin\Languages@automatic` to bulk-translate language files.

## Self-updater

`Helpers\AutoUpdate` — pulls versions from `https://cdn.gempixel.com/updater` (constant `serverURL`). Requires an Envato CodeCanyon purchase key. Used by `Admin\Dashboard@update`.

## Outgoing webhooks initiated by the app

| Hook | Where | Purpose |
|---|---|---|
| `user.zapview` | `Traits\Links::updateStats` (every click) | Per-user Zapier-style click webhook |
| `config('saleszapier')` | `SubscriptionController@process` | Post-checkout sale notification |
| Plugin `payment.success` listeners | `WebhookController@index` | Plugin hook — apps register here |
| Slack `response_url` | `WebhookController@slack` | Async response to a slow `/shorten` slash command |

## All Composer packages

From `composer.json`:

| Package | Used for |
|---|---|
| `abraham/twitteroauth` | Twitter/X OAuth login |
| `altcha-org/altcha` | Altcha captcha |
| `defuse/php-encryption` | Cookie/session encryption (`Helper::encrypt/decrypt`) |
| `endroid/qr-code` (4.6.1) | GD QR rendering |
| `jaybizzle/crawler-detect` | Bot detection (`BlockBot`) |
| `maxmind-db/reader` | GeoIP lookups |
| `mollie/mollie-api-php` | Mollie payments |
| `monolog/monolog` ^2.0 | Logging (`GemError`) |
| `nikic/fast-route` | Routing |
| `paypal/rest-api-sdk-php` | PayPal REST |
| `phpfastcache/phpfastcache` ^8.0 | Cache (`Helper::cache*`) |
| `phpmailer/phpmailer` ^6.1 | SMTP/sendmail email |
| `setasign/fpdf` ^1.8 | PDF generation (invoices, etc.) |
| `sonata-project/google-authenticator` | 2FA TOTP |
| `stripe/stripe-php` ^15.10 | Stripe payments |

Also vendored under `core/support/` (re-namespaced, not via Composer):
- **Idiorm** (`ORM.class.php`) — the actual ORM.
- **Paris** semantics in `core/Model.class.php`.
- **bacon/bacon-qr-code** — used by `QrImagick`; via Composer.
