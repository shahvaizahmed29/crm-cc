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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-linear-to-br from-slate-100 via-slate-50 to-sky-50 text-slate-900 font-sans antialiased">
    <header
        class="sticky top-0 z-40 border-b border-slate-200/80 bg-slate-900/95 text-white shadow-lg backdrop-blur"
        x-data="{ mobileMenuOpen: false }"
    >
        <div class="mx-auto max-w-360 px-4 sm:px-6 lg:px-8">
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
                    <a href="{{ route('credit-reports.index') }}" class="relative shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('credit-reports.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        CR Reports
                        <span class="js-cr-pending-badge ml-1 hidden items-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                            <span class="mr-1">🔔</span><span class="js-cr-pending-count">0</span>
                        </span>
                    </a>
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
                    <a href="{{ route('credit-reports.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('credit-reports.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        CR Reports
                        <span class="js-cr-pending-badge ml-1 hidden items-center rounded-full bg-rose-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                            <span class="mr-1">🔔</span><span class="js-cr-pending-count">0</span>
                        </span>
                    </a>
                    @endif
                </nav>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-360 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
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
    <script defer src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script defer src="{{ asset('js/jquery.creditCardValidator.js') }}"></script>
    @if(auth()->user()->isAdmin())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pollIntervalMs = 3000;
            const endpoint = '{{ route('credit-reports.pending-count') }}';
            const alertSoundUrl = '{{ asset('sounds/notification.wav') }}';
            const crSoundEnabled = {{ \App\Models\Setting::get('cr_sound_notifications_enabled', '1') === '1' ? 'true' : 'false' }};
            const isCrReportsPage = {{ request()->routeIs('credit-reports.*') ? 'true' : 'false' }};
            const soundCooldownMs = 9000;
            // Testing toggle: set true to force beep every poll cycle.
            const forceCrSoundTest = false;
            let audioContext = null;
            let audioUnlocked = false;
            let alertAudio = null;
            let lastSoundPlayedAt = 0;

            const updateBadges = (count) => {
                const badges = document.querySelectorAll('.js-cr-pending-badge');
                const counts = document.querySelectorAll('.js-cr-pending-count');
                counts.forEach((el) => {
                    el.textContent = String(count);
                });
                badges.forEach((badge) => {
                    if (count > 0) {
                        badge.classList.remove('hidden');
                        badge.classList.add('inline-flex');
                    } else {
                        badge.classList.add('hidden');
                        badge.classList.remove('inline-flex');
                    }
                });
            };

            const playFallbackTone = async () => {
                try {
                    if (!audioUnlocked) {
                        return false;
                    }

                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) {
                        return false;
                    }

                    if (!audioContext) {
                        audioContext = new AudioCtx();
                    }
                    if (audioContext.state === 'suspended') {
                        await audioContext.resume();
                    }

                    const now = audioContext.currentTime;
                    const beep = (startAt, freq) => {
                        const osc = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(freq, startAt);
                        gain.gain.setValueAtTime(0.0001, startAt);
                        gain.gain.exponentialRampToValueAtTime(0.08, startAt + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, startAt + 0.22);
                        osc.connect(gain);
                        gain.connect(audioContext.destination);
                        osc.start(startAt);
                        osc.stop(startAt + 0.24);
                    };

                    // Double beep to make notification obvious.
                    beep(now, 920);
                    beep(now + 0.28, 1040);
                    return true;
                } catch (e) {
                    // Ignore browser autoplay/audio context limitations.
                    return false;
                }
            };

            const ensureAlertAudio = () => {
                if (alertAudio) {
                    return alertAudio;
                }

                const audio = new Audio(alertSoundUrl);
                audio.preload = 'auto';
                audio.volume = 1;
                alertAudio = audio;

                return alertAudio;
            };

            const playAlertSound = async () => {
                const audio = ensureAlertAudio();
                if (audio) {
                    try {
                        audio.pause();
                        audio.currentTime = 0;
                        await audio.play();
                        return true;
                    } catch (e) {
                        // Fall back to generated tone if file playback fails.
                    }
                }

                return playFallbackTone();
            };

            const unlockAudio = async () => {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    let unlocked = false;

                    if (AudioCtx) {
                        if (!audioContext) {
                            audioContext = new AudioCtx();
                        }
                        if (audioContext.state === 'suspended') {
                            await audioContext.resume();
                        }
                        unlocked = audioContext.state === 'running';
                    }

                    const audio = ensureAlertAudio();
                    if (audio) {
                        try {
                            audio.pause();
                            audio.currentTime = 0;
                            await audio.play();
                            audio.pause();
                            audio.currentTime = 0;
                            unlocked = true;
                        } catch (e) {
                            // Ignore; fallback tone may still work after interaction.
                        }
                    }

                    audioUnlocked = unlocked;
                } catch (e) {
                    // Ignore; browser may still require another interaction.
                }
            };

            const poll = async () => {
                try {
                    const response = await fetch(endpoint, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    const count = Number(data?.count ?? 0) || 0;
                    const shouldBugByPending = count > 0 && !isCrReportsPage;

                    const shouldPlaySound = forceCrSoundTest || (crSoundEnabled && shouldBugByPending);
                    const nowMs = Date.now();
                    if (shouldPlaySound && audioUnlocked && (nowMs - lastSoundPlayedAt >= soundCooldownMs)) {
                        lastSoundPlayedAt = nowMs;
                        void playAlertSound();
                    }

                    updateBadges(count);
                } catch (e) {
                    // Silent fail; next poll will retry.
                }
            };

            ['click', 'keydown', 'touchstart', 'pointerdown'].forEach((eventName) => {
                window.addEventListener(eventName, () => { void unlockAudio(); }, { once: true, passive: true });
            });
            poll();
            setInterval(poll, pollIntervalMs);
        });
    </script>
    @endif
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('scripts')
</body>
</html>
