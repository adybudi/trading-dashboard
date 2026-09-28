@extends('layouts.app')

@section('content')
<div x-data="simulationDashboardComponent()" class="space-y-6">

    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider bg-accent/10 text-accent border border-accent/20">
                    SIMULASI TRADE &amp; BACKTEST ENGINE
                </span>
                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase bg-trade-profit-bg text-trade-profit border border-trade-profit/20 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-trade-profit animate-pulse"></span>
                    <span>Spot &amp; Futures Paper Trading</span>
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-text-primary font-mono mt-1">
                SIMULASI TRADE • SPOT &amp; FUTURES VIRTUAL DESK
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">
                Uji strategi Spot Diskon (Default) dan Futures Leverage secara live dengan kalkulasi saldo dompet, konversi Rupiah/USD, &amp; floating PnL real-time.
            </p>
        </div>

        <!-- Mode Tab Switcher: Backtest vs Live Paper Trading -->
        <div class="flex items-center bg-surface border border-border-subtle rounded-xl p-1 font-mono text-xs shadow-xs">
            <button
                type="button"
                @click="activeTab = 'backtest'"
                class="px-4 py-2 rounded-lg font-bold transition flex items-center gap-2 cursor-pointer"
                :class="activeTab === 'backtest' ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'"
            >
                <x-app-icon name="clock" class="w-4 h-4" />
                <span>Historical Backtester</span>
            </button>
            <button
                type="button"
                @click="activeTab = 'paper'"
                class="px-4 py-2 rounded-lg font-bold transition flex items-center gap-2 cursor-pointer"
                :class="activeTab === 'paper' ? 'bg-accent text-white shadow-xs' : 'text-text-secondary hover:text-text-primary'"
            >
                <x-app-icon name="play-circle" class="w-4 h-4" />
                <span>Paper Trading Virtual</span>
            </button>
        </div>
    </div>

    <!-- Currency & Trading Mode Control Bar (Persistent via LocalStorage) -->
    <div class="flex flex-wrap items-center justify-between gap-3 bg-surface border border-border-subtle rounded-xl p-3 font-mono text-xs shadow-xs">

        <!-- Left: Filter Tipe Trading (Semua vs Spot vs Futures) -->
        <div class="flex items-center gap-2">
            <span class="text-text-muted text-[11px] uppercase font-bold">Filter Mode:</span>
            <div class="flex items-center bg-surface-elevated border border-border-subtle rounded-lg p-0.5">
                <button
                    type="button"
                    @click="setTradingFilter('all')"
                    class="px-3 py-1 rounded-md text-xs font-bold transition cursor-pointer"
                    :class="tradeTypeFilter === 'all' ? 'bg-surface text-text-primary shadow-xs' : 'text-text-muted hover:text-text-primary'"
                >
                    Semua
                </button>
                <button
                    type="button"
                    @click="setTradingFilter('spot')"
                    class="px-3 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    :class="tradeTypeFilter === 'spot' ? 'bg-accent text-white shadow-xs' : 'text-text-muted hover:text-text-primary'"
                >
                    <x-app-icon name="gem" class="w-3.5 h-3.5" />
                    <span>Spot Saja</span>
                </button>
                <button
                    type="button"
                    @click="setTradingFilter('futures')"
                    class="px-3 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    :class="tradeTypeFilter === 'futures' ? 'bg-purple-600 text-white shadow-xs' : 'text-text-muted hover:text-text-primary'"
                >
                    <x-app-icon name="zap" class="w-3.5 h-3.5" />
                    <span>Futures Saja</span>
                </button>
            </div>
        </div>

        <!-- Right: Currency Switcher (USD $ vs Rupiah Rp) -->
        <div class="flex items-center gap-2">
            <span class="text-text-muted text-[11px] uppercase font-bold">Mata Uang:</span>
            <div class="flex items-center bg-surface-elevated border border-border-subtle rounded-lg p-0.5">
                <button
                    type="button"
                    @click="setCurrency('USD')"
                    class="px-3 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    :class="currency === 'USD' ? 'bg-surface text-accent shadow-xs' : 'text-text-muted hover:text-text-primary'"
                >
                    <x-app-icon name="dollar-sign" class="w-3.5 h-3.5" />
                    <span>USD ($)</span>
                </button>
                <button
                    type="button"
                    @click="setCurrency('IDR')"
                    class="px-3 py-1 rounded-md text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
                    :class="currency === 'IDR' ? 'bg-surface text-emerald-400 shadow-xs' : 'text-text-muted hover:text-text-primary'"
                >
                    <x-app-icon name="banknote" class="w-3.5 h-3.5" />
                    <span>Rupiah (Rp)</span>
                </button>
            </div>
            <span class="text-[10px] text-text-muted font-normal" x-show="currency === 'IDR'">(1 USD = Rp 17.756,00)</span>
        </div>
    </div>

    <!-- LIVE SPOT WALLET & PNL DASHBOARD (5 METRIC TILES) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5 font-mono">

        <!-- Card 1: Total Virtual Equity (Net Worth) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4 space-y-1.5 relative overflow-hidden">
            <div class="flex items-center justify-between text-text-muted text-[10px] uppercase font-bold">
                <span>Total Virtual Equity</span>
                <span class="inline-flex items-center gap-1 text-[10px] text-emerald-400 font-bold">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>LIVE</span>
                </span>
            </div>
            <div class="text-2xl font-bold text-text-primary" x-text="formatMoney(accountData.total_virtual_equity || 0)"></div>
            <div class="text-[10px] text-text-secondary">
                <span>Modal Awal: <strong x-text="formatMoney(accountData.initial_balance || 100)"></strong></span>
            </div>
        </div>

        <!-- Card 2: Saldo Kas Bebas Dompet (Free Available Cash) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4 space-y-1.5">
            <div class="flex items-center justify-between text-text-muted text-[10px] uppercase font-bold">
                <span>Saldo Kas Bebas</span>
                <x-app-icon name="wallet" class="w-3.5 h-3.5 text-accent" />
            </div>
            <div class="text-2xl font-bold text-accent" x-text="formatMoney(accountData.available_cash || 0)"></div>
            <div class="text-[10px] text-text-muted">
                <span>Siap dibelanjakan</span>
            </div>
        </div>

        <!-- Card 3: Modal Terpakai di Posisi (Locked in Positions) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4 space-y-1.5">
            <div class="flex items-center justify-between text-text-muted text-[10px] uppercase font-bold">
                <span>Modal di Posisi</span>
                <span class="text-[10px] text-text-muted" x-text="openTrades.length + ' Posisi'"></span>
            </div>
            <div class="text-2xl font-bold text-text-primary" x-text="formatMoney(accountData.locked_margin || 0)"></div>
            <div class="text-[10px] text-text-secondary">
                <span>Nilai Koin: <strong class="text-text-primary" x-text="formatMoney(accountData.total_positions_value || 0)"></strong></span>
            </div>
        </div>

        <!-- Card 4: Floating Unrealized PnL (Live Real-Time) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4 space-y-1.5">
            <div class="flex items-center justify-between text-text-muted text-[10px] uppercase font-bold">
                <span>Floating Unrealized PnL</span>
                <span class="w-1.5 h-1.5 rounded-full" :class="(accountData.floating_unrealized_pnl_usd || 0) >= 0 ? 'bg-emerald-400' : 'bg-rose-400'"></span>
            </div>
            <div
                class="text-2xl font-bold transition-all duration-300"
                :class="(accountData.floating_unrealized_pnl_usd || 0) >= 0 ? 'text-trade-profit' : 'text-trade-loss'"
                x-text="formatMoney(accountData.floating_unrealized_pnl_usd || 0, true)"
            ></div>
            <div class="text-[10px] font-bold" :class="(accountData.floating_unrealized_pnl_pct || 0) >= 0 ? 'text-trade-profit' : 'text-trade-loss'">
                <span x-text="'(' + ((accountData.floating_unrealized_pnl_pct || 0) >= 0 ? '+' : '') + Number(accountData.floating_unrealized_pnl_pct || 0).toFixed(2) + '%)'"></span>
                <span class="text-text-muted font-normal ml-1">Laba/rugi berjalan</span>
            </div>
        </div>

        <!-- Card 5: Rekor & Atur Saldo Awal -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4 flex flex-col justify-between space-y-2">
            <div class="flex items-center justify-between text-[10px] text-text-muted uppercase font-bold">
                <span>Win Rate: <strong class="text-accent" x-text="Number(accountData.win_rate || 0).toFixed(0) + '%'"></strong></span>
                <span x-text="accountData.winning_trades + 'W / ' + accountData.losing_trades + 'L'"></span>
            </div>
            <button
                type="button"
                @click="openResetModal()"
                class="w-full py-2 px-2 bg-surface-elevated hover:bg-accent hover:text-white text-text-primary border border-border-subtle rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
            >
                <x-app-icon name="sliders" class="w-3.5 h-3.5" />
                <span>Atur Saldo Awal</span>
            </button>
        </div>
    </div>

    <!-- TAB 1: HISTORICAL STRATEGY BACKTESTER -->
    <div x-show="activeTab === 'backtest'" class="space-y-6">

        <!-- Backtest Configuration Form Card -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                <h3 class="text-sm font-mono font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                    <x-app-icon name="sliders" class="w-4 h-4 text-accent" />
                    <span>Parameter Pengujian Strategi Historis</span>
                </h3>
                <span class="text-xs font-mono text-text-muted">Replay Algoritma Diskon iFVG + PADAWAE</span>
            </div>

            <form @submit.prevent="runBacktest()" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 font-mono text-xs">

                <!-- Coin Selector (250 Koin) -->
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Pilih Aset / Koin</label>
                    <select
                        x-model="backtestForm.symbol"
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer"
                    >
                        <template x-for="coin in topCoins" :key="coin.symbol">
                            <option :value="coin.symbol" x-text="coin.pair + ' (' + coin.name + ')'"></option>
                        </template>
                    </select>
                </div>

                <!-- Timeframe -->
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Timeframe Candle</label>
                    <select
                        x-model="backtestForm.timeframe"
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer"
                    >
                        <option value="15m">15 Menit (Scalping Cepat)</option>
                        <option value="1h">1 Jam (Standard Swing)</option>
                        <option value="4h">4 Jam (Major Swing)</option>
                        <option value="1d">1 Hari (Position Trade)</option>
                    </select>
                </div>

                <!-- Target Profile -->
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Profil Target Keuntungan</label>
                    <select
                        x-model="backtestForm.target_profile"
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer"
                    >
                        <option value="scalp">Scalp (+5% – +10%)</option>
                        <option value="standard">Standard (+10% – +20%)</option>
                        <option value="extended">Extended (+20% – +35%)</option>
                        <option value="moonshot">Big Runner (+35% – +50%+)</option>
                        <option value="custom">Custom (Atur Sendiri)</option>
                    </select>
                </div>

                <!-- Strategy Mode -->
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block" title="PADAWAE: trendline break/retest murni. IFVG: liquidity sweep + inversi FVG + diskon/premium. Money Flow confluence otomatis disertakan di kedua mode.">Strategi</label>
                    <select
                        x-model="backtestForm.strategy"
                        @change="localStorage.setItem('backtest_strategy_mode', backtestForm.strategy)"
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer"
                    >
                        <option value="ifvg">IFVG</option>
                        <option value="padawae">PADAWAE</option>
                    </select>
                </div>

                <!-- Custom Target Range (only shown when profile = custom) -->
                <div class="space-y-1.5" x-show="backtestForm.target_profile === 'custom'">
                    <label class="text-text-muted uppercase text-[11px] block">Target Custom (Min% – Maks%)</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            x-model.number="backtestForm.custom_min"
                            min="0.1"
                            step="1"
                            placeholder="Min %"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent"
                        />
                        <span class="text-text-muted">–</span>
                        <input
                            type="number"
                            x-model.number="backtestForm.custom_max"
                            min="0.1"
                            step="1"
                            placeholder="Maks %"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent"
                        />
                    </div>
                </div>

                <!-- Initial Virtual Capital -->
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Modal Awal Virtual ($ USD)</label>
                    <input
                        type="number"
                        x-model.number="backtestForm.initial_capital"
                        min="10"
                        step="50"
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent"
                    />
                </div>

                <!-- Fee & Slippage (Realistic Cost Modeling) -->
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Fee &amp; Slippage per Sisi (%)</label>
                    <div class="flex items-center gap-1.5">
                        <input
                            type="number"
                            x-model.number="backtestForm.fee_pct"
                            min="0"
                            step="0.01"
                            placeholder="Fee %"
                            title="Fee exchange per sisi (entry & exit)"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent"
                        />
                        <span class="text-text-muted">+</span>
                        <input
                            type="number"
                            x-model.number="backtestForm.slippage_pct"
                            min="0"
                            step="0.01"
                            placeholder="Slip %"
                            title="Estimasi slippage per sisi (entry & exit)"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent"
                        />
                    </div>
                </div>

                <!-- Action Button -->
                <div class="space-y-1.5 flex flex-col justify-end">
                    <button
                        type="submit"
                        :disabled="runningBacktest"
                        class="w-full py-2 px-4 bg-accent hover:bg-accent-hover disabled:opacity-50 text-white rounded-lg font-bold transition flex items-center justify-center gap-2 cursor-pointer shadow-sm"
                    >
                        <template x-if="!runningBacktest">
                            <span class="flex items-center gap-2">
                                <x-app-icon name="play" class="w-3.5 h-3.5" />
                                <span>Jalankan Backtest</span>
                            </span>
                        </template>
                        <template x-if="runningBacktest">
                            <span class="flex items-center gap-2">
                                <div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                <span>Memproses...</span>
                            </span>
                        </template>
                    </button>
                </div>
            </form>
        </div>

        <!-- Backtest Results Display (KPIs & Chart) -->
        <template x-if="backtestResult">
            <div class="space-y-6 font-mono">

                <!-- Performance KPI Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-surface border border-border-subtle rounded-xl p-3.5 space-y-1">
                        <span class="text-[10px] text-text-muted uppercase block">Win Rate</span>
                        <div class="text-xl font-bold text-accent" x-text="backtestResult.kpis.win_rate + '%'"></div>
                        <span class="text-[10px] text-text-secondary" x-text="backtestResult.kpis.winning_trades + 'W / ' + backtestResult.kpis.losing_trades + 'L'"></span>
                    </div>

                    <div class="bg-surface border border-border-subtle rounded-xl p-3.5 space-y-1">
                        <span class="text-[10px] text-text-muted uppercase block">Net Profit</span>
                        <div class="text-xl font-bold" :class="backtestResult.kpis.net_profit >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="formatMoney(backtestResult.kpis.net_profit, true)"></div>
                        <span class="text-[10px] text-text-secondary" x-text="(backtestResult.kpis.net_roi_percent >= 0 ? '+' : '') + backtestResult.kpis.net_roi_percent + '% ROI'"></span>
                    </div>

                    <div class="bg-surface border border-border-subtle rounded-xl p-3.5 space-y-1">
                        <span class="text-[10px] text-text-muted uppercase block">Profit Factor</span>
                        <div class="text-xl font-bold text-text-primary" x-text="backtestResult.kpis.profit_factor"></div>
                        <span class="text-[10px] text-text-secondary" x-text="'Target RRR 1:' + backtestResult.kpis.avg_risk_reward"></span>
                    </div>

                    <div class="bg-surface border border-border-subtle rounded-xl p-3.5 space-y-1">
                        <span class="text-[10px] text-text-muted uppercase block">Max Drawdown</span>
                        <div class="text-xl font-bold text-trade-loss" x-text="'-' + backtestResult.kpis.max_drawdown_percent + '%'"></div>
                        <span class="text-[10px] text-text-secondary" x-text="formatMoney(backtestResult.kpis.max_drawdown_amount)"></span>
                    </div>

                    <div class="bg-surface border border-border-subtle rounded-xl p-3.5 space-y-1">
                        <span class="text-[10px] text-text-muted uppercase block">Total Sinyal</span>
                        <div class="text-xl font-bold text-text-primary" x-text="backtestResult.kpis.total_trades"></div>
                        <span class="text-[10px] text-text-secondary" x-text="'TF ' + backtestResult.timeframe.toUpperCase()"></span>
                    </div>

                    <div class="bg-surface border border-border-subtle rounded-xl p-3.5 space-y-1">
                        <span class="text-[10px] text-text-muted uppercase block">Saldo Akhir</span>
                        <div class="text-xl font-bold text-trade-profit" x-text="formatMoney(backtestResult.kpis.final_capital)"></div>
                        <span class="text-[10px] text-text-secondary" x-text="'Awal: ' + formatMoney(backtestResult.initial_capital)"></span>
                    </div>
                </div>

                <!-- Cumulative Equity Curve Chart -->
                <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                            <x-app-icon name="trending-up" class="w-4 h-4 text-trade-profit" />
                            <span>Kurva Pertumbuhan Ekuitas Virtual (Cumulative Equity Curve)</span>
                        </h3>
                        <span class="text-xs text-text-muted" x-text="backtestResult.symbol + ' • TF ' + backtestResult.timeframe.toUpperCase()"></span>
                    </div>
                    <div id="equityCurveChart" class="w-full"></div>
                </div>

                <!-- Trade Logs Table -->
                <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                            <x-app-icon name="list" class="w-4 h-4 text-accent" />
                            <span>Log Transaksi Hasil Replay Backtest</span>
                        </h3>
                        <span class="text-xs text-text-muted" x-text="backtestResult.trades.length + ' Transaksi Tereksekusi'"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase">
                                    <th class="pb-2 font-medium">#</th>
                                    <th class="pb-2 font-medium">Waktu Entry</th>
                                    <th class="pb-2 font-medium text-right">Harga Masuk</th>
                                    <th class="pb-2 font-medium">Waktu Selesai</th>
                                    <th class="pb-2 font-medium text-right">Harga Keluar</th>
                                    <th class="pb-2 font-medium text-center">Hasil</th>
                                    <th class="pb-2 font-medium text-right">Laba / Rugi</th>
                                    <th class="pb-2 font-medium text-right">Saldo Berjalan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                <template x-for="(t, idx) in backtestResult.trades" :key="idx">
                                    <tr class="hover:bg-surface-hover transition">
                                        <td class="py-3 text-text-muted" x-text="idx + 1"></td>
                                        <td class="py-3 text-text-secondary" x-text="t.entry_time"></td>
                                        <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(t.entry_price, false, true)"></td>
                                        <td class="py-3 text-text-secondary" x-text="t.exit_time"></td>
                                        <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(t.exit_price, false, true)"></td>
                                        <td class="py-3 text-center">
                                            <span
                                                class="px-2 py-0.5 rounded text-[10px] font-bold"
                                                :class="t.is_win ? 'bg-trade-profit-bg text-trade-profit border border-trade-profit/30' : 'bg-trade-loss-bg text-trade-loss border border-trade-loss/30'"
                                                x-text="t.outcome_label"
                                            ></span>
                                        </td>
                                        <td class="py-3 text-right font-bold" :class="t.pnl_amount >= 0 ? 'text-trade-profit' : 'text-trade-loss'">
                                            <span x-text="formatMoney(t.pnl_amount, true)"></span>
                                            <span class="text-[10px] block font-normal" x-text="'(' + (t.pnl_percent >= 0 ? '+' : '') + t.pnl_percent + '%)'"></span>
                                        </td>
                                        <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(t.running_balance)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <!-- Mass Validation ("Layer 10 QA" Automation) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4 font-mono">
            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                        <x-app-icon name="layers" class="w-4 h-4 text-accent" />
                        <span>Validasi Massal (Layer 10 QA)</span>
                    </h3>
                    <p class="text-xs text-text-secondary mt-0.5">Uji strategi di banyak koin sekaligus untuk mengecek klaim win-rate ≥80% secara statistik, bukan cuma 1 koin.</p>
                </div>
            </div>

            <form @submit.prevent="openBatchBalanceModal()" class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-xs">
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Jumlah Koin (Top by Volume)</label>
                    <select x-model.number="batchForm.coin_count" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer">
                        <option value="10">10 Koin</option>
                        <option value="20">20 Koin</option>
                        <option value="50">50 Koin</option>
                        <option value="100">100 Koin</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Timeframe</label>
                    <select x-model="batchForm.timeframe" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer">
                        <option value="15m">15 Menit</option>
                        <option value="1h">1 Jam</option>
                        <option value="4h">4 Jam</option>
                        <option value="1d">1 Hari</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block">Target Profit</label>
                    <select x-model="batchForm.target_profile" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer">
                        <option value="scalp">Scalp (+5-10%)</option>
                        <option value="standard">Standard (+10-20%)</option>
                        <option value="extended">Extended (+20-35%)</option>
                        <option value="moonshot">Big Runner (+35-50%)</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="text-text-muted uppercase text-[11px] block" title="PADAWAE: trendline break/retest murni. IFVG: liquidity sweep + inversi FVG + diskon/premium. Money Flow confluence otomatis disertakan di kedua mode.">Strategi</label>
                    <select x-model="batchForm.strategy" @change="saveBatchStrategyPreference()" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-2 text-text-primary font-bold focus:outline-none focus:border-accent cursor-pointer">
                        <option value="ifvg">IFVG</option>
                        <option value="padawae">PADAWAE</option>
                    </select>
                </div>
                <div class="space-y-1.5 flex flex-col justify-end sm:col-span-3 lg:col-span-2">
                    <button type="submit" :disabled="runningBatch" class="w-full py-2 px-4 bg-accent hover:bg-accent-hover disabled:opacity-50 text-white rounded-lg font-bold transition flex items-center justify-center gap-2 cursor-pointer shadow-sm">
                        <template x-if="!runningBatch">
                            <span class="flex items-center gap-2"><x-app-icon name="play" class="w-3.5 h-3.5" /><span>Jalankan Validasi Massal</span></span>
                        </template>
                        <template x-if="runningBatch">
                            <span class="flex items-center gap-2">
                                <div class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                <span x-text="'Menguji ' + batchForm.coin_count + ' koin...'"></span>
                            </span>
                        </template>
                    </button>
                </div>
            </form>

            <template x-if="batchResult">
                <div class="space-y-4 pt-2">
                    <!-- Ringkasan PnL Simulasi Saldo Real -->
                    <div class="bg-accent/5 border border-accent/20 rounded-xl p-4 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-accent flex items-center gap-2">
                            <x-app-icon name="dollar-sign" class="w-3.5 h-3.5" />
                            <span>Ringkasan PnL (Simulasi Saldo Real)</span>
                        </h4>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                            <div>
                                <span class="text-[10px] text-text-muted uppercase block">Saldo Awal</span>
                                <div class="text-base font-bold text-text-primary" x-text="formatMoney(batchResult.portfolio.starting_balance)"></div>
                                <span class="text-[10px] text-text-secondary" x-text="'~' + formatMoney(batchResult.portfolio.capital_per_coin) + '/koin'"></span>
                            </div>
                            <div>
                                <span class="text-[10px] text-text-muted uppercase block">Saldo Akhir</span>
                                <div class="text-base font-bold text-trade-profit" x-text="formatMoney(batchResult.portfolio.ending_balance)"></div>
                            </div>
                            <div>
                                <span class="text-[10px] text-text-muted uppercase block">Net Profit</span>
                                <div class="text-base font-bold" :class="batchResult.portfolio.net_profit_amount >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(batchResult.portfolio.net_profit_amount >= 0 ? '+' : '') + formatMoney(batchResult.portfolio.net_profit_amount)"></div>
                                <span class="text-[10px]" :class="batchResult.portfolio.net_profit_percent >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(batchResult.portfolio.net_profit_percent >= 0 ? '+' : '') + batchResult.portfolio.net_profit_percent + '%'"></span>
                            </div>
                            <div>
                                <span class="text-[10px] text-text-muted uppercase block">Menang / Kalah</span>
                                <div class="text-base font-bold text-text-primary">
                                    <span class="text-trade-profit" x-text="batchResult.aggregate.winning_trades"></span>
                                    <span class="text-text-muted">/</span>
                                    <span class="text-trade-loss" x-text="batchResult.aggregate.losing_trades"></span>
                                </div>
                            </div>
                            <div>
                                <span class="text-[10px] text-text-muted uppercase block">Rata-Rata RR</span>
                                <div class="text-base font-bold text-text-primary" x-text="'1 : ' + batchResult.portfolio.avg_risk_reward"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Aggregate KPIs -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-surface-elevated border border-border-subtle rounded-xl p-3 space-y-1">
                            <span class="text-[10px] text-text-muted uppercase block">Win Rate Agregat</span>
                            <div class="text-lg font-bold" :class="batchResult.aggregate.overall_win_rate >= 80 ? 'text-trade-profit' : 'text-trade-loss'" x-text="batchResult.aggregate.overall_win_rate + '%'"></div>
                        </div>
                        <div class="bg-surface-elevated border border-border-subtle rounded-xl p-3 space-y-1">
                            <span class="text-[10px] text-text-muted uppercase block">Profit Factor</span>
                            <div class="text-lg font-bold text-text-primary" x-text="batchResult.aggregate.overall_profit_factor"></div>
                        </div>
                        <div class="bg-surface-elevated border border-border-subtle rounded-xl p-3 space-y-1">
                            <span class="text-[10px] text-text-muted uppercase block">Total Sinyal Diuji</span>
                            <div class="text-lg font-bold text-text-primary" x-text="batchResult.aggregate.total_trades"></div>
                            <span class="text-[10px] text-text-secondary" x-text="batchResult.symbols_with_data + ' / ' + batchResult.symbols_requested + ' koin ada data'"></span>
                        </div>
                        <div class="bg-surface-elevated border border-border-subtle rounded-xl p-3 space-y-1">
                            <span class="text-[10px] text-text-muted uppercase block">Status Layer 10</span>
                            <div class="text-sm font-bold" :class="batchResult.aggregate.meets_layer10_criteria ? 'text-trade-profit' : 'text-trade-loss'" x-text="batchResult.aggregate.meets_layer10_criteria ? 'TERVALIDASI' : 'BELUM TERVALIDASI'"></div>
                        </div>
                    </div>

                    <!-- Regime Breakdown -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <template x-for="regime in ['bullish', 'sideways', 'bearish']" :key="regime">
                            <div class="border border-border-subtle rounded-xl p-3 space-y-1"
                                :class="regime === 'bullish' ? 'bg-trade-profit-bg/20' : (regime === 'bearish' ? 'bg-trade-loss-bg/20' : 'bg-surface-elevated')">
                                <span class="text-[10px] uppercase font-bold block" x-text="regime === 'bullish' ? 'Kondisi Bullish' : (regime === 'bearish' ? 'Kondisi Bearish' : 'Kondisi Sideways')"></span>
                                <div class="text-base font-bold text-text-primary" x-text="batchResult.by_regime[regime].win_rate + '% Win Rate'"></div>
                                <span class="text-[10px] text-text-secondary" x-text="batchResult.by_regime[regime].symbols_count + ' koin • ' + batchResult.by_regime[regime].total_trades + ' sinyal • PF ' + batchResult.by_regime[regime].profit_factor"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Per-Symbol Breakdown -->
                    <div class="overflow-x-auto max-h-64 overflow-y-auto border border-border-subtle rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-surface">
                                <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase">
                                    <th class="p-2 font-medium">Koin</th>
                                    <th class="p-2 font-medium">Regime</th>
                                    <th class="p-2 font-medium text-right">Sinyal</th>
                                    <th class="p-2 font-medium text-right">Win Rate</th>
                                    <th class="p-2 font-medium text-right">Profit Factor</th>
                                    <th class="p-2 font-medium text-right">Net Profit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border-subtle">
                                <template x-for="row in batchResult.per_symbol" :key="row.symbol">
                                    <tr>
                                        <td class="p-2 font-bold text-text-primary" x-text="row.symbol"></td>
                                        <td class="p-2 text-text-secondary" x-text="row.regime"></td>
                                        <td class="p-2 text-right text-text-secondary" x-text="row.total_trades"></td>
                                        <td class="p-2 text-right font-bold" :class="row.win_rate >= 80 ? 'text-trade-profit' : 'text-trade-loss'" x-text="row.win_rate + '%'"></td>
                                        <td class="p-2 text-right text-text-primary" x-text="row.profit_factor"></td>
                                        <td class="p-2 text-right font-bold" :class="row.net_profit_percent >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(row.net_profit_percent >= 0 ? '+' : '') + row.net_profit_percent + '%'"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>

        <!-- Backtest History (Persisted Runs — Compare Configurations Over Time) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-3 font-mono">
            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                <h3 class="text-sm font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                    <x-app-icon name="clock" class="w-4 h-4 text-accent" />
                    <span>Riwayat Backtest Tersimpan</span>
                </h3>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-text-muted" x-text="backtestHistory.length + ' Run Tersimpan'"></span>
                    <a
                        href="{{ route('simulation.backtest.history.pdf') }}"
                        x-show="backtestHistory.length > 0"
                        class="px-2.5 py-1 bg-surface-elevated hover:bg-accent hover:text-white text-text-primary border border-border-subtle rounded-lg text-[11px] font-bold transition flex items-center gap-1"
                        title="Download riwayat backtest sebagai PDF"
                    >
                        <x-app-icon name="image" class="w-3 h-3" />
                        <span>Download PDF</span>
                    </a>
                    <button
                        type="button"
                        @click="deleteAllBacktestHistory()"
                        x-show="backtestHistory.length > 0"
                        class="px-2.5 py-1 bg-surface-elevated hover:bg-rose-500/20 hover:text-rose-400 text-text-primary border border-border-subtle rounded-lg text-[11px] font-bold transition flex items-center gap-1 cursor-pointer"
                        title="Hapus semua riwayat backtest"
                    >
                        <x-app-icon name="trash-2" class="w-3 h-3" />
                        <span>Hapus Semua</span>
                    </button>
                </div>
            </div>

            <template x-if="backtestHistory.length === 0">
                <p class="text-xs text-text-secondary py-4 text-center">Belum ada riwayat backtest. Jalankan backtest di atas untuk mulai menyimpan riwayat.</p>
            </template>

            <div class="overflow-x-auto" x-show="backtestHistory.length > 0">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase">
                            <th class="pb-2 font-medium">Waktu</th>
                            <th class="pb-2 font-medium">Koin</th>
                            <th class="pb-2 font-medium">TF</th>
                            <th class="pb-2 font-medium text-right">Win Rate</th>
                            <th class="pb-2 font-medium text-right">Profit Factor</th>
                            <th class="pb-2 font-medium text-right">Net Profit</th>
                            <th class="pb-2 font-medium text-right">Max DD</th>
                            <th class="pb-2 font-medium text-right">Total Sinyal</th>
                            <th class="pb-2 font-medium text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        <template x-for="run in backtestHistory" :key="run.id">
                            <tr class="hover:bg-surface-hover transition">
                                <td class="py-2.5 text-text-secondary" x-text="run.created_at"></td>
                                <td class="py-2.5 font-bold text-text-primary" x-text="run.symbol"></td>
                                <td class="py-2.5 text-text-secondary" x-text="run.timeframe.toUpperCase()"></td>
                                <td class="py-2.5 text-right font-bold text-accent" x-text="run.win_rate + '%'"></td>
                                <td class="py-2.5 text-right text-text-primary" x-text="run.profit_factor"></td>
                                <td class="py-2.5 text-right font-bold" :class="run.net_profit_amount >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="(run.net_profit_amount >= 0 ? '+$' : '-$') + Math.abs(run.net_profit_amount).toFixed(2)"></td>
                                <td class="py-2.5 text-right text-trade-loss" x-text="'-' + run.max_drawdown_percent + '%'"></td>
                                <td class="py-2.5 text-right text-text-secondary" x-text="run.total_trades"></td>
                                <td class="py-2.5 text-center">
                                    <button
                                        type="button"
                                        @click="deleteBacktestRun(run.id)"
                                        class="p-1.5 rounded-lg text-text-muted hover:text-rose-400 hover:bg-rose-500/10 transition cursor-pointer"
                                        title="Hapus riwayat ini"
                                    >
                                        <x-app-icon name="trash-2" class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: LIVE PAPER TRADING PORTFOLIO -->
    <div x-show="activeTab === 'paper'" class="space-y-6">

        <!-- Open Virtual Positions (Live Real-Time Monitoring with Flash Animation) -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4 font-mono">
            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                        <x-app-icon name="activity" class="w-4 h-4 text-trade-profit" />
                        <span>Posisi Simulasi Terbuka (Manual Close Control)</span>
                    </h3>
                    <p class="text-xs text-text-secondary mt-0.5">Dipantau real-time setiap 3 detik. Posisi tetap berjalan aktif hingga Anda menutup posisi secara manual.</p>
                </div>
                <div class="flex items-center gap-2 text-[11px] text-emerald-400">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span x-text="'Monitoring Live (' + filteredOpenTrades.length + ' Posisi)'"></span>
                </div>
            </div>

            <template x-if="filteredOpenTrades.length === 0">
                <div class="py-12 text-center space-y-2">
                    <div class="w-10 h-10 rounded-full bg-surface-elevated flex items-center justify-center mx-auto text-text-muted">
                        <x-app-icon name="inbox" class="w-5 h-5" />
                    </div>
                    <h4 class="text-xs font-bold text-text-primary">Belum Ada Posisi Simulasi Terbuka</h4>
                    <p class="text-[11px] text-text-secondary max-w-sm mx-auto">
                        Buka menu <strong>Coin Hunter</strong> dan klik tombol <em>"Simulasi"</em> pada kartu sinyal aktif untuk menentukan modal Spot / Futures dan membuka posisi paper trade.
                    </p>
                </div>
            </template>

            <template x-if="filteredOpenTrades.length > 0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase tracking-wider">
                                <th class="pb-2.5 font-medium">Koin</th>
                                <th class="pb-2.5 font-medium text-center">Tipe</th>
                                <th class="pb-2.5 font-medium">Waktu Entry</th>
                                <th class="pb-2.5 font-medium">Durasi Hold</th>
                                <th class="pb-2.5 font-medium text-right">Modal Margin</th>
                                <th class="pb-2.5 font-medium text-right">Harga Entry</th>
                                <th class="pb-2.5 font-medium text-right">Harga Live</th>
                                <th class="pb-2.5 font-medium text-right">Target TP</th>
                                <th class="pb-2.5 font-medium text-right">Harga Likuidasi</th>
                                <th class="pb-2.5 font-medium text-right">Floating PnL</th>
                                <th class="pb-2.5 font-medium text-center">Aksi Tutup</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border-subtle">
                            <template x-for="trade in filteredOpenTrades" :key="trade.id">
                                <tr class="hover:bg-surface-hover transition">

                                    <!-- Aset & TF -->
                                    <td class="py-3 font-bold text-text-primary">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 rounded bg-surface-elevated border border-border-subtle flex items-center justify-center text-[10px] text-accent font-bold" x-text="trade.symbol.replace('USDT', '').substring(0, 3)"></span>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span x-text="trade.symbol"></span>
                                                    <template x-if="trade.target_status === 'TP2'">
                                                        <span class="px-1 py-0.2 rounded text-[9px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">TP2 HIT</span>
                                                    </template>
                                                    <template x-if="trade.target_status === 'TP1'">
                                                        <span class="px-1 py-0.2 rounded text-[9px] font-bold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">TP1 HIT</span>
                                                    </template>
                                                    <template x-if="trade.target_status === 'SL'">
                                                        <span class="px-1 py-0.2 rounded text-[9px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">SL AREA</span>
                                                    </template>
                                                </div>
                                                <span class="text-[10px] text-text-muted block font-normal" x-text="'TF ' + (trade.timeframe || '1H').toUpperCase()"></span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Tipe & Arah -->
                                    <td class="py-3 text-center">
                                        <template x-if="trade.trading_type === 'futures'">
                                            <div class="space-y-0.5">
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                                    <x-app-icon name="zap" class="w-3 h-3" />
                                                    <span x-text="'FUTURES ' + (trade.leverage || 1) + 'x'"></span>
                                                </span>
                                                <span class="text-[9px] font-bold uppercase block" :class="trade.side === 'short' ? 'text-rose-400' : 'text-emerald-400'" x-text="trade.side === 'short' ? 'SHORT' : 'LONG'"></span>
                                            </div>
                                        </template>
                                        <template x-if="trade.trading_type !== 'futures'">
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-accent/15 text-accent border border-accent/25">
                                                <x-app-icon name="gem" class="w-3 h-3" />
                                                <span>SPOT</span>
                                            </span>
                                        </template>
                                    </td>

                                    <!-- Waktu Entry (Jam & Tanggal) -->
                                    <td class="py-3 text-left">
                                        <div class="space-y-0.5">
                                            <span class="text-xs font-bold text-text-primary block font-mono" x-text="formatEntryClock(trade.entry_time || trade.created_at)"></span>
                                            <span class="text-[10px] text-text-muted block font-mono" x-text="formatEntryDate(trade.entry_time || trade.created_at)"></span>
                                        </div>
                                    </td>

                                    <!-- Durasi Hold (Live Ticking Countdown/Up) -->
                                    <td class="py-3 text-left">
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold bg-surface-elevated text-accent border border-border-subtle font-mono shadow-2xs">
                                            <x-app-icon name="clock" class="w-3 h-3 text-accent animate-pulse" />
                                            <span x-text="formatHoldDuration(trade.entry_time || trade.created_at)"></span>
                                        </span>
                                    </td>

                                    <!-- Modal Margin -->
                                    <td class="py-3 text-right font-bold text-text-primary">
                                        <span x-text="formatMoney(trade.margin_amount || trade.allocated_amount)"></span>
                                        <template x-if="trade.trading_type === 'futures' && trade.leverage > 1">
                                            <span class="text-[10px] text-text-muted block font-normal" x-text="'Pos: ' + formatMoney(trade.allocated_amount)"></span>
                                        </template>
                                    </td>

                                    <!-- Harga Entry -->
                                    <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(trade.entry_price, false, true)"></td>

                                    <!-- Real-Time Price with Dynamic Flash -->
                                    <td
                                        class="py-3 text-right font-bold transition-all duration-300 px-2 rounded"
                                        :class="trade.priceFlash === 'up' ? 'bg-emerald-500/20 text-emerald-400' : (trade.priceFlash === 'down' ? 'bg-rose-500/20 text-rose-400' : 'text-accent')"
                                    >
                                        <span x-text="trade.current_price ? formatMoney(trade.current_price, false, true) : '-'"></span>
                                    </td>

                                    <!-- TP1 / TP2 Target -->
                                    <td class="py-3 text-right font-bold text-trade-profit">
                                        <span x-text="formatMoney(trade.take_profit_1, false, true) + ' / ' + formatMoney(trade.take_profit_2, false, true)"></span>
                                        <span class="text-[10px] text-trade-loss block font-normal" x-text="'SL: ' + formatMoney(trade.stop_loss, false, true)"></span>
                                    </td>

                                    <!-- Liquidation Price -->
                                    <td class="py-3 text-right font-bold" :class="trade.liquidation_price ? 'text-amber-400' : 'text-text-muted'">
                                        <span x-text="trade.liquidation_price ? formatMoney(trade.liquidation_price, false, true) : '-'"></span>
                                    </td>

                                    <!-- Dynamic Real-Time Floating PnL (net of funding for leveraged futures) -->
                                    <td class="py-3 text-right font-bold" :class="(trade.unrealized_pnl_amount || 0) >= 0 ? 'text-trade-profit' : 'text-trade-loss'">
                                        <span x-text="formatMoney(trade.unrealized_pnl_amount || 0, true)"></span>
                                        <span class="text-[10px] block font-normal" x-text="'(' + ((trade.unrealized_pnl_percent || 0) >= 0 ? '+' : '') + Number(trade.unrealized_pnl_percent || 0).toFixed(2) + '%)'"></span>
                                        <template x-if="trade.trading_type === 'futures' && trade.leverage > 1 && Number(trade.funding_paid || 0) !== 0">
                                            <span class="text-[10px] block font-normal text-amber-400" :title="'Funding rate perpetual (~8 jam) sudah dipotong dari PnL'" x-text="'Funding: ' + (trade.funding_paid > 0 ? '-' : '+') + '$' + Math.abs(trade.funding_paid).toFixed(2)"></span>
                                        </template>
                                    </td>

                                    <td class="py-3 text-center">
                                        <button
                                            type="button"
                                            @click="closePaperTrade(trade.id)"
                                            class="px-3 py-1.5 bg-surface-elevated hover:bg-rose-500/20 hover:text-rose-400 hover:border-rose-500/40 text-text-primary border border-border-subtle rounded-lg text-xs font-bold transition cursor-pointer shadow-xs"
                                            title="Tutup posisi sekarang pada harga pasar live"
                                        >
                                            Tutup Posisi
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Closed Paper Trades History -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4 font-mono">
            <div class="flex items-center justify-between pb-3 border-b border-border-subtle">
                <h3 class="text-sm font-bold uppercase tracking-wider text-text-primary flex items-center gap-2">
                    <x-app-icon name="check-circle-2" class="w-4 h-4 text-accent" />
                    <span>Riwayat Transaksi Simulasi Selesai (Paper Trades History)</span>
                </h3>
            </div>

            <template x-if="closedTrades.length === 0">
                <div class="py-8 text-center text-xs text-text-muted">
                    Belum ada riwayat transaksi simulasi yang telah ditutup.
                </div>
            </template>

            <div class="overflow-x-auto" x-show="closedTrades.length > 0">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-border-subtle text-text-muted text-[11px] uppercase tracking-wider">
                            <th class="pb-2.5 font-medium">Aset / Koin</th>
                            <th class="pb-2.5 font-medium text-center">Tipe</th>
                            <th class="pb-2.5 font-medium">Waktu Entry</th>
                            <th class="pb-2.5 font-medium">Waktu Selesai</th>
                            <th class="pb-2.5 font-medium">Durasi Hold</th>
                            <th class="pb-2.5 font-medium text-right">Modal Margin</th>
                            <th class="pb-2.5 font-medium text-right">Harga Entry</th>
                            <th class="pb-2.5 font-medium text-right">Harga Exit</th>
                            <th class="pb-2.5 font-medium text-center">Hasil (Status)</th>
                            <th class="pb-2.5 font-medium text-right">Realisasi PnL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border-subtle">
                        <template x-for="ct in closedTrades" :key="ct.id">
                            <tr class="hover:bg-surface-hover transition">
                                <td class="py-3 font-bold text-text-primary">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded bg-surface-elevated border border-border-subtle flex items-center justify-center text-[10px] text-accent font-bold" x-text="ct.symbol.substring(0, 3)"></span>
                                        <span x-text="ct.symbol"></span>
                                    </div>
                                </td>
                                <td class="py-3 text-center">
                                    <span x-show="ct.trading_type === 'futures'" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                        <x-app-icon name="zap" class="w-3 h-3" />
                                        <span x-text="'FUTURES ' + ct.leverage + 'x (' + (ct.side || 'long').toUpperCase() + ')'"></span>
                                    </span>
                                    <span x-show="ct.trading_type !== 'futures'" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-accent/15 text-accent border border-accent/25">
                                        <x-app-icon name="gem" class="w-3 h-3" />
                                        <span>SPOT</span>
                                    </span>
                                </td>
                                <td class="py-3 text-text-secondary text-[11px] font-mono" x-text="formatFullDateTime(ct.entry_time || ct.created_at)"></td>
                                <td class="py-3 text-text-secondary text-[11px] font-mono" x-text="formatFullDateTime(ct.exit_time)"></td>
                                <td class="py-3 text-text-secondary text-[11px] font-mono">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-surface-elevated text-text-primary border border-border-subtle">
                                        <x-app-icon name="clock" class="w-3 h-3 text-text-muted" />
                                        <span x-text="formatClosedDuration(ct.entry_time || ct.created_at, ct.exit_time)"></span>
                                    </span>
                                </td>
                                <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(Number(ct.margin_amount || ct.allocated_amount))"></td>
                                <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(Number(ct.entry_price), false, true)"></td>
                                <td class="py-3 text-right font-bold text-text-primary" x-text="formatMoney(Number(ct.exit_price), false, true)"></td>
                                <td class="py-3 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded text-[10px] font-bold"
                                        :class="['win_tp1', 'win_tp2', 'win_manual'].includes(ct.status) ? 'bg-trade-profit-bg text-trade-profit border border-trade-profit/30' : 'bg-trade-loss-bg text-trade-loss border border-trade-loss/30'"
                                        x-text="ct.status.replace(/_/g, ' ').toUpperCase()"
                                    ></span>
                                </td>
                                <td class="py-3 text-right font-bold" :class="Number(ct.pnl_amount) >= 0 ? 'text-trade-profit' : 'text-trade-loss'" x-text="formatMoney(Number(ct.pnl_amount), true)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL ATUR SALDO AWAL AKUN SIMULASI -->
    <div
        x-show="resetModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        @keydown.escape.window="closeResetModal()"
    >
        <div
            x-show="resetModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="closeResetModal()"
            class="bg-surface border border-border-subtle rounded-2xl w-full max-w-md shadow-2xl overflow-hidden font-mono"
        >
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-surface-elevated/80 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-accent/20 border border-accent/30 flex items-center justify-center text-accent">
                        <x-app-icon name="sliders" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-text-primary">Atur Saldo Awal Akun Simulasi</h3>
                        <p class="text-[11px] text-text-secondary">Tentukan modal virtual untuk memulai simulasi</p>
                    </div>
                </div>
                <button @click="closeResetModal()" class="text-text-muted hover:text-text-primary p-1.5 rounded-lg hover:bg-surface-hover transition cursor-pointer">
                    <x-app-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Form -->
            <form @submit.prevent="executeCustomReset()" class="p-6 space-y-5">
                <div class="space-y-3">
                    <label class="text-xs font-bold text-text-primary uppercase tracking-wider block">Pilih Nominal Saldo Awal Virtual ($ USD):</label>

                    <!-- Preset Chips -->
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="amt in [50, 100, 500, 1000, 5000, 10000]" :key="amt">
                            <button
                                type="button"
                                @click="customInitialBalance = amt"
                                class="py-2 px-2 rounded-xl text-xs font-bold transition border cursor-pointer text-center"
                                :class="customInitialBalance === amt ? 'bg-accent text-white border-accent shadow-xs' : 'bg-surface-elevated text-text-secondary border-border-subtle hover:text-text-primary'"
                                x-text="'$' + (amt >= 1000 ? (amt/1000) + 'K' : amt)"
                            ></button>
                        </template>
                    </div>

                    <!-- Custom Number Input -->
                    <div class="relative pt-2">
                        <div class="absolute inset-y-0 left-0 pt-2 pl-3.5 flex items-center pointer-events-none text-text-muted font-bold">$</div>
                        <input
                            type="number"
                            x-model.number="customInitialBalance"
                            min="10"
                            step="any"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-xl pl-8 pr-4 py-2.5 text-sm font-bold text-text-primary focus:outline-none focus:border-accent"
                            placeholder="Kustom saldo awal..."
                        />
                    </div>
                </div>

                <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-400 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <x-app-icon name="alert-triangle" class="w-3.5 h-3.5" />
                        <span>Pemberitahuan Reset</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-text-secondary">
                        Mengubah saldo awal akan mereset akun simulasi dan menghapus riwayat transaksi paper trading sebelumnya untuk memulai sesi baru.
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="closeResetModal()"
                        class="w-1/3 py-2.5 bg-surface-elevated hover:bg-surface-hover text-text-secondary rounded-xl text-xs font-bold uppercase transition border border-border-subtle cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="w-2/3 py-2.5 bg-accent hover:bg-accent-hover text-white rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <x-app-icon name="check-circle" class="w-4 h-4" />
                        <span>Konfirmasi &amp; Reset</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL SALDO SIMULASI UNTUK VALIDASI MASSAL -->
    <div
        x-show="batchBalanceModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        @keydown.escape.window="batchBalanceModalOpen = false"
    >
        <div
            x-show="batchBalanceModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            @click.away="batchBalanceModalOpen = false"
            class="bg-surface border border-border-subtle rounded-2xl w-full max-w-md shadow-2xl overflow-hidden font-mono"
        >
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-surface-elevated/80 border-b border-border-subtle flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-accent/20 border border-accent/30 flex items-center justify-center text-accent">
                        <x-app-icon name="dollar-sign" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-text-primary">Simulasikan dengan Saldo Real</h3>
                        <p class="text-[11px] text-text-secondary">Modal ini akan dibagi rata ke semua koin yang diuji</p>
                    </div>
                </div>
                <button @click="batchBalanceModalOpen = false" class="text-text-muted hover:text-text-primary p-1.5 rounded-lg hover:bg-surface-hover transition cursor-pointer">
                    <x-app-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Form -->
            <form @submit.prevent="confirmBatchBacktest()" class="p-6 space-y-5">
                <div class="space-y-3">
                    <label class="text-xs font-bold text-text-primary uppercase tracking-wider block">Total Modal Simulasi ($ USD):</label>

                    <!-- Preset Chips -->
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="amt in [1000, 5000, 10000, 25000, 50000, 100000]" :key="amt">
                            <button
                                type="button"
                                @click="batchForm.initial_capital = amt"
                                class="py-2 px-2 rounded-xl text-xs font-bold transition border cursor-pointer text-center"
                                :class="batchForm.initial_capital === amt ? 'bg-accent text-white border-accent shadow-xs' : 'bg-surface-elevated text-text-secondary border-border-subtle hover:text-text-primary'"
                                x-text="'$' + (amt >= 1000 ? (amt/1000) + 'K' : amt)"
                            ></button>
                        </template>
                    </div>

                    <!-- Custom Number Input -->
                    <div class="relative pt-2">
                        <div class="absolute inset-y-0 left-0 pt-2 pl-3.5 flex items-center pointer-events-none text-text-muted font-bold">$</div>
                        <input
                            type="number"
                            x-model.number="batchForm.initial_capital"
                            min="10"
                            step="any"
                            class="w-full bg-surface-elevated border border-border-subtle rounded-xl pl-8 pr-4 py-2.5 text-sm font-bold text-text-primary focus:outline-none focus:border-accent"
                            placeholder="Kustom modal simulasi..."
                        />
                    </div>

                    <p class="text-[11px] text-text-secondary" x-show="batchForm.coin_count > 0">
                        ≈ <span x-text="'$' + (batchForm.initial_capital / batchForm.coin_count).toFixed(2)"></span> per koin, dibagi rata ke <span x-text="batchForm.coin_count"></span> koin yang diuji.
                    </p>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button
                        type="button"
                        @click="batchBalanceModalOpen = false"
                        class="w-1/3 py-2.5 bg-surface-elevated hover:bg-surface-hover text-text-secondary rounded-xl text-xs font-bold uppercase transition border border-border-subtle cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="w-2/3 py-2.5 bg-accent hover:bg-accent-hover text-white rounded-xl text-xs font-bold uppercase tracking-wider transition shadow-md flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <x-app-icon name="play" class="w-4 h-4" />
                        <span>Mulai Simulasi</span>
                    </button>
                </div>
            </form>
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

