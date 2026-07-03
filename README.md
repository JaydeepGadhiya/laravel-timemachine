# ⏱ Laravel Time Machine

**Inspect, profile and visualize every stage of your Laravel application's request lifecycle — from bootstrapping to response — with detailed execution timelines and performance insights.**

Understand exactly what happens inside every request: how long the framework took to boot, when the route matched, how long the controller ran, every database query on the timeline, and your own custom spans — all rendered as an interactive Gantt-style dashboard.

---

## Features

- 🧭 **Full lifecycle timeline** — Bootstrap & Boot → Middleware & Routing → Controller & Response → Terminate, each measured to the millisecond.
- 🗄 **Query profiling** — every SQL statement captured with bindings, connection, offset on the timeline and duration (slow queries highlighted).
- 📌 **Custom spans & marks** — instrument your own code with a one-line facade.
- 📊 **Visual dashboard** — a self-contained web UI listing recent requests with a Gantt-style timeline detail view. No asset compilation required.
- 🧠 **Performance insights** — total time, peak memory, query count/time, slow-request flagging.
- 🪶 **Zero-overhead when disabled** — off in production by default; nothing recorded, nothing rendered.

## Installation

```bash
composer require jaydeep/laravel-time-machine
```

The service provider and `TimeMachine` facade are auto-discovered. Publish the config if you want to tune it:

```bash
php artisan vendor:publish --tag=time-machine-config
```

## Usage

By default Time Machine is active whenever `APP_DEBUG=true`. Browse your app, then open the dashboard:

```
http://your-app.test/time-machine
```

Every non-ignored HTTP request is recorded automatically. Click a request to see its full lifecycle timeline, queries and marks.

### Instrument your own code

```php
use Jaydeep\LaravelTimeMachine\Facades\TimeMachine;

// Drop a marker on the timeline
TimeMachine::mark('cache primed');

// Time a block of code as a span
$report = TimeMachine::measure('generate-report', function () {
    return Report::build();
});

// Or open/close manually
TimeMachine::startSpan('external-api');
$response = Http::get('https://api.example.com');
TimeMachine::endSpan('external-api');
```

## Configuration

`config/time-machine.php` (env keys in brackets):

| Key | Default | Purpose |
|-----|---------|---------|
| `enabled` `[TIME_MACHINE_ENABLED]` | follows `APP_DEBUG` | Master on/off switch |
| `dashboard.path` `[TIME_MACHINE_PATH]` | `time-machine` | Dashboard URI prefix |
| `dashboard.middleware` | `['web']` | Guards the dashboard (add `auth` in production) |
| `storage.max_records` `[TIME_MACHINE_MAX_RECORDS]` | `100` | Profiles retained (oldest pruned) |
| `storage.path` | `storage/time-machine` | Where profiles are written |
| `collectors.queries` | `true` | Capture DB queries |
| `ignore_paths` | assets, telescope… | Paths never profiled (wildcards) |
| `thresholds.slow_request` / `slow_query` | `500` / `50` ms | UI highlighting |

> **Production tip:** if you enable it in production, lock the dashboard down with an auth middleware via `dashboard.middleware`.

## How it works

Time Machine anchors the timeline to `LARAVEL_START` and records phase boundaries from framework hooks and events:

- `LARAVEL_START` → boot start
- `Application::booted()` → providers booted
- `RouteMatched` event → routing complete
- `RequestHandled` event → response ready
- A prepended global middleware's `terminate()` → request finished, profile persisted

Queries are captured via the `QueryExecuted` event. Everything is flattened into millisecond offsets and stored as one JSON file per request.

## Requirements

- PHP 7.4+ / 8.x
- Laravel 8, 9 or 10

## License

MIT © Jaydeep Gadhiya
