<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Create Trader Account • ALPHAMACHINE</title>

    <script>
        if (localStorage.theme === 'light' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas text-text-primary font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-accent/20 selection:text-accent">

    <header class="p-4 flex items-center justify-between max-w-7xl mx-auto w-full">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                <x-app-icon name="activity" class="w-4 h-4" />
            </div>
            <span class="font-mono text-sm font-bold tracking-wider uppercase text-text-primary">ALPHAMACHINE</span>
        </div>

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
        >
            <template x-if="dark">
                <x-app-icon name="sun" class="w-4 h-4 text-amber-400" />
            </template>
            <template x-if="!dark">
                <x-app-icon name="moon" class="w-4 h-4 text-slate-700" />
            </template>
        </button>
    </header>

    <main class="flex-1 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-surface border border-border-subtle rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
            
            <div class="space-y-1.5 text-center">
                <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono uppercase">
                    New Trader Registration
                </h1>
                <p class="text-xs text-text-secondary">
                    Create an account to start tracking, evaluating, and journaling trades.
                </p>
            </div>

            @if ($errors->any())
                <div class="p-3 rounded-lg bg-trade-loss-bg border border-trade-loss/20 text-trade-loss text-xs font-mono">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('register') }}" method="POST" class="space-y-4">
                @csrf

                <div class="space-y-1">
                    <label class="block text-xs font-mono uppercase text-text-secondary">Trader Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        value="{{ old('name') }}" 
                        required 
                        autofocus 
                        placeholder="John Trader" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono placeholder:text-text-muted"
                    />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-mono uppercase text-text-secondary">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="{{ old('email') }}" 
                        required 
                        placeholder="john@example.com" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono placeholder:text-text-muted"
                    />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-mono uppercase text-text-secondary">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="Minimum 8 characters" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono placeholder:text-text-muted"
                    />
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-mono uppercase text-text-secondary">Confirm Password</label>
                    <input 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        placeholder="Repeat password" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono placeholder:text-text-muted"
                    />
                </div>

                <button 
                    type="submit" 
                    class="w-full py-2.5 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold tracking-wider uppercase transition shadow-sm flex items-center justify-center gap-2 cursor-pointer"
                >
                    <x-app-icon name="plus-circle" class="w-3.5 h-3.5" />
                    <span>Create Account</span>
                </button>
            </form>

            <div class="text-center text-xs text-text-muted font-mono pt-2 border-t border-border-subtle">
                Already registered? 
                <a href="{{ route('login') }}" class="text-accent hover:underline font-semibold">Sign In</a>
            </div>
        </div>
    </main>

    <footer class="p-4 text-center text-xs text-text-muted font-mono">
        AlphaJournal v1.0 • Fintech Execution Terminal
    </footer>
</body>
</html>
