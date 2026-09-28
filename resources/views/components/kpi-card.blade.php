@props([
    'title' => '',
    'value' => '',
    'subtext' => null,
    'icon' => null,
    'badge' => null,
    'type' => 'neutral' // 'profit', 'loss', 'accent', 'neutral'
])

@php
$valueColor = match($type) {
    'profit' => 'text-trade-profit',
    'loss' => 'text-trade-loss',
    'accent' => 'text-accent',
    default => 'text-text-primary'
};
$iconColor = match($type) {
    'profit' => 'text-trade-profit',
    'loss' => 'text-trade-loss',
    'accent' => 'text-accent',
    default => 'text-text-secondary'
};
$badgeColor = match($type) {
    'profit' => 'bg-trade-profit-bg text-trade-profit border border-trade-profit/20',
    'loss' => 'bg-trade-loss-bg text-trade-loss border border-trade-loss/20',
    'accent' => 'bg-accent/10 text-accent border border-accent/20',
    default => 'bg-surface-hover text-text-secondary border border-border-subtle'
};
@endphp

<div class="bg-surface border border-border-subtle rounded-xl p-4 transition duration-200 hover:border-border-muted hover:shadow-sm">
    <div class="flex items-center justify-between text-text-secondary text-[11px] font-medium uppercase tracking-wider mb-2">
        <span>{{ $title }}</span>
        @if($icon)
            <x-app-icon :name="$icon" class="w-4 h-4 {{ $iconColor }}" />
        @endif
    </div>

    <div class="flex items-baseline gap-2">
        <span class="font-mono text-xl sm:text-2xl font-bold tracking-tight {{ $valueColor }} tabular-nums">
            {{ $value }}
        </span>
        @if($badge)
            <span class="font-mono text-[11px] px-1.5 py-0.5 rounded {{ $badgeColor }}">
                {{ $badge }}
            </span>
        @endif
    </div>

    @if($subtext)
        <p class="text-text-muted text-[11px] mt-2 font-mono truncate">
            {{ $subtext }}
        </p>
    @endif
</div>
