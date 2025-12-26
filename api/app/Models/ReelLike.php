<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReelLike extends Model
{
    protected $table = 'reels_likes';
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

