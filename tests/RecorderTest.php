<?php

namespace Jaydeep\LaravelTimeMachine\Tests;

use Jaydeep\LaravelTimeMachine\Recorder;
use PHPUnit\Framework\TestCase;

class RecorderTest extends TestCase
{
    public function test_it_builds_lifecycle_spans_from_phases()
    {
        $t = 1000.0;
        $recorder = new Recorder($t);

        $recorder->phase('booted', $t + 0.010);          // +10ms
        $recorder->phase('route_matched', $t + 0.015);   // +15ms
        $recorder->phase('request_handled', $t + 0.040); // +40ms
        $recorder->phase('terminate', $t + 0.042);       // +42ms

        $profile = $recorder->toArray();

        $this->assertGreaterThanOrEqual(40, $profile['total_ms']);
        $this->assertCount(4, $profile['lifecycle']);

        $boot = $profile['lifecycle'][0];
        $this->assertSame('Bootstrap & Boot', $boot['name']);
        $this->assertEqualsWithDelta(10.0, $boot['duration_ms'], 0.001);
        $this->assertEqualsWithDelta(0.0, $boot['start_ms'], 0.001);
    }

    public function test_it_skips_segments_with_missing_boundaries()
    {
        $recorder = new Recorder(500.0);
        $recorder->phase('booted', 500.010);
        // no route_matched (e.g. a 404) → Middleware/Controller segments dropped

        $profile = $recorder->toArray();
        $names = array_column($profile['lifecycle'], 'name');

        $this->assertContains('Bootstrap & Boot', $names);
        $this->assertNotContains('Controller & Response', $names);
    }

    public function test_it_records_queries_with_derived_start()
    {
        $recorder = new Recorder(microtime(true) - 1);
        $recorder->recordQuery('select * from users where id = ?', [7], 12.5, 'mysql');

        $profile = $recorder->toArray();

        $this->assertSame(1, $profile['query_count']);
        $this->assertEqualsWithDelta(12.5, $profile['queries'][0]['duration_ms'], 0.001);
        $this->assertSame([7], $profile['queries'][0]['bindings']);
    }

    public function test_disabled_recorder_captures_nothing()
    {
        $recorder = (new Recorder(0.0))->enable(false);
        $recorder->mark('x');
        $recorder->recordQuery('select 1', [], 1, 'mysql');

        $profile = $recorder->toArray();

        $this->assertSame(0, $profile['query_count']);
        $this->assertCount(0, $profile['marks']);
    }

    public function test_measure_returns_callback_value()
    {
        $recorder = new Recorder(0.0);
        $result = $recorder->measure('work', function () {
            return 42;
        });

        $this->assertSame(42, $result);
        $this->assertCount(1, $recorder->toArray()['spans']);
    }
}
