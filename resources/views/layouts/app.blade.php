<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) — Call Center CRM</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-linear-to-br from-slate-100 via-slate-50 to-sky-50 text-slate-900 font-sans antialiased">
    <header
        class="sticky top-0 z-40 border-b border-slate-200/80 bg-slate-900/95 text-white shadow-lg backdrop-blur"
        x-data="{ mobileMenuOpen: false }"
    >
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between gap-3 md:h-18">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-700 text-slate-200 hover:bg-slate-800 md:hidden"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        aria-label="Toggle navigation"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 truncate font-semibold tracking-wide text-white">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-sky-500/20 text-sky-300 ring-1 ring-sky-400/30">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </span>
                        <span class="truncate">CC CRM</span>
                    </a>
                </div>

                <nav class="hidden min-w-0 flex-1 items-center gap-2 overflow-x-auto px-2 md:flex">
                    <a href="{{ route('dashboard') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('dashboard') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Dashboard</a>
                    @if(auth()->user()->isAgent())
                    <a href="{{ route('agent.queue') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('agent.queue') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Queue</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('leads.new.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">New Leads</a>
                    @endif
                    <a href="{{ route('leads.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('leads.*') && !request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Leads</a>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('reports.sales') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('reports.sales') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Sales</a>
                    <a href="{{ route('users.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('users.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Users</a>
                    <a href="{{ route('settings.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('settings.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Settings</a>
                    <div
                        x-data="{
                            pollIntervalMs: {{ (int) config('crm.cr_nav_poll_interval_ms', 15000) }},
                            pendingCount: 0,
                            poll() {
                                fetch('{{ route('credit-reports.pending-count') }}', { headers: { 'Accept': 'application/json' } })
                                    .then((response) => response.ok ? response.json() : null)
                                    .then((data) => {
                                        if (data && typeof data.count !== 'undefined') {
                                            this.pendingCount = Number(data.count) || 0;
                                        }
                                    })
                                    .catch(() => {});
                            }
                        }"
                        x-init="poll(); setInterval(() => poll(), pollIntervalMs)"
                        class="relative"
                    >
                        <a href="{{ route('credit-reports.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('credit-reports.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            CR Reports
                            <span x-cloak x-show="pendingCount > 0" class="ml-1 inline-flex items-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                                <span class="mr-1">🔔</span><span x-text="pendingCount"></span>
                            </span>
                        </a>
                    </div>
                    @endif
                </nav>

                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden rounded-md bg-slate-800 px-2.5 py-1 text-xs text-slate-300 xl:inline">{{ auth()->user()->displayName() }}</span>
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" class="flex items-center gap-1 rounded-xl border border-slate-700 px-3 py-2 text-[13px] font-semibold text-white hover:bg-slate-800">
                            <span>{{ auth()->user()->roles->first()?->name ?? 'User' }}</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div x-show="open" x-cloak @click.outside="open = false"
                             class="absolute right-0 z-50 mt-2 w-48 rounded-xl bg-white py-1 text-slate-900 shadow-xl ring-1 ring-black/5">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-100">Log out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div x-cloak x-show="mobileMenuOpen" class="border-t border-slate-800 py-2 md:hidden">
                <nav class="grid gap-1 pb-2">
                    <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Dashboard</a>
                    @if(auth()->user()->isAgent())
                    <a href="{{ route('agent.queue') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('agent.queue') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Queue</a>
                    @endif
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('leads.new.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">New Leads</a>
                    @endif
                    <a href="{{ route('leads.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('leads.*') && !request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Leads</a>
                    @if(auth()->user()->isAdmin())
                    <a href="{{ route('reports.sales') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('reports.sales') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Sales</a>
                    <a href="{{ route('users.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Users</a>
                    <a href="{{ route('settings.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('settings.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Settings</a>
                    <a href="{{ route('credit-reports.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('credit-reports.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">CR Reports</a>
                    @endif
                </nav>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-sm">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="mt-10 border-t border-slate-200/80 bg-white/80 py-4 backdrop-blur">
        <div class="mx-auto max-w-7xl px-4 text-center text-xs text-slate-500 sm:px-6 lg:px-8">
            &copy; {{ date('Y') }} Call Center CRM
        </div>
    </footer>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
