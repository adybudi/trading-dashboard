<?php

namespace App\Events;

use App\Models\SpotSignal;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpotSignalDetected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SpotSignal $signal;
    public string $message;

    /**
     * Create a new event instance.
     */
    public function __construct(SpotSignal $signal)
    {
        $this->signal = $signal;
        $this->message = "NEW SPOT BUY SIGNAL: {$signal->symbol} [Grade {$signal->grade}] Entry @ {$signal->formatted_price} (Target +10% - +20%)";
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('spot-signals'),
        ];
    }
}
