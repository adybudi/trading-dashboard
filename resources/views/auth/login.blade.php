<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sign In • ALPHAMACHINE Pro Terminal</title>

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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-text-primary font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-accent/20 selection:text-accent">

    <!-- Top Utility Bar -->
    <header class="p-4 flex items-center justify-between max-w-7xl mx-auto w-full">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                <x-app-icon name="activity" class="w-4 h-4" />
            </div>
            <span class="font-mono text-sm font-bold tracking-wider uppercase text-text-primary">ALPHAMACHINE</span>
        </div>

        <!-- Light / Dark Mode Toggle -->
        <button
            x-data="{
                dark: document.documentElement.classList.contains('dark'),
                toggle() {
                    this.dark = !this.dark;
                    if (this.dark) {
                        document.documentElement.classList.add('dark');
                        localStorage.setItem('theme', 'dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.setItem('theme', 'light');
                    }
                }
            }"
            @click="toggle()"
            type="button"
            class="p-2 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-hover transition border border-border-subtle cursor-pointer"
            title="Toggle Light / Dark Mode"
        >
            <template x-if="dark">
                <x-app-icon name="sun" class="w-4 h-4 text-amber-400" />
            </template>
            <template x-if="!dark">
                <x-app-icon name="moon" class="w-4 h-4 text-slate-700" />
            </template>
        </button>
    </header>

    <!-- Main Centered Login Card -->
    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-surface border border-border-subtle rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">

            <div class="space-y-1.5 text-center">
                <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono uppercase">
                    Terminal Authentication
                </h1>
                <p class="text-xs text-text-secondary">
                    Sign in to access your trading journal and live performance metrics.
                </p>
            </div>

            <!-- Validation Error Alert -->
            @if ($errors->any())
                <div class="p-3 rounded-lg bg-trade-loss-bg border border-trade-loss/20 text-trade-loss text-xs font-mono">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Login Form -->
            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <!-- Email Input -->
                <div class="space-y-1">
                    <label class="block text-xs font-mono uppercase text-text-secondary">Email Address</label>
                    <div class="relative">
                        <input
                            type="email"
                            name="email"
                            value="{{ old('email', 'trader@example.com') }}"
                            required
                            autofocus
                            placeholder="trader@example.com"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono placeholder:text-text-muted"
                        />
                    </div>
                </div>

                <!-- Password Input -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-mono uppercase text-text-secondary">Password</label>
                    </div>
                    <div class="relative">
                        <input
                            type="password"
                            name="password"
                            value="password"
                            required
                            placeholder="••••••••"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono placeholder:text-text-muted"
                        />
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs font-mono">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-text-secondary">
                        <input type="checkbox" name="remember" checked class="rounded border-border-muted text-accent focus:ring-0">
                        <span>Remember session</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="w-full py-2.5 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold tracking-wider uppercase transition shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                >
                    <x-app-icon name="lock" class="w-3.5 h-3.5" />
                    <span>Sign In to Terminal</span>
                </button>
            </form>

            <!-- 1-Click Quick Demo Login Button -->
            <div class="pt-2 border-t border-border-subtle space-y-3">
                <a
                    href="{{ route('demo.login') }}"
                    class="w-full py-2 bg-surface-hover hover:bg-border-subtle text-text-primary rounded-lg text-xs font-mono font-medium border border-border-subtle transition flex items-center justify-center gap-2 cursor-pointer"
                >
                    <x-app-icon name="user" class="w-3.5 h-3.5 text-accent" />
                    <span>1-Click Demo Trader Login</span>
                </a>

                <div class="text-center text-xs text-text-muted font-mono">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="text-accent hover:underline font-semibold">Create Account</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 text-center text-xs text-text-muted font-mono">
        AlphaJournal v1.0 • Fintech Execution Terminal
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @if(session('success'))
                if (window.Alert) {
                    window.Alert.toast(@json(session('success')), 'success');
                }
            @endif
        });
    </script>
</body>
</html>
