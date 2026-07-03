<?php

namespace Jaydeep\LaravelTimeMachine;

use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\ServiceProvider;
use Jaydeep\LaravelTimeMachine\Http\Middleware\TimeMachineMiddleware;
use Jaydeep\LaravelTimeMachine\Storage\FileStorage;
use Jaydeep\LaravelTimeMachine\Storage\StorageContract;

class TimeMachineServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/time-machine.php', 'time-machine');

        $bootStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

        $this->app->singleton(Recorder::class, function ($app) use ($bootStart) {
            $recorder = new Recorder($bootStart);
            $recorder->enable((bool) $app['config']->get('time-machine.enabled', false));

            return $recorder;
        });

        $this->app->alias(Recorder::class, 'time-machine');

        $this->app->singleton(StorageContract::class, function ($app) {
            $storage = $app['config']->get('time-machine.storage');

            return new FileStorage($storage['path'], $storage['max_records'] ?? 100);
        });

        // Capture the exact moment the framework finishes booting all providers.
        $this->app->booted(function ($app) {
            $app->make(Recorder::class)->phase('booted');
        });
    }

    public function boot()
    {
        $this->registerPublishing();

        // The dashboard is always available so previously recorded profiles
        // remain viewable even when live recording is switched off.
        $this->registerDashboard();

        if (! (bool) $this->app['config']->get('time-machine.enabled', false)) {
            return;
        }

        $recorder = $this->app->make(Recorder::class);

        $this->registerCollectors($recorder);
        $this->registerHttpProfiling();
    }

    /**
     * Wire the event listeners that feed the recorder.
     */
    protected function registerCollectors(Recorder $recorder)
    {
        $events = $this->app['events'];
        $config = $this->app['config'];

        $events->listen(RouteMatched::class, function (RouteMatched $event) use ($recorder) {
            $route = $event->route;
            $recorder->phase('route_matched');
            $recorder->setMeta([
                'route'  => $route->getName() ?: ($route->uri() ? '/'.ltrim($route->uri(), '/') : null),
                'action' => $route->getActionName(),
            ]);
        });

        $events->listen(RequestHandled::class, function () use ($recorder) {
            $recorder->phase('request_handled');
        });

        if ($config->get('time-machine.collectors.queries', true)) {
            $events->listen(QueryExecuted::class, function (QueryExecuted $query) use ($recorder) {
                $recorder->recordQuery($query->sql, $query->bindings, $query->time, $query->connectionName);
            });
        }
    }

    /**
     * Prepend the profiling middleware onto the HTTP kernel so it wraps the
     * entire request. HTTP only — console commands are not profiled.
     */
    protected function registerHttpProfiling()
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $kernel = $this->app->make(HttpKernel::class);

        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(TimeMachineMiddleware::class);
        }
    }

    /**
     * Load the dashboard views and routes if the UI is enabled.
     */
    protected function registerDashboard()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'time-machine');

        $config = $this->app['config'];

        if (! $config->get('time-machine.dashboard.enabled', true)) {
            return;
        }

        $this->app['router']->group([
            'prefix'     => $config->get('time-machine.dashboard.path', 'time-machine'),
            'middleware' => $config->get('time-machine.dashboard.middleware', ['web']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        });
    }

    /**
     * Register publishable assets (config).
     */
    protected function registerPublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/time-machine.php' => $this->app->configPath('time-machine.php'),
            ], 'time-machine-config');
        }
    }
}
