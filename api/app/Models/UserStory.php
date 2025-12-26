<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStory extends Model
{
    protected $table = 'users_story';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'storyType',
        'story',
        'storyTime',
        'seen',
        'deleted',
        'visible',
        'rekognition',
        'story_src_hls',
        'lat',
        'lng',
    ];

    protected $casts = [
        'storyTime' => 'integer',
        'deleted' => 'boolean',
        'visible' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function scopeActive($query)
    {
        $expiryTime = now()->subDay()->timestamp;
        return $query->where('deleted', 0)
            ->where('visible', 1)
            ->where('storyTime', '>', $expiryTime);
    }

    public function scopeVisible($query)
    {
        return $query->where('visible', 1);
    }
}

