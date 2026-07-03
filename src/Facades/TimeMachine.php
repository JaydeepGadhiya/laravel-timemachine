<?php

namespace Jaydeep\LaravelTimeMachine\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Jaydeep\LaravelTimeMachine\Recorder mark(string $label, array $meta = [])
 * @method static \Jaydeep\LaravelTimeMachine\Recorder startSpan(string $name, string $group = 'custom', array $meta = [])
 * @method static \Jaydeep\LaravelTimeMachine\Recorder endSpan(string $name)
 * @method static mixed measure(string $name, callable $callback, string $group = 'custom', array $meta = [])
 * @method static bool isEnabled()
 *
 * @see \Jaydeep\LaravelTimeMachine\Recorder
 */
class TimeMachine extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'time-machine';
    }
}
