# 06 — Controllers

The app has **~73 controllers** in 4 namespaces. File naming convention: class is `Foo`, file is `FooController.php`. The autoloader (`core/functions/core.php`'s `autoloadController`) maps the class name to `CONTROLLER/<lowercase-namespace>/<Name>Controller.php`.

Quick legend: "★" = controller you should know about; deep notes in [10-business-logic.md](./10-business-logic.md).

## Root-level controllers (15)

| Class | File | Purpose | Public actions |
|---|---|---|---|
| `Home` | `HomeController.php` | Public home page + serves bundled JS/CSS packs from `appConfig('cdn')` | `index`, `packed` |
| `Blog` | `BlogController.php` | Public blog (list, category, search, single) | `index`, `category`, `search`, `post` |
| `Help` | `HelpController.php` | Help/knowledge-base frontend | `index`, `search`, `category`, `single` |
| `Page` | `PageController.php` | CMS pages, contact, report, FAQ/API/affiliate/QR/bio landing, consent | `index`, `contact`, `contactSend`, `report`, `reportSend`, `faq`, `api`, `affiliate`, `qr`, `bio`, `consent` |
| ★ `Link` | `LinkController.php` | URL-shortener core — create, redirect, edit, delete, QR, profile, verify | `shorten`, `redirect`, `image`, `icon`, `qr`, `qrDownload`, `delete`, `deleteMany`, `archiveSelected`, `unarchiveSelected`, `publicSelected`, `privateSelected`, `edit`, `update`, `addtocampaign`, `bookmark`, `scriptjs`, `fullpage`, `quick`, `campaign`, `campaignList`, `profile`, `reset`, `verify` |
| `QR` | `QRController.php` | Public QR rendering: SVG/PNG generation, download, public QR builder | `generate`, `download`, `generateqr` |
| `Stats` | `StatsController.php` | Per-link click analytics (data tables + chart JSON feeds) | `simple`, `index`, `dataClicks`, `countries`, `dataCountries`, `dataCities`, `platforms`, `dataPlatforms`, `browsers`, `dataBrowsers`, `languages`, `dataLanguages`, `referrers`, `abtesting`, `activity` |
| ★ `Users` | `UsersController.php` | All authentication: login/2FA/register/reset/social/SSO/activate/invite | `login`, `loginAuth`, `login2FA`, `login2FAValidate`, `login2FARecover`, `reset2FA`, `register`, `registerValidate`, `forgot`, `forgotSend`, `reset`, `resetChange`, `activate`, `invited`, `acceptInvitation`, `logout`, `loginWithFacebook`, `loginWithTwitter`, `loginWithGoogle`, `sso`, `return`, `verifyEmail`, `unsubscribe` |
| ★ `Subscription` | `SubscriptionController.php` | Public pricing + checkout + payment processing | `pricing`, `checkout`, `process`, `coupon`, `tax`, `redeem` |
| `Server` | `ServerController.php` | Misc API: contact, subscribe, vote, deeplink | `contact`, `subscribe`, `vote`, `deeplink` |
| ★ `Webhook` | `WebhookController.php` | Payment-provider webhook dispatcher + Slack `/shorten` command | `index`, `slack`, `ipn` |
| ★ `Cron` | `CronController.php` | Token-protected scheduled jobs | `user`, `data`, `urls`, `remind`, `imports` |
| `Sitemap` | `SitemapController.php` | XML sitemap index + child sitemaps | `index`, `site`, `links`, `bio`, `url` (helper) |
| `Update` | `UpdateController.php` | Self-update + DB migrations | `index`, `extracorrections`, `importFaqs`, `migrateTeams`, `migratepixels` |
| `Setup` | `SetupController.php` | First-run installer wizard | `__construct` (dispatch), `stepvalidate`, `stepdatabase`, `stepuser` |

## Admin controllers (30) — namespace `Admin\`

| Class | Purpose |
|---|---|
| `Admin\Dashboard` | Admin home, global search, broadcast email, version check, php-info, cron-log viewer |
| `Admin\Stats` | Link/user/click analytics dashboards, geo map, memberships, subscriptions, payments |
| `Admin\Plans` | Subscription plans CRUD + remote sync + enable toggle |
| `Admin\Membership` | Active subscriptions, payment ledger, invoice viewer |
| `Admin\Finance` | Finance overview |
| `Admin\Coupons` | Discount coupon CRUD (pro-gated) |
| `Admin\Vouchers` | Pro vouchers CRUD + bulk codes generator |
| `Admin\Tax` | Per-country tax rate CRUD (pro-gated) |
| `Admin\Links` | Global link admin: lists, abuse reports, blacklists, bulk delete/enable/disable, import |
| `Admin\Users` | Massive surface: CRUD, ban, login-as, testimonials, plan changes, role mgmt, team mgmt, login activity |
| `Admin\Roles` | Roles + permissions CRUD, assign/remove users |
| `Admin\Verifications` | KYC requests review/process |
| `Admin\Bio` | Browse, delete, toggle, reassign bio profiles |
| `Admin\BioThemes` | Bio-page theme templates CRUD |
| `Admin\Qr` | QR browse/delete/reassign |
| `Admin\QrTemplates` | Shareable QR design templates CRUD |
| `Admin\Pages` | Static CMS pages CRUD |
| `Admin\Blog` | Blog posts + categories CRUD |
| `Admin\Domains` | Custom domain CRUD + activation states |
| `Admin\Faqs` | FAQ items + categories CRUD |
| `Admin\Affiliates` | Affiliate accounts, payments, withdrawals, settings |
| `Admin\Ads` | Ad slots CRUD |
| `Admin\Themes` | Theme manager: clone/delete/activate, upload, settings, editor, custom CSS, menu |
| `Admin\Plugins` | Plugin browser/installer/activator + marketplace |
| `Admin\OAuth` | OAuth2 client (app) management + per-app token list |
| `Admin\Settings` | Read/write global settings, checklist, per-tab config, smtp/CDN verify and sync |
| `Admin\Languages` | List/install/upload/edit/sync/auto-translate languages |
| `Admin\Tools` | Maintenance: action dispatcher, data tools, backup/restore, clear cache, robots.txt |
| `Admin\EmailManager` | Outgoing mail config + email template CRUD + test send |
| `Admin\Notifications` | Site-wide admin notifications (toast/banner) CRUD |
| `Admin\Integrations` | **EMPTY FILE** (1 byte; placeholder) |

## API controllers (12) — namespace `API\`

| Class | Purpose |
|---|---|
| `API\OAuth` | OAuth2 server endpoints: authorize, proceed, token |
| `API\Index` | API root index |
| `API\Account` | Authenticated account read/update |
| ★ `API\Links` | REST CRUD for short links — list, single, create, update, delete |
| `API\QR` | REST CRUD for QR codes |
| `API\Domains` | REST CRUD for user custom domains |
| `API\Campaigns` | REST CRUD for campaigns + assign links |
| `API\Channels` | REST CRUD for channels + assign items |
| `API\Splash` | REST list of splash pages |
| `API\Overlay` | REST list of overlays |
| `API\Pixels` | REST CRUD for tracking pixels |
| `API\Plans` | REST list plans + subscribe user (admin-only) |
| `API\Users` | Admin-only REST: list, single, create, delete, generate login token |

Each API controller's constructor enforces auth: usually `Auth::ApiUser()->keyCan('<scope>')` (returns 403 JSON on failure), with admin-only controllers checking `Auth::ApiUser()->admin`.

## User dashboard controllers (19) — namespace `User\`

| Class | Purpose | Key actions |
|---|---|---|
| `User\Dashboard` | Main dashboard, links/archived/expired tables, totals chart | `index`, `links`, `archived`, `expired`, `statsClicks`, `refresh`, `refreshArchive`, `search`, `fetch` |
| `User\Account` | Billing, cancel/terminate, invoice, settings, 2FA, security log, social connect | `billing`, `billingCancel`, `invoice`, `terminate`, `settings`, `settingsUpdate`, `twoFA`, `confirmation`, `manage`, `security`, `connect` |
| `User\Affiliate` | Affiliate dashboard + withdrawal request | `index`, `affiliateSave`, `withdrawals`, `withdrawalRequest` |
| `User\Bio` | Bio pages CRUD + per-block editor | `index`, `save`, `delete`, `edit`, `update`, `updateBlock`, `updateSettings`, `updateOrder`, `deleteBlock`, `preview`, `default`, `importBio`, `duplicate`, `widgets`, `toggle` |
| `User\Campaigns` | Campaign CRUD + stats | `index`, `save`, `update`, `delete`, `stats`, `statsClicks`, `statsMap`, `statsBrowser`, `statsOs` |
| `User\Channels` | Channel CRUD + add/remove items | `index`, `channel`, `save`, `update`, `delete`, `addto`, `removefrom` |
| `User\Developers` | API key management | `keys`, `keyCreate`, `keyRevoke` |
| `User\Domains` | Custom-domain CRUD | `index`, `create`, `save`, `edit`, `update`, `delete` |
| `User\Export` | CSV/JSON export of links/stats/campaign | `links`, `single`, `stats`, `campaign`, `exportSelected` |
| `User\Import` | Upload CSV of links + cancel import | `links`, `importLinks`, `cancel` |
| `User\Integrations` | Connect/disconnect integrations | `index`, `integrations` |
| `User\Overlay` | Overlay/CTA builder per type | `index`, `create`, `save`, `edit`, `update`, `delete`, plus per-type: `contactCreate/Save/Edit/Update`, `pollCreate/...`, `messageCreate/...`, `imageCreate/...`, `newsletterCreate/...`, `couponCreate/...` |
| `User\Pixels` | Tracking-pixel CRUD + attach to items | `index`, `create`, `save`, `edit`, `update`, `delete`, `addto` |
| `User\QR` | QR dashboard CRUD, preview, duplicate, bulk | `index`, `create`, `preview`, `save`, `edit`, `update`, `delete`, `duplicate`, `createbulk`, `savebulk`, `deleteall`, `downloadall` |
| `User\Splash` | Splash-page CRUD | `index`, `create`, `save`, `edit`, `update`, `delete` |
| `User\Stats` | User-level analytics | `index`, `statsLinks`, `statsClicks`, `clicksMap`, `recent`, `clicksPlatforms`, `clicksBrowsers`, `clicksLanguages` |
| `User\Teams` | Team mgmt: invite, edit, toggle, switch, accept | `index`, `invite`, `edit`, `update`, `delete`, `toggle`, `switch`, `accept` |
| `User\Tools` | Integration tools: Slack, Zapier | `index`, `slack`, `zapier` |
| `User\Verification` | KYC submission | `index`, `verify` |

---

## Patterns

- All controllers can be instantiated with `new` and no arguments. Constructor params are NOT injected — only method params.
- Method params are ReflectionMethod-injected: typed non-builtin params (e.g. `Core\Request $request`) are default-constructed (special-case: `Core\Request` reuses the captured request). Route variables are appended positionally.
- API controllers return through `Core\Response::factory(...)->json()`. View controllers call `View::render($template, $data)`.
- Heavy logic lives in **traits** (`Traits\Links`, `Traits\Payments`, `Traits\Pixels`, `Traits\Overlays`, `Traits\Teams`) — controllers `use` them.

See [09-helpers-traits.md](./09-helpers-traits.md) for trait detail and [10-business-logic.md](./10-business-logic.md) for end-to-end flows.
