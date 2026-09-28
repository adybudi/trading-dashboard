<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trade extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'strategy_id',
        'symbol',
        'direction',
        'status',
        'entry_at',
        'entry_price',
        'exit_at',
        'exit_price',
        'quantity',
        'stop_loss',
        'take_profit',
        'fees',
        'gross_pnl',
        'net_pnl',
        'roi_percentage',
        'planned_rr',
        'actual_rr',
        'notes',
        'lesson_learned',
    ];

    protected function casts(): array
    {
        return [
            'entry_at' => 'datetime',
            'exit_at' => 'datetime',
            'entry_price' => 'decimal:8',
            'exit_price' => 'decimal:8',
            'quantity' => 'decimal:8',
            'stop_loss' => 'decimal:8',
            'take_profit' => 'decimal:8',
            'fees' => 'decimal:2',
            'gross_pnl' => 'decimal:2',
            'net_pnl' => 'decimal:2',
            'roi_percentage' => 'decimal:4',
            'planned_rr' => 'decimal:2',
            'actual_rr' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(Strategy::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TradeAttachment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'trade_tag');
    }

    public function isWinning(): bool
    {
        return $this->status === 'closed' && (float) $this->net_pnl > 0;
    }

    public function isLosing(): bool
    {
        return $this->status === 'closed' && (float) $this->net_pnl < 0;
    }

    public function isBreakEven(): bool
    {
        return $this->status === 'closed' && (float) $this->net_pnl == 0;
    }
}
