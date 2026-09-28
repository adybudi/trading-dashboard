@extends('layouts.app')

@section('content')
@if(!$article)
    <div class="max-w-2xl mx-auto py-16 text-center space-y-4">
        <x-app-icon name="alert-triangle" class="w-10 h-10 text-amber-400 mx-auto" />
        <h1 class="text-lg font-bold text-text-primary font-mono">Berita Tidak Ditemukan</h1>
        <p class="text-sm text-text-secondary">
            Artikel ini sudah tidak ada di cache feed terkini (berita bersifat sementara, tidak disimpan permanen).
            Silakan kembali ke Berita Dunia untuk melihat berita terbaru.
        </p>
        <a href="{{ route('news.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-semibold transition">
            <x-app-icon name="chevron-left" class="w-3.5 h-3.5" />
            <span>Kembali ke Berita Dunia</span>
        </a>
    </div>
@else
    <div
        x-data="newsDetail(@js($article))"
        class="max-w-3xl mx-auto space-y-6"
    >
        <a href="{{ route('news.index') }}" class="inline-flex items-center gap-1.5 text-xs font-mono text-text-secondary hover:text-accent transition">
            <x-app-icon name="chevron-left" class="w-3.5 h-3.5" />
            <span>Kembali ke Berita Dunia</span>
        </a>

        <div class="bg-surface border border-border-subtle rounded-xl p-6 space-y-5">
            <!-- Meta row -->
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-mono uppercase tracking-wider text-text-muted">{{ $article['source'] }}</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase {{ $article['impact'] === 'high' ? 'bg-trade-loss-bg text-trade-loss border-trade-loss/20' : ($article['impact'] === 'medium' ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-surface-hover text-text-muted border-border-subtle') }}">
                        {{ $article['impact'] }}
                    </span>
                    <span class="text-[10px] font-mono px-1.5 py-0.5 rounded border uppercase {{ $article['sentiment'] === 'bullish' ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20' : ($article['sentiment'] === 'bearish' ? 'bg-trade-loss-bg text-trade-loss border-trade-loss/20' : 'bg-surface-hover text-text-secondary border-border-subtle') }}">
                        {{ $article['sentiment'] }}
                    </span>
                </div>

                <!-- Language Toggle -->
                <div class="flex items-center rounded-lg bg-surface-elevated border border-border-subtle p-0.5">
                    <button type="button" @click="setLang('id')" class="px-2.5 py-1 rounded-md text-[11px] font-mono transition" :class="lang === 'id' ? 'bg-surface text-accent shadow-xs' : 'text-text-muted hover:text-text-primary'">Indonesia</button>
                    <button type="button" @click="setLang('en')" class="px-2.5 py-1 rounded-md text-[11px] font-mono transition" :class="lang === 'en' ? 'bg-surface text-accent shadow-xs' : 'text-text-muted hover:text-text-primary'">English</button>
                </div>
            </div>

            @if($article['image'])
                <img src="{{ $article['image'] }}" alt="" class="w-full max-h-80 object-cover rounded-lg border border-border-subtle" loading="lazy" onerror="this.remove()">
            @endif

            <!-- Title & Body -->
            <div class="relative">
                <div x-show="loading" class="absolute inset-0 flex items-center justify-center bg-surface/60">
                    <x-app-icon name="refresh-cw" class="w-5 h-5 text-accent animate-spin" />
                </div>
                <h1 class="text-xl font-bold text-text-primary leading-snug" x-text="title"></h1>
                <p class="text-sm text-text-secondary leading-relaxed mt-4 whitespace-pre-line" x-text="body || 'Ringkasan tidak tersedia dari sumber ini.'"></p>
                <p x-show="lang === 'id' && !translated && !loading" class="text-[11px] font-mono text-amber-400 mt-3">
                    Layanan terjemahan sedang tidak tersedia, menampilkan teks asli (English).
                </p>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-border-subtle flex-wrap gap-3">
                <span class="text-[11px] font-mono text-text-muted">{{ \Illuminate\Support\Carbon::createFromTimestamp($article['published_at'])->translatedFormat('d M Y, H:i') }}</span>
                <a href="{{ $article['url'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-semibold transition">
                    <span>Buka Sumber Asli</span>
                    <x-app-icon name="arrow-up-right" class="w-3.5 h-3.5" />
                </a>
            </div>
        </div>
    </div>

    <script>
    function newsDetail(article) {
        return {
            title: article.title,
            body: article.body,
            lang: 'en',
            translated: true,
            loading: false,
            cache: {},

            async setLang(target) {
                this.lang = target;

                if (this.cache[target]) {
                    this.title = this.cache[target].title;
                    this.body = this.cache[target].body;
                    this.translated = this.cache[target].translated !== false;
                    return;
                }

                this.loading = true;
                try {
                    const res = await fetch(`{{ route('news.translate', ':id') }}`.replace(':id', article.id) + `?lang=${target}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    this.cache[target] = data;
                    this.title = data.title;
                    this.body = data.body;
                    this.translated = data.translated !== false;
                } catch (e) {
                    console.error('[NewsDetail] translate failed', e);
                    this.translated = false;
                } finally {
                    this.loading = false;
                }
            },
        };
    }
    </script>
@endif
@endsection
