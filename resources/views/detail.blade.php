@extends('time-machine::layout')

@section('title', $profile['method'].' '.$profile['uri'])

@section('styles')
    .back { margin-bottom: 18px; display: inline-block; }
    .head { padding: 18px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .head .uri { font-family: var(--mono); font-size: 16px; word-break: break-all; }
    .meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 24px; }
    .meta { padding: 14px 16px; }
    .meta .n { font-size: 20px; font-weight: 700; font-family: var(--mono); }
    .meta .l { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .6px; }
    .section-title { font-size: 12px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted);
        margin: 28px 0 12px; }

    /* Timeline / Gantt */
    .timeline { padding: 18px 20px; }
    .axis { position: relative; height: 18px; margin-left: 180px; border-bottom: 1px dashed var(--border); margin-bottom: 10px; }
    .axis span { position: absolute; transform: translateX(-50%); font-size: 10px; color: var(--muted); font-family: var(--mono); }
    .tl-row { display: flex; align-items: center; height: 30px; }
    .tl-label { width: 180px; flex: 0 0 180px; font-size: 12px; color: var(--text); padding-right: 12px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .tl-track { position: relative; flex: 1; height: 22px; background: var(--panel-2); border-radius: 6px; }
    .tl-bar { position: absolute; top: 0; height: 22px; border-radius: 6px; min-width: 2px; display: flex;
        align-items: center; padding: 0 6px; font-size: 10px; font-family: var(--mono); color: #06121f;
        font-weight: 700; overflow: hidden; white-space: nowrap; cursor: default; }
    .g-lifecycle-0 { background: var(--boot); color: #fff; }
    .g-lifecycle-1 { background: var(--mw); }
    .g-lifecycle-2 { background: var(--ctrl); }
    .g-lifecycle-3 { background: var(--term); }
    .g-custom { background: linear-gradient(90deg,#a78bfa,#7c5cff); color:#fff; }
    .g-query { background: var(--query); }
    .tl-mark { position: absolute; top: -6px; bottom: -6px; width: 2px; background: var(--mark); }
    .tl-mark::after { content: attr(data-label); position: absolute; top: -14px; left: 3px; font-size: 9px;
        color: var(--mark); white-space: nowrap; font-family: var(--mono); }

    .legend { display: flex; gap: 16px; flex-wrap: wrap; margin: 14px 0 4px 180px; }
    .legend span { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; color: var(--muted); }
    .legend i { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }

    table.q { width: 100%; border-collapse: collapse; }
    table.q th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
        color: var(--muted); padding: 10px 14px; border-bottom: 1px solid var(--border); }
    table.q td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: top; }
    table.q tr:last-child td { border-bottom: none; }
    .sql { font-family: var(--mono); font-size: 12px; color: #cbd5e1; word-break: break-word; }
    .q-time { font-family: var(--mono); white-space: nowrap; }
    .q-time.slow { color: var(--bad); font-weight: 700; }
    @media (max-width: 720px) { .meta-grid { grid-template-columns: repeat(2,1fr);} .tl-label,.axis{width:120px;flex-basis:120px;margin-left:120px;} .legend{margin-left:120px;} }
@endsection

@section('content')
    @php
        $base   = url(config('time-machine.dashboard.path','time-machine'));
        $total  = max(0.001, (float) ($profile['total_ms'] ?? 0));
        $status = (int) ($profile['status'] ?? 0);
        $slowQ  = $thresholds['slow_query'] ?? 50;
        $pct    = function ($ms) use ($total) { return max(0, min(100, ($ms / $total) * 100)); };
    @endphp

    <a href="{{ $base }}" class="back">← All requests</a>

    <div class="panel head">
        <span class="pill method-{{ $profile['method'] }}">{{ $profile['method'] }}</span>
        <span class="uri">{{ $profile['uri'] }}</span>
        <span class="pill status-{{ (int) floor($status / 100) }}">{{ $status ?: '—' }}</span>
        @if(!empty($profile['route']))
            <span class="muted mono" style="font-size:12px">{{ $profile['route'] }}</span>
        @endif
    </div>

    <div class="meta-grid">
        <div class="panel meta"><div class="n">{{ number_format($total, 1) }}<span class="muted" style="font-size:13px"> ms</span></div><div class="l">Total time</div></div>
        <div class="panel meta"><div class="n">{{ $profile['query_count'] ?? 0 }}</div><div class="l">Queries · {{ number_format($profile['query_time_ms'] ?? 0, 1) }} ms</div></div>
        <div class="panel meta"><div class="n">{{ !empty($profile['memory_peak']) ? round($profile['memory_peak'] / 1048576, 1) : '—' }}<span class="muted" style="font-size:13px"> MB</span></div><div class="l">Peak memory</div></div>
        <div class="panel meta"><div class="n">{{ count($profile['spans'] ?? []) }}</div><div class="l">Custom spans</div></div>
    </div>

    <div class="section-title">Lifecycle timeline</div>
    <div class="panel timeline">
        <div class="axis">
            @for($i = 0; $i <= 4; $i++)
                <span style="left: {{ $i * 25 }}%">{{ number_format($total * $i / 4, 1) }} ms</span>
            @endfor
        </div>

        @foreach($profile['lifecycle'] ?? [] as $idx => $span)
            <div class="tl-row">
                <div class="tl-label" title="{{ $span['name'] }}">{{ $span['name'] }}</div>
                <div class="tl-track">
                    <div class="tl-bar g-lifecycle-{{ $idx }}"
                         style="left: {{ $pct($span['start_ms']) }}%; width: {{ max(0.5, $pct($span['duration_ms'] + $span['start_ms']) - $pct($span['start_ms'])) }}%"
                         title="{{ $span['name'] }} — {{ number_format($span['duration_ms'], 2) }} ms">
                        {{ number_format($span['duration_ms'], 1) }} ms
                    </div>
                    @foreach($profile['marks'] ?? [] as $mark)
                        @if($idx === 0)
                            <div class="tl-mark" style="left: {{ $pct($mark['at_ms']) }}%" data-label="{{ $mark['label'] }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach

        @foreach($profile['spans'] ?? [] as $span)
            <div class="tl-row">
                <div class="tl-label" title="{{ $span['name'] }}">↳ {{ $span['name'] }}</div>
                <div class="tl-track">
                    <div class="tl-bar g-custom"
                         style="left: {{ $pct($span['start_ms']) }}%; width: {{ max(0.5, $pct($span['end_ms']) - $pct($span['start_ms'])) }}%"
                         title="{{ $span['name'] }} — {{ number_format($span['duration_ms'], 2) }} ms">
                        {{ number_format($span['duration_ms'], 1) }} ms
                    </div>
                </div>
            </div>
        @endforeach

        @if(count($profile['queries'] ?? []))
            <div class="tl-row">
                <div class="tl-label muted">Queries ({{ count($profile['queries']) }})</div>
                <div class="tl-track">
                    @foreach($profile['queries'] as $q)
                        <div class="tl-bar g-query"
                             style="left: {{ $pct($q['start_ms']) }}%; width: {{ max(0.4, $pct($q['end_ms']) - $pct($q['start_ms'])) }}%"
                             title="{{ number_format($q['duration_ms'], 2) }} ms — {{ \Illuminate\Support\Str::limit($q['sql'], 120) }}"></div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="legend">
            <span><i style="background:var(--boot)"></i> Bootstrap</span>
            <span><i style="background:var(--mw)"></i> Middleware/Routing</span>
            <span><i style="background:var(--ctrl)"></i> Controller</span>
            <span><i style="background:var(--term)"></i> Terminate</span>
            <span><i style="background:var(--query)"></i> Query</span>
            @if(count($profile['marks'] ?? []))<span><i style="background:var(--mark)"></i> Mark</span>@endif
        </div>
    </div>

    @if(count($profile['queries'] ?? []))
        <div class="section-title">Database queries · {{ number_format($profile['query_time_ms'] ?? 0, 2) }} ms total</div>
        <div class="panel">
            <table class="q">
                <thead>
                    <tr><th style="width:44px">#</th><th>Query</th><th style="width:110px">Connection</th><th style="width:90px">@ ms</th><th style="width:90px">Duration</th></tr>
                </thead>
                <tbody>
                    @foreach($profile['queries'] as $i => $q)
                        <tr>
                            <td class="muted mono">{{ $i + 1 }}</td>
                            <td>
                                <div class="sql">{{ $q['sql'] }}</div>
                                @if(!empty($q['bindings']))
                                    <div class="muted mono" style="font-size:11px; margin-top:4px">
                                        [{{ implode(', ', array_map(function($b){ return is_scalar($b) ? $b : json_encode($b); }, $q['bindings'])) }}]
                                    </div>
                                @endif
                            </td>
                            <td class="muted mono">{{ $q['connection'] ?? '—' }}</td>
                            <td class="q-time muted">{{ number_format($q['start_ms'], 1) }}</td>
                            <td class="q-time {{ $q['duration_ms'] >= $slowQ ? 'slow' : '' }}">{{ number_format($q['duration_ms'], 2) }} ms</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if(count($profile['marks'] ?? []))
        <div class="section-title">Timeline marks</div>
        <div class="panel">
            <table class="q">
                <thead><tr><th>Label</th><th style="width:120px">@ ms</th></tr></thead>
                <tbody>
                    @foreach($profile['marks'] as $mark)
                        <tr><td class="mono">{{ $mark['label'] }}</td><td class="q-time muted">{{ number_format($mark['at_ms'], 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
