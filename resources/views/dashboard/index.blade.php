@extends('layouts.app')

@section('content')
<div
    x-data="dashboardLive(@js($paperWallet), @js($recentSignals), @js($topInflow), @js($topOutflow), @js($nextHighImpactEvent), @js($latestNews))"
    x-init="init()"
    class="space-y-6"
>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="activity" class="w-5 h-5 text-accent" />
                <span>TRADING COMMAND CENTER</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">Ringkasan real-time posisi berjalan, sinyal, dan kondisi pasar hari ini.</p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Currency Selector (USD vs IDR) synced with Simulasi Trade -->
            <div class="flex items-center bg-surface border border-border-subtle rounded-lg p-0.5 font-mono text-xs" title="Pilih mata uang tampilan dashboard (tersimpan konsisten dengan Simulasi Trade)">
                <button type="button" @click="setCurrency('USD')" class="px-2.5 py-1 rounded text-xs font-bold transition" :class="currency === 'USD' ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">USD ($)</button>
                <button type="button" @click="setCurrency('IDR')" class="px-2.5 py-1 rounded text-xs font-bold transition" :class="currency === 'IDR' ? 'bg-emerald-500 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">IDR (Rp)</button>
            </div>

            <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-mono border bg-trade-profit-bg text-trade-profit border-trade-profit/20">
                <span class="w-1.5 h-1.5 rounded-full bg-trade-profit animate-pulse"></span>
                <span x-text="'LIVE • diperbarui ' + lastUpdatedLabel"></span>
            </span>
            <a href="{{ route('trades.create') }}" class="inline-flex items-center gap-1.5 px-3 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-semibold transition">
                <x-app-icon name="plus" class="w-3.5 h-3.5" />
                <span>Log Trade</span>
            </a>
        </div>
    </div>

    <!-- Row 1: Paper Trading (Simulasi Trade) KPI — the real, always-live activity -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <div class="flex items-center justify-between text-text-secondary text-[11px] font-medium uppercase tracking-wider mb-2">
                <span>Total Virtual Equity</span>
                <x-app-icon name="wallet" class="w-4 h-4 text-accent" />
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-xl sm:text-2xl font-bold tracking-tight text-accent tabular-nums" x-text="formatMoney(wallet.total_virtual_equity)"></span>
                <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-accent/10 text-accent border border-accent/20">SIMULASI TRADE</span>
            </div>
            <p class="text-text-muted text-[11px] mt-2 font-mono truncate" x-text="'Saldo: ' + formatMoney(wallet.virtual_balance)"></p>
        </div>

        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <div class="flex items-center justify-between text-text-secondary text-[11px] font-medium uppercase tracking-wider mb-2">
                <span>Floating P&amp;L (Open)</span>
                <template x-if="wallet.floating_unrealized_pnl_usd >= 0">
                    <x-app-icon name="trending-up" class="w-4 h-4 text-trade-profit" />
                </template>
                <template x-if="wallet.floating_unrealized_pnl_usd < 0">
                    <x-app-icon name="trending-down" class="w-4 h-4 text-trade-loss" />
                </template>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-xl sm:text-2xl font-bold tracking-tight tabular-nums" :class="wallet.floating_unrealized_pnl_usd >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="formatMoney(wallet.floating_unrealized_pnl_usd, true)"></span>
                <span class="font-mono text-[11px] px-1.5 py-0.5 rounded border" :class="wallet.floating_unrealized_pnl_usd >= 0 ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-trade-loss-bg text-trade-loss border-trade-loss/20'" x-text="(wallet.floating_unrealized_pnl_pct >= 0 ? '+' : '') + wallet.floating_unrealized_pnl_pct + '%'"></span>
            </div>
            <p class="text-text-muted text-[11px] mt-2 font-mono truncate" x-text="(wallet.open_trades ? wallet.open_trades.length : 0) + ' posisi terbuka'"></p>
        </div>

        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <div class="flex items-center justify-between text-text-secondary text-[11px] font-medium uppercase tracking-wider mb-2">
                <span>Win Rate</span>
                <x-app-icon name="target" class="w-4 h-4 text-text-secondary" />
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-xl sm:text-2xl font-bold tracking-tight tabular-nums" :class="wallet.win_rate >= 50 ? 'text-trade-profit' : 'text-trade-loss'" x-text="wallet.win_rate + '%'"></span>
                <span class="font-mono text-[11px] px-1.5 py-0.5 rounded border" :class="wallet.win_rate >= 50 ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-trade-loss-bg text-trade-loss border-trade-loss/20'" x-text="wallet.winning_trades + 'W - ' + wallet.losing_trades + 'L'"></span>
            </div>
            <p class="text-text-muted text-[11px] mt-2 font-mono truncate" x-text="wallet.total_trades + ' total trade selesai'"></p>
        </div>

        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <div class="flex items-center justify-between text-text-secondary text-[11px] font-medium uppercase tracking-wider mb-2">
                <span>Net Profit (Realized)</span>
                <x-app-icon name="bar-chart-2" class="w-4 h-4" x-bind:class="wallet.net_profit_amount >= 0 ? 'text-trade-profit' : 'text-trade-loss'" />
            </div>
            <div class="flex items-baseline gap-2">
                <span class="font-mono text-xl sm:text-2xl font-bold tracking-tight tabular-nums" :class="wallet.net_profit_amount >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="formatMoney(wallet.net_profit_amount, true)"></span>
                <span class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-surface-hover text-text-secondary border border-border-subtle">ALL-TIME</span>
            </div>
            <p class="text-text-muted text-[11px] mt-2 font-mono truncate" x-text="'Kas Bebas: ' + formatMoney(wallet.available_cash)"></p>
        </div>
    </div>

    <!-- Row 2: Open Positions + Coin Hunter -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Open Positions (2 cols) -->
        <div class="lg:col-span-2 bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="play-circle" class="w-4 h-4 text-accent" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Posisi Berjalan (Simulasi Trade)</h3>
                </div>
                <a href="{{ route('simulation.index') }}" class="font-mono text-xs text-accent hover:underline flex items-center gap-1">
                    <span>Buka Simulasi</span>
                    <x-app-icon name="chevron-right" class="w-3.5 h-3.5" />
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-elevated text-text-secondary border-b border-border-subtle font-mono uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-3">Symbol</th>
                            <th class="py-2.5 px-3">Arah</th>
                            <th class="py-2.5 px-3 text-right">Entry</th>
                            <th class="py-2.5 px-3 text-right">Harga Saat Ini</th>
                            <th class="py-2.5 px-3 text-right">Floating P&amp;L</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle font-mono">
                        <template x-for="pos in (wallet.open_trades || [])" :key="pos.id">
                            <tr class="hover:bg-surface-hover transition-colors">
                                <td class="py-2.5 px-3 font-bold text-text-primary" x-text="pos.symbol"></td>
                                <td class="py-2.5 px-3">
                                    <span
                                        class="inline-flex items-center gap-1 text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase"
                                        :class="(pos.side || 'long').toLowerCase() === 'short' ? 'bg-trade-loss-bg text-trade-loss border-trade-loss/20' : 'bg-trade-profit-bg text-trade-profit border-trade-profit/20'"
                                        x-text="(pos.side || 'long').toUpperCase()"
                                    ></span>
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-text-secondary" x-text="formatMoney(pos.entry_price, false, true)"></td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-text-secondary" x-text="pos.current_price ? formatMoney(pos.current_price, false, true) : '—'"></td>
                                <td class="py-2.5 px-3 text-right tabular-nums font-bold" :class="pos.unrealized_pnl_amount >= 0 ? 'text-trade-profit' : 'text-trade-loss'">
                                    <span x-text="formatMoney(pos.unrealized_pnl_amount, true)"></span>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="!wallet.open_trades || wallet.open_trades.length === 0">
                            <td colspan="5" class="text-center py-8 text-text-muted font-mono">
                                Belum ada posisi terbuka. Buka posisi pertama dari Coin Hunter atau Simulasi Trade.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Coin Hunter Recent Signals (1 col) -->
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden flex flex-col">
            <div class="p-4 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="target" class="w-4 h-4 text-trade-profit" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Coin Hunter</h3>
                </div>
                <a href="{{ route('signals.index') }}" class="font-mono text-[11px] text-accent hover:underline">Lihat Semua</a>
            </div>

            <div class="divide-y divide-border-subtle/60 flex-1">
                <template x-for="sig in recentSignals" :key="sig.id">
                    <a href="{{ route('signals.index') }}" class="flex items-center justify-between px-4 py-2.5 hover:bg-surface-hover transition">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded border" :class="sig.grade === 'A+' ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-accent/10 text-accent border-accent/20'" x-text="sig.grade"></span>
                            <span class="text-xs font-semibold text-text-primary" x-text="sig.symbol"></span>
                        </div>
                        <span class="text-[10px] font-mono text-text-muted" x-text="sig.timeframe"></span>
                    </a>
                </template>
                <div x-show="recentSignals.length === 0" class="px-4 py-8 text-center text-xs text-text-muted font-mono">
                    Belum ada sinyal Grade A/A+ terdeteksi.
                </div>
            </div>
        </div>
    </div>

    <!-- Row 3: Money Flow / Kalender Ekonomi / Berita Dunia -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Money Flow Snapshot -->
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="waves" class="w-4 h-4 text-accent" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Money Flow</h3>
                </div>
                <a href="{{ route('money-flow.index') }}" class="font-mono text-[11px] text-accent hover:underline">Detail</a>
            </div>
            <div class="p-3 space-y-3">
                <div>
                    <span class="text-[10px] font-mono uppercase text-text-muted">Top Inflow</span>
                    <div class="space-y-1 mt-1">
                        <template x-for="coin in topInflow" :key="'in-' + coin.symbol">
                            <div class="flex items-center justify-between text-xs font-mono">
                                <span class="text-text-primary font-semibold" x-text="coin.symbol"></span>
                                <span class="text-trade-profit" x-text="formatFlow(coin.net_flow_24h, true)"></span>
                            </div>
                        </template>
                        <p x-show="topInflow.length === 0" class="text-[11px] text-text-muted font-mono">Tidak ada data.</p>
                    </div>
                </div>
                <div class="pt-2 border-t border-border-subtle/60">
                    <span class="text-[10px] font-mono uppercase text-text-muted">Top Outflow</span>
                    <div class="space-y-1 mt-1">
                        <template x-for="coin in topOutflow" :key="'out-' + coin.symbol">
                            <div class="flex items-center justify-between text-xs font-mono">
                                <span class="text-text-primary font-semibold" x-text="coin.symbol"></span>
                                <span class="text-trade-loss" x-text="formatFlow(coin.net_flow_24h, false)"></span>
                            </div>
                        </template>
                        <p x-show="topOutflow.length === 0" class="text-[11px] text-text-muted font-mono">Tidak ada data.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kalender Ekonomi Next Event -->
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="calendar-days" class="w-4 h-4 text-accent" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Kalender Ekonomi</h3>
                </div>
                <a href="{{ route('calendar.index') }}" class="font-mono text-[11px] text-accent hover:underline">Detail</a>
            </div>
            <div class="p-4">
                <template x-if="nextEvent">
                    <div>
                        <span class="text-[10px] font-mono uppercase tracking-widest text-trade-loss">Rilis High Impact Berikutnya</span>
                        <h4 class="text-sm font-bold text-text-primary mt-1" x-text="nextEvent.title"></h4>
                        <p class="text-xs text-text-secondary mt-1 font-mono" x-text="nextEvent.currency"></p>
                        <p class="text-xs text-text-muted mt-2 font-mono" x-text="formatEventDate(nextEvent.timestamp)"></p>
                    </div>
                </template>
                <p x-show="!nextEvent" class="text-xs text-text-muted font-mono text-center py-6">Tidak ada event high-impact terjadwal saat ini.</p>
            </div>
        </div>

        <!-- Berita Dunia Preview -->
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-app-icon name="newspaper" class="w-4 h-4 text-accent" />
                    <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Berita Dunia</h3>
                </div>
                <a href="{{ route('news.index') }}" class="font-mono text-[11px] text-accent hover:underline">Semua</a>
            </div>
            <div class="divide-y divide-border-subtle/60">
                <template x-for="item in latestNews" :key="item.id">
                    <a :href="item.url" target="_blank" rel="noopener noreferrer" class="block px-4 py-2.5 hover:bg-surface-hover transition">
                        <p class="text-xs font-medium text-text-primary leading-snug line-clamp-2" x-text="item.title"></p>
                        <span class="text-[10px] font-mono text-text-muted" x-text="item.source"></span>
                    </a>
                </template>
                <p x-show="latestNews.length === 0" class="px-4 py-8 text-center text-xs text-text-muted font-mono">Belum ada berita.</p>
            </div>
        </div>
    </div>

    <!-- Row 4: Trade Journal Compact Summary (manual data — not live-polled) -->
    <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
        <div class="p-4 border-b border-border-subtle flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-app-icon name="book-open" class="w-4 h-4 text-accent" />
                <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Trade Journal</h3>
            </div>
            <a href="{{ route('trades.index') }}" class="font-mono text-xs text-accent hover:underline flex items-center gap-1">
                <span>Lihat Semua ({{ $metrics['total_trades'] }})</span>
                <x-app-icon name="chevron-right" class="w-3.5 h-3.5" />
            </a>
        </div>

        @if($metrics['total_trades'] > 0)
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 font-mono text-xs border-b border-border-subtle">
                <div class="flex flex-col">
                    <span class="text-text-muted text-[10px] uppercase">Net P&L</span>
                    <span class="font-bold {{ $metrics['net_pnl'] >= 0 ? 'text-trade-profit' : 'text-trade-loss' }} tabular-nums" x-text="formatMoney({{ (float)$metrics['net_pnl'] }}, true)">
                        {{ $metrics['net_pnl'] >= 0 ? '+' : '' }}${{ number_format($metrics['net_pnl'], 2) }}
                    </span>
                </div>
                <div class="flex flex-col">
                    <span class="text-text-muted text-[10px] uppercase">Win Rate</span>
                    <span class="font-bold text-text-primary tabular-nums">{{ $metrics['win_rate'] }}%</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-text-muted text-[10px] uppercase">Profit Factor</span>
                    <span class="font-bold text-text-primary tabular-nums">{{ number_format($metrics['profit_factor'], 2) }}</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-text-muted text-[10px] uppercase">Total Closed</span>
                    <span class="font-bold text-text-primary tabular-nums">{{ $metrics['total_closed'] }} ({{ $metrics['total_open'] }} Open)</span>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-surface-elevated text-text-secondary border-b border-border-subtle font-mono uppercase text-[10px] tracking-wider">
                        <th class="py-2.5 px-3">Tanggal</th>
                        <th class="py-2.5 px-3">Symbol</th>
                        <th class="py-2.5 px-3">Arah</th>
                        <th class="py-2.5 px-3 text-right">Net P&L</th>
                        <th class="py-2.5 px-3">Strategy</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle font-mono">
                    @forelse($recentTrades as $trade)
                        <tr class="hover:bg-surface-hover transition-colors">
                            <td class="py-2.5 px-3 text-text-secondary whitespace-nowrap">{{ $trade->entry_at->format('d M H:i') }}</td>
                            <td class="py-2.5 px-3 font-bold text-text-primary">{{ $trade->symbol }}</td>
                            <td class="py-2.5 px-3"><x-badge :type="$trade->direction" /></td>
                            <td class="py-2.5 px-3 text-right tabular-nums font-bold whitespace-nowrap">
                                @if($trade->status === 'open')
                                    <x-badge type="open" />
                                @else
                                    <span class="{{ $trade->net_pnl >= 0 ? 'text-trade-profit' : 'text-trade-loss' }}" x-text="formatMoney({{ (float)$trade->net_pnl }}, true)">
                                        {{ $trade->net_pnl >= 0 ? '+' : '' }}${{ number_format($trade->net_pnl, 2) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-text-secondary truncate max-w-[120px]">
                                {{ $trade->strategy->name ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-text-muted font-mono">
                                Belum ada jurnal trading. Klik "Log Trade" untuk mencatat trade pertama Anda.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function dashboardLive(initialWallet, initialSignals, initialInflow, initialOutflow, initialNextEvent, initialNews) {
    return {
        wallet: initialWallet || {},
        recentSignals: initialSignals || [],
        topInflow: initialInflow || [],
        topOutflow: initialOutflow || [],
        nextEvent: initialNextEvent || null,
        latestNews: initialNews || [],
        lastUpdated: Date.now(),
        lastUpdatedLabel: 'baru saja',
        pollTimer: null,
        labelTimer: null,

        // Currency (USD vs IDR) synced with Simulasi Trade via localStorage ('paper_currency')
        currency: localStorage.getItem('paper_currency') || 'USD',
        usdToIdrRate: 17756,

        setCurrency(cur) {
            this.currency = cur;
            localStorage.setItem('paper_currency', cur);
        },

        formatMoney(amountInUsd, signed = false, isCryptoPrice = false) {
            if (amountInUsd === null || amountInUsd === undefined || isNaN(amountInUsd)) {
                return this.currency === 'IDR' ? 'Rp 0' : '$0.00';
            }
            const num = Number(amountInUsd);

            if (this.currency === 'IDR') {
                const idrVal = num * this.usdToIdrRate;
                const absVal = Math.abs(idrVal);
                const prefix = signed ? (idrVal >= 0 ? '+Rp ' : '-Rp ') : 'Rp ';

                if (absVal >= 1000) {
                    return prefix + Math.round(absVal).toLocaleString('id-ID');
                } else if (absVal >= 1) {
                    return prefix + absVal.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    return prefix + absVal.toFixed(4);
                }
            } else {
                if (isCryptoPrice) {
                    return this.formatPrice(num);
                }
                const absVal = Math.abs(num);
                const prefix = signed ? (num >= 0 ? '+$' : '-$') : '$';
                return prefix + absVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        },

        formatFlow(netFlowUsd, isPositive) {
            const val = (Number(netFlowUsd) || 0) / 1000000;
            const sign = isPositive ? '+' : '';
            if (this.currency === 'IDR') {
                const idrM = val * (this.usdToIdrRate / 1000);
                return sign + idrM.toFixed(2) + 'M Rp';
            }
            return sign + val.toFixed(2) + 'M';
        },

        init() {
            this.pollTimer = setInterval(() => this.fetchLiveData(), 8000);
            this.labelTimer = setInterval(() => this.refreshLabel(), 5000);
            window.addEventListener('beforeunload', () => {
                clearInterval(this.pollTimer);
                clearInterval(this.labelTimer);
            });
        },

        async fetchLiveData() {
            try {
                const liveRoute = '{{ route('dashboard.live') }}';
                const res = await fetch(liveRoute, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.wallet = data.paperWallet || {};
                this.recentSignals = data.recentSignals || [];
                this.topInflow = data.topInflow || [];
                this.topOutflow = data.topOutflow || [];
                this.nextEvent = data.nextHighImpactEvent || null;
                this.latestNews = data.latestNews || [];
                this.lastUpdated = Date.now();
                this.refreshLabel();
            } catch (e) {
                console.error('[Dashboard] live data fetch failed', e);
            }
        },

        refreshLabel() {
            const diff = Math.max(0, Math.floor((Date.now() - this.lastUpdated) / 1000));
            if (diff < 3) this.lastUpdatedLabel = 'baru saja';
            else if (diff < 60) this.lastUpdatedLabel = diff + 'd lalu';
            else this.lastUpdatedLabel = Math.floor(diff / 60) + 'm lalu';
        },

        formatPrice(price) {
            const p = Number(price) || 0;
            if (this.currency === 'IDR') {
                const idr = p * this.usdToIdrRate;
                if (idr <= 0) return 'Rp 0';
                if (idr >= 1000) return 'Rp ' + Math.round(idr).toLocaleString('id-ID');
                if (idr >= 1) return 'Rp ' + idr.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                return 'Rp ' + idr.toFixed(4);
            }
            if (p <= 0) return '$0.00';
            if (p >= 50) return '$' + p.toFixed(2);
            if (p >= 0.01) return '$' + p.toFixed(4);
            if (p >= 0.0001) return '$' + p.toFixed(6);
            return '$' + p.toFixed(8);
        },

        formatEventDate(unixSeconds) {
            const dt = new Date(unixSeconds * 1000);
            return dt.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
@endsection
