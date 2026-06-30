# 01 — Project Overview

## What this is

**GemPixel Premium URL Shortener** — a commercial, self-hosted SaaS-style PHP application sold by GemPixel via CodeCanyon. It is a multi-tenant URL shortener with deep analytics and several adjacent products in the same install:

| Module | What it does |
|---|---|
| **Short links** | Create branded short links with custom alias, expiry, password gate, click cap, geo/device/language targeting, A/B rotators, UTM injection, deep-links to native apps. |
| **QR codes** | Stylised QR with custom modules, eyes, frames, logos, gradients; static (text/email/wifi/vcard/event/geo/crypto) or dynamic (linked to a short URL). |
| **Bio pages** ("link in bio") | Linktree-style profile pages with 40+ widget types (social, video, audio, PayPal, Spotify, Calendly, etc.). |
| **Click analytics** | Per-link stats by country, city, OS, browser, language, referrer; A/B test reporting; chart JSON feeds. |
| **Custom domains** | Bring-your-own domain pointed at the host; resolves to either a custom redirect, a bio profile, or a landing page. |
| **Teams** | Owner can invite members with a permission matrix; members switch context between their own account and the team. |
| **Subscriptions & billing** | Free + paid plans, trials, coupons, vouchers, taxes; 8 gateways: Stripe, Mollie, PayPal Standard, PayPal REST, Paddle Classic, Paddle Billing, PayStack, manual Bank. |
| **REST API** | OAuth2 + bearer-token API (42 endpoints) covering links, QR, channels, campaigns, domains, pixels, splash, overlay, plans, users, account. |
| **Affiliates** | Per-payment commission tracking, payout history, withdrawal requests. |
| **Content** | CMS pages, blog with categories, help center, FAQs — all admin-managed. |
| **Admin panel** | 242 admin routes across 30 controllers covering every artifact in the system. |

## Who runs it

A site operator (one customer / install) who runs the app on their own server and sells short-link / QR / bio features to *their* end-users. The system supports:

- **Public visitors** — browse marketing pages, click short links, view bio profiles.
- **Free users** — register, create capped links/QRs/bio pages.
- **Paid users** — subscribe to plans; plan JSON drives all feature gating (`Models\Plans`, `User::has($perm)`, `User::hasLimit($perm)`).
- **Team members** — operate within a team owner's quota with a permission subset.
- **Affiliates** — earn commission on referred subscriptions.
- **Admins / role-holders** — staff with `Models\Role` permissions (super-admin = `users.admin` column == `1`).

## Headline counts (this install)

- **~251 first-party PHP files** (`*.php` excluding `vendor/` and `storage/`).
- **~526 HTTP routes** in `app/routes.php` (735 lines).
- **~73 controllers** — root (15), admin (30), api (12), user (19).
- **5 models** — `User`, `Url`, `Plans`, `Role`, `Settings`.
- **14 middleware** classes.
- **~20 inferred DB tables** (no SQL/migration files ship with the source — schema is built by `core/DB.class.php` schema builder calls or imported by `SetupController`/`UpdateController`).
- **2 themes** under `storage/themes/` (`default`, `the23`).
- **1 sample plugin** at `storage/plugins/helloworld/`.
- **0 automated tests.**

## Product positioning

This is a *single-vendor commercial framework*: GemFramework is **not** distributed independently — it ships with this product. The licensing notice in every framework file forbids redistribution. Treat `core/` as a black box you can extend (plugins, hooks) but not modify.

## How users interact (high level)

1. **Marketing site / home** — `/` (`HomeController@index`) shows pricing, FAQ, contact, blog.
2. **Public shorten form** — `POST /shorten` (anonymous, rate-limited, captcha-gated).
3. **Click a short link** — `/{alias}` → `LinkController@redirect` → `Helpers\Gate::direct|splash|password|frame|overlay|media|profile` → final destination.
4. **Sign up / log in** — `/user/register`, `/user/login` (+ 2FA, social login).
5. **Dashboard** — `/user` (138 authed routes for links, campaigns, QR, bio, stats, teams, etc.).
6. **Pay** — `/checkout/{plan}/{type}` → chosen processor → webhook updates subscription.
7. **API** — `/api/oauth/token` (public) → `Authorization: Bearer …` against the 41 authed `/api/*` endpoints.
8. **Admin** — `/admin` (path configurable via `appConfig('app.adminroute')`).

The catch-all `GET|POST /{alias}` lives at `app/routes.php:723` and is the heart of the product.
