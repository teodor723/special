<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVisit extends Model
{
    protected $table = 'users_visits';
    public $timestamps = false;

    protected $fillable = [
        'u1',
        'u2',
        'timeago',
        'notification',
    ];

    protected $casts = [
        'timeago' => 'integer',
        'notification' => 'boolean',
    ];

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'u1');
    }

    public function visited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'u2');
    }

    public function scopeUnread($query)
    {
        return $query->where('notification', 0);
    }
}

