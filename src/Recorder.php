<?php

namespace Jaydeep\LaravelTimeMachine;

/**
 * The heart of Time Machine.
 *
 * A per-request singleton that records lifecycle phase boundaries, custom
 * spans, instantaneous marks and executed queries — then flattens the whole
 * thing into a serializable timeline the dashboard can render.
 *
 * All times are captured as absolute microtime(true) seconds and only
 * converted to millisecond offsets (relative to the boot start) at
 * serialization time, so the timeline is always anchored to LARAVEL_START.
 */
class Recorder
{
    /** @var bool */
    protected $enabled = true;

    /** @var float Absolute microtime of the request origin (LARAVEL_START). */
    protected $bootStart;

    /** @var array<string,float> Lifecycle phase boundaries: name => absolute time. */
    protected $phases = [];

    /** @var array<int,array> Completed spans. */
    protected $spans = [];

    /** @var array<string,array> Spans currently open, keyed by name. */
    protected $openSpans = [];

    /** @var array<int,array> Instantaneous marks. */
    protected $marks = [];

    /** @var array<int,array> Executed database queries. */
    protected $queries = [];

    /** @var array Request-level metadata set by the middleware. */
    protected $meta = [];

    public function __construct($bootStart = null)
    {
        $this->bootStart = $bootStart ?: (defined('LARAVEL_START') ? LARAVEL_START : microtime(true));
    }

