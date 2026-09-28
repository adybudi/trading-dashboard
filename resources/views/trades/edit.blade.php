@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header Breadcrumb -->
    <div class="flex items-center justify-between pb-4 border-b border-border-subtle">
        <div class="flex items-center gap-3">
            <a href="{{ route('trades.index') }}" class="p-2 rounded-lg bg-surface hover:bg-surface-hover text-text-secondary hover:text-text-primary border border-border-subtle transition">
                <x-app-icon name="chevron-left" class="w-4 h-4" />
            </a>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                    <x-app-icon name="edit" class="w-4 h-4 text-accent" />
                    <span>EDIT TRADE: {{ $trade->symbol }}</span>
                </h1>
                <p class="text-xs text-text-secondary">Update price parameters, exit metrics, reflections, or screenshots.</p>
            </div>
        </div>

        <a href="{{ route('trades.show', $trade) }}" class="text-xs font-mono text-text-muted hover:text-text-secondary">
            View Details
        </a>
    </div>

    <!-- Form -->
    <form 
        action="{{ route('trades.update', $trade) }}" 
        method="POST" 
        enctype="multipart/form-data" 
        x-data="{
            direction: '{{ $trade->direction }}',
            status: '{{ $trade->status }}',
            entry_price: '{{ $trade->entry_price }}',
            exit_price: '{{ $trade->exit_price }}',
            quantity: '{{ $trade->quantity }}',
            stop_loss: '{{ $trade->stop_loss }}',
            take_profit: '{{ $trade->take_profit }}',
            fees: '{{ $trade->fees }}',
            
            planned_risk_amount: 0,
            planned_reward_amount: 0,
            planned_rr: '{{ $trade->planned_rr }}',
            estimated_net_pnl: '{{ $trade->net_pnl }}',
            estimated_roi: '{{ $trade->roi_percentage }}',

            calculate() {
                const ep = parseFloat(this.entry_price) || 0;
                const xp = parseFloat(this.exit_price) || 0;
                const qty = parseFloat(this.quantity) || 0;
                const sl = parseFloat(this.stop_loss) || 0;
                const tp = parseFloat(this.take_profit) || 0;
                const fee = parseFloat(this.fees) || 0;

                if (ep > 0 && sl > 0 && tp > 0) {
                    const riskPerUnit = this.direction === 'long' ? (ep - sl) : (sl - ep);
                    const rewardPerUnit = this.direction === 'long' ? (tp - ep) : (ep - tp);
                    
                    if (riskPerUnit > 0 && rewardPerUnit > 0) {
                        this.planned_risk_amount = (riskPerUnit * (qty || 1)).toFixed(2);
                        this.planned_reward_amount = (rewardPerUnit * (qty || 1)).toFixed(2);
                        this.planned_rr = (rewardPerUnit / riskPerUnit).toFixed(2);
                    } else {
                        this.planned_rr = null;
                    }
                }

                if (ep > 0 && xp > 0 && qty > 0) {
                    const gross = this.direction === 'long' ? (xp - ep) * qty : (ep - xp) * qty;
                    const net = gross - fee;
                    this.estimated_net_pnl = net.toFixed(2);
                    const invested = ep * qty;
                    this.estimated_roi = invested > 0 ? ((net / invested) * 100).toFixed(2) : 0;
                }
            }
        }"
        class="space-y-6"
    >
        @csrf
        @method('PUT')

        <!-- Live Calculations HUD -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 bg-surface border border-accent/20 rounded-xl font-mono text-xs shadow-sm">
            <div>
                <span class="text-text-muted text-[10px] uppercase">Planned Risk (SL)</span>
                <div class="font-bold text-trade-loss tabular-nums mt-0.5" x-text="planned_risk_amount > 0 ? '-$' + planned_risk_amount : '—'">—</div>
            </div>
            <div>
                <span class="text-text-muted text-[10px] uppercase">Planned Target (TP)</span>
                <div class="font-bold text-trade-profit tabular-nums mt-0.5" x-text="planned_reward_amount > 0 ? '+$' + planned_reward_amount : '—'">—</div>
            </div>
            <div>
                <span class="text-text-muted text-[10px] uppercase">Planned R:R</span>
                <div class="font-bold text-accent tabular-nums mt-0.5" x-text="planned_rr ? '1 : ' + planned_rr : '—'">—</div>
            </div>
            <div>
                <span class="text-text-muted text-[10px] uppercase">Computed Net P&L</span>
                <div 
                    class="font-bold tabular-nums mt-0.5"
                    :class="estimated_net_pnl > 0 ? 'text-trade-profit' : (estimated_net_pnl < 0 ? 'text-trade-loss' : 'text-text-muted')"
                    x-text="estimated_net_pnl !== null ? (estimated_net_pnl >= 0 ? '+' : '') + '$' + estimated_net_pnl + ' (' + estimated_roi + '%)' : 'Pending Exit'"
                >
                    {{ $trade->net_pnl ? ($trade->net_pnl >= 0 ? '+' : '') . '$' . $trade->net_pnl : 'Pending Exit' }}
                </div>
            </div>
        </div>

        <!-- Section 1: Account & Setup -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
                <x-app-icon name="sliders" class="w-3.5 h-3.5 text-accent" />
                <span>Account & Instrument Setup</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Trading Account *</label>
                    <select name="account_id" required class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ $trade->account_id == $acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} ({{ $acc->currency }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Symbol / Pair *</label>
                    <input 
                        type="text" 
                        name="symbol" 
                        required 
                        value="{{ $trade->symbol }}" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono uppercase"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Position Side *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button 
                            type="button" 
                            @click="direction = 'long'; calculate()"
                            :class="direction === 'long' ? 'bg-trade-profit-bg text-trade-profit border-trade-profit' : 'bg-surface-elevated text-text-secondary border-border-subtle'"
                            class="py-2 rounded-lg text-xs font-mono font-bold border transition flex items-center justify-center gap-1 cursor-pointer"
                        >
                            <x-app-icon name="arrow-up-right" class="w-3.5 h-3.5" />
                            <span>LONG</span>
                        </button>
                        <button 
                            type="button" 
                            @click="direction = 'short'; calculate()"
                            :class="direction === 'short' ? 'bg-trade-loss-bg text-trade-loss border-trade-loss' : 'bg-surface-elevated text-text-secondary border-border-subtle'"
                            class="py-2 rounded-lg text-xs font-mono font-bold border transition flex items-center justify-center gap-1 cursor-pointer"
                        >
                            <x-app-icon name="arrow-down-right" class="w-3.5 h-3.5" />
                            <span>SHORT</span>
                        </button>
                    </div>
                    <input type="hidden" name="direction" :value="direction" />
                </div>
            </div>
        </div>

        <!-- Section 2: Pricing & Execution Parameters -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
                <x-app-icon name="dollar-sign" class="w-3.5 h-3.5 text-accent" />
                <span>Pricing & Quantity</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Entry Price *</label>
                    <input 
                        type="number" 
                        step="any" 
                        name="entry_price" 
                        x-model="entry_price" 
                        @input="calculate()" 
                        required 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Quantity / Lot *</label>
                    <input 
                        type="number" 
                        step="any" 
                        name="quantity" 
                        x-model="quantity" 
                        @input="calculate()" 
                        required 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Stop Loss (SL)</label>
                    <input 
                        type="number" 
                        step="any" 
                        name="stop_loss" 
                        x-model="stop_loss" 
                        @input="calculate()" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Take Profit (TP)</label>
                    <input 
                        type="number" 
                        step="any" 
                        name="take_profit" 
                        x-model="take_profit" 
                        @input="calculate()" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>
            </div>

            <!-- Exit Parameters -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 pt-2 border-t border-border-subtle">
                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Status *</label>
                    <select name="status" x-model="status" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                        <option value="closed">Closed</option>
                        <option value="open">Open (Active)</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Exit Price</label>
                    <input 
                        type="number" 
                        step="any" 
                        name="exit_price" 
                        x-model="exit_price" 
                        @input="calculate()" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Entry Timestamp *</label>
                    <input 
                        type="datetime-local" 
                        name="entry_at" 
                        required 
                        value="{{ $trade->entry_at->format('Y-m-d\TH:i') }}" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Fees ($)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        name="fees" 
                        x-model="fees" 
                        @input="calculate()" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono"
                    />
                </div>
            </div>
        </div>

        <!-- Section 3: Strategy & Notes -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
                <x-app-icon name="tag" class="w-3.5 h-3.5 text-accent" />
                <span>Strategy, Reflections & Screenshots</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Strategy / Setup</label>
                    <select name="strategy_id" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                        <option value="">No Strategy</option>
                        @foreach($strategies as $strat)
                            <option value="{{ $strat->id }}" {{ $trade->strategy_id == $strat->id ? 'selected' : '' }}>
                                {{ $strat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Tags</label>
                    @php $selectedTagIds = $trade->tags->pluck('id')->toArray(); @endphp
                    <div class="flex flex-wrap gap-2 p-2 bg-surface-elevated border border-border-subtle rounded-lg max-h-24 overflow-y-auto">
                        @foreach($tags as $tag)
                            <label class="inline-flex items-center gap-1 text-[11px] font-mono px-2 py-0.5 rounded bg-surface border border-border-subtle hover:border-border-muted cursor-pointer">
                                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" {{ in_array($tag->id, $selectedTagIds) ? 'checked' : '' }} class="rounded text-accent focus:ring-0">
                                <span>{{ $tag->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-border-subtle">
                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Trade Notes</label>
                    <textarea name="notes" rows="3" class="w-full bg-surface-elevated border border-border-subtle rounded-lg p-3 text-xs text-text-primary focus:outline-none focus:border-accent font-sans">{{ $trade->notes }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-mono uppercase text-text-secondary mb-1">Lesson Learned</label>
                    <textarea name="lesson_learned" rows="3" class="w-full bg-surface-elevated border border-border-subtle rounded-lg p-3 text-xs text-text-primary focus:outline-none focus:border-accent font-sans">{{ $trade->lesson_learned }}</textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('trades.index') }}" class="px-4 py-2 rounded-lg text-xs font-mono text-text-secondary hover:text-text-primary hover:bg-surface-hover transition border border-border-subtle">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold tracking-wide transition shadow-sm cursor-pointer">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection
