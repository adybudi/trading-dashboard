@props(['data' => []])

@php
$days = $data['days'] ?? [];
$monthName = $data['month_name'] ?? date('F Y');
// Days of the week headers
$weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Calculate offset for the first day of the month (1 = Mon, ..., 7 = Sun)
$firstDayOffset = count($days) > 0 ? ($days[0]['day_of_week'] - 1) : 0;
@endphp

<div class="bg-surface border border-border-subtle rounded-xl p-5">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <x-app-icon name="calendar" class="w-4 h-4 text-accent" />
            <h3 class="font-mono text-xs font-bold uppercase tracking-wider text-text-primary">Performance Calendar</h3>
        </div>
        <span class="font-mono text-xs text-text-secondary font-medium">{{ $monthName }}</span>
    </div>

    <!-- Weekday Header -->
    <div class="grid grid-cols-7 gap-1.5 mb-1.5 text-center">
        @foreach($weekdays as $dayLabel)
            <div class="font-mono text-[10px] font-medium text-text-muted uppercase tracking-wider py-1">
                {{ $dayLabel }}
            </div>
        @endforeach
    </div>

    <!-- Days Grid -->
    <div class="grid grid-cols-7 gap-1.5">
        {{-- Empty slots before the 1st day of month --}}
        @for($i = 0; $i < $firstDayOffset; $i++)
            <div class="aspect-square rounded-lg bg-surface-hover/30 border border-border-subtle/30"></div>
        @endfor

        {{-- Days of the month --}}
        @foreach($days as $day)
            @php
                $pnl = (float) $day['net_pnl'];
                $count = (int) $day['trades_count'];
                
                $bgClass = 'bg-surface-elevated border-border-subtle hover:border-border-muted';
                $textClass = 'text-text-muted';
                $pnlClass = 'text-text-muted';

                if ($count > 0) {
                    if ($pnl > 0) {
                        $bgClass = 'bg-trade-profit-bg border-trade-profit/30 hover:border-trade-profit/60';
                        $textClass = 'text-trade-profit font-semibold';
                        $pnlClass = 'text-trade-profit';
                    } elseif ($pnl < 0) {
                        $bgClass = 'bg-trade-loss-bg border-trade-loss/30 hover:border-trade-loss/60';
                        $textClass = 'text-trade-loss font-semibold';
                        $pnlClass = 'text-trade-loss';
                    } else {
                        $bgClass = 'bg-surface-hover border-trade-neutral/30';
                        $textClass = 'text-trade-neutral font-semibold';
                        $pnlClass = 'text-trade-neutral';
                    }
                }
            @endphp

            <div 
                class="group relative aspect-square rounded-lg border p-1.5 flex flex-col justify-between transition-all duration-150 cursor-pointer {{ $bgClass }}"
            >
                <div class="flex items-center justify-between">
                    <span class="font-mono text-[10px] {{ $textClass }}">{{ $day['day'] }}</span>
                    @if($count > 0)
                        <span class="font-mono text-[9px] text-text-muted group-hover:text-text-secondary">{{ $count }}T</span>
                    @endif
                </div>

                @if($count > 0)
                    <div class="font-mono text-[10px] font-bold tracking-tight text-right {{ $pnlClass }} tabular-nums truncate">
                        {{ $pnl >= 0 ? '+' : '' }}${{ number_format(abs($pnl), 0) }}
                    </div>
                @else
                    <div class="h-2"></div>
                @endif

                <!-- Tooltip on Hover -->
                @if($count > 0)
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:flex flex-col gap-1 w-36 p-2 rounded-lg bg-surface-elevated border border-border-muted shadow-xl text-left z-30 pointer-events-none">
                        <div class="font-mono text-[10px] text-text-secondary border-b border-border-subtle pb-1 font-semibold">
                            {{ date('d M Y', strtotime($day['date'])) }}
                        </div>
                        <div class="flex justify-between font-mono text-[10px]">
                            <span class="text-text-muted">Net PnL:</span>
                            <span class="font-bold {{ $pnl >= 0 ? 'text-trade-profit' : 'text-trade-loss' }}">
                                {{ $pnl >= 0 ? '+' : '' }}${{ number_format($pnl, 2) }}
                            </span>
                        </div>
                        <div class="flex justify-between font-mono text-[10px]">
                            <span class="text-text-muted">Trades:</span>
                            <span class="text-text-primary">{{ $count }} ({{ $day['win_count'] }}W / {{ $day['loss_count'] }}L)</span>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