    public function enable($enabled = true)
    {
        $this->enabled = (bool) $enabled;

        return $this;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function bootStart()
    {
        return $this->bootStart;
    }

    /**
     * Record a lifecycle phase boundary (bootstrap, booted, route_matched…).
     */
    public function phase($name, $at = null)
    {
        if ($this->enabled) {
            $this->phases[$name] = $at ?: microtime(true);
        }

        return $this;
    }

    /**
     * Drop an instantaneous marker onto the timeline.
     */
    public function mark($label, array $meta = [])
    {
        if ($this->enabled) {
            $this->marks[] = ['label' => $label, 'at' => microtime(true), 'meta' => $meta];
        }

        return $this;
    }

    /**
     * Open a named span. Pair with endSpan().
     */
    public function startSpan($name, $group = 'custom', array $meta = [])
    {
        if ($this->enabled) {
            $this->openSpans[$name] = ['group' => $group, 'start' => microtime(true), 'meta' => $meta];
        }

        return $this;
    }

    /**
     * Close a previously opened span.
     */
    public function endSpan($name)
    {
        if ($this->enabled && isset($this->openSpans[$name])) {
            $open = $this->openSpans[$name];
            unset($this->openSpans[$name]);

            $this->spans[] = [
                'name'  => $name,
                'group' => $open['group'],
                'start' => $open['start'],
                'end'   => microtime(true),
                'meta'  => $open['meta'],
            ];
        }

        return $this;
    }

    /**
     * Time a callback as a span and return its result.
     *
     * @return mixed
     */
    public function measure($name, callable $callback, $group = 'custom', array $meta = [])
    {
        if (! $this->enabled) {
            return $callback();
        }

        $this->startSpan($name, $group, $meta);

        try {
            return $callback();
        } finally {
            $this->endSpan($name);
        }
    }

    /**
     * Record an executed query. $timeMs is the DB-reported duration (ms).
     */
    public function recordQuery($sql, $bindings, $timeMs, $connection = null)
    {
        if ($this->enabled) {
            $end = microtime(true);

            $this->queries[] = [
                'sql'        => $sql,
                'bindings'   => $this->normalizeBindings($bindings),
                'time'       => (float) $timeMs,
                'connection' => $connection,
                'end'        => $end,
                'start'      => $end - ((float) $timeMs / 1000),
            ];
        }

        return $this;
    }

    public function setMeta(array $meta)
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    /**
     * Flatten everything into a serializable profile array.
     */
    public function toArray()
    {
        $end   = $this->latestTime();
        $total = max(0.0, ($end - $this->bootStart) * 1000);

        return array_merge($this->meta, [
            'started_at'     => $this->bootStart,
            'total_ms'       => round($total, 3),
            'lifecycle'      => $this->lifecycleSpans(),
            'spans'          => $this->customSpans(),
            'queries'        => $this->querySpans(),
            'query_count'    => count($this->queries),
            'query_time_ms'  => round(array_sum(array_column($this->queries, 'time')), 3),
            'marks'          => $this->markPoints(),
        ]);
    }

    /**
     * Derive the four canonical lifecycle segments from phase boundaries.
     * Segments with a missing boundary are skipped gracefully (e.g. a 404
     * that never matched a route).
     */
    protected function lifecycleSpans()
    {
        $segments = [
            ['name' => 'Bootstrap & Boot',      'from' => 'boot_start',      'to' => 'booted'],
            ['name' => 'Middleware & Routing',  'from' => 'booted',          'to' => 'route_matched'],
            ['name' => 'Controller & Response', 'from' => 'route_matched',   'to' => 'request_handled'],
            ['name' => 'Terminate',             'from' => 'request_handled', 'to' => 'terminate'],
        ];

        $phases = $this->phases + ['boot_start' => $this->bootStart];
        $out    = [];

        foreach ($segments as $segment) {
            if (! isset($phases[$segment['from']], $phases[$segment['to']])) {
                continue;
            }

            $start = $phases[$segment['from']];
            $stop  = $phases[$segment['to']];

            if ($stop < $start) {
                continue;
            }

            $out[] = [
                'name'        => $segment['name'],
                'group'       => 'lifecycle',
                'start_ms'    => $this->offset($start),
                'end_ms'      => $this->offset($stop),
                'duration_ms' => round(($stop - $start) * 1000, 3),
            ];
        }

        return $out;
    }

    protected function customSpans()
    {
        $out = [];

        foreach ($this->spans as $span) {
            $out[] = [
                'name'        => $span['name'],
                'group'       => $span['group'],
                'start_ms'    => $this->offset($span['start']),
                'end_ms'      => $this->offset($span['end']),
                'duration_ms' => round(($span['end'] - $span['start']) * 1000, 3),
                'meta'        => $span['meta'],
            ];
        }

        return $out;
    }

    protected function querySpans()
    {
        $out = [];

        foreach ($this->queries as $query) {
            $out[] = [
                'sql'         => $query['sql'],
                'bindings'    => $query['bindings'],
                'connection'  => $query['connection'],
                'start_ms'    => $this->offset($query['start']),
                'end_ms'      => $this->offset($query['end']),
                'duration_ms' => round($query['time'], 3),
            ];
        }

        return $out;
    }

    protected function markPoints()
    {
        $out = [];

        foreach ($this->marks as $mark) {
            $out[] = [
                'label' => $mark['label'],
                'at_ms' => $this->offset($mark['at']),
                'meta'  => $mark['meta'],
            ];
        }

        return $out;
    }

    protected function offset($time)
    {
        return round(($time - $this->bootStart) * 1000, 3);
    }

    protected function latestTime()
    {
        $times = array_values($this->phases);

        foreach ($this->spans as $span) {
            $times[] = $span['end'];
        }

        foreach ($this->queries as $query) {
            $times[] = $query['end'];
        }

        $times[] = $this->bootStart;

        return max($times);
    }

    protected function normalizeBindings($bindings)
    {
        if (! is_array($bindings)) {
            return [];
        }

        return array_map(function ($binding) {
            if ($binding instanceof \DateTimeInterface) {
                return $binding->format('Y-m-d H:i:s');
            }

            if (is_object($binding)) {
                return method_exists($binding, '__toString') ? (string) $binding : get_class($binding);
            }

            return $binding;
        }, $bindings);
    }
}
