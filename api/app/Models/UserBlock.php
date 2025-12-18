<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    protected $table = 'users_blocks';
    public $timestamps = false;

    protected $fillable = [
        'uid1',
        'uid2',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid1');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uid2');
    }
}

