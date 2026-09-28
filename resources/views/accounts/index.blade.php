@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="wallet" class="w-5 h-5 text-accent" />
                <span>PORTFOLIO & TRADING ACCOUNTS</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">Manage multiple brokers, prop firm challenges, and personal portfolios.</p>
        </div>

        <button 
            type="button" 
            onclick="document.getElementById('add-account-card').classList.toggle('hidden')" 
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-semibold transition cursor-pointer"
        >
            <x-app-icon name="plus" class="w-3.5 h-3.5" />
            <span>Create New Account</span>
        </button>
    </div>

    <!-- Add Account Card Form (Toggleable) -->
    <div id="add-account-card" class="hidden bg-surface border border-accent/30 rounded-xl p-5 space-y-4 shadow-md">
        <div class="flex items-center justify-between">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Add Trading Account</h3>
            <button type="button" onclick="document.getElementById('add-account-card').classList.add('hidden')" class="text-text-muted hover:text-text-primary">
                <x-app-icon name="x-circle" class="w-4 h-4" />
            </button>
        </div>

        <form action="{{ route('accounts.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            @csrf
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-secondary mb-1">Account Name *</label>
                <input type="text" name="name" required placeholder="e.g. FTMO 100K" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-1.5 text-xs text-text-primary font-mono">
            </div>
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-secondary mb-1">Broker / Platform</label>
                <input type="text" name="broker" placeholder="e.g. Binance, FTMO" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-1.5 text-xs text-text-primary font-mono">
            </div>
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-secondary mb-1">Market Type</label>
                <select name="account_type" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-2 py-1.5 text-xs text-text-primary font-mono">
                    <option value="forex">Forex</option>
                    <option value="crypto">Crypto</option>
                    <option value="futures">Futures</option>
                    <option value="stock">Stock</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-secondary mb-1">Initial Capital ($) *</label>
                <input type="number" step="any" name="initial_balance" required placeholder="10000" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-1.5 text-xs text-text-primary font-mono">
            </div>
            <div class="flex items-end">
                <input type="hidden" name="currency" value="USD">
                <button type="submit" class="w-full px-4 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold transition">
                    Save Account
                </button>
            </div>
        </form>
    </div>

    <!-- Accounts List -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @forelse($accounts as $acc)
            @php
                $netGrowth = $acc->current_balance - $acc->initial_balance;
                $growthPct = $acc->initial_balance > 0 ? round(($netGrowth / $acc->initial_balance) * 100, 2) : 0;
            @endphp
            <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4 hover:border-border-muted transition">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono text-sm font-bold text-text-primary">{{ $acc->name }}</span>
                            <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-surface-hover text-text-secondary border border-border-subtle uppercase">
                                {{ $acc->account_type }}
                            </span>
                        </div>
                        <p class="text-xs text-text-muted font-mono mt-0.5">
                            Broker: {{ $acc->broker ?: 'Manual' }} • {{ $acc->trades_count }} logged trades
                        </p>
                    </div>

                    <div class="flex items-center gap-1">
                        <button 
                            type="button" 
                            onclick="handleDeleteAccount({{ $acc->id }}, '{{ $acc->name }}')" 
                            class="p-1 text-text-muted hover:text-trade-loss hover:bg-trade-loss-bg rounded transition cursor-pointer"
                            title="Delete Account"
                        >
                            <x-app-icon name="trash-2" class="w-3.5 h-3.5" />
                        </button>
                        <form id="delete-account-form-{{ $acc->id }}" action="{{ route('accounts.destroy', $acc) }}" method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 p-3 bg-surface-elevated rounded-lg font-mono text-xs">
                    <div>
                        <span class="text-text-muted text-[10px] uppercase">Starting Capital</span>
                        <div class="font-bold text-text-secondary tabular-nums mt-0.5">${{ number_format($acc->initial_balance, 2) }}</div>
                    </div>
                    <div>
                        <span class="text-text-muted text-[10px] uppercase">Current Balance</span>
                        <div class="font-bold text-text-primary tabular-nums mt-0.5">${{ number_format($acc->current_balance, 2) }}</div>
                    </div>
                </div>

                <div class="flex items-center justify-between font-mono text-xs pt-1 border-t border-border-subtle">
                    <span class="text-text-muted">Net Return:</span>
                    <span class="font-bold {{ $netGrowth >= 0 ? 'text-trade-profit' : 'text-trade-loss' }} tabular-nums">
                        {{ $netGrowth >= 0 ? '+' : '' }}${{ number_format($netGrowth, 2) }} ({{ $growthPct >= 0 ? '+' : '' }}{{ $growthPct }}%)
                    </span>
                </div>
            </div>
        @empty
            <div class="col-span-2 text-center py-12 text-text-muted font-mono bg-surface border border-border-subtle rounded-xl">
                No accounts created yet. Click "Create New Account" above to start.
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    function handleDeleteAccount(accId, name) {
        if (window.Alert) {
            window.Alert.confirmDelete(
                `Hapus Akun ${name}?`,
                'Semua data trade dan attachment yang terhubung ke akun ini akan ikut terhapus.'
            ).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`delete-account-form-${accId}`).submit();
                }
            });
        } else {
            if (confirm(`Hapus akun ${name}?`)) {
                document.getElementById(`delete-account-form-${accId}`).submit();
            }
        }
    }
</script>
@endpush
@endsection
