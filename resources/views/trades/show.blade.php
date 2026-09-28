@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header Breadcrumb & Actions -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-4 border-b border-border-subtle">
        <div class="flex items-center gap-3">
            <a href="{{ route('trades.index') }}" class="p-2 rounded-lg bg-surface hover:bg-surface-hover text-text-secondary hover:text-text-primary border border-border-subtle transition">
                <x-app-icon name="chevron-left" class="w-4 h-4" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-text-primary font-mono">{{ $trade->symbol }}</h1>
                    <x-badge :type="$trade->direction" />
                    <x-badge :type="$trade->status" />
                </div>
                <p class="text-xs text-text-secondary font-mono mt-0.5">
                    {{ $trade->account->name }} • {{ $trade->entry_at->format('d M Y, H:i') }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('trades.edit', $trade) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-surface-hover hover:bg-border-subtle text-text-primary rounded-lg text-xs font-mono font-semibold border border-border-subtle transition">
                <x-app-icon name="edit" class="w-3.5 h-3.5" />
                <span>Edit</span>
            </a>

            <button 
                type="button" 
                onclick="handleDeleteTrade()" 
                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-trade-loss-bg hover:bg-trade-loss text-trade-loss hover:text-white rounded-lg text-xs font-mono font-semibold border border-trade-loss/20 transition cursor-pointer"
            >
                <x-app-icon name="trash-2" class="w-3.5 h-3.5" />
                <span>Delete</span>
            </button>

            <form id="delete-trade-form" action="{{ route('trades.destroy', $trade) }}" method="POST" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <!-- PnL & Financial Summary Bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        
        <!-- Net PnL -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <span class="text-[10px] font-mono uppercase text-text-muted">Net P&L</span>
            <div class="font-mono text-2xl font-bold mt-1 {{ $trade->net_pnl > 0 ? 'text-trade-profit' : ($trade->net_pnl < 0 ? 'text-trade-loss' : 'text-trade-neutral') }} tabular-nums">
                {{ $trade->net_pnl !== null ? ($trade->net_pnl >= 0 ? '+' : '') . '$' . number_format($trade->net_pnl, 2) : 'Open Trade' }}
            </div>
            @if($trade->roi_percentage !== null)
                <span class="text-[11px] font-mono {{ $trade->roi_percentage >= 0 ? 'text-trade-profit' : 'text-trade-loss' }}">
                    ROI: {{ $trade->roi_percentage >= 0 ? '+' : '' }}{{ $trade->roi_percentage }}%
                </span>
            @endif
        </div>

        <!-- Risk:Reward -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <span class="text-[10px] font-mono uppercase text-text-muted">Risk : Reward</span>
            <div class="font-mono text-2xl font-bold mt-1 text-text-primary tabular-nums">
                {{ $trade->actual_rr ? $trade->actual_rr . 'R' : ($trade->planned_rr ? $trade->planned_rr . 'R (P)' : '—') }}
            </div>
            <span class="text-[11px] font-mono text-text-muted">
                Planned: {{ $trade->planned_rr ? $trade->planned_rr . 'R' : 'N/A' }}
            </span>
        </div>

        <!-- Quantity / Size -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <span class="text-[10px] font-mono uppercase text-text-muted">Position Size</span>
            <div class="font-mono text-2xl font-bold mt-1 text-text-primary tabular-nums">
                {{ number_format($trade->quantity, 2) }}
            </div>
            <span class="text-[11px] font-mono text-text-muted">
                Fees: ${{ number_format($trade->fees, 2) }}
            </span>
        </div>

        <!-- Strategy -->
        <div class="bg-surface border border-border-subtle rounded-xl p-4">
            <span class="text-[10px] font-mono uppercase text-text-muted">Strategy Model</span>
            <div class="font-mono text-base font-bold mt-1 text-text-primary truncate">
                {{ $trade->strategy ? $trade->strategy->name : 'Discretionary' }}
            </div>
            <div class="flex flex-wrap gap-1 mt-1">
                @foreach($trade->tags as $tag)
                    <span class="text-[9px] font-mono px-1 rounded bg-surface-hover text-text-secondary border border-border-subtle">
                        {{ $tag->name }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Execution Details Grid -->
    <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4 font-mono text-xs">
        <h3 class="text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
            <x-app-icon name="activity" class="w-4 h-4 text-accent" />
            <span>Execution Milestones</span>
        </h3>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-3 bg-surface-elevated rounded-lg">
            <div>
                <span class="text-text-muted text-[10px] uppercase">Entry Price</span>
                <div class="font-bold text-text-primary tabular-nums mt-0.5">${{ number_format($trade->entry_price, 2) }}</div>
                <div class="text-[10px] text-text-muted">{{ $trade->entry_at->format('d/m/y H:i') }}</div>
            </div>

            <div>
                <span class="text-text-muted text-[10px] uppercase">Exit Price</span>
                <div class="font-bold text-text-primary tabular-nums mt-0.5">{{ $trade->exit_price ? '$' . number_format($trade->exit_price, 2) : 'Open Position' }}</div>
                <div class="text-[10px] text-text-muted">{{ $trade->exit_at ? $trade->exit_at->format('d/m/y H:i') : 'Active' }}</div>
            </div>

            <div>
                <span class="text-text-muted text-[10px] uppercase">Stop Loss (SL)</span>
                <div class="font-bold text-trade-loss tabular-nums mt-0.5">{{ $trade->stop_loss ? '$' . number_format($trade->stop_loss, 2) : 'None' }}</div>
            </div>

            <div>
                <span class="text-text-muted text-[10px] uppercase">Take Profit (TP)</span>
                <div class="font-bold text-trade-profit tabular-nums mt-0.5">{{ $trade->take_profit ? '$' . number_format($trade->take_profit, 2) : 'None' }}</div>
            </div>
        </div>
    </div>

    <!-- Qualitative Notes & Reflection -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        
        <!-- Trade Reasoning -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-2">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
                <x-app-icon name="book-open" class="w-3.5 h-3.5 text-accent" />
                <span>Trade Reasoning</span>
            </h3>
            <p class="text-xs text-text-secondary leading-relaxed font-sans whitespace-pre-wrap">
                {{ $trade->notes ?: 'No trade setup notes provided.' }}
            </p>
        </div>

        <!-- Lessons Learned -->
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-2">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
                <x-app-icon name="target" class="w-3.5 h-3.5 text-trade-profit" />
                <span>Lessons Learned & Review</span>
            </h3>
            <p class="text-xs text-text-secondary leading-relaxed font-sans whitespace-pre-wrap">
                {{ $trade->lesson_learned ?: 'No execution reflections provided.' }}
            </p>
        </div>
    </div>

    <!-- Chart Screenshots (Before / After) -->
    @if($trade->attachments->count() > 0)
        <div class="bg-surface border border-border-subtle rounded-xl p-5 space-y-4">
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary flex items-center gap-1.5">
                <x-app-icon name="image" class="w-4 h-4 text-accent" />
                <span>Visual Chart Attachments</span>
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($trade->attachments as $att)
                    <div class="space-y-1.5 border border-border-subtle rounded-lg p-2 bg-surface-elevated">
                        <div class="flex items-center justify-between text-xs font-mono text-text-secondary">
                            <span class="font-semibold uppercase text-[10px]">{{ str_replace('_', ' ', $att->stage) }}</span>
                            <span class="text-[10px] text-text-muted">{{ $att->caption }}</span>
                        </div>
                        <div class="rounded-md overflow-hidden border border-border-subtle bg-black/40">
                            <img src="{{ $att->url }}" alt="{{ $att->caption }}" class="w-full h-auto object-cover max-h-96">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
    function handleDeleteTrade() {
        if (window.Alert) {
            window.Alert.confirmDelete(
                'Hapus Jurnal Trade {{ $trade->symbol }}?', 
                'Data transaksi dan riwayat screenshot akan dihapus secara permanen.'
            ).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('delete-trade-form').submit();
                }
            });
        } else {
            if (confirm('Yakin ingin menghapus trade ini?')) {
                document.getElementById('delete-trade-form').submit();
            }
        }
    }
</script>
@endpush
@endsection
