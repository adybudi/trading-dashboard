<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TradeAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_id',
        'file_path',
        'stage',
        'caption',
    ];

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }
}
