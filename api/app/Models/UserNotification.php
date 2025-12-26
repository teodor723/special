<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'users_notifications';
    public $timestamps = false;
    protected $primaryKey = 'uid';
    public $incrementing = false;

    protected $fillable = [
        'uid',
        'fan',
        'match_me',
        'message',
        'visit',
        'near_me',
    ];

    protected $casts = [
        'fan' => 'string',
        'match_me' => 'string',
        'message' => 'string',
        'visit' => 'string',
        'near_me' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function getNotificationSettings(string $type): array
    {
        // Map 'match' to 'match_me' for backward compatibility
        $column = ($type === 'match') ? 'match_me' : $type;
        $setting = $this->$column ?? '1,1,1';
        $parts = explode(',', $setting);
        
        return [
            'inapp' => (int) ($parts[0] ?? 1),
            'email' => (int) ($parts[1] ?? 1),
            'push' => (int) ($parts[2] ?? 1),
        ];
    }
}

