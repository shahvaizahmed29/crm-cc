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
                    <a href="{{ route('deal-sheets.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('deal-sheets.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Deal sheets</a>
                    <a href="{{ route('leads.new.index') }}" class="relative shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        New Leads
                        <span class="js-new-leads-badge ml-1 hidden items-center rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                            <span class="js-new-leads-count">0</span>
                        </span>
                    </a>
                    @endif
                    <a href="{{ route('leads.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('leads.*') && !request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Leads</a>
                    <a href="{{ route('callbacks.index') }}" class="shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-[13px] font-semibold {{ request()->routeIs('callbacks.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Callbacks</a>
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

                <div class="flex shrink-0 items-center gap-2">
                    <div class="relative" x-data="notificationDropdown()" data-recent-url="{{ route('notifications.recent') }}" @notifications-updated.window="onUpdate($event.detail)" x-init="fetchRecent()">
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="Toggle notifications" class="inline-flex items-center rounded-xl px-3 py-2 text-[13px] font-semibold {{ request()->routeIs('notifications.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <span class="sr-only md:not-sr-only md:inline">Notifications</span>
                            <span class="js-notification-badge ml-1 items-center rounded-full bg-sky-500 px-1.5 py-0.5 text-[10px] font-semibold text-white" :class="count > 0 ? 'inline-flex' : 'hidden'">
                                <span class="mr-0.5">🔔</span><span class="js-notification-count" x-text="count"></span>
                            </span>
                            <svg class="ml-1 h-4 w-4 shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" @click.outside="open = false"
                             class="absolute right-0 top-full z-[100] mt-2 w-[28rem] max-w-[calc(100vw-2rem)] max-h-[min(28rem,70vh)] overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-xl ring-1 ring-black/5">
                            <div class="border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600">Notifications</div>
                            <div class="max-h-80 overflow-y-auto">
                                <template x-if="items.length === 0">
                                    <p class="px-3 py-4 text-center text-sm text-slate-500">No notifications yet</p>
                                </template>
                                <template x-for="n in items" :key="n.id">
                                    <a :href="n.open_url" class="block border-b border-slate-100 px-3 py-2.5 text-left last:border-b-0 transition-colors"
                                       :class="n.read_at ? 'bg-white hover:bg-slate-50' : 'border-l-4 border-l-sky-500 bg-sky-50/70 hover:bg-sky-100/80'">
                                        <p class="text-sm font-medium" :class="n.read_at ? 'text-slate-600' : 'text-slate-900'" x-text="n.title"></p>
                                        <p class="mt-0.5 text-xs line-clamp-2" :class="n.read_at ? 'text-slate-500' : 'text-slate-600'" x-text="n.message || ''"></p>
                                        <p class="mt-1 text-xs text-slate-400" x-text="n.notify_at_human"></p>
                                    </a>
                                </template>
                            </div>
                            <div class="border-t border-slate-200 bg-slate-50 px-3 py-2">
                                <a href="{{ route('notifications.index') }}" class="block text-center text-sm font-medium text-sky-600 hover:text-sky-500">View all</a>
                            </div>
                        </div>
                    </div>
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
                    <a href="{{ route('deal-sheets.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('deal-sheets.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Deal sheets</a>
                    <a href="{{ route('leads.new.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        New Leads
                        <span class="js-new-leads-badge ml-1 hidden items-center rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                            <span class="js-new-leads-count">0</span>
                        </span>
                    </a>
                    @endif
                    <a href="{{ route('leads.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('leads.*') && !request()->routeIs('leads.new.index') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Leads</a>
                    <a href="{{ route('callbacks.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('callbacks.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Callbacks</a>
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
                    <a href="{{ route('notifications.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('notifications.*') ? 'bg-slate-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">Notifications</a>
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
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('notificationDropdown', () => ({
                open: false,
                count: 0,
                items: [],
                onUpdate(payload) {
                    this.count = payload.count ?? 0;
                    this.items = payload.items ?? [];
                },
                fetchRecent() {
                    const url = this.$el.dataset.recentUrl;
                    if (!url) return;
                    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                        .then(r => r.ok ? r.json() : { count: 0, items: [] })
                        .then(d => { this.count = d.count ?? 0; this.items = d.items ?? []; })
                        .catch(() => {});
                }
            }));
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pollIntervalSeconds = Math.max(1, Math.min(300, {{ (int) config('app.notification_poll_seconds', 3) }}));
            const pollIntervalMs = pollIntervalSeconds * 1000;
            const recentEndpoint = '{{ route('notifications.recent') }}';
            const crNotificationsEndpoint = '{{ route('notifications.unread-count', ['type_prefix' => 'cr']) }}';
            const newLeadsCountEndpoint = '{{ route('leads.new.count') }}';
            const alertSoundUrl = '{{ asset('sounds/notification.wav') }}';
            const crSoundEnabled = {{ \App\Models\Setting::get('cr_sound_notifications_enabled', '1') === '1' ? 'true' : 'false' }};
            const isCrReportsPage = {{ request()->routeIs('credit-reports.*') ? 'true' : 'false' }};
            const isAdmin = {{ auth()->user()->isAdmin() ? 'true' : 'false' }};
            const soundCooldownMs = 9000;
            // Testing toggle: set true to force beep every poll cycle.
            const forceCrSoundTest = false;
            let audioContext = null;
            let audioUnlocked = false;
            let alertAudio = null;
            let lastSoundPlayedAt = 0;

            const updateBadge = (badgeSelector, countSelector, count) => {
                const badges = document.querySelectorAll(badgeSelector);
                const counts = document.querySelectorAll(countSelector);
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
                    const response = await fetch(recentEndpoint, {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    const totalCount = Number(data?.count ?? 0) || 0;
                    updateBadge('.js-notification-badge', '.js-notification-count', totalCount);
                    window.dispatchEvent(new CustomEvent('notifications-updated', { detail: { count: totalCount, items: data?.items ?? [] } }));

                    let crCount = 0;
                    if (isAdmin) {
                        const crResponse = await fetch(crNotificationsEndpoint, {
                            method: 'GET',
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (crResponse.ok) {
                            const crData = await crResponse.json();
                            crCount = Number(crData?.count ?? 0) || 0;
                        }
                    }

                    updateBadge('.js-cr-pending-badge', '.js-cr-pending-count', crCount);

                    let newLeadsCount = 0;
                    if (isAdmin) {
                        try {
                            const nlResponse = await fetch(newLeadsCountEndpoint, {
                                method: 'GET',
                                headers: { 'Accept': 'application/json' },
                                credentials: 'same-origin',
                            });
                            if (nlResponse.ok) {
                                const nlData = await nlResponse.json();
                                newLeadsCount = Number(nlData?.count ?? 0) || 0;
                            }
                        } catch (e) {
                            // Silent fail
                        }
                    }
                    updateBadge('.js-new-leads-badge', '.js-new-leads-count', newLeadsCount);

                    const shouldBugByPending = crCount > 0 && !isCrReportsPage;
                    const shouldPlaySound = forceCrSoundTest || (isAdmin && crSoundEnabled && shouldBugByPending);
                    const nowMs = Date.now();
                    if (shouldPlaySound && audioUnlocked && (nowMs - lastSoundPlayedAt >= soundCooldownMs)) {
                        lastSoundPlayedAt = nowMs;
                        void playAlertSound();
                    }
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('scripts')
</body>
</html>
