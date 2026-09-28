<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'AlphaMachine • Pro Trading Terminal' }}</title>

    <!-- Anti-FOUC Theme Initializer -->
    <script>
        if (localStorage.theme === 'light' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>

    <!-- Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Vite Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="{
        mobileSidebarOpen: false,
        dark: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.dark = !this.dark;
            if (this.dark) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: this.dark } }));
        }
    }"
    class="bg-canvas text-text-primary font-sans antialiased min-h-screen selection:bg-accent/20 selection:text-accent"
>

    <!-- Mobile Sidebar Drawer Backdrop Overlay -->
    <div
        x-show="mobileSidebarOpen"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="mobileSidebarOpen = false"
        class="fixed inset-0 bg-black/60 backdrop-blur-xs z-40 lg:hidden"
        style="display: none;"
    ></div>

    <!-- Left Sidebar (Desktop Fixed & Mobile Slide-Out) -->
    <aside
        class="fixed inset-y-0 left-0 z-50 w-64 bg-surface border-r border-border-subtle flex flex-col justify-between transition-transform duration-200 lg:translate-x-0"
        :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        <!-- Top Section: Brand & Navigation -->
        <div class="flex-1 flex flex-col overflow-y-auto px-4 py-5 space-y-6">

            <!-- Brand Logo -->
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 group">
                    <div class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent group-hover:border-accent/40 transition">
                        <x-app-icon name="activity" class="w-4 h-4" />
                    </div>
                    <div class="flex flex-col">
                        <span class="font-mono text-sm font-bold tracking-wider text-text-primary uppercase">ALPHAMACHINE</span>
                        <span class="text-[10px] font-mono text-text-muted -mt-1 tracking-widest uppercase">Pro Terminal</span>
                    </div>
                </a>

                <!-- Mobile Close Button -->
                <button
                    @click="mobileSidebarOpen = false"
                    type="button"
                    class="lg:hidden p-1.5 rounded-lg text-text-muted hover:text-text-primary hover:bg-surface-hover transition"
                >
                    <x-app-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <!-- WebSocket Connection Status Pill -->
            <div
                x-data="{ connected: false }"
                x-init="
                    if (window.Echo) {
                        window.Echo.connector.pusher.connection.bind('connected', () => connected = true);
                        window.Echo.connector.pusher.connection.bind('disconnected', () => connected = false);
                        window.Echo.connector.pusher.connection.bind('error', () => connected = false);
                    }
                "
                class="flex items-center justify-between px-3 py-1.5 rounded-lg text-[11px] font-mono border"
                :class="connected ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-surface-elevated text-text-muted border-border-subtle'"
            >
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full" :class="connected ? 'bg-trade-profit animate-pulse' : 'bg-text-muted'"></span>
                    <span x-text="connected ? 'Reverb Live Connected' : 'Sync Standby'"></span>
                </div>
                <x-app-icon name="wifi" class="w-3.5 h-3.5" />
            </div>

            <!-- System Harness (TradingView Remote & OmniRoute AI) -->
            <x-system-harness-widget variant="sidebar" />

            <!-- Primary Action CTA -->
            <a
                href="{{ route('trades.create') }}"
                class="w-full py-2.5 px-3 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold tracking-wider uppercase transition shadow-sm flex items-center justify-center gap-2 cursor-pointer"
            >
                <x-app-icon name="plus" class="w-4 h-4" />
                <span>Log New Trade</span>
            </a>

            <!-- Vertical Navigation Menu -->
            <div class="space-y-1">
                <span class="block px-3 text-[10px] font-mono uppercase tracking-widest text-text-muted mb-2">Main Terminal</span>

                <!-- Dashboard -->
                <a
                    href="{{ route('dashboard') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('dashboard') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="bar-chart-3" class="w-4 h-4" />
                    <span>Dashboard</span>
                </a>

                <!-- Coin Hunter (Spot Radar) -->
                <a
                    href="{{ route('signals.index') }}"
                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('signals.*') ? 'bg-surface-elevated text-trade-profit border border-trade-profit/30 font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <div class="flex items-center gap-3">
                        <x-app-icon name="target" class="w-4 h-4 text-trade-profit" />
                        <span>Coin Hunter</span>
                    </div>
                    <span class="relative flex h-2 w-2" title="Live Market Scanner Active">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-trade-profit opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-trade-profit"></span>
                    </span>
                </a>

                <!-- Smart Screen Future (2-Way Futures Scanner) -->
                <a
                    href="{{ route('futures-signals.index') }}"
                    class="flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('futures-signals.*') ? 'bg-surface-elevated text-accent border border-accent/30 font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <div class="flex items-center gap-3">
                        <x-app-icon name="zap" class="w-4 h-4 text-accent" />
                        <span>Smart Screen Future</span>
                    </div>
                    <span class="relative flex h-2 w-2" title="Live 2-Way Futures Scanner Active">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                    </span>
                </a>

                <!-- Simulasi Trade & Backtest -->
                <a
                    href="{{ route('simulation.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('simulation.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="play-circle" class="w-4 h-4 text-accent" />
                    <span>Simulasi Trade</span>
                </a>

                <!-- Berita Dunia -->
                <a
                    href="{{ route('news.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('news.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="newspaper" class="w-4 h-4" />
                    <span>Berita Dunia</span>
                </a>

                <!-- Kalender Ekonomi -->
                <a
                    href="{{ route('calendar.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('calendar.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="calendar-days" class="w-4 h-4" />
                    <span>Kalender Ekonomi</span>
                </a>

                <!-- Money Flow & Whale Tracker -->
                <a
                    href="{{ route('money-flow.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('money-flow.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="waves" class="w-4 h-4" />
                    <span>Money Flow</span>
                </a>

                <!-- Trade Journal -->
                <a
                    href="{{ route('trades.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('trades.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="book-open" class="w-4 h-4" />
                    <span>Trade Journal</span>
                </a>

                <!-- Analytics & Psychology -->
                <a
                    href="{{ route('analytics.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('analytics.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="target" class="w-4 h-4" />
                    <span>Analytics & Psychology</span>
                </a>

                <!-- Accounts -->
                <a
                    href="{{ route('accounts.index') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('accounts.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="wallet" class="w-4 h-4" />
                    <span>Trading Accounts</span>
                </a>

                <!-- Account & Password Settings -->
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs font-medium transition {{ request()->routeIs('profile.*') ? 'bg-surface-elevated text-accent border border-border-subtle font-semibold shadow-xs' : 'text-text-secondary hover:text-text-primary hover:bg-surface-hover' }}"
                >
                    <x-app-icon name="sliders" class="w-4 h-4" />
                    <span>Profile & Password</span>
                </a>
            </div>
        </div>

        <!-- Bottom Sidebar Section: User Profile & Utility -->
        <div class="p-4 border-t border-border-subtle bg-surface-elevated/40 space-y-3">

            <!-- User Info (Clickable to Edit Profile) -->
            @auth
                <a href="{{ route('profile.edit') }}" class="flex items-center justify-between group p-1.5 -m-1.5 rounded-lg hover:bg-surface-hover transition" title="Edit Profile & Password">
                    <div class="flex items-center gap-2.5 truncate">
                        <div class="w-7 h-7 rounded-md bg-accent/10 border border-accent/20 flex items-center justify-center font-mono text-xs font-bold text-accent group-hover:border-accent/40 transition">
                            {{ substr(auth()->user()->name, 0, 2) }}
                        </div>
                        <div class="truncate">
                            <div class="text-xs font-medium text-text-primary group-hover:text-accent transition truncate">{{ auth()->user()->name }}</div>
                            <div class="text-[10px] font-mono text-text-muted truncate">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                    <x-app-icon name="chevron-right" class="w-3.5 h-3.5 text-text-muted group-hover:text-accent transition" />
                </a>
            @endauth

            <!-- Bottom Action Row: Theme Toggle & Logout -->
            <div class="flex items-center justify-between pt-2 border-t border-border-subtle/60">
                <!-- Theme Toggle Button -->
                <button
                    @click="toggleTheme()"
                    type="button"
                    class="p-2 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-hover transition border border-border-subtle cursor-pointer"
                    title="Toggle Dark / Light Mode"
                >
                    <template x-if="dark">
                        <x-app-icon name="sun" class="w-4 h-4 text-amber-400" />
                    </template>
                    <template x-if="!dark">
                        <x-app-icon name="moon" class="w-4 h-4 text-slate-700" />
                    </template>
                </button>

                <!-- Logout Button with SweetAlert2 -->
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button
                        type="button"
                        onclick="handleLogout()"
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-mono text-text-secondary hover:text-trade-loss hover:bg-trade-loss-bg transition border border-border-subtle cursor-pointer"
                        title="Sign Out"
                    >
                        <x-app-icon name="log-out" class="w-3.5 h-3.5" />
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Container (Padded left on Desktop) -->
    <div class="lg:pl-64 flex flex-col min-h-screen">

        <!-- Desktop Top Navigation Header Bar -->
        <header class="hidden lg:flex items-center justify-between h-14 px-6 sm:px-8 border-b border-border-subtle bg-surface/80 backdrop-blur-md sticky top-0 z-30">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1.5 px-2 py-0.5 rounded bg-surface-elevated text-accent border border-border-subtle font-mono text-[11px] font-bold tracking-wider">
                    <x-app-icon name="activity" class="w-3.5 h-3.5 text-accent" />
                    <span>ALPHAMACHINE</span>
                </span>
                <span class="text-xs font-mono text-text-muted">/</span>
                <span class="text-xs font-mono text-text-secondary tracking-wide">Triangulation Quant Terminal</span>
            </div>

            <div class="flex items-center gap-3">
                <!-- System Status Harness: TV Remote :9222 + OmniRoute Gemini Flash 3.8 -->
                <x-system-harness-widget variant="header" />
            </div>
        </header>

        <!-- Mobile Top Navigation Header -->
        <header class="sticky top-0 z-30 lg:hidden flex items-center justify-between h-14 px-4 border-b border-border-subtle bg-surface/90 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <button
                    @click="mobileSidebarOpen = true"
                    type="button"
                    class="p-2 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-hover transition border border-border-subtle"
                    aria-label="Open sidebar"
                >
                    <x-app-icon name="menu" class="w-4 h-4" />
                </button>

                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-md bg-accent/10 flex items-center justify-center text-accent">
                        <x-app-icon name="activity" class="w-3.5 h-3.5" />
                    </div>
                    <span class="font-mono text-xs font-bold uppercase text-text-primary">AlphaMachine</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Mobile TV Remote status pill -->
                <x-system-harness-widget variant="header" />

                <button
                    @click="toggleTheme()"
                    type="button"
                    class="p-1.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-hover border border-border-subtle"
                >
                    <template x-if="dark">
                        <x-app-icon name="sun" class="w-3.5 h-3.5 text-amber-400" />
                    </template>
                    <template x-if="!dark">
                        <x-app-icon name="moon" class="w-3.5 h-3.5 text-slate-700" />
                    </template>
                </button>
                <a href="{{ route('trades.create') }}" class="p-1.5 bg-accent text-white rounded-lg">
                    <x-app-icon name="plus" class="w-3.5 h-3.5" />
                </a>
            </div>
        </header>

        <!-- Main Body -->
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            {{ $slot ?? '' }}
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="border-t border-border-subtle bg-surface/30 py-4 text-xs text-text-muted">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
                <div class="flex items-center gap-2 font-mono text-[11px]">
                    <span class="w-2 h-2 rounded-full bg-trade-profit"></span>
                    <span>AlphaJournal v1.0 • Left Sidebar Terminal</span>
                </div>
                <div class="font-mono text-[11px] text-text-muted">
                    Fintech Execution • Discipline Over Luck
                </div>
            </div>
        </footer>
    </div>

    <!-- Global SweetAlert Flash Handler & WebSocket Echo Listener -->
    <script>
        function handleLogout() {
            if (window.Alert) {
                window.Alert.confirmDelete(
                    'Sign Out of Terminal?',
                    'Are you sure you want to end your current session?'
                ).then((result) => {
                    if (result.isConfirmed) {
                        document.getElementById('logout-form').submit();
                    }
                });
            } else {
                if (confirm('Sign out?')) {
                    document.getElementById('logout-form').submit();
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            @if(session('success'))
                if (window.Alert) {
                    window.Alert.toast(@json(session('success')), 'success');
                }
            @endif

            @if(session('error'))
                if (window.Alert) {
                    window.Alert.error('Error', @json(session('error')));
                }
            @endif

            @php $currentUserId = auth()->id() ?? (\App\Models\User::first()->id ?? 1); @endphp
            if (window.Echo) {
                window.Echo.private('user.{{ $currentUserId }}')
                    .listen('TradeCreated', (e) => {
                        if (window.Alert) {
                            window.Alert.liveUpdateToast(e.message, 'info');
                        }
                        window.dispatchEvent(new CustomEvent('trade-created', { detail: e }));
                    })
                    .listen('TradeUpdated', (e) => {
                        if (window.Alert) {
                            window.Alert.liveUpdateToast(e.message, e.is_win ? 'profit' : 'loss');
                        }
                        window.dispatchEvent(new CustomEvent('trade-updated', { detail: e }));
                    })
                    .listen('TradeDeleted', (e) => {
                        if (window.Alert) {
                            window.Alert.toast(e.message, 'info');
                        }
                        window.dispatchEvent(new CustomEvent('trade-deleted', { detail: e }));
                    });

                // Listen to public Spot Signal Radar channel
                window.Echo.channel('spot-signals')
                    .listen('SpotSignalDetected', (e) => {
                        if (window.Alert) {
                            window.Alert.liveUpdateToast(e.message, 'profit');
                        }
                        window.dispatchEvent(new CustomEvent('spot-signal-detected', { detail: e }));
                    });
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
