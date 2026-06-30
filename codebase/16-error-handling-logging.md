# 16 — Error Handling & Logging

All error/logging plumbing routes through `GemError` (`core/GemError.class.php`).

## PHP error handlers (registered at bootstrap)

Defined in `core/functions/core.php`:

```php
set_error_handler('GemError', E_ALL);          // → GemError::exception()
register_shutdown_function('FatalError');       // → logs error_get_last()
```

| Hook | Handler | Behavior |
|---|---|---|
| Any PHP error (`E_ALL`) | `GemError::exception($code, $msg, $file, $line)` | Logs to system channel; dispatches plugin event `error.exception`. |
| Shutdown w/ fatal | `GemError::fatal($message)` | Logs final fatal; dispatches `error.fatal`. |
| Explicit (`stop()`, 404/405 in dispatcher) | `GemError::trigger($code, $error, $uri)` | Sets HTTP status, dispatches `error.trigger`, renders themed error page. |

## Monolog channels

| Channel | File | Created by | Level | Used for |
|---|---|---|---|---|
| `system` | `storage/logs/Log-<m-d-Y>.log` | `GemError::logger(LOGS)` at bootstrap | `ERROR` | All app/PHP errors |
| (custom) | `storage/logs/<name>.log` | `GemError::channel($name, LOGS)` | `DEBUG` | Anything `GemError::toChannel($name, ...)` writes |

The `system` channel is initialized once in `Gem::Bootstrap()`. Custom channels are created on demand by callers — `CronController`, for example, creates a channel per cron job.

Format (LineFormatter):

```
[2026-06-30 14:22:31] system.ERROR: <message> {"data":{...},"REQUEST_URI":"..."} []
```

## Logging API

```php
GemError::log($error, $data = [], $channel = 'system', $type = 'error');
GemError::channel('imports', LOGS);              // create new channel
GemError::toChannel('imports', $error, $data);  // log to it
GemError::trigger(404);                          // → render errors/404, log if !=404
```

`GemError::log` automatically appends the current `REQUEST_URI` to the context.

`trigger(404, ...)` deliberately **does not log** — too noisy. Other status codes do.

## Error pages

`GemError::trigger($code)` resolves a page in this order:

1. `View::error($code)` — looks for `storage/themes/<theme>/errors/<code>.php`. Renders inside the theme.
2. Falls back to `themes/<default>/errors/<code>.php`.
3. Falls back to the framework's built-in inline HTML:
   - **In `DEBUG` mode:** stack-trace template via `GemError::template()` (uses `xdebug_print_function_stack` when available).
   - **In production:** minimal `cleanError()` template.

So to customize an error page, drop a file at `storage/themes/default/errors/404.php`.

## Plugin events fired

| Event | Where | Payload |
|---|---|---|
| `error.exception` | `GemError::exception` | `[$code, $message, $file, $line]` |
| `error.fatal` | `GemError::fatal` | `[$message]` |
| `error.trigger` | `GemError::trigger` | `[$code, $error, $uri]` |

Register a handler in a plugin's `plugin.php`:

```php
Plugin::register('error.exception', function($code, $msg, $file, $line) {
    // forward to Sentry, Bugsnag, etc.
});
```

## Where logs go in production

| Path | Contents |
|---|---|
| `storage/logs/Log-MM-DD-YYYY.log` | System channel — every uncaught PHP error |
| `storage/logs/<channel>.log` | Cron channels: `user`, `data`, `urls`, `remind`, `imports` |
| Webhook gateway responses | Logged to system channel on cURL failure (`Core\Http`) |
| Email send failures | Logged to system channel by `Core\Email` / mailer drivers |
| Google Web Risk / VirusTotal errors | Logged to system channel by `Traits\Links` |

## Read logs from the admin panel

`Admin\Dashboard@crons` (`/admin/crons`) renders the contents of any `storage/logs/*.log` file. `Admin\Dashboard@cronsClear` truncates them.

## Application event log (different from error logs)

`Helpers\Events::for($type)->user($id)->log($data)` writes a row into the `appevents` table. Different surface:

- Persistent in DB, not in files.
- Surfaced to users (`User\Account@security`) and admins (`Admin\Users@activity`).
- Only fires when `config('userlogging')` is on.
- Event types observed: `login`, `notification` (used by `User::notifications()`).

This is for **user-visible activity** ("you logged in from 1.2.3.4 at …"), not for engineer debugging. Don't conflate with Monolog channels.

## Common error patterns to recognise in code

```php
// Translate then redirect with flash
Helper::redirect(route('home'))->with('danger', e('Something went wrong'))->exit();

// Hard 404
GemError::trigger(404);
exit;

// AJAX JSON error
Response::factory(['error' => true, 'message' => 'X'])->json();
exit;

// Log + continue
try { ... } catch (\Exception $e) { GemError::log($e->getMessage(), [$context]); }
```

## DEBUG levels

`DEBUG` constant (from `config.php`):

| Level | Behavior |
|---|---|
| `0` (default) | Production: errors logged silently, clean error pages shown |
| `1` | Errors logged + stack-trace error page shown |
| `2` | Same plus `Core\Email` enables PHPMailer SMTP debug output, `Core\Http` disables SSL verification (development only) |

⚠️ Never run with `DEBUG > 0` in production.
