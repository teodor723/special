<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhoto extends Model
{
    protected $table = 'users_photos';
    public $timestamps = false;

    protected $fillable = [
        'u_id',
        'photo',
        'thumb',
        'profile',
        'approved',
        'video',
        'fake',
        'time',
        'ig_id',
        'request_id',
        'media_id',
        'status',
    ];

    protected $casts = [
        'profile' => 'boolean',
        'approved' => 'boolean',
        'video' => 'boolean',
        'fake' => 'boolean',
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'u_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', 1);
    }

    public function scopeProfile($query)
    {
        return $query->where('profile', 1);
    }

    public function scopeVideos($query)
    {
        return $query->where('video', 1);
    }

    public function scopePhotos($query)
    {
        return $query->where('video', 0);
    }
}