function simulationDashboardComponent() {
    return {
        activeTab: 'paper', // Default to Paper Trading
        runningBacktest: false,
        backtestResult: null,
        runningBatch: false,
        batchResult: null,
        batchBalanceModalOpen: false,
        batchForm: {
            coin_count: 20,
            timeframe: '1h',
            target_profile: 'standard',
            initial_capital: 10000,
            // Persisted separately from Coin Hunter's live-scan strategy
            // toggle — you may want to backtest PADAWAE while live-scanning
            // IFVG (or vice versa), so these aren't forced to match.
            strategy: localStorage.getItem('validasi_massal_strategy_mode') || 'ifvg',
        },
        equityChartInstance: null,

        saveBatchStrategyPreference() {
            localStorage.setItem('validasi_massal_strategy_mode', this.batchForm.strategy);
        },

        // Persistent Currency Preference (USD vs IDR) - Kurs 1 USD = Rp 17.756,00
        currency: localStorage.getItem('paper_currency') || 'USD',
        usdToIdrRate: 17756,

        setCurrency(cur) {
            this.currency = cur;
            localStorage.setItem('paper_currency', cur);
        },

        // Persistent Trading Mode Filter ('all' | 'spot' | 'futures')
        tradeTypeFilter: localStorage.getItem('paper_trading_filter') || 'all',

        setTradingFilter(filter) {
            this.tradeTypeFilter = filter;
            localStorage.setItem('paper_trading_filter', filter);
        },

        // Money & Currency Formatting Helper (USD vs IDR Rp)
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
                // USD
                if (isCryptoPrice) {
                    return formatCryptoPrice(num);
                }
                const absVal = Math.abs(num);
                const prefix = signed ? (num >= 0 ? '+$' : '-$') : '$';

                return prefix + absVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        },

        // Live Hold Duration & Real-Time Clock
        currentTime: Date.now(),

        parseDate(dateInput) {
            if (!dateInput) return null;
            if (dateInput instanceof Date) return dateInput;
            if (typeof dateInput === 'number') return new Date(dateInput);
            const s = String(dateInput).trim().replace(' ', 'T');
            const d = new Date(s);
            return isNaN(d.getTime()) ? null : d;
        },

        formatEntryDate(dateInput) {
            const d = this.parseDate(dateInput);
            if (!d) return '-';
            const day = String(d.getDate()).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            const month = months[d.getMonth()];
            const year = d.getFullYear();
            return `${day} ${month} ${year}`;
        },

        formatEntryClock(dateInput) {
            const d = this.parseDate(dateInput);
            if (!d) return '-';
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            const seconds = String(d.getSeconds()).padStart(2, '0');
            return `${hours}:${minutes}:${seconds}`;
        },

        formatHoldDuration(dateInput) {
            const d = this.parseDate(dateInput);
            if (!d) return '-';
            const diffMs = Math.max(0, this.currentTime - d.getTime());
            const diffSec = Math.floor(diffMs / 1000);

            const days = Math.floor(diffSec / 86400);
            const hours = Math.floor((diffSec % 86400) / 3600);
            const minutes = Math.floor((diffSec % 3600) / 60);
            const seconds = diffSec % 60;

            if (days > 0) {
                return `${days}h ${hours}j ${minutes}m`;
            } else if (hours > 0) {
                return `${hours}j ${minutes}m ${seconds}d`;
            } else if (minutes > 0) {
                return `${minutes}m ${seconds}d`;
            } else {
                return `${seconds}d`;
            }
        },

        formatFullDateTime(dateInput) {
            const d = this.parseDate(dateInput);
            if (!d) return '-';
            return this.formatEntryDate(d) + ', ' + this.formatEntryClock(d).slice(0, 5);
        },

        formatClosedDuration(startInput, endInput) {
            const start = this.parseDate(startInput);
            const end = this.parseDate(endInput);
            if (!start || !end) return '-';
            const diffSec = Math.max(0, Math.floor((end.getTime() - start.getTime()) / 1000));
            const days = Math.floor(diffSec / 86400);
            const hours = Math.floor((diffSec % 86400) / 3600);
            const minutes = Math.floor((diffSec % 3600) / 60);
            if (days > 0) return `${days}h ${hours}j`;
            if (hours > 0) return `${hours}j ${minutes}m`;
            return `${minutes}m`;
        },

        get filteredOpenTrades() {
            if (this.tradeTypeFilter === 'spot') {
                return this.openTrades.filter(t => t.trading_type !== 'futures');
            }
            if (this.tradeTypeFilter === 'futures') {
                return this.openTrades.filter(t => t.trading_type === 'futures');
            }
            return this.openTrades;
        },

        // Reset Initial Balance Modal State
        resetModalOpen: false,
        customInitialBalance: 100,

        openResetModal() {
            this.customInitialBalance = this.accountData.initial_balance || 100;
            this.resetModalOpen = true;
        },

        closeResetModal() {
            this.resetModalOpen = false;
        },

        backtestForm: {
            symbol: 'BTCUSDT',
            timeframe: '1h',
            target_profile: 'standard',
            custom_min: 8,
            custom_max: 18,
            initial_capital: 100,
            allocation_pct: 25,
            candle_count: 80,
            fee_pct: 0.1,
            slippage_pct: 0.05,
            strategy: localStorage.getItem('backtest_strategy_mode') || 'ifvg',
        },
        backtestHistory: [],

        topCoins: @json($topCoins),
        accountData: @json($wallet ?? []),
        openTrades: @json($openTrades ?? []),
        closedTrades: @json($closedTrades ?? []),

        init() {
            // Live 1-second ticker to update hold duration seconds in real-time
            setInterval(() => {
                this.currentTime = Date.now();
            }, 1000);

            // Live poll open paper positions & wallet metrics every 3 seconds
            setInterval(() => {
                this.fetchLivePaperStatus();
            }, 3000);

            this.fetchBacktestHistory();
        },

        fetchBacktestHistory() {
            fetch('/simulation/backtest/history')
                .then(res => res.json())
                .then(data => {
                    this.backtestHistory = data.runs || [];
                })
                .catch(err => console.debug('Backtest history fetch:', err));
        },

        deleteBacktestRun(id) {
            window.Alert.confirmDelete('Hapus Riwayat Backtest Ini?', 'Data run backtest ini akan dihapus permanen dan tidak bisa dikembalikan.')
                .then(result => {
                    if (!result.isConfirmed) return;

                    fetch(`/simulation/backtest/history/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.backtestHistory = this.backtestHistory.filter(r => r.id !== id);
                            window.Alert.toast(data.message, 'success');
                        } else if (window.Alert) {
                            window.Alert.error('Gagal Menghapus', data.message || 'Riwayat tidak ditemukan.');
                        }
                    })
                    .catch(err => console.error('Delete backtest run error:', err));
                });
        },

        deleteAllBacktestHistory() {
            window.Alert.confirmDelete('Hapus SEMUA Riwayat Backtest?', 'Seluruh riwayat backtest tersimpan akan dihapus permanen dan tidak bisa dikembalikan.')
                .then(result => {
                    if (!result.isConfirmed) return;

                    fetch('/simulation/backtest/history', {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        this.backtestHistory = [];
                        window.Alert.toast(data.message, 'success');
                    })
                    .catch(err => console.error('Delete all backtest history error:', err));
                });
        },

        openBatchBalanceModal() {
            this.batchBalanceModalOpen = true;
        },

        confirmBatchBacktest() {
            this.batchBalanceModalOpen = false;
            this.runBatchBacktest();
        },

        runBatchBacktest() {
            this.runningBatch = true;

            fetch('/simulation/backtest/batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.batchForm)
            })
            .then(res => res.json())
            .then(data => {
                this.runningBatch = false;
                if (data.success) {
                    this.batchResult = data;
                } else if (window.Alert) {
                    window.Alert.error('Validasi Massal Gagal', data.message || 'Gagal menjalankan validasi massal.');
                }
            })
            .catch(err => {
                this.runningBatch = false;
                console.error('Batch backtest error:', err);
            });
        },

        runBacktest() {
            this.runningBacktest = true;

            fetch('/simulation/backtest/run', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.backtestForm)
            })
            .then(res => res.json())
            .then(data => {
                this.runningBacktest = false;
                if (data.success) {
                    this.backtestResult = data;
                    this.$nextTick(() => {
                        this.renderEquityChart(data.equity_curve);
                    });
                    this.fetchBacktestHistory();
                } else {
                    if (window.Alert) {
                        window.Alert.error('Backtest Gagal', data.message || 'Gagal menjalankan simulasi.');
                    }
                }
            })
            .catch(err => {
                this.runningBacktest = false;
                console.error('Backtest error:', err);
            });
        },

        renderEquityChart(equityData) {
            const chartEl = document.querySelector('#equityCurveChart');
            if (!chartEl || !equityData || equityData.length === 0) return;

            if (this.equityChartInstance) {
                this.equityChartInstance.destroy();
            }

            const isDark = document.documentElement.classList.contains('dark');
            const seriesData = equityData.map(d => ({
                x: d.time,
                y: d.balance
            }));

            const options = {
                series: [{
                    name: 'Saldo Virtual ($)',
                    data: seriesData
                }],
                chart: {
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    background: 'transparent',
                    animations: { enabled: true, easing: 'easeinout', speed: 600 }
                },
                colors: ['#10B981'],
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [0, 95, 100]
                    }
                },
                stroke: {
                    curve: 'smooth',
                    width: 2.5
                },
                xaxis: {
                    type: 'datetime',
                    labels: {
                        style: {
                            colors: isDark ? '#64748B' : '#94A3B8',
                            fontFamily: 'JetBrains Mono, monospace',
                            fontSize: '10px'
                        }
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: (val) => '$' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }),
                        style: {
                            colors: isDark ? '#64748B' : '#94A3B8',
                            fontFamily: 'JetBrains Mono, monospace',
                            fontSize: '10px'
                        }
                    }
                },
                grid: {
                    borderColor: isDark ? '#1A222D' : '#E2E8F0',
                    strokeDashArray: 3
                },
                tooltip: {
                    theme: isDark ? 'dark' : 'light',
                    x: { format: 'dd MMM yyyy HH:mm' },
                    y: {
                        formatter: (val) => '$' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    }
                }
            };

            this.equityChartInstance = new ApexCharts(chartEl, options);
            this.equityChartInstance.render();
        },

        fetchLivePaperStatus() {
            fetch('/simulation/live-status')
                .then(res => res.json())
                .then(data => {
                    if (data) {
                        this.accountData = {
                            virtual_balance: data.virtual_balance,
                            initial_balance: data.initial_balance,
                            available_cash: data.available_cash,
                            locked_margin: data.locked_margin,
                            total_positions_value: data.total_positions_value,
                            floating_unrealized_pnl_usd: data.floating_unrealized_pnl_usd,
                            floating_unrealized_pnl_pct: data.floating_unrealized_pnl_pct,
                            total_virtual_equity: data.total_virtual_equity,
                            win_rate: data.win_rate,
                            total_trades: data.total_trades,
                            winning_trades: data.winning_trades,
                            losing_trades: data.losing_trades,
                            net_profit_amount: data.net_profit_amount,
                            formatted_net_profit: data.formatted_net_profit,
                        };

                        if (data.open_trades) {
                            // Flash animation update
                            this.openTrades = data.open_trades.map(newTrade => {
                                const oldMatch = this.openTrades.find(t => t.id === newTrade.id);
                                let flash = null;
                                if (oldMatch && oldMatch.current_price) {
                                    if (newTrade.current_price > oldMatch.current_price) flash = 'up';
                                    else if (newTrade.current_price < oldMatch.current_price) flash = 'down';
                                }
                                return {
                                    ...newTrade,
                                    priceFlash: flash
                                };
                            });
                        }
                    }
                })
                .catch(err => console.debug('Live paper ticker tick:', err));
        },

        closePaperTrade(id) {
            fetch(`/simulation/paper/${id}/close`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (window.Alert) {
                    window.Alert.toast(data.message, 'info');
                }

                // Move the trade from "open" to "history" instantly — no
                // need to wait for the next 3s poll or a page reload.
                if (data.success && data.trade) {
                    this.openTrades = this.openTrades.filter(t => t.id !== data.trade.id);
                    this.closedTrades = [data.trade, ...this.closedTrades];
                }

                this.fetchLivePaperStatus();
            });
        },

        executeCustomReset() {
            fetch('/simulation/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    initial_balance: this.customInitialBalance
                })
            })
            .then(res => res.json())
            .then(data => {
                this.closeResetModal();
                if (window.Alert) {
                    window.Alert.toast(data.message, 'success');
                }
                this.fetchLivePaperStatus();
            });
        }
    };
}
</script>
@endsection
