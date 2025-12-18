<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'users_notifications';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'fan',
        'match',
        'message',
        'visit',
        'gift',
    ];

    protected $casts = [
        'fan' => 'string',
        'match' => 'string',
        'message' => 'string',
        'visit' => 'string',
        'gift' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function getNotificationSettings(string $type): array
    {
        $setting = $this->$type ?? '1,1,1';
        $parts = explode(',', $setting);
        
        return [
            'inapp' => (int) ($parts[0] ?? 1),
            'email' => (int) ($parts[1] ?? 1),
            'push' => (int) ($parts[2] ?? 1),
        ];
    }
}

