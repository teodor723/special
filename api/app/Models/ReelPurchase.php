<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelPurchase extends Model
{
    protected $table = 'users_reels_purchases';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'rid',
        'time',
    ];

    protected $casts = [
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class, 'id', 'rid');
    }
}

