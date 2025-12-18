<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    protected $table = 'chat';
    public $timestamps = false;

    protected $fillable = [
        's_id',
        'r_id',
        'message',
        'time',
        'seen',
        'notification',
        'photo',
        'gif',
        'gift',
        'story',
        'credits',
        'fake',
        'online_day',
        'access',
        'translated',
        'translated_text',
    ];

    protected $casts = [
        'time' => 'integer',
        'seen' => 'integer',
        'notification' => 'integer',
        'photo' => 'integer',
        'gif' => 'boolean',
        'gift' => 'boolean',
        'story' => 'integer',
        'credits' => 'integer',
        'fake' => 'boolean',
        'access' => 'integer',
        'translated' => 'boolean',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 's_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'r_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('seen', 0);
    }

    public function scopeNotDeleted($query)
    {
        return $query->where('seen', '!=', 2)->where('notification', '!=', 2);
    }

    public function scopeConversation($query, $userId1, $userId2)
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where('s_id', $userId1)->where('r_id', $userId2);
        })->orWhere(function ($q) use ($userId1, $userId2) {
            $q->where('s_id', $userId2)->where('r_id', $userId1);
        });
    }
}

