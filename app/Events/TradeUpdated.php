<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeUpdated implements ShouldBroadcastNow
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
        $isWin = (float) $this->trade->net_pnl > 0;
        $formattedPnl = ($this->trade->net_pnl >= 0 ? '+' : '') . '$' . number_format((float) $this->trade->net_pnl, 2);

        return [
            'type' => 'trade.updated',
            'trade' => $this->trade,
            'is_win' => $isWin,
            'message' => "Trade {$this->trade->symbol} updated: PnL {$formattedPnl}",
        ];
    }
}
