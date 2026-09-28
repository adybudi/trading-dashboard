@extends('layouts.app')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Row -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono flex items-center gap-2">
                <x-app-icon name="book-open" class="w-5 h-5 text-accent" />
                <span>TRADE JOURNAL</span>
            </h1>
            <p class="text-xs text-text-secondary mt-0.5">Comprehensive execution logs, rule compliance, and analysis.</p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <a href="{{ route('trades.create') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 bg-accent hover:bg-accent-hover text-white rounded-lg text-xs font-semibold tracking-wide transition shadow-sm cursor-pointer">
                <x-app-icon name="plus" class="w-4 h-4" />
                <span>Log New Trade</span>
            </a>
        </div>
    </div>

    <!-- Filter & Search Panel -->
    <div class="bg-surface border border-border-subtle rounded-xl p-4">
        <form method="GET" action="{{ route('trades.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
            
            <!-- Symbol Search -->
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-muted mb-1">Symbol</label>
                <div class="relative">
                    <input 
                        type="text" 
                        name="symbol" 
                        value="{{ request('symbol') }}" 
                        placeholder="e.g. BTCUSDT" 
                        class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-3 py-1.5 text-xs text-text-primary placeholder:text-text-muted focus:outline-none focus:border-accent font-mono uppercase"
                    />
                </div>
            </div>

            <!-- Account Filter -->
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-muted mb-1">Account</label>
                <select name="account_id" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-2 py-1.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                    <option value="">All Accounts</option>
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}" {{ request('account_id') == $acc->id ? 'selected' : '' }}>
                            {{ $acc->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Direction -->
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-muted mb-1">Direction</label>
                <select name="direction" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-2 py-1.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                    <option value="">All Sides</option>
                    <option value="long" {{ request('direction') == 'long' ? 'selected' : '' }}>Long (Buy)</option>
                    <option value="short" {{ request('direction') == 'short' ? 'selected' : '' }}>Short (Sell)</option>
                </select>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-muted mb-1">Status</label>
                <select name="status" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-2 py-1.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                    <option value="">All Statuses</option>
                    <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>Open</option>
                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </div>

            <!-- Strategy -->
            <div>
                <label class="block text-[10px] font-mono uppercase text-text-muted mb-1">Strategy</label>
                <select name="strategy_id" class="w-full bg-surface-elevated border border-border-subtle rounded-lg px-2 py-1.5 text-xs text-text-primary focus:outline-none focus:border-accent font-mono">
                    <option value="">All Strategies</option>
                    @foreach($strategies as $strat)
                        <option value="{{ $strat->id }}" {{ request('strategy_id') == $strat->id ? 'selected' : '' }}>
                            {{ $strat->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Buttons -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 px-3 py-1.5 bg-surface-hover hover:bg-border-subtle text-text-primary rounded-lg text-xs font-semibold border border-border-subtle transition font-mono flex items-center justify-center gap-1 cursor-pointer">
                    <x-app-icon name="filter" class="w-3.5 h-3.5" />
                    <span>Filter</span>
                </button>
                <a href="{{ route('trades.index') }}" class="p-1.5 bg-surface-hover hover:bg-border-subtle text-text-muted hover:text-text-primary rounded-lg text-xs border border-border-subtle transition cursor-pointer" title="Reset Filters">
                    <x-app-icon name="refresh-cw" class="w-3.5 h-3.5" />
                </a>
            </div>
        </form>
    </div>

    <!-- Trades Table -->
    <div class="bg-surface border border-border-subtle rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-surface-elevated text-text-secondary border-b border-border-subtle font-mono uppercase text-[10px] tracking-wider">
                        <th class="py-3 px-3">Entry Date</th>
                        <th class="py-3 px-3">Account</th>
                        <th class="py-3 px-3">Symbol</th>
                        <th class="py-3 px-3">Direction</th>
                        <th class="py-3 px-3 text-right">Entry Price</th>
                        <th class="py-3 px-3 text-right">Exit Price</th>
                        <th class="py-3 px-3 text-right">Qty / Lot</th>
                        <th class="py-3 px-3 text-right">Net P&L</th>
                        <th class="py-3 px-3 text-center">R:R (Act/Plan)</th>
                        <th class="py-3 px-3">Strategy & Tags</th>
                        <th class="py-3 px-3 text-center">Media</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border-subtle font-mono">
                    @forelse($trades as $trade)
                        <tr class="hover:bg-surface-hover transition-colors">
                            
                            <!-- Entry Date -->
                            <td class="py-3 px-3 text-text-secondary whitespace-nowrap">
                                <div>{{ $trade->entry_at->format('d M Y') }}</div>
                                <div class="text-[10px] text-text-muted">{{ $trade->entry_at->format('H:i') }}</div>
                            </td>

                            <!-- Account -->
                            <td class="py-3 px-3 text-text-secondary whitespace-nowrap truncate max-w-[110px]" title="{{ $trade->account->name }}">
                                {{ $trade->account->name }}
                            </td>

                            <!-- Symbol -->
                            <td class="py-3 px-3 font-bold text-text-primary whitespace-nowrap">
                                {{ $trade->symbol }}
                            </td>

                            <!-- Direction -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <x-badge :type="$trade->direction" />
                            </td>

                            <!-- Entry Price -->
                            <td class="py-3 px-3 text-right tabular-nums text-text-primary whitespace-nowrap">
                                ${{ number_format($trade->entry_price, 2) }}
                            </td>

                            <!-- Exit Price -->
                            <td class="py-3 px-3 text-right tabular-nums text-text-secondary whitespace-nowrap">
                                {{ $trade->exit_price ? '$' . number_format($trade->exit_price, 2) : '—' }}
                            </td>

                            <!-- Quantity -->
                            <td class="py-3 px-3 text-right tabular-nums text-text-secondary whitespace-nowrap">
                                {{ number_format($trade->quantity, 2) }}
                            </td>

                            <!-- Net PnL -->
                            <td class="py-3 px-3 text-right tabular-nums font-bold whitespace-nowrap">
                                @if($trade->status === 'open')
                                    <x-badge type="open" />
                                @elseif($trade->net_pnl > 0)
                                    <span class="text-trade-profit">+${{ number_format($trade->net_pnl, 2) }}</span>
                                    <div class="text-[10px] text-trade-profit/80">+{{ $trade->roi_percentage }}%</div>
                                @elseif($trade->net_pnl < 0)
                                    <span class="text-trade-loss">-${{ number_format(abs($trade->net_pnl), 2) }}</span>
                                    <div class="text-[10px] text-trade-loss/80">{{ $trade->roi_percentage }}%</div>
                                @else
                                    <span class="text-trade-neutral">$0.00</span>
                                @endif
                            </td>

                            <!-- Risk:Reward -->
                            <td class="py-3 px-3 text-center tabular-nums whitespace-nowrap text-text-secondary">
                                @if($trade->actual_rr)
                                    <span class="font-bold {{ $trade->actual_rr > 0 ? 'text-trade-profit' : 'text-trade-loss' }}">{{ $trade->actual_rr }}R</span>
                                @elseif($trade->planned_rr)
                                    <span class="text-text-muted">{{ $trade->planned_rr }}R (P)</span>
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>

                            <!-- Strategy & Tags -->
                            <td class="py-3 px-3">
                                <div class="flex flex-col gap-1 max-w-[150px]">
                                    @if($trade->strategy)
                                        <span class="inline-flex items-center gap-1 text-[11px] truncate text-text-primary">
                                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color: {{ $trade->strategy->color_code }}"></span>
                                            <span class="truncate">{{ $trade->strategy->name }}</span>
                                        </span>
                                    @endif
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($trade->tags as $tag)
                                            <span class="text-[9px] px-1 rounded bg-surface-hover text-text-muted border border-border-subtle">
                                                {{ $tag->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </td>

                            <!-- Media Attachments -->
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                @if($trade->attachments->count() > 0)
                                    <span class="inline-flex items-center gap-1 text-accent text-[11px]" title="{{ $trade->attachments->count() }} screenshots attached">
                                        <x-app-icon name="image" class="w-3.5 h-3.5" />
                                        <span>{{ $trade->attachments->count() }}</span>
                                    </span>
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('trades.show', $trade) }}" class="p-1 text-text-secondary hover:text-text-primary hover:bg-surface-elevated rounded transition" title="View Details">
                                        <x-app-icon name="eye" class="w-3.5 h-3.5" />
                                    </a>
                                    <a href="{{ route('trades.edit', $trade) }}" class="p-1 text-text-secondary hover:text-text-primary hover:bg-surface-elevated rounded transition" title="Edit Trade">
                                        <x-app-icon name="edit" class="w-3.5 h-3.5" />
                                    </a>
                                    
                                    <!-- Delete Button with SweetAlert2 -->
                                    <button 
                                        type="button" 
                                        onclick="handleDeleteTrade({{ $trade->id }}, '{{ $trade->symbol }}')" 
                                        class="p-1 text-text-muted hover:text-trade-loss hover:bg-trade-loss-bg rounded transition cursor-pointer" 
                                        title="Delete Trade"
                                    >
                                        <x-app-icon name="trash-2" class="w-3.5 h-3.5" />
                                    </button>

                                    <form id="delete-form-{{ $trade->id }}" action="{{ route('trades.destroy', $trade) }}" method="POST" class="hidden">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-12 text-text-muted font-mono">
                                No trade entries found. Match your search query or log a new trade.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($trades->hasPages())
            <div class="p-4 border-t border-border-subtle bg-surface-elevated/50 font-mono text-xs">
                {{ $trades->links() }}
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function handleDeleteTrade(tradeId, symbol) {
        if (window.Alert) {
            window.Alert.confirmDelete(
                `Hapus Jurnal Trade ${symbol}?`, 
                'Data transaksi dan riwayat screenshot akan dihapus secara permanen.'
            ).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`delete-form-${tradeId}`).submit();
                }
            });
        } else {
            if (confirm(`Yakin ingin menghapus trade ${symbol}?`)) {
                document.getElementById(`delete-form-${tradeId}`).submit();
            }
        }
    }
</script>
@endpush
@endsection
