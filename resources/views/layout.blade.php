<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Time Machine') · Laravel Time Machine</title>
    <style>
        :root {
            --bg: #0d1117; --panel: #161b22; --panel-2: #1c2230; --border: #2b3444;
            --text: #e6edf3; --muted: #8b98a9; --accent: #7c5cff; --accent-2: #22d3ee;
            --ok: #3fb950; --warn: #d29922; --bad: #f85149;
            --boot: #7c5cff; --mw: #22d3ee; --ctrl: #3fb950; --term: #d29922; --query: #f0883e; --mark: #f85149;
            --radius: 10px; --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            --sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font-family: var(--sans);
            font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
        a { color: var(--accent-2); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 24px 20px 64px; }
        header.top { display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding-bottom: 20px; margin-bottom: 24px; border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .brand { display: flex; align-items: center; gap: 12px; }
        .brand .logo { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); font-size: 20px; }
        .brand h1 { font-size: 17px; margin: 0; letter-spacing: .2px; }
        .brand small { color: var(--muted); font-weight: 400; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px;
            background: var(--panel-2); border: 1px solid var(--border); color: var(--text); cursor: pointer;
            font-size: 13px; font-family: inherit; }
        .btn:hover { border-color: var(--accent); text-decoration: none; }
        .btn.danger:hover { border-color: var(--bad); color: var(--bad); }
        .panel { background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius); }
        .muted { color: var(--muted); }
        .mono { font-family: var(--mono); }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600;
            font-family: var(--mono); border: 1px solid var(--border); }
        .method-GET { color: #3fb950; } .method-POST { color: #d29922; }
        .method-PUT, .method-PATCH { color: #22d3ee; } .method-DELETE { color: #f85149; }
        .status-2 { color: var(--ok); } .status-3 { color: var(--accent-2); }
        .status-4 { color: var(--warn); } .status-5 { color: var(--bad); }
        .empty { text-align: center; padding: 72px 20px; color: var(--muted); }
        .empty .big { font-size: 44px; margin-bottom: 12px; }
        footer.foot { margin-top: 40px; text-align: center; color: var(--muted); font-size: 12px; }
        @yield('styles')
    </style>
</head>
<body>
    <div class="wrap">
        <header class="top">
            <a href="{{ url(config('time-machine.dashboard.path', 'time-machine')) }}" class="brand" style="color:inherit">
                <div class="logo">⏱</div>
                <div>
                    <h1>Laravel Time Machine</h1>
                    <small>Request lifecycle profiler</small>
                </div>
            </a>
            <div style="display:flex; gap:10px; align-items:center">
                @yield('actions')
            </div>
        </header>

        @yield('content')

        <footer class="foot">
            Laravel Time Machine — <span class="mono">jaydeep/laravel-time-machine</span>
        </footer>
    </div>
    @yield('scripts')
</body>
</html>
