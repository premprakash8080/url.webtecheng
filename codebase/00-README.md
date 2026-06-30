# Second Brain — GemPixel Premium URL Shortener

A senior-engineer-grade reference for this codebase. Read top-to-bottom on first encounter; later, jump straight to the section you need.

> **Repo root:** `/Users/prem/Projects/Personal/url.webtecheng/`
> **This folder (`codebase/`):** documentation only — no executable code lives here.
> **Generated:** structural analysis based on a full read of `core/`, `app/`, `public/`, `storage/`, `config.php`, `app/routes.php`, all controllers, models, middleware, helpers, traits, and config files.

## Index

| # | File | What it covers |
|---|---|---|
| 01 | [overview.md](./01-overview.md) | What the product is, who uses it, headline features |
| 02 | [architecture.md](./02-architecture.md) | Layers, request lifecycle, bootstrap sequence |
| 03 | [folder-structure.md](./03-folder-structure.md) | Where everything lives, what to never edit |
| 04 | [framework-core.md](./04-framework-core.md) | GemFramework internals (Gem, DB, Auth, Model, View, …) |
| 05 | [routing.md](./05-routing.md) | Route DSL, all 526 routes grouped by area |
| 06 | [controllers.md](./06-controllers.md) | Every controller, its actions and dependencies |
| 07 | [models-database.md](./07-models-database.md) | The 5 models + inferred DB schema (~20 tables) |
| 08 | [middleware.md](./08-middleware.md) | 14 middleware classes — what they block, when |
| 09 | [helpers-traits.md](./09-helpers-traits.md) | Helper catalogue + trait inventory |
| 10 | [business-logic.md](./10-business-logic.md) | Shortlink redirect, link create, checkout, auth flows |
| 11 | [auth-permissions.md](./11-auth-permissions.md) | Sessions, 2FA, API keys, OAuth, roles, plan limits, teams |
| 12 | [api.md](./12-api.md) | Public REST API (42 routes) + OAuth2 |
| 13 | [payments.md](./13-payments.md) | 8 gateway integrations + webhook routing |
| 14 | [external-integrations.md](./14-external-integrations.md) | Every third-party service the app touches |
| 15 | [config-environment.md](./15-config-environment.md) | Constants, config files, DB-backed settings |
| 16 | [error-handling-logging.md](./16-error-handling-logging.md) | Monolog channels, error pages, plugin events |
| 17 | [frontend-assets.md](./17-frontend-assets.md) | Themes, public/static, View asset pipeline, CDN |
| 18 | [storage-cache-plugins.md](./18-storage-cache-plugins.md) | Storage dirs, cache layer, plugin loading |
| 19 | [conventions.md](./19-conventions.md) | Naming, autoloading, controller resolution rules |
| 20 | [tech-debt-risks.md](./20-tech-debt-risks.md) | Known bugs, tight coupling, security gotchas |
| 21 | [critical-files.md](./21-critical-files.md) | The ~30 most load-bearing files |

## One-paragraph summary

This is the **GemPixel Premium URL Shortener** — a commercial PHP application sold on CodeCanyon (current installed version `1.1` per `app/core.php`, installer reports `7.7`). It bundles a URL shortener, QR-code generator, "link in bio" pages, click analytics, custom domains, teams, an OAuth2-enabled REST API, and a subscription/billing system supporting 8 payment gateways. It runs on a proprietary in-house framework called **GemFramework**, which is a Laravel-shaped thin wrapper around FastRoute, Idiorm/Paris (the real ORM), PHPMailer, Phpfastcache, Monolog, and Defuse Crypto. PHP 7.4+, MySQL, Composer. ~251 first-party PHP files, ~526 HTTP routes, ~73 controllers across 4 namespaces (root, `Admin`, `API`, `User`).

## How to use this Second Brain

- **New to the codebase?** Read 01 → 02 → 03 → 04 → 05 in order.
- **Adding a feature?** 05 (routing), 06 (controller it belongs to), 07 (model/columns), 09 (existing helpers/traits to reuse).
- **Debugging a payment?** 13 → 10 (business-logic checkout section) → 16 (logs).
- **Touching auth?** 11 first, then 04 (`Auth.class.php` section).
- **Don't break anything?** 20 + 21.

## Critical "do not touch" reminders

- **`core/` and `vendor/`** — proprietary framework + composer-managed.
- **`config.php`** at root contains live-looking secrets (DB password, AuthToken, EncryptionToken). Rotating `AuthToken` invalidates all bcrypt hashes (it's used as a per-password salt suffix). Rotating `EncryptionToken` makes all Defuse-encrypted blobs unreadable.
- **`app/routes.php` line 723** — the `/{alias}` catch-all redirect. Adding any new root-level route AFTER it will be unreachable; before it must not collide.
