<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Broadcast a single coin's live ticker tick, pushed from the
 * market:stream daemon (App\Console\Commands\StreamMarketDataCommand) as it
 * receives updates from Gate.io's public WebSocket feed. Public channel —
 * spot ticker prices are public market data, no per-user auth needed.
 */
class TickerPriceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public array $coin)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('market-ticker')];
    }

    public function broadcastAs(): string
    {
        return 'ticker.updated';
    }

    public function broadcastWith(): array
    {
        return $this->coin;
    }
}
