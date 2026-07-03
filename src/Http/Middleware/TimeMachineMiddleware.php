<?php

namespace Jaydeep\LaravelTimeMachine\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Jaydeep\LaravelTimeMachine\Recorder;
use Jaydeep\LaravelTimeMachine\Storage\StorageContract;

/**
 * Global middleware. Marks the entry into the middleware stack on the way in
 * and, once the response has been sent (terminate), snapshots request metadata
 * and persists the recorded profile. It is intentionally defensive — nothing
 * here may ever break the host application.
 */
class TimeMachineMiddleware
{
    /** @var Recorder */
    protected $recorder;

    /** @var StorageContract */
    protected $storage;

    /** @var Config */
    protected $config;

    public function __construct(Recorder $recorder, StorageContract $storage, Config $config)
    {
        $this->recorder = $recorder;
        $this->storage  = $storage;
        $this->config   = $config;
    }

    public function handle($request, Closure $next)
    {
        $this->recorder->phase('middleware_start');

        return $next($request);
    }

    public function terminate($request, $response)
    {
        try {
            if (! $this->recorder->isEnabled() || $this->shouldIgnore($request)) {
                return;
            }

            $this->recorder->phase('terminate');

            $meta = [
                'method'   => $request->getMethod(),
                'uri'      => '/'.ltrim($request->path(), '/'),
                'full_url' => $request->fullUrl(),
                'status'   => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
            ];

            if ($this->config->get('time-machine.collectors.memory', true)) {
                $meta['memory_peak']    = memory_get_peak_usage(true);
                $meta['memory_current'] = memory_get_usage(true);
            }

            $this->recorder->setMeta($meta);

            $this->storage->store($this->recorder->toArray());
        } catch (\Throwable $e) {
            // Profiling must never take the application down.
        }
    }

    protected function shouldIgnore($request)
    {
        $path = '/'.ltrim($request->path(), '/');

        // Never profile the dashboard itself.
        $dashboard = trim((string) $this->config->get('time-machine.dashboard.path', 'time-machine'), '/');

        if ($dashboard !== '' && Str::is([$dashboard, $dashboard.'/*'], ltrim($path, '/'))) {
            return true;
        }

        foreach ((array) $this->config->get('time-machine.ignore_paths', []) as $pattern) {
            if (Str::is($pattern, ltrim($path, '/'))) {
                return true;
            }
        }

        return false;
    }
}
