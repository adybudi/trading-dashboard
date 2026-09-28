<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountBalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Account $account)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->account->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'account.balance_updated',
            'account_id' => $this->account->id,
            'current_balance' => (float) $this->account->current_balance,
            'message' => "Account {$this->account->name} balance updated to \${$this->account->current_balance}",
        ];
    }
}
