<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpotSignal extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'timeframe',
        'grade',
        'score',
        'current_price',
        'entry_price',
        'take_profit_1',
        'take_profit_2',
        'stop_loss',
        'potential_gain_percent',
        'risk_percent',
        'confluence_summary',
        'metrics_data',
        'status',
    ];

    protected $casts = [
        'current_price' => 'decimal:8',
        'entry_price' => 'decimal:8',
        'take_profit_1' => 'decimal:8',
        'take_profit_2' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'potential_gain_percent' => 'decimal:2',
        'risk_percent' => 'decimal:2',
        'score' => 'integer',
        'metrics_data' => 'array',
    ];

    public function getIsGradeAPlusAttribute(): bool
    {
        return $this->grade === 'A+';
    }

    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->entry_price, $this->entry_price < 1 ? 4 : 2);
    }
}
