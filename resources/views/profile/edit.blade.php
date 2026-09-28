@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header Breadcrumb -->
    <div class="flex items-center justify-between pb-4 border-b border-border-subtle">
        <div class="flex items-center gap-3">
            <a href="{{ route('dashboard') }}" class="p-2 rounded-lg bg-surface hover:bg-surface-hover text-text-secondary hover:text-text-primary border border-border-subtle transition">
                <x-app-icon name="chevron-left" class="w-4 h-4" />
            </a>
            <div>
                <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                    <x-app-icon name="user" class="w-5 h-5 text-accent" />
                    <span>TRADER ACCOUNT & SECURITY</span>
                </h1>
                <p class="text-xs text-text-secondary mt-0.5">Manage your credentials, login email, and terminal password.</p>
            </div>
        </div>
    </div>

    <!-- Error Summary Alert if any -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-trade-loss-bg border border-trade-loss/20 text-trade-loss text-xs font-mono">
            <div class="font-bold mb-1">Periksa kembali input Anda:</div>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Card 1: Profile & Email Settings -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-border-subtle">
                <x-app-icon name="user" class="w-4 h-4 text-accent" />
                <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">
                    Profile & Login Email
                </h3>
            </div>

            <form action="{{ route('profile.update') }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Trader Name *</label>
                    <input 
                        type="text" 
                        name="name" 
                        value="{{ old('name', $user->name) }}" 
                        required 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary font-mono focus:outline-none focus:border-accent"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Login Email Address *</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="{{ old('email', $user->email) }}" 
                        required 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary font-mono focus:outline-none focus:border-accent"
                    />
                    <p class="text-[10px] text-text-muted font-mono mt-1">
                        Email ini digunakan untuk masuk (sign in) ke terminal.
                    </p>
                </div>

                <div class="pt-2 flex justify-end">
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold tracking-wide transition shadow-sm cursor-pointer"
                    >
                        Save Profile Changes
                    </button>
                </div>
            </form>
        </div>

        <!-- Card 2: Password Security Settings -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <div class="flex items-center gap-2 pb-2 border-b border-border-subtle">
                <x-app-icon name="lock" class="w-4 h-4 text-trade-loss" />
                <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">
                    Change Password
                </h3>
            </div>

            <form action="{{ route('profile.password') }}" method="POST" class="space-y-3">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Current Password *</label>
                    <input 
                        type="password" 
                        name="current_password" 
                        required 
                        placeholder="••••••••" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary font-mono focus:outline-none focus:border-accent"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">New Password *</label>
                    <input 
                        type="password" 
                        name="password" 
                        required 
                        placeholder="Min. 8 characters" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary font-mono focus:outline-none focus:border-accent"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Confirm New Password *</label>
                    <input 
                        type="password" 
                        name="password_confirmation" 
                        required 
                        placeholder="Repeat new password" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary font-mono focus:outline-none focus:border-accent"
                    />
                </div>

                <div class="pt-2 flex justify-end">
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-surface-hover hover:bg-border-subtle text-text-primary border border-border-subtle hover:border-border-muted rounded-lg text-xs font-mono font-bold tracking-wide transition shadow-sm cursor-pointer"
                    >
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Security Info Summary -->
    <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-2 font-mono text-xs">
        <h4 class="font-bold text-text-primary uppercase text-[11px]">Terminal Session Details</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 text-text-secondary">
            <div>Member Since: <span class="text-text-primary font-bold">{{ $user->created_at->format('d M Y') }}</span></div>
            <div>Active Portfolios: <span class="text-text-primary font-bold">{{ $user->accounts()->count() }} Accounts</span></div>
            <div>Total Trades Logged: <span class="text-text-primary font-bold">{{ $user->trades()->count() }} Trades</span></div>
        </div>
    </div>
</div>
@endsection
