<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'virtual_balance',
        'initial_balance',
        'total_trades',
        'winning_trades',
        'losing_trades',
    ];

    protected $casts = [
        'virtual_balance' => 'float',
        'initial_balance' => 'float',
        'total_trades' => 'integer',
        'winning_trades' => 'integer',
        'losing_trades' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function simulatedTrades(): HasMany
    {
        return $this->hasMany(SimulatedTrade::class);
    }

    public function getWinRateAttribute(): float
    {
        if ($this->total_trades === 0) {
            return 0.0;
        }

        return round(($this->winning_trades / $this->total_trades) * 100, 1);
    }

    public function getNetProfitAmountAttribute(): float
    {
        return round($this->virtual_balance - $this->initial_balance, 2);
    }

    public function getNetProfitPercentAttribute(): float
    {
        if ($this->initial_balance <= 0) {
            return 0.0;
        }

        return round((($this->virtual_balance - $this->initial_balance) / $this->initial_balance) * 100, 2);
    }
}
