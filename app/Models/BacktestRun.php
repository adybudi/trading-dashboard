<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'symbol',
        'timeframe',
        'strategy_mode',
        'target_min_pct',
        'target_max_pct',
        'fee_pct',
        'slippage_pct',
        'candle_count',
        'initial_capital',
        'allocation_pct',
        'final_balance',
        'net_profit_amount',
        'net_profit_percent',
        'total_trades',
        'winning_trades',
        'losing_trades',
        'win_rate',
        'profit_factor',
        'max_drawdown_percent',
        'equity_curve',
        'trades_log',
    ];

    protected $casts = [
        'target_min_pct' => 'float',
        'target_max_pct' => 'float',
        'fee_pct' => 'float',
        'slippage_pct' => 'float',
        'candle_count' => 'integer',
        'initial_capital' => 'float',
        'allocation_pct' => 'float',
        'final_balance' => 'float',
        'net_profit_amount' => 'float',
        'net_profit_percent' => 'float',
        'total_trades' => 'integer',
        'winning_trades' => 'integer',
        'losing_trades' => 'integer',
        'win_rate' => 'float',
        'profit_factor' => 'float',
        'max_drawdown_percent' => 'float',
        'equity_curve' => 'array',
        'trades_log' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
