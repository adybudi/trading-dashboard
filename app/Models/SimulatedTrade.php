<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulatedTrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'simulation_account_id',
        'mode',
        'trading_type',
        'side',
        'leverage',
        'symbol',
        'timeframe',
        'status',
        'initial_capital',
        'allocated_amount',
        'margin_amount',
        'funding_paid',
        'entry_price',
        'exit_price',
        'take_profit_1',
        'take_profit_2',
        'stop_loss',
        'liquidation_price',
        'pnl_amount',
        'pnl_percent',
        'entry_time',
        'last_funding_at',
        'exit_time',
        'notes',
    ];

    protected $casts = [
        'leverage' => 'integer',
        'initial_capital' => 'float',
        'allocated_amount' => 'float',
        'margin_amount' => 'float',
        'funding_paid' => 'float',
        'entry_price' => 'float',
        'exit_price' => 'float',
        'take_profit_1' => 'float',
        'take_profit_2' => 'float',
        'stop_loss' => 'float',
        'liquidation_price' => 'float',
        'pnl_amount' => 'float',
        'pnl_percent' => 'float',
        'entry_time' => 'datetime',
        'last_funding_at' => 'datetime',
        'exit_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function simulationAccount(): BelongsTo
    {
        return $this->belongsTo(SimulationAccount::class);
    }
}
