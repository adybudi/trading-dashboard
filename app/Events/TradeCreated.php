<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Trade $trade)
    {
        $this->trade->load(['account', 'strategy', 'tags']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->trade->account->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'trade.created',
            'trade' => $this->trade,
            'message' => "New trade {$this->trade->symbol} ({$this->trade->direction}) recorded.",
        ];
    }
}
