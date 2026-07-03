<?php

namespace Jaydeep\LaravelTimeMachine\Http\Controllers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Jaydeep\LaravelTimeMachine\Storage\StorageContract;

class DashboardController
{
    /** @var StorageContract */
    protected $storage;

    /** @var Config */
    protected $config;

    public function __construct(StorageContract $storage, Config $config)
    {
        $this->storage = $storage;
        $this->config  = $config;
    }

    public function index()
    {
        $all = $this->storage->all(); // newest first

        // Stats reflect every stored profile, not just the current page.
        $count = count($all);
        $stats = [
            'count'   => $count,
            'avg'     => $count ? array_sum(array_column($all, 'total_ms')) / $count : 0,
            'slowest' => $count ? max(array_column($all, 'total_ms')) : 0,
            'queries' => array_sum(array_column($all, 'query_count')),
        ];

        $perPage = (int) $this->config->get('time-machine.dashboard.per_page', 15);
        $page    = Paginator::resolveCurrentPage('page');

        $profiles = new LengthAwarePaginator(
            array_slice($all, ($page - 1) * $perPage, $perPage),
            $count,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        );

        return view('time-machine::dashboard', [
            'profiles'   => $profiles,
            'stats'      => $stats,
            'thresholds' => $this->config->get('time-machine.thresholds'),
        ]);
    }

    public function show($id)
    {
        $profile = $this->storage->find($id);

        if ($profile === null) {
            abort(404, 'Request profile not found.');
        }

        return view('time-machine::detail', [
            'profile'    => $profile,
            'thresholds' => $this->config->get('time-machine.thresholds'),
        ]);
    }

    public function clear()
    {
        $this->storage->clear();

        return new RedirectResponse(url($this->config->get('time-machine.dashboard.path', 'time-machine')));
    }
}
