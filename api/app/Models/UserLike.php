<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLike extends Model
{
    protected $table = 'users_likes';
    public $timestamps = false;

    const UNLIKE = 0;
    const LIKE = 1;
    const SUPER_LIKE = 3;

    protected $fillable = [
        'u1',
        'u2',
        'love',
        'time',
        'notification',
    ];

    protected $casts = [
        'love' => 'integer',
        'time' => 'integer',
        'notification' => 'boolean',
    ];

    public function liker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'u1');
    }

    public function liked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'u2');
    }

    public function scopeLikes($query)
    {
        return $query->where('love', self::LIKE);
    }

    public function scopeSuperLikes($query)
    {
        return $query->where('love', self::SUPER_LIKE);
    }

    public function scopeUnread($query)
    {
        return $query->where('notification', 0);
    }
}

