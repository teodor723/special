<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelPurchase extends Model
{
    protected $table = 'reels_purchases';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'reel',
        'amount',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class, 'reel');
    }
}

