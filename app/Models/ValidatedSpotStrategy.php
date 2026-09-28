<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ValidatedSpotStrategy extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'timeframe',
        'calibrated_win_rate',
        'profit_consistency_score',
        'max_win_streak',
        'net_profit_percent',
        'profit_factor',
        'calibrated_weights',
        'golden_entry_price',
        'take_profit_1',
        'take_profit_2',
        'stop_loss',
        'repeatability_status',
        'confluences',
        'last_calibrated_at',
    ];

    protected $casts = [
        'calibrated_win_rate' => 'float',
        'profit_consistency_score' => 'float',
        'max_win_streak' => 'integer',
        'net_profit_percent' => 'float',
        'profit_factor' => 'float',
        'calibrated_weights' => 'array',
        'golden_entry_price' => 'float',
        'take_profit_1' => 'float',
        'take_profit_2' => 'float',
        'stop_loss' => 'float',
        'confluences' => 'array',
        'last_calibrated_at' => 'datetime',
    ];
}
