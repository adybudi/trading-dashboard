@props([
    'variant' => 'header', // 'header' (expanded horizontal) or 'sidebar' (compact vertical)
])

<div
    x-data="{
        tvConnected: false,
        tvLaunching: false,
        tvDropdownOpen: false,
        aiConnected: false,
        aiStatus: 'loading',
        modelName: 'Gemini Flash 3.8',
        pollTimer: null,

        init() {
            this.checkStatus();
            this.pollTimer = setInterval(() => this.checkStatus(), 12000);
            window.addEventListener('tv-status-refresh', () => this.checkStatus());
        },

        async checkStatus() {
            try {
                const res = await fetch('/tradingview/status', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.tvConnected = !!(data.tradingview && data.tradingview.connected);
                    this.aiConnected = !!(data.omniroute && data.omniroute.connected);
                    this.aiStatus = (data.omniroute && data.omniroute.status) || 'offline';
                    if (data.omniroute && data.omniroute.provider) {
                        this.modelName = data.omniroute.provider;
                    }
                }
            } catch (e) {
                this.tvConnected = false;
                this.aiConnected = false;
            }
        },

        async launchTv() {
            if (this.tvLaunching) return;
            this.tvLaunching = true;
            try {
                const res = await fetch('/tradingview/launch-remote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                    }
                });
                const data = await res.json();
                if (data.connected) {
                    this.tvConnected = true;
                    if (window.Alert) {
                        window.Alert.toast(data.message || 'TradingView Desktop terhubung pada port 9222!', 'success');
                    }
                } else {
                    if (window.Alert) {
                        window.Alert.toast(data.message || 'TradingView diluncurkan. Menunggu respon port 9222...', 'warning');
                    }
                }
            } catch (e) {
                if (window.Alert) {
                    window.Alert.error('Error', 'Gagal meluncurkan TradingView Remote');
                }
            } finally {
                this.tvLaunching = false;
                this.checkStatus();
            }
        },

        async restartClean() {
            if (this.tvLaunching) return;
            this.tvLaunching = true;
            this.tvDropdownOpen = false;
            try {
                const res = await fetch('/tradingview/restart-clean', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''
                    }
                });
                const data = await res.json();
                if (data.connected) {
                    this.tvConnected = true;
                    if (window.Alert) {
                        window.Alert.toast('TradingView di-restart bersih & terhubung pada port 9222!', 'success');
                    }
                } else {
                    if (window.Alert) {
                        window.Alert.toast(data.message || 'TradingView di-restart. Tunggu beberapa detik.', 'info');
                    }
                }
            } catch (e) {
                if (window.Alert) {
                    window.Alert.error('Error', 'Gagal merestart TradingView');
                }
            } finally {
                this.tvLaunching = false;
                this.checkStatus();
            }
        }
    }"
    class="{{ $variant === 'header' ? 'flex items-center gap-2' : 'space-y-2' }}"
