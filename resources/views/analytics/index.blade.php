@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Header & Account Selector -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="target" class="w-5 h-5 text-accent" />
                <span>PERFORMANCE & PSYCHOLOGY ANALYTICS</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">Strategy edge verification, emotion tagging, and risk leak detection.</p>
        </div>

        <form method="GET" action="{{ route('analytics.index') }}" class="w-full sm:w-auto">
            <select name="account_id" onchange="this.form.submit()" class="w-full sm:w-64 bg-surface border border-border-subtle text-text-primary text-xs rounded-lg px-3 py-2 font-mono">
                <option value="">All Accounts Consolidated</option>
                @foreach($accounts as $acc)
                    <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                        {{ $acc->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <!-- Analytics KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-kpi-card 
            title="Trade Expectancy"
            :value="($metrics['expectancy'] >= 0 ? '+' : '') . '$' . number_format($metrics['expectancy'], 2)"
            badge="PER TRADE"
            :type="$metrics['expectancy'] >= 0 ? 'profit' : 'loss'"
            icon="target"
            :subtext="'Avg Win: $' . number_format($metrics['avg_win'], 2) . ' | Avg Loss: $' . number_format($metrics['avg_loss'], 2)"
        />

        <x-kpi-card 
            title="Profit Factor"
            :value="number_format($metrics['profit_factor'], 2)"
            :badge="$metrics['profit_factor'] >= 1.5 ? 'EDGE VALIDATED' : 'REVIEW'"
            :type="$metrics['profit_factor'] >= 1.0 ? 'profit' : 'loss'"
            icon="bar-chart-2"
            :subtext="'Gross Profit / Gross Loss Ratio'"
        />

        <x-kpi-card 
            title="Avg Risk : Reward"
            :value="'1 : ' . number_format($metrics['avg_rr'], 2)"
            badge="ACTUAL"
            type="accent"
            icon="activity"
            :subtext="'Realized R-Multiple across closed trades'"
        />

        <x-kpi-card 
            title="Broker Friction (Fees)"
            :value="'$' . number_format($metrics['total_fees'], 2)"
            badge="COMMISSIONS"
            type="neutral"
            icon="dollar-sign"
            :subtext="'Total broker commissions & fees'"
        />
    </div>

    <!-- Strategy Matrix & Tag Psychology Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Strategy Edge Matrix -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="layers" class="w-4 h-4 text-accent" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Strategy Edge Matrix</h3>
                </div>
                <span class="text-[10px] font-mono text-text-muted">Ranked by Net P&L</span>
            </div>

            <div class="space-y-3">
                @forelse($strategyBreakdown as $strat)
                    <div class="p-3 rounded-lg bg-surface-elevated border border-border-subtle space-y-2">
                        <div class="flex items-center justify-between font-mono text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $strat['color'] }}"></span>
                                <span class="font-semibold text-text-primary">{{ $strat['name'] }}</span>
                            </div>
                            <span class="font-bold {{ $strat['net_pnl'] >= 0 ? 'text-trade-profit' : 'text-trade-loss' }} tabular-nums">
                                {{ $strat['net_pnl'] >= 0 ? '+' : '' }}${{ number_format($strat['net_pnl'], 2) }}
                            </span>
                        </div>

                        <div class="w-full bg-surface-hover rounded-full h-2 overflow-hidden">
                            <div class="bg-trade-profit h-2 rounded-full" style="width: {{ $strat['win_rate'] }}%"></div>
                        </div>

                        <div class="grid grid-cols-3 text-[10px] font-mono text-text-muted pt-1">
                            <div>Trades: <span class="text-text-secondary font-bold">{{ $strat['trades_count'] }}</span></div>
                            <div>Wins: <span class="text-text-secondary font-bold">{{ $strat['win_count'] }}</span></div>
                            <div class="text-right">Win Rate: <span class="text-trade-profit font-bold">{{ $strat['win_rate'] }}%</span></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-text-muted text-center py-6 font-mono">No strategy performance records found.</p>
                @endforelse
            </div>
        </div>

        <!-- Psychology & Discipline Leak Detection -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="shield-alert" class="w-4 h-4 text-trade-loss" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Psychology & Mistake Tracker</h3>
                </div>
                <span class="text-[10px] font-mono text-text-muted">Emotion & Rule Leaks</span>
            </div>

            <div class="space-y-3">
                @forelse($tagBreakdown as $tag)
                    <div class="p-3 rounded-lg bg-surface-elevated border border-border-subtle flex items-center justify-between font-mono text-xs">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-1.5 font-semibold text-text-primary">
                                <x-app-icon name="tag" class="w-3.5 h-3.5 text-text-muted" />
                                <span>{{ $tag['name'] }}</span>
                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-surface-hover text-text-muted uppercase">
                                    {{ $tag['category'] }}
                                </span>
                            </div>
                            <div class="text-[10px] text-text-muted">
                                {{ $tag['trades_count'] }} trades logged • Win Rate: {{ $tag['win_rate'] }}%
                            </div>
                        </div>

                        <div class="text-right">
                            <div class="font-bold tabular-nums {{ $tag['net_pnl'] >= 0 ? 'text-trade-profit' : 'text-trade-loss' }}">
                                {{ $tag['net_pnl'] >= 0 ? '+' : '' }}${{ number_format($tag['net_pnl'], 2) }}
                            </div>
                            <div class="text-[10px] text-text-muted">Net Impact</div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-text-muted text-center py-6 font-mono">No tagged trades found. Tag trades with emotions & mistakes to see leak analytics.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
