@props([
    'type' => 'default', // 'long', 'short', 'win', 'loss', 'be', 'open', 'closed', 'cancelled'
    'label' => null,
])

@php
$config = match($type) {
    'long' => [
        'bg' => 'bg-trade-profit-bg text-trade-profit border-trade-profit/20',
        'icon' => 'arrow-up-right',
        'text' => $label ?? 'LONG'
    ],
    'short' => [
        'bg' => 'bg-trade-loss-bg text-trade-loss border-trade-loss/20',
        'icon' => 'arrow-down-right',
        'text' => $label ?? 'SHORT'
    ],
    'win' => [
        'bg' => 'bg-trade-profit-bg text-trade-profit border-trade-profit/20',
        'icon' => 'trending-up',
        'text' => $label ?? 'WIN'
    ],
    'loss' => [
        'bg' => 'bg-trade-loss-bg text-trade-loss border-trade-loss/20',
        'icon' => 'trending-down',
        'text' => $label ?? 'LOSS'
    ],
    'be' => [
        'bg' => 'bg-trade-neutral-bg text-trade-neutral border-trade-neutral/20',
        'icon' => 'activity',
        'text' => $label ?? 'B/E'
    ],
    'open' => [
        'bg' => 'bg-accent/10 text-accent border-accent/20',
        'icon' => 'clock',
        'text' => $label ?? 'OPEN'
    ],
    'closed' => [
        'bg' => 'bg-surface-hover text-text-secondary border-border-subtle',
        'icon' => 'check-circle-2',
        'text' => $label ?? 'CLOSED'
    ],
    default => [
        'bg' => 'bg-surface-hover text-text-secondary border-border-subtle',
        'icon' => null,
        'text' => $label ?? ''
    ]
};
@endphp

<span class="inline-flex items-center gap-1 font-mono text-[10px] font-semibold tracking-wider uppercase px-2 py-0.5 rounded border {{ $config['bg'] }}">
    @if($config['icon'])
        <x-app-icon :name="$config['icon']" class="w-3 h-3" />
    @endif
    <span>{{ $config['text'] }}</span>
</span>