>
    @if($variant === 'header')
        <!-- Header Variant: Full status pills & action button -->
        <div class="flex items-center gap-2">

            <!-- OmniRoute AI Status Pill -->
            <div
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-mono border transition-all"
                :class="aiConnected
                    ? 'bg-accent/10 text-accent border-accent/30 shadow-xs'
                    : 'bg-amber-500/10 text-amber-500 border-amber-500/30'"
                :title="aiConnected ? 'OmniRoute AI Online • Gemini Flash 3.8 Multimodal Active' : 'OmniRoute AI Offline/Fallback • Berjalan dengan Quant Core Default'"
            >
                <span class="w-1.5 h-1.5 rounded-full" :class="aiConnected ? 'bg-accent animate-pulse' : 'bg-amber-500'"></span>
                <span x-text="aiConnected ? 'AI: 🟢 Gemini Flash 3.8' : 'AI: 🔴 Fallback Mode'"></span>
            </div>

            <!-- TradingView Remote Widget -->
            <div class="relative inline-flex items-center">
                <!-- Status Badge & Connect Button -->
                <div class="flex items-center rounded-lg border overflow-hidden text-[10px] font-mono"
                    :class="tvConnected
                        ? 'bg-trade-profit-bg border-trade-profit/30 text-trade-profit'
                        : 'bg-trade-loss-bg border-trade-loss/30 text-trade-loss'"
                >
                    <!-- Status Display -->
                    <button
                        type="button"
                        @click="checkStatus()"
                        class="flex items-center gap-1.5 px-2.5 py-1 transition hover:opacity-80 cursor-pointer"
                        :title="tvConnected ? 'TradingView CDP Port 9222 Terhubung (Klik untuk Refresh)' : 'Port 9222 Offline (Klik untuk Refresh)'"
                    >
                        <span class="w-1.5 h-1.5 rounded-full" :class="tvConnected ? 'bg-trade-profit animate-pulse' : 'bg-trade-loss'"></span>
                        <span x-text="tvConnected ? 'TV: 🟢 :9222 Connected' : 'TV: 🔴 Offline'"></span>
                    </button>

                    <!-- Launch Remote Action Button (Shown when offline or launching) -->
                    <template x-if="!tvConnected || tvLaunching">
                        <button
                            type="button"
                            @click="launchTv()"
                            :disabled="tvLaunching"
                            class="flex items-center gap-1 px-2 py-1 bg-accent hover:bg-accent-hover text-white font-bold transition border-l border-border-subtle cursor-pointer disabled:opacity-50"
                            title="Buka TradingView Desktop dengan port remote debugging 9222"
                        >
                            <template x-if="tvLaunching">
                                <span class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            </template>
                            <template x-if="!tvLaunching">
                                <x-app-icon name="tv" class="w-3 h-3" />
                            </template>
                            <span x-text="tvLaunching ? 'Launching...' : 'Launch Remote'"></span>
                        </button>
                    </template>

                    <!-- Dropdown Toggle Menu Button -->
                    <button
                        type="button"
                        @click="tvDropdownOpen = !tvDropdownOpen"
                        @click.outside="tvDropdownOpen = false"
                        class="p-1 hover:bg-surface-hover/50 transition border-l border-border-subtle cursor-pointer text-text-secondary hover:text-text-primary"
                        title="Opsi TradingView"
                    >
                        <x-app-icon name="chevron-down" class="w-3 h-3" />
                    </button>
                </div>

                <!-- Dropdown Menu -->
                <div
                    x-show="tvDropdownOpen"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="absolute right-0 top-full mt-1.5 w-56 rounded-xl bg-surface border border-border-subtle shadow-xl py-1 z-50 text-xs font-mono"
                    style="display: none;"
                >
                    <div class="px-3 py-1.5 border-b border-border-subtle text-[10px] text-text-muted">
                        TradingView Remote Control (:9222)
                    </div>
                    <button
                        type="button"
                        @click="launchTv()"
                        class="w-full flex items-center gap-2 px-3 py-2 text-left text-text-primary hover:bg-surface-hover transition cursor-pointer"
                    >
                        <x-app-icon name="play" class="w-3.5 h-3.5 text-accent" />
                        <span>Launch Remote (:9222)</span>
                    </button>
                    <button
                        type="button"
                        @click="restartClean()"
                        class="w-full flex items-center gap-2 px-3 py-2 text-left text-amber-400 hover:bg-surface-hover transition cursor-pointer"
                    >
                        <x-app-icon name="rotate-ccw" class="w-3.5 h-3.5" />
                        <span>Restart Clean (pkill TV)</span>
                    </button>
                    <button
                        type="button"
                        @click="checkStatus(); tvDropdownOpen = false;"
                        class="w-full flex items-center gap-2 px-3 py-2 text-left text-text-secondary hover:bg-surface-hover transition cursor-pointer"
                    >
                        <x-app-icon name="refresh-cw" class="w-3.5 h-3.5" />
                        <span>Periksa Status Port</span>
                    </button>
                </div>
            </div>

        </div>
    @else
        <!-- Sidebar Compact Variant -->
        <div class="space-y-1.5 font-mono text-[11px]">
            <!-- TradingView Status Pill -->
            <div
                class="flex items-center justify-between px-3 py-1.5 rounded-lg border transition"
                :class="tvConnected
                    ? 'bg-trade-profit-bg text-trade-profit border-trade-profit/20'
                    : 'bg-surface-elevated text-text-muted border-border-subtle'"
            >
                <div class="flex items-center gap-2 truncate">
                    <span class="w-2 h-2 rounded-full" :class="tvConnected ? 'bg-trade-profit animate-pulse' : 'bg-trade-loss'"></span>
                    <span class="truncate" x-text="tvConnected ? 'TV Port 9222 Live' : 'TV Disconnected'"></span>
                </div>
                <button
                    type="button"
                    @click="launchTv()"
                    :disabled="tvLaunching"
                    class="p-1 rounded hover:bg-surface-hover transition text-text-primary cursor-pointer disabled:opacity-50"
                    title="Launch TradingView Remote"
                >
                    <template x-if="tvLaunching">
                        <span class="w-3 h-3 border border-accent border-t-transparent rounded-full animate-spin block"></span>
                    </template>
                    <template x-if="!tvLaunching">
                        <x-app-icon name="play" class="w-3 h-3 text-accent" />
                    </template>
                </button>
            </div>

            <!-- OmniRoute AI Pill -->
            <div
                class="flex items-center justify-between px-3 py-1 rounded-lg border text-[10px]"
                :class="aiConnected
                    ? 'bg-accent/10 text-accent border-accent/20'
                    : 'bg-surface-elevated text-text-muted border-border-subtle'"
                :title="aiConnected ? 'Gemini Flash 3.8 Ready' : 'AI Offline (Fallback Mode)'"
            >
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full" :class="aiConnected ? 'bg-accent' : 'bg-text-muted'"></span>
                    <span x-text="aiConnected ? 'OmniRoute AI Active' : 'AI Standby/Fallback'"></span>
                </div>
                <x-app-icon name="cpu" class="w-3 h-3 text-accent" />
            </div>
        </div>
    @endif
</div>
