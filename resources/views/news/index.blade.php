@extends('layouts.app')

@section('content')
<div
    x-data="newsFeed('{{ $category }}')"
    x-init="init()"
    class="space-y-6"
>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="newspaper" class="w-5 h-5 text-accent" />
                <span>BERITA DUNIA</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">Agregasi berita kripto, forex, dan makroekonomi global secara real-time.</p>
        </div>

        <div class="flex items-center gap-2">
            <span
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-mono border"
                :class="isLive ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : 'bg-surface-elevated text-text-muted border-border-subtle'"
            >
                <span class="w-1.5 h-1.5 rounded-full" :class="isLive ? 'bg-trade-profit animate-pulse' : 'bg-text-muted'"></span>
                <span x-text="isLive ? 'LIVE FEED' : 'MODE FALLBACK'"></span>
            </span>
            <button
                @click="fetchNews(true)"
                type="button"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-mono border border-border-subtle text-text-secondary hover:text-text-primary hover:bg-surface-hover transition"
            >
                <x-app-icon name="refresh-cw" class="w-3.5 h-3.5" x-bind:class="loading ? 'animate-spin' : ''" />
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Category Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2">
        <template x-for="cat in categories" :key="cat.key">
            <button
                type="button"
                @click="setCategory(cat.key)"
                class="px-3 py-1.5 rounded-lg text-xs font-mono font-medium border transition"
                :class="category === cat.key ? 'bg-accent/10 text-accent border-accent/30' : 'bg-surface border-border-subtle text-text-secondary hover:text-text-primary hover:bg-surface-hover'"
                x-text="cat.label"
            ></button>
        </template>

        <div class="ml-auto flex items-center rounded-lg bg-surface-elevated border border-border-subtle p-0.5">
            <button type="button" @click="layout = 'grid'" class="px-2.5 py-1 rounded-md text-[11px] font-mono transition" :class="layout === 'grid' ? 'bg-surface text-accent shadow-xs' : 'text-text-muted hover:text-text-primary'">Grid</button>
            <button type="button" @click="layout = 'list'" class="px-2.5 py-1 rounded-md text-[11px] font-mono transition" :class="layout === 'list' ? 'bg-surface text-accent shadow-xs' : 'text-text-muted hover:text-text-primary'">List</button>
        </div>
    </div>

    <!-- Empty State -->
    <template x-if="!loading && items.length === 0">
        <div class="bg-surface border border-border-subtle rounded-xl p-10 text-center text-text-muted text-sm font-mono">
            Tidak ada berita untuk kategori ini saat ini.
        </div>
    </template>

    <!-- Card Grid Layout -->
    <div x-show="layout === 'grid'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <template x-for="item in pagedItems" :key="item.id">
            <a :href="item.url" target="_blank" rel="noopener noreferrer" class="bg-surface border border-border-subtle rounded-xl p-4 flex flex-col gap-3 hover:border-border-muted hover:shadow-sm transition group">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[10px] font-mono uppercase tracking-wider text-text-muted truncate" x-text="item.source"></span>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <span
                            class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase"
                            :class="{
                                'bg-trade-loss-bg text-trade-loss border-trade-loss/20': item.impact === 'high',
                                'bg-amber-500/10 text-amber-400 border-amber-500/20': item.impact === 'medium',
                                'bg-surface-hover text-text-muted border-border-subtle': item.impact === 'low',
                            }"
                            x-text="item.impact"
                        ></span>
                        <span
                            class="text-[10px] font-mono px-1.5 py-0.5 rounded border"
                            :class="{
                                'bg-trade-profit-bg text-trade-profit border-trade-profit/20': item.sentiment === 'bullish',
                                'bg-trade-loss-bg text-trade-loss border-trade-loss/20': item.sentiment === 'bearish',
                                'bg-surface-hover text-text-secondary border-border-subtle': item.sentiment === 'neutral',
                            }"
                            x-text="item.sentiment.toUpperCase()"
                        ></span>
                    </div>
                </div>
                <h3 class="text-sm font-semibold text-text-primary group-hover:text-accent transition leading-snug" x-text="item.title"></h3>
                <p class="text-xs text-text-secondary leading-relaxed line-clamp-3" x-text="item.body"></p>
                <div class="flex items-center justify-between mt-auto pt-2 border-t border-border-subtle/60">
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-surface-elevated text-text-muted border border-border-subtle uppercase" x-text="item.category"></span>
                    <span class="text-[10px] font-mono text-text-muted" x-text="timeAgo(item.published_at)"></span>
                </div>
            </a>
        </template>
    </div>

    <!-- Compact List Layout -->
    <div x-show="layout === 'list'" class="bg-surface border border-border-subtle rounded-xl divide-y divide-border-subtle/60 overflow-hidden">
        <template x-for="item in pagedItems" :key="'list-' + item.id">
            <a :href="item.url" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-4 py-3 hover:bg-surface-hover transition group">
                <span
                    class="w-1.5 h-1.5 rounded-full shrink-0"
                    :class="{
                        'bg-trade-profit': item.sentiment === 'bullish',
                        'bg-trade-loss': item.sentiment === 'bearish',
                        'bg-text-muted': item.sentiment === 'neutral',
                    }"
                ></span>
                <span class="text-xs font-medium text-text-primary group-hover:text-accent transition truncate flex-1" x-text="item.title"></span>
                <span
                    class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase shrink-0 hidden sm:inline-block"
                    :class="{
                        'bg-trade-loss-bg text-trade-loss border-trade-loss/20': item.impact === 'high',
                        'bg-amber-500/10 text-amber-400 border-amber-500/20': item.impact === 'medium',
                        'bg-surface-hover text-text-muted border-border-subtle': item.impact === 'low',
                    }"
                    x-text="item.impact"
                ></span>
                <span class="text-[10px] font-mono text-text-muted shrink-0 hidden sm:inline" x-text="item.source"></span>
                <span class="text-[10px] font-mono text-text-muted shrink-0" x-text="timeAgo(item.published_at)"></span>
            </a>
        </template>
    </div>

    <!-- Pagination -->
    <div x-show="items.length > 0" class="flex items-center justify-between gap-3 pt-2">
        <span class="text-[11px] font-mono text-text-muted" x-text="paginationLabel"></span>
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                @click="goToPage(currentPage - 1)"
                :disabled="currentPage <= 1"
                class="p-1.5 rounded-lg border border-border-subtle text-text-secondary hover:text-text-primary hover:bg-surface-hover transition disabled:opacity-30 disabled:pointer-events-none"
            >
                <x-app-icon name="chevron-left" class="w-3.5 h-3.5" />
            </button>
            <template x-for="p in pageNumbers" :key="'page-' + p">
                <button
                    type="button"
                    @click="goToPage(p)"
                    class="min-w-[28px] h-7 px-1.5 rounded-lg text-[11px] font-mono border transition"
                    :class="p === currentPage ? 'bg-accent/10 text-accent border-accent/30' : 'bg-surface border-border-subtle text-text-secondary hover:text-text-primary hover:bg-surface-hover'"
                    x-text="p"
                ></button>
            </template>
            <button
                type="button"
                @click="goToPage(currentPage + 1)"
                :disabled="currentPage >= totalPages"
                class="p-1.5 rounded-lg border border-border-subtle text-text-secondary hover:text-text-primary hover:bg-surface-hover transition disabled:opacity-30 disabled:pointer-events-none"
            >
                <x-app-icon name="chevron-right" class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>
