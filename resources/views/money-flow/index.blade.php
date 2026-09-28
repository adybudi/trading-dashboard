@extends('layouts.app')

@section('content')
<div
    x-data="moneyFlow(@js($netFlowMatrix), @js($whales))"
    x-init="init()"
    class="space-y-6"
>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="waves" class="w-5 h-5 text-accent" />
                <span>MONEY FLOW & WHALE TRACKER</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">Arus modal institusional & transaksi paus real-time dari data Gate.io.</p>
        </div>

        <div class="flex items-center gap-2">
            <span
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-mono border"
                :class="isLive ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-surface-elevated text-text-muted border-border-subtle'"
            >
                <span class="w-1.5 h-1.5 rounded-full" :class="isLive ? 'bg-trade-profit animate-pulse' : 'bg-text-muted'"></span>
                <span x-text="isLive ? 'LIVE FEED' : 'MODE FALLBACK'"></span>
            </span>

            <div class="flex items-center rounded-lg bg-surface-elevated border border-border-subtle p-0.5">
                <button type="button" @click="setCurrency('USD')" class="px-2.5 py-1 rounded-md text-[11px] font-mono transition" :class="currencyUnit === 'USD' ? 'bg-surface text-emerald-400 shadow-xs' : 'text-text-muted hover:text-text-primary'">USD</button>
                <button type="button" @click="setCurrency('IDR')" class="px-2.5 py-1 rounded-md text-[11px] font-mono transition" :class="currencyUnit === 'IDR' ? 'bg-surface text-emerald-400 shadow-xs' : 'text-text-muted hover:text-text-primary'">IDR</button>
            </div>
        </div>
    </div>
    <p class="text-[10px] font-mono text-text-muted -mt-4" x-show="currencyUnit === 'IDR'">(Kurs 1 USD = Rp 17.756,00)</p>

    <!-- Pergerakan Per Coin: full 250-coin list, click a row for live order book -->
    <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-border-subtle flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                </span>
                <span class="text-xs font-bold font-mono text-text-primary uppercase tracking-wider">Pergerakan Per Coin</span>
                <span class="text-[10px] font-mono text-text-muted" x-text="'(' + netFlowMatrix.length + ' koin)'"></span>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="sortMode = 'inflow'" class="px-2.5 py-1 rounded-md text-[10px] font-mono border transition" :class="sortMode === 'inflow' ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/30' : 'bg-surface-elevated border-border-subtle text-text-muted hover:text-text-primary'">Top Inflow</button>
                <button type="button" @click="sortMode = 'outflow'" class="px-2.5 py-1 rounded-md text-[10px] font-mono border transition" :class="sortMode === 'outflow' ? 'bg-trade-loss-bg text-trade-loss border-trade-loss/30' : 'bg-surface-elevated border-border-subtle text-text-muted hover:text-text-primary'">Top Outflow</button>
            </div>
        </div>

        <p class="px-4 pt-3 text-[10px] font-mono text-text-muted">Klik salah satu koin untuk melihat order book live-nya.</p>

        <div class="overflow-x-auto max-h-[480px] overflow-y-auto mt-2">
            <table class="w-full text-xs">
                <thead class="sticky top-0 bg-surface-elevated z-10">
                    <tr class="text-text-muted font-mono uppercase text-[10px] tracking-wider">
                        <th class="text-left px-4 py-2.5 w-10">#</th>
                        <th class="text-left px-4 py-2.5">Koin</th>
                        <th class="text-right px-4 py-2.5">Harga</th>
                        <th class="text-right px-4 py-2.5">Net Flow 1H</th>
                        <th class="text-right px-4 py-2.5">Net Flow 24H</th>
                        <th class="text-right px-4 py-2.5">Volume 24H</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle/60">
                    <template x-for="(coin, idx) in sortedMatrix" :key="'row-' + coin.symbol">
                        <tr class="hover:bg-surface-hover transition cursor-pointer" @click="openCoinDetail(coin)">
                            <td class="px-4 py-2 font-mono text-text-muted" x-text="idx + 1"></td>
                            <td class="px-4 py-2 font-semibold text-text-primary" x-text="coin.symbol"></td>
                            <td class="px-4 py-2 text-right font-mono text-text-secondary" x-text="coin.price ? formatPrice(coin.price) : '—'"></td>
                            <td class="px-4 py-2 text-right font-mono" :class="coin.net_flow_1h >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(coin.net_flow_1h >= 0 ? '+' : '') + formatMoney(coin.net_flow_1h)"></td>
                            <td class="px-4 py-2 text-right font-mono font-bold" :class="coin.net_flow_24h >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(coin.net_flow_24h >= 0 ? '+' : '') + formatMoney(coin.net_flow_24h)"></td>
                            <td class="px-4 py-2 text-right font-mono text-text-muted" x-text="formatMoney(coin.volume_24h)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div x-show="netFlowMatrix.length === 0" class="px-4 py-10 text-center text-xs text-text-muted font-mono">Data tidak tersedia.</div>
        </div>
    </div>

    <!-- Net Inflow / Outflow Leaderboards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Top Inflow -->
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border-subtle flex items-center gap-2">
                <x-app-icon name="trending-up" class="w-4 h-4 text-trade-profit" />
                <span class="text-xs font-bold font-mono text-text-primary uppercase tracking-wider">Top 10 Net Inflow (24H)</span>
            </div>
            <div class="divide-y divide-border-subtle/60">
                <template x-for="(coin, idx) in topInflow" :key="'in-' + coin.symbol">
                    <div class="flex items-center justify-between px-4 py-2.5 hover:bg-surface-hover transition cursor-pointer" @click="openCoinDetail(coin)">
                        <div class="flex items-center gap-2.5">
                            <span class="text-[10px] font-mono text-text-muted w-4" x-text="idx + 1"></span>
                            <span class="text-xs font-semibold text-text-primary" x-text="coin.symbol"></span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-mono font-bold text-trade-profit" x-text="'+' + formatMoney(coin.net_flow_24h)"></div>
                            <div class="text-[10px] font-mono text-text-muted" x-text="coin.flow_pct_24h + '%'"></div>
                        </div>
                    </div>
                </template>
                <div x-show="topInflow.length === 0" class="px-4 py-6 text-center text-xs text-text-muted font-mono">Data tidak tersedia.</div>
            </div>
        </div>

        <!-- Top Outflow -->
        <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
            <div class="px-4 py-3 border-b border-border-subtle flex items-center gap-2">
                <x-app-icon name="trending-down" class="w-4 h-4 text-trade-loss" />
                <span class="text-xs font-bold font-mono text-text-primary uppercase tracking-wider">Top 10 Net Outflow (24H)</span>
            </div>
            <div class="divide-y divide-border-subtle/60">
                <template x-for="(coin, idx) in topOutflow" :key="'out-' + coin.symbol">
                    <div class="flex items-center justify-between px-4 py-2.5 hover:bg-surface-hover transition cursor-pointer" @click="openCoinDetail(coin)">
                        <div class="flex items-center gap-2.5">
                            <span class="text-[10px] font-mono text-text-muted w-4" x-text="idx + 1"></span>
                            <span class="text-xs font-semibold text-text-primary" x-text="coin.symbol"></span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-mono font-bold text-trade-loss" x-text="formatMoney(coin.net_flow_24h)"></div>
                            <div class="text-[10px] font-mono text-text-muted" x-text="coin.flow_pct_24h + '%'"></div>
                        </div>
                    </div>
                </template>
                <div x-show="topOutflow.length === 0" class="px-4 py-6 text-center text-xs text-text-muted font-mono">Data tidak tersedia.</div>
            </div>
        </div>
    </div>

    <!-- Whale Transactions Live Tape -->
    <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-border-subtle flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <x-app-icon name="zap" class="w-4 h-4 text-accent" />
                <span class="text-xs font-bold font-mono text-text-primary uppercase tracking-wider">Whale Transactions Radar</span>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="whaleTier = 1; fetchWhales()" class="px-2.5 py-1 rounded-md text-[10px] font-mono border transition" :class="whaleTier === 1 ? 'bg-accent/10 text-accent border-accent/30' : 'bg-surface-elevated border-border-subtle text-text-muted hover:text-text-primary'">Tier 1 ($1M+)</button>
                <button type="button" @click="whaleTier = 2; fetchWhales()" class="px-2.5 py-1 rounded-md text-[10px] font-mono border transition" :class="whaleTier === 2 ? 'bg-accent/10 text-accent border-accent/30' : 'bg-surface-elevated border-border-subtle text-text-muted hover:text-text-primary'">Tier 2 ($10M+)</button>
                <button type="button" @click="whaleTier = 3; fetchWhales()" class="px-2.5 py-1 rounded-md text-[10px] font-mono border transition" :class="whaleTier === 3 ? 'bg-accent/10 text-accent border-accent/30' : 'bg-surface-elevated border-border-subtle text-text-muted hover:text-text-primary'">Tier 3 ($50M+)</button>
            </div>
        </div>

        <div class="overflow-x-auto max-h-[420px] overflow-y-auto">
            <table class="w-full text-xs">
                <thead class="sticky top-0 bg-surface-elevated">
                    <tr class="text-text-muted font-mono uppercase text-[10px] tracking-wider">
                        <th class="text-left px-4 py-2.5">Waktu</th>
                        <th class="text-left px-4 py-2.5">Koin</th>
                        <th class="text-center px-4 py-2.5">Arah</th>
                        <th class="text-center px-4 py-2.5">Tier</th>
                        <th class="text-right px-4 py-2.5">Nominal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle/60">
                    <template x-for="w in whales" :key="w.symbol + '-' + w.timestamp + '-' + w.notional_usd">
                        <tr class="hover:bg-surface-hover transition">
                            <td class="px-4 py-2.5 font-mono text-text-muted whitespace-nowrap" x-text="formatTime(w.timestamp)"></td>
                            <td class="px-4 py-2.5 font-semibold text-text-primary" x-text="w.symbol"></td>
                            <td class="px-4 py-2.5 text-center">
                                <span
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase"
                                    :class="w.side === 'buy' ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-trade-loss-bg text-trade-loss border-trade-loss/20'"
                                    x-text="w.side === 'buy' ? 'Beli Agresif' : 'Jual Masif'"
                                ></span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded border"
                                    :class="{
                                        'bg-amber-500/10 text-amber-400 border-amber-500/20': w.tier === 1,
                                        'bg-accent/10 text-accent border-accent/20': w.tier === 2,
                                        'bg-purple-500/10 text-purple-400 border-purple-500/20': w.tier === 3,
                                    }"
                                    x-text="'T' + w.tier"
                                ></span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono font-bold text-text-primary" x-text="formatMoney(w.notional_usd)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div x-show="whales.length === 0" class="px-4 py-10 text-center text-xs text-text-muted font-mono">
                Belum ada transaksi paus terdeteksi pada tier ini.
            </div>
        </div>
    </div>

    <!-- Per-Coin Detail Modal: net flow summary + live order book -->
    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-xs" @click="closeModal()"></div>

        <div class="relative bg-surface border border-border-subtle rounded-xl w-full max-w-lg max-h-[85vh] overflow-y-auto shadow-xl">
            <div class="sticky top-0 bg-surface px-5 py-4 border-b border-border-subtle flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold font-mono text-text-primary" x-text="selectedCoin?.symbol"></h3>
                    <p class="text-[10px] font-mono text-text-muted" x-text="selectedCoin?.price ? formatPrice(selectedCoin.price) : ''"></p>
                </div>
                <button type="button" @click="closeModal()" class="p-1.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-surface-hover transition">
                    <x-app-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <!-- Net flow summary -->
            <div class="grid grid-cols-3 gap-2 p-4 border-b border-border-subtle" x-show="selectedCoin">
                <div class="text-center">
                    <span class="block text-[9px] font-mono uppercase text-text-muted">1H</span>
                    <span class="block text-xs font-mono font-bold" :class="selectedCoin?.net_flow_1h >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="selectedCoin ? (selectedCoin.net_flow_1h >= 0 ? '+' : '') + formatMoney(selectedCoin.net_flow_1h) : ''"></span>
                </div>
                <div class="text-center">
                    <span class="block text-[9px] font-mono uppercase text-text-muted">4H</span>
                    <span class="block text-xs font-mono font-bold" :class="selectedCoin?.net_flow_4h >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="selectedCoin ? (selectedCoin.net_flow_4h >= 0 ? '+' : '') + formatMoney(selectedCoin.net_flow_4h) : ''"></span>
                </div>
                <div class="text-center">
                    <span class="block text-[9px] font-mono uppercase text-text-muted">24H</span>
                    <span class="block text-xs font-mono font-bold" :class="selectedCoin?.net_flow_24h >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="selectedCoin ? (selectedCoin.net_flow_24h >= 0 ? '+' : '') + formatMoney(selectedCoin.net_flow_24h) : ''"></span>
                </div>
            </div>

            <!-- Order book -->
            <div class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-text-muted">Order Book Live</span>
                    <x-app-icon name="refresh-cw" class="w-3 h-3 text-accent" x-bind:class="orderBookLoading ? 'animate-spin' : ''" />
                </div>

                <template x-if="!orderBookLoading && !orderBook">
                    <p class="text-xs text-text-muted font-mono text-center py-6">Order book tidak tersedia untuk koin ini.</p>
                </template>

                <template x-if="orderBook">
                    <div class="space-y-0.5 font-mono text-[11px]">
                        <div class="grid grid-cols-2 gap-2 text-[9px] uppercase text-text-muted pb-1">
                            <span>Harga (Ask)</span>
                            <span class="text-right">Jumlah</span>
                        </div>
                        <template x-for="(level, i) in [...orderBook.asks].reverse()" :key="'ask-' + i">
                            <div class="grid grid-cols-2 gap-2">
                                <span class="text-trade-loss" x-text="formatPrice(level.price)"></span>
                                <span class="text-right text-text-secondary" x-text="level.amount"></span>
                            </div>
                        </template>

                        <div class="text-center py-1.5 my-1 border-y border-border-subtle/60 text-text-primary font-bold" x-text="selectedCoin?.price ? formatPrice(selectedCoin.price) : ''"></div>

                        <template x-for="(level, i) in orderBook.bids" :key="'bid-' + i">
                            <div class="grid grid-cols-2 gap-2">
                                <span class="text-trade-profit" x-text="formatPrice(level.price)"></span>
                                <span class="text-right text-text-secondary" x-text="level.amount"></span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function moneyFlow(initialMatrix, initialWhales) {
    return {
        netFlowMatrix: initialMatrix || [],
        whales: initialWhales || [],
        whaleTier: 1,
        sortMode: 'inflow',
        currencyUnit: localStorage.getItem('money_flow_currency') || 'USD',
        isLive: true,
        usdToIdrRate: 17756,
        matrixTimer: null,
        whaleTimer: null,

        showModal: false,
        selectedCoin: null,
        orderBook: null,
        orderBookLoading: false,
        orderBookTimer: null,

        get sortedMatrix() {
            const list = [...this.netFlowMatrix];
            return this.sortMode === 'inflow' ? list : list.reverse();
        },

        get topInflow() {
            return this.netFlowMatrix.slice(0, 10);
        },

        get topOutflow() {
            return [...this.netFlowMatrix].reverse().slice(0, 10);
        },

        setCurrency(cur) {
            this.currencyUnit = cur;
            localStorage.setItem('money_flow_currency', cur);
        },

        init() {
            this.matrixTimer = setInterval(() => this.fetchMatrix(), 30000);
            this.whaleTimer = setInterval(() => this.fetchWhales(), 12000);
            window.addEventListener('beforeunload', () => {
                clearInterval(this.matrixTimer);
                clearInterval(this.whaleTimer);
                clearInterval(this.orderBookTimer);
            });
        },

        async fetchMatrix() {
            try {
                const res = await fetch(`{{ route('money-flow.matrix') }}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.netFlowMatrix = data.matrix || [];
                this.isLive = !!data.is_live;
            } catch (e) {
                console.error('[MoneyFlow] matrix fetch failed', e);
            }
        },

        async fetchWhales() {
            try {
                const res = await fetch(`{{ route('money-flow.whales') }}?tier=${this.whaleTier}`, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                this.whales = data.whales || [];
                this.isLive = !!data.is_live;
            } catch (e) {
                console.error('[MoneyFlow] whale fetch failed', e);
            }
        },

        openCoinDetail(coin) {
            this.selectedCoin = coin;
            this.orderBook = null;
            this.showModal = true;
            this.fetchOrderBook();
            clearInterval(this.orderBookTimer);
            this.orderBookTimer = setInterval(() => this.fetchOrderBook(), 5000);
        },

        closeModal() {
            this.showModal = false;
            clearInterval(this.orderBookTimer);
        },

        async fetchOrderBook() {
            if (!this.selectedCoin) return;
            this.orderBookLoading = true;
            try {
                const res = await fetch(`{{ route('money-flow.orderbook') }}?symbol=${this.selectedCoin.symbol}`, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) {
                    this.orderBook = null;
                    return;
                }
                this.orderBook = await res.json();
            } catch (e) {
                console.error('[MoneyFlow] order book fetch failed', e);
                this.orderBook = null;
            } finally {
                this.orderBookLoading = false;
            }
        },

        formatPrice(price) {
            const p = Number(price) || 0;
            if (p <= 0) return '$0.00';
            if (p >= 50) return '$' + p.toFixed(2);
            if (p >= 0.01) return '$' + p.toFixed(4);
            if (p >= 0.0001) return '$' + p.toFixed(6);
            return '$' + p.toFixed(8);
        },

        formatMoney(usdValue) {
            const val = Number(usdValue) || 0;
            if (this.currencyUnit === 'IDR') {
                const idr = val * this.usdToIdrRate;
                const abs = Math.abs(idr);
                const sign = idr < 0 ? '-' : '';
                if (abs >= 1e12) return sign + 'Rp ' + (abs / 1e12).toFixed(2) + 'T';
                if (abs >= 1e9) return sign + 'Rp ' + (abs / 1e9).toFixed(2) + 'M';
                return sign + 'Rp ' + Math.round(abs).toLocaleString('id-ID');
            }
            const abs = Math.abs(val);
            const sign = val < 0 ? '-' : '';
            if (abs >= 1e6) return sign + '$' + (abs / 1e6).toFixed(2) + 'M';
            if (abs >= 1e3) return sign + '$' + (abs / 1e3).toFixed(1) + 'K';
            return sign + '$' + abs.toFixed(0);
        },

        formatTime(unixSeconds) {
            const dt = new Date(unixSeconds * 1000);
            return dt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        },
    };
}
</script>
@endsection
