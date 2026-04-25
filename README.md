# Laravel HTTP Beacon

A lightweight, HTTP-focused observability package for Laravel. Beacon records every incoming HTTP request and every outgoing HTTP call, along with the queries, model events, and queued jobs they triggered, and serves them through a built-in Vue dashboard.

It is intentionally narrower than [Laravel Telescope](https://github.com/laravel/telescope) — only the parts most teams care about in production: HTTP traffic and the work that traffic kicked off.

## Why Beacon?

- **HTTP-first.** No watchers for things you rarely need in production (mail, cache, redis, views, dumps, ...). Just requests in, requests out, and what they did.
- **Filterable at scale.** Beacon stores queries, model touches, and job dispatches in normalized tables with composite indexes.
- **Caller everywhere.** Each captured query, model event, and dispatched job is tagged with the user-code call site (e.g. `App\Services\UserService@updateUser:14`), not just queries.
- **Modern stack.** Vue 3, Vite, Tailwind v4. Compiled assets ship with the package — no Node toolchain required in the consumer app.

## Features

- Incoming HTTP request capture (method, path, status, duration, memory, IP, headers, payload, response, controller action, middleware)
- Outgoing HTTP request capture (method, URI, status, duration, headers, payload, response, error)
- Per-request rollups: queries (with bindings), model touches (with diff), dispatched jobs (with payload)
- Caller stack capture for queries, models, and jobs — `Class@method:line`
- Header and parameter redaction (case-insensitive headers, dot/wildcard parameter paths)
- Search + method + status-range + date-range + failed-only filters
- Keyset pagination (`?before_id=N`)
- Pause / resume recording from the UI or via Artisan
- Bulk delete from the UI
- Retention pruning command (chunked `DELETE`)
- Configurable sampling rate, body size limits, ignored hosts/paths/methods/status codes
- Dashboard with 24h aggregations: counts, status buckets, slowest endpoints, failed outgoing

## Beacon vs Telescope


|                                      | Beacon                         | Telescope                                  |
| ------------------------------------ | ------------------------------ | ------------------------------------------ |
| Incoming HTTP requests               | yes                            | yes                                        |
| Outgoing HTTP client                 | yes                            | yes                                        |
| Queries (with bindings + caller)     | yes                            | yes                                        |
| Model events (with diff)             | yes                            | yes                                        |
| Job dispatches                       | yes                            | yes                                        |
| Caller (file:line + `Class@method`)  | queries, models, jobs          | queries only                               |
| Authorization gates                  | no                             | yes                                        |
| Mail / Notifications                 | no                             | yes                                        |
| Cache / Redis                        | no                             | yes                                        |
| Logs / Exceptions                    | no                             | yes                                        |
| Dumps (`dd` / `dump`)                | no                             | yes                                        |
| Schedule / Commands                  | no                             | yes                                        |
| Views                                | no                             | yes                                        |
| Normalized DB tables (indexed)       | yes                            | no — single JSON entries                   |
| Indexed filter on method/status/date | yes                            | no — JSON column scans                     |
| Built-in dashboard widgets           | yes — counts, buckets, slowest | basic listing only                         |
| UI stack                             | Vue 3 + Tailwind v4            | Vue 2 + Bootstrap                          |
| Auth gate by default                 | no — open at `/beacon`         | yes — `Gate::define('viewTelescope', ...)` |


If you need a kitchen-sink debug tool in development, Telescope is the better fit. If you want production-grade HTTP traffic observability with fast filtering and predictable storage, use Beacon.

## Requirements

- PHP 8.3+
- Laravel 13+
- MySQL, Postgres, or SQLite

## Installation

```bash
composer require tintaungkhant/laravel-http-beacon
```

Run the install command — it publishes the config, the migrations, and the compiled UI assets, then runs the migration:

```bash
php artisan beacon:install
php artisan migrate
```

Open `/beacon` in your browser.

## Configuration

After install, the config lives at `config/beacon.php`. The most-used keys:

```php
return [
    'enabled' => env('BEACON_ENABLED', true),

    'storage' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
    ],

    'sampling_rate' => (float) env('BEACON_SAMPLING_RATE', 1.0), // 0.1 = 10% of traffic

    'redact' => (bool) env('BEACON_REDACT', true),

    'hidden_headers' => [
        'authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-csrf-token',
    ],

    'hidden_parameters' => [
        'password', 'password_confirmation', 'token', 'secret', '_token',
    ],

    'incoming' => [
        'enabled' => true,
        'body_size_limit_kb' => 64,
        'only_paths' => [],            // ['api/*'] to record only API routes
        'ignore_paths' => ['beacon*', 'horizon*', 'telescope*', '_ignition*'],
        'ignore_methods' => [],
        'ignore_status_codes' => [],
    ],

    'outgoing' => [
        'enabled' => true,
        'body_size_limit_kb' => 64,
        'ignore_hosts' => [],          // ['*.amazonaws.com']
    ],

    'collect' => [
        'queries' => true,
        'models' => true,
        'jobs' => true,
        'memory' => true,
        'model_actions' => ['created', 'updated', 'deleted', 'restored', 'retrieved'],
        'max_queries_per_request' => null, // 0 / null = unlimited
    ],

    'retention' => [
        'hours' => (int) env('BEACON_RETENTION_HOURS', 168), // 7 days
        'chunk_size' => 1000,
    ],
];
```

Hidden parameter paths support dot notation and wildcards: `user.password`, `tokens.*.value`.

## Artisan Commands

```bash
php artisan beacon:install   # publish config + migrations + assets, then migrate
php artisan beacon:pause     # stop recording (cache flag, persists across requests)
php artisan beacon:resume    # resume recording
php artisan beacon:clear     # truncate beacon_incoming_requests + beacon_outgoing_requests
php artisan beacon:prune     # delete entries older than retention.hours
php artisan beacon:prune --hours=24      # override retention
php artisan beacon:prune --dry-run       # count without deleting
```

The same pause / resume / clear actions are available from the dashboard header.

Schedule pruning with your application's scheduler:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('beacon:prune')->daily();
```

## Authentication

Beacon ships **without** an auth gate. The `/beacon/`* routes are open by default — fine for local development, **not** for production. Wrap the package's routes in your own middleware in your app's `bootstrap/app.php`, or only enable Beacon in non-production environments:

```php
// .env (production)
BEACON_ENABLED=false
```

A built-in gate is on the roadmap.

## Testing

```bash
composer install
composer test       # phpunit
vendor/bin/phpstan  # level 5 with larastan
```

## Screenshots

### Dashboard

24-hour aggregation: incoming + outgoing volumes, failure counts, status breakdown, slowest endpoints (clickable through to detail).

Dashboard

### Incoming Requests

List view with search, method, status range, date range, and clickable rows. Pause / resume / delete-all in the header.

Incoming Requests List

### Incoming Request Detail

Full request payload, headers, response, plus the queries / models / jobs that ran during the request — each tagged with the user-code caller.

Incoming Request Detail — Attributes & Tabs

Incoming Request Detail — Queries / Models / Jobs

### Outgoing Requests

List view with the `Failed only` toggle for connection errors.

Outgoing Requests List

### Outgoing Request Detail

URI, status, duration, request payload, response, and headers — same redaction rules as incoming.

Outgoing Request Detail

## License

The MIT License (MIT). See [LICENSE](LICENSE).