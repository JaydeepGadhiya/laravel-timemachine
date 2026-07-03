@php
    $base    = url(config('time-machine.dashboard.path', 'time-machine'));
    $slowReq = $thresholds['slow_request'] ?? 500;
    $slowest = $stats['slowest'] ?: 1;
@endphp
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Requests · Laravel Time Machine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #0d1117; }
        .tm-logo { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center;
            font-size: 20px; background: linear-gradient(135deg, #7c5cff, #22d3ee); }
        .tm-brand small { color: #8b98a9; }
        .card { background: #161b22; border-color: #2b3444; }
        .table { --bs-table-bg: transparent; --bs-table-color: #e6edf3; --bs-table-hover-bg: #1c2230; }
        .table > :not(caption) > * > * { border-color: #2b3444; }
        thead th { color: #8b98a9; text-transform: uppercase; letter-spacing: .5px; font-size: 11px; font-weight: 600; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .row-link { cursor: pointer; }
        .stat .n { font-size: 24px; font-weight: 700; }
        .stat .l { color: #8b98a9; font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
        .dur-wrap { display: flex; align-items: center; gap: 10px; min-width: 160px; }
        .dur-wrap .progress { flex: 1 1 auto; height: 6px; background: #1c2230; }
        .dur-wrap .ms { flex: 0 0 auto; white-space: nowrap; font-size: 12px; } /* never wraps */
        .page-link { background: #161b22; border-color: #2b3444; color: #e6edf3; }
        .page-item.active .page-link { background: #7c5cff; border-color: #7c5cff; }
        .page-item.disabled .page-link { background: #12161d; border-color: #2b3444; color: #55606f; }
        a { text-decoration: none; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 1140px;">

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom border-secondary-subtle">
        <a href="{{ $base }}" class="d-flex align-items-center gap-3 text-reset tm-brand">
            <span class="tm-logo">⏱</span>
            <span>
                <h1 class="h5 mb-0">Laravel Time Machine</h1>
                <small>Request lifecycle profiler</small>
            </span>
        </a>
        @if($stats['count'])
            <form method="POST" action="{{ $base }}" onsubmit="return confirm('Clear all recorded request profiles?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit">🗑 Clear all</button>
            </form>
        @endif
    </div>

    <div class="row g-3 mb-4">
        @php $tiles = [
            ['n' => number_format($stats['count']),        'suffix' => '',   'l' => 'Requests'],
            ['n' => number_format($stats['avg'], 1),        'suffix' => 'ms', 'l' => 'Avg duration'],
            ['n' => number_format($stats['slowest'], 1),    'suffix' => 'ms', 'l' => 'Slowest'],
            ['n' => number_format($stats['queries']),       'suffix' => '',   'l' => 'Total queries'],
        ]; @endphp
        @foreach($tiles as $t)
            <div class="col-6 col-md-3">
                <div class="card stat h-100"><div class="card-body py-3">
                    <div class="n mono">{{ $t['n'] }}<small class="text-secondary fs-6"> {{ $t['suffix'] }}</small></div>
                    <div class="l">{{ $t['l'] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if(! $stats['count'])
                <div class="text-center text-secondary py-5">
                    <div style="font-size:44px" class="mb-2">⏱</div>
                    <p class="mb-1 fw-semibold">No requests recorded yet.</p>
                    <p class="mb-0">Browse your application and requests will appear here automatically.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Method</th>
                                <th>URI</th>
                                <th class="d-none d-md-table-cell">Status</th>
                                <th class="d-none d-md-table-cell">Queries</th>
                                <th class="d-none d-md-table-cell">Memory</th>
                                <th style="width:30%">Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($profiles as $p)
                                @php
                                    $url    = $base.'/'.$p['id'];
                                    $isSlow = ($p['total_ms'] ?? 0) >= $slowReq;
                                    $barPct = max(3, min(100, ($p['total_ms'] / $slowest) * 100));
                                    $status = (int) ($p['status'] ?? 0);
                                    $mColor = ['GET'=>'success','POST'=>'warning','PUT'=>'info','PATCH'=>'info','DELETE'=>'danger'][$p['method']] ?? 'secondary';
                                    $sColor = [2=>'success',3=>'info',4=>'warning',5=>'danger'][(int) floor($status/100)] ?? 'secondary';
                                @endphp
                                <tr class="row-link" onclick="window.location='{{ $url }}'">
                                    <td class="ps-3"><span class="badge text-bg-{{ $mColor }} mono">{{ $p['method'] }}</span></td>
                                    <td class="mono">{{ $p['uri'] }}
                                        @if(!empty($p['route']))<div class="text-secondary" style="font-size:11px">{{ $p['route'] }}</div>@endif
                                    </td>
                                    <td class="d-none d-md-table-cell"><span class="badge text-bg-{{ $sColor }}">{{ $status ?: '—' }}</span></td>
                                    <td class="d-none d-md-table-cell mono">{{ $p['query_count'] }}</td>
                                    <td class="d-none d-md-table-cell mono">{{ $p['memory_peak'] ? round($p['memory_peak'] / 1048576, 1).' MB' : '—' }}</td>
                                    <td>
                                        <div class="dur-wrap">
                                            <div class="progress" role="progressbar">
                                                <div class="progress-bar {{ $isSlow ? 'bg-danger' : '' }}"
                                                     style="width: {{ $barPct }}%; {{ $isSlow ? '' : 'background-color:#7c5cff;' }}"></div>
                                            </div>
                                            <span class="ms mono {{ $isSlow ? 'text-danger' : '' }}">{{ number_format($p['total_ms'], 1) }} ms</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if($profiles->hasPages())
        <nav class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-secondary small">
                Showing {{ $profiles->firstItem() }}–{{ $profiles->lastItem() }} of {{ number_format($profiles->total()) }}
            </span>
            <ul class="pagination mb-0">
                <li class="page-item {{ $profiles->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="{{ $profiles->previousPageUrl() ?: '#' }}">‹ Prev</a>
                </li>
                @foreach(range(1, $profiles->lastPage()) as $page)
                    <li class="page-item {{ $page == $profiles->currentPage() ? 'active' : '' }}">
                        <a class="page-link" href="{{ $profiles->url($page) }}">{{ $page }}</a>
                    </li>
                @endforeach
                <li class="page-item {{ $profiles->hasMorePages() ? '' : 'disabled' }}">
                    <a class="page-link" href="{{ $profiles->nextPageUrl() ?: '#' }}">Next ›</a>
                </li>
            </ul>
        </nav>
    @endif

    <p class="text-center text-secondary small mt-4 mb-0">
        Laravel Time Machine — <span class="mono">jaydeep/laravel-time-machine</span>
    </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
