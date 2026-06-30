# 13 — Payments

## Architecture

All payment work goes through the **gateway registry** in `Traits\Payments::processor()`. Consumers (`SubscriptionController`, `WebhookController`, `User\Account`, `Admin\Membership`, `Admin\Plans`, `Admin\Settings`, `Admin\Coupons`, `Admin\Tax`, `Admin\Vouchers`) resolve `[Class, 'method']` callable pairs from this registry and `call_user_func_array($callable, $args)`.

```php
use Traits\Payments;

class SubscriptionController {
    use Payments;
    public function process(Request $request, $id, $type) {
        // …
        $callable = $this->processor($request->payment, 'payment');
        return call_user_func_array($callable, [$request, $id, $type]);
    }
}
```

This means **adding a new gateway only requires** (a) creating a class in `app/helpers/payments/`, (b) registering it inside `Traits\Payments::processor()` (or via the `payment.extend` plugin hook).

## The 8 gateways

| Key | Class | One-off | Subscription | SDK / API |
|---|---|---|---|---|
| `stripe` | `Helpers\Payments\Stripe` | ✓ | ✓ | `stripe/stripe-php` (`api.stripe.com`) |
| `mollie` | `Helpers\Payments\Mollie` | ✓ | partial | `mollie/mollie-api-php` |
| `paypalapi` | `Helpers\Payments\PaypalApi` | ✓ | ✓ | `paypal/rest-api-sdk-php` |
| `paypal` | `Helpers\Payments\Paypal` | ✓ | — | Classic IPN (`paypal.com/cgi-bin/webscr`) |
| `paddle` | `Helpers\Payments\Paddle` | ✓ | ✓ | Paddle Classic (`vendors.paddle.com`) |
| `paddlebilling` | `Helpers\Payments\PaddleBilling` | ✓ | ✓ | Paddle Billing (`api.paddle.com`) |
| `paystack` | `Helpers\Payments\PayStack` | ✓ | ✓ | `api.paystack.co` |
| `bank` | `Helpers\Payments\Bank` | ✓ | — | Manual bank transfer (admin approval) |

Plus runtime extension via `Plugin::dispatch('payment.extend')`.

## Standard gateway shape

Every gateway exposes a subset of these static methods (same naming across all classes):

| Method | When called |
|---|---|
| `settings()` | Renders the admin settings panel for this gateway (`Admin\Settings`). |
| `checkout($plan)` | Renders the checkout button/script (used by `SubscriptionController@checkout` view). |
| `payment(Request, int $id, string $type)` | Processes a `POST /checkout/{id}/{type}` — typically creates a session/intent at the provider and redirects. |
| `callback(Request)` | Handles the user's return from the provider (where applicable). Routes are `/callback/paddle`, `/callback/paystack`, `/callback/mollie`. |
| `webhook(Request)` | Handles provider→server webhooks. Routes through `WebhookController@index`. |
| `cancel($user, $subscription)` | Cancel an active subscription. |
| `createplan($plan)` | Push a new plan to the provider (when supported). |
| `updateplan($request, $plan)` | Update an existing plan at the provider. |
| `syncplan($plan)` | Pull plan state from the provider. |
| `createcoupon($request)` / `createtax($request)` | Push coupons / tax rates to the provider (Stripe, Paddle Billing, PayStack). |
| `manage($user, $subscription)` | Open customer portal (Stripe Billing Portal, etc.). |

## Per-gateway notes

### Stripe (`Helpers\Payments\Stripe`)

The most complete integration. Uses `\Stripe\StripeClient`, `\Stripe\Webhook::constructEvent` (signature verification), `\Stripe\BillingPortal\Session`. Builds Payment Links or Checkout Sessions for one-offs and subscriptions. Embeds `https://js.stripe.com/v3/`.

