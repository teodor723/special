<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCredit extends Model
{
    protected $table = 'users_credits';
    public $timestamps = false;

    protected $fillable = [
        'uid',
        'credits',
        'reason',
        'time',
    ];

    protected $casts = [
        'credits' => 'integer',
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }
}

