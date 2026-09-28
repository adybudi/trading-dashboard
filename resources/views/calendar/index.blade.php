@extends('layouts.app')

@section('content')
<div
    x-data="econCalendar('{{ $currency }}', '{{ $range }}', @js($nextHighImpact))"
    x-init="init()"
    class="space-y-6"
>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="calendar-days" class="w-5 h-5 text-accent" />
                <span>KALENDER EKONOMI</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">FOMC, NFP, CPI, dan rilis data ekonomi berdampak tinggi lainnya.</p>
        </div>

        <span
            class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-mono border"
            :class="isLive ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-surface-elevated text-text-muted border-border-subtle'"
        >
            <span class="w-1.5 h-1.5 rounded-full" :class="isLive ? 'bg-trade-profit animate-pulse' : 'bg-text-muted'"></span>
            <span x-text="isLive ? 'LIVE FEED' : 'MODE FALLBACK'"></span>
        </span>
    </div>

    <!-- Live Countdown Hero -->
    <div class="bg-surface border border-border-subtle rounded-xl p-5" x-show="nextEvent">
        <template x-if="nextEvent">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] font-mono uppercase tracking-widest text-trade-loss">Rilis High Impact Berikutnya</span>
                    <h2 class="text-lg font-bold text-text-primary mt-1" x-text="nextEvent.title"></h2>
                    <p class="text-xs text-text-secondary mt-0.5" x-text="nextEvent.currency + ' • ' + formatDate(nextEvent.timestamp)"></p>
                </div>
                <div class="font-mono text-2xl sm:text-3xl font-bold text-accent tabular-nums tracking-tight" x-text="countdownLabel"></div>
            </div>
        </template>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
        <select x-model="currency" @change="fetchEvents()" class="bg-surface border border-border-subtle text-text-primary text-xs rounded-lg px-3 py-2 font-mono">
            <option value="all">Semua Mata Uang</option>
            <option value="USD">USD (AS)</option>
            <option value="EUR">EUR (Eropa)</option>
            <option value="GBP">GBP (Inggris)</option>
            <option value="JPY">JPY (Jepang)</option>
            <option value="CRYPTO">CRYPTO</option>
        </select>

        <select x-model="range" @change="fetchEvents()" class="bg-surface border border-border-subtle text-text-primary text-xs rounded-lg px-3 py-2 font-mono">
            <option value="today">Hari Ini</option>
            <option value="week">Minggu Ini</option>
            <option value="month">Bulan Ini</option>
            <option value="upcoming">Event Mendatang</option>
        </select>
    </div>

    <!-- Empty State -->
    <template x-if="!loading && events.length === 0">
        <div class="bg-surface border border-border-subtle rounded-xl p-10 text-center text-sm font-mono">
            <template x-if="!isLive">
                <div class="text-amber-400">
                    Sumber data kalender sedang dibatasi sementara (rate limit) oleh penyedia feed.<br>
                    <span class="text-text-muted text-xs">Sistem otomatis mencoba lagi setiap beberapa puluh detik — tidak perlu refresh manual.</span>
                </div>
            </template>
            <template x-if="isLive">
                <div class="text-text-muted">Tidak ada event ekonomi untuk filter ini.</div>
            </template>
        </div>
    </template>

    <!-- Event Table -->
    <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden" x-show="events.length > 0">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-surface-elevated text-text-muted font-mono uppercase text-[10px] tracking-wider">
                        <th class="text-left px-4 py-2.5">Waktu</th>
                        <th class="text-left px-4 py-2.5">Mata Uang</th>
                        <th class="text-left px-4 py-2.5">Event</th>
                        <th class="text-center px-4 py-2.5">Dampak</th>
                        <th class="text-right px-4 py-2.5">Actual</th>
                        <th class="text-right px-4 py-2.5">Forecast</th>
                        <th class="text-right px-4 py-2.5">Previous</th>
                        <th class="text-center px-4 py-2.5">Deviasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle/60">
                    <template x-for="ev in events" :key="ev.id">
                        <tr class="hover:bg-surface-hover transition">
                            <td class="px-4 py-2.5 font-mono text-text-secondary whitespace-nowrap" x-text="formatDate(ev.timestamp)"></td>
                            <td class="px-4 py-2.5 font-mono font-semibold text-text-primary" x-text="ev.currency"></td>
                            <td class="px-4 py-2.5 text-text-primary" x-text="ev.title"></td>
                            <td class="px-4 py-2.5 text-center">
                                <span
                                    class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase"
                                    :class="{
                                        'bg-trade-loss-bg text-trade-loss border-trade-loss/20': ev.impact === 'high',
                                        'bg-amber-500/10 text-amber-400 border-amber-500/20': ev.impact === 'medium',
                                        'bg-surface-hover text-text-muted border-border-subtle': ev.impact === 'low',
                                    }"
                                    x-text="ev.impact"
                                ></span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-text-primary" x-text="ev.actual ?? '-'"></td>
                            <td class="px-4 py-2.5 text-right font-mono text-text-secondary" x-text="ev.forecast ?? '-'"></td>
                            <td class="px-4 py-2.5 text-right font-mono text-text-muted" x-text="ev.previous ?? '-'"></td>
                            <td class="px-4 py-2.5 text-center">
                                <template x-if="ev.deviation">
                                    <span
                                        class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase"
                                        :class="{
                                            'bg-trade-profit-bg text-trade-profit border-trade-profit/20': ev.deviation === 'beat',
                                            'bg-trade-loss-bg text-trade-loss border-trade-loss/20': ev.deviation === 'miss',
                                            'bg-surface-hover text-text-secondary border-border-subtle': ev.deviation === 'inline',
                                        }"
                                        x-text="ev.deviation"
                                    ></span>
                                </template>
                                <span x-show="!ev.deviation" class="text-text-muted">-</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function econCalendar(initialCurrency, initialRange, initialNextEvent) {
    return {
        events: [],
        currency: initialCurrency || 'all',
        range: initialRange || 'week',
        loading: false,
        isLive: true,
        nextEvent: initialNextEvent || null,
        countdownLabel: '--:--:--',
        pollTimer: null,
        countdownTimer: null,

        init() {
            this.fetchEvents();
            this.pollTimer = setInterval(() => this.fetchEvents(), 30000);
            this.countdownTimer = setInterval(() => this.tickCountdown(), 1000);
            this.tickCountdown();
            window.addEventListener('beforeunload', () => {
                clearInterval(this.pollTimer);
                clearInterval(this.countdownTimer);
            });
        },

        async fetchEvents() {
            this.loading = true;
            try {
                const res = await fetch(`{{ route('calendar.events') }}?currency=${this.currency}&range=${this.range}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.events = data.events || [];
                this.nextEvent = data.next_high_impact || null;
                this.isLive = !!data.is_live;
            } catch (e) {
                console.error('[KalenderEkonomi] fetch failed', e);
            } finally {
                this.loading = false;
            }
        },

        tickCountdown() {
            if (!this.nextEvent) {
                this.countdownLabel = '--:--:--';
                return;
            }
            const diff = (this.nextEvent.timestamp * 1000) - Date.now();
            if (diff <= 0) {
                this.countdownLabel = 'RILIS SEKARANG';
                this.fetchEvents();
                return;
            }
            const d = Math.floor(diff / 86400000);
            const h = Math.floor((diff % 86400000) / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.countdownLabel = (d > 0 ? d + 'h ' : '') + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        formatDate(unixSeconds) {
            const dt = new Date(unixSeconds * 1000);
            return dt.toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
    };
}
</script>
@endsection