Webhook events handled: `invoice.paid`, `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `checkout.session.completed`, plus refunds.

### Mollie (`Helpers\Payments\Mollie`)

`Mollie\Api\MollieApiClient`. Creates payments and (limited) subscriptions. Webhook URL `/webhook/mollie`.

### PayPal REST (`Helpers\Payments\PaypalApi`)

`paypal/rest-api-sdk-php`. Handles both subscriptions and one-off payments. Mode toggles `sandbox`/`live` by `DEBUG` flag. Auto-registers webhook event types via `\PayPal\Api\WebhookEventType` on `createplan`.

### PayPal Classic (`Helpers\Payments\Paypal`)

Legacy `paypal.com/cgi-bin/webscr` form-post with IPN postback for verification (`IpnListener`). Single-payment only.

### Paddle Classic (`Helpers\Payments\Paddle`)

Pre-billing Paddle. Constants `CHECKOUT = "https://checkout.paddle.com/api/"`, `VENDOR = "https://vendors.paddle.com/api/2.0"`. Embeds `cdn.paddle.com/paddle/paddle.js`. Supports the classic alert webhooks and the cancel flow via API.

### Paddle Billing (`Helpers\Payments\PaddleBilling`)

The new (post-2023) Paddle API. Constant `API = "https://api.paddle.com"`. Embeds `cdn.paddle.com/paddle/v2/paddle.js`. Full support: plans, coupons, transactions, webhooks with signature verification.

### PayStack (`Helpers\Payments\PayStack`)

African-market gateway. Constant `API = "https://api.paystack.co"`. Manage page is a no-op — PayStack doesn't have a customer portal equivalent.

### Bank (`Helpers\Payments\Bank`)

Manual: inserts a pending `payment` row, shows "transfer to this account" instructions, requires admin to mark paid via `Admin\Membership@markAs`.

## Webhook dispatch

```
POST /webhook[/{provider}]
   WebhookController@index(Request, $provider = null)
   1. $provider ??= 'stripe'
      'paypal' → 'paypalapi'      (alias)
   2. Plugin::register('payment.success', $affiliateCreditClosure)
        On every payment.success, find affiliate referrer via 'urid' cookie / DB::affiliates
        Credit fixed amount OR percentage; one-off OR recurring
        INSERT INTO affiliates (userid, referrerid, paymentid, amount, type, recurring, status)
   3. Try Webhook::$method   (e.g. stripe() defined on WebhookController)
      Else Traits\Payments::processor($provider, 'webhook')
   4. Invoke
```

Then inside the gateway's `webhook($request)`:

```
1. Verify signature (Stripe::Webhook::constructEvent, Paddle::verifyWebhook, …)
2. Parse event
3. INSERT INTO payment (...)
4. UPDATE subscription SET status = ..., data = ... WHERE userid = ...
5. UPDATE user SET pro = 1, planid = ..., expiration = ...
6. Plugin::dispatch('payment.success', $payment)   ← affiliate credit fires
7. (optional) Helpers\Emails::renewEmail / canceled
```

## Database tables touched

- `payment` — every transaction (one row per charge/invoice).
- `subscription` — active state per user (one row per user, updated in place).
- `affiliates` — commission entries linked to payment ids.
- `coupons`, `vouchers`, `taxrates` — billing-time discounts and tax.
- `user.pro`, `user.planid`, `user.expiration`, `user.trial` — denormalized fast-path for plan gating.

## Settings keys

Each gateway has a top-level `settings` row consumed by `Admin\Settings`:

- `stripe.{enabled, publishable_key, secret_key, webhook_secret}`
- `paddle.{enabled, vendor_id, vendor_auth_code, public_key}`
- `paddlebilling.{enabled, api_key, client_token, webhook_secret}`
- `paypal.{enabled, mode, email}`
- `paypalapi.{enabled, mode, client_id, client_secret}`
- `mollie.{enabled, api_key}`
- `paystack.{enabled, public_key, secret_key}`
- `bank.{enabled, instructions}`

Plus generic settings: `pro` (master pro toggle), `freetrial`, `currency`, `vatrate`, `saleszapier` (post-purchase webhook).

## Affiliate commission flow

1. Visitor clicks `?ref=<userid>` link.
2. `CheckDomain` middleware sets cookie `urid = <userid>` for 90 days.
3. Visitor signs up → cookie carries forward.
4. Visitor pays → webhook fires `payment.success`.
5. Closure registered by `WebhookController@index` reads `urid` and credits affiliate.

## Adding a new gateway

1. Create `app/helpers/payments/<Name>.php`:
   ```php
   <?php
   namespace Helpers\Payments;
   class Name {
       public static function settings() { /* admin panel HTML */ }
       public static function checkout($plan) { /* checkout HTML/JS */ }
       public static function payment($request, $id, $type) { /* … */ }
       public static function webhook($request) { /* … */ }
       public static function cancel($user, $subscription) { /* … */ }
   }
   ```
2. Either register inline in `Traits\Payments::processor()` array, or expose via a plugin:
   ```php
   Plugin::register('payment.extend', function() {
       return ['name' => [
           'enabled' => config('name')->enabled ?? false,
           'name'    => 'My Gateway',
           'logo'    => '/static/images/name.png',
           'settings' => [\Helpers\Payments\Name::class, 'settings'],
           'checkout' => [\Helpers\Payments\Name::class, 'checkout'],
           'payment'  => [\Helpers\Payments\Name::class, 'payment'],
           'webhook'  => [\Helpers\Payments\Name::class, 'webhook'],
           'cancel'   => [\Helpers\Payments\Name::class, 'cancel'],
       ]];
   });
   ```
3. Webhook route `/webhook/name` automatically routes through `WebhookController@index`.
