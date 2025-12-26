<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelView extends Model
{
    protected $table = 'users_reels_played';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'rid',
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

