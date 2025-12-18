<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reel extends Model
{
    protected $table = 'reels';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'gender',
        'reel_price',
        'reel_src',
        'reel_src_hls',
        'reel_meta',
        'time',
        'visible',
        'rekognition',
    ];

    protected $casts = [
        'reel_price' => 'integer',
        'time' => 'integer',
        'visible' => 'integer',
        'gender' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ReelLike::class, 'reel');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ReelView::class, 'reel');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ReelPurchase::class, 'reel');
    }

    public function scopeApproved($query)
    {
        return $query->where('visible', 1);
    }

    public function scopeFree($query)
    {
        return $query->where('reel_price', 0);
    }

    public function scopePremium($query)
    {
        return $query->where('reel_price', '>', 0);
    }

    public function scopeTrending($query)
    {
        return $query->withCount('likes', 'views')
            ->orderBy('likes_count', 'desc')
            ->orderBy('views_count', 'desc');
    }

    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('uid', $userId)->exists();
    }

    public function isPurchasedBy(int $userId): bool
    {
        return $this->purchases()->where('uid', $userId)->exists();
    }
}

