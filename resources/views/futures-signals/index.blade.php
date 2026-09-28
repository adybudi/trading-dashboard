@extends('layouts.app')

@section('content')
<div x-data="smartScreenFutureComponent()" class="space-y-6">

    <!-- Top Action Header & Direction / Timeframe / Target Selector -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider bg-accent/10 text-accent border border-accent/20">
                    SMART SCREEN FUTURE ENGINE v1.0
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider bg-trade-profit-bg text-trade-profit border border-trade-profit/20 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-trade-profit animate-pulse"></span>
                    <span>Target: +{{ $targetMin }}% s.d. +{{ $targetMax }}%</span>
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-surface-elevated text-accent border border-border-subtle">
                    TF: {{ strtoupper($timeframe ?? '1h') }}
                </span>
                <span
                    x-show="isLive"
                    class="hidden sm:inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono bg-surface-elevated text-text-muted border border-border-subtle"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    <span x-text="'Live: ' + lastUpdated"></span>
                </span>
                <span
                    x-show="!isLive"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-amber-500/10 text-amber-500 border border-amber-500/30"
                    title="Koneksi exchange gagal — sistem menampilkan data cadangan (statis/sintetis), bukan harga live."
                >
                    <x-app-icon name="shield-alert" class="w-3 h-3" />
                    <span>Mode Fallback — Data Tidak Live</span>
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-surface-elevated text-text-secondary border border-border-subtle">
                    <span x-text="coinsData.length + ' Koin Pasar Futures Aktif (dari 250+ Pasangan Likuid)'"></span>
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-text-primary font-mono mt-1">
                SMART SCREEN FUTURE • 2-WAY LONG/SHORT SCANNER
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">
                Pemindaian futures 2 arah berbasis <strong>Inversion FVG (iFVG)</strong>, <strong>Liquidity Sweep (SSL/BSL)</strong>, Fibonacci Dealing Range &amp; konfluensi <strong>Money Flow Whale Tracker</strong>
            </p>
        </div>

        <!-- Controls: Direction, Timeframe, Target Selector & Run Scanner -->
        <div class="flex flex-wrap items-center gap-2.5">

            <!-- Direction Filter Pills -->
            <div class="flex items-center bg-surface border border-border-subtle rounded-lg p-0.5 text-xs font-mono" title="Semua Arah: pilih otomatis LONG (zona diskon) atau SHORT (zona premium) per koin. LONG Only / SHORT Only: paksa evaluasi 1 arah saja.">
                <span class="text-text-muted text-[10px] uppercase px-2">Arah:</span>
                <button type="button" @click="setDirection('all')" class="px-2.5 py-1 rounded text-xs font-bold transition" :class="direction === 'all' ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">Semua</button>
                <button type="button" @click="setDirection('long')" class="px-2.5 py-1 rounded text-xs font-bold transition" :class="direction === 'long' ? 'bg-emerald-500 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">LONG</button>
                <button type="button" @click="setDirection('short')" class="px-2.5 py-1 rounded text-xs font-bold transition" :class="direction === 'short' ? 'bg-rose-500 text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">SHORT</button>
            </div>

            <!-- Timeframe Selector Pills -->
            <div class="flex items-center bg-surface border border-border-subtle rounded-lg p-0.5 text-xs font-mono">
                <span class="text-text-muted text-[10px] uppercase px-2">TF:</span>
                @foreach(['15m' => '15M', '1h' => '1H', '4h' => '4H', '1d' => '1D'] as $tfKey => $tfLabel)
                    <button
                        type="button"
                        @click="navigateTimeframe('{{ $tfKey }}')"
                        class="px-2.5 py-1 rounded text-xs font-bold transition {{ ($timeframe ?? '1h') === $tfKey ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary' }}"
                    >
                        {{ $tfLabel }}
                    </button>
                @endforeach
            </div>

            <!-- Target Profit Selector -->
            <div class="flex items-center gap-1.5 bg-surface border border-border-subtle rounded-lg px-2.5 py-1.5 text-xs font-mono">
                <span class="text-text-muted text-[11px] uppercase">Target:</span>
                <select
                    @change="changeTargetProfile($event.target.value)"
                    class="bg-transparent text-text-primary font-bold focus:outline-none cursor-pointer"
                >
                    <option value="scalp" {{ ($targetProfile ?? '') === 'scalp' ? 'selected' : '' }} class="bg-surface text-text-primary">Scalp (+5% – +10%)</option>
                    <option value="standard" {{ ($targetProfile ?? 'standard') === 'standard' ? 'selected' : '' }} class="bg-surface text-text-primary">Standard (+10% – +20%)</option>
                    <option value="extended" {{ ($targetProfile ?? '') === 'extended' ? 'selected' : '' }} class="bg-surface text-text-primary">Extended (+20% – +35%)</option>
                    <option value="moonshot" {{ ($targetProfile ?? '') === 'moonshot' ? 'selected' : '' }} class="bg-surface text-text-primary">Big Runner (+35% – +50%+)</option>
                </select>
            </div>

            <form action="{{ route('futures-signals.scan') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="target_profile" value="{{ $targetProfile }}">
                <input type="hidden" name="timeframe" value="{{ $timeframe ?? '1h' }}">
                <input type="hidden" name="direction" :value="direction">
                <button
                    type="submit"
                    class="px-4 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-mono font-bold tracking-wider uppercase transition shadow-sm flex items-center gap-2 cursor-pointer"
                >
                    <x-app-icon name="refresh-cw" class="w-3.5 h-3.5" />
                    <span>Scan Semua Koin</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Search Bar & View Mode Switcher Control Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-surface border border-border-subtle rounded-xl p-3.5 shadow-xs">
        <div class="relative flex-1 max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                <x-app-icon name="search" class="w-4 h-4" />
            </div>
            <input
                type="text"
                x-model="searchQuery"
                @input="currentPage = 1"
                placeholder="Cari koin/sinyal (misal: BTC, ETH, SOL)..."
                class="w-full bg-surface-elevated border border-border-subtle rounded-lg pl-9 pr-3 py-2 text-xs text-text-primary font-mono focus:outline-none focus:border-accent placeholder:text-text-muted"
            />
        </div>

        <div class="flex items-center gap-2">
            <span class="text-[11px] font-mono text-text-muted uppercase">Tampilan Sinyal:</span>
            <div class="flex items-center bg-surface-elevated border border-border-subtle rounded-lg p-0.5">
                <button
                    type="button"
                    @click="viewMode = 'card'"
                    class="px-3 py-1.5 rounded-md text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer"
                    :class="viewMode === 'card' ? 'bg-surface text-accent shadow-xs' : 'text-text-secondary hover:text-text-primary'"
                >
                    <x-app-icon name="layers" class="w-3.5 h-3.5" />
                    <span>Card View</span>
                </button>
                <button
                    type="button"
                    @click="viewMode = 'list'"
                    class="px-3 py-1.5 rounded-md text-xs font-mono font-bold transition flex items-center gap-1.5 cursor-pointer"
                    :class="viewMode === 'list' ? 'bg-surface text-accent shadow-xs' : 'text-text-secondary hover:text-text-primary'"
                >
                    <x-app-icon name="sliders" class="w-3.5 h-3.5" />
                    <span>List View</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Active 2-Way Signal Section -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-mono font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                <x-app-icon name="zap" class="w-4 h-4 text-accent" />
                <span>SINYAL 2-ARAH TERDETEKSI (GRADE A+ &amp; A)</span>
            </h2>
            <div class="flex items-center gap-3 text-xs font-mono">
                <span class="text-trade-profit font-bold">TP1: +{{ $targetMin }}%</span>
                <span class="text-trade-profit font-bold">TP2: +{{ $targetMax }}%</span>
                <span class="text-text-muted">| TF: {{ strtoupper($timeframe ?? '1h') }}</span>
                <span class="flex items-center gap-1 text-[11px] text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Live Signals Price</span>
                </span>
            </div>
        </div>

        <template x-if="filteredSignals.length === 0">
            <div class="bg-surface border border-border-subtle rounded-xl p-8 text-center space-y-3 font-mono">
                <div class="w-10 h-10 rounded-full bg-surface-hover flex items-center justify-center mx-auto text-text-muted">
                    <x-app-icon name="search" class="w-5 h-5" />
                </div>
                <h3 class="text-sm font-bold text-text-primary">Tidak Ada Sinyal yang Cocok</h3>
                <p class="text-xs text-text-secondary max-w-md mx-auto">
                    Klik tombol "Scan Semua Koin" di atas untuk memindai ulang pasangan futures pada timeframe {{ strtoupper($timeframe ?? '1h') }}.
                </p>
            </div>
        </template>

        <!-- CARD VIEW -->
        <div x-show="viewMode === 'card' && filteredSignals.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <template x-for="signal in filteredSignals" :key="signal.id">
                <div
                    class="bg-surface border rounded-xl p-5 flex flex-col justify-between space-y-4 hover:border-border-muted transition duration-200"
                    :class="[
                        signal.grade === 'A+' ? 'border-trade-profit/40 shadow-lg shadow-trade-profit/5' : 'border-border-subtle',
                        signal.isNew ? 'ring-2 ring-accent shadow-lg shadow-accent/20' : ''
                    ]"
                >
                    <!-- Card Header -->
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-surface-elevated border border-border-subtle flex items-center justify-center font-mono text-xs font-bold text-accent" x-text="signal.symbol.replace('USDT', '').substring(0, 3)"></div>
                            <div>
                                <div class="font-mono text-sm font-bold text-text-primary" x-text="signal.symbol"></div>
                                <div class="text-[10px] font-mono text-text-muted" x-text="'Futures • TF ' + signal.timeframe.toUpperCase()"></div>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-1 font-mono">
                            <div class="flex items-center gap-1.5">
                                <span
                                    x-show="signal.isNew"
                                    class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-accent text-white animate-pulse"
                                >Baru</span>
                                <span
                                    class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase flex items-center gap-1"
                                    :class="signal.direction === 'short' ? 'bg-rose-500/15 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'"
                                >
                                    <x-app-icon name="trending-up" class="w-3 h-3" x-show="signal.direction === 'long'" />
                                    <x-app-icon name="trending-down" class="w-3 h-3" x-show="signal.direction === 'short'" />
                                    <span x-text="signal.direction === 'short' ? 'SHORT' : 'LONG'"></span>
                                </span>
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-bold"
                                    :class="signal.grade === 'A+' ? 'bg-trade-profit-bg text-trade-profit border border-trade-profit/30' : 'bg-accent/10 text-accent border border-accent/20'"
                                    x-text="'Grade ' + signal.grade"
                                ></span>
                                <span class="text-[11px] font-semibold text-text-secondary" x-text="signal.score + '/100'"></span>
                            </div>

                            <div
                                class="text-[11px] font-bold px-1.5 py-0.5 rounded transition-all duration-300 flex items-center gap-1"
                                :class="signal.priceFlash === 'up' ? 'bg-emerald-500/20 text-emerald-400' : (signal.priceFlash === 'down' ? 'bg-rose-500/20 text-rose-400' : 'text-text-secondary bg-surface-elevated')"
                            >
                                <span x-text="'Live: ' + formatCryptoPrice(signal.current_price)"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Price Levels Matrix -->
                    <div class="grid grid-cols-2 gap-2 text-xs font-mono">
                        <div class="p-2.5 rounded-lg bg-surface-elevated border border-border-subtle">
                            <span class="text-[10px] uppercase text-text-muted block" x-text="signal.direction === 'short' ? 'Entry Jual (Premium)' : 'Entry Beli (Diskon)'"></span>
                            <span class="font-bold text-text-primary text-sm" x-text="formatCryptoPrice(signal.entry_price)"></span>
                        </div>

                        <div
                            class="p-2.5 rounded-lg border transition-all duration-300"
                            :class="signal.priceFlash === 'up' ? 'bg-emerald-500/20 border-emerald-500/40' : (signal.priceFlash === 'down' ? 'bg-rose-500/20 border-rose-500/40' : 'bg-surface-elevated border-border-subtle')"
                        >
                            <span class="text-[10px] uppercase text-text-muted block">Harga Terkini</span>
                            <span class="font-bold text-sm" :class="signal.priceFlash === 'up' ? 'text-emerald-400' : (signal.priceFlash === 'down' ? 'text-rose-400' : 'text-text-primary')" x-text="formatCryptoPrice(signal.current_price)"></span>
                        </div>

                        <div class="p-2.5 rounded-lg bg-trade-profit-bg border border-trade-profit/20">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase text-trade-profit block">Target TP 1</span>
                                <span class="text-[10px] font-bold text-trade-profit" x-text="(signal.direction === 'short' ? '-' : '+') + targetMin + '%'"></span>
                            </div>
                            <span class="font-bold text-trade-profit text-sm" x-text="formatCryptoPrice(signal.take_profit_1)"></span>
                        </div>

                        <div class="p-2.5 rounded-lg bg-trade-profit-bg border border-trade-profit/30">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase text-trade-profit block">Target TP 2</span>
                                <span class="text-[10px] font-bold text-trade-profit" x-text="(signal.direction === 'short' ? '-' : '+') + targetMax + '%'"></span>
                            </div>
                            <span class="font-bold text-trade-profit text-sm" x-text="formatCryptoPrice(signal.take_profit_2)"></span>
                        </div>

                        <div class="p-2.5 rounded-lg bg-trade-loss-bg border border-trade-loss/20 col-span-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase text-trade-loss block">Stop Loss (Proteksi Risiko)</span>
                                <span class="text-[10px] font-bold text-trade-loss" x-text="'-' + signal.risk_percent + '%'"></span>
                            </div>
                            <span class="font-bold text-trade-loss text-sm" x-text="formatCryptoPrice(signal.stop_loss)"></span>
                        </div>
                    </div>

                    <!-- Risk:Reward + Liquidation @10x -->
                    <div class="grid grid-cols-2 gap-2 text-[11px] font-mono">
                        <div class="flex items-center justify-between px-2 py-1 rounded bg-surface-elevated/80 border border-border-subtle">
                            <span class="text-text-muted">Risk : Reward</span>
                            <span class="font-bold text-trade-profit" x-text="'1 : ' + signal.risk_reward_ratio"></span>
                        </div>
                        <div class="flex items-center justify-between px-2 py-1 rounded bg-amber-500/10 border border-amber-500/20">
                            <span class="text-amber-400">Liq. Est. @10x</span>
                            <span class="font-bold text-amber-400" x-text="formatCryptoPrice(signal.estimated_liquidation_10x)"></span>
                        </div>
                    </div>

                    <!-- Confluence Summary -->
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] font-mono uppercase text-text-muted block font-semibold">Konfluensi Teknikal:</span>
                        <p class="text-[11px] font-mono text-text-secondary leading-relaxed bg-surface-elevated/60 p-2.5 rounded-lg border border-border-subtle/60" x-text="signal.confluence_summary"></p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="grid grid-cols-2 gap-2 pt-2">
                        <button
                            type="button"
                            @click="openDetail(signal.symbol, signal.direction)"
                            class="w-full py-2 px-2 bg-surface-elevated hover:bg-border-subtle text-text-primary rounded-lg text-xs font-mono font-bold tracking-wide border border-border-subtle transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                            title="Buka Chart Candlestick"
                        >
                            <x-app-icon name="bar-chart-2" class="w-3.5 h-3.5 text-accent" />
                            <span>Chart</span>
                        </button>

                        <button
                            type="button"
                            @click="openSimModal(signal)"
                            class="w-full py-2 px-2 text-white rounded-lg text-xs font-mono font-bold tracking-wide transition shadow-sm flex items-center justify-center gap-1.5 cursor-pointer"
                            :class="signal.direction === 'short' ? 'bg-rose-500 hover:bg-rose-600' : 'bg-emerald-500 hover:bg-emerald-600'"
                            title="Buka Posisi Simulasi Futures Berpengungkit"
                        >
                            <x-app-icon name="play-circle" class="w-3.5 h-3.5" />
                            <span>Simulasi Futures</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- LIST VIEW -->
        <div x-show="viewMode === 'list' && filteredSignals.length > 0" class="bg-surface border border-border-subtle rounded-xl p-4 overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead>
                    <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase tracking-wider">
                        <th class="pb-2.5 font-medium">Aset / Koin</th>
                        <th class="pb-2.5 font-medium text-center">Arah</th>
                        <th class="pb-2.5 font-medium text-center">Grade / Skor</th>
                        <th class="pb-2.5 font-medium text-right">Harga Entry</th>
                        <th class="pb-2.5 font-medium text-right">Harga Live</th>
                        <th class="pb-2.5 font-medium text-right">TP1</th>
                        <th class="pb-2.5 font-medium text-right">TP2</th>
                        <th class="pb-2.5 font-medium text-right">Stop Loss</th>
                        <th class="pb-2.5 font-medium text-center">RRR</th>
                        <th class="pb-2.5 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    <template x-for="signal in filteredSignals" :key="signal.id">
                        <tr class="hover:bg-surface-hover transition">
                            <td class="py-3 font-bold text-text-primary">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded bg-surface-elevated border border-border-subtle flex items-center justify-center text-[10px] text-accent font-bold" x-text="signal.symbol.replace('USDT', '').substring(0, 3)"></span>
                                    <div>
                                        <span x-text="signal.symbol"></span>
                                        <span class="text-[10px] text-text-muted block font-normal" x-text="'TF ' + signal.timeframe.toUpperCase()"></span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 text-center">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                                    :class="signal.direction === 'short' ? 'bg-rose-500/15 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'"
                                    x-text="signal.direction"
                                ></span>
                            </td>
                            <td class="py-3 text-center">
                                <span
                                    class="px-2 py-0.5 rounded text-[11px] font-bold"
                                    :class="signal.grade === 'A+' ? 'bg-trade-profit-bg text-trade-profit border border-trade-profit/30' : 'bg-accent/10 text-accent border border-accent/20'"
                                    x-text="signal.grade + ' (' + signal.score + ')'"
                                ></span>
                            </td>
                            <td class="py-3 text-right font-bold text-text-primary" x-text="formatCryptoPrice(signal.entry_price)"></td>
                            <td class="py-3 text-right font-bold transition-all duration-300 rounded px-2"
                                :class="signal.priceFlash === 'up' ? 'bg-emerald-500/20 text-emerald-400' : (signal.priceFlash === 'down' ? 'bg-rose-500/20 text-rose-400' : 'text-text-primary')"
                                x-text="formatCryptoPrice(signal.current_price)"
                            ></td>
                            <td class="py-3 text-right font-bold text-trade-profit" x-text="formatCryptoPrice(signal.take_profit_1)"></td>
                            <td class="py-3 text-right font-bold text-trade-profit" x-text="formatCryptoPrice(signal.take_profit_2)"></td>
                            <td class="py-3 text-right font-bold text-trade-loss">
                                <span x-text="formatCryptoPrice(signal.stop_loss)"></span>
                                <span class="text-[10px] text-trade-loss block font-normal" x-text="'-' + signal.risk_percent + '%'"></span>
                            </td>
                            <td class="py-3 text-center font-bold text-trade-profit" x-text="'1:' + signal.risk_reward_ratio"></td>
                            <td class="py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="openDetail(signal.symbol, signal.direction)"
                                        class="px-2 py-1 bg-surface-elevated hover:bg-border-subtle text-text-primary border border-border-subtle rounded text-[11px] font-bold transition cursor-pointer"
                                    >Chart</button>
                                    <button
                                        type="button"
                                        @click="openSimModal(signal)"
                                        class="px-2 py-1 text-white rounded text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                                        :class="signal.direction === 'short' ? 'bg-rose-500 hover:bg-rose-600' : 'bg-emerald-500 hover:bg-emerald-600'"
                                    >
                                        <x-app-icon name="play-circle" class="w-3 h-3" />
                                        <span>Simulasi</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Live Futures Crypto Market List Table (Top 250 Coins with Dynamic Client Pagination & Real-Time Ticker) -->
    <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-border-subtle">
            <div>
                <h3 class="text-sm font-mono font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                    <x-app-icon name="wallet" class="w-4 h-4 text-accent" />
                    <span>Daftar Koin Futures Real-Time (Live Auto-Ticker Stream • Top 250 Pasar Dunia)</span>
                </h3>
                <p class="text-xs text-text-secondary mt-0.5">
                    Data pasar live teratas diurutkan otomatis dari volume transaksi 24 jam tertinggi — 50 koin teratas dari daftar ini yang aktif di-scan untuk sinyal 2-arah tiap siklus.
                </p>
            </div>
            <div class="flex items-center gap-2 font-mono text-[11px] text-text-muted">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Auto-Ticker Aktif</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs font-mono">
                <thead>
                    <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase tracking-wider">
                        <th class="pb-2.5 font-medium">Aset / Koin</th>
                        <th class="pb-2.5 font-medium text-right">Harga Terkini</th>
                        <th class="pb-2.5 font-medium text-right">Perubahan 24h</th>
                        <th class="pb-2.5 font-medium text-right">Volume 24h</th>
                        <th class="pb-2.5 font-medium text-right">Tertinggi / Terendah 24h</th>
                        <th class="pb-2.5 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle">
                    <template x-for="coin in paginatedCoins" :key="coin.symbol">
                        <tr class="hover:bg-surface-hover transition-colors">
                            <td class="py-3 font-bold text-text-primary">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded bg-surface-elevated border border-border-subtle flex items-center justify-center text-[10px] text-accent font-bold" x-text="coin.name.substring(0, 2)"></span>
                                    <div>
                                        <span x-text="coin.pair"></span>
                                        <span class="text-[10px] text-text-muted block font-normal" x-text="coin.name"></span>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3 text-right font-bold transition-all duration-300 rounded px-2"
                                :class="coin.priceFlash === 'up' ? 'bg-emerald-500/20 text-emerald-400' : (coin.priceFlash === 'down' ? 'bg-rose-500/20 text-rose-400' : 'text-text-primary')"
                                x-text="formatCryptoPrice(coin.price)"
                            ></td>

                            <td class="py-3 text-right font-bold" :class="coin.is_positive ? 'text-trade-profit' : 'text-trade-loss'">
                                <span x-text="(coin.is_positive ? '+' : '') + Number(coin.change_24h).toFixed(2) + '%'"></span>
                            </td>

                            <td class="py-3 text-right text-text-secondary" x-text="coin.formatted_volume"></td>

                            <td class="py-3 text-right text-text-muted text-[11px]">
                                <span x-text="formatCryptoPrice(coin.high_24h) + ' / ' + formatCryptoPrice(coin.low_24h)"></span>
                            </td>

                            <td class="py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="openDetail(coin.symbol, 'auto')"
                                        class="px-2 py-1 bg-surface-elevated hover:bg-accent hover:text-white text-text-primary border border-border-subtle rounded text-[11px] font-bold transition cursor-pointer"
                                        title="Buka Grafik Candlestick & Analisis 2-Arah"
                                    >
                                        Chart &amp; Analisis
                                    </button>

                                    <form action="{{ route('futures-signals.scan') }}" method="POST" class="inline">
                                        @csrf
                                        <input type="hidden" name="target_profile" value="{{ $targetProfile }}">
                                        <input type="hidden" name="timeframe" value="{{ $timeframe ?? '1h' }}">
                                        <input type="hidden" name="direction" :value="direction">
                                        <button
                                            type="submit"
                                            class="px-2 py-1 bg-accent/10 hover:bg-accent text-accent hover:text-white border border-accent/20 rounded text-[11px] font-bold transition cursor-pointer"
                                            title="Scan Sinyal"
                                        >
                                            Scan
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Dynamic Pagination Bar & Controls -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-3 border-t border-border-subtle text-xs font-mono">
            <div class="text-text-muted">
                Menampilkan koin <span class="font-bold text-text-primary" x-text="((currentPage - 1) * perPage) + 1"></span> – <span class="font-bold text-text-primary" x-text="Math.min(currentPage * perPage, filteredCoins.length)"></span> dari total <span class="font-bold text-accent" x-text="filteredCoins.length"></span> koin futures
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1 text-text-muted">
                    <span>Tampilkan:</span>
                    <select
                        x-model.number="perPage"
                        @change="currentPage = 1"
                        class="bg-surface-elevated text-text-primary border border-border-subtle rounded px-2 py-1 focus:outline-none cursor-pointer"
                    >
                        <option value="15">15</option>
                        <option value="20">20</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="prevPage()"
                        :disabled="currentPage === 1"
                        class="px-3 py-1 bg-surface-elevated text-text-primary border border-border-subtle rounded transition disabled:opacity-40 disabled:cursor-not-allowed hover:bg-surface-hover cursor-pointer"
                    >
                        Sebelumnya
                    </button>

                    <span class="px-3 py-1 bg-surface text-accent border border-border-subtle rounded font-bold" x-text="currentPage + ' / ' + totalPages"></span>

                    <button
                        type="button"
                        @click="nextPage()"
                        :disabled="currentPage >= totalPages"
                        class="px-3 py-1 bg-surface-elevated text-text-primary border border-border-subtle rounded transition disabled:opacity-40 disabled:cursor-not-allowed hover:bg-surface-hover cursor-pointer"
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CHART & DETAIL MODAL -->
    <div
        x-show="modalOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 bg-black/75 backdrop-blur-sm overflow-y-auto"
        style="display: none;"
    >
        <div
            @click.outside="closeModal()"
            class="bg-surface border border-border-subtle rounded-2xl w-full max-w-6xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]"
        >
            <div class="px-6 py-4 border-b border-border-subtle flex items-center justify-between bg-surface-elevated/40">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center font-mono text-sm font-bold text-accent">
                        <x-app-icon name="zap" class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold font-mono text-text-primary" x-text="activeSymbol + ' • Analisis 2-Arah Futures'"></h3>
                            <template x-if="activeData">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase" :class="activeData.direction === 'short' ? 'bg-rose-500/15 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'" x-text="activeData.direction"></span>
                            </template>
                            <template x-if="activeData">
                                <span
                                    class="px-2 py-0.5 rounded text-xs font-mono font-bold"
                                    :class="activeData.is_triggered ? 'bg-trade-profit-bg text-trade-profit border border-trade-profit/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'"
                                    x-text="activeData.is_triggered ? 'TRIGGERED (ENTRY SEKARANG)' : 'WATCHLIST (TUNGGU KONFIRMASI)'"
                                ></span>
                            </template>
                        </div>
                        <p class="text-xs text-text-secondary font-mono">Gate.io Real-Time Candlestick Feed</p>
                    </div>
                </div>
                <button @click="closeModal()" class="p-2 rounded-lg text-text-muted hover:text-text-primary hover:bg-surface-hover transition cursor-pointer">
                    <x-app-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div x-show="loadingChart" class="py-24 text-center space-y-3">
                    <div class="w-8 h-8 border-2 border-accent border-t-transparent rounded-full animate-spin mx-auto"></div>
                    <p class="text-xs font-mono text-text-secondary">Memuat data candlestick &amp; menghitung zona 2-arah...</p>
                </div>

                <div x-show="!loadingChart && activeData" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                    <div class="lg:col-span-7 space-y-4">
                        <div class="bg-surface-elevated/40 border border-border-subtle rounded-xl p-4">
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-border-subtle text-xs font-mono">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-text-primary" x-text="activeSymbol"></span>
                                    <div class="flex items-center bg-surface border border-border-subtle rounded-md p-0.5 ml-2">
                                        <template x-for="tf in ['15m', '1h', '4h', '1d']" :key="tf">
                                            <button
                                                type="button"
                                                @click="switchModalTimeframe(tf)"
                                                class="px-2 py-0.5 rounded text-[10px] font-bold uppercase transition cursor-pointer"
                                                :class="modalTimeframe === tf ? 'bg-accent text-white shadow-xs' : 'text-text-muted hover:text-text-primary'"
                                                x-text="tf"
                                            ></button>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 text-[11px]">
                                    <span class="flex items-center gap-1 text-trade-profit"><span class="w-2 h-2 rounded-full bg-trade-profit"></span> TP1/TP2</span>
                                    <template x-if="activeData && activeData.is_triggered">
                                        <span class="flex items-center gap-1 text-accent"><span class="w-2 h-2 rounded-full bg-accent"></span> Entry</span>
                                    </template>
                                    <template x-if="activeData && !activeData.is_triggered">
                                        <span class="flex items-center gap-1 text-amber-400"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Area Tunggu</span>
                                    </template>
                                    <span class="flex items-center gap-1 text-trade-loss"><span class="w-2 h-2 rounded-full bg-trade-loss"></span> SL</span>
                                </div>
                            </div>
                            <div id="futuresCandleChart" class="w-full"></div>
                        </div>

                        <template x-if="activeData">
                            <div class="grid grid-cols-4 gap-2 text-xs font-mono">
                                <div class="p-2.5 rounded-lg bg-surface border border-border-subtle">
                                    <span class="text-[10px] text-text-muted uppercase block" x-text="activeData.is_triggered ? 'Entry' : 'Area Tunggu Ideal'"></span>
                                    <span class="font-bold text-text-primary" x-text="activeData.is_triggered ? activeData.formatted_entry_price : activeData.formatted_wait_price_ideal"></span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-trade-profit-bg border border-trade-profit/20">
                                    <span class="text-[10px] text-trade-profit uppercase block" x-text="'TP1 (' + (activeData.direction === 'short' ? '-' : '+') + activeData.target_min_pct + '%)'"></span>
                                    <span class="font-bold text-trade-profit" x-text="activeData.formatted_tp1"></span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-trade-profit-bg border border-trade-profit/30">
                                    <span class="text-[10px] text-trade-profit uppercase block" x-text="'TP2 (' + (activeData.direction === 'short' ? '-' : '+') + activeData.target_max_pct + '%)'"></span>
                                    <span class="font-bold text-trade-profit" x-text="activeData.formatted_tp2"></span>
                                </div>
                                <div class="p-2.5 rounded-lg bg-trade-loss-bg border border-trade-loss/20">
                                    <span class="text-[10px] text-trade-loss uppercase block">Invalidation (SL)</span>
                                    <span class="font-bold text-trade-loss" x-text="activeData.formatted_sl"></span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="lg:col-span-5 space-y-4">
                        <template x-if="activeData">
                            <div class="space-y-4 font-mono">
                                <div class="border rounded-xl p-4 space-y-2" :class="activeData.is_triggered ? 'bg-trade-profit-bg/20 border-trade-profit/30' : 'bg-amber-500/10 border-amber-500/20'">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-text-secondary uppercase font-bold text-[11px]">Status Rekomendasi:</span>
                                        <span
                                            class="font-bold text-right"
                                            :class="activeData.is_triggered ? 'text-trade-profit' : 'text-amber-400'"
                                            x-text="activeData.is_triggered
                                                ? (activeData.direction === 'short' ? 'Buka SHORT Sekarang' : 'Buka LONG Sekarang')
                                                : (activeData.direction === 'short' ? 'Menunggu Zona Premium' : 'Menunggu Zona Diskon')"
                                        ></span>
                                    </div>
                                    <p class="text-[10px] text-text-muted leading-relaxed" x-show="!activeData.is_triggered">
                                        Harga saat ini: <span class="font-bold text-text-primary" x-text="activeData.formatted_current_price"></span> —
                                        lihat rincian di panel "Konfluensi Teknikal" di bawah untuk penjelasan lengkap.
                                    </p>
                                </div>

                                <div class="bg-surface border border-border-subtle rounded-xl p-4 space-y-2.5">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                                        <x-app-icon name="check-circle-2" class="w-4 h-4 text-trade-profit" />
                                        <span>Konfluensi Teknikal:</span>
                                    </h4>
                                    <div class="space-y-2 text-xs text-text-secondary">
                                        <template x-for="(conf, idx) in activeData.confluences_list" :key="idx">
                                            <div class="flex items-start gap-2 p-2 rounded-lg bg-surface-elevated/50 border border-border-subtle/50">
                                                <span class="text-trade-profit font-bold">✓</span>
                                                <span class="leading-relaxed" x-text="conf"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div class="bg-surface border border-border-subtle rounded-xl p-4 space-y-2 text-xs">
                                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-text-primary">Rincian Dealing Range:</h4>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-text-secondary pt-1">
                                        <div>Swing High: <span class="font-bold text-text-primary" x-text="formatCryptoPrice(activeData.metrics_data.swing_high)"></span></div>
                                        <div>Swing Low: <span class="font-bold text-text-primary" x-text="formatCryptoPrice(activeData.metrics_data.swing_low)"></span></div>
                                        <div>Equilibrium (50%): <span class="font-bold text-accent" x-text="formatCryptoPrice(activeData.metrics_data.equilibrium)"></span></div>
                                        <div x-show="activeData.direction === 'short'">Premium OTE (61.8%): <span class="font-bold text-rose-400" x-text="formatCryptoPrice(activeData.metrics_data.ote_premium)"></span></div>
                                        <div x-show="activeData.direction !== 'short'">Diskon OTE (61.8%): <span class="font-bold text-trade-profit" x-text="formatCryptoPrice(activeData.metrics_data.ote_discount)"></span></div>
                                        <div x-show="activeData.metrics_data.money_flow_pct_24h !== null">Money Flow 24H: <span class="font-bold" :class="activeData.metrics_data.money_flow_pct_24h >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(activeData.metrics_data.money_flow_pct_24h >= 0 ? '+' : '') + activeData.metrics_data.money_flow_pct_24h + '%'"></span></div>
                                        <div>Liquidity Sweep: <span class="font-bold" :class="activeData.metrics_data.sweep_detected ? 'text-trade-profit' : 'text-text-muted'" x-text="activeData.metrics_data.sweep_detected ? 'Terdeteksi' : 'Belum'"></span></div>
                                        <div>Inversi FVG: <span class="font-bold" :class="activeData.metrics_data.ifvg_detected ? 'text-trade-profit' : 'text-text-muted'" x-text="activeData.metrics_data.ifvg_detected ? 'Terdeteksi' : 'Belum'"></span></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SIMULATION MODAL (Futures-Only: side locked to the analyzed signal direction) -->
    <div
        x-show="simModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        @keydown.escape.window="closeSimModal()"
    >
        <div
            x-show="simModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="closeSimModal()"
            class="bg-surface border border-border-subtle rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden font-mono"
        >
            <div class="px-6 py-4 bg-surface-elevated/80 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-accent/20 border border-accent/30 flex items-center justify-center text-accent">
                        <x-app-icon name="play-circle" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-text-primary flex items-center gap-2">
                            <span>Simulasi Futures</span>
                            <template x-if="simSignal">
                                <span class="px-2 py-0.5 rounded text-[11px] bg-accent/10 text-accent border border-accent/20" x-text="simSignal.symbol"></span>
                            </template>
                            <template x-if="simSignal">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase" :class="simSignal.direction === 'short' ? 'bg-rose-500/15 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30'" x-text="simSignal.direction"></span>
                            </template>
                        </h3>
                        <p class="text-[11px] text-text-secondary">Arah posisi mengikuti sinyal yang dianalisis — tidak dapat diubah manual</p>
                    </div>
                </div>
                <button @click="closeSimModal()" class="text-text-muted hover:text-text-primary p-1.5 rounded-lg hover:bg-surface-hover transition cursor-pointer">
                    <x-app-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <template x-if="simSignal">
                <form action="{{ route('simulation.paper.buy') }}" method="POST" class="p-6 space-y-4">
                    @csrf
                    <input type="hidden" name="symbol" :value="simSignal.symbol">
                    <input type="hidden" name="timeframe" :value="simSignal.timeframe || '1h'">
                    <input type="hidden" name="entry_price" :value="simSignal.entry_price">
                    <input type="hidden" name="take_profit_1" :value="simSignal.take_profit_1">
                    <input type="hidden" name="take_profit_2" :value="simSignal.take_profit_2">
                    <input type="hidden" name="stop_loss" :value="simSignal.stop_loss">
                    <input type="hidden" name="allocated_amount" :value="simEquity">
                    <input type="hidden" name="trading_type" value="futures">
                    <input type="hidden" name="side" :value="simSignal.direction">
                    <input type="hidden" name="leverage" :value="simLeverage">
                    <input type="hidden" name="risk_pct" :value="simSizingMode === 'risk' ? simRiskPct : ''">

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-text-secondary">Pilih Leverage:</span>
                            <span class="font-bold text-accent" x-text="simLeverage + 'x'"></span>
                        </div>
                        <div class="grid grid-cols-6 gap-1">
                            <template x-for="lev in [2, 5, 10, 20, 50, 100]" :key="lev">
                                <button
                                    type="button"
                                    @click="setSimLeverage(lev)"
                                    class="py-1 rounded text-xs font-bold transition border cursor-pointer text-center"
                                    :class="simLeverage === lev ? 'bg-accent text-white border-accent shadow-xs' : 'bg-surface text-text-secondary border-border-subtle hover:text-text-primary'"
                                    x-text="lev + 'x'"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-2 bg-surface-elevated/40 border border-border-subtle/80 rounded-xl p-3 text-xs">
                        <div>
                            <span class="text-[10px] text-text-muted uppercase block">Entry Price</span>
                            <span class="font-bold text-text-primary" x-text="formatCryptoPrice(simSignal.entry_price)"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-trade-profit uppercase block">TP1</span>
                            <span class="font-bold text-trade-profit" x-text="formatCryptoPrice(simSignal.take_profit_1)"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-trade-profit uppercase block">TP2</span>
                            <span class="font-bold text-trade-profit" x-text="formatCryptoPrice(simSignal.take_profit_2)"></span>
                        </div>
                        <div>
                            <span class="text-[10px] text-trade-loss uppercase block">Stop Loss</span>
                            <span class="font-bold text-trade-loss" x-text="formatCryptoPrice(simSignal.stop_loss)"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 p-1 bg-surface-elevated rounded-xl border border-border-subtle text-xs font-bold">
                        <button type="button" @click="simSizingMode = 'fixed'" class="py-1.5 rounded-lg transition cursor-pointer" :class="simSizingMode === 'fixed' ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">Modal Tetap ($)</button>
                        <button type="button" @click="simSizingMode = 'risk'" class="py-1.5 rounded-lg transition cursor-pointer" :class="simSizingMode === 'risk' ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'">Risk % Akun</button>
                    </div>

                    <div class="space-y-2.5" x-show="simSizingMode === 'fixed'">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-text-primary uppercase tracking-wider">Margin Modal USDT:</label>
                            <span class="text-xs font-bold text-accent" x-text="'$' + Number(simEquity).toLocaleString('en-US')"></span>
                        </div>
                        <div class="grid grid-cols-6 gap-1.5">
                            <template x-for="amt in [25, 50, 100, 250, 500, 1000]" :key="amt">
                                <button type="button" @click="simEquity = amt" class="py-1.5 px-1 rounded-lg text-xs font-bold transition border cursor-pointer text-center" :class="simEquity === amt ? 'bg-accent text-white border-accent shadow-xs' : 'bg-surface-elevated text-text-secondary border-border-subtle hover:text-text-primary'" x-text="'$' + amt"></button>
                            </template>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-text-muted font-bold">$</div>
                            <input type="number" x-model.number="simEquity" min="1" step="any" class="w-full bg-surface-elevated border border-border-subtle rounded-xl pl-8 pr-4 py-2 text-sm font-bold text-text-primary focus:outline-none focus:border-accent" placeholder="Nominal modal...">
                        </div>
                    </div>

                    <div class="space-y-2.5" x-show="simSizingMode === 'risk'">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-text-primary uppercase tracking-wider">Risiko per Trade (% Saldo Akun):</label>
                            <span class="text-xs font-bold text-accent" x-text="simRiskPct + '%'"></span>
                        </div>
                        <div class="grid grid-cols-4 gap-1.5">
                            <template x-for="r in [0.5, 1, 2, 3]" :key="r">
                                <button type="button" @click="simRiskPct = r" class="py-1.5 rounded-lg text-xs font-bold transition border cursor-pointer text-center" :class="simRiskPct === r ? 'bg-accent text-white border-accent shadow-xs' : 'bg-surface-elevated text-text-secondary border-border-subtle hover:text-text-primary'" x-text="r + '%'"></button>
                            </template>
                        </div>
                        <p class="text-[10px] text-text-muted">Margin dihitung otomatis dari jarak Entry ke Stop Loss, supaya kerugian maksimal jika SL kena selalu <span x-text="simRiskPct"></span>% dari saldo.</p>
                    </div>

                    <div class="bg-surface-elevated/70 border rounded-xl p-3 flex items-center justify-between text-xs" :class="simExceedsAvailableCash ? 'border-rose-500/40' : 'border-border-subtle'">
                        <span class="text-text-secondary flex items-center gap-1.5">
                            <x-app-icon name="wallet" class="w-3.5 h-3.5" />
                            <span>Saldo Kas Bebas:</span>
                        </span>
                        <template x-if="simCashLoaded">
                            <span class="font-bold text-accent" x-text="'$' + simAvailableCash.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </template>
                        <template x-if="!simCashLoaded">
                            <span class="text-text-muted">Memuat...</span>
                        </template>
                    </div>

                    <template x-if="simExceedsAvailableCash">
                        <div class="p-2.5 bg-rose-500/10 border border-rose-500/30 rounded-xl text-[11px] text-rose-400 flex items-center gap-2">
                            <x-app-icon name="shield-alert" class="w-4 h-4 flex-shrink-0" />
                            <span>Margin yang dibutuhkan (<span class="font-bold" x-text="'$' + simEstimatedMargin.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>) melebihi saldo kas bebas Anda.</span>
                        </div>
                    </template>

                    <div class="bg-surface-elevated/70 border border-border-subtle rounded-xl p-3.5 space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-text-secondary">
                            <span>Ukuran Total Posisi:</span>
                            <span class="font-bold text-text-primary" x-text="'$' + Number(simEquity * simLeverage).toLocaleString('en-US')"></span>
                        </div>
                        <div class="flex items-center justify-between text-amber-400">
                            <span>Estimasi Harga Likuidasi:</span>
                            <span class="font-bold" x-text="formatCryptoPrice(estimatedLiquidationPrice)"></span>
                        </div>
                        <div class="flex items-center justify-between text-trade-profit">
                            <span>Potensi Profit TP2:</span>
                            <span class="font-bold" x-text="'+$' + (simEquity * (targetMax / 100) * simLeverage).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (+' + (targetMax * simLeverage) + '%)'"></span>
                        </div>
                        <div class="flex items-center justify-between text-trade-loss">
                            <span>Maksimum Risiko SL:</span>
                            <span class="font-bold" x-text="'-$' + (simEquity * ((Math.abs(Number(simSignal.entry_price) - Number(simSignal.stop_loss))) / Math.max(Number(simSignal.entry_price), 0.00000001)) * simLeverage).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="button" @click="closeSimModal()" class="w-1/3 py-2.5 bg-surface-elevated hover:bg-surface-hover text-text-secondary rounded-xl text-xs font-bold uppercase transition border border-border-subtle cursor-pointer">Batal</button>
                        <button
                            type="submit"
                            :disabled="simExceedsAvailableCash"
                            class="w-2/3 py-2.5 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md flex items-center justify-center gap-2 cursor-pointer"
                            :class="simSignal.direction === 'short' ? 'bg-rose-500 hover:bg-rose-600' : 'bg-emerald-500 hover:bg-emerald-600'"
                        >
                            <x-app-icon name="check-circle" class="w-4 h-4" />
                            <span x-text="'Buka ' + (simSignal.direction === 'short' ? 'SHORT' : 'LONG') + ' ' + simLeverage + 'x'"></span>
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>

<script>
function formatCryptoPrice(price) {
    if (price === null || price === undefined || isNaN(price) || Number(price) <= 0) return '$0.00';
    const num = Number(price);
    if (num >= 50.0) {
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else if (num >= 1.0) {
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    } else if (num >= 0.01) {
        return '$' + num.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    } else if (num >= 0.0001) {
        return '$' + num.toFixed(6);
    } else {
        return '$' + num.toFixed(8);
    }
}

function smartScreenFutureComponent() {
    return {
        modalOpen: false,
        loadingChart: false,
        activeSymbol: 'BTCUSDT',
        activeData: null,
        chartInstance: null,
        viewMode: 'card',
        searchQuery: '',
        targetProfile: @json($targetProfile ?? 'standard'),
        targetMin: {{ (int) ($targetMin ?? 10) }},
        targetMax: {{ (int) ($targetMax ?? 20) }},
        timeframe: @json($timeframe ?? '1h'),
        // Direction filter locked via localStorage across menu navigation,
        // same convention as Coin Hunter's strategy-mode lock (see
        // brain/engine_v1/STRATEGY_MODE_SELECTOR.md).
        direction: localStorage.getItem('futures_signals_direction') || @json($direction ?? 'all'),
        modalTimeframe: @json($timeframe ?? '1h'),
        lastUpdated: 'Real-Time',
        isLive: @json($isMarketLive ?? true),
        coinsData: @json($topCoins),
        signalsData: @json($signals),

        simModalOpen: false,
        simSignal: null,
        simEquity: 50,
        simLeverage: Number(localStorage.getItem('futures_sim_leverage') || 10),
        simSizingMode: 'fixed',
        simRiskPct: 2,
        simAvailableCash: 0,
        simVirtualBalance: 0,
        simCashLoaded: false,

        setSimLeverage(lev) {
            this.simLeverage = lev;
            localStorage.setItem('futures_sim_leverage', lev);
        },

        openSimModal(signal) {
            this.simSignal = signal;
            this.simEquity = 50;
            this.simLeverage = Number(localStorage.getItem('futures_sim_leverage') || 10);
            this.simModalOpen = true;
            this.fetchSimAvailableCash();
        },

        closeSimModal() {
            this.simModalOpen = false;
            this.simSignal = null;
        },

        fetchSimAvailableCash() {
            this.simCashLoaded = false;
            fetch('/simulation/live-status')
                .then(res => res.json())
                .then(data => {
                    this.simAvailableCash = Number(data.available_cash || 0);
                    this.simVirtualBalance = Number(data.virtual_balance || 0);
                    this.simCashLoaded = true;
                })
                .catch(err => {
                    console.debug('Fetch available cash:', err);
                    this.simCashLoaded = true;
                });
        },

        get simEstimatedMargin() {
            if (this.simSizingMode === 'risk' && this.simSignal) {
                const riskAmount = this.simVirtualBalance * (this.simRiskPct / 100);
                const riskPerUnit = Math.abs(Number(this.simSignal.entry_price) - Number(this.simSignal.stop_loss));
                if (riskPerUnit > 0 && Number(this.simSignal.entry_price) > 0) {
                    const positionValue = (riskAmount / riskPerUnit) * Number(this.simSignal.entry_price);
                    return positionValue / this.simLeverage;
                }
                return 0;
            }
            return Number(this.simEquity) || 0;
        },

        get simExceedsAvailableCash() {
            return this.simCashLoaded && this.simEstimatedMargin > this.simAvailableCash;
        },

        // Mirrors PaperTradingService::estimateLiquidationPrice() exactly
        // (0.5% maintenance margin rate) — same formula, client-side
        // preview only, so what the user sees before submitting matches
        // what the server actually persists.
        get estimatedLiquidationPrice() {
            if (!this.simSignal || this.simLeverage <= 1) return 0;
            const entry = Number(this.simSignal.entry_price);
            const mmr = 0.005;
            return this.simSignal.direction === 'short'
                ? entry * (1 + (1 / this.simLeverage) - mmr)
                : entry * Math.max(0, (1 - (1 / this.simLeverage) + mmr));
        },

        get filteredSignals() {
            if (!this.searchQuery || this.searchQuery.trim() === '') {
                return this.signalsData;
            }
            const q = this.searchQuery.toLowerCase().trim();
            return this.signalsData.filter(s => s.symbol && s.symbol.toLowerCase().includes(q));
        },

        // Coin list table: pagination + shared search bar (same convention
        // as Coin Hunter's ticker table).
        currentPage: 1,
        perPage: 20,

        get filteredCoins() {
            if (!this.searchQuery || this.searchQuery.trim() === '') {
                return this.coinsData;
            }
            const q = this.searchQuery.toLowerCase().trim();
            return this.coinsData.filter(c =>
                (c.name && c.name.toLowerCase().includes(q)) ||
                (c.symbol && c.symbol.toLowerCase().includes(q)) ||
                (c.base_symbol && c.base_symbol.toLowerCase().includes(q))
            );
        },

        get totalPages() {
            return Math.max(1, Math.ceil(this.filteredCoins.length / this.perPage));
        },

        get paginatedCoins() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredCoins.slice(start, start + this.perPage);
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },

        init() {
            this.coinsData = this.coinsData.map(c => ({
                ...c,
                priceFlash: null
            }));

            this.signalsData = this.signalsData.map(s => ({
                ...s,
                formatted_current_price: formatCryptoPrice(s.current_price),
                priceFlash: null,
                isNew: false
            }));

            if (window.Echo) {
                window.Echo.channel('market-ticker').listen('.ticker.updated', (coin) => {
                    this.applyLiveTick(coin);
                    this.isLive = true;
                    this.lastUpdated = new Date().toLocaleTimeString();
                });
            }

            // REST poll fallback/backfill for the ticker table — reuses
            // Coin Hunter's existing /signals/live-prices endpoint since the
            // underlying top-250 ticker data is identical either way (no
            // need for a second, duplicate futures-specific price feed).
            setInterval(() => {
                this.fetchLivePrices();
            }, 4000);

            setInterval(() => {
                this.fetchLiveSignals();
            }, 5000);
        },

        applyLiveTick(newCoin) {
            const matchIndex = this.coinsData.findIndex(c => c.symbol === newCoin.symbol);
            if (matchIndex !== -1) {
                const oldPrice = this.coinsData[matchIndex].price;
                const newPrice = newCoin.price;

                let coinFlash = null;
                if (newPrice > oldPrice) coinFlash = 'up';
                else if (newPrice < oldPrice) coinFlash = 'down';

                this.coinsData[matchIndex] = {
                    ...this.coinsData[matchIndex],
                    ...newCoin,
                    priceFlash: coinFlash
                };

                if (coinFlash) {
                    setTimeout(() => {
                        if (this.coinsData[matchIndex]) {
                            this.coinsData[matchIndex].priceFlash = null;
                        }
                    }, 800);
                }
            }

            const sigIndex = this.signalsData.findIndex(s => s.symbol === newCoin.symbol);
            if (sigIndex !== -1) {
                const oldPrice = this.signalsData[sigIndex].current_price;
                const newPrice = newCoin.price;
                let flash = null;
                if (newPrice > oldPrice) flash = 'up';
                else if (newPrice < oldPrice) flash = 'down';

                this.signalsData[sigIndex] = {
                    ...this.signalsData[sigIndex],
                    current_price: newPrice,
                    formatted_current_price: formatCryptoPrice(newPrice),
                    priceFlash: flash
                };

                if (flash) {
                    setTimeout(() => {
                        if (this.signalsData[sigIndex]) {
                            this.signalsData[sigIndex].priceFlash = null;
                        }
                    }, 800);
                }
            }
        },

        fetchLivePrices() {
            fetch('/signals/live-prices')
                .then(res => res.json())
                .then(data => {
                    if (data && data.coins) {
                        const now = new Date();
                        this.lastUpdated = now.toLocaleTimeString();
                        this.isLive = data.is_live !== false;
                        data.coins.forEach(newCoin => this.applyLiveTick(newCoin));
                    }
                })
                .catch(err => console.debug('Live ticker tick:', err));
        },

        fetchLiveSignals() {
            const params = new URLSearchParams({
                target_profile: this.targetProfile,
                timeframe: this.timeframe,
                custom_min: this.targetMin,
                custom_max: this.targetMax,
                direction: this.direction,
            });

            fetch(`/futures-signals/live-signals?${params.toString()}`)
                .then(res => res.json())
                .then(data => {
                    if (!data || !Array.isArray(data.signals)) return;

                    const incoming = new Map(data.signals.map(s => [s.symbol + '_' + s.direction, s]));

                    this.signalsData = this.signalsData
                        .filter(s => incoming.has(s.symbol + '_' + s.direction))
                        .map(s => {
                            const fresh = incoming.get(s.symbol + '_' + s.direction);
                            incoming.delete(s.symbol + '_' + s.direction);
                            return {
                                ...s,
                                ...fresh,
                                formatted_current_price: formatCryptoPrice(fresh.current_price),
                                isNew: false,
                            };
                        });

                    incoming.forEach(fresh => {
                        this.signalsData.push({
                            ...fresh,
                            formatted_current_price: formatCryptoPrice(fresh.current_price),
                            priceFlash: null,
                            isNew: true,
                        });
                    });

                    const justAdded = this.signalsData.filter(s => s.isNew);
                    if (justAdded.length) {
                        setTimeout(() => {
                            justAdded.forEach(s => { s.isNew = false; });
                        }, 6000);
                    }
                })
                .catch(err => console.debug('Live futures signals tick:', err));
        },

        setDirection(dir) {
            if (dir === this.direction) return;
            this.direction = dir;
            localStorage.setItem('futures_signals_direction', dir);
            this.signalsData = [];
            this.fetchLiveSignals();
        },

        changeTargetProfile(profile) {
            window.location.href = `/futures-signals?target_profile=${profile}&timeframe=${this.timeframe}&direction=${this.direction}`;
        },

        navigateTimeframe(tf) {
            window.location.href = `/futures-signals?target_profile=${this.targetProfile}&timeframe=${tf}&direction=${this.direction}`;
        },

        openDetail(symbol, dir) {
            this.activeSymbol = symbol;
            this.modalTimeframe = this.timeframe;
            this.modalOpen = true;
            this.loadingChart = true;
            this.activeData = null;
            this.loadChartData(symbol, this.modalTimeframe, dir);
        },

        switchModalTimeframe(tf) {
            this.modalTimeframe = tf;
            this.loadingChart = true;
            this.loadChartData(this.activeSymbol, tf, this.activeData ? this.activeData.direction : 'auto');
        },

        loadChartData(symbol, tf, dir) {
            fetch(`/futures-signals/chart-data/${symbol}?target_min=${this.targetMin}&target_max=${this.targetMax}&timeframe=${tf}&direction=${dir || 'auto'}`)
                .then(res => res.json())
                .then(data => {
                    this.activeData = data;
                    this.loadingChart = false;
                    this.$nextTick(() => {
                        this.renderChart(data);
                    });
                })
                .catch(err => {
                    console.error('Error fetching futures chart data:', err);
                    this.loadingChart = false;
                });
        },

        closeModal() {
            this.modalOpen = false;
            if (this.chartInstance) {
                this.chartInstance.destroy();
                this.chartInstance = null;
            }
        },

        renderChart(data) {
            const chartEl = document.querySelector('#futuresCandleChart');
            if (!chartEl) return;

            if (this.chartInstance) {
                this.chartInstance.destroy();
            }

            const isDark = document.documentElement.classList.contains('dark');
            const isTriggered = (data.status === 'triggered');
            const entryPrice = data.entry_price;
            const tp1 = data.take_profit_1;
            const tp2 = data.take_profit_2;
            const sl = data.stop_loss;
            const waitPrice = data.wait_price_ideal;

            const yaxisAnnotations = [];

            if (isTriggered) {
                yaxisAnnotations.push({
                    y: entryPrice,
                    borderColor: '#3B82F6',
                    borderWidth: 1.5,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#3B82F6',
                        style: { color: '#fff', background: '#2563EB', fontFamily: 'JetBrains Mono', fontSize: '10px' },
                        text: 'Entry (' + formatCryptoPrice(entryPrice) + ')'
                    }
                });
            } else {
                yaxisAnnotations.push({
                    y: waitPrice,
                    borderColor: '#F59E0B',
                    borderWidth: 1.5,
                    strokeDashArray: 4,
                    label: {
                        borderColor: '#F59E0B',
                        style: { color: '#fff', background: '#D97706', fontFamily: 'JetBrains Mono', fontSize: '10px' },
                        text: 'Area Tunggu (' + formatCryptoPrice(waitPrice) + ')'
                    }
                });
            }

            yaxisAnnotations.push({
                y: tp1,
                borderColor: '#10B981',
                borderWidth: 1.5,
                label: {
                    borderColor: '#10B981',
                    style: { color: '#fff', background: '#059669', fontFamily: 'JetBrains Mono', fontSize: '10px' },
                    text: 'TP1 (' + formatCryptoPrice(tp1) + ')'
                }
            });

            yaxisAnnotations.push({
                y: tp2,
                borderColor: '#059669',
                borderWidth: 1.5,
                label: {
                    borderColor: '#059669',
                    style: { color: '#fff', background: '#047857', fontFamily: 'JetBrains Mono', fontSize: '10px' },
                    text: 'TP2 (' + formatCryptoPrice(tp2) + ')'
                }
            });

            yaxisAnnotations.push({
                y: sl,
                borderColor: '#F43F5E',
                borderWidth: 1.5,
                strokeDashArray: 4,
                label: {
                    borderColor: '#F43F5E',
                    style: { color: '#fff', background: '#E11D48', fontFamily: 'JetBrains Mono', fontSize: '10px' },
                    text: 'SL (' + formatCryptoPrice(sl) + ')'
                }
            });

            const options = {
                series: [{ name: 'Candles', data: data.candlestick }],
                chart: { type: 'candlestick', height: 420, toolbar: { show: false }, background: 'transparent', animations: { enabled: false } },
                plotOptions: { candlestick: { colors: { upward: '#10B981', downward: '#F43F5E' }, wick: { useFillColor: true } } },
                xaxis: {
                    type: 'datetime',
                    labels: { style: { colors: isDark ? '#64748B' : '#94A3B8', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    tooltip: { enabled: true },
                    labels: { formatter: (val) => formatCryptoPrice(val), style: { colors: isDark ? '#64748B' : '#94A3B8', fontFamily: 'JetBrains Mono, monospace', fontSize: '10px' } }
                },
                grid: { borderColor: isDark ? '#1A222D' : '#E2E8F0', strokeDashArray: 3 },
                annotations: { yaxis: yaxisAnnotations }
            };

            this.chartInstance = new ApexCharts(chartEl, options);
            this.chartInstance.render();
        }
    };
}
</script>
@endsection