</div>

<script>
function newsFeed(initialCategory) {
    return {
        items: [],
        category: initialCategory || 'all',
        layout: 'grid',
        loading: false,
        isLive: true,
        pollTimer: null,
        currentPage: 1,
        perPage: 12,
        categories: [
            { key: 'all', label: 'Semua Berita' },
            { key: 'crypto', label: 'Crypto Focus' },
            { key: 'forex', label: 'Forex & Mata Uang' },
            { key: 'central-bank', label: 'Bank Sentral & The Fed' },
            { key: 'macro', label: 'Makroekonomi' },
        ],

        get totalPages() {
            return Math.max(1, Math.ceil(this.items.length / this.perPage));
        },

        get pagedItems() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.items.slice(start, start + this.perPage);
        },

        get pageNumbers() {
            const pages = [];
            for (let p = 1; p <= this.totalPages; p++) pages.push(p);
            return pages;
        },

        get paginationLabel() {
            if (this.items.length === 0) return '';
            const start = (this.currentPage - 1) * this.perPage + 1;
            const end = Math.min(this.currentPage * this.perPage, this.items.length);
            return `Menampilkan ${start}-${end} dari ${this.items.length} berita`;
        },

        goToPage(p) {
            if (p < 1 || p > this.totalPages) return;
            this.currentPage = p;
        },

        init() {
            this.fetchNews(true);
            this.pollTimer = setInterval(() => this.fetchNews(false), 20000);
            window.addEventListener('beforeunload', () => clearInterval(this.pollTimer));
        },

        setCategory(key) {
            this.category = key;
            this.currentPage = 1;
            this.fetchNews(true);
        },

        async fetchNews(showLoading) {
            if (showLoading) this.loading = true;
            try {
                const res = await fetch(`{{ route('news.live') }}?category=${this.category}`, {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.items = data.items || [];
                this.isLive = !!data.is_live;
                if (this.currentPage > this.totalPages) this.currentPage = this.totalPages;
            } catch (e) {
                console.error('[BeritaDunia] fetch failed', e);
            } finally {
                this.loading = false;
            }
        },

        timeAgo(unixSeconds) {
            const diff = Math.max(0, Math.floor(Date.now() / 1000) - unixSeconds);
            if (diff < 60) return 'Baru saja';
            if (diff < 3600) return Math.floor(diff / 60) + ' menit lalu';
            if (diff < 86400) return Math.floor(diff / 3600) + ' jam lalu';
            return Math.floor(diff / 86400) + ' hari lalu';
        },
    };
}
</script>
@endsection
